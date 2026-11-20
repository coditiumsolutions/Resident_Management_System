<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Add Rental Agreement';
$currentNav = 'residents-agreements';
$errors = [];

$occupancies = db()->query(
    "SELECT oc.OccupancyID, p.FullName, u.UnitNumber, pr.PropertyName
     FROM UnitOccupancies oc
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
     WHERE oc.OccupancyType = 'Tenant' AND oc.Status = 'Active'
     ORDER BY p.FullName"
)->fetchAll();

$form = [
    'OccupancyID' => '',
    'MonthlyRent' => '40000',
    'SecurityDeposit' => '80000',
    'AgreementStartDate' => date('Y-m-d'),
    'AgreementEndDate' => date('Y-m-d', strtotime('+1 year')),
    'RentDueDay' => '5',
    'AgreementStatus' => 'Active',
    'Remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ((int) $form['OccupancyID'] <= 0) {
            $errors[] = 'Please select a tenant occupancy.';
        }
        if ($form['AgreementStartDate'] === '') {
            $errors[] = 'Agreement start date is required.';
        }
        if (!in_array($form['AgreementStatus'], ['Active', 'Expired', 'Terminated', 'Draft'], true)) {
            $errors[] = 'Invalid agreement status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO RentalAgreements
                        (OccupancyID, MonthlyRent, SecurityDeposit, AgreementStartDate, AgreementEndDate, RentDueDay, AgreementStatus, Remarks)
                     VALUES
                        (:occupancy_id, :rent, :deposit, :start_date, :end_date, :due_day, :status, :remarks)'
                )->execute([
                    'occupancy_id' => (int) $form['OccupancyID'],
                    'rent' => (float) $form['MonthlyRent'],
                    'deposit' => (float) $form['SecurityDeposit'],
                    'start_date' => $form['AgreementStartDate'],
                    'end_date' => $form['AgreementEndDate'] !== '' ? $form['AgreementEndDate'] : null,
                    'due_day' => (int) $form['RentDueDay'] ?: 5,
                    'status' => $form['AgreementStatus'],
                    'remarks' => $form['Remarks'] !== '' ? $form['Remarks'] : null,
                ]);
                flash_set('success', 'Rental agreement created successfully.');
                redirect('modules/residents/agreements.php');
            } catch (Throwable $e) {
                error_log('Agreement create failed: ' . $e->getMessage());
                $errors[] = 'Could not create agreement.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Add Rental Agreement</h1><p class="text-muted mb-0">Attach a lease to a tenant occupancy</p></div>
    <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if (!$occupancies): ?>
        <div class="alert alert-warning mb-0">No active tenant occupancies available. Add a tenant first.</div>
    <?php else: ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="OccupancyID">Tenant Occupancy</label>
                    <select class="form-select" id="OccupancyID" name="OccupancyID" required>
                        <option value="">Select occupancy</option>
                        <?php foreach ($occupancies as $row): ?>
                            <option value="<?= (int) $row['OccupancyID'] ?>" <?= (int) $form['OccupancyID'] === (int) $row['OccupancyID'] ? 'selected' : '' ?>>
                                <?= e($row['FullName'] . ' · ' . $row['PropertyName'] . ' / ' . $row['UnitNumber']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="AgreementStatus">Status</label>
                    <select class="form-select" id="AgreementStatus" name="AgreementStatus">
                        <?php foreach (['Active', 'Draft', 'Expired', 'Terminated'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $form['AgreementStatus'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label" for="MonthlyRent">Monthly Rent</label><input type="number" class="form-control" id="MonthlyRent" name="MonthlyRent" min="0" step="0.01" value="<?= e($form['MonthlyRent']) ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="SecurityDeposit">Security Deposit</label><input type="number" class="form-control" id="SecurityDeposit" name="SecurityDeposit" min="0" step="0.01" value="<?= e($form['SecurityDeposit']) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="AgreementStartDate">Start Date</label><input type="date" class="form-control" id="AgreementStartDate" name="AgreementStartDate" value="<?= e($form['AgreementStartDate']) ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="AgreementEndDate">End Date</label><input type="date" class="form-control" id="AgreementEndDate" name="AgreementEndDate" value="<?= e($form['AgreementEndDate']) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="RentDueDay">Rent Due Day</label><input type="number" class="form-control" id="RentDueDay" name="RentDueDay" min="1" max="28" value="<?= e($form['RentDueDay']) ?>"></div>
                <div class="col-md-9"><label class="form-label" for="Remarks">Remarks</label><input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="300" value="<?= e($form['Remarks']) ?>"></div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Save Agreement</button>
                <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
