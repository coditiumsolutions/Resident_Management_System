<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/dashboard_data.php';
require_login();

$pageTitle = 'Dashboard';
$currentNav = 'dashboard';
$data = get_dashboard_data(db());
$stats = $data['stats'];
$charts = $data['charts'];

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="text-muted mb-0">
            Overview for <?= e(date('F Y')) ?> · Billing month <?= e($data['billing_month']) ?>
        </p>
    </div>
    <div class="text-muted small">
        <i class="bi bi-calendar3 me-1"></i><?= e(date('l, d M Y')) ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Total Properties', $stats['total_properties'], 'bi-buildings', 'navy', false],
        ['Total Units', $stats['total_units'], 'bi-door-closed', 'blue', false],
        ['Occupied Units', $stats['occupied_units'], 'bi-house-check', 'green', false],
        ['Vacant Units', $stats['vacant_units'], 'bi-house', 'orange', false],
        ['Total Owners', $stats['total_owners'], 'bi-person-badge', 'navy', false],
        ['Total Tenants', $stats['total_tenants'], 'bi-person-vcard', 'blue', false],
        ['Current Month Bills', $stats['month_bills'], 'bi-receipt', 'gold', true],
        ['Current Month Collection', $stats['month_collection'], 'bi-cash-coin', 'green', true],
        ['Outstanding Amount', $stats['outstanding'], 'bi-exclamation-circle', 'orange', true],
        ['Pending Complaints', $stats['pending_complaints'], 'bi-chat-left-dots', 'red', false],
    ];
    foreach ($cards as [$label, $value, $icon, $tone, $money]):
    ?>
        <div class="col-6 col-md-4 col-xl-3 col-xxl">
            <div class="stat-card">
                <div class="stat-icon <?= e($tone) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                <div>
                    <div class="label"><?= e($label) ?></div>
                    <div class="value"><?= $money ? e(format_money($value)) : e((string) $value) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Monthly Collection (Last 12 Months)</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartCollection"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Billing Summary · <?= e($data['billing_month']) ?></div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartBilling"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Complaint Status</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartComplaints"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Unit Occupancy</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartOccupancy"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Payments</span>
                <a class="small" href="<?= e(base_url('modules/billing/history.php')) ?>">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Resident</th>
                            <th>Unit</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$data['recent_payments']): ?>
                            <tr><td colspan="6" class="empty-state">No payments recorded yet.</td></tr>
                        <?php else: foreach ($data['recent_payments'] as $row): ?>
                            <tr>
                                <td><?= e($row['ReceiptNo']) ?></td>
                                <td><?= e($row['ResidentName']) ?></td>
                                <td><?= e($row['UnitNumber'] ?? '—') ?></td>
                                <td><?= e(format_datetime($row['PaymentDate'])) ?></td>
                                <td><?= e(format_money($row['Amount'])) ?></td>
                                <td><?= e($row['PaymentMethod']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Complaints</span>
                <a class="small" href="<?= e(base_url('modules/complaints/index.php')) ?>">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>No.</th>
                            <th>Resident</th>
                            <th>Unit</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$data['recent_complaints']): ?>
                            <tr><td colspan="7" class="empty-state">No complaints recorded yet.</td></tr>
                        <?php else: foreach ($data['recent_complaints'] as $row): ?>
                            <tr>
                                <td><?= e($row['ComplaintNo']) ?></td>
                                <td><?= e($row['ResidentName']) ?></td>
                                <td><?= e($row['UnitNumber']) ?></td>
                                <td><?= e($row['Subject']) ?></td>
                                <td><span class="badge badge-soft <?= e(status_badge_class($row['Priority'])) ?>"><?= e($row['Priority']) ?></span></td>
                                <td><span class="badge badge-soft <?= e(status_badge_class($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td><?= e(format_datetime($row['CreatedDate'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Visitors</span>
                <a class="small" href="<?= e(base_url('modules/visitors/history.php')) ?>">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Visitor</th>
                            <th>Contact</th>
                            <th>Unit</th>
                            <th>Check-in</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$data['recent_visitors']): ?>
                            <tr><td colspan="5" class="empty-state">No visitor activity yet.</td></tr>
                        <?php else: foreach ($data['recent_visitors'] as $row): ?>
                            <tr>
                                <td><?= e($row['VisitorName']) ?></td>
                                <td><?= e($row['ContactNumber'] ?? '—') ?></td>
                                <td><?= e($row['UnitNumber']) ?></td>
                                <td><?= e(format_datetime($row['CheckInTime'])) ?></td>
                                <td><span class="badge badge-soft <?= e(status_badge_class($row['VisitStatus'])) ?>"><?= e($row['VisitStatus']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Announcements</span>
                <a class="small" href="<?= e(base_url('modules/coordination/announcements.php')) ?>">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Posted Date</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$data['recent_announcements']): ?>
                            <tr><td colspan="3" class="empty-state">No announcements posted yet.</td></tr>
                        <?php else: foreach ($data['recent_announcements'] as $row): ?>
                            <tr>
                                <td><?= e($row['Title']) ?></td>
                                <td><?= e(format_datetime($row['PostedDate'])) ?></td>
                                <td><span class="badge badge-soft <?= e(status_badge_class($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartJson = json_encode($charts, JSON_UNESCAPED_UNICODE);
$pageScripts = <<<HTML
<script>
(() => {
  const data = {$chartJson};
  const colors = {
    navy: '#0b1f3a',
    blue: '#1f6feb',
    gold: '#c9a227',
    green: '#15803d',
    orange: '#c2410c',
    slate: '#64748b',
    purple: '#6d28d9'
  };

  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
    }
  };

  new Chart(document.getElementById('chartCollection'), {
    type: 'bar',
    data: {
      labels: data.collection.labels,
      datasets: [{
        label: 'Collection',
        data: data.collection.values,
        backgroundColor: 'rgba(31, 111, 235, 0.75)',
        borderRadius: 6,
        maxBarThickness: 28
      }]
    },
    options: {
      ...commonOptions,
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { callback: (v) => Number(v).toLocaleString() } }
      }
    }
  });

  new Chart(document.getElementById('chartBilling'), {
    type: 'doughnut',
    data: {
      labels: data.billing.labels,
      datasets: [{
        data: data.billing.values,
        backgroundColor: [colors.navy, colors.gold, colors.blue, '#38bdf8', colors.orange],
        borderWidth: 0
      }]
    },
    options: commonOptions
  });

  new Chart(document.getElementById('chartComplaints'), {
    type: 'doughnut',
    data: {
      labels: data.complaints.labels,
      datasets: [{
        data: data.complaints.values,
        backgroundColor: [colors.blue, colors.purple, colors.orange, colors.slate, colors.green, '#334155'],
        borderWidth: 0
      }]
    },
    options: commonOptions
  });

  new Chart(document.getElementById('chartOccupancy'), {
    type: 'pie',
    data: {
      labels: data.occupancy.labels,
      datasets: [{
        data: data.occupancy.values,
        backgroundColor: [colors.green, colors.gold, colors.orange],
        borderWidth: 0
      }]
    },
    options: commonOptions
  });
})();
</script>
HTML;

require ROOT_PATH . '/includes/footer.php';
