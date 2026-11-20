<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Service Categories';
$currentNav = 'services-categories';
$errors = [];

$form = [
    'CategoryName' => '',
    'Description' => '',
    'Status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($form['CategoryName'] === '') {
            $errors[] = 'Category name is required.';
        }
        if (!in_array($form['Status'], service_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO ServiceCategories (CategoryName, Description, Status)
                     VALUES (:name, :description, :status)'
                )->execute([
                    'name' => $form['CategoryName'],
                    'description' => $form['Description'] !== '' ? $form['Description'] : null,
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'Service category created.');
                redirect('modules/services/categories.php');
            } catch (Throwable $e) {
                error_log('Service category create failed: ' . $e->getMessage());
                $errors[] = 'Could not create category. Name may already exist.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT c.*,
               (SELECT COUNT(*) FROM ServiceCatalog s WHERE s.CategoryID = c.ServiceCategoryID) AS ServiceCount
        FROM ServiceCategories c WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (c.CategoryName LIKE :q OR c.Description LIKE :q2)';
    $like = '%' . $search . '%';
    $params = ['q' => $like, 'q2' => $like];
}
$sql .= ' ORDER BY c.CategoryName';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Service Categories</h1>
        <p class="text-muted mb-0">Group available services by category</p>
    </div>
    <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-list-check me-1"></i> Available Services
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Add Category</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="CategoryName">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="CategoryName" name="CategoryName" maxlength="100" value="<?= e($form['CategoryName']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Description">Description</label>
                        <input type="text" class="form-control" id="Description" name="Description" maxlength="250" value="<?= e($form['Description']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Status">Status</label>
                        <select class="form-select" id="Status" name="Status">
                            <?php foreach (service_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Save Category</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Category name or description">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary w-100" type="submit">Search</button>
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
                            <th>Category</th>
                            <th>Description</th>
                            <th>Services</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$categories): ?>
                            <tr><td colspan="5" class="empty-state">No service categories yet.</td></tr>
                        <?php else: foreach ($categories as $row): ?>
                            <tr>
                                <td><strong><?= e($row['CategoryName']) ?></strong></td>
                                <td><?= e($row['Description'] ?: '—') ?></td>
                                <td><?= e((string) $row['ServiceCount']) ?></td>
                                <td><span class="badge badge-soft <?= e(service_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/services/category_edit.php?id=' . (int) $row['ServiceCategoryID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/services/category_delete.php?id=' . (int) $row['ServiceCategoryID'])) ?>"><i class="bi bi-trash"></i></a>
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
