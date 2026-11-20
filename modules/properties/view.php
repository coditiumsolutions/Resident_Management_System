<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Property Details';
$currentNav = 'properties';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid property selected.');
    redirect('modules/properties/index.php');
}

$stmt = db()->prepare('SELECT * FROM Properties WHERE PropertyID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$property = $stmt->fetch();

if (!$property) {
    flash_set('error', 'Property not found.');
    redirect('modules/properties/index.php');
}

$summary = db()->prepare(
    "SELECT
        COUNT(DISTINCT b.BlockID) AS BlockCount,
        COUNT(DISTINCT f.FloorID) AS FloorCount,
        COUNT(DISTINCT u.UnitID) AS UnitCount,
        SUM(CASE WHEN u.Status = 'Occupied' THEN 1 ELSE 0 END) AS OccupiedUnits,
        SUM(CASE WHEN u.Status = 'Vacant' THEN 1 ELSE 0 END) AS VacantUnits,
        SUM(CASE WHEN u.Status IN ('Under Maintenance', 'Maintenance') THEN 1 ELSE 0 END) AS MaintenanceUnits
     FROM Properties p
     LEFT JOIN Blocks b ON b.PropertyID = p.PropertyID
     LEFT JOIN Floors f ON f.BlockID = b.BlockID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     WHERE p.PropertyID = :id"
);
$summary->execute(['id' => $id]);
$stats = $summary->fetch() ?: [];

$floors = db()->prepare(
    "SELECT f.FloorID, f.FloorName, f.FloorNumber, f.Status, b.BlockName,
            COUNT(u.UnitID) AS UnitCount
     FROM Floors f
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     WHERE b.PropertyID = :id
     GROUP BY f.FloorID, f.FloorName, f.FloorNumber, f.Status, b.BlockName
     ORDER BY f.FloorNumber ASC"
);
$floors->execute(['id' => $id]);
$floorRows = $floors->fetchAll();

$units = db()->prepare(
    "SELECT u.UnitID, u.UnitNumber, u.UnitType, u.AreaSqFt, u.Status, f.FloorNumber, b.BlockName
     FROM Units u
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     WHERE b.PropertyID = :id
     ORDER BY f.FloorNumber ASC, u.UnitNumber ASC
     LIMIT 60"
);
$units->execute(['id' => $id]);
$unitRows = $units->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($property['PropertyName']) ?></h1>
        <p class="text-muted mb-0">Property overview and structure</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/properties/edit.php?id=' . (int) $property['PropertyID'])) ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <a href="<?= e(base_url('modules/properties/index.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card panel-card h-100">
            <div class="card-header">Property Details</div>
            <div class="card-body">
                <dl class="row mb-0 detail-list">
                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8"><?= e($property['PropertyName']) ?></dd>
                    <dt class="col-sm-4">Address</dt>
                    <dd class="col-sm-8"><?= e($property['Address'] ?: '—') ?></dd>
                    <dt class="col-sm-4">City</dt>
                    <dd class="col-sm-8"><?= e($property['City'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Contact</dt>
                    <dd class="col-sm-8"><?= e($property['ContactNumber'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8">
                        <span class="badge badge-soft <?= ($property['Status'] === 'Active') ? 'resolved' : 'hold' ?>">
                            <?= e($property['Status']) ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card panel-card h-100">
            <div class="card-header">Structure Summary</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6"><div class="stat-mini"><span>Blocks</span><strong><?= e((string) ($stats['BlockCount'] ?? 0)) ?></strong></div></div>
                    <div class="col-6"><div class="stat-mini"><span>Floors</span><strong><?= e((string) ($stats['FloorCount'] ?? 0)) ?></strong></div></div>
                    <div class="col-6"><div class="stat-mini"><span>Units</span><strong><?= e((string) ($stats['UnitCount'] ?? 0)) ?></strong></div></div>
                    <div class="col-6"><div class="stat-mini"><span>Occupied</span><strong><?= e((string) ($stats['OccupiedUnits'] ?? 0)) ?></strong></div></div>
                    <div class="col-6"><div class="stat-mini"><span>Vacant</span><strong><?= e((string) ($stats['VacantUnits'] ?? 0)) ?></strong></div></div>
                    <div class="col-6"><div class="stat-mini"><span>Maintenance</span><strong><?= e((string) ($stats['MaintenanceUnits'] ?? 0)) ?></strong></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card panel-card h-100">
            <div class="card-header">Floors</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Floor</th>
                            <th>Block</th>
                            <th class="text-center">Units</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$floorRows): ?>
                            <tr><td colspan="4" class="empty-state">No floors linked to this property.</td></tr>
                        <?php else: foreach ($floorRows as $floor): ?>
                            <tr>
                                <td><?= e($floor['FloorName']) ?> <span class="text-muted">(#<?= e((string) $floor['FloorNumber']) ?>)</span></td>
                                <td><?= e($floor['BlockName']) ?></td>
                                <td class="text-center"><?= e((string) $floor['UnitCount']) ?></td>
                                <td><span class="badge badge-soft <?= ($floor['Status'] === 'Active') ? 'resolved' : 'hold' ?>"><?= e($floor['Status']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card panel-card h-100">
            <div class="card-header">Units Preview <span class="text-muted fw-normal">(first 60)</span></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Unit</th>
                            <th>Floor</th>
                            <th>Type</th>
                            <th>Area (sqft)</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$unitRows): ?>
                            <tr><td colspan="5" class="empty-state">No units linked to this property.</td></tr>
                        <?php else: foreach ($unitRows as $unit): ?>
                            <tr>
                                <td><?= e($unit['UnitNumber']) ?></td>
                                <td><?= e((string) $unit['FloorNumber']) ?></td>
                                <td><?= e($unit['UnitType']) ?></td>
                                <td><?= e(number_format((float) $unit['AreaSqFt'], 0)) ?></td>
                                <td>
                                    <?php
                                    $badge = match ($unit['Status']) {
                                        'Occupied' => 'resolved',
                                        'Under Maintenance', 'Maintenance' => 'progress',
                                        default => 'hold',
                                    };
                                    ?>
                                    <span class="badge badge-soft <?= e($badge) ?>"><?= e($unit['Status']) ?></span>
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
