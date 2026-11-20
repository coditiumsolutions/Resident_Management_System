<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Add Tenant';
$currentNav = 'residents-tenants';
$errors = [];
$parties = resident_party_options(db());
$units = resident_unit_options(db());

$form = [
    'mode' => 'new',
    'PartyID' => '',
    'UnitID' => '',
    'StartDate' => date('Y-m-d'),
    'MonthlyRent' => '40000',
    'SecurityDeposit' => '80000',
    'AgreementEndDate' => date('Y-m-d', strtotime('+1 year')),
    'RentDueDay' => '5',
    'FullName' => '', 'CNIC' => '', 'Email' => '', 'ContactNumber' => '', 'Gender' => '', 'Status' => 'Active',
    'DateOfBirth' => '', 'PermanentAddress' => '', 'EmergencyContactName' => '', 'EmergencyContactNumber' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            if (isset($_POST[$key])) {
                $form[$key] = trim((string) $_POST[$key]);
            }
        }

        $unitId = (int) $form['UnitID'];
        if ($unitId <= 0) {
            $errors[] = 'Please select a unit.';
        }
        if ($form['StartDate'] === '') {
            $errors[] = 'Start date is required.';
        }
        if ((float) $form['MonthlyRent'] < 0) {
            $errors[] = 'Monthly rent cannot be negative.';
        }

        $partyId = 0;
        if ($form['mode'] === 'existing') {
            $partyId = (int) $form['PartyID'];
            if ($partyId <= 0) {
                $errors[] = 'Please select an existing party.';
            }
        } else {
            $errors = array_merge($errors, resident_validate_party($form));
        }

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                if ($form['mode'] !== 'existing') {
                    $partyId = resident_save_party($pdo, $form);
                    resident_save_contact($pdo, $partyId, $form['ContactNumber']);
                }

                $pdo->prepare(
                    "INSERT INTO UnitOccupancies (UnitID, PartyID, OccupancyType, StartDate, EndDate, IsPrimary, Status)
                     VALUES (:unit_id, :party_id, 'Tenant', :start_date, NULL, 1, 'Active')"
                )->execute([
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'start_date' => $form['StartDate'],
                ]);
                $occupancyId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    "INSERT INTO RentalAgreements
                        (OccupancyID, MonthlyRent, SecurityDeposit, AgreementStartDate, AgreementEndDate, RentDueDay, AgreementStatus, Remarks)
                     VALUES
                        (:occupancy_id, :rent, :deposit, :start_date, :end_date, :due_day, 'Active', :remarks)"
                )->execute([
                    'occupancy_id' => $occupancyId,
                    'rent' => (float) $form['MonthlyRent'],
                    'deposit' => (float) $form['SecurityDeposit'],
                    'start_date' => $form['StartDate'],
                    'end_date' => $form['AgreementEndDate'] !== '' ? $form['AgreementEndDate'] : null,
                    'due_day' => (int) $form['RentDueDay'] ?: 5,
                    'remarks' => 'Tenant agreement',
                ]);

                resident_sync_unit_status($pdo, $unitId);
                $pdo->commit();
                flash_set('success', 'Tenant created successfully.');
                redirect('modules/residents/tenants.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Tenant create failed: ' . $e->getMessage());
                $errors[] = 'Could not create tenant.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Add Tenant</h1><p class="text-muted mb-0">Create tenant occupancy and rental agreement</p></div>
    <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Tenant Source</label>
                <select class="form-select" name="mode" id="mode">
                    <option value="new" <?= $form['mode'] === 'new' ? 'selected' : '' ?>>Create New Party</option>
                    <option value="existing" <?= $form['mode'] === 'existing' ? 'selected' : '' ?>>Existing Party</option>
                </select>
            </div>
            <div class="col-md-8" id="existingPartyWrap" style="display:none;">
                <label class="form-label" for="PartyID">Party</label>
                <select class="form-select" id="PartyID" name="PartyID">
                    <option value="">Select party</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= (int) $party['PartyID'] ?>" <?= (int) $form['PartyID'] === (int) $party['PartyID'] ? 'selected' : '' ?>>
                            <?= e($party['FullName']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="newPartyWrap" class="mb-3">
            <?php $party = $form; require __DIR__ . '/_party_form.php'; ?>
        </div>
        <hr>
        <h6 class="mb-3">Unit & Agreement</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="UnitID">Unit <span class="text-danger">*</span></label>
                <select class="form-select" id="UnitID" name="UnitID" required>
                    <option value="">Select unit</option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) $form['UnitID'] === (int) $unit['UnitID'] ? 'selected' : '' ?>>
                            <?= e(resident_format_unit_label($unit)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="StartDate">Start Date</label>
                <input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= e($form['StartDate']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="AgreementEndDate">Agreement End</label>
                <input type="date" class="form-control" id="AgreementEndDate" name="AgreementEndDate" value="<?= e($form['AgreementEndDate']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="MonthlyRent">Monthly Rent</label>
                <input type="number" class="form-control" id="MonthlyRent" name="MonthlyRent" min="0" step="0.01" value="<?= e($form['MonthlyRent']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="SecurityDeposit">Security Deposit</label>
                <input type="number" class="form-control" id="SecurityDeposit" name="SecurityDeposit" min="0" step="0.01" value="<?= e($form['SecurityDeposit']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="RentDueDay">Rent Due Day</label>
                <input type="number" class="form-control" id="RentDueDay" name="RentDueDay" min="1" max="28" value="<?= e($form['RentDueDay']) ?>">
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Save Tenant</button>
            <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<script>
(() => {
  const mode = document.getElementById('mode');
  const existingWrap = document.getElementById('existingPartyWrap');
  const newWrap = document.getElementById('newPartyWrap');
  const sync = () => {
    const isNew = mode.value === 'new';
    existingWrap.style.display = isNew ? 'none' : '';
    newWrap.style.display = isNew ? '' : 'none';
  };
  mode.addEventListener('change', sync);
  sync();
})();
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
