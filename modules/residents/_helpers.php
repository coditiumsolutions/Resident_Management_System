<?php

declare(strict_types=1);

function resident_unit_options(PDO $pdo, bool $vacantOnly = false): array
{
    $sql = "SELECT u.UnitID, u.UnitNumber, u.Status, u.UnitType,
                   f.FloorName, b.BlockName, p.PropertyName
            FROM Units u
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            INNER JOIN Properties p ON p.PropertyID = b.PropertyID";
    if ($vacantOnly) {
        $sql .= " WHERE u.Status = 'Vacant'";
    }
    $sql .= ' ORDER BY p.PropertyName, b.BlockName, f.FloorNumber, u.UnitNumber';

    return $pdo->query($sql)->fetchAll();
}

function resident_party_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT PartyID, FullName, CNIC, Status
         FROM Parties
         ORDER BY FullName"
    )->fetchAll();
}

function resident_format_unit_label(array $unit): string
{
    return sprintf(
        '%s / %s / %s / Unit %s (%s)',
        $unit['PropertyName'],
        $unit['BlockName'],
        $unit['FloorName'],
        $unit['UnitNumber'],
        $unit['Status']
    );
}

function resident_has_party_links(PDO $pdo, int $partyId): bool
{
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM UnitOwnerships WHERE OwnerPartyID = :id1) AS c1,
            (SELECT COUNT(*) FROM UnitOccupancies WHERE PartyID = :id2) AS c2,
            (SELECT COUNT(*) FROM Complaints WHERE PartyID = :id3) AS c3,
            (SELECT COUNT(*) FROM Payments WHERE PayerPartyID = :id4) AS c4,
            (SELECT COUNT(*) FROM Invoices WHERE BilledPartyID = :id5) AS c5,
            (SELECT COUNT(*) FROM Visits WHERE HostPartyID = :id6) AS c6"
    );
    $stmt->execute([
        'id1' => $partyId,
        'id2' => $partyId,
        'id3' => $partyId,
        'id4' => $partyId,
        'id5' => $partyId,
        'id6' => $partyId,
    ]);

    return array_sum(array_map('intval', $stmt->fetch() ?: [])) > 0;
}

function resident_sync_unit_status(PDO $pdo, int $unitId): void
{
    $active = $pdo->prepare(
        "SELECT COUNT(*) FROM UnitOccupancies
         WHERE UnitID = :unit_id
           AND Status = 'Active'
           AND (EndDate IS NULL OR EndDate >= CURDATE())"
    );
    $active->execute(['unit_id' => $unitId]);
    $status = ((int) $active->fetchColumn() > 0) ? 'Occupied' : 'Vacant';

    $pdo->prepare('UPDATE Units SET Status = :status WHERE UnitID = :unit_id')
        ->execute(['status' => $status, 'unit_id' => $unitId]);
}

function resident_validate_party(array $data): array
{
    $errors = [];
    if (trim((string) ($data['FullName'] ?? '')) === '') {
        $errors[] = 'Full name is required.';
    }
    if (($data['Email'] ?? '') !== '' && !filter_var($data['Email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (!in_array(($data['Status'] ?? ''), ['Active', 'Inactive'], true)) {
        $errors[] = 'Invalid status selected.';
    }
    if (($data['Gender'] ?? '') !== '' && !in_array($data['Gender'], ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Invalid gender selected.';
    }

    return $errors;
}

function resident_save_party(PDO $pdo, array $data, ?int $partyId = null): int
{
    $payload = [
        'full_name' => trim((string) $data['FullName']),
        'cnic' => trim((string) ($data['CNIC'] ?? '')) ?: null,
        'email' => trim((string) ($data['Email'] ?? '')) ?: null,
        'dob' => trim((string) ($data['DateOfBirth'] ?? '')) ?: null,
        'gender' => trim((string) ($data['Gender'] ?? '')) ?: null,
        'address' => trim((string) ($data['PermanentAddress'] ?? '')) ?: null,
        'emergency_name' => trim((string) ($data['EmergencyContactName'] ?? '')) ?: null,
        'emergency_number' => trim((string) ($data['EmergencyContactNumber'] ?? '')) ?: null,
        'status' => trim((string) ($data['Status'] ?? 'Active')),
    ];

    if ($partyId) {
        $pdo->prepare(
            'UPDATE Parties
             SET FullName = :full_name, CNIC = :cnic, Email = :email, DateOfBirth = :dob,
                 Gender = :gender, PermanentAddress = :address,
                 EmergencyContactName = :emergency_name, EmergencyContactNumber = :emergency_number,
                 Status = :status
             WHERE PartyID = :id'
        )->execute($payload + ['id' => $partyId]);

        return $partyId;
    }

    $pdo->prepare(
        'INSERT INTO Parties
            (FullName, CNIC, Email, DateOfBirth, Gender, PermanentAddress, EmergencyContactName, EmergencyContactNumber, Status)
         VALUES
            (:full_name, :cnic, :email, :dob, :gender, :address, :emergency_name, :emergency_number, :status)'
    )->execute($payload);

    return (int) $pdo->lastInsertId();
}

function resident_save_contact(PDO $pdo, int $partyId, string $number, bool $replacePrimary = true): void
{
    $number = trim($number);
    if ($number === '') {
        return;
    }

    if ($replacePrimary) {
        $pdo->prepare('DELETE FROM PartyContacts WHERE PartyID = :id AND IsPrimary = 1')->execute(['id' => $partyId]);
    }

    $pdo->prepare(
        'INSERT INTO PartyContacts (PartyID, ContactNumber, ContactType, IsPrimary)
         VALUES (:party_id, :number, :type, 1)'
    )->execute([
        'party_id' => $partyId,
        'number' => $number,
        'type' => 'Mobile',
    ]);
}
