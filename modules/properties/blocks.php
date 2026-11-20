<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Blocks';
$currentNav = 'blocks';

$propertyFilter = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: 0;

$properties = db()->query('SELECT PropertyID, PropertyName FROM Properties ORDER BY PropertyName')->fetchAll();

$sql = "SELECT b.BlockID, b.BlockName, b.Description, b.Status, b.PropertyID,
               p.PropertyName,
               COUNT(DISTINCT f.FloorID) AS FloorCount,
               COUNT(DISTINCT u.UnitID) AS UnitCount
        FROM Blocks b
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        LEFT JOIN Floors f ON f.BlockID = b.BlockID
        LEFT JOIN Units u ON u.FloorID = f.FloorID";
$params = [];

if ($propertyFilter > 0) {
    $sql .= ' WHERE b.PropertyID = :property_id';
    $params['property_id'] = $propertyFilter;
}

$sql .= ' GROUP BY b.BlockID, b.BlockName, b.Description, b.Status, b.PropertyID, p.PropertyName
          ORDER BY p.PropertyName, b.BlockName';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$blocks = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Blocks</h1>
        <p class="text-muted mb-0">Manage blocks within properties</p>
    </div>
    <a href="<?= e(base_url('modules/properties/block_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Block
    </a>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="property_id">Filter by Property</label>
                <select class="form-select" id="property_id" name="property_id" onchange="this.form.submit()">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?= (int) $property['PropertyID'] ?>" <?= $propertyFilter === (int) $property['PropertyID'] ? 'selected' : '' ?>>
                            <?= e($property['PropertyName']) ?>
                        </option>
                    <?php endforeach; ?>
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
                    <th>Block</th>
                    <th>Property</th>
                    <th>Description</th>
                    <th class="text-center">Floors</th>
                    <th class="text-center">Units</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$blocks): ?>
                    <tr><td colspan="7" class="empty-state">No blocks found.</td></tr>
                <?php else: foreach ($blocks as $row): ?>
                    <tr>
                        <td><strong><?= e($row['BlockName']) ?></strong></td>
                        <td><?= e($row['PropertyName']) ?></td>
                        <td><?= e($row['Description'] ?: '—') ?></td>
                        <td class="text-center"><?= e((string) $row['FloorCount']) ?></td>
                        <td class="text-center"><?= e((string) $row['UnitCount']) ?></td>
                        <td><span class="badge badge-soft <?= $row['Status'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['Status']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/properties/floors.php?block_id=' . (int) $row['BlockID'])) ?>" title="Floors"><i class="bi bi-layers"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/properties/block_edit.php?id=' . (int) $row['BlockID'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/properties/block_delete.php?id=' . (int) $row['BlockID'])) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
