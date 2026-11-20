<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Complaint Category';
$currentNav = 'complaints-categories';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid category selected.');
    redirect('modules/complaints/categories.php');
}

$stmt = db()->prepare('SELECT * FROM ComplaintCategories WHERE ComplaintCategoryID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$category = $stmt->fetch();
if (!$category) {
    flash_set('error', 'Category not found.');
    redirect('modules/complaints/categories.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'CategoryName' => trim((string) ($_POST['CategoryName'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
        ];
        if ($data['CategoryName'] === '') {
            $errors[] = 'Category name is required.';
        }
        if (!in_array($data['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE ComplaintCategories
                     SET CategoryName = :name, Description = :description, Status = :status
                     WHERE ComplaintCategoryID = :id'
                )->execute([
                    'name' => $data['CategoryName'],
                    'description' => $data['Description'] !== '' ? $data['Description'] : null,
                    'status' => $data['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Complaint category updated.');
                redirect('modules/complaints/categories.php');
            } catch (Throwable $e) {
                error_log('Complaint category update failed: ' . $e->getMessage());
                $errors[] = 'Could not update category.';
            }
        }
        $category = array_merge($category, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Complaint Category</h1><p class="text-muted mb-0"><?= e($category['CategoryName']) ?></p></div>
    <a href="<?= e(base_url('modules/complaints/categories.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label" for="CategoryName">Category Name</label><input type="text" class="form-control" id="CategoryName" name="CategoryName" maxlength="100" value="<?= e($category['CategoryName']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Description">Description</label><input type="text" class="form-control" id="Description" name="Description" maxlength="250" value="<?= e($category['Description'] ?? '') ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (['Active', 'Inactive'] as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $category['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
        <a href="<?= e(base_url('modules/complaints/categories.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
