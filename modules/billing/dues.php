<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Outstanding Dues';
$currentNav = 'billing-dues';

$sql = billing_invoice_list_sql(
    " AND i.InvoiceStatus NOT IN ('Cancelled', 'Void', 'Paid')
      AND (i.TotalAmount - COALESCE(a.allocated, 0)) > 0.009"
) . ' ORDER BY i.DueDate ASC, BalanceAmount DESC';

$invoices = db()->query($sql)->fetchAll();
$totalDue = array_sum(array_map(static fn ($r) => max(0, (float) $r['BalanceAmount']), $invoices));
$overdue = array_filter($invoices, static fn ($r) => $r['DueDate'] < date('Y-m-d'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Outstanding Dues</h1>
        <p class="text-muted mb-0">Unpaid and partially paid invoices</p>
    </div>
    <a href="<?= e(base_url('modules/billing/payments.php')) ?>" class="btn btn-primary">
        <i class="bi bi-wallet2 me-1"></i> Collect Payment
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="stat-mini"><span>Open Invoices</span><strong><?= e((string) count($invoices)) ?></strong></div></div>
    <div class="col-6 col-md-4"><div class="stat-mini"><span>Overdue</span><strong><?= e((string) count($overdue)) ?></strong></div></div>
    <div class="col-6 col-md-4"><div class="stat-mini"><span>Total Outstanding</span><strong><?= e(format_money($totalDue)) ?></strong></div></div>
</div>

<?php require __DIR__ . '/_invoice_table.php'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
