<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Invoice Details';
$currentNav = 'billing-all';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid invoice selected.');
    redirect('modules/billing/index.php');
}

$stmt = db()->prepare(billing_invoice_list_sql(' AND i.InvoiceID = :id'));
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash_set('error', 'Invoice not found.');
    redirect('modules/billing/index.php');
}

$lines = db()->prepare(
    "SELECT il.*, ct.ChargeName
     FROM InvoiceLines il
     INNER JOIN ChargeTypes ct ON ct.ChargeTypeID = il.ChargeTypeID
     WHERE il.InvoiceID = :id
     ORDER BY il.InvoiceLineID"
);
$lines->execute(['id' => $id]);
$lineRows = $lines->fetchAll();

$payments = db()->prepare(
    "SELECT p.ReceiptNo, p.PaymentDate, p.PaymentMethod, pa.AllocatedAmount, party.FullName
     FROM PaymentAllocations pa
     INNER JOIN Payments p ON p.PaymentID = pa.PaymentID
     INNER JOIN Parties party ON party.PartyID = p.PayerPartyID
     WHERE pa.InvoiceID = :id
     ORDER BY p.PaymentDate DESC"
);
$payments->execute(['id' => $id]);
$paymentRows = $payments->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($invoice['InvoiceNo']) ?></h1>
        <p class="text-muted mb-0"><?= e($invoice['PartyName']) ?> · Unit <?= e($invoice['UnitNumber']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ((float) $invoice['BalanceAmount'] > 0.009): ?>
            <a href="<?= e(base_url('modules/billing/payments.php?invoice_id=' . $id)) ?>" class="btn btn-primary">
                <i class="bi bi-wallet2 me-1"></i> Collect Payment
            </a>
        <?php endif; ?>
        <a href="<?= e(base_url('modules/billing/index.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card panel-card h-100">
            <div class="card-header">Invoice Summary</div>
            <div class="card-body">
                <dl class="row mb-0 detail-list">
                    <dt class="col-5">Month</dt><dd class="col-7"><?= e($invoice['BillingMonth']) ?></dd>
                    <dt class="col-5">Issue Date</dt><dd class="col-7"><?= e(format_date($invoice['IssueDate'])) ?></dd>
                    <dt class="col-5">Due Date</dt><dd class="col-7"><?= e(format_date($invoice['DueDate'])) ?></dd>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7"><span class="badge badge-soft <?= e(billing_status_badge($invoice['InvoiceStatus'])) ?>"><?= e($invoice['InvoiceStatus']) ?></span></dd>
                    <dt class="col-5">Total</dt><dd class="col-7"><?= e(format_money($invoice['TotalAmount'])) ?></dd>
                    <dt class="col-5">Paid</dt><dd class="col-7"><?= e(format_money($invoice['AllocatedAmount'])) ?></dd>
                    <dt class="col-5">Balance</dt><dd class="col-7"><strong><?= e(format_money(max(0, (float) $invoice['BalanceAmount']))) ?></strong></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-header">Charge Lines</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Charge</th><th>Description</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                        <tbody>
                        <?php foreach ($lineRows as $line): ?>
                            <tr>
                                <td><?= e($line['ChargeName']) ?></td>
                                <td><?= e($line['Description'] ?: '—') ?></td>
                                <td><?= e(number_format((float) $line['Quantity'], 2)) ?></td>
                                <td><?= e(format_money($line['Rate'])) ?></td>
                                <td><?= e(format_money($line['Amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card panel-card">
            <div class="card-header">Payment Allocations</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Receipt</th><th>Payer</th><th>Date</th><th>Method</th><th>Allocated</th></tr></thead>
                        <tbody>
                        <?php if (!$paymentRows): ?>
                            <tr><td colspan="5" class="empty-state">No payments allocated yet.</td></tr>
                        <?php else: foreach ($paymentRows as $pay): ?>
                            <tr>
                                <td><?= e($pay['ReceiptNo']) ?></td>
                                <td><?= e($pay['FullName']) ?></td>
                                <td><?= e(format_datetime($pay['PaymentDate'])) ?></td>
                                <td><?= e($pay['PaymentMethod']) ?></td>
                                <td><?= e(format_money($pay['AllocatedAmount'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
