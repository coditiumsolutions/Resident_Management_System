<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Floors';
$currentNav = 'floors';

$blockFilter = filter_input(INPUT_GET, 'block_id', FILTER_VALIDATE_INT) ?: 0;
$propertyFilter = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: 0;

$properties = db()->query('SELECT PropertyID, PropertyName FROM Properties ORDER BY PropertyName')->fetchAll();
$blocks = db()->query(
    "SELECT b.BlockID, b.BlockName, p.PropertyName, b.PropertyID
     FROM Blocks b
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     ORDER BY p.PropertyName, b.BlockName"
)->fetchAll();

$sql = "SELECT f.FloorID, f.FloorName, f.FloorNumber, f.Status, f.BlockID,
               b.BlockName, p.PropertyName, p.PropertyID,
               COUNT(u.UnitID) AS UnitCount
        FROM Floors f
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        LEFT JOIN Units u ON u.FloorID = f.FloorID
        WHERE 1=1";
$params = [];

if ($blockFilter > 0) {
    $sql .= ' AND f.BlockID = :block_id';
    $params['block_id'] = $blockFilter;
}
if ($propertyFilter > 0) {
    $sql .= ' AND p.PropertyID = :property_id';
    $params['property_id'] = $propertyFilter;
}

$sql .= ' GROUP BY f.FloorID, f.FloorName, f.FloorNumber, f.Status, f.BlockID, b.BlockName, p.PropertyName, p.PropertyID
          ORDER BY p.PropertyName, b.BlockName, f.FloorNumber';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$floors = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Floors</h1>
        <p class="text-muted mb-0">Manage floors within blocks</p>
    </div>
    <a href="<?= e(base_url('modules/properties/floor_create.php' . ($blockFilter ? ('?block_id=' . $blockFilter) : ''))) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Floor
    </a>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="property_id">Property</label>
                <select class="form-select" id="property_id" name="property_id" onchange="this.form.submit()">
                    <option value="0">All Properties</option>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?= (int) $property['PropertyID'] ?>" <?= $propertyFilter === (int) $property['PropertyID'] ? 'selected' : '' ?>>
                            <?= e($property['PropertyName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="block_id">Block</label>
                <select class="form-select" id="block_id" name="block_id" onchange="this.form.submit()">
                    <option value="0">All Blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <?php if ($propertyFilter > 0 && (int) $block['PropertyID'] !== $propertyFilter) {
                            continue;
                        } ?>
                        <option value="<?= (int) $block['BlockID'] ?>" <?= $blockFilter === (int) $block['BlockID'] ? 'selected' : '' ?>>
                            <?= e($block['PropertyName'] . ' / ' . $block['BlockName']) ?>
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
                    <th>Floor</th>
                    <th>No.</th>
                    <th>Block</th>
                    <th>Property</th>
                    <th class="text-center">Units</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$floors): ?>
                    <tr><td colspan="7" class="empty-state">No floors found.</td></tr>
                <?php else: foreach ($floors as $row): ?>
                    <tr>
                        <td><strong><?= e($row['FloorName']) ?></strong></td>
                        <td><?= e((string) $row['FloorNumber']) ?></td>
                        <td><?= e($row['BlockName']) ?></td>
                        <td><?= e($row['PropertyName']) ?></td>
                        <td class="text-center"><?= e((string) $row['UnitCount']) ?></td>
                        <td><span class="badge badge-soft <?= $row['Status'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['Status']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/properties/units.php?floor_id=' . (int) $row['FloorID'])) ?>" title="Units"><i class="bi bi-door-closed"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/properties/floor_edit.php?id=' . (int) $row['FloorID'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/properties/floor_delete.php?id=' . (int) $row['FloorID'])) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
