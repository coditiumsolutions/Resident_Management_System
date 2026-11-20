<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Communication History';
$currentNav = 'coord-history';

$type = trim((string) ($_GET['type'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

$params = [];
$annWhere = '1=1';
$notWhere = '1=1';

if ($search !== '') {
    $annWhere .= ' AND (a.Title LIKE :q OR a.Message LIKE :q2)';
    $notWhere .= ' AND (n.Title LIKE :q3 OR n.Message LIKE :q4)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}
if ($from !== '') {
    $annWhere .= ' AND DATE(a.PostedDate) >= :from1';
    $notWhere .= ' AND DATE(n.SentDate) >= :from2';
    $params['from1'] = $from;
    $params['from2'] = $from;
}
if ($to !== '') {
    $annWhere .= ' AND DATE(a.PostedDate) <= :to1';
    $notWhere .= ' AND DATE(n.SentDate) <= :to2';
    $params['to1'] = $to;
    $params['to2'] = $to;
}

$sqlParts = [];
if ($type === '' || $type === 'Announcement') {
    $sqlParts[] = "SELECT 'Announcement' AS CommType, a.AnnouncementID AS RefID, a.Title, a.Message,
                          a.TargetType, a.Status, a.PostedDate AS EventDate, u.FullName AS ActorName,
                          NULL AS Channel, NULL AS RecipientCount
                   FROM Announcements a
                   INNER JOIN Users u ON u.UserID = a.PostedByUserID
                   WHERE {$annWhere}";
}
if ($type === '' || $type === 'Notification') {
    $sqlParts[] = "SELECT 'Notification' AS CommType, n.NotificationID AS RefID, n.Title, n.Message,
                          n.TargetType, n.Status, n.SentDate AS EventDate, u.FullName AS ActorName,
                          n.Channel, n.RecipientCount
                   FROM Notifications n
                   INNER JOIN Users u ON u.UserID = n.SentByUserID
                   WHERE {$notWhere}";
}

$sql = implode(' UNION ALL ', $sqlParts) . ' ORDER BY EventDate DESC, RefID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$annCount = count(array_filter($rows, static fn ($r) => $r['CommType'] === 'Announcement'));
$notCount = count(array_filter($rows, static fn ($r) => $r['CommType'] === 'Notification'));

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Communication History</h1>
        <p class="text-muted mb-0">Combined announcements and notification log</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/coordination/announcements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-newspaper me-1"></i> Announcements</a>
        <a href="<?= e(base_url('modules/coordination/notify.php')) ?>" class="btn btn-primary"><i class="bi bi-send me-1"></i> Send</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Records</span><strong><?= e((string) count($rows)) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Announcements</span><strong><?= e((string) $annCount) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Notifications</span><strong><?= e((string) $notCount) ?></strong></div></div>
</div>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="from">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="to">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="type">Type</label>
                <select class="form-select" id="type" name="type">
                    <option value="">All</option>
                    <option value="Announcement" <?= $type === 'Announcement' ? 'selected' : '' ?>>Announcement</option>
                    <option value="Notification" <?= $type === 'Notification' ? 'selected' : '' ?>>Notification</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Title or message">
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
                    <th>When</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>Audience</th>
                    <th>Channel / Recipients</th>
                    <th>By</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty-state">No communication history found.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(format_datetime($row['EventDate'])) ?></td>
                        <td><span class="badge badge-soft <?= $row['CommType'] === 'Announcement' ? 'new' : 'progress' ?>"><?= e($row['CommType']) ?></span></td>
                        <td>
                            <strong><?= e($row['Title']) ?></strong>
                            <?php $msg = strip_tags((string) $row['Message']); ?>
                            <div class="text-muted small"><?= e(strlen($msg) > 70 ? substr($msg, 0, 70) . '…' : $msg) ?></div>
                        </td>
                        <td><?= e($row['TargetType']) ?></td>
                        <td>
                            <?= e($row['Channel'] ?: '—') ?>
                            <?php if ($row['RecipientCount'] !== null): ?>
                                <div class="text-muted small"><?= e((string) $row['RecipientCount']) ?> recipients</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($row['ActorName']) ?></td>
                        <td><span class="badge badge-soft <?= e(coord_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
