<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/complaints/_helpers.php';
require_login();

$pageTitle = 'Complaint Report';
$currentNav = 'report-complaint';

$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? date('Y-m-t')));
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
$search = trim((string) ($_GET['q'] ?? ''));

$where = ' AND DATE(c.CreatedDate) BETWEEN :from_date AND :to_date';
$params = ['from_date' => $from, 'to_date' => $to];

if ($status !== '') {
    $where .= ' AND c.Status = :status';
    $params['status'] = $status;
}
if ($priority !== '') {
    $where .= ' AND c.Priority = :priority';
    $params['priority'] = $priority;
}
if ($categoryId > 0) {
    $where .= ' AND c.CategoryID = :category_id';
    $params['category_id'] = $categoryId;
}
if ($search !== '') {
    $where .= ' AND (c.ComplaintNo LIKE :q OR c.Subject LIKE :q2 OR party.FullName LIKE :q3 OR u.UnitNumber LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}

$sql = complaint_list_sql($where) . ' ORDER BY c.CreatedDate DESC, c.ComplaintID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pending = count(array_filter($rows, static fn ($r) => in_array($r['Status'], complaint_pending_statuses(), true)));
$resolved = count(array_filter($rows, static fn ($r) => in_array($r['Status'], complaint_resolved_statuses(), true)));

$byCategory = [];
foreach ($rows as $row) {
    $byCategory[$row['CategoryName']] = ($byCategory[$row['CategoryName']] ?? 0) + 1;
}
arsort($byCategory);

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Complaint Report</h1>
        <p class="text-muted mb-0">Complaint volume and status analytics</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Complaints</span><strong><?= e((string) count($rows)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Pending</span><strong><?= e((string) $pending) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Resolved</span><strong><?= e((string) $resolved) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Top Category</span><strong><?= e($byCategory ? (string) array_key_first($byCategory) : '—') ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label" for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (complaint_statuses() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All</option>
                    <?php foreach (complaint_priorities() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $priority === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All</option>
                    <?php foreach (complaint_categories(db()) as $cat): ?>
                        <option value="<?= (int) $cat['ComplaintCategoryID'] ?>" <?= $categoryId === (int) $cat['ComplaintCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
            <div class="col-md-12">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Complaint no, subject, resident, unit">
            </div>
        </form>
    </div>
</div>

<?php if ($byCategory): ?>
<div class="card panel-card mb-3">
    <div class="card-header">By Category</div>
    <div class="card-body">
        <div class="row g-2">
            <?php foreach ($byCategory as $name => $count): ?>
                <div class="col-6 col-md-3"><div class="stat-mini"><span><?= e($name) ?></span><strong><?= e((string) $count) ?></strong></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Complaint #</th>
                    <th>Subject</th>
                    <th>Resident / Unit</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty-state">No complaints found for this period.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['ComplaintNo']) ?></strong></td>
                        <td><?= e($row['Subject']) ?></td>
                        <td><?= e($row['PartyName']) ?><div class="text-muted small">Unit <?= e($row['UnitNumber']) ?></div></td>
                        <td><?= e($row['CategoryName']) ?></td>
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

<?php require ROOT_PATH . '/includes/footer.php'; ?>
