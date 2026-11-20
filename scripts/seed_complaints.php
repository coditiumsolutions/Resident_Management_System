<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/complaints/_helpers.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM Complaints')->fetchColumn() > 0) {
    echo "Complaints already exist. Skipping complaint seed.\n";
    exit(0);
}

$units = complaint_unit_options($pdo);
if (!$units) {
    fwrite(STDERR, "No occupied units found. Seed residents first.\n");
    exit(1);
}

$userId = (int) ($pdo->query("SELECT UserID FROM Users WHERE Status = 'Active' ORDER BY UserID LIMIT 1")->fetchColumn() ?: 0);
if ($userId <= 0) {
    $userId = (int) ($pdo->query('SELECT UserID FROM Users ORDER BY UserID LIMIT 1')->fetchColumn() ?: 0);
}
if ($userId <= 0) {
    fwrite(STDERR, "No users found for complaint updates.\n");
    exit(1);
}

$categories = [
    ['Plumbing', 'Water leaks, drainage and plumbing issues'],
    ['Electrical', 'Power, wiring and fixture issues'],
    ['Cleaning', 'Housekeeping and cleanliness complaints'],
    ['Security', 'Gate, parking and security concerns'],
    ['Noise', 'Noise disturbance complaints'],
    ['General', 'General building and facility issues'],
];

$samples = [
    ['Plumbing', 'Kitchen sink leakage', 'Water dripping under the kitchen sink since yesterday.', 'High', 'New'],
    ['Electrical', 'Corridor light not working', 'Main corridor light near unit entrance is out.', 'Normal', 'Assigned'],
    ['Cleaning', 'Garbage not collected', 'Common area garbage was not picked today morning.', 'Normal', 'In Progress'],
    ['Security', 'Visitor gate delay', 'Gate staff delayed visitor entry for 20 minutes.', 'Low', 'On Hold'],
    ['Noise', 'Late night construction noise', 'Drilling noise from nearby unit after 11 PM.', 'Urgent', 'In Progress'],
    ['General', 'Lobby AC not cooling', 'Lobby air conditioner is blowing warm air.', 'High', 'Resolved'],
    ['Plumbing', 'Bathroom drain clogged', 'Bathroom drain is blocked and overflowing.', 'Urgent', 'Closed'],
    ['Electrical', 'Intercom not working', 'Unit intercom has no dial tone.', 'Normal', 'New'],
    ['Cleaning', 'Staircase dirty', 'Staircase between floors needs cleaning.', 'Low', 'Assigned'],
    ['General', 'Parking barrier stuck', 'Vehicle barrier at entrance is stuck open.', 'High', 'Resolved'],
];

try {
    $pdo->beginTransaction();

    $catInsert = $pdo->prepare(
        'INSERT INTO ComplaintCategories (CategoryName, Description, Status)
         VALUES (:name, :description, :status)'
    );
    $catIds = [];
    foreach ($categories as [$name, $desc]) {
        $existing = $pdo->prepare('SELECT ComplaintCategoryID FROM ComplaintCategories WHERE CategoryName = :name LIMIT 1');
        $existing->execute(['name' => $name]);
        $id = $existing->fetchColumn();
        if ($id) {
            $catIds[$name] = (int) $id;
            continue;
        }
        $catInsert->execute([
            'name' => $name,
            'description' => $desc,
            'status' => 'Active',
        ]);
        $catIds[$name] = (int) $pdo->lastInsertId();
    }

    $insert = $pdo->prepare(
        'INSERT INTO Complaints
            (ComplaintNo, PartyID, UnitID, CategoryID, Subject, Description, Priority, Status, AssignedToUserID, CreatedDate, ResolvedDate)
         VALUES
            (:no, :party_id, :unit_id, :category_id, :subject, :description, :priority, :status, :assigned_to, :created, :resolved)'
    );

    foreach ($samples as $i => [$cat, $subject, $desc, $priority, $status]) {
        $unit = $units[$i % count($units)];
        $created = date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days 10:15:00'));
        $resolved = in_array($status, ['Resolved', 'Closed'], true)
            ? date('Y-m-d H:i:s', strtotime('-' . $i . ' days 16:40:00'))
            : null;
        $assigned = in_array($status, ['New'], true) ? null : $userId;
        $no = 'CMP-' . date('Ym', strtotime($created)) . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

        $insert->execute([
            'no' => $no,
            'party_id' => (int) $unit['PartyID'],
            'unit_id' => (int) $unit['UnitID'],
            'category_id' => $catIds[$cat],
            'subject' => $subject,
            'description' => $desc,
            'priority' => $priority,
            'status' => $status,
            'assigned_to' => $assigned,
            'created' => $created,
            'resolved' => $resolved,
        ]);

        $complaintId = (int) $pdo->lastInsertId();
        complaint_add_update($pdo, $complaintId, $userId, null, 'New', 'Complaint registered');
        if ($status !== 'New') {
            complaint_add_update($pdo, $complaintId, $userId, 'New', $status, 'Status updated during seed');
        }
    }

    $pdo->commit();
    echo "Complaint seed completed.\n";
    echo 'Categories=' . $pdo->query('SELECT COUNT(*) FROM ComplaintCategories')->fetchColumn() . PHP_EOL;
    echo 'Complaints=' . $pdo->query('SELECT COUNT(*) FROM Complaints')->fetchColumn() . PHP_EOL;
    echo 'Updates=' . $pdo->query('SELECT COUNT(*) FROM ComplaintUpdates')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
