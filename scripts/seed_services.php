<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/services/_helpers.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM ServiceCatalog')->fetchColumn() > 0) {
    echo "Services already exist. Skipping service seed.\n";
    exit(0);
}

$units = service_unit_options($pdo);
if (!$units) {
    fwrite(STDERR, "No occupied units found. Seed residents first.\n");
    exit(1);
}

$categories = [
    ['Utilities', 'Electricity, water and related utilities'],
    ['Maintenance', 'Building and unit maintenance services'],
    ['Security', 'Gate, CCTV and security coverage'],
    ['Housekeeping', 'Cleaning and janitorial services'],
    ['Parking', 'Vehicle parking and related facilities'],
];

$services = [
    ['Utilities', 'Electricity Connection', 'Dedicated electricity meter support', 'Monthly', 1500],
    ['Utilities', 'Water Supply', 'Municipal water connection fee', 'Monthly', 800],
    ['Maintenance', 'General Maintenance', 'Routine building maintenance', 'Monthly', 1200],
    ['Maintenance', 'AC Service', 'Air conditioner servicing', 'Quarterly', 2500],
    ['Security', '24/7 Security', 'Round-the-clock security staff', 'Monthly', 2000],
    ['Housekeeping', 'Daily Cleaning', 'Common area daily cleaning', 'Monthly', 1000],
    ['Housekeeping', 'Deep Cleaning', 'Unit deep cleaning on request', 'One-time', 3500],
    ['Parking', 'Covered Parking', 'Reserved covered parking slot', 'Monthly', 1800],
];

try {
    $pdo->beginTransaction();

    $catInsert = $pdo->prepare(
        'INSERT INTO ServiceCategories (CategoryName, Description, Status)
         VALUES (:name, :description, :status)'
    );
    $catIds = [];
    foreach ($categories as [$name, $desc]) {
        $catInsert->execute([
            'name' => $name,
            'description' => $desc,
            'status' => 'Active',
        ]);
        $catIds[$name] = (int) $pdo->lastInsertId();
    }

    $svcInsert = $pdo->prepare(
        'INSERT INTO ServiceCatalog (CategoryID, ServiceName, Description, BillingType, StandardRate, Status)
         VALUES (:category_id, :name, :description, :billing_type, :rate, :status)'
    );
    $svcIds = [];
    foreach ($services as [$cat, $name, $desc, $billing, $rate]) {
        $svcInsert->execute([
            'category_id' => $catIds[$cat],
            'name' => $name,
            'description' => $desc,
            'billing_type' => $billing,
            'rate' => number_format((float) $rate, 2, '.', ''),
            'status' => 'Active',
        ]);
        $svcIds[] = (int) $pdo->lastInsertId();
    }

    $assign = $pdo->prepare(
        'INSERT INTO UnitServices
            (UnitID, PartyID, ServiceID, StartDate, EndDate, AgreedRate, Quantity, Status)
         VALUES
            (:unit_id, :party_id, :service_id, :start_date, NULL, :rate, :qty, :status)'
    );

    $rateStmt = $pdo->prepare('SELECT StandardRate FROM ServiceCatalog WHERE ServiceID = :id');
    for ($i = 0; $i < min(8, count($units)); $i++) {
        $unit = $units[$i];
        $serviceId = $svcIds[$i % count($svcIds)];
        $rateStmt->execute(['id' => $serviceId]);
        $rate = (float) $rateStmt->fetchColumn();
        $assign->execute([
            'unit_id' => (int) $unit['UnitID'],
            'party_id' => (int) $unit['PartyID'],
            'service_id' => $serviceId,
            'start_date' => date('Y-m-d', strtotime('-' . ($i + 5) . ' days')),
            'rate' => number_format($rate, 2, '.', ''),
            'qty' => '1.00',
            'status' => $i === 7 ? 'Inactive' : 'Active',
        ]);
    }

    $pdo->commit();
    echo "Service seed completed.\n";
    echo 'Categories=' . $pdo->query('SELECT COUNT(*) FROM ServiceCategories')->fetchColumn() . PHP_EOL;
    echo 'Services=' . $pdo->query('SELECT COUNT(*) FROM ServiceCatalog')->fetchColumn() . PHP_EOL;
    echo 'Assignments=' . $pdo->query('SELECT COUNT(*) FROM UnitServices')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
