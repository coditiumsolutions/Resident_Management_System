<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Ownership History';
$currentNav = 'residents-ownership';

$filter = trim((string) ($_GET['filter'] ?? 'all'));

$sql = "SELECT o.*,
               p.FullName, p.PartyID, p.CNIC,
               u.UnitNumber, b.BlockName, pr.PropertyName,
               CASE
                   WHEN o.EndDate IS NULL OR o.EndDate >= CURDATE() THEN 'Current'
                   ELSE 'Historical'
               END AS OwnershipState
        FROM UnitOwnerships o
        INNER JOIN Parties p ON p.PartyID = o.OwnerPartyID
        INNER JOIN Units u ON u.UnitID = o.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
        WHERE 1=1";

if ($filter === 'current') {
    $sql .= ' AND (o.EndDate IS NULL OR o.EndDate >= CURDATE())';
} elseif ($filter === 'historical') {
    $sql .= ' AND o.EndDate IS NOT NULL AND o.EndDate < CURDATE()';
}

$sql .= ' ORDER BY o.StartDate DESC, o.OwnershipID DESC';
$rows = db()->query($sql)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Ownership History</h1>
        <p class="text-muted mb-0">Current and past unit ownership records</p>
    </div>
    <a href="<?= e(base_url('modules/residents/owner_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Ownership
    </a>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="filter">Show</label>
                <select class="form-select" id="filter" name="filter" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Records</option>
                    <option value="current" <?= $filter === 'current' ? 'selected' : '' ?>>Current Only</option>
                    <option value="historical" <?= $filter === 'historical' ? 'selected' : '' ?>>Historical Only</option>
                </select>
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
                    <th>Owner</th>
                    <th>CNIC</th>
                    <th>Unit</th>
                    <th>Property / Block</th>
                    <th>Share</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>State</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="empty-state">No ownership history found.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['FullName']) ?></strong></td>
                        <td><?= e($row['CNIC'] ?: '—') ?></td>
                        <td><?= e($row['UnitNumber']) ?></td>
                        <td><?= e($row['PropertyName'] . ' / ' . $row['BlockName']) ?></td>
                        <td><?= e(number_format((float) $row['OwnershipShare'], 2)) ?>%</td>
                        <td><?= e(format_date($row['StartDate'])) ?></td>
                        <td><?= e($row['EndDate'] ? format_date($row['EndDate']) : '—') ?></td>
                        <td>
                            <span class="badge badge-soft <?= $row['OwnershipState'] === 'Current' ? 'resolved' : 'hold' ?>">
                                <?= e($row['OwnershipState']) ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/residents/party_view.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/residents/owner_edit.php?id=' . (int) $row['OwnershipID'])) ?>"><i class="bi bi-pencil"></i></a>
                            <?php if ($row['OwnershipState'] === 'Current'): ?>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/residents/owner_end.php?id=' . (int) $row['OwnershipID'])) ?>"><i class="bi bi-x-circle"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
