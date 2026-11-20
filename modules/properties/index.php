<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Properties';
$currentNav = 'properties';

$properties = db()->query(
    "SELECT p.PropertyID, p.PropertyName, p.Address, p.City, p.ContactNumber, p.Status, p.CreatedAt,
            COUNT(DISTINCT b.BlockID) AS BlockCount,
            COUNT(DISTINCT f.FloorID) AS FloorCount,
            COUNT(DISTINCT u.UnitID) AS UnitCount
     FROM Properties p
     LEFT JOIN Blocks b ON b.PropertyID = p.PropertyID
     LEFT JOIN Floors f ON f.BlockID = b.BlockID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     GROUP BY p.PropertyID, p.PropertyName, p.Address, p.City, p.ContactNumber, p.Status, p.CreatedAt
     ORDER BY p.PropertyName ASC"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Properties</h1>
        <p class="text-muted mb-0">Manage plaza and property records</p>
    </div>
    <a href="<?= e(base_url('modules/properties/create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Property
    </a>
</div>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Property</th>
                    <th>City</th>
                    <th>Contact</th>
                    <th class="text-center">Blocks</th>
                    <th class="text-center">Floors</th>
                    <th class="text-center">Units</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$properties): ?>
                    <tr>
                        <td colspan="8" class="empty-state">No properties found. Add your first property to get started.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($properties as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e($row['PropertyName']) ?></strong>
                                <?php if (!empty($row['Address'])): ?>
                                    <div class="text-muted small"><?= e($row['Address']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($row['City'] ?: '—') ?></td>
                            <td><?= e($row['ContactNumber'] ?: '—') ?></td>
                            <td class="text-center"><?= e((string) $row['BlockCount']) ?></td>
                            <td class="text-center"><?= e((string) $row['FloorCount']) ?></td>
                            <td class="text-center"><?= e((string) $row['UnitCount']) ?></td>
                            <td>
                                <span class="badge badge-soft <?= ($row['Status'] === 'Active') ? 'resolved' : 'hold' ?>">
                                    <?= e($row['Status']) ?>
                                </span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/properties/view.php?id=' . (int) $row['PropertyID'])) ?>" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/properties/edit.php?id=' . (int) $row['PropertyID'])) ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/properties/delete.php?id=' . (int) $row['PropertyID'])) ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
