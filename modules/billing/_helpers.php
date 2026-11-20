<?php

declare(strict_types=1);

function billing_charge_types(PDO $pdo, bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM ChargeTypes';
    if ($activeOnly) {
        $sql .= " WHERE Status = 'Active'";
    }
    $sql .= ' ORDER BY ChargeName';

    return $pdo->query($sql)->fetchAll();
}

function billing_ensure_charge_types(PDO $pdo): void
{
    $defaults = [
        ['Rent', 'Monthly rent charges', 1],
        ['Electricity', 'Electricity utility charges', 1],
        ['Maintenance', 'Building maintenance charges', 1],
        ['Water', 'Water utility charges', 1],
        ['Additional Charges', 'Miscellaneous additional charges', 0],
    ];

    $check = $pdo->prepare('SELECT ChargeTypeID FROM ChargeTypes WHERE ChargeName = :name LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO ChargeTypes (ChargeName, Description, IsRecurring, Status)
         VALUES (:name, :description, :recurring, :status)'
    );

    foreach ($defaults as [$name, $desc, $recurring]) {
        $check->execute(['name' => $name]);
        if (!$check->fetch()) {
            $insert->execute([
                'name' => $name,
                'description' => $desc,
                'recurring' => $recurring,
                'status' => 'Active',
            ]);
        }
    }
}

function billing_next_invoice_no(PDO $pdo, string $billingMonth): string
{
    $prefix = 'INV-' . str_replace('-', '', $billingMonth) . '-';
    $stmt = $pdo->prepare("SELECT InvoiceNo FROM Invoices WHERE InvoiceNo LIKE :prefix ORDER BY InvoiceID DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $seq = ((int) $m[1]) + 1;
    }

    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function billing_next_receipt_no(PDO $pdo): string
{
    $prefix = 'RCPT-' . date('Ym') . '-';
    $stmt = $pdo->prepare("SELECT ReceiptNo FROM Payments WHERE ReceiptNo LIKE :prefix ORDER BY PaymentID DESC LIMIT 1");
    $stmt->execute(['prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/(\d+)$/', (string) $last, $m)) {
        $seq = ((int) $m[1]) + 1;
    }

    return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function billing_invoice_allocated(PDO $pdo, int $invoiceId): float
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(AllocatedAmount), 0) FROM PaymentAllocations WHERE InvoiceID = :id');
    $stmt->execute(['id' => $invoiceId]);

    return (float) $stmt->fetchColumn();
}

function billing_invoice_balance(PDO $pdo, array $invoice): float
{
    $allocated = billing_invoice_allocated($pdo, (int) $invoice['InvoiceID']);

    return max(0, (float) $invoice['TotalAmount'] - $allocated);
}

function billing_refresh_invoice_status(PDO $pdo, int $invoiceId): void
{
    $stmt = $pdo->prepare('SELECT TotalAmount, DueDate, InvoiceStatus FROM Invoices WHERE InvoiceID = :id');
    $stmt->execute(['id' => $invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice || in_array($invoice['InvoiceStatus'], ['Cancelled', 'Void'], true)) {
        return;
    }

    $allocated = billing_invoice_allocated($pdo, $invoiceId);
    $total = (float) $invoice['TotalAmount'];

    if ($allocated <= 0) {
        $status = ($invoice['DueDate'] < date('Y-m-d')) ? 'Overdue' : 'Issued';
    } elseif ($allocated + 0.009 >= $total) {
        $status = 'Paid';
    } else {
        $status = 'Partially Paid';
    }

    $pdo->prepare('UPDATE Invoices SET InvoiceStatus = :status WHERE InvoiceID = :id')
        ->execute(['status' => $status, 'id' => $invoiceId]);
}

function billing_billable_units(PDO $pdo): array
{
    return $pdo->query(
        "SELECT u.UnitID, u.UnitNumber, b.BlockName, p.PropertyName,
                oc.PartyID, party.FullName AS PartyName, oc.OccupancyType
         FROM Units u
         INNER JOIN Floors f ON f.FloorID = u.FloorID
         INNER JOIN Blocks b ON b.BlockID = f.BlockID
         INNER JOIN Properties p ON p.PropertyID = b.PropertyID
         INNER JOIN UnitOccupancies oc ON oc.UnitID = u.UnitID
            AND oc.Status = 'Active'
            AND oc.IsPrimary = 1
            AND (oc.EndDate IS NULL OR oc.EndDate >= CURDATE())
         INNER JOIN Parties party ON party.PartyID = oc.PartyID
         ORDER BY p.PropertyName, b.BlockName, u.UnitNumber"
    )->fetchAll();
}

function billing_invoice_list_sql(string $extraWhere = '', string $extraJoin = ''): string
{
    return "SELECT i.*,
                   u.UnitNumber, b.BlockName, pr.PropertyName,
                   party.FullName AS PartyName,
                   COALESCE(a.allocated, 0) AS AllocatedAmount,
                   (i.TotalAmount - COALESCE(a.allocated, 0)) AS BalanceAmount
            FROM Invoices i
            INNER JOIN Units u ON u.UnitID = i.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            INNER JOIN Properties pr ON pr.PropertyID = b.PropertyID
            INNER JOIN Parties party ON party.PartyID = i.BilledPartyID
            LEFT JOIN (
                SELECT InvoiceID, SUM(AllocatedAmount) AS allocated
                FROM PaymentAllocations
                GROUP BY InvoiceID
            ) a ON a.InvoiceID = i.InvoiceID
            {$extraJoin}
            WHERE 1=1 {$extraWhere}";
}

function billing_status_badge(string $status): string
{
    return match ($status) {
        'Paid' => 'resolved',
        'Partially Paid' => 'progress',
        'Overdue' => 'urgent',
        'Cancelled', 'Void' => 'hold',
        default => 'new',
    };
}

function billing_charge_type_by_name(PDO $pdo, string $name): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM ChargeTypes WHERE ChargeName = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();

    return $row ?: null;
}
