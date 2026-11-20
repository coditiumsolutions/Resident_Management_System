<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/visitors/_helpers.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM Visitors')->fetchColumn() > 0) {
    echo "Visitors already exist. Skipping visitor seed.\n";
    exit(0);
}

$hosts = visitor_host_options($pdo);
if (!$hosts) {
    fwrite(STDERR, "No host residents/units found. Seed residents first.\n");
    exit(1);
}

$people = [
    ['Ali Raza', '35202-4000001-1', '03014560001', 'LEA-101'],
    ['Sana Gul', '35202-4000002-2', '03014560002', 'LED-202'],
    ['Tariq Mehmood', '35202-4000003-3', '03014560003', null],
    ['Fatima Zahra', '35202-4000004-4', '03014560004', 'LES-303'],
    ['Kashif Nadeem', '35202-4000005-5', '03014560005', null],
    ['Nimra Shahid', '35202-4000006-6', '03014560006', 'LEB-404'],
    ['Asad Ullah', '35202-4000007-7', '03014560007', 'LEC-505'],
    ['Hira Yousaf', '35202-4000008-8', '03014560008', null],
    ['Junaid Akram', '35202-4000009-9', '03014560009', 'LER-606'],
    ['Mahnoor Ali', '35202-4000010-0', '03014560010', 'LEF-707'],
];

$purposes = ['Family Visit', 'Delivery', 'Maintenance Work', 'Guest Stay', 'Meeting', 'Courier Pickup'];

try {
    $pdo->beginTransaction();

    $visitorInsert = $pdo->prepare(
        'INSERT INTO Visitors (VisitorName, CNIC, ContactNumber, VehicleNumber, Remarks)
         VALUES (:name, :cnic, :contact, :vehicle, :remarks)'
    );
    $visitInsert = $pdo->prepare(
        'INSERT INTO Visits
            (VisitorID, UnitID, HostPartyID, Purpose, VisitDate, CheckInTime, CheckOutTime, VisitStatus, ApprovedByUserID, Remarks)
         VALUES
            (:visitor_id, :unit_id, :host_id, :purpose, :visit_date, :check_in, :check_out, :status, :approved_by, :remarks)'
    );

    $userId = (int) ($pdo->query('SELECT UserID FROM Users ORDER BY UserID LIMIT 1')->fetchColumn() ?: 0);
    $visitorIds = [];

    foreach ($people as $i => $person) {
        [$name, $cnic, $phone, $vehicle] = $person;
        $visitorInsert->execute([
            'name' => $name,
            'cnic' => $cnic,
            'contact' => $phone,
            'vehicle' => $vehicle,
            'remarks' => 'Registered visitor',
        ]);
        $visitorIds[] = (int) $pdo->lastInsertId();
    }

    // Active checked-in visits
    for ($i = 0; $i < 4; $i++) {
        $host = $hosts[$i % count($hosts)];
        $checkIn = date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' hours'));
        $visitInsert->execute([
            'visitor_id' => $visitorIds[$i],
            'unit_id' => (int) $host['UnitID'],
            'host_id' => (int) $host['PartyID'],
            'purpose' => $purposes[$i % count($purposes)],
            'visit_date' => date('Y-m-d'),
            'check_in' => $checkIn,
            'check_out' => null,
            'status' => 'Checked In',
            'approved_by' => $userId ?: null,
            'remarks' => 'Currently on premises',
        ]);
    }

    // Completed historical visits
    for ($i = 4; $i < 10; $i++) {
        $host = $hosts[$i % count($hosts)];
        $daysAgo = ($i - 3);
        $checkIn = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days 10:00:00"));
        $checkOut = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days 12:30:00"));
        $visitInsert->execute([
            'visitor_id' => $visitorIds[$i],
            'unit_id' => (int) $host['UnitID'],
            'host_id' => (int) $host['PartyID'],
            'purpose' => $purposes[$i % count($purposes)],
            'visit_date' => date('Y-m-d', strtotime("-{$daysAgo} days")),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => 'Checked Out',
            'approved_by' => $userId ?: null,
            'remarks' => 'Visit completed',
        ]);
    }

    // Extra past visits for first few visitors
    for ($i = 0; $i < 3; $i++) {
        $host = $hosts[($i + 2) % count($hosts)];
        $checkIn = date('Y-m-d H:i:s', strtotime('-' . (8 + $i) . ' days 15:00:00'));
        $checkOut = date('Y-m-d H:i:s', strtotime('-' . (8 + $i) . ' days 16:20:00'));
        $visitInsert->execute([
            'visitor_id' => $visitorIds[$i],
            'unit_id' => (int) $host['UnitID'],
            'host_id' => (int) $host['PartyID'],
            'purpose' => 'Follow-up Visit',
            'visit_date' => date('Y-m-d', strtotime('-' . (8 + $i) . ' days')),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'status' => 'Checked Out',
            'approved_by' => $userId ?: null,
            'remarks' => null,
        ]);
    }

    $pdo->commit();

    echo "Visitor seed completed.\n";
    echo 'Visitors=' . $pdo->query('SELECT COUNT(*) FROM Visitors')->fetchColumn() . PHP_EOL;
    echo 'Visits=' . $pdo->query('SELECT COUNT(*) FROM Visits')->fetchColumn() . PHP_EOL;
    echo 'CheckedIn=' . $pdo->query("SELECT COUNT(*) FROM Visits WHERE VisitStatus='Checked In'")->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
