<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Visitor History';
$currentNav = 'visitors-history';

$status = trim((string) ($_GET['status'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT vis.*,
               v.VisitorName, v.ContactNumber, v.CNIC, v.VehicleNumber,
               u.UnitNumber, b.BlockName, p.PropertyName,
               host.FullName AS HostName
        FROM Visits vis
        INNER JOIN Visitors v ON v.VisitorID = vis.VisitorID
        INNER JOIN Units u ON u.UnitID = vis.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        INNER JOIN Parties host ON host.PartyID = vis.HostPartyID
        WHERE 1=1";
$params = [];

if ($status !== '') {
    $sql .= ' AND vis.VisitStatus = :status';
    $params['status'] = $status;
}
if ($from !== '') {
    $sql .= ' AND vis.VisitDate >= :from_date';
    $params['from_date'] = $from;
}
if ($to !== '') {
    $sql .= ' AND vis.VisitDate <= :to_date';
    $params['to_date'] = $to;
}
if ($search !== '') {
    $sql .= ' AND (v.VisitorName LIKE :q OR v.ContactNumber LIKE :q2 OR u.UnitNumber LIKE :q3 OR host.FullName LIKE :q4 OR v.VehicleNumber LIKE :q5)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
    $params['q5'] = $like;
}

$sql .= ' ORDER BY vis.CheckInTime DESC, vis.VisitID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll();

$checkedIn = count(array_filter($visits, static fn ($r) => $r['VisitStatus'] === 'Checked In'));
$checkedOut = count(array_filter($visits, static fn ($r) => $r['VisitStatus'] === 'Checked Out'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Visitor History</h1>
        <p class="text-muted mb-0">Complete entry and exit visit records</p>
    </div>
    <a href="<?= e(base_url('modules/visitors/entry.php')) ?>" class="btn btn-primary">
        <i class="bi bi-door-open me-1"></i> Entry / Exit
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Records</span><strong><?= e((string) count($visits)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Checked In</span><strong><?= e((string) $checkedIn) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Checked Out</span><strong><?= e((string) $checkedOut) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="from">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['Checked In', 'Checked Out', 'Expected', 'Cancelled'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Visitor, unit, host, vehicle">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Contact</th>
                    <th>Unit</th>
                    <th>Host</th>
                    <th>Purpose</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$visits): ?>
                    <tr><td colspan="8" class="empty-state">No visitor history found.</td></tr>
                <?php else: foreach ($visits as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['VisitorName']) ?></strong>
                            <?php if (!empty($row['VehicleNumber'])): ?>
                                <div class="text-muted small"><?= e($row['VehicleNumber']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['ContactNumber'] ?: '—') ?></td>
                        <td><?= e($row['UnitNumber']) ?></td>
                        <td><?= e($row['HostName']) ?></td>
                        <td><?= e($row['Purpose'] ?: '—') ?></td>
                        <td><?= e(format_datetime($row['CheckInTime'])) ?></td>
                        <td><?= e($row['CheckOutTime'] ? format_datetime($row['CheckOutTime']) : '—') ?></td>
                        <td><span class="badge badge-soft <?= e(visitor_status_badge($row['VisitStatus'])) ?>"><?= e($row['VisitStatus']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
