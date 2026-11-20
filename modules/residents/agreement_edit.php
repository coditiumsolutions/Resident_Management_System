<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Edit Rental Agreement';
$currentNav = 'residents-agreements';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid agreement selected.');
    redirect('modules/residents/agreements.php');
}

$stmt = db()->prepare(
    "SELECT ra.*, p.FullName, u.UnitNumber
     FROM RentalAgreements ra
     INNER JOIN UnitOccupancies oc ON oc.OccupancyID = ra.OccupancyID
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     WHERE ra.AgreementID = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$agreement = $stmt->fetch();
if (!$agreement) {
    flash_set('error', 'Agreement not found.');
    redirect('modules/residents/agreements.php');
}

$occupancies = db()->query(
    "SELECT oc.OccupancyID, p.FullName, u.UnitNumber, pr.PropertyName
     FROM UnitOccupancies oc
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
     WHERE oc.OccupancyType = 'Tenant'
     ORDER BY p.FullName"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'OccupancyID' => (int) ($_POST['OccupancyID'] ?? 0),
            'MonthlyRent' => (float) ($_POST['MonthlyRent'] ?? 0),
            'SecurityDeposit' => (float) ($_POST['SecurityDeposit'] ?? 0),
            'AgreementStartDate' => trim((string) ($_POST['AgreementStartDate'] ?? '')),
            'AgreementEndDate' => trim((string) ($_POST['AgreementEndDate'] ?? '')),
            'RentDueDay' => (int) ($_POST['RentDueDay'] ?? 5),
            'AgreementStatus' => trim((string) ($_POST['AgreementStatus'] ?? 'Active')),
            'Remarks' => trim((string) ($_POST['Remarks'] ?? '')),
        ];

        if ($data['OccupancyID'] <= 0 || $data['AgreementStartDate'] === '') {
            $errors[] = 'Occupancy and start date are required.';
        }
        if (!in_array($data['AgreementStatus'], ['Active', 'Expired', 'Terminated', 'Draft'], true)) {
            $errors[] = 'Invalid agreement status.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE RentalAgreements
                     SET OccupancyID = :occupancy_id, MonthlyRent = :rent, SecurityDeposit = :deposit,
                         AgreementStartDate = :start_date, AgreementEndDate = :end_date,
                         RentDueDay = :due_day, AgreementStatus = :status, Remarks = :remarks
                     WHERE AgreementID = :id'
                )->execute([
                    'occupancy_id' => $data['OccupancyID'],
                    'rent' => $data['MonthlyRent'],
                    'deposit' => $data['SecurityDeposit'],
                    'start_date' => $data['AgreementStartDate'],
                    'end_date' => $data['AgreementEndDate'] !== '' ? $data['AgreementEndDate'] : null,
                    'due_day' => $data['RentDueDay'] ?: 5,
                    'status' => $data['AgreementStatus'],
                    'remarks' => $data['Remarks'] !== '' ? $data['Remarks'] : null,
                    'id' => $id,
                ]);
                flash_set('success', 'Agreement updated successfully.');
                redirect('modules/residents/agreements.php');
            } catch (Throwable $e) {
                error_log('Agreement update failed: ' . $e->getMessage());
                $errors[] = 'Could not update agreement.';
            }
        }
        $agreement = array_merge($agreement, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Rental Agreement</h1><p class="text-muted mb-0"><?= e($agreement['FullName']) ?> · Unit <?= e($agreement['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="OccupancyID">Tenant Occupancy</label>
                <select class="form-select" id="OccupancyID" name="OccupancyID" required>
                    <?php foreach ($occupancies as $row): ?>
                        <option value="<?= (int) $row['OccupancyID'] ?>" <?= (int) $agreement['OccupancyID'] === (int) $row['OccupancyID'] ? 'selected' : '' ?>>
                            <?= e($row['FullName'] . ' · ' . $row['PropertyName'] . ' / ' . $row['UnitNumber']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="AgreementStatus">Status</label>
                <select class="form-select" id="AgreementStatus" name="AgreementStatus">
                    <?php foreach (['Active', 'Draft', 'Expired', 'Terminated'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $agreement['AgreementStatus'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="MonthlyRent">Monthly Rent</label><input type="number" class="form-control" id="MonthlyRent" name="MonthlyRent" min="0" step="0.01" value="<?= e((string) $agreement['MonthlyRent']) ?>" required></div>
            <div class="col-md-3"><label class="form-label" for="SecurityDeposit">Security Deposit</label><input type="number" class="form-control" id="SecurityDeposit" name="SecurityDeposit" min="0" step="0.01" value="<?= e((string) $agreement['SecurityDeposit']) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="AgreementStartDate">Start Date</label><input type="date" class="form-control" id="AgreementStartDate" name="AgreementStartDate" value="<?= e($agreement['AgreementStartDate']) ?>" required></div>
            <div class="col-md-3"><label class="form-label" for="AgreementEndDate">End Date</label><input type="date" class="form-control" id="AgreementEndDate" name="AgreementEndDate" value="<?= e($agreement['AgreementEndDate'] ?? '') ?>"></div>
            <div class="col-md-3"><label class="form-label" for="RentDueDay">Rent Due Day</label><input type="number" class="form-control" id="RentDueDay" name="RentDueDay" min="1" max="28" value="<?= e((string) $agreement['RentDueDay']) ?>"></div>
            <div class="col-md-9"><label class="form-label" for="Remarks">Remarks</label><input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="300" value="<?= e($agreement['Remarks'] ?? '') ?>"></div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update Agreement</button>
            <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
