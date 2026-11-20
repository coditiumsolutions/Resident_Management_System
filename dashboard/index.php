<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/dashboard_data.php';
require_login();

$pageTitle = 'Home';
$currentNav = 'dashboard';
$data = get_dashboard_data(db());
$stats = $data['stats'];
$charts = $data['charts'];

require ROOT_PATH . '/includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Monthly Collection Trend (Line)</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartCollection"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Billing Summary (Bar) · <?= e($data['billing_month']) ?></div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartBilling"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Complaint Status (Bar)</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="chartComplaints"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card panel-card h-100">
            <div class="card-header">Unit Occupancy (Line)</div>
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
    type: 'line',
    data: {
      labels: data.collection.labels,
      datasets: [{
        label: 'Collection',
        data: data.collection.values,
        borderColor: colors.blue,
        backgroundColor: 'rgba(31, 111, 235, 0.18)',
        fill: true,
        tension: 0.35,
        pointRadius: 4,
        pointBackgroundColor: colors.blue
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
    type: 'bar',
    data: {
      labels: data.billing.labels,
      datasets: [{
        label: 'Amount',
        data: data.billing.values,
        backgroundColor: [colors.navy, colors.gold, colors.blue, '#38bdf8', colors.orange],
        borderRadius: 6,
        maxBarThickness: 42
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

  new Chart(document.getElementById('chartComplaints'), {
    type: 'bar',
    data: {
      labels: data.complaints.labels,
      datasets: [{
        label: 'Complaints',
        data: data.complaints.values,
        backgroundColor: 'rgba(109, 40, 217, 0.75)',
        borderRadius: 6,
        maxBarThickness: 36
      }]
    },
    options: {
      ...commonOptions,
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });

  new Chart(document.getElementById('chartOccupancy'), {
    type: 'line',
    data: {
      labels: data.occupancy.labels,
      datasets: [{
        label: 'Units',
        data: data.occupancy.values,
        borderColor: colors.green,
        backgroundColor: 'rgba(21, 128, 61, 0.16)',
        fill: true,
        tension: 0.3,
        pointRadius: 5,
        pointBackgroundColor: colors.green
      }]
    },
    options: {
      ...commonOptions,
      scales: {
        x: { grid: { display: false } },
        y: { beginAtZero: true, ticks: { stepSize: 1 } }
      }
    }
  });
})();
</script>
HTML;

require ROOT_PATH . '/includes/footer.php';
