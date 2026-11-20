<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Tenant Occupancy';
$currentNav = 'residents-tenants';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid tenancy selected.');
    redirect('modules/residents/tenants.php');
}

$stmt = db()->prepare(
    "SELECT oc.*, p.FullName, u.UnitNumber
     FROM UnitOccupancies oc
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     WHERE oc.OccupancyID = :id AND oc.OccupancyType = 'Tenant'
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$occupancy = $stmt->fetch();
if (!$occupancy) {
    flash_set('error', 'Tenant occupancy not found.');
    redirect('modules/residents/tenants.php');
}

$units = resident_unit_options(db());
$parties = resident_party_options(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $unitId = (int) ($_POST['UnitID'] ?? 0);
        $partyId = (int) ($_POST['PartyID'] ?? 0);
        $startDate = trim((string) ($_POST['StartDate'] ?? ''));
        $endDate = trim((string) ($_POST['EndDate'] ?? ''));
        $status = trim((string) ($_POST['Status'] ?? 'Active'));
        $isPrimary = (int) ($_POST['IsPrimary'] ?? 1) ? 1 : 0;

        if ($unitId <= 0 || $partyId <= 0 || $startDate === '') {
            $errors[] = 'Unit, tenant and start date are required.';
        }
        if (!in_array($status, ['Active', 'Ended', 'Inactive'], true)) {
            $errors[] = 'Invalid status.';
        }

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $oldUnitId = (int) $occupancy['UnitID'];
                $pdo->prepare(
                    'UPDATE UnitOccupancies
                     SET UnitID = :unit_id, PartyID = :party_id, StartDate = :start_date,
                         EndDate = :end_date, IsPrimary = :is_primary, Status = :status
                     WHERE OccupancyID = :id'
                )->execute([
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'start_date' => $startDate,
                    'end_date' => $endDate !== '' ? $endDate : null,
                    'is_primary' => $isPrimary,
                    'status' => $status,
                    'id' => $id,
                ]);
                resident_sync_unit_status($pdo, $oldUnitId);
                resident_sync_unit_status($pdo, $unitId);
                $pdo->commit();
                flash_set('success', 'Tenant occupancy updated successfully.');
                redirect('modules/residents/tenants.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Tenant edit failed: ' . $e->getMessage());
                $errors[] = 'Could not update tenant occupancy.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Tenant Occupancy</h1><p class="text-muted mb-0"><?= e($occupancy['FullName']) ?> · Unit <?= e($occupancy['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="PartyID">Tenant</label>
                <select class="form-select" id="PartyID" name="PartyID" required>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= (int) $party['PartyID'] ?>" <?= (int) $occupancy['PartyID'] === (int) $party['PartyID'] ? 'selected' : '' ?>><?= e($party['FullName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="UnitID">Unit</label>
                <select class="form-select" id="UnitID" name="UnitID" required>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) $occupancy['UnitID'] === (int) $unit['UnitID'] ? 'selected' : '' ?>><?= e(resident_format_unit_label($unit)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="StartDate">Start Date</label>
                <input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= e($occupancy['StartDate']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="EndDate">End Date</label>
                <input type="date" class="form-control" id="EndDate" name="EndDate" value="<?= e($occupancy['EndDate'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="Status">Status</label>
                <select class="form-select" id="Status" name="Status">
                    <?php foreach (['Active', 'Ended', 'Inactive'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $occupancy['Status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="IsPrimary">Primary</label>
                <select class="form-select" id="IsPrimary" name="IsPrimary">
                    <option value="1" <?= (int) $occupancy['IsPrimary'] === 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= (int) $occupancy['IsPrimary'] === 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
            <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
