<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit User';
$currentNav = 'users';
$errors = [];
$roles = user_role_names(db(), false);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid user selected.');
    redirect('modules/users/index.php');
}

$stmt = db()->prepare('SELECT * FROM Users WHERE UserID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch();
if (!$user) {
    flash_set('error', 'User not found.');
    redirect('modules/users/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'FullName' => trim((string) ($_POST['FullName'] ?? '')),
            'Username' => trim((string) ($_POST['Username'] ?? '')),
            'Email' => trim((string) ($_POST['Email'] ?? '')),
            'UserType' => trim((string) ($_POST['UserType'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
            'Password' => (string) ($_POST['Password'] ?? ''),
            'PasswordConfirm' => (string) ($_POST['PasswordConfirm'] ?? ''),
        ];

        if ($data['FullName'] === '' || $data['Username'] === '') {
            $errors[] = 'Full name and username are required.';
        }
        if (strlen($data['Username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if ($data['Email'] !== '' && !filter_var($data['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!in_array($data['UserType'], $roles, true)) {
            $errors[] = 'Invalid user role.';
        }
        if (!in_array($data['Status'], user_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if ($data['Password'] !== '') {
            if (strlen($data['Password']) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($data['Password'] !== $data['PasswordConfirm']) {
                $errors[] = 'Password confirmation does not match.';
            }
        }
        if ((int) $id === (int) ($_SESSION['user_id'] ?? 0) && $data['Status'] !== 'Active') {
            $errors[] = 'You cannot deactivate your own account.';
        }

        if (!$errors) {
            try {
                if ($data['Password'] !== '') {
                    db()->prepare(
                        'UPDATE Users
                         SET FullName = :full_name, Username = :username, Email = :email,
                             UserType = :user_type, Status = :status, PasswordHash = :password_hash
                         WHERE UserID = :id'
                    )->execute([
                        'full_name' => $data['FullName'],
                        'username' => $data['Username'],
                        'email' => $data['Email'] !== '' ? $data['Email'] : null,
                        'user_type' => $data['UserType'],
                        'status' => $data['Status'],
                        'password_hash' => password_hash($data['Password'], PASSWORD_DEFAULT),
                        'id' => $id,
                    ]);
                } else {
                    db()->prepare(
                        'UPDATE Users
                         SET FullName = :full_name, Username = :username, Email = :email,
                             UserType = :user_type, Status = :status
                         WHERE UserID = :id'
                    )->execute([
                        'full_name' => $data['FullName'],
                        'username' => $data['Username'],
                        'email' => $data['Email'] !== '' ? $data['Email'] : null,
                        'user_type' => $data['UserType'],
                        'status' => $data['Status'],
                        'id' => $id,
                    ]);
                }
                flash_set('success', 'User updated successfully.');
                redirect('modules/users/index.php');
            } catch (Throwable $e) {
                error_log('User update failed: ' . $e->getMessage());
                $errors[] = 'Could not update user. Username or email may already exist.';
            }
        }
        $user = array_merge($user, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit User</h1><p class="text-muted mb-0"><?= e($user['FullName']) ?></p></div>
    <a href="<?= e(base_url('modules/users/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label" for="FullName">Full Name</label><input type="text" class="form-control" id="FullName" name="FullName" maxlength="150" value="<?= e($user['FullName']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Username">Username</label><input type="text" class="form-control" id="Username" name="Username" maxlength="100" value="<?= e($user['Username']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Email">Email</label><input type="email" class="form-control" id="Email" name="Email" maxlength="150" value="<?= e($user['Email'] ?? '') ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="UserType">Role</label>
            <select class="form-select" id="UserType" name="UserType">
                <?php foreach ($roles as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $user['UserType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (user_statuses() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $user['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="Password">New Password</label><input type="password" class="form-control" id="Password" name="Password" minlength="8" placeholder="Leave blank to keep current"></div>
        <div class="mb-3"><label class="form-label" for="PasswordConfirm">Confirm New Password</label><input type="password" class="form-control" id="PasswordConfirm" name="PasswordConfirm" minlength="8"></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update User</button>
        <a href="<?= e(base_url('modules/users/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
