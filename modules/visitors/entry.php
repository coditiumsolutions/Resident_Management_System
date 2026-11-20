<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Visitor Entry / Exit';
$currentNav = 'visitors-entry';
$errors = [];

$preselectedVisitorId = filter_input(INPUT_GET, 'visitor_id', FILTER_VALIDATE_INT) ?: 0;
$visitors = db()->query('SELECT VisitorID, VisitorName, CNIC, ContactNumber FROM Visitors ORDER BY VisitorName')->fetchAll();
$hosts = visitor_host_options(db());

$form = [
    'VisitorID' => $preselectedVisitorId ?: '',
    'HostKey' => '',
    'Purpose' => '',
    'CheckInTime' => date('Y-m-d\TH:i'),
    'Remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'checkin');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($action === 'checkout') {
        $visitId = (int) ($_POST['VisitID'] ?? 0);
        $checkOut = trim((string) ($_POST['CheckOutTime'] ?? ''));
        if ($visitId <= 0) {
            $errors[] = 'Invalid visit selected.';
        }
        if ($checkOut === '') {
            $errors[] = 'Check-out time is required.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    "UPDATE Visits
                     SET CheckOutTime = :check_out, VisitStatus = 'Checked Out'
                     WHERE VisitID = :id AND VisitStatus = 'Checked In'"
                )->execute([
                    'check_out' => date('Y-m-d H:i:s', strtotime($checkOut)),
                    'id' => $visitId,
                ]);
                flash_set('success', 'Visitor checked out successfully.');
                redirect('modules/visitors/entry.php');
            } catch (Throwable $e) {
                error_log('Checkout failed: ' . $e->getMessage());
                $errors[] = 'Could not check out visitor.';
            }
        }
    } else {
        foreach (['VisitorID', 'HostKey', 'Purpose', 'CheckInTime', 'Remarks'] as $key) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        $visitorId = (int) $form['VisitorID'];
        if ($visitorId <= 0) {
            $errors[] = 'Please select a visitor.';
        }
        if ($form['HostKey'] === '' || !str_contains($form['HostKey'], ':')) {
            $errors[] = 'Please select a host unit.';
        }
        if ($form['CheckInTime'] === '') {
            $errors[] = 'Check-in time is required.';
        }

        $unitId = 0;
        $hostId = 0;
        if ($form['HostKey'] !== '' && str_contains($form['HostKey'], ':')) {
            [$unitId, $hostId] = array_map('intval', explode(':', $form['HostKey'], 2));
        }
        if ($unitId <= 0 || $hostId <= 0) {
            $errors[] = 'Invalid host/unit selection.';
        }

        // Prevent duplicate active check-in for same visitor
        if (!$errors) {
            $active = db()->prepare("SELECT VisitID FROM Visits WHERE VisitorID = :id AND VisitStatus = 'Checked In' LIMIT 1");
            $active->execute(['id' => $visitorId]);
            if ($active->fetch()) {
                $errors[] = 'This visitor is already checked in.';
            }
        }

        if (!$errors) {
            try {
                $user = current_user();
                $checkInTs = date('Y-m-d H:i:s', strtotime($form['CheckInTime']));
                db()->prepare(
                    "INSERT INTO Visits
                        (VisitorID, UnitID, HostPartyID, Purpose, VisitDate, CheckInTime, CheckOutTime, VisitStatus, ApprovedByUserID, Remarks)
                     VALUES
                        (:visitor_id, :unit_id, :host_id, :purpose, :visit_date, :check_in, NULL, 'Checked In', :approved_by, :remarks)"
                )->execute([
                    'visitor_id' => $visitorId,
                    'unit_id' => $unitId,
                    'host_id' => $hostId,
                    'purpose' => $form['Purpose'] !== '' ? $form['Purpose'] : null,
                    'visit_date' => date('Y-m-d', strtotime($checkInTs)),
                    'check_in' => $checkInTs,
                    'approved_by' => $user['UserID'] ?? null,
                    'remarks' => $form['Remarks'] !== '' ? $form['Remarks'] : null,
                ]);
                flash_set('success', 'Visitor checked in successfully.');
                redirect('modules/visitors/entry.php');
            } catch (Throwable $e) {
                error_log('Check-in failed: ' . $e->getMessage());
                $errors[] = 'Could not check in visitor.';
            }
        }
    }
}

$activeVisits = db()->query(
    "SELECT vis.*, v.VisitorName, v.ContactNumber, v.CNIC, v.VehicleNumber,
            u.UnitNumber, b.BlockName, p.PropertyName, host.FullName AS HostName
     FROM Visits vis
     INNER JOIN Visitors v ON v.VisitorID = vis.VisitorID
     INNER JOIN Units u ON u.UnitID = vis.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     INNER JOIN Parties host ON host.PartyID = vis.HostPartyID
     WHERE vis.VisitStatus = 'Checked In'
     ORDER BY vis.CheckInTime DESC"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Visitor Entry / Exit</h1>
        <p class="text-muted mb-0">Check visitors in and out of Ready Plaza units</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/visitors/register.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-person-plus me-1"></i> Register</a>
        <a href="<?= e(base_url('modules/visitors/history.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i> History</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-mini"><span>Currently Inside</span><strong><?= e((string) count($activeVisits)) ?></strong></div></div>
    <div class="col-md-4"><div class="stat-mini"><span>Registered Visitors</span><strong><?= e((string) count($visitors)) ?></strong></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Check In Visitor</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if (!$visitors || !$hosts): ?>
                    <div class="alert alert-warning mb-0">
                        <?= !$visitors ? 'Register a visitor first.' : 'No host units available.' ?>
                    </div>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="checkin">
                        <div class="mb-3">
                            <label class="form-label" for="VisitorID">Visitor</label>
                            <select class="form-select" id="VisitorID" name="VisitorID" required>
                                <option value="">Select visitor</option>
                                <?php foreach ($visitors as $visitor): ?>
                                    <option value="<?= (int) $visitor['VisitorID'] ?>" <?= (int) $form['VisitorID'] === (int) $visitor['VisitorID'] ? 'selected' : '' ?>>
                                        <?= e($visitor['VisitorName'] . ($visitor['ContactNumber'] ? ' (' . $visitor['ContactNumber'] . ')' : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="HostKey">Host Unit</label>
                            <select class="form-select" id="HostKey" name="HostKey" required>
                                <option value="">Select host / unit</option>
                                <?php foreach ($hosts as $host): ?>
                                    <?php $key = (int) $host['UnitID'] . ':' . (int) $host['PartyID']; ?>
                                    <option value="<?= e($key) ?>" <?= $form['HostKey'] === $key ? 'selected' : '' ?>>
                                        <?= e(visitor_format_host_label($host)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Purpose">Purpose</label>
                            <input type="text" class="form-control" id="Purpose" name="Purpose" maxlength="200" value="<?= e($form['Purpose']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="CheckInTime">Check-in Time</label>
                            <input type="datetime-local" class="form-control" id="CheckInTime" name="CheckInTime" value="<?= e($form['CheckInTime']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Remarks">Remarks</label>
                            <input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="300" value="<?= e($form['Remarks']) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Check In</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card">
            <div class="card-header">Active Visitors (Checked In)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Unit</th>
                            <th>Host</th>
                            <th>Purpose</th>
                            <th>Check-in</th>
                            <th class="text-end">Exit</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$activeVisits): ?>
                            <tr><td colspan="6" class="empty-state">No visitors currently inside.</td></tr>
                        <?php else: foreach ($activeVisits as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['VisitorName']) ?></strong>
                                    <div class="text-muted small"><?= e($row['ContactNumber'] ?: ($row['CNIC'] ?: '')) ?></div>
                                </td>
                                <td><?= e($row['UnitNumber']) ?></td>
                                <td><?= e($row['HostName']) ?></td>
                                <td><?= e($row['Purpose'] ?: '—') ?></td>
                                <td><?= e(format_datetime($row['CheckInTime'])) ?></td>
                                <td class="text-end">
                                    <form method="post" class="d-inline-flex gap-1 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="checkout">
                                        <input type="hidden" name="VisitID" value="<?= (int) $row['VisitID'] ?>">
                                        <input type="datetime-local" class="form-control form-control-sm" name="CheckOutTime" value="<?= e(date('Y-m-d\TH:i')) ?>" required style="width:190px;">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Check Out"><i class="bi bi-box-arrow-right"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
