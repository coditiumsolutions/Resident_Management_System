<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Service';
$currentNav = 'services';
$errors = [];

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

$countStmt = db()->prepare('SELECT COUNT(*) FROM UnitServices WHERE ServiceID = :id');
$countStmt->execute(['id' => $id]);
$assignCount = (int) $countStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($assignCount > 0) {
        $errors[] = 'Service is assigned to residents and cannot be deleted.';
    } else {
        try {
            db()->prepare('DELETE FROM ServiceCatalog WHERE ServiceID = :id')->execute(['id' => $id]);
            flash_set('success', 'Service deleted.');
            redirect('modules/services/index.php');
        } catch (Throwable $e) {
            error_log('Service delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete service.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Service</h1><p class="text-muted mb-0"><?= e($service['ServiceName']) ?></p></div>
    <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($assignCount > 0): ?>
        <div class="alert alert-warning mb-0">This service has <?= e((string) $assignCount) ?> resident assignment(s). Remove them first.</div>
    <?php else: ?>
        <p>Delete this service from the catalog permanently?</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
