<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Ownership';
$currentNav = 'residents-owners';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid ownership selected.');
    redirect('modules/residents/owners.php');
}

$stmt = db()->prepare(
    "SELECT o.*, p.FullName, u.UnitNumber
     FROM UnitOwnerships o
     INNER JOIN Parties p ON p.PartyID = o.OwnerPartyID
     INNER JOIN Units u ON u.UnitID = o.UnitID
     WHERE o.OwnershipID = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$ownership = $stmt->fetch();
if (!$ownership) {
    flash_set('error', 'Ownership not found.');
    redirect('modules/residents/owners.php');
}

$units = resident_unit_options(db());
$parties = resident_party_options(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $unitId = (int) ($_POST['UnitID'] ?? 0);
        $partyId = (int) ($_POST['OwnerPartyID'] ?? 0);
        $share = (float) ($_POST['OwnershipShare'] ?? 0);
        $startDate = trim((string) ($_POST['StartDate'] ?? ''));
        $endDate = trim((string) ($_POST['EndDate'] ?? ''));
        $isPrimary = (int) ($_POST['IsPrimary'] ?? 1) ? 1 : 0;

        if ($unitId <= 0 || $partyId <= 0) {
            $errors[] = 'Unit and owner are required.';
        }
        if ($share <= 0 || $share > 100) {
            $errors[] = 'Ownership share must be between 0.01 and 100.';
        }
        if ($startDate === '') {
            $errors[] = 'Start date is required.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE UnitOwnerships
                     SET UnitID = :unit_id, OwnerPartyID = :party_id, OwnershipShare = :share,
                         StartDate = :start_date, EndDate = :end_date, IsPrimary = :is_primary
                     WHERE OwnershipID = :id'
                )->execute([
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'share' => $share,
                    'start_date' => $startDate,
                    'end_date' => $endDate !== '' ? $endDate : null,
                    'is_primary' => $isPrimary,
                    'id' => $id,
                ]);
                flash_set('success', 'Ownership updated successfully.');
                redirect('modules/residents/owners.php');
            } catch (Throwable $e) {
                error_log('Owner edit failed: ' . $e->getMessage());
                $errors[] = 'Could not update ownership.';
            }
        }

        $ownership = array_merge($ownership, [
            'UnitID' => $unitId,
            'OwnerPartyID' => $partyId,
            'OwnershipShare' => $share,
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'IsPrimary' => $isPrimary,
        ]);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Ownership</h1><p class="text-muted mb-0"><?= e($ownership['FullName']) ?> · Unit <?= e($ownership['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="OwnerPartyID">Owner</label>
                <select class="form-select" id="OwnerPartyID" name="OwnerPartyID" required>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= (int) $party['PartyID'] ?>" <?= (int) $ownership['OwnerPartyID'] === (int) $party['PartyID'] ? 'selected' : '' ?>>
                            <?= e($party['FullName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="UnitID">Unit</label>
                <select class="form-select" id="UnitID" name="UnitID" required>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) $ownership['UnitID'] === (int) $unit['UnitID'] ? 'selected' : '' ?>>
                            <?= e(resident_format_unit_label($unit)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="OwnershipShare">Share %</label>
                <input type="number" class="form-control" name="OwnershipShare" id="OwnershipShare" min="0.01" max="100" step="0.01" value="<?= e((string) $ownership['OwnershipShare']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="StartDate">Start Date</label>
                <input type="date" class="form-control" name="StartDate" id="StartDate" value="<?= e($ownership['StartDate']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="EndDate">End Date</label>
                <input type="date" class="form-control" name="EndDate" id="EndDate" value="<?= e($ownership['EndDate'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="IsPrimary">Primary</label>
                <select class="form-select" name="IsPrimary" id="IsPrimary">
                    <option value="1" <?= (int) $ownership['IsPrimary'] === 1 ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= (int) $ownership['IsPrimary'] === 0 ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
            <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
