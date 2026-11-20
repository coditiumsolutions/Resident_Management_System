<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Outstanding Report';
$currentNav = 'report-outstanding';

$month = trim((string) ($_GET['month'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));

$where = " AND i.InvoiceStatus NOT IN ('Cancelled', 'Void', 'Paid')";
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

$sql = billing_invoice_list_sql($where) . ' ORDER BY i.DueDate ASC, i.InvoiceID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$totalBilled = array_sum(array_map(static fn ($r) => (float) $r['TotalAmount'], $invoices));
$totalPaid = array_sum(array_map(static fn ($r) => (float) $r['AllocatedAmount'], $invoices));
$totalBalance = array_sum(array_map(static fn ($r) => max(0, (float) $r['BalanceAmount']), $invoices));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Outstanding Report</h1>
        <p class="text-muted mb-0">Unpaid and partially paid invoices</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Invoices</span><strong><?= e((string) count($invoices)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Billed</span><strong><?= e(format_money($totalBilled)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Collected</span><strong><?= e(format_money($totalPaid)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Outstanding</span><strong><?= e(format_money($totalBalance)) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="month">Billing Month</label>
                <select class="form-select" id="month" name="month">
                    <option value="">All</option>
                    <?php foreach (report_month_options() as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $month === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All open</option>
                    <?php foreach (['Issued', 'Partially Paid', 'Overdue'] as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Invoice, resident, unit">
            </div>
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
                    <th>Invoice</th>
                    <th>Resident</th>
                    <th>Unit</th>
                    <th>Month</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$invoices): ?>
                    <tr><td colspan="9" class="empty-state">No outstanding invoices found.</td></tr>
                <?php else: foreach ($invoices as $row): ?>
                    <tr>
                        <td><strong><?= e($row['InvoiceNo']) ?></strong></td>
                        <td><?= e($row['PartyName']) ?></td>
                        <td><?= e($row['UnitNumber']) ?></td>
                        <td><?= e($row['BillingMonth']) ?></td>
                        <td><?= e(format_date($row['DueDate'])) ?></td>
                        <td><span class="badge badge-soft <?= e(billing_status_badge($row['InvoiceStatus'])) ?>"><?= e($row['InvoiceStatus']) ?></span></td>
                        <td class="text-end"><?= e(format_money($row['TotalAmount'])) ?></td>
                        <td class="text-end"><?= e(format_money($row['AllocatedAmount'])) ?></td>
                        <td class="text-end"><strong><?= e(format_money(max(0, (float) $row['BalanceAmount']))) ?></strong></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
