<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Resident Services';
$currentNav = 'services-resident';
$errors = [];

$units = service_unit_options(db());
$catalog = service_catalog(db(), true);

$form = [
    'UnitKey' => '',
    'ServiceID' => '',
    'StartDate' => date('Y-m-d'),
    'EndDate' => '',
    'AgreedRate' => '',
    'Quantity' => '1.00',
    'Status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        $unitId = 0;
        $partyId = 0;
        if ($form['UnitKey'] !== '' && str_contains($form['UnitKey'], ':')) {
            [$unitId, $partyId] = array_map('intval', explode(':', $form['UnitKey'], 2));
        }
        $serviceId = (int) $form['ServiceID'];

        if ($unitId <= 0 || $partyId <= 0) {
            $errors[] = 'Please select a resident unit.';
        }
        if ($serviceId <= 0) {
            $errors[] = 'Please select a service.';
        }
        if ($form['StartDate'] === '') {
            $errors[] = 'Start date is required.';
        }
        if ($form['AgreedRate'] === '' || !is_numeric($form['AgreedRate']) || (float) $form['AgreedRate'] < 0) {
            $errors[] = 'Agreed rate must be a valid amount.';
        }
        if ($form['Quantity'] === '' || !is_numeric($form['Quantity']) || (float) $form['Quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if (!in_array($form['Status'], service_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO UnitServices
                        (UnitID, PartyID, ServiceID, StartDate, EndDate, AgreedRate, Quantity, Status)
                     VALUES
                        (:unit_id, :party_id, :service_id, :start_date, :end_date, :rate, :qty, :status)'
                )->execute([
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'service_id' => $serviceId,
                    'start_date' => $form['StartDate'],
                    'end_date' => $form['EndDate'] !== '' ? $form['EndDate'] : null,
                    'rate' => number_format((float) $form['AgreedRate'], 2, '.', ''),
                    'qty' => number_format((float) $form['Quantity'], 2, '.', ''),
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'Resident service assigned.');
                redirect('modules/services/resident.php');
            } catch (Throwable $e) {
                error_log('Resident service create failed: ' . $e->getMessage());
                $errors[] = 'Could not assign service.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));

$sql = "SELECT us.*,
               s.ServiceName, s.BillingType, c.CategoryName,
               u.UnitNumber, b.BlockName, p.PropertyName,
               party.FullName AS PartyName
        FROM UnitServices us
        INNER JOIN ServiceCatalog s ON s.ServiceID = us.ServiceID
        LEFT JOIN ServiceCategories c ON c.ServiceCategoryID = s.CategoryID
        INNER JOIN Units u ON u.UnitID = us.UnitID
        INNER JOIN Floors f ON f.FloorID = u.FloorID
        INNER JOIN Blocks b ON b.BlockID = f.BlockID
        INNER JOIN Properties p ON p.PropertyID = b.PropertyID
        LEFT JOIN Parties party ON party.PartyID = us.PartyID
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (s.ServiceName LIKE :q OR u.UnitNumber LIKE :q2 OR party.FullName LIKE :q3 OR c.CategoryName LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($statusFilter !== '') {
    $sql .= ' AND us.Status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY us.StartDate DESC, us.UnitServiceID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$activeCount = count(array_filter($assignments, static fn ($r) => $r['Status'] === 'Active'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Resident Services</h1>
        <p class="text-muted mb-0">Assign catalog services to occupied units</p>
    </div>
    <a href="<?= e(base_url('modules/services/index.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-list-check me-1"></i> Catalog
    </a>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Assignments</span><strong><?= e((string) count($assignments)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Active</span><strong><?= e((string) $activeCount) ?></strong></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Assign Service</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (!$units || !$catalog): ?>
                    <div class="alert alert-warning mb-0">
                        <?= !$catalog ? 'Add services to the catalog first.' : 'No occupied units with residents found.' ?>
                    </div>
                <?php else: ?>
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label class="form-label" for="UnitKey">Resident / Unit <span class="text-danger">*</span></label>
                            <select class="form-select" id="UnitKey" name="UnitKey" required>
                                <option value="">Select unit</option>
                                <?php foreach ($units as $unit): ?>
                                    <?php $key = (int) $unit['UnitID'] . ':' . (int) $unit['PartyID']; ?>
                                    <option value="<?= e($key) ?>" <?= $form['UnitKey'] === $key ? 'selected' : '' ?>><?= e(service_format_unit_label($unit)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="ServiceID">Service <span class="text-danger">*</span></label>
                            <select class="form-select" id="ServiceID" name="ServiceID" required>
                                <option value="">Select service</option>
                                <?php foreach ($catalog as $svc): ?>
                                    <option value="<?= (int) $svc['ServiceID'] ?>"
                                            data-rate="<?= e((string) $svc['StandardRate']) ?>"
                                            <?= (int) $form['ServiceID'] === (int) $svc['ServiceID'] ? 'selected' : '' ?>>
                                        <?= e(($svc['CategoryName'] ? $svc['CategoryName'] . ' · ' : '') . $svc['ServiceName'] . ' (' . format_money($svc['StandardRate']) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="AgreedRate">Agreed Rate <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="AgreedRate" name="AgreedRate" value="<?= e($form['AgreedRate']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Quantity">Quantity</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="Quantity" name="Quantity" value="<?= e($form['Quantity']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="StartDate">Start Date</label>
                            <input type="date" class="form-control" id="StartDate" name="StartDate" value="<?= e($form['StartDate']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="EndDate">End Date</label>
                            <input type="date" class="form-control" id="EndDate" name="EndDate" value="<?= e($form['EndDate']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Status">Status</label>
                            <select class="form-select" id="Status" name="Status">
                                <?php foreach (service_statuses() as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Assign Service</button>
                    </form>
                    <script>
                    document.getElementById('ServiceID')?.addEventListener('change', function () {
                        const opt = this.options[this.selectedIndex];
                        const rate = opt?.getAttribute('data-rate');
                        if (rate) document.getElementById('AgreedRate').value = rate;
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Service, unit, resident">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (service_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                            <th>Resident / Unit</th>
                            <th>Service</th>
                            <th>Rate</th>
                            <th>Qty</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$assignments): ?>
                            <tr><td colspan="7" class="empty-state">No resident services assigned yet.</td></tr>
                        <?php else: foreach ($assignments as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['PartyName'] ?: '—') ?></strong>
                                    <div class="text-muted small">Unit <?= e($row['UnitNumber']) ?></div>
                                </td>
                                <td>
                                    <strong><?= e($row['ServiceName']) ?></strong>
                                    <div class="text-muted small"><?= e($row['CategoryName'] ?: ($row['BillingType'] ?: '')) ?></div>
                                </td>
                                <td><?= e(format_money($row['AgreedRate'])) ?></td>
                                <td><?= e(rtrim(rtrim(number_format((float) $row['Quantity'], 2, '.', ''), '0'), '.') ?: '0') ?></td>
                                <td>
                                    <?= e(format_date($row['StartDate'])) ?>
                                    <div class="text-muted small"><?= e($row['EndDate'] ? 'to ' . format_date($row['EndDate']) : 'Ongoing') ?></div>
                                </td>
                                <td><span class="badge badge-soft <?= e(service_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/services/resident_edit.php?id=' . (int) $row['UnitServiceID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/services/resident_delete.php?id=' . (int) $row['UnitServiceID'])) ?>"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
