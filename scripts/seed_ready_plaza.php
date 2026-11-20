<?php

declare(strict_types=1);

/**
 * Seed Ready Plaza: 1 property, 1 block, 10 floors, 15 units per floor (150 units).
 * Safe to re-run: skips if a property named "Ready Plaza" already exists.
 */

require dirname(__DIR__) . '/includes/functions.php';

$pdo = db();

$exists = $pdo->prepare('SELECT PropertyID FROM Properties WHERE PropertyName = :name LIMIT 1');
$exists->execute(['name' => 'Ready Plaza']);
$existing = $exists->fetch();

if ($existing) {
    echo "Ready Plaza already exists (PropertyID={$existing['PropertyID']}). Skipping seed.\n";
    exit(0);
}

$unitTypes = ['Apartment', 'Apartment', 'Apartment', 'Shop', 'Office'];

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO Properties (PropertyName, Address, City, ContactNumber, Status)
         VALUES (:name, :address, :city, :contact, :status)'
    )->execute([
        'name' => 'Ready Plaza',
        'address' => 'Main Boulevard, Commercial District',
        'city' => 'Lahore',
        'contact' => '0300-1234567',
        'status' => 'Active',
    ]);
    $propertyId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO Blocks (PropertyID, BlockName, Description, Status)
         VALUES (:property_id, :block_name, :description, :status)'
    )->execute([
        'property_id' => $propertyId,
        'block_name' => 'Main Tower',
        'description' => 'Primary tower of Ready Plaza',
        'status' => 'Active',
    ]);
    $blockId = (int) $pdo->lastInsertId();

    $floorStmt = $pdo->prepare(
        'INSERT INTO Floors (BlockID, FloorName, FloorNumber, Status)
         VALUES (:block_id, :floor_name, :floor_number, :status)'
    );
    $unitStmt = $pdo->prepare(
        'INSERT INTO Units (FloorID, UnitNumber, UnitType, AreaSqFt, Status, Description)
         VALUES (:floor_id, :unit_number, :unit_type, :area, :status, :description)'
    );

    $totalUnits = 0;

    for ($floor = 1; $floor <= 10; $floor++) {
        $floorStmt->execute([
            'block_id' => $blockId,
            'floor_name' => 'Floor ' . $floor,
            'floor_number' => $floor,
            'status' => 'Active',
        ]);
        $floorId = (int) $pdo->lastInsertId();

        for ($unit = 1; $unit <= 15; $unit++) {
            $unitNumber = sprintf('%d%02d', $floor, $unit); // e.g. 101, 215
            $type = $unitTypes[($unit - 1) % count($unitTypes)];
            $area = $type === 'Shop' ? 450 : ($type === 'Office' ? 650 : 950);

            // Mix statuses for realistic dashboard stats
            if ($floor === 1 && $unit <= 8) {
                $status = 'Occupied';
            } elseif ($floor === 2 && $unit <= 5) {
                $status = 'Occupied';
            } elseif ($floor === 10 && $unit === 15) {
                $status = 'Under Maintenance';
            } else {
                $status = 'Vacant';
            }

            $unitStmt->execute([
                'floor_id' => $floorId,
                'unit_number' => $unitNumber,
                'unit_type' => $type,
                'area' => $area,
                'status' => $status,
                'description' => "Ready Plaza unit {$unitNumber}",
            ]);
            $totalUnits++;
        }
    }

    $pdo->commit();

    echo "Seeded Ready Plaza successfully.\n";
    echo "PropertyID: {$propertyId}\n";
    echo "BlockID: {$blockId}\n";
    echo "Floors: 10\n";
    echo "Units: {$totalUnits}\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
