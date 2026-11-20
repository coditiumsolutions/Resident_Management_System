<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!users_exist()) {
    redirect('setup.php');
}

if (is_logged_in()) {
    redirect('dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid session token. Please try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username and password are required.';
        } else {
            $stmt = db()->prepare(
                'SELECT UserID, FullName, Username, PasswordHash, UserType, Status
                 FROM Users
                 WHERE Username = :username
                 LIMIT 1'
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            if (!$user || ($user['Status'] ?? '') !== 'Active' || !password_verify($password, $user['PasswordHash'])) {
                $error = 'Invalid username or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['UserID'];
                $_SESSION['user_name'] = $user['FullName'];
                $_SESSION['user_type'] = $user['UserType'];

                $redirect = $_SESSION['redirect_after_login'] ?? base_url('dashboard/');
                unset($_SESSION['redirect_after_login']);

                if (str_starts_with($redirect, 'http')) {
                    $redirect = base_url('dashboard/');
                }

                header('Location: ' . $redirect);
                exit;
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
    <title>Login | <?= e(APP_SHORT_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="auth-head">
            <span class="brand-mark">RP</span>
            <h1 class="h4 mb-1"><?= e(APP_SHORT_NAME) ?></h1>
            <p class="mb-0 opacity-75">Property Services Management System</p>
        </div>
        <div class="auth-body-inner">
            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
