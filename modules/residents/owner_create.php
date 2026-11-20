<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Assign Owner';
$currentNav = 'residents-owners';
$errors = [];
$parties = resident_party_options(db());
$units = resident_unit_options(db());

$form = [
    'mode' => 'existing',
    'PartyID' => '',
    'UnitID' => '',
    'OwnershipShare' => '100',
    'StartDate' => date('Y-m-d'),
    'IsPrimary' => '1',
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
        $share = (float) $form['OwnershipShare'];
        if ($unitId <= 0) {
            $errors[] = 'Please select a unit.';
        }
        if ($share <= 0 || $share > 100) {
            $errors[] = 'Ownership share must be between 0.01 and 100.';
        }
        if ($form['StartDate'] === '') {
            $errors[] = 'Start date is required.';
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
                    'INSERT INTO UnitOwnerships (UnitID, OwnerPartyID, OwnershipShare, StartDate, EndDate, IsPrimary)
                     VALUES (:unit_id, :party_id, :share, :start_date, NULL, :is_primary)'
                )->execute([
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'share' => $share,
                    'start_date' => $form['StartDate'],
                    'is_primary' => (int) $form['IsPrimary'] ? 1 : 0,
                ]);
                $pdo->commit();
                flash_set('success', 'Owner assigned successfully.');
                redirect('modules/residents/owners.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Owner create failed: ' . $e->getMessage());
                $errors[] = 'Could not assign owner.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Assign Owner</h1><p class="text-muted mb-0">Link a party to a property unit</p></div>
    <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Owner Source</label>
                <select class="form-select" name="mode" id="mode">
                    <option value="existing" <?= $form['mode'] === 'existing' ? 'selected' : '' ?>>Existing Party</option>
                    <option value="new" <?= $form['mode'] === 'new' ? 'selected' : '' ?>>Create New Party</option>
                </select>
            </div>
            <div class="col-md-8" id="existingPartyWrap">
                <label class="form-label" for="PartyID">Party</label>
                <select class="form-select" id="PartyID" name="PartyID">
                    <option value="">Select party</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?= (int) $party['PartyID'] ?>" <?= (int) $form['PartyID'] === (int) $party['PartyID'] ? 'selected' : '' ?>>
                            <?= e($party['FullName'] . ($party['CNIC'] ? ' (' . $party['CNIC'] . ')' : '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="newPartyWrap" class="mb-3" style="display:none;">
            <?php $party = $form; require __DIR__ . '/_party_form.php'; ?>
        </div>
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
            <div class="col-md-2">
                <label class="form-label" for="OwnershipShare">Share %</label>
                <input type="number" class="form-control" id="OwnershipShare" name="OwnershipShare" min="0.01" max="100" step="0.01" value="<?= e($form['OwnershipShare']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="StartDate">Start Date</label>
                <input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= e($form['StartDate']) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="IsPrimary">Primary</label>
                <select class="form-select" id="IsPrimary" name="IsPrimary">
                    <option value="1" <?= $form['IsPrimary'] === '1' ? 'selected' : '' ?>>Yes</option>
                    <option value="0" <?= $form['IsPrimary'] === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Save Ownership</button>
            <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
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
