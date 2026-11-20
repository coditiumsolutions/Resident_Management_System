<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Announcement';
$currentNav = 'coord-announcements';
$errors = [];
$units = coord_unit_options(db());
$residents = coord_resident_options(db());

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid announcement selected.');
    redirect('modules/coordination/announcements.php');
}

$stmt = db()->prepare('SELECT * FROM Announcements WHERE AnnouncementID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    flash_set('error', 'Announcement not found.');
    redirect('modules/coordination/announcements.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'Title' => trim((string) ($_POST['Title'] ?? '')),
            'Message' => trim((string) ($_POST['Message'] ?? '')),
            'TargetType' => trim((string) ($_POST['TargetType'] ?? '')),
            'TargetUnitID' => trim((string) ($_POST['TargetUnitID'] ?? '')),
            'TargetPartyID' => trim((string) ($_POST['TargetPartyID'] ?? '')),
            'ExpiryDate' => trim((string) ($_POST['ExpiryDate'] ?? '')),
            'Status' => trim((string) ($_POST['Status'] ?? '')),
        ];
        if ($data['Title'] === '' || $data['Message'] === '') {
            $errors[] = 'Title and message are required.';
        }
        if (!in_array($data['TargetType'], coord_target_types(), true)) {
            $errors[] = 'Invalid target type.';
        }
        if (!in_array($data['Status'], coord_announcement_statuses(), true)) {
            $errors[] = 'Invalid status.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE Announcements
                     SET Title = :title, Message = :message, TargetType = :target_type,
                         TargetUnitID = :unit_id, TargetPartyID = :party_id,
                         ExpiryDate = :expiry, Status = :status
                     WHERE AnnouncementID = :id'
                )->execute([
                    'title' => $data['Title'],
                    'message' => $data['Message'],
                    'target_type' => $data['TargetType'],
                    'unit_id' => $data['TargetType'] === 'Specific Unit' ? (int) $data['TargetUnitID'] : null,
                    'party_id' => $data['TargetType'] === 'Specific Resident' ? (int) $data['TargetPartyID'] : null,
                    'expiry' => $data['ExpiryDate'] !== '' ? date('Y-m-d H:i:s', strtotime($data['ExpiryDate'])) : null,
                    'status' => $data['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Announcement updated.');
                redirect('modules/coordination/announcements.php');
            } catch (Throwable $e) {
                error_log('Announcement update failed: ' . $e->getMessage());
                $errors[] = 'Could not update announcement.';
            }
        }
        $row = array_merge($row, $data);
    }
}

$expiryLocal = !empty($row['ExpiryDate']) ? date('Y-m-d\TH:i', strtotime($row['ExpiryDate'])) : '';

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Announcement</h1><p class="text-muted mb-0"><?= e($row['Title']) ?></p></div>
    <a href="<?= e(base_url('modules/coordination/announcements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:720px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label" for="Title">Title</label><input type="text" class="form-control" id="Title" name="Title" maxlength="200" value="<?= e($row['Title']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="Message">Message</label><textarea class="form-control" id="Message" name="Message" rows="5" required><?= e($row['Message']) ?></textarea></div>
        <div class="mb-3">
            <label class="form-label" for="TargetType">Audience</label>
            <select class="form-select" id="TargetType" name="TargetType">
                <?php foreach (coord_target_types() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $row['TargetType'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3" id="unitWrap">
            <label class="form-label" for="TargetUnitID">Unit</label>
            <select class="form-select" id="TargetUnitID" name="TargetUnitID">
                <option value="">Select unit</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) ($row['TargetUnitID'] ?? 0) === (int) $unit['UnitID'] ? 'selected' : '' ?>><?= e($unit['PropertyName'] . ' / Unit ' . $unit['UnitNumber']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3" id="partyWrap">
            <label class="form-label" for="TargetPartyID">Resident</label>
            <select class="form-select" id="TargetPartyID" name="TargetPartyID">
                <option value="">Select resident</option>
                <?php foreach ($residents as $resident): ?>
                    <option value="<?= (int) $resident['PartyID'] ?>" <?= (int) ($row['TargetPartyID'] ?? 0) === (int) $resident['PartyID'] ? 'selected' : '' ?>><?= e($resident['FullName'] . ' · Unit ' . $resident['UnitNumber']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3"><label class="form-label" for="ExpiryDate">Expiry</label><input type="datetime-local" class="form-control" id="ExpiryDate" name="ExpiryDate" value="<?= e($expiryLocal) ?>"></div>
        <div class="mb-3">
            <label class="form-label" for="Status">Status</label>
            <select class="form-select" id="Status" name="Status">
                <?php foreach (coord_announcement_statuses() as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $row['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update</button>
        <a href="<?= e(base_url('modules/coordination/announcements.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
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
