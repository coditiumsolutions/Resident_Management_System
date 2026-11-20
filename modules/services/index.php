<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Available Services';
$currentNav = 'services';
$errors = [];
$categories = service_categories(db(), true);

$form = [
    'CategoryID' => '',
    'ServiceName' => '',
    'Description' => '',
    'BillingType' => 'Monthly',
    'StandardRate' => '0.00',
    'Status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($form['ServiceName'] === '') {
            $errors[] = 'Service name is required.';
        }
        if (!in_array($form['BillingType'], service_billing_types(), true)) {
            $errors[] = 'Invalid billing type.';
        }
        if (!is_numeric($form['StandardRate']) || (float) $form['StandardRate'] < 0) {
            $errors[] = 'Standard rate must be a valid amount.';
        }
        if (!in_array($form['Status'], service_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        $categoryId = (int) $form['CategoryID'];
        if ($categoryId <= 0) {
            $errors[] = 'Please select a category.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO ServiceCatalog (CategoryID, ServiceName, Description, BillingType, StandardRate, Status)
                     VALUES (:category_id, :name, :description, :billing_type, :rate, :status)'
                )->execute([
                    'category_id' => $categoryId,
                    'name' => $form['ServiceName'],
                    'description' => $form['Description'] !== '' ? $form['Description'] : null,
                    'billing_type' => $form['BillingType'],
                    'rate' => number_format((float) $form['StandardRate'], 2, '.', ''),
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'Service added to catalog.');
                redirect('modules/services/index.php');
            } catch (Throwable $e) {
                error_log('Service create failed: ' . $e->getMessage());
                $errors[] = 'Could not create service. Name may already exist.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = "SELECT s.*, c.CategoryName,
               (SELECT COUNT(*) FROM UnitServices us WHERE us.ServiceID = s.ServiceID) AS AssignmentCount
        FROM ServiceCatalog s
        LEFT JOIN ServiceCategories c ON c.ServiceCategoryID = s.CategoryID
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (s.ServiceName LIKE :q OR s.Description LIKE :q2 OR c.CategoryName LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
if ($categoryFilter > 0) {
    $sql .= ' AND s.CategoryID = :category_id';
    $params['category_id'] = $categoryFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND s.Status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY c.CategoryName, s.ServiceName';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Available Services</h1>
        <p class="text-muted mb-0">Service catalog rates and billing types</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/services/categories.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-tags me-1"></i> Categories</a>
        <a href="<?= e(base_url('modules/services/resident.php')) ?>" class="btn btn-primary"><i class="bi bi-person-gear me-1"></i> Resident Services</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Add Service</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (!$categories): ?>
                    <div class="alert alert-warning mb-0">Create a service category first.</div>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label" for="CategoryID">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="CategoryID" name="CategoryID" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['ServiceCategoryID'] ?>" <?= (int) $form['CategoryID'] === (int) $cat['ServiceCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ServiceName">Service Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ServiceName" name="ServiceName" maxlength="150" value="<?= e($form['ServiceName']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Description">Description</label>
                            <input type="text" class="form-control" id="Description" name="Description" maxlength="300" value="<?= e($form['Description']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="BillingType">Billing Type</label>
                            <select class="form-select" id="BillingType" name="BillingType">
                                <?php foreach (service_billing_types() as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['BillingType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="StandardRate">Standard Rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="StandardRate" name="StandardRate" value="<?= e($form['StandardRate']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Status">Status</label>
                            <select class="form-select" id="Status" name="Status">
                                <?php foreach (service_statuses() as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Save Service</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Service or category">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="category_id">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All</option>
                            <?php foreach (service_categories(db()) as $cat): ?>
                                <option value="<?= (int) $cat['ServiceCategoryID'] ?>" <?= $categoryFilter === (int) $cat['ServiceCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (service_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
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
                            <th>Service</th>
                            <th>Category</th>
                            <th>Billing</th>
                            <th>Rate</th>
                            <th>Assigned</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$services): ?>
                            <tr><td colspan="7" class="empty-state">No services in catalog yet.</td></tr>
                        <?php else: foreach ($services as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['ServiceName']) ?></strong>
                                    <?php if (!empty($row['Description'])): ?>
                                        <div class="text-muted small"><?= e($row['Description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['CategoryName'] ?: '—') ?></td>
                                <td><?= e($row['BillingType'] ?: '—') ?></td>
                                <td><?= e(format_money($row['StandardRate'])) ?></td>
                                <td><?= e((string) $row['AssignmentCount']) ?></td>
                                <td><span class="badge badge-soft <?= e(service_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/services/service_edit.php?id=' . (int) $row['ServiceID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/services/service_delete.php?id=' . (int) $row['ServiceID'])) ?>"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
