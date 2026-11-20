<?php

declare(strict_types=1);

function coord_target_types(): array
{
    return ['All Residents', 'Owners', 'Tenants', 'Specific Unit', 'Specific Resident', 'Staff'];
}

function coord_announcement_statuses(): array
{
    return ['Published', 'Draft', 'Expired', 'Archived'];
}

function coord_notification_channels(): array
{
    return ['In-App', 'Email', 'SMS', 'Notice Board'];
}

function coord_notification_statuses(): array
{
    return ['Sent', 'Queued', 'Failed', 'Cancelled'];
}

function coord_unit_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT u.UnitID, u.UnitNumber, b.BlockName, p.PropertyName
         FROM Units u
         INNER JOIN Floors f ON f.FloorID = u.FloorID
         INNER JOIN Blocks b ON b.BlockID = f.BlockID
         INNER JOIN Properties p ON p.PropertyID = b.PropertyID
         ORDER BY p.PropertyName, u.UnitNumber"
    )->fetchAll();
}

function coord_resident_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT party.PartyID, party.FullName, u.UnitNumber, oc.OccupancyType
         FROM Parties party
         INNER JOIN UnitOccupancies oc ON oc.PartyID = party.PartyID
            AND oc.Status = 'Active'
            AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
         INNER JOIN Units u ON u.UnitID = oc.UnitID
         ORDER BY party.FullName"
    )->fetchAll();
}

function coord_estimate_recipients(PDO $pdo, string $targetType, ?int $unitId = null, ?int $partyId = null): int
{
    return match ($targetType) {
        'All Residents' => (int) $pdo->query(
            "SELECT COUNT(DISTINCT PartyID) FROM UnitOccupancies
             WHERE Status = 'Active' AND (EndDate IS NULL OR EndDate >= CURDATE())"
        )->fetchColumn(),
        'Owners' => (int) $pdo->query(
            "SELECT COUNT(DISTINCT OwnerPartyID) FROM UnitOwnerships
             WHERE EndDate IS NULL OR EndDate >= CURDATE()"
        )->fetchColumn(),
        'Tenants' => (int) $pdo->query(
            "SELECT COUNT(DISTINCT PartyID) FROM UnitOccupancies
             WHERE Status = 'Active' AND OccupancyType = 'Tenant'
               AND (EndDate IS NULL OR EndDate >= CURDATE())"
        )->fetchColumn(),
        'Staff' => (int) $pdo->query("SELECT COUNT(*) FROM Users WHERE Status = 'Active'")->fetchColumn(),
        'Specific Unit' => $unitId ? 1 : 0,
        'Specific Resident' => $partyId ? 1 : 0,
        default => 0,
    };
}

function coord_status_badge(string $status): string
{
    return match ($status) {
        'Published', 'Sent' => 'resolved',
        'Draft', 'Queued' => 'new',
        'Expired', 'Archived', 'Cancelled' => 'hold',
        'Failed' => 'urgent',
        default => 'progress',
    };
}
