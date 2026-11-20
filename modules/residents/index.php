<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Residents';
$currentNav = 'residents';

$search = trim((string) ($_GET['q'] ?? ''));
$typeFilter = trim((string) ($_GET['type'] ?? ''));

$sql = "SELECT p.PartyID, p.FullName, p.CNIC, p.Email, p.Gender, p.Status, p.CreatedAt,
               (SELECT ContactNumber FROM PartyContacts pc WHERE pc.PartyID = p.PartyID AND pc.IsPrimary = 1 LIMIT 1) AS Mobile,
               EXISTS(
                   SELECT 1 FROM UnitOwnerships o
                   WHERE o.OwnerPartyID = p.PartyID AND (o.EndDate IS NULL OR o.EndDate >= CURDATE())
               ) AS IsOwner,
               EXISTS(
                   SELECT 1 FROM UnitOccupancies oc
                   WHERE oc.PartyID = p.PartyID AND oc.OccupancyType = 'Tenant'
                     AND oc.Status = 'Active' AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
               ) AS IsTenant,
               EXISTS(
                   SELECT 1 FROM UnitOccupancies oc
                   WHERE oc.PartyID = p.PartyID AND oc.OccupancyType IN ('Resident', 'Owner')
                     AND oc.Status = 'Active' AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
               ) AS IsResident
        FROM Parties p
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.FullName LIKE :q OR p.CNIC LIKE :q2 OR p.Email LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql .= ' ORDER BY p.FullName';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$residents = $stmt->fetchAll();

if ($typeFilter === 'owner') {
    $residents = array_values(array_filter($residents, static fn ($r) => (int) $r['IsOwner'] === 1));
} elseif ($typeFilter === 'tenant') {
    $residents = array_values(array_filter($residents, static fn ($r) => (int) $r['IsTenant'] === 1));
} elseif ($typeFilter === 'resident') {
    $residents = array_values(array_filter($residents, static fn ($r) => (int) $r['IsResident'] === 1));
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Residents</h1>
        <p class="text-muted mb-0">All parties linked to Ready Plaza</p>
    </div>
    <a href="<?= e(base_url('modules/residents/party_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Resident
    </a>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, CNIC, email">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All</option>
                    <option value="owner" <?= $typeFilter === 'owner' ? 'selected' : '' ?>>Owners</option>
                    <option value="tenant" <?= $typeFilter === 'tenant' ? 'selected' : '' ?>>Tenants</option>
                    <option value="resident" <?= $typeFilter === 'resident' ? 'selected' : '' ?>>Residents</option>
                </select>
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
                    <th>Name</th>
                    <th>CNIC</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Roles</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$residents): ?>
                    <tr><td colspan="7" class="empty-state">No residents found.</td></tr>
                <?php else: foreach ($residents as $row): ?>
                    <tr>
                        <td><strong><?= e($row['FullName']) ?></strong></td>
                        <td><?= e($row['CNIC'] ?: '—') ?></td>
                        <td><?= e($row['Mobile'] ?: '—') ?></td>
                        <td><?= e($row['Email'] ?: '—') ?></td>
                        <td>
                            <?php if ((int) $row['IsOwner']): ?><span class="badge badge-soft assigned me-1">Owner</span><?php endif; ?>
                            <?php if ((int) $row['IsTenant']): ?><span class="badge badge-soft new me-1">Tenant</span><?php endif; ?>
                            <?php if ((int) $row['IsResident']): ?><span class="badge badge-soft resolved">Resident</span><?php endif; ?>
                            <?php if (!(int) $row['IsOwner'] && !(int) $row['IsTenant'] && !(int) $row['IsResident']): ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-soft <?= $row['Status'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['Status']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/residents/party_view.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/residents/party_edit.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/residents/party_delete.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
