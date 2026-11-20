<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Visitor';
$currentNav = 'visitors-register';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid visitor selected.');
    redirect('modules/visitors/register.php');
}

$stmt = db()->prepare('SELECT * FROM Visitors WHERE VisitorID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$visitor = $stmt->fetch();
if (!$visitor) {
    flash_set('error', 'Visitor not found.');
    redirect('modules/visitors/register.php');
}

$countStmt = db()->prepare('SELECT COUNT(*) FROM Visits WHERE VisitorID = :id');
$countStmt->execute(['id' => $id]);
$visitCount = (int) $countStmt->fetchColumn();

$activeStmt = db()->prepare("SELECT COUNT(*) FROM Visits WHERE VisitorID = :id AND VisitStatus = 'Checked In'");
$activeStmt->execute(['id' => $id]);
$activeCount = (int) $activeStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($activeCount > 0) {
        $errors[] = 'Visitor is currently checked in and cannot be deleted.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM Visits WHERE VisitorID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Visitors WHERE VisitorID = :id')->execute(['id' => $id]);
            $pdo->commit();
            flash_set('success', 'Visitor deleted successfully.');
            redirect('modules/visitors/register.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Visitor delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete visitor.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Visitor</h1><p class="text-muted mb-0"><?= e($visitor['VisitorName']) ?></p></div>
    <a href="<?= e(base_url('modules/visitors/register.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="alert alert-warning">This will also delete <?= e((string) $visitCount) ?> related visit record(s).</div>
    <?php if ($activeCount > 0): ?>
        <div class="alert alert-danger mb-0">Visitor is currently checked in. Check out first.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/visitors/register.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
