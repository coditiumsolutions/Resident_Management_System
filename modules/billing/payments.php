<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Payment Collection';
$currentNav = 'billing-payments';
$errors = [];

$preselectedInvoiceId = filter_input(INPUT_GET, 'invoice_id', FILTER_VALIDATE_INT) ?: 0;

$openInvoices = db()->query(
    billing_invoice_list_sql(
        " AND i.InvoiceStatus NOT IN ('Cancelled', 'Void', 'Paid')
          AND (i.TotalAmount - COALESCE(a.allocated, 0)) > 0.009"
    ) . ' ORDER BY i.DueDate ASC, i.InvoiceID ASC'
)->fetchAll();

$parties = db()->query(
    "SELECT DISTINCT p.PartyID, p.FullName
     FROM Parties p
     INNER JOIN Invoices i ON i.BilledPartyID = p.PartyID
     ORDER BY p.FullName"
)->fetchAll();

$form = [
    'InvoiceID' => $preselectedInvoiceId,
    'PayerPartyID' => '',
    'Amount' => '',
    'PaymentDate' => date('Y-m-d\TH:i'),
    'PaymentMethod' => 'Cash',
    'TransactionReference' => '',
    'Remarks' => '',
];

if ($preselectedInvoiceId) {
    foreach ($openInvoices as $inv) {
        if ((int) $inv['InvoiceID'] === $preselectedInvoiceId) {
            $form['PayerPartyID'] = (string) $inv['BilledPartyID'];
            $form['Amount'] = number_format(max(0, (float) $inv['BalanceAmount']), 2, '.', '');
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        $invoiceId = (int) $form['InvoiceID'];
        $partyId = (int) $form['PayerPartyID'];
        $amount = (float) $form['Amount'];

        if ($invoiceId <= 0) {
            $errors[] = 'Please select an invoice.';
        }
        if ($partyId <= 0) {
            $errors[] = 'Please select a payer.';
        }
        if ($amount <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        }
        if ($form['PaymentDate'] === '') {
            $errors[] = 'Payment date is required.';
        }
        if (!in_array($form['PaymentMethod'], ['Cash', 'Bank Transfer', 'JazzCash', 'EasyPaisa', 'Cheque', 'Card'], true)) {
            $errors[] = 'Invalid payment method.';
        }

        $invoiceStmt = db()->prepare('SELECT * FROM Invoices WHERE InvoiceID = :id LIMIT 1');
        $invoiceStmt->execute(['id' => $invoiceId]);
        $invoice = $invoiceStmt->fetch();

        if (!$invoice) {
            $errors[] = 'Selected invoice was not found.';
        } elseif (in_array($invoice['InvoiceStatus'], ['Cancelled', 'Void'], true)) {
            $errors[] = 'Cannot collect payment against a cancelled invoice.';
        } else {
            $balance = billing_invoice_balance(db(), $invoice);
            if ($amount - $balance > 0.009) {
                $errors[] = 'Amount exceeds outstanding balance of ' . format_money($balance) . '.';
            }
        }

        if (!$errors && $invoice) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $receipt = billing_next_receipt_no($pdo);
                $user = current_user();

                $pdo->prepare(
                    'INSERT INTO Payments
                        (ReceiptNo, PayerPartyID, PaymentDate, Amount, PaymentMethod, TransactionReference, ReceivedByUserID, PaymentStatus, Remarks)
                     VALUES
                        (:receipt, :party_id, :date, :amount, :method, :reference, :user_id, :status, :remarks)'
                )->execute([
                    'receipt' => $receipt,
                    'party_id' => $partyId,
                    'date' => date('Y-m-d H:i:s', strtotime($form['PaymentDate'])),
                    'amount' => $amount,
                    'method' => $form['PaymentMethod'],
                    'reference' => $form['TransactionReference'] !== '' ? $form['TransactionReference'] : null,
                    'user_id' => $user['UserID'] ?? null,
                    'status' => 'Completed',
                    'remarks' => $form['Remarks'] !== '' ? $form['Remarks'] : null,
                ]);
                $paymentId = (int) $pdo->lastInsertId();

                $pdo->prepare(
                    'INSERT INTO PaymentAllocations (PaymentID, InvoiceID, AllocatedAmount)
                     VALUES (:payment_id, :invoice_id, :amount)'
                )->execute([
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                ]);

                billing_refresh_invoice_status($pdo, $invoiceId);
                $pdo->commit();

                flash_set('success', 'Payment collected successfully. Receipt: ' . $receipt);
                redirect('modules/billing/history.php');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Payment collection failed: ' . $e->getMessage());
                $errors[] = 'Could not save payment. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payment Collection</h1>
        <p class="text-muted mb-0">Record resident payments against open invoices</p>
    </div>
    <a href="<?= e(base_url('modules/billing/history.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-journal-text me-1"></i> Payment History
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card panel-card">
            <div class="card-header">Collect Payment</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>

                <?php if (!$openInvoices): ?>
                    <div class="alert alert-success mb-0">No outstanding invoices available for collection.</div>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="mb-3">
                            <label class="form-label" for="InvoiceID">Invoice</label>
                            <select class="form-select" id="InvoiceID" name="InvoiceID" required>
                                <option value="">Select invoice</option>
                                <?php foreach ($openInvoices as $inv): ?>
                                    <option value="<?= (int) $inv['InvoiceID'] ?>"
                                            data-party="<?= (int) $inv['BilledPartyID'] ?>"
                                            data-balance="<?= e(number_format(max(0, (float) $inv['BalanceAmount']), 2, '.', '')) ?>"
                                        <?= (int) $form['InvoiceID'] === (int) $inv['InvoiceID'] ? 'selected' : '' ?>>
                                        <?= e($inv['InvoiceNo'] . ' · ' . $inv['UnitNumber'] . ' · Bal ' . format_money($inv['BalanceAmount'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="PayerPartyID">Payer</label>
                            <select class="form-select" id="PayerPartyID" name="PayerPartyID" required>
                                <option value="">Select payer</option>
                                <?php foreach ($parties as $party): ?>
                                    <option value="<?= (int) $party['PartyID'] ?>" <?= (int) $form['PayerPartyID'] === (int) $party['PartyID'] ? 'selected' : '' ?>>
                                        <?= e($party['FullName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Amount">Amount</label>
                            <input type="number" class="form-control" id="Amount" name="Amount" min="0.01" step="0.01" value="<?= e($form['Amount']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="PaymentDate">Payment Date</label>
                            <input type="datetime-local" class="form-control" id="PaymentDate" name="PaymentDate" value="<?= e($form['PaymentDate']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="PaymentMethod">Method</label>
                            <select class="form-select" id="PaymentMethod" name="PaymentMethod" required>
                                <?php foreach (['Cash', 'Bank Transfer', 'JazzCash', 'EasyPaisa', 'Cheque', 'Card'] as $method): ?>
                                    <option value="<?= e($method) ?>" <?= $form['PaymentMethod'] === $method ? 'selected' : '' ?>><?= e($method) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="TransactionReference">Reference</label>
                            <input type="text" class="form-control" id="TransactionReference" name="TransactionReference" maxlength="100" value="<?= e($form['TransactionReference']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Remarks">Remarks</label>
                            <input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="300" value="<?= e($form['Remarks']) ?>">
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i> Save Payment</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card panel-card">
            <div class="card-header">Open Invoices</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Resident</th>
                            <th>Unit</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$openInvoices): ?>
                            <tr><td colspan="5" class="empty-state">No open invoices.</td></tr>
                        <?php else: foreach ($openInvoices as $row): ?>
                            <tr>
                                <td><?= e($row['InvoiceNo']) ?></td>
                                <td><?= e($row['PartyName']) ?></td>
                                <td><?= e($row['UnitNumber']) ?></td>
                                <td><?= e(format_money($row['BalanceAmount'])) ?></td>
                                <td><span class="badge badge-soft <?= e(billing_status_badge($row['InvoiceStatus'])) ?>"><?= e($row['InvoiceStatus']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
  const invoice = document.getElementById('InvoiceID');
  const payer = document.getElementById('PayerPartyID');
  const amount = document.getElementById('Amount');
  if (!invoice) return;
  invoice.addEventListener('change', () => {
    const opt = invoice.options[invoice.selectedIndex];
    if (!opt) return;
    if (opt.dataset.party) payer.value = opt.dataset.party;
    if (opt.dataset.balance) amount.value = opt.dataset.balance;
  });
})();
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
