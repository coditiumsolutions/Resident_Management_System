<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Units';
$currentNav = 'units';

$floorFilter = filter_input(INPUT_GET, 'floor_id', FILTER_VALIDATE_INT) ?: 0;
$blockFilter = filter_input(INPUT_GET, 'block_id', FILTER_VALIDATE_INT) ?: 0;
$propertyFilter = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: 0;
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$properties = db()->query('SELECT PropertyID, PropertyName FROM Properties ORDER BY PropertyName')->fetchAll();
$blocks = db()->query(
    "SELECT b.BlockID, b.BlockName, b.PropertyID, p.PropertyName
     FROM Blocks b INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     ORDER BY p.PropertyName, b.BlockName"
)->fetchAll();
$floorsList = db()->query(
    "SELECT f.FloorID, f.FloorName, f.FloorNumber, f.BlockID, b.BlockName, b.PropertyID, p.PropertyName
     FROM Floors f
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     ORDER BY p.PropertyName, b.BlockName, f.FloorNumber"
)->fetchAll();

$sql = "SELECT u.UnitID, u.UnitNumber, u.UnitType, u.AreaSqFt, u.Status, u.Description, u.FloorID,
               f.FloorName, f.FloorNumber, b.BlockName, b.BlockID, p.PropertyName, p.PropertyID
        FROM Units u
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        WHERE 1=1";
$params = [];

if ($floorFilter > 0) {
    $sql .= ' AND u.FloorID = :floor_id';
    $params['floor_id'] = $floorFilter;
}
if ($blockFilter > 0) {
    $sql .= ' AND b.BlockID = :block_id';
    $params['block_id'] = $blockFilter;
}
if ($propertyFilter > 0) {
    $sql .= ' AND p.PropertyID = :property_id';
    $params['property_id'] = $propertyFilter;
}
if ($statusFilter !== '' && in_array($statusFilter, ['Vacant', 'Occupied', 'Under Maintenance', 'Reserved'], true)) {
    $sql .= ' AND u.Status = :status';
    $params['status'] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (u.UnitNumber LIKE :q OR u.UnitType LIKE :q2 OR u.Description LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql .= ' ORDER BY p.PropertyName, b.BlockName, f.FloorNumber, u.UnitNumber';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();

$summarySql = "SELECT
        COUNT(*) AS TotalUnits,
        SUM(CASE WHEN u.Status = 'Occupied' THEN 1 ELSE 0 END) AS OccupiedUnits,
        SUM(CASE WHEN u.Status = 'Vacant' THEN 1 ELSE 0 END) AS VacantUnits,
        SUM(CASE WHEN u.Status IN ('Under Maintenance', 'Maintenance') THEN 1 ELSE 0 END) AS MaintenanceUnits
     FROM Units u
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     WHERE 1=1";
$summaryParams = [];
if ($floorFilter > 0) {
    $summarySql .= ' AND u.FloorID = :floor_id';
    $summaryParams['floor_id'] = $floorFilter;
}
if ($blockFilter > 0) {
    $summarySql .= ' AND b.BlockID = :block_id';
    $summaryParams['block_id'] = $blockFilter;
}
if ($propertyFilter > 0) {
    $summarySql .= ' AND p.PropertyID = :property_id';
    $summaryParams['property_id'] = $propertyFilter;
}
$summaryStmt = db()->prepare($summarySql);
$summaryStmt->execute($summaryParams);
$summary = $summaryStmt->fetch() ?: [];

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Units</h1>
        <p class="text-muted mb-0">Manage units across floors and blocks</p>
    </div>
    <a href="<?= e(base_url('modules/properties/unit_create.php' . ($floorFilter ? ('?floor_id=' . $floorFilter) : ''))) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Unit
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Total Units</span><strong><?= e((string) ($summary['TotalUnits'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Occupied</span><strong><?= e((string) ($summary['OccupiedUnits'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Vacant</span><strong><?= e((string) ($summary['VacantUnits'] ?? 0)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Maintenance</span><strong><?= e((string) ($summary['MaintenanceUnits'] ?? 0)) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label" for="block_id">Block</label>
                <select class="form-select" id="block_id" name="block_id" onchange="this.form.submit()">
                    <option value="0">All Blocks</option>
                    <?php foreach ($blocks as $block): ?>
                        <?php if ($propertyFilter > 0 && (int) $block['PropertyID'] !== $propertyFilter) {
                            continue;
                        } ?>
                        <option value="<?= (int) $block['BlockID'] ?>" <?= $blockFilter === (int) $block['BlockID'] ? 'selected' : '' ?>>
                            <?= e($block['BlockName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="floor_id">Floor</label>
                <select class="form-select" id="floor_id" name="floor_id" onchange="this.form.submit()">
                    <option value="0">All Floors</option>
                    <?php foreach ($floorsList as $floor): ?>
                        <?php
                        if ($propertyFilter > 0 && (int) $floor['PropertyID'] !== $propertyFilter) {
                            continue;
                        }
                        if ($blockFilter > 0 && (int) $floor['BlockID'] !== $blockFilter) {
                            continue;
                        }
                        ?>
                        <option value="<?= (int) $floor['FloorID'] ?>" <?= $floorFilter === (int) $floor['FloorID'] ? 'selected' : '' ?>>
                            <?= e($floor['FloorName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (['Vacant', 'Occupied', 'Under Maintenance', 'Reserved'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Unit no / type">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Apply</button>
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
                    <th>Unit</th>
                    <th>Type</th>
                    <th>Floor</th>
                    <th>Block</th>
                    <th>Property</th>
                    <th>Area</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$units): ?>
                    <tr><td colspan="8" class="empty-state">No units found.</td></tr>
                <?php else: foreach ($units as $row): ?>
                    <?php
                    $badge = match ($row['Status']) {
                        'Occupied' => 'resolved',
                        'Under Maintenance', 'Maintenance' => 'progress',
                        'Reserved' => 'assigned',
                        default => 'hold',
                    };
                    ?>
                    <tr>
                        <td><strong><?= e($row['UnitNumber']) ?></strong></td>
                        <td><?= e($row['UnitType']) ?></td>
                        <td><?= e($row['FloorName']) ?></td>
                        <td><?= e($row['BlockName']) ?></td>
                        <td><?= e($row['PropertyName']) ?></td>
                        <td><?= e(number_format((float) $row['AreaSqFt'], 0)) ?></td>
                        <td><span class="badge badge-soft <?= e($badge) ?>"><?= e($row['Status']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/properties/unit_edit.php?id=' . (int) $row['UnitID'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/properties/unit_delete.php?id=' . (int) $row['UnitID'])) ?>" title="Delete"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
