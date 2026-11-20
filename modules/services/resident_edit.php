<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Resident Service';
$currentNav = 'services-resident';
$errors = [];
$catalog = service_catalog(db());

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid assignment selected.');
    redirect('modules/services/resident.php');
}

$stmt = db()->prepare('SELECT * FROM UnitServices WHERE UnitServiceID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    flash_set('error', 'Assignment not found.');
    redirect('modules/services/resident.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'ServiceID' => trim((string) ($_POST['ServiceID'] ?? '')),
            'StartDate' => trim((string) ($_POST['StartDate'] ?? '')),
            'EndDate' => trim((string) ($_POST['EndDate'] ?? '')),
            'AgreedRate' => trim((string) ($_POST['AgreedRate'] ?? '')),
            'Quantity' => trim((string) ($_POST['Quantity'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
        ];
        if ((int) $data['ServiceID'] <= 0) {
            $errors[] = 'Please select a service.';
        }
        if ($data['StartDate'] === '') {
            $errors[] = 'Start date is required.';
        }
        if ($data['AgreedRate'] === '' || !is_numeric($data['AgreedRate']) || (float) $data['AgreedRate'] < 0) {
            $errors[] = 'Agreed rate must be a valid amount.';
        }
        if ($data['Quantity'] === '' || !is_numeric($data['Quantity']) || (float) $data['Quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if (!in_array($data['Status'], service_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE UnitServices
                     SET ServiceID = :service_id, StartDate = :start_date, EndDate = :end_date,
                         AgreedRate = :rate, Quantity = :qty, Status = :status
                     WHERE UnitServiceID = :id'
                )->execute([
                    'service_id' => (int) $data['ServiceID'],
                    'start_date' => $data['StartDate'],
                    'end_date' => $data['EndDate'] !== '' ? $data['EndDate'] : null,
                    'rate' => number_format((float) $data['AgreedRate'], 2, '.', ''),
                    'qty' => number_format((float) $data['Quantity'], 2, '.', ''),
                    'status' => $data['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Resident service updated.');
                redirect('modules/services/resident.php');
            } catch (Throwable $e) {
                error_log('Resident service update failed: ' . $e->getMessage());
                $errors[] = 'Could not update assignment.';
            }
        }
        $row = array_merge($row, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Resident Service</h1><p class="text-muted mb-0">Assignment #<?= e((string) $id) ?></p></div>
    <a href="<?= e(base_url('modules/services/resident.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <label class="form-label" for="ServiceID">Service</label>
            <select class="form-select" id="ServiceID" name="ServiceID" required>
                <?php foreach ($catalog as $svc): ?>
                    <option value="<?= (int) $svc['ServiceID'] ?>" <?= (int) $row['ServiceID'] === (int) $svc['ServiceID'] ? 'selected' : '' ?>>
                        <?= e(($svc['CategoryName'] ? $svc['CategoryName'] . ' · ' : '') . $svc['ServiceName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="AgreedRate">Agreed Rate</label><input type="number" step="0.01" min="0" class="form-control" id="AgreedRate" name="AgreedRate" value="<?= e((string) $row['AgreedRate']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Quantity">Quantity</label><input type="number" step="0.01" min="0.01" class="form-control" id="Quantity" name="Quantity" value="<?= e((string) $row['Quantity']) ?>"></div>
        <div class="mb-3"><label class="form-label" for="StartDate">Start Date</label><input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= e($row['StartDate']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="EndDate">End Date</label><input type="date" class="form-control" id="EndDate" name="EndDate" value="<?= e($row['EndDate'] ?? '') ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (service_statuses() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $row['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
        <a href="<?= e(base_url('modules/services/resident.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
