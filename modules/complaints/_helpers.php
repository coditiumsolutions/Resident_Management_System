<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/dashboard_data.php';

function complaint_statuses(): array
{
    return ['New', 'Assigned', 'In Progress', 'On Hold', 'Resolved', 'Closed'];
}

function complaint_priorities(): array
{
    return ['Low', 'Normal', 'High', 'Urgent'];
}

function complaint_pending_statuses(): array
{
    return ['New', 'Assigned', 'In Progress', 'On Hold'];
}

function complaint_resolved_statuses(): array
{
    return ['Resolved', 'Closed'];
}

function complaint_categories(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM ComplaintCategories';
    if ($activeOnly) {
        $sql .= " WHERE Status = 'Active'";
    }
    $sql .= ' ORDER BY CategoryName';

    return $pdo->query($sql)->fetchAll();
}

function complaint_unit_options(PDO $pdo): array
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

function complaint_format_unit_label(array $row): string
{
    return sprintf(
        '%s / Unit %s · %s',
        $row['PropertyName'],
        $row['UnitNumber'],
        $row['PartyName']
    );
}

function complaint_users(PDO $pdo): array
{
    return $pdo->query(
        "SELECT UserID, FullName, Username
         FROM Users
         WHERE Status = 'Active' OR Status IS NULL OR Status = ''
         ORDER BY FullName, Username"
    )->fetchAll();
}

function complaint_next_no(PDO $pdo): string
{
    $prefix = 'CMP-' . date('Ym') . '-';
    $stmt = $pdo->prepare('SELECT ComplaintNo FROM Complaints WHERE ComplaintNo LIKE :prefix ORDER BY ComplaintID DESC LIMIT 1');
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $seq = ((int) $m[1]) + 1;
    }

    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function complaint_list_sql(string $extraWhere = ''): string
{
    return "SELECT c.*,
                   cat.CategoryName,
                   party.FullName AS PartyName,
                   u.UnitNumber,
                   b.BlockName,
                   p.PropertyName,
                   assignee.FullName AS AssignedToName
            FROM Complaints c
            INNER JOIN ComplaintCategories cat ON cat.ComplaintCategoryID = c.CategoryID
            INNER JOIN Parties party ON party.PartyID = c.PartyID
            INNER JOIN Units u ON u.UnitID = c.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            INNER JOIN Properties p ON p.PropertyID = b.PropertyID
            LEFT JOIN Users assignee ON assignee.UserID = c.AssignedToUserID
            WHERE 1=1 {$extraWhere}";
}

function complaint_add_update(
    PDO $pdo,
    int $complaintId,
    int $userId,
    ?string $previousStatus,
    string $newStatus,
    ?string $remarks = null
): void {
    $pdo->prepare(
        'INSERT INTO ComplaintUpdates
            (ComplaintID, UpdatedByUserID, PreviousStatus, NewStatus, Remarks)
         VALUES
            (:complaint_id, :user_id, :prev, :new, :remarks)'
    )->execute([
        'complaint_id' => $complaintId,
        'user_id' => $userId,
        'prev' => $previousStatus,
        'new' => $newStatus,
        'remarks' => $remarks,
    ]);
}

function complaint_apply_status(PDO $pdo, int $complaintId, string $newStatus, int $userId, ?string $remarks = null): void
{
    $stmt = $pdo->prepare('SELECT Status FROM Complaints WHERE ComplaintID = :id LIMIT 1');
    $stmt->execute(['id' => $complaintId]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        throw new RuntimeException('Complaint not found.');
    }

    if (in_array($newStatus, complaint_resolved_statuses(), true)) {
        $pdo->prepare(
            'UPDATE Complaints
             SET Status = :status, ResolvedDate = COALESCE(ResolvedDate, :resolved)
             WHERE ComplaintID = :id'
        )->execute([
            'status' => $newStatus,
            'resolved' => date('Y-m-d H:i:s'),
            'id' => $complaintId,
        ]);
    } else {
        $pdo->prepare(
            'UPDATE Complaints SET Status = :status, ResolvedDate = NULL WHERE ComplaintID = :id'
        )->execute([
            'status' => $newStatus,
            'id' => $complaintId,
        ]);
    }

    complaint_add_update($pdo, $complaintId, $userId, (string) $current, $newStatus, $remarks);
}
