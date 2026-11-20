<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

billing_ensure_charge_types(db());

$chargeName = $chargeName ?? 'Rent';
$pageTitle = $pageTitle ?? ($chargeName . ' Bills');
$currentNav = $currentNav ?? 'billing-rent';

$charge = billing_charge_type_by_name(db(), $chargeName);
if (!$charge) {
    flash_set('error', $chargeName . ' charge type not found.');
    redirect('modules/billing/index.php');
}

$month = trim((string) ($_GET['month'] ?? ''));
$params = ['charge_id' => (int) $charge['ChargeTypeID']];
$where = ' AND EXISTS (
    SELECT 1 FROM InvoiceLines il
    WHERE il.InvoiceID = i.InvoiceID AND il.ChargeTypeID = :charge_id
)';

if ($month !== '') {
    $where .= ' AND i.BillingMonth = :month';
    $params['month'] = $month;
}

$sql = "SELECT i.*,
               u.UnitNumber, b.BlockName, pr.PropertyName,
               party.FullName AS PartyName,
               COALESCE(a.allocated, 0) AS AllocatedAmount,
               (i.TotalAmount - COALESCE(a.allocated, 0)) AS BalanceAmount,
               (
                   SELECT COALESCE(SUM(il2.Amount), 0)
                   FROM InvoiceLines il2
                   WHERE il2.InvoiceID = i.InvoiceID AND il2.ChargeTypeID = :charge_id2
               ) AS ChargeAmount
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
        WHERE i.InvoiceStatus NOT IN ('Cancelled', 'Void') {$where}
        ORDER BY i.BillingMonth DESC, i.InvoiceID DESC";

$params['charge_id2'] = (int) $charge['ChargeTypeID'];
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();
$showCharge = true;

$chargeTotal = array_sum(array_map(static fn ($r) => (float) ($r['ChargeAmount'] ?? 0), $invoices));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="text-muted mb-0">Invoices containing <?= e($chargeName) ?> charges</p>
    </div>
    <a href="<?= e(base_url('modules/billing/generate.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i> Generate Bills
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="stat-mini"><span>Invoices</span><strong><?= e((string) count($invoices)) ?></strong></div></div>
    <div class="col-6 col-md-4"><div class="stat-mini"><span><?= e($chargeName) ?> Total</span><strong><?= e(format_money($chargeTotal)) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="month">Billing Month</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= e($month) ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_invoice_table.php'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
