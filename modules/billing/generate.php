<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Generate Bills';
$currentNav = 'billing-generate';
$errors = [];

billing_ensure_charge_types(db());
$chargeTypes = billing_charge_types(db());
$units = billing_billable_units(db());

$defaults = [
    'BillingMonth' => date('Y-m'),
    'IssueDate' => date('Y-m-d'),
    'DueDate' => date('Y-m-d', strtotime('+10 days')),
    'UnitIDs' => [],
    'ChargeTypeIDs' => array_map(static fn ($c) => (int) $c['ChargeTypeID'], $chargeTypes),
    'Rates' => [],
];

foreach ($chargeTypes as $ct) {
    $defaults['Rates'][(int) $ct['ChargeTypeID']] = match ($ct['ChargeName']) {
        'Rent' => '40000',
        'Electricity' => '4500',
        'Maintenance' => '2500',
        'Water' => '1200',
        default => '1000',
    };
}

$form = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $form['BillingMonth'] = trim((string) ($_POST['BillingMonth'] ?? ''));
        $form['IssueDate'] = trim((string) ($_POST['IssueDate'] ?? ''));
        $form['DueDate'] = trim((string) ($_POST['DueDate'] ?? ''));
        $form['UnitIDs'] = array_map('intval', (array) ($_POST['UnitIDs'] ?? []));
        $form['ChargeTypeIDs'] = array_map('intval', (array) ($_POST['ChargeTypeIDs'] ?? []));
        $form['Rates'] = [];
        foreach ((array) ($_POST['Rates'] ?? []) as $chargeId => $rate) {
            $form['Rates'][(int) $chargeId] = trim((string) $rate);
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $form['BillingMonth'])) {
            $errors[] = 'Valid billing month is required.';
        }
        if ($form['IssueDate'] === '' || $form['DueDate'] === '') {
            $errors[] = 'Issue date and due date are required.';
        }
        if (!$form['UnitIDs']) {
            $errors[] = 'Select at least one unit.';
        }
        if (!$form['ChargeTypeIDs']) {
            $errors[] = 'Select at least one charge type.';
        }

        $selectedCharges = [];
        foreach ($chargeTypes as $ct) {
            $id = (int) $ct['ChargeTypeID'];
            if (in_array($id, $form['ChargeTypeIDs'], true)) {
                $rate = (float) ($form['Rates'][$id] ?? 0);
                if ($rate < 0) {
                    $errors[] = 'Rates cannot be negative.';
                    break;
                }
                $selectedCharges[] = ['id' => $id, 'name' => $ct['ChargeName'], 'rate' => $rate];
            }
        }

        if (!$errors) {
            $unitMap = [];
            foreach ($units as $unit) {
                $unitMap[(int) $unit['UnitID']] = $unit;
            }

            $created = 0;
            $skipped = 0;
            $pdo = db();

            try {
                $pdo->beginTransaction();

                foreach ($form['UnitIDs'] as $unitId) {
                    if (!isset($unitMap[$unitId])) {
                        continue;
                    }
                    $unit = $unitMap[$unitId];

                    $exists = $pdo->prepare(
                        'SELECT InvoiceID FROM Invoices
                         WHERE UnitID = :unit_id AND BillingMonth = :month
                           AND InvoiceStatus NOT IN (\'Cancelled\', \'Void\')
                         LIMIT 1'
                    );
                    $exists->execute(['unit_id' => $unitId, 'month' => $form['BillingMonth']]);
                    if ($exists->fetch()) {
                        $skipped++;
                        continue;
                    }

                    $subtotal = array_sum(array_column($selectedCharges, 'rate'));
                    $invoiceNo = billing_next_invoice_no($pdo, $form['BillingMonth']);

                    $pdo->prepare(
                        'INSERT INTO Invoices
                            (InvoiceNo, UnitID, BilledPartyID, BillingMonth, IssueDate, DueDate,
                             SubtotalAmount, DiscountAmount, TaxAmount, TotalAmount, InvoiceStatus, Remarks)
                         VALUES
                            (:no, :unit_id, :party_id, :month, :issue, :due,
                             :subtotal, 0, 0, :total, :status, :remarks)'
                    )->execute([
                        'no' => $invoiceNo,
                        'unit_id' => $unitId,
                        'party_id' => (int) $unit['PartyID'],
                        'month' => $form['BillingMonth'],
                        'issue' => $form['IssueDate'],
                        'due' => $form['DueDate'],
                        'subtotal' => $subtotal,
                        'total' => $subtotal,
                        'status' => ($form['DueDate'] < date('Y-m-d')) ? 'Overdue' : 'Issued',
                        'remarks' => 'Generated bill for unit ' . $unit['UnitNumber'],
                    ]);
                    $invoiceId = (int) $pdo->lastInsertId();

                    $lineStmt = $pdo->prepare(
                        'INSERT INTO InvoiceLines (InvoiceID, ChargeTypeID, Description, Quantity, Rate, Amount)
                         VALUES (:invoice_id, :charge_type_id, :description, 1, :rate, :amount)'
                    );
                    foreach ($selectedCharges as $charge) {
                        $lineStmt->execute([
                            'invoice_id' => $invoiceId,
                            'charge_type_id' => $charge['id'],
                            'description' => $charge['name'] . ' - ' . $unit['UnitNumber'] . ' (' . $form['BillingMonth'] . ')',
                            'rate' => $charge['rate'],
                            'amount' => $charge['rate'],
                        ]);
                    }
                    $created++;
                }

                $pdo->commit();
                flash_set('success', "Generated {$created} bill(s)" . ($skipped ? ", skipped {$skipped} existing." : '.'));
                redirect('modules/billing/index.php?month=' . urlencode($form['BillingMonth']));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Generate bills failed: ' . $e->getMessage());
                $errors[] = 'Could not generate bills. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Generate Bills</h1>
        <p class="text-muted mb-0">Create monthly invoices for occupied units</p>
    </div>
    <a href="<?= e(base_url('modules/billing/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$units): ?>
            <div class="alert alert-warning mb-0">No occupied units with primary residents found.</div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label" for="BillingMonth">Billing Month</label>
                        <input type="month" class="form-control" id="BillingMonth" name="BillingMonth" value="<?= e($form['BillingMonth']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="IssueDate">Issue Date</label>
                        <input type="date" class="form-control" id="IssueDate" name="IssueDate" value="<?= e($form['IssueDate']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="DueDate">Due Date</label>
                        <input type="date" class="form-control" id="DueDate" name="DueDate" value="<?= e($form['DueDate']) ?>" required>
                    </div>
                </div>

                <h6 class="mb-2">Charge Types & Rates</h6>
                <div class="row g-2 mb-4">
                    <?php foreach ($chargeTypes as $ct): ?>
                        <?php $cid = (int) $ct['ChargeTypeID']; ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="ChargeTypeIDs[]" value="<?= $cid ?>" id="ct<?= $cid ?>"
                                        <?= in_array($cid, $form['ChargeTypeIDs'], true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="ct<?= $cid ?>"><strong><?= e($ct['ChargeName']) ?></strong></label>
                                </div>
                                <input type="number" class="form-control" name="Rates[<?= $cid ?>]" min="0" step="0.01"
                                       value="<?= e((string) ($form['Rates'][$cid] ?? '0')) ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Units (<?= count($units) ?> billable)</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleUnits">Select / Clear All</button>
                </div>
                <div class="row g-2 mb-4" style="max-height: 320px; overflow:auto;">
                    <?php foreach ($units as $unit): ?>
                        <?php $uid = (int) $unit['UnitID']; ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="form-check border rounded px-3 py-2">
                                <input class="form-check-input unit-check" type="checkbox" name="UnitIDs[]" value="<?= $uid ?>" id="u<?= $uid ?>"
                                    <?= in_array($uid, $form['UnitIDs'], true) || $_SERVER['REQUEST_METHOD'] !== 'POST' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="u<?= $uid ?>">
                                    <strong><?= e($unit['UnitNumber']) ?></strong>
                                    <span class="text-muted small d-block"><?= e($unit['PartyName']) ?> · <?= e($unit['OccupancyType']) ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn btn-primary"><i class="bi bi-lightning-charge me-1"></i> Generate Bills</button>
                <a href="<?= e(base_url('modules/billing/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('toggleUnits')?.addEventListener('click', () => {
  const boxes = [...document.querySelectorAll('.unit-check')];
  const allChecked = boxes.every((b) => b.checked);
  boxes.forEach((b) => { b.checked = !allChecked; });
});
</script>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
