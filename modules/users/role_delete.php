<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Role';
$currentNav = 'users-roles';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid role selected.');
    redirect('modules/users/roles.php');
}

$stmt = db()->prepare('SELECT * FROM Roles WHERE RoleID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$role = $stmt->fetch();
if (!$role) {
    flash_set('error', 'Role not found.');
    redirect('modules/users/roles.php');
}

$userCount = user_count_by_role(db(), (string) $role['RoleName']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($userCount > 0) {
        $errors[] = 'Role is assigned to users and cannot be deleted.';
    } elseif ($role['RoleName'] === 'Admin') {
        $errors[] = 'The Admin role cannot be deleted.';
    } else {
        try {
            db()->prepare('DELETE FROM Roles WHERE RoleID = :id')->execute(['id' => $id]);
            flash_set('success', 'Role deleted.');
            redirect('modules/users/roles.php');
        } catch (Throwable $e) {
            error_log('Role delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete role.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Role</h1><p class="text-muted mb-0"><?= e($role['RoleName']) ?></p></div>
    <a href="<?= e(base_url('modules/users/roles.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($userCount > 0 || $role['RoleName'] === 'Admin'): ?>
        <div class="alert alert-warning mb-0">
            <?= $role['RoleName'] === 'Admin' ? 'The Admin role cannot be deleted.' : ('This role is assigned to ' . $userCount . ' user(s). Reassign them first.') ?>
        </div>
    <?php else: ?>
        <p>Delete this role permanently?</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/users/roles.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
