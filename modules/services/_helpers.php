<?php

declare(strict_types=1);

function service_billing_types(): array
{
    return ['Monthly', 'Quarterly', 'Yearly', 'One-time', 'As Needed'];
}

function service_statuses(): array
{
    return ['Active', 'Inactive'];
}

function service_categories(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM ServiceCategories';
    if ($activeOnly) {
        $sql .= " WHERE Status = 'Active'";
    }
    $sql .= ' ORDER BY CategoryName';

    return $pdo->query($sql)->fetchAll();
}

function service_catalog(PDO $pdo, bool $activeOnly = false): array
{
    $sql = "SELECT s.*, c.CategoryName
            FROM ServiceCatalog s
            LEFT JOIN ServiceCategories c ON c.ServiceCategoryID = s.CategoryID";
    if ($activeOnly) {
        $sql .= " WHERE s.Status = 'Active'";
    }
    $sql .= ' ORDER BY c.CategoryName, s.ServiceName';

    return $pdo->query($sql)->fetchAll();
}

function service_unit_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT u.UnitID, u.UnitNumber, b.BlockName, p.PropertyName,
                oc.PartyID, party.FullName AS PartyName
         FROM Units u
         INNER JOIN Floors f ON f.FloorID = u.FloorID
         INNER JOIN Blocks b ON b.BlockID = f.BlockID
         INNER JOIN Properties p ON p.PropertyID = b.PropertyID
         INNER JOIN UnitOccupancies oc ON oc.UnitID = u.UnitID
            AND oc.Status = 'Active'
            AND oc.IsPrimary = 1
            AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
         INNER JOIN Parties party ON party.PartyID = oc.PartyID
         ORDER BY p.PropertyName, u.UnitNumber"
    )->fetchAll();
}

function service_format_unit_label(array $row): string
{
    return sprintf(
        '%s / Unit %s · %s',
        $row['PropertyName'],
        $row['UnitNumber'],
        $row['PartyName']
    );
}

function service_status_badge(string $status): string
{
    return match ($status) {
        'Active' => 'resolved',
        'Inactive' => 'hold',
        default => 'new',
    };
}
