<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Resident Report';
$currentNav = 'report-resident';

$type = trim((string) ($_GET['type'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'Active'));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT party.PartyID, party.FullName, party.CNIC, party.Email, party.Status AS PartyStatus,
               oc.OccupancyType, oc.Status AS OccupancyStatus, oc.StartDate, oc.EndDate,
               u.UnitNumber, b.BlockName, p.PropertyName,
               (SELECT ContactNumber FROM PartyContacts pc WHERE pc.PartyID = party.PartyID ORDER BY pc.IsPrimary DESC, pc.ContactID LIMIT 1) AS ContactNumber
        FROM Parties party
        INNER JOIN UnitOccupancies oc ON oc.PartyID = party.PartyID
        INNER JOIN Units u ON u.UnitID = oc.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        WHERE 1=1";
$params = [];

if ($type !== '') {
    $sql .= ' AND oc.OccupancyType = :type';
    $params['type'] = $type;
}
if ($status !== '') {
    $sql .= ' AND oc.Status = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $sql .= ' AND (party.FullName LIKE :q OR party.CNIC LIKE :q2 OR u.UnitNumber LIKE :q3 OR party.Email LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
$sql .= ' ORDER BY party.FullName, u.UnitNumber';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$owners = count(array_filter($rows, static fn ($r) => $r['OccupancyType'] === 'Owner'));
$tenants = count(array_filter($rows, static fn ($r) => $r['OccupancyType'] === 'Tenant'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Resident Report</h1>
        <p class="text-muted mb-0">Owners and tenants by unit occupancy</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Records</span><strong><?= e((string) count($rows)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Owners</span><strong><?= e((string) $owners) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Tenants</span><strong><?= e((string) $tenants) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="type">Occupancy Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All</option>
                    <?php foreach (['Owner', 'Tenant', 'Resident'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $type === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Occupancy Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['Active', 'Ended', 'Pending'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, CNIC, unit, email">
            </div>
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
                    <th>Resident</th>
                    <th>Contact</th>
                    <th>Unit</th>
                    <th>Type</th>
                    <th>Period</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="empty-state">No residents found.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['FullName']) ?></strong>
                            <div class="text-muted small"><?= e($row['CNIC'] ?: '') ?></div>
                        </td>
                        <td>
                            <?= e($row['ContactNumber'] ?: '—') ?>
                            <div class="text-muted small"><?= e($row['Email'] ?: '') ?></div>
                        </td>
                        <td><?= e($row['UnitNumber']) ?><div class="text-muted small"><?= e($row['PropertyName']) ?></div></td>
                        <td><?= e($row['OccupancyType']) ?></td>
                        <td><?= e(format_date($row['StartDate'])) ?><div class="text-muted small"><?= e($row['EndDate'] ? 'to ' . format_date($row['EndDate']) : 'Ongoing') ?></div></td>
                        <td><span class="badge badge-soft <?= e($row['OccupancyStatus'] === 'Active' ? 'resolved' : 'hold') ?>"><?= e($row['OccupancyStatus']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
