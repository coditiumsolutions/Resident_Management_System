<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Service';
$currentNav = 'services';
$errors = [];
$categories = service_categories(db());

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid service selected.');
    redirect('modules/services/index.php');
}

$stmt = db()->prepare('SELECT * FROM ServiceCatalog WHERE ServiceID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$service = $stmt->fetch();
if (!$service) {
    flash_set('error', 'Service not found.');
    redirect('modules/services/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'CategoryID' => trim((string) ($_POST['CategoryID'] ?? '')),
            'ServiceName' => trim((string) ($_POST['ServiceName'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'BillingType' => trim((string) ($_POST['BillingType'] ?? '')),
            'StandardRate' => trim((string) ($_POST['StandardRate'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
        ];
        if ($data['ServiceName'] === '') {
            $errors[] = 'Service name is required.';
        }
        if ((int) $data['CategoryID'] <= 0) {
            $errors[] = 'Please select a category.';
        }
        if (!in_array($data['BillingType'], service_billing_types(), true)) {
            $errors[] = 'Invalid billing type.';
        }
        if (!is_numeric($data['StandardRate']) || (float) $data['StandardRate'] < 0) {
            $errors[] = 'Standard rate must be a valid amount.';
        }
        if (!in_array($data['Status'], service_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE ServiceCatalog
                     SET CategoryID = :category_id, ServiceName = :name, Description = :description,
                         BillingType = :billing_type, StandardRate = :rate, Status = :status
                     WHERE ServiceID = :id'
                )->execute([
                    'category_id' => (int) $data['CategoryID'],
                    'name' => $data['ServiceName'],
                    'description' => $data['Description'] !== '' ? $data['Description'] : null,
                    'billing_type' => $data['BillingType'],
                    'rate' => number_format((float) $data['StandardRate'], 2, '.', ''),
                    'status' => $data['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Service updated.');
                redirect('modules/services/index.php');
            } catch (Throwable $e) {
                error_log('Service update failed: ' . $e->getMessage());
                $errors[] = 'Could not update service.';
            }
        }
        $service = array_merge($service, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Service</h1><p class="text-muted mb-0"><?= e($service['ServiceName']) ?></p></div>
    <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3">
            <label class="form-label" for="CategoryID">Category</label>
            <select class="form-select" id="CategoryID" name="CategoryID" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['ServiceCategoryID'] ?>" <?= (int) $service['CategoryID'] === (int) $cat['ServiceCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="ServiceName">Service Name</label><input type="text" class="form-control" id="ServiceName" name="ServiceName" maxlength="150" value="<?= e($service['ServiceName']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Description">Description</label><input type="text" class="form-control" id="Description" name="Description" maxlength="300" value="<?= e($service['Description'] ?? '') ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="BillingType">Billing Type</label>
            <select class="form-select" id="BillingType" name="BillingType">
                <?php foreach (service_billing_types() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $service['BillingType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="StandardRate">Standard Rate</label><input type="number" step="0.01" min="0" class="form-control" id="StandardRate" name="StandardRate" value="<?= e((string) $service['StandardRate']) ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (service_statuses() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $service['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
        <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
