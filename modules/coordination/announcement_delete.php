<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Announcement';
$currentNav = 'coord-announcements';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid announcement selected.');
    redirect('modules/coordination/announcements.php');
}

$stmt = db()->prepare('SELECT * FROM Announcements WHERE AnnouncementID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    flash_set('error', 'Announcement not found.');
    redirect('modules/coordination/announcements.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        try {
            db()->prepare('DELETE FROM Announcements WHERE AnnouncementID = :id')->execute(['id' => $id]);
            flash_set('success', 'Announcement deleted.');
            redirect('modules/coordination/announcements.php');
        } catch (Throwable $e) {
            error_log('Announcement delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete announcement.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Announcement</h1><p class="text-muted mb-0"><?= e($row['Title']) ?></p></div>
    <a href="<?= e(base_url('modules/coordination/announcements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <p>Delete this announcement permanently?</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
        <a href="<?= e(base_url('modules/coordination/announcements.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
