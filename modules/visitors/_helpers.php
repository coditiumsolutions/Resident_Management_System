<?php

declare(strict_types=1);

function visitor_host_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT u.UnitID, u.UnitNumber, b.BlockName, p.PropertyName,
                oc.PartyID, party.FullName AS HostName
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

function visitor_status_badge(string $status): string
{
    return match ($status) {
        'Checked In' => 'resolved',
        'Checked Out' => 'hold',
        'Expected' => 'new',
        'Cancelled' => 'urgent',
        default => 'hold',
    };
}

function visitor_format_host_label(array $row): string
{
    return sprintf(
        '%s / Unit %s · Host: %s',
        $row['PropertyName'],
        $row['UnitNumber'],
        $row['HostName']
    );
}
