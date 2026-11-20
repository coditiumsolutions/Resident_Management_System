<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (users_exist()) {
    redirect('login.php');
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($fullName === '' || $username === '' || $password === '') {
            $error = 'Full name, username and password are required.';
        } elseif (strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Password confirmation does not match.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO Users (FullName, Username, Email, PasswordHash, UserType, Status)
                     VALUES (:full_name, :username, :email, :password_hash, :user_type, :status)'
                );
                $stmt->execute([
                    'full_name' => $fullName,
                    'username' => $username,
                    'email' => $email !== '' ? $email : null,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'user_type' => 'Admin',
                    'status' => 'Active',
                ]);

                flash_set('success', 'Administrator account created. Please sign in.');
                redirect('login.php');
            } catch (PDOException $e) {
                error_log('Setup error: ' . $e->getMessage());
                $error = 'Could not create administrator account. Username or email may already exist.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial Setup | <?= e(APP_SHORT_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark">RP</span>
            <h1 class="h4 mb-1">Initial Setup</h1>
            <p class="mb-0 opacity-75">Create the first administrator account</p>
        </div>
        <div class="auth-body-inner">
            <p class="text-muted small">No users found in the database. Create a secure admin account to continue. A default password will not be inserted for you.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="mb-3">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e($_POST['full_name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           value="<?= e($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email (optional)</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password_confirm">Confirm Password</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-shield-check me-1"></i> Create Administrator
                </button>
            </form>
        </div>
    </div>
</body>
</html>
