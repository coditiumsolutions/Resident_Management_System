<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if (!users_exist()) {
    redirect('setup.php');
}

if (is_logged_in()) {
    redirect('dashboard/');
}

redirect('login.php');
