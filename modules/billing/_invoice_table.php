<?php

declare(strict_types=1);

/** @var array $invoices */
$invoices = $invoices ?? [];
$showCharge = $showCharge ?? false;
?>
<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Resident</th>
                    <th>Unit</th>
                    <th>Month</th>
                    <?php if ($showCharge): ?><th>Charge</th><?php endif; ?>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$invoices): ?>
                    <tr><td colspan="<?= $showCharge ? 11 : 10 ?>" class="empty-state">No bills found.</td></tr>
                <?php else: foreach ($invoices as $row): ?>
                    <tr>
                        <td><strong><?= e($row['InvoiceNo']) ?></strong></td>
                        <td><?= e($row['PartyName']) ?></td>
                        <td><?= e($row['UnitNumber']) ?></td>
                        <td><?= e($row['BillingMonth']) ?></td>
                        <?php if ($showCharge): ?>
                            <td><?= e(format_money($row['ChargeAmount'] ?? 0)) ?></td>
                        <?php endif; ?>
                        <td><?= e(format_money($row['TotalAmount'])) ?></td>
                        <td><?= e(format_money($row['AllocatedAmount'])) ?></td>
                        <td><?= e(format_money(max(0, (float) $row['BalanceAmount']))) ?></td>
                        <td><?= e(format_date($row['DueDate'])) ?></td>
                        <td>
                            <span class="badge badge-soft <?= e(billing_status_badge($row['InvoiceStatus'])) ?>">
                                <?= e($row['InvoiceStatus']) ?>
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('modules/billing/invoice_view.php?id=' . (int) $row['InvoiceID'])) ?>" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ((float) $row['BalanceAmount'] > 0.009 && !in_array($row['InvoiceStatus'], ['Cancelled', 'Void'], true)): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/billing/payments.php?invoice_id=' . (int) $row['InvoiceID'])) ?>" title="Collect">
                                    <i class="bi bi-wallet2"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
