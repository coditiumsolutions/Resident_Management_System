<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Rental Agreements';
$currentNav = 'residents-agreements';

$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = "SELECT ra.*,
               p.FullName AS TenantName, p.PartyID,
               u.UnitNumber, b.BlockName, pr.PropertyName,
               oc.Status AS OccupancyStatus
        FROM RentalAgreements ra
        INNER JOIN UnitOccupancies oc ON oc.OccupancyID = ra.OccupancyID
        INNER JOIN Parties p ON p.PartyID = oc.PartyID
        INNER JOIN Units u ON u.UnitID = oc.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
        WHERE 1=1";
$params = [];
if ($statusFilter !== '') {
    $sql .= ' AND ra.AgreementStatus = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY ra.AgreementStartDate DESC, ra.AgreementID DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$agreements = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Rental Agreements</h1>
        <p class="text-muted mb-0">Lease contracts linked to tenant occupancies</p>
    </div>
    <a href="<?= e(base_url('modules/residents/agreement_create.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Agreement
    </a>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach (['Active', 'Expired', 'Terminated', 'Draft'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
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
                    <th>Tenant</th>
                    <th>Unit</th>
                    <th>Property</th>
                    <th>Rent</th>
                    <th>Deposit</th>
                    <th>Period</th>
                    <th>Due Day</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$agreements): ?>
                    <tr><td colspan="9" class="empty-state">No rental agreements found.</td></tr>
                <?php else: foreach ($agreements as $row): ?>
                    <tr>
                        <td><strong><?= e($row['TenantName']) ?></strong></td>
                        <td><?= e($row['UnitNumber']) ?></td>
                        <td><?= e($row['PropertyName'] . ' / ' . $row['BlockName']) ?></td>
                        <td><?= e(format_money($row['MonthlyRent'])) ?></td>
                        <td><?= e(format_money($row['SecurityDeposit'])) ?></td>
                        <td><?= e(format_date($row['AgreementStartDate'])) ?> → <?= e($row['AgreementEndDate'] ? format_date($row['AgreementEndDate']) : 'Open') ?></td>
                        <td><?= e((string) $row['RentDueDay']) ?></td>
                        <td><span class="badge badge-soft <?= $row['AgreementStatus'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['AgreementStatus']) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/residents/agreement_edit.php?id=' . (int) $row['AgreementID'])) ?>"><i class="bi bi-pencil"></i></a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/residents/agreement_delete.php?id=' . (int) $row['AgreementID'])) ?>"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
