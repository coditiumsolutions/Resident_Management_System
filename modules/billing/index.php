<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'All Bills';
$currentNav = 'billing-all';

$month = trim((string) ($_GET['month'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$where = '';
$params = [];
if ($month !== '') {
    $where .= ' AND i.BillingMonth = :month';
    $params['month'] = $month;
}
if ($status !== '') {
    $where .= ' AND i.InvoiceStatus = :status';
    $params['status'] = $status;
}
if ($search !== '') {
    $where .= ' AND (i.InvoiceNo LIKE :q OR party.FullName LIKE :q2 OR u.UnitNumber LIKE :q3)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$sql = billing_invoice_list_sql($where) . ' ORDER BY i.IssueDate DESC, i.InvoiceID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$summary = [
    'count' => count($invoices),
    'total' => array_sum(array_map(static fn ($r) => (float) $r['TotalAmount'], $invoices)),
    'paid' => array_sum(array_map(static fn ($r) => (float) $r['AllocatedAmount'], $invoices)),
    'balance' => array_sum(array_map(static fn ($r) => max(0, (float) $r['BalanceAmount']), $invoices)),
];

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>All Bills</h1>
        <p class="text-muted mb-0">Complete invoice listing across all charge types</p>
    </div>
    <a href="<?= e(base_url('modules/billing/generate.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Generate Bills
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Bills</span><strong><?= e((string) $summary['count']) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Billed</span><strong><?= e(format_money($summary['total'])) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Collected</span><strong><?= e(format_money($summary['paid'])) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Outstanding</span><strong><?= e(format_money($summary['balance'])) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="month">Billing Month</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= e($month) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (['Issued', 'Partially Paid', 'Paid', 'Overdue', 'Cancelled'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Invoice, resident, unit">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_invoice_table.php'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
