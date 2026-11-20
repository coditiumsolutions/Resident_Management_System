<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/users/_helpers.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn() > 1) {
    echo "Additional users already exist. Skipping user seed.\n";
    exit(0);
}

$users = [
    ['Ready Plaza Manager', 'manager', 'manager@readyplaza.local', 'Manager', 'Manager@12345'],
    ['Billing Desk', 'accountant', 'accounts@readyplaza.local', 'Accountant', 'Accounts@12345'],
    ['Front Desk', 'reception', 'reception@readyplaza.local', 'Reception', 'Reception@12345'],
    ['Gate Security', 'security', 'security@readyplaza.local', 'Security', 'Security@12345'],
];

try {
    $insert = $pdo->prepare(
        'INSERT INTO Users (PartyID, FullName, Username, Email, PasswordHash, UserType, Status)
         VALUES (NULL, :full_name, :username, :email, :password_hash, :user_type, :status)'
    );

    foreach ($users as [$name, $username, $email, $role, $password]) {
        $exists = $pdo->prepare('SELECT UserID FROM Users WHERE Username = :u LIMIT 1');
        $exists->execute(['u' => $username]);
        if ($exists->fetch()) {
            continue;
        }
        $insert->execute([
            'full_name' => $name,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'user_type' => $role,
            'status' => 'Active',
        ]);
    }

    echo "User seed completed.\n";
    echo 'Users=' . $pdo->query('SELECT COUNT(*) FROM Users')->fetchColumn() . PHP_EOL;
    echo 'Roles=' . $pdo->query('SELECT COUNT(*) FROM Roles')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
