<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Owners';
$currentNav = 'residents-owners';

$owners = db()->query(
    "SELECT o.OwnershipID, o.OwnershipShare, o.StartDate, o.EndDate, o.IsPrimary,
            p.PartyID, p.FullName, p.CNIC, p.Status AS PartyStatus,
            u.UnitNumber, u.UnitID, b.BlockName, pr.PropertyName,
            (SELECT ContactNumber FROM PartyContacts pc WHERE pc.PartyID = p.PartyID AND pc.IsPrimary = 1 LIMIT 1) AS Mobile
     FROM UnitOwnerships o
     INNER JOIN Parties p ON p.PartyID = o.OwnerPartyID
     INNER JOIN Units u ON u.UnitID = o.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
     WHERE o.EndDate IS NULL OR o.EndDate >= CURDATE()
     ORDER BY pr.PropertyName, u.UnitNumber, p.FullName"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Owners</h1>
        <p class="text-muted mb-0">Active unit ownership assignments</p>
    </div>
    <a href="<?= e(base_url('modules/residents/owner_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Assign Owner
    </a>
</div>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Owner</th>
                    <th>Mobile</th>
                    <th>Unit</th>
                    <th>Property / Block</th>
                    <th>Share</th>
                    <th>Start</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$owners): ?>
                    <tr><td colspan="7" class="empty-state">No active owners found.</td></tr>
                <?php else: foreach ($owners as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['FullName']) ?></strong>
                            <div class="text-muted small"><?= e($row['CNIC'] ?: '') ?></div>
                        </td>
                        <td><?= e($row['Mobile'] ?: '—') ?></td>
                        <td><strong><?= e($row['UnitNumber']) ?></strong></td>
                        <td><?= e($row['PropertyName'] . ' / ' . $row['BlockName']) ?></td>
                        <td><?= e(number_format((float) $row['OwnershipShare'], 2)) ?>%</td>
                        <td><?= e(format_date($row['StartDate'])) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/residents/party_view.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/residents/owner_edit.php?id=' . (int) $row['OwnershipID'])) ?>"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/residents/owner_end.php?id=' . (int) $row['OwnershipID'])) ?>" title="End ownership"><i class="bi bi-x-circle"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
