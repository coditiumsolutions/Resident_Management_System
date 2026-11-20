<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Visitor Report';
$currentNav = 'report-visitor';

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-t')));
$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT vis.*, v.VisitorName, v.ContactNumber, v.CNIC, v.VehicleNumber,
               u.UnitNumber, host.FullName AS HostName, p.PropertyName
        FROM Visits vis
        INNER JOIN Visitors v ON v.VisitorID = vis.VisitorID
        INNER JOIN Units u ON u.UnitID = vis.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        INNER JOIN Parties host ON host.PartyID = vis.HostPartyID
        WHERE vis.VisitDate BETWEEN :from_date AND :to_date";
$params = ['from_date' => $from, 'to_date' => $to];

if ($status !== '') {
    $sql .= ' AND vis.VisitStatus = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $sql .= ' AND (v.VisitorName LIKE :q OR u.UnitNumber LIKE :q2 OR host.FullName LIKE :q3 OR v.VehicleNumber LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
$sql .= ' ORDER BY vis.CheckInTime DESC, vis.VisitID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$checkedIn = count(array_filter($rows, static fn ($r) => $r['VisitStatus'] === 'Checked In'));
$checkedOut = count(array_filter($rows, static fn ($r) => $r['VisitStatus'] === 'Checked Out'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Visitor Report</h1>
        <p class="text-muted mb-0">Visit activity by date range</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Visits</span><strong><?= e((string) count($rows)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Checked In</span><strong><?= e((string) $checkedIn) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Checked Out</span><strong><?= e((string) $checkedOut) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['Checked In', 'Checked Out', 'Expected', 'Cancelled'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="q">Search</label><input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Visitor, unit, host, vehicle"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
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
                    <th>Unit / Host</th>
                    <th>Purpose</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="empty-state">No visitor records for this period.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['VisitorName']) ?></strong>
                            <div class="text-muted small"><?= e($row['ContactNumber'] ?: ($row['VehicleNumber'] ?: '')) ?></div>
                        </td>
                        <td>
                            Unit <?= e($row['UnitNumber']) ?>
                            <div class="text-muted small"><?= e($row['HostName']) ?></div>
                        </td>
                        <td><?= e($row['Purpose'] ?: '—') ?></td>
                        <td><?= e(format_datetime($row['CheckInTime'])) ?></td>
                        <td><?= e($row['CheckOutTime'] ? format_datetime($row['CheckOutTime']) : '—') ?></td>
                        <td><span class="badge badge-soft <?= e(status_badge_class($row['VisitStatus'])) ?>"><?= e($row['VisitStatus']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
