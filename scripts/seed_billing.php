<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/modules/billing/_helpers.php';

$pdo = db();
billing_ensure_charge_types($pdo);

if ((int) $pdo->query('SELECT COUNT(*) FROM Invoices')->fetchColumn() > 0) {
    echo "Invoices already exist. Skipping billing seed.\n";
    exit(0);
}

$chargeMap = [];
foreach ($pdo->query('SELECT ChargeTypeID, ChargeName FROM ChargeTypes')->fetchAll() as $row) {
    $chargeMap[$row['ChargeName']] = (int) $row['ChargeTypeID'];
}

$units = billing_billable_units($pdo);
if (!$units) {
    fwrite(STDERR, "No billable occupied units found. Seed residents first.\n");
    exit(1);
}

$months = [
    date('Y-m', strtotime('-2 months')),
    date('Y-m', strtotime('-1 month')),
    date('Y-m'),
];

$rateMap = [
    'Rent' => [35000, 48000],
    'Electricity' => [2500, 8500],
    'Maintenance' => [1500, 3500],
    'Water' => [800, 2200],
    'Additional Charges' => [500, 4000],
];

try {
    $pdo->beginTransaction();

    $invoiceInsert = $pdo->prepare(
        'INSERT INTO Invoices
            (InvoiceNo, UnitID, BilledPartyID, BillingMonth, IssueDate, DueDate,
             SubtotalAmount, DiscountAmount, TaxAmount, TotalAmount, InvoiceStatus, Remarks)
         VALUES
            (:no, :unit_id, :party_id, :month, :issue, :due,
             :subtotal, 0, 0, :total, :status, :remarks)'
    );
    $lineInsert = $pdo->prepare(
        'INSERT INTO InvoiceLines (InvoiceID, ChargeTypeID, Description, Quantity, Rate, Amount)
         VALUES (:invoice_id, :charge_type_id, :description, 1, :rate, :amount)'
    );
    $paymentInsert = $pdo->prepare(
        'INSERT INTO Payments
            (ReceiptNo, PayerPartyID, PaymentDate, Amount, PaymentMethod, TransactionReference, ReceivedByUserID, PaymentStatus, Remarks)
         VALUES
            (:receipt, :party_id, :date, :amount, :method, :reference, :user_id, :status, :remarks)'
    );
    $allocInsert = $pdo->prepare(
        'INSERT INTO PaymentAllocations (PaymentID, InvoiceID, AllocatedAmount)
         VALUES (:payment_id, :invoice_id, :amount)'
    );

    $userId = (int) ($pdo->query('SELECT UserID FROM Users ORDER BY UserID LIMIT 1')->fetchColumn() ?: 0);
    $createdInvoices = [];
    $invoiceCount = 0;

    foreach ($months as $monthIndex => $month) {
        $issueDate = $month . '-01';
        $dueDate = date('Y-m-d', strtotime($issueDate . ' +10 days'));

        foreach ($units as $i => $unit) {
            // Skip some units in older months for variety
            if ($monthIndex === 0 && $i % 3 === 0) {
                continue;
            }

            $lines = [];
            foreach ($rateMap as $chargeName => [$min, $max]) {
                // Not every invoice gets additional charges
                if ($chargeName === 'Additional Charges' && ($i + $monthIndex) % 2 !== 0) {
                    continue;
                }
                $rate = random_int($min, $max);
                $lines[] = [
                    'charge' => $chargeName,
                    'rate' => $rate,
                    'amount' => $rate,
                    'description' => $chargeName . ' for ' . $unit['UnitNumber'] . ' (' . $month . ')',
                ];
            }

            if (!$lines) {
                continue;
            }

            $subtotal = array_sum(array_column($lines, 'amount'));
            $invoiceNo = billing_next_invoice_no($pdo, $month);

            $invoiceInsert->execute([
                'no' => $invoiceNo,
                'unit_id' => (int) $unit['UnitID'],
                'party_id' => (int) $unit['PartyID'],
                'month' => $month,
                'issue' => $issueDate,
                'due' => $dueDate,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => 'Issued',
                'remarks' => 'Auto-generated bill for unit ' . $unit['UnitNumber'],
            ]);
            $invoiceId = (int) $pdo->lastInsertId();
            $invoiceCount++;

            foreach ($lines as $line) {
                $lineInsert->execute([
                    'invoice_id' => $invoiceId,
                    'charge_type_id' => $chargeMap[$line['charge']],
                    'description' => $line['description'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                ]);
            }

            $createdInvoices[] = [
                'id' => $invoiceId,
                'party_id' => (int) $unit['PartyID'],
                'total' => $subtotal,
                'month' => $month,
                'month_index' => $monthIndex,
                'unit' => $unit['UnitNumber'],
            ];
        }
    }

    // Create payments: fully pay older invoices, partial for some current
    $methods = ['Cash', 'Bank Transfer', 'JazzCash', 'EasyPaisa', 'Cheque'];
    $paymentCount = 0;

    foreach ($createdInvoices as $idx => $inv) {
        $mode = $idx % 4;

        if ($inv['month_index'] === 2 && $mode === 0) {
            // leave unpaid current bills
            billing_refresh_invoice_status($pdo, $inv['id']);
            continue;
        }

        $payAmount = $inv['total'];
        if ($mode === 1) {
            $payAmount = round($inv['total'] * 0.5, 2); // partial
        } elseif ($mode === 2 && $inv['month_index'] < 2) {
            $payAmount = $inv['total']; // full
        }

        $payDate = date('Y-m-d H:i:s', strtotime($inv['month'] . '-12 +' . ($idx % 10) . ' days'));
        $receipt = billing_next_receipt_no($pdo);

        $paymentInsert->execute([
            'receipt' => $receipt,
            'party_id' => $inv['party_id'],
            'date' => $payDate,
            'amount' => $payAmount,
            'method' => $methods[$idx % count($methods)],
            'reference' => 'TXN-' . strtoupper(substr(md5((string) $inv['id']), 0, 8)),
            'user_id' => $userId ?: null,
            'status' => 'Completed',
            'remarks' => 'Payment against invoice for unit ' . $inv['unit'],
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $paymentCount++;

        $allocInsert->execute([
            'payment_id' => $paymentId,
            'invoice_id' => $inv['id'],
            'amount' => $payAmount,
        ]);

        billing_refresh_invoice_status($pdo, $inv['id']);
    }

    // Refresh remaining unpaid
    foreach ($createdInvoices as $inv) {
        billing_refresh_invoice_status($pdo, $inv['id']);
    }

    $pdo->commit();

    echo "Billing seed completed.\n";
    echo 'ChargeTypes=' . $pdo->query('SELECT COUNT(*) FROM ChargeTypes')->fetchColumn() . PHP_EOL;
    echo "Invoices={$invoiceCount}\n";
    echo 'InvoiceLines=' . $pdo->query('SELECT COUNT(*) FROM InvoiceLines')->fetchColumn() . PHP_EOL;
    echo "Payments={$paymentCount}\n";
    echo 'Allocations=' . $pdo->query('SELECT COUNT(*) FROM PaymentAllocations')->fetchColumn() . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
