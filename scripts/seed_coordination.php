<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/coordination/_helpers.php';

$pdo = db();

$userId = (int) ($pdo->query("SELECT UserID FROM Users WHERE Status = 'Active' ORDER BY UserID LIMIT 1")->fetchColumn() ?: 0);
if ($userId <= 0) {
    fwrite(STDERR, "No active users found.\n");
    exit(1);
}

$annCount = (int) $pdo->query('SELECT COUNT(*) FROM Announcements')->fetchColumn();
$notCount = (int) $pdo->query('SELECT COUNT(*) FROM Notifications')->fetchColumn();
if ($annCount > 0 && $notCount > 0) {
    echo "Coordination data already exists. Skipping seed.\n";
    exit(0);
}

$units = coord_unit_options($pdo);
$residents = coord_resident_options($pdo);

try {
    $pdo->beginTransaction();

    if ($annCount === 0) {
        $ann = $pdo->prepare(
            'INSERT INTO Announcements
                (Title, Message, TargetType, TargetUnitID, TargetPartyID, TargetUserID, PostedByUserID, PostedDate, ExpiryDate, Status)
             VALUES
                (:title, :message, :target_type, :unit_id, :party_id, NULL, :posted_by, :posted, :expiry, :status)'
        );

        $announcements = [
            ['Water Supply Maintenance', 'Water supply will be interrupted tomorrow from 10 AM to 2 PM for tank cleaning.', 'All Residents', null, null, 'Published', '-1 day'],
            ['Parking Reminder', 'Please park only in assigned slots. Unauthorized vehicles may be clamped.', 'Owners', null, null, 'Published', '-3 days'],
            ['Tenant Meeting', 'Monthly tenants meeting will be held in the lobby on Saturday at 6 PM.', 'Tenants', null, null, 'Published', '-5 days'],
            ['Unit Inspection Notice', 'Scheduled inspection for your unit this week. Kindly ensure access.', 'Specific Unit', $units[0]['UnitID'] ?? null, null, 'Published', '-2 days'],
            ['Draft Festival Notice', 'Eid decoration plan draft for management review.', 'Staff', null, null, 'Draft', '-6 hours'],
        ];

        foreach ($announcements as [$title, $message, $target, $unitId, $partyId, $status, $when]) {
            $ann->execute([
                'title' => $title,
                'message' => $message,
                'target_type' => $target,
                'unit_id' => $unitId,
                'party_id' => $partyId,
                'posted_by' => $userId,
                'posted' => date('Y-m-d H:i:s', strtotime($when)),
                'expiry' => date('Y-m-d H:i:s', strtotime('+15 days')),
                'status' => $status,
            ]);
        }
    }

    if ($notCount === 0) {
        $not = $pdo->prepare(
            'INSERT INTO Notifications
                (Title, Message, Channel, TargetType, TargetUnitID, TargetPartyID, SentByUserID, SentDate, RecipientCount, Status, Remarks)
             VALUES
                (:title, :message, :channel, :target_type, :unit_id, :party_id, :sent_by, :sent, :recipients, :status, :remarks)'
        );

        $notifications = [
            ['Bill Reminder', 'Please clear pending dues before the 10th to avoid late charges.', 'SMS', 'All Residents'],
            ['Gate Timing Update', 'Main gate closes at 11:30 PM. Late entry via side gate.', 'In-App', 'All Residents'],
            ['Owner Circular', 'Ownership document verification drive starts next Monday.', 'Email', 'Owners'],
            ['Staff Briefing', 'Security briefing at 8 AM in control room.', 'Notice Board', 'Staff'],
        ];

        foreach ($notifications as $i => [$title, $message, $channel, $target]) {
            $recipients = coord_estimate_recipients($pdo, $target);
            $not->execute([
                'title' => $title,
                'message' => $message,
                'channel' => $channel,
                'target_type' => $target,
                'unit_id' => null,
                'party_id' => null,
                'sent_by' => $userId,
                'sent' => date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days 09:30:00')),
                'recipients' => $recipients,
                'status' => 'Sent',
                'remarks' => 'Seeded notification',
            ]);
        }

        if ($residents) {
            $resident = $residents[0];
            $not->execute([
                'title' => 'Personal Follow-up',
                'message' => 'Please contact the office regarding your pending service request.',
                'channel' => 'SMS',
                'target_type' => 'Specific Resident',
                'unit_id' => null,
                'party_id' => (int) $resident['PartyID'],
                'sent_by' => $userId,
                'sent' => date('Y-m-d H:i:s', strtotime('-8 hours')),
                'recipients' => 1,
                'status' => 'Sent',
                'remarks' => null,
            ]);
        }
    }

    $pdo->commit();
    echo "Coordination seed completed.\n";
    echo 'Announcements=' . $pdo->query('SELECT COUNT(*) FROM Announcements')->fetchColumn() . PHP_EOL;
    echo 'Notifications=' . $pdo->query('SELECT COUNT(*) FROM Notifications')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
