<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Role';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'RoleName' => trim((string) ($_POST['RoleName'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
        ];
        if ($data['RoleName'] === '') {
            $errors[] = 'Role name is required.';
        }
        if (!in_array($data['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $oldName = (string) $role['RoleName'];
                $pdo->prepare(
                    'UPDATE Roles SET RoleName = :name, Description = :description, Status = :status WHERE RoleID = :id'
                )->execute([
                    'name' => $data['RoleName'],
                    'description' => $data['Description'] !== '' ? $data['Description'] : null,
                    'status' => $data['Status'],
                    'id' => $id,
                ]);
                if ($oldName !== $data['RoleName']) {
                    $pdo->prepare('UPDATE Users SET UserType = :new WHERE UserType = :old')
                        ->execute(['new' => $data['RoleName'], 'old' => $oldName]);
                }
                $pdo->commit();
                flash_set('success', 'Role updated.');
                redirect('modules/users/roles.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Role update failed: ' . $e->getMessage());
                $errors[] = 'Could not update role. Name may already exist.';
            }
        }
        $role = array_merge($role, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Role</h1><p class="text-muted mb-0"><?= e($role['RoleName']) ?></p></div>
    <a href="<?= e(base_url('modules/users/roles.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label" for="RoleName">Role Name</label><input type="text" class="form-control" id="RoleName" name="RoleName" maxlength="50" value="<?= e($role['RoleName']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Description">Description</label><input type="text" class="form-control" id="Description" name="Description" maxlength="250" value="<?= e($role['Description'] ?? '') ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (['Active', 'Inactive'] as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $role['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
        <a href="<?= e(base_url('modules/users/roles.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
