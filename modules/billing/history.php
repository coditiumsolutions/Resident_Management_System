<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Payment History';
$currentNav = 'billing-history';

$method = trim((string) ($_GET['method'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT p.*, party.FullName AS PayerName,
               (
                   SELECT GROUP_CONCAT(DISTINCT CONCAT(i.InvoiceNo, ' (', FORMAT(pa.AllocatedAmount, 2), ')') SEPARATOR ', ')
                   FROM PaymentAllocations pa
                   INNER JOIN Invoices i ON i.InvoiceID = pa.InvoiceID
                   WHERE pa.PaymentID = p.PaymentID
               ) AS Allocations
        FROM Payments p
        INNER JOIN Parties party ON party.PartyID = p.PayerPartyID
        WHERE 1=1";
$params = [];

if ($method !== '') {
    $sql .= ' AND p.PaymentMethod = :method';
    $params['method'] = $method;
}
if ($from !== '') {
    $sql .= ' AND DATE(p.PaymentDate) >= :from_date';
    $params['from_date'] = $from;
}
if ($to !== '') {
    $sql .= ' AND DATE(p.PaymentDate) <= :to_date';
    $params['to_date'] = $to;
}
if ($search !== '') {
    $sql .= ' AND (p.ReceiptNo LIKE :q OR party.FullName LIKE :q2 OR p.TransactionReference LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql .= ' ORDER BY p.PaymentDate DESC, p.PaymentID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

$total = array_sum(array_map(static fn ($r) => (float) $r['Amount'], $payments));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payment History</h1>
        <p class="text-muted mb-0">Completed payment receipts and allocations</p>
    </div>
    <a href="<?= e(base_url('modules/billing/payments.php')) ?>" class="btn btn-primary">
        <i class="bi bi-wallet2 me-1"></i> Collect Payment
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Payments</span><strong><?= e((string) count($payments)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Total Collected</span><strong><?= e(format_money($total)) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="from">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="method">Method</label>
                <select class="form-select" id="method" name="method">
                    <option value="">All</option>
                    <?php foreach (['Cash', 'Bank Transfer', 'JazzCash', 'EasyPaisa', 'Cheque', 'Card'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $method === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Receipt, payer, reference">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Payer</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Allocated To</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$payments): ?>
                    <tr><td colspan="8" class="empty-state">No payments found.</td></tr>
                <?php else: foreach ($payments as $row): ?>
                    <tr>
                        <td><strong><?= e($row['ReceiptNo']) ?></strong></td>
                        <td><?= e($row['PayerName']) ?></td>
                        <td><?= e(format_datetime($row['PaymentDate'])) ?></td>
                        <td><?= e(format_money($row['Amount'])) ?></td>
                        <td><?= e($row['PaymentMethod']) ?></td>
                        <td><?= e($row['TransactionReference'] ?: '—') ?></td>
                        <td class="small"><?= e($row['Allocations'] ?: '—') ?></td>
                        <td><span class="badge badge-soft resolved"><?= e($row['PaymentStatus']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
