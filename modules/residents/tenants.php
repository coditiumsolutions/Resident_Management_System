<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Tenants';
$currentNav = 'residents-tenants';

$tenants = db()->query(
    "SELECT oc.OccupancyID, oc.StartDate, oc.EndDate, oc.Status,
            p.PartyID, p.FullName, p.CNIC,
            u.UnitNumber, b.BlockName, pr.PropertyName,
            ra.AgreementID, ra.MonthlyRent, ra.AgreementStatus,
            (SELECT ContactNumber FROM PartyContacts pc WHERE pc.PartyID = p.PartyID AND pc.IsPrimary = 1 LIMIT 1) AS Mobile
     FROM UnitOccupancies oc
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
     LEFT JOIN RentalAgreements ra ON ra.OccupancyID = oc.OccupancyID
        AND ra.AgreementID = (
            SELECT MAX(ra2.AgreementID) FROM RentalAgreements ra2 WHERE ra2.OccupancyID = oc.OccupancyID
        )
     WHERE oc.OccupancyType = 'Tenant'
       AND oc.Status = 'Active'
       AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
     ORDER BY pr.PropertyName, u.UnitNumber, p.FullName"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Tenants</h1>
        <p class="text-muted mb-0">Active tenant occupancies and rent details</p>
    </div>
    <a href="<?= e(base_url('modules/residents/tenant_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Tenant
    </a>
</div>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Mobile</th>
                    <th>Unit</th>
                    <th>Property / Block</th>
                    <th>Start</th>
                    <th>Monthly Rent</th>
                    <th>Agreement</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$tenants): ?>
                    <tr><td colspan="8" class="empty-state">No active tenants found.</td></tr>
                <?php else: foreach ($tenants as $row): ?>
                    <tr>
                        <td>
                            <strong><?= e($row['FullName']) ?></strong>
                            <div class="text-muted small"><?= e($row['CNIC'] ?: '') ?></div>
                        </td>
                        <td><?= e($row['Mobile'] ?: '—') ?></td>
                        <td><strong><?= e($row['UnitNumber']) ?></strong></td>
                        <td><?= e($row['PropertyName'] . ' / ' . $row['BlockName']) ?></td>
                        <td><?= e(format_date($row['StartDate'])) ?></td>
                        <td><?= $row['MonthlyRent'] !== null ? e(format_money($row['MonthlyRent'])) : '—' ?></td>
                        <td><span class="badge badge-soft <?= ($row['AgreementStatus'] ?? '') === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['AgreementStatus'] ?: 'None') ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/residents/party_view.php?id=' . (int) $row['PartyID'])) ?>"><i class="bi bi-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/residents/tenant_edit.php?id=' . (int) $row['OccupancyID'])) ?>"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/residents/tenant_end.php?id=' . (int) $row['OccupancyID'])) ?>" title="End tenancy"><i class="bi bi-x-circle"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
