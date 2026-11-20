<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Announcements';
$currentNav = 'coord-announcements';
$errors = [];
$units = coord_unit_options(db());
$residents = coord_resident_options(db());

$form = [
    'Title' => '',
    'Message' => '',
    'TargetType' => 'All Residents',
    'TargetUnitID' => '',
    'TargetPartyID' => '',
    'ExpiryDate' => '',
    'Status' => 'Published',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($form['Title'] === '') {
            $errors[] = 'Title is required.';
        }
        if ($form['Message'] === '') {
            $errors[] = 'Message is required.';
        }
        if (!in_array($form['TargetType'], coord_target_types(), true)) {
            $errors[] = 'Invalid target type.';
        }
        if (!in_array($form['Status'], coord_announcement_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if ($form['TargetType'] === 'Specific Unit' && (int) $form['TargetUnitID'] <= 0) {
            $errors[] = 'Please select a target unit.';
        }
        if ($form['TargetType'] === 'Specific Resident' && (int) $form['TargetPartyID'] <= 0) {
            $errors[] = 'Please select a target resident.';
        }

        if (!$errors) {
            try {
                $user = current_user();
                db()->prepare(
                    'INSERT INTO Announcements
                        (Title, Message, TargetType, TargetUnitID, TargetPartyID, TargetUserID, PostedByUserID, PostedDate, ExpiryDate, Status)
                     VALUES
                        (:title, :message, :target_type, :unit_id, :party_id, NULL, :posted_by, NOW(), :expiry, :status)'
                )->execute([
                    'title' => $form['Title'],
                    'message' => $form['Message'],
                    'target_type' => $form['TargetType'],
                    'unit_id' => $form['TargetType'] === 'Specific Unit' ? (int) $form['TargetUnitID'] : null,
                    'party_id' => $form['TargetType'] === 'Specific Resident' ? (int) $form['TargetPartyID'] : null,
                    'posted_by' => (int) $user['UserID'],
                    'expiry' => $form['ExpiryDate'] !== '' ? date('Y-m-d H:i:s', strtotime($form['ExpiryDate'])) : null,
                    'status' => $form['Status'],
                ]);
                flash_set('success', 'Announcement posted.');
                redirect('modules/coordination/announcements.php');
            } catch (Throwable $e) {
                error_log('Announcement create failed: ' . $e->getMessage());
                $errors[] = 'Could not post announcement.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$sql = "SELECT a.*, u.FullName AS PostedByName, un.UnitNumber, p.FullName AS TargetPartyName
        FROM Announcements a
        INNER JOIN Users u ON u.UserID = a.PostedByUserID
        LEFT JOIN Units un ON un.UnitID = a.TargetUnitID
        LEFT JOIN Parties p ON p.PartyID = a.TargetPartyID
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (a.Title LIKE :q OR a.Message LIKE :q2)';
    $like = '%' . $search . '%';
    $params['q'] = $like;
    $params['q2'] = $like;
}
if ($status !== '') {
    $sql .= ' AND a.Status = :status';
    $params['status'] = $status;
}
$sql .= ' ORDER BY a.PostedDate DESC, a.AnnouncementID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Announcements</h1>
        <p class="text-muted mb-0">Publish notices for residents and staff</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/coordination/notify.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-send me-1"></i> Notify</a>
        <a href="<?= e(base_url('modules/coordination/history.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-chat-square-text me-1"></i> History</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Post Announcement</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="Title">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="Title" name="Title" maxlength="200" value="<?= e($form['Title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Message">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="Message" name="Message" rows="4" required><?= e($form['Message']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="TargetType">Audience</label>
                        <select class="form-select" id="TargetType" name="TargetType">
                            <?php foreach (coord_target_types() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['TargetType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="unitWrap">
                        <label class="form-label" for="TargetUnitID">Unit</label>
                        <select class="form-select" id="TargetUnitID" name="TargetUnitID">
                            <option value="">Select unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) $form['TargetUnitID'] === (int) $unit['UnitID'] ? 'selected' : '' ?>>
                                    <?= e($unit['PropertyName'] . ' / Unit ' . $unit['UnitNumber']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="partyWrap">
                        <label class="form-label" for="TargetPartyID">Resident</label>
                        <select class="form-select" id="TargetPartyID" name="TargetPartyID">
                            <option value="">Select resident</option>
                            <?php foreach ($residents as $resident): ?>
                                <option value="<?= (int) $resident['PartyID'] ?>" <?= (int) $form['TargetPartyID'] === (int) $resident['PartyID'] ? 'selected' : '' ?>>
                                    <?= e($resident['FullName'] . ' · Unit ' . $resident['UnitNumber']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ExpiryDate">Expiry</label>
                        <input type="datetime-local" class="form-control" id="ExpiryDate" name="ExpiryDate" value="<?= e($form['ExpiryDate']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Status">Status</label>
                        <select class="form-select" id="Status" name="Status">
                            <?php foreach (coord_announcement_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-megaphone me-1"></i> Publish</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Title or message">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All</option>
                            <?php foreach (coord_announcement_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
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
                            <th>Title</th>
                            <th>Audience</th>
                            <th>Posted</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="5" class="empty-state">No announcements yet.</td></tr>
                        <?php else: foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['Title']) ?></strong>
                                    <div class="text-muted small"><?= e(strlen(strip_tags($row['Message'])) > 80 ? substr(strip_tags($row['Message']), 0, 80) . '…' : strip_tags($row['Message'])) ?></div>
                                </td>
                                <td>
                                    <?= e($row['TargetType']) ?>
                                    <?php if ($row['UnitNumber']): ?><div class="text-muted small">Unit <?= e($row['UnitNumber']) ?></div><?php endif; ?>
                                    <?php if ($row['TargetPartyName']): ?><div class="text-muted small"><?= e($row['TargetPartyName']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <?= e(format_datetime($row['PostedDate'])) ?>
                                    <div class="text-muted small"><?= e($row['PostedByName']) ?></div>
                                </td>
                                <td><span class="badge badge-soft <?= e(coord_status_badge($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/coordination/announcement_edit.php?id=' . (int) $row['AnnouncementID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/coordination/announcement_delete.php?id=' . (int) $row['AnnouncementID'])) ?>"><i class="bi bi-trash"></i></a>
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
<script>
(function () {
  const type = document.getElementById('TargetType');
  const unitWrap = document.getElementById('unitWrap');
  const partyWrap = document.getElementById('partyWrap');
  function sync() {
    unitWrap.style.display = type.value === 'Specific Unit' ? '' : 'none';
    partyWrap.style.display = type.value === 'Specific Resident' ? '' : 'none';
  }
  type?.addEventListener('change', sync);
  sync();
})();
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
