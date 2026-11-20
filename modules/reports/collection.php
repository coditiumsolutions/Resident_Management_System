<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Collection Report';
$currentNav = 'report-collection';

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-t')));
$method = trim((string) ($_GET['method'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT p.*, party.FullName AS PayerName, u.FullName AS ReceivedByName
        FROM Payments p
        INNER JOIN Parties party ON party.PartyID = p.PayerPartyID
        LEFT JOIN Users u ON u.UserID = p.ReceivedByUserID
        WHERE p.PaymentStatus = 'Completed'
          AND DATE(p.PaymentDate) BETWEEN :from_date AND :to_date";
$params = ['from_date' => $from, 'to_date' => $to];

if ($method !== '') {
    $sql .= ' AND p.PaymentMethod = :method';
    $params['method'] = $method;
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
$byMethod = [];
foreach ($payments as $row) {
    $key = $row['PaymentMethod'] ?: 'Other';
    $byMethod[$key] = ($byMethod[$key] ?? 0) + (float) $row['Amount'];
}
$methods = db()->query("SELECT DISTINCT PaymentMethod FROM Payments WHERE PaymentMethod IS NOT NULL AND PaymentMethod <> '' ORDER BY PaymentMethod")->fetchAll(PDO::FETCH_COLUMN);

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Collection Report</h1>
        <p class="text-muted mb-0">Completed payment collections by date range</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Receipts</span><strong><?= e((string) count($payments)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Collected</span><strong><?= e(format_money($total)) ?></strong></div></div>
    <?php foreach (array_slice($byMethod, 0, 2, true) as $m => $amt): ?>
        <div class="col-6 col-md-3"><div class="stat-mini"><span><?= e($m) ?></span><strong><?= e(format_money($amt)) ?></strong></div></div>
    <?php endforeach; ?>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-3">
                <label class="form-label" for="method">Method</label>
                <select class="form-select" id="method" name="method">
                    <option value="">All</option>
                    <?php foreach ($methods as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $method === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label" for="q">Search</label><input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Receipt, payer, reference"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
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
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Received By</th>
                    <th class="text-end">Amount</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$payments): ?>
                    <tr><td colspan="7" class="empty-state">No collections found for this period.</td></tr>
                <?php else: foreach ($payments as $row): ?>
                    <tr>
                        <td><strong><?= e($row['ReceiptNo']) ?></strong></td>
                        <td><?= e($row['PayerName']) ?></td>
                        <td><?= e(format_datetime($row['PaymentDate'])) ?></td>
                        <td><?= e($row['PaymentMethod'] ?: '—') ?></td>
                        <td><?= e($row['TransactionReference'] ?: '—') ?></td>
                        <td><?= e($row['ReceivedByName'] ?: '—') ?></td>
                        <td class="text-end"><strong><?= e(format_money($row['Amount'])) ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ($payments): ?>
                <tfoot>
                    <tr>
                        <th colspan="6" class="text-end">Total</th>
                        <th class="text-end"><?= e(format_money($total)) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
