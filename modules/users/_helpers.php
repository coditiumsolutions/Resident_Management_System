<?php

declare(strict_types=1);

function user_roles(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM Roles';
    if ($activeOnly) {
        $sql .= " WHERE Status = 'Active'";
    }
    $sql .= ' ORDER BY RoleName';

    return $pdo->query($sql)->fetchAll();
}

function user_statuses(): array
{
    return ['Active', 'Inactive', 'Suspended'];
}

function user_role_names(PDO $pdo, bool $activeOnly = true): array
{
    return array_column(user_roles($pdo, $activeOnly), 'RoleName');
}

function user_status_badge(string $status): string
{
    return match ($status) {
        'Active' => 'resolved',
        'Inactive' => 'hold',
        'Suspended' => 'urgent',
        default => 'new',
    };
}

function user_count_by_role(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM Users WHERE UserType = :role');
    $stmt->execute(['role' => $roleName]);

    return (int) $stmt->fetchColumn();
}
