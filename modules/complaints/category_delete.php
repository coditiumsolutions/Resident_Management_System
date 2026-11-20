<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Complaint Category';
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

$countStmt = db()->prepare('SELECT COUNT(*) FROM Complaints WHERE CategoryID = :id');
$countStmt->execute(['id' => $id]);
$complaintCount = (int) $countStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($complaintCount > 0) {
        $errors[] = 'Category has linked complaints and cannot be deleted.';
    } else {
        try {
            db()->prepare('DELETE FROM ComplaintCategories WHERE ComplaintCategoryID = :id')->execute(['id' => $id]);
            flash_set('success', 'Complaint category deleted.');
            redirect('modules/complaints/categories.php');
        } catch (Throwable $e) {
            error_log('Complaint category delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete category.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Complaint Category</h1><p class="text-muted mb-0"><?= e($category['CategoryName']) ?></p></div>
    <a href="<?= e(base_url('modules/complaints/categories.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($complaintCount > 0): ?>
        <div class="alert alert-warning mb-0">This category has <?= e((string) $complaintCount) ?> complaint(s). Reassign them first.</div>
    <?php else: ?>
        <p>Delete this category permanently?</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/complaints/categories.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
