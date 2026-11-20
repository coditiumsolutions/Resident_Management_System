<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'User Roles';
$currentNav = 'users-roles';
$errors = [];

$form = [
    'RoleName' => '',
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
        if ($form['RoleName'] === '') {
            $errors[] = 'Role name is required.';
        }
        if (!in_array($form['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO Roles (RoleName, Description, Status)
                     VALUES (:name, :description, :status)'
                )->execute([
                    'name' => $form['RoleName'],
                    'description' => $form['Description'] !== '' ? $form['Description'] : null,
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'Role created.');
                redirect('modules/users/roles.php');
            } catch (Throwable $e) {
                error_log('Role create failed: ' . $e->getMessage());
                $errors[] = 'Could not create role. Name may already exist.';
            }
        }
    }
}

$roles = db()->query(
    "SELECT r.*,
            (SELECT COUNT(*) FROM Users u WHERE u.UserType = r.RoleName) AS UserCount
     FROM Roles r
     ORDER BY r.RoleName"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>User Roles</h1>
        <p class="text-muted mb-0">Define login roles used by system users</p>
    </div>
    <a href="<?= e(base_url('modules/users/index.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-person-gear me-1"></i> Users
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Add Role</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="RoleName">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="RoleName" name="RoleName" maxlength="50" value="<?= e($form['RoleName']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Description">Description</label>
                        <input type="text" class="form-control" id="Description" name="Description" maxlength="250" value="<?= e($form['Description']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Status">Status</label>
                        <select class="form-select" id="Status" name="Status">
                            <?php foreach (['Active', 'Inactive'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Save Role</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Role</th>
                            <th>Description</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$roles): ?>
                            <tr><td colspan="5" class="empty-state">No roles defined.</td></tr>
                        <?php else: foreach ($roles as $row): ?>
                            <tr>
                                <td><strong><?= e($row['RoleName']) ?></strong></td>
                                <td><?= e($row['Description'] ?: '—') ?></td>
                                <td><?= e((string) $row['UserCount']) ?></td>
                                <td><span class="badge badge-soft <?= e($row['Status'] === 'Active' ? 'resolved' : 'hold') ?>"><?= e($row['Status']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/users/role_edit.php?id=' . (int) $row['RoleID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/users/role_delete.php?id=' . (int) $row['RoleID'])) ?>"><i class="bi bi-trash"></i></a>
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
