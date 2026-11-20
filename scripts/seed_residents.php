<?php

declare(strict_types=1);

/**
 * Seed owners, tenants, residents, ownerships, occupancies and rental agreements
 * using existing Ready Plaza units.
 */

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/residents/_helpers.php';

$pdo = db();

if ((int) $pdo->query('SELECT COUNT(*) FROM Parties')->fetchColumn() > 0) {
    echo "Parties already exist. Skipping resident seed.\n";
    exit(0);
}

$units = $pdo->query(
    "SELECT u.UnitID, u.UnitNumber, u.Status
     FROM Units u
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     ORDER BY f.FloorNumber, u.UnitNumber"
)->fetchAll();

if (!$units) {
    fwrite(STDERR, "No units found. Seed properties first.\n");
    exit(1);
}

$unitsByNumber = [];
foreach ($units as $unit) {
    $unitsByNumber[$unit['UnitNumber']] = (int) $unit['UnitID'];
}

$owners = [
    ['Ahmed Khan', '35202-1000001-1', 'ahmed.khan@example.com', '03001110001', 'Male', '101'],
    ['Sara Malik', '35202-1000002-2', 'sara.malik@example.com', '03001110002', 'Female', '102'],
    ['Bilal Hussain', '35202-1000003-3', 'bilal.hussain@example.com', '03001110003', 'Male', '103'],
    ['Nadia Iqbal', '35202-1000004-4', 'nadia.iqbal@example.com', '03001110004', 'Female', '104'],
    ['Usman Raza', '35202-1000005-5', 'usman.raza@example.com', '03001110005', 'Male', '105'],
    ['Hina Shah', '35202-1000006-6', 'hina.shah@example.com', '03001110006', 'Female', '106'],
    ['Imran Ali', '35202-1000007-7', 'imran.ali@example.com', '03001110007', 'Male', '107'],
    ['Ayesha Noor', '35202-1000008-8', 'ayesha.noor@example.com', '03001110008', 'Female', '108'],
];

$tenants = [
    ['Farhan Siddiqui', '35202-2000001-1', 'farhan.s@example.com', '03002220001', 'Male', '201', 45000],
    ['Mehwish Tariq', '35202-2000002-2', 'mehwish.t@example.com', '03002220002', 'Female', '202', 42000],
    ['Kamran Javed', '35202-2000003-3', 'kamran.j@example.com', '03002220003', 'Male', '203', 48000],
    ['Sana Fatima', '35202-2000004-4', 'sana.f@example.com', '03002220004', 'Female', '204', 40000],
    ['Zain Abbas', '35202-2000005-5', 'zain.a@example.com', '03002220005', 'Male', '205', 46000],
    ['Rabia Aziz', '35202-2000006-6', 'rabia.a@example.com', '03002220006', 'Female', '301', 39000],
    ['Haris Qureshi', '35202-2000007-7', 'haris.q@example.com', '03002220007', 'Male', '302', 41000],
    ['Iqra Naveed', '35202-2000008-8', 'iqra.n@example.com', '03002220008', 'Female', '303', 43000],
];

$familyResidents = [
    ['Hamza Khan', '35202-3000001-1', 'hamza.khan@example.com', '03003330001', 'Male', '101'],
    ['Maha Malik', '35202-3000002-2', 'maha.malik@example.com', '03003330002', 'Female', '102'],
];

try {
    $pdo->beginTransaction();

    $insertOwnership = $pdo->prepare(
        'INSERT INTO UnitOwnerships (UnitID, OwnerPartyID, OwnershipShare, StartDate, EndDate, IsPrimary)
         VALUES (:unit_id, :party_id, :share, :start_date, :end_date, :is_primary)'
    );
    $insertOccupancy = $pdo->prepare(
        'INSERT INTO UnitOccupancies (UnitID, PartyID, OccupancyType, StartDate, EndDate, IsPrimary, Status)
         VALUES (:unit_id, :party_id, :type, :start_date, :end_date, :is_primary, :status)'
    );
    $insertAgreement = $pdo->prepare(
        'INSERT INTO RentalAgreements
            (OccupancyID, MonthlyRent, SecurityDeposit, AgreementStartDate, AgreementEndDate, RentDueDay, AgreementStatus, Remarks)
         VALUES
            (:occupancy_id, :rent, :deposit, :start_date, :end_date, :due_day, :status, :remarks)'
    );

    $ownerIds = [];
    foreach ($owners as $i => $row) {
        [$name, $cnic, $email, $phone, $gender, $unitNo] = $row;
        $partyId = resident_save_party($pdo, [
            'FullName' => $name,
            'CNIC' => $cnic,
            'Email' => $email,
            'Gender' => $gender,
            'DateOfBirth' => sprintf('198%u-0%u-15', $i % 9, ($i % 8) + 1),
            'PermanentAddress' => 'Ready Plaza, Lahore',
            'EmergencyContactName' => 'Family Contact',
            'EmergencyContactNumber' => '0300999' . sprintf('%04d', $i + 1),
            'Status' => 'Active',
        ]);
        resident_save_contact($pdo, $partyId, $phone);
        $ownerIds[] = $partyId;

        $unitId = $unitsByNumber[$unitNo] ?? null;
        if (!$unitId) {
            throw new RuntimeException("Unit {$unitNo} not found for owner seed.");
        }

        // Current ownership
        $insertOwnership->execute([
            'unit_id' => $unitId,
            'party_id' => $partyId,
            'share' => 100,
            'start_date' => '2022-01-01',
            'end_date' => null,
            'is_primary' => 1,
        ]);

        // Owner also occupies their unit
        $insertOccupancy->execute([
            'unit_id' => $unitId,
            'party_id' => $partyId,
            'type' => 'Owner',
            'start_date' => '2022-01-01',
            'end_date' => null,
            'is_primary' => 1,
            'status' => 'Active',
        ]);
        resident_sync_unit_status($pdo, $unitId);
    }

    // Historical ownership for unit 109 (ended)
    if (isset($unitsByNumber['109'], $ownerIds[0])) {
        $insertOwnership->execute([
            'unit_id' => $unitsByNumber['109'],
            'party_id' => $ownerIds[0],
            'share' => 100,
            'start_date' => '2020-01-01',
            'end_date' => '2021-12-31',
            'is_primary' => 1,
        ]);
    }
    if (isset($unitsByNumber['109'], $ownerIds[1])) {
        $insertOwnership->execute([
            'unit_id' => $unitsByNumber['109'],
            'party_id' => $ownerIds[1],
            'share' => 100,
            'start_date' => '2022-01-01',
            'end_date' => '2023-06-30',
            'is_primary' => 1,
        ]);
    }

    foreach ($tenants as $i => $row) {
        [$name, $cnic, $email, $phone, $gender, $unitNo, $rent] = $row;
        $partyId = resident_save_party($pdo, [
            'FullName' => $name,
            'CNIC' => $cnic,
            'Email' => $email,
            'Gender' => $gender,
            'DateOfBirth' => sprintf('199%u-0%u-10', $i % 9, ($i % 8) + 1),
            'PermanentAddress' => 'Ready Plaza Tenant Residence, Lahore',
            'EmergencyContactName' => 'Emergency Person',
            'EmergencyContactNumber' => '0300888' . sprintf('%04d', $i + 1),
            'Status' => 'Active',
        ]);
        resident_save_contact($pdo, $partyId, $phone);

        $unitId = $unitsByNumber[$unitNo] ?? null;
        if (!$unitId) {
            throw new RuntimeException("Unit {$unitNo} not found for tenant seed.");
        }

        // Ensure owner exists for rented units (assign rotating owner)
        $ownerPartyId = $ownerIds[$i % count($ownerIds)];
        $existingOwner = $pdo->prepare(
            "SELECT OwnershipID FROM UnitOwnerships
             WHERE UnitID = :unit_id AND EndDate IS NULL LIMIT 1"
        );
        $existingOwner->execute(['unit_id' => $unitId]);
        if (!$existingOwner->fetch()) {
            $insertOwnership->execute([
                'unit_id' => $unitId,
                'party_id' => $ownerPartyId,
                'share' => 100,
                'start_date' => '2021-06-01',
                'end_date' => null,
                'is_primary' => 1,
            ]);
        }

        $insertOccupancy->execute([
            'unit_id' => $unitId,
            'party_id' => $partyId,
            'type' => 'Tenant',
            'start_date' => '2024-01-01',
            'end_date' => null,
            'is_primary' => 1,
            'status' => 'Active',
        ]);
        $occupancyId = (int) $pdo->lastInsertId();

        $insertAgreement->execute([
            'occupancy_id' => $occupancyId,
            'rent' => $rent,
            'deposit' => $rent * 2,
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
            'due_day' => 5,
            'status' => 'Active',
            'remarks' => "Rental agreement for unit {$unitNo}",
        ]);

        resident_sync_unit_status($pdo, $unitId);
    }

    foreach ($familyResidents as $i => $row) {
        [$name, $cnic, $email, $phone, $gender, $unitNo] = $row;
        $partyId = resident_save_party($pdo, [
            'FullName' => $name,
            'CNIC' => $cnic,
            'Email' => $email,
            'Gender' => $gender,
            'DateOfBirth' => sprintf('200%u-05-20', $i + 1),
            'PermanentAddress' => 'Ready Plaza, Lahore',
            'EmergencyContactName' => 'Parent',
            'EmergencyContactNumber' => '0300777' . sprintf('%04d', $i + 1),
            'Status' => 'Active',
        ]);
        resident_save_contact($pdo, $partyId, $phone);

        $unitId = $unitsByNumber[$unitNo] ?? null;
        if ($unitId) {
            $insertOccupancy->execute([
                'unit_id' => $unitId,
                'party_id' => $partyId,
                'type' => 'Resident',
                'start_date' => '2023-03-01',
                'end_date' => null,
                'is_primary' => 0,
                'status' => 'Active',
            ]);
        }
    }

    // One ended tenancy for history
    if (isset($unitsByNumber['304'])) {
        $pastTenantId = resident_save_party($pdo, [
            'FullName' => 'Omar Sheikh',
            'CNIC' => '35202-2000099-9',
            'Email' => 'omar.sheikh@example.com',
            'Gender' => 'Male',
            'PermanentAddress' => 'Former Ready Plaza Tenant',
            'Status' => 'Inactive',
        ]);
        resident_save_contact($pdo, $pastTenantId, '03002229999');
        $insertOccupancy->execute([
            'unit_id' => $unitsByNumber['304'],
            'party_id' => $pastTenantId,
            'type' => 'Tenant',
            'start_date' => '2022-01-01',
            'end_date' => '2023-12-31',
            'is_primary' => 1,
            'status' => 'Ended',
        ]);
        $pastOccId = (int) $pdo->lastInsertId();
        $insertAgreement->execute([
            'occupancy_id' => $pastOccId,
            'rent' => 35000,
            'deposit' => 70000,
            'start_date' => '2022-01-01',
            'end_date' => '2023-12-31',
            'due_day' => 5,
            'status' => 'Expired',
            'remarks' => 'Past rental agreement',
        ]);
        resident_sync_unit_status($pdo, $unitsByNumber['304']);
    }

    $pdo->commit();

    echo "Resident seed completed.\n";
    echo 'Parties=' . $pdo->query('SELECT COUNT(*) FROM Parties')->fetchColumn() . PHP_EOL;
    echo 'Ownerships=' . $pdo->query('SELECT COUNT(*) FROM UnitOwnerships')->fetchColumn() . PHP_EOL;
    echo 'Occupancies=' . $pdo->query('SELECT COUNT(*) FROM UnitOccupancies')->fetchColumn() . PHP_EOL;
    echo 'Agreements=' . $pdo->query('SELECT COUNT(*) FROM RentalAgreements')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
