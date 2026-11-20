<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Users';
$currentNav = 'users';
$errors = [];
$roles = user_role_names(db(), true);

$form = [
    'FullName' => '',
    'Username' => '',
    'Email' => '',
    'UserType' => $roles[0] ?? 'Staff',
    'Status' => 'Active',
    'Password' => '',
    'PasswordConfirm' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($form['FullName'] === '' || $form['Username'] === '') {
            $errors[] = 'Full name and username are required.';
        }
        if (strlen($form['Username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if ($form['Email'] !== '' && !filter_var($form['Email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!in_array($form['UserType'], $roles, true)) {
            $errors[] = 'Invalid user role.';
        }
        if (!in_array($form['Status'], user_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if (strlen($form['Password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($form['Password'] !== $form['PasswordConfirm']) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO Users (PartyID, FullName, Username, Email, PasswordHash, UserType, Status)
                     VALUES (NULL, :full_name, :username, :email, :password_hash, :user_type, :status)'
                )->execute([
                    'full_name' => $form['FullName'],
                    'username' => $form['Username'],
                    'email' => $form['Email'] !== '' ? $form['Email'] : null,
                    'password_hash' => password_hash($form['Password'], PASSWORD_DEFAULT),
                    'user_type' => $form['UserType'],
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'User created successfully.');
                redirect('modules/users/index.php');
            } catch (Throwable $e) {
                error_log('User create failed: ' . $e->getMessage());
                $errors[] = 'Could not create user. Username or email may already exist.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = trim((string) ($_GET['role'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = 'SELECT UserID, FullName, Username, Email, UserType, Status, CreatedDate FROM Users WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (FullName LIKE :q OR Username LIKE :q2 OR Email LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}
if ($roleFilter !== '') {
    $sql .= ' AND UserType = :role';
    $params['role'] = $roleFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND Status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY FullName, Username';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Users</h1>
        <p class="text-muted mb-0">Manage system login accounts</p>
    </div>
    <a href="<?= e(base_url('modules/users/roles.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-key me-1"></i> User Roles
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Add User</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (!$roles): ?>
                    <div class="alert alert-warning mb-0">Create a user role first.</div>
                <?php else: ?>
                    <form method="post" novalidate autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label" for="FullName">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="FullName" name="FullName" maxlength="150" value="<?= e($form['FullName']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Username">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Username" name="Username" maxlength="100" value="<?= e($form['Username']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Email">Email</label>
                            <input type="email" class="form-control" id="Email" name="Email" maxlength="150" value="<?= e($form['Email']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="UserType">Role</label>
                            <select class="form-select" id="UserType" name="UserType">
                                <?php foreach ($roles as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['UserType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Status">Status</label>
                            <select class="form-select" id="Status" name="Status">
                                <?php foreach (user_statuses() as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Password">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="Password" name="Password" minlength="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="PasswordConfirm">Confirm Password</label>
                            <input type="password" class="form-control" id="PasswordConfirm" name="PasswordConfirm" minlength="8" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i> Create User</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, username, email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">All</option>
                            <?php foreach (user_role_names(db(), false) as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $roleFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (user_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
                </form>
            </div>
        </div>

        <div class="card panel-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$users): ?>
                            <tr><td colspan="6" class="empty-state">No users found.</td></tr>
                        <?php else: foreach ($users as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['FullName']) ?></strong>
                                    <div class="text-muted small"><?= e($row['Email'] ?: '') ?></div>
                                </td>
                                <td><?= e($row['Username']) ?></td>
                                <td><?= e($row['UserType']) ?></td>
                                <td><span class="badge badge-soft <?= e(user_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td><?= e(format_datetime($row['CreatedDate'])) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/users/user_edit.php?id=' . (int) $row['UserID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <?php if ((int) $row['UserID'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                        <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/users/user_delete.php?id=' . (int) $row['UserID'])) ?>"><i class="bi bi-trash"></i></a>
                                    <?php endif; ?>
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
