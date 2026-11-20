<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete User';
$currentNav = 'users';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid user selected.');
    redirect('modules/users/index.php');
}

if ((int) $id === (int) ($_SESSION['user_id'] ?? 0)) {
    flash_set('error', 'You cannot delete your own account.');
    redirect('modules/users/index.php');
}

$stmt = db()->prepare('SELECT * FROM Users WHERE UserID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();
if (!$user) {
    flash_set('error', 'User not found.');
    redirect('modules/users/index.php');
}

$linkStmt = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM Announcements WHERE PostedByUserID = :id1) AS c1,
        (SELECT COUNT(*) FROM Notifications WHERE SentByUserID = :id2) AS c2,
        (SELECT COUNT(*) FROM Complaints WHERE AssignedToUserID = :id3) AS c3,
        (SELECT COUNT(*) FROM ComplaintUpdates WHERE UpdatedByUserID = :id4) AS c4,
        (SELECT COUNT(*) FROM Payments WHERE ReceivedByUserID = :id5) AS c5,
        (SELECT COUNT(*) FROM Visits WHERE ApprovedByUserID = :id6) AS c6"
);
$linkStmt->execute([
    'id1' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id, 'id5' => $id, 'id6' => $id,
]);
$links = $linkStmt->fetch() ?: [];
$linked = array_sum(array_map('intval', $links));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($linked > 0) {
        $errors[] = 'User has linked records and cannot be deleted. Set status to Inactive instead.';
    } else {
        try {
            db()->prepare('DELETE FROM Users WHERE UserID = :id')->execute(['id' => $id]);
            flash_set('success', 'User deleted.');
            redirect('modules/users/index.php');
        } catch (Throwable $e) {
            error_log('User delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete user. Set status to Inactive instead.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete User</h1><p class="text-muted mb-0"><?= e($user['FullName']) ?> (<?= e($user['Username']) ?>)</p></div>
    <a href="<?= e(base_url('modules/users/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($linked > 0): ?>
        <div class="alert alert-warning mb-0">This user has linked operational records and cannot be deleted. Edit the user and set status to Inactive.</div>
    <?php else: ?>
        <p>Delete this user account permanently?</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/users/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
