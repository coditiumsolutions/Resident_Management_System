<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Send Notifications';
$currentNav = 'coord-notify';
$errors = [];
$units = coord_unit_options(db());
$residents = coord_resident_options(db());

$form = [
    'Title' => '',
    'Message' => '',
    'Channel' => 'In-App',
    'TargetType' => 'All Residents',
    'TargetUnitID' => '',
    'TargetPartyID' => '',
    'Remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        if ($form['Title'] === '' || $form['Message'] === '') {
            $errors[] = 'Title and message are required.';
        }
        if (!in_array($form['Channel'], coord_notification_channels(), true)) {
            $errors[] = 'Invalid channel.';
        }
        if (!in_array($form['TargetType'], coord_target_types(), true)) {
            $errors[] = 'Invalid audience.';
        }
        $unitId = $form['TargetType'] === 'Specific Unit' ? (int) $form['TargetUnitID'] : null;
        $partyId = $form['TargetType'] === 'Specific Resident' ? (int) $form['TargetPartyID'] : null;
        if ($form['TargetType'] === 'Specific Unit' && (!$unitId || $unitId <= 0)) {
            $errors[] = 'Please select a target unit.';
        }
        if ($form['TargetType'] === 'Specific Resident' && (!$partyId || $partyId <= 0)) {
            $errors[] = 'Please select a target resident.';
        }

        if (!$errors) {
            try {
                $user = current_user();
                $recipients = coord_estimate_recipients(db(), $form['TargetType'], $unitId, $partyId);
                db()->prepare(
                    'INSERT INTO Notifications
                        (Title, Message, Channel, TargetType, TargetUnitID, TargetPartyID, SentByUserID, SentDate, RecipientCount, Status, Remarks)
                     VALUES
                        (:title, :message, :channel, :target_type, :unit_id, :party_id, :sent_by, NOW(), :recipients, :status, :remarks)'
                )->execute([
                    'title' => $form['Title'],
                    'message' => $form['Message'],
                    'channel' => $form['Channel'],
                    'target_type' => $form['TargetType'],
                    'unit_id' => $unitId,
                    'party_id' => $partyId,
                    'sent_by' => (int) $user['UserID'],
                    'recipients' => $recipients,
                    'status' => 'Sent',
                    'remarks' => $form['Remarks'] !== '' ? $form['Remarks'] : null,
                ]);
                flash_set('success', 'Notification recorded as sent to ' . $recipients . ' recipient(s).');
                redirect('modules/coordination/history.php');
            } catch (Throwable $e) {
                error_log('Notification send failed: ' . $e->getMessage());
                $errors[] = 'Could not send notification.';
            }
        }
    }
}

$recent = db()->query(
    "SELECT n.*, u.FullName AS SentByName
     FROM Notifications n
     INNER JOIN Users u ON u.UserID = n.SentByUserID
     ORDER BY n.SentDate DESC, n.NotificationID DESC
     LIMIT 8"
)->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Send Notifications</h1>
        <p class="text-muted mb-0">Dispatch notices by in-app, email, SMS, or notice board</p>
    </div>
    <a href="<?= e(base_url('modules/coordination/history.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-chat-square-text me-1"></i> History
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card panel-card">
            <div class="card-header">Compose Notification</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="send">
                    <div class="mb-3">
                        <label class="form-label" for="Title">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="Title" name="Title" maxlength="200" value="<?= e($form['Title']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Message">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="Message" name="Message" rows="4" required><?= e($form['Message']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Channel">Channel</label>
                        <select class="form-select" id="Channel" name="Channel">
                            <?php foreach (coord_notification_channels() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $form['Channel'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
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
                                <option value="<?= (int) $unit['UnitID'] ?>" <?= (int) $form['TargetUnitID'] === (int) $unit['UnitID'] ? 'selected' : '' ?>><?= e($unit['PropertyName'] . ' / Unit ' . $unit['UnitNumber']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="partyWrap">
                        <label class="form-label" for="TargetPartyID">Resident</label>
                        <select class="form-select" id="TargetPartyID" name="TargetPartyID">
                            <option value="">Select resident</option>
                            <?php foreach ($residents as $resident): ?>
                                <option value="<?= (int) $resident['PartyID'] ?>" <?= (int) $form['TargetPartyID'] === (int) $resident['PartyID'] ? 'selected' : '' ?>><?= e($resident['FullName'] . ' · Unit ' . $resident['UnitNumber']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Remarks">Remarks</label>
                        <input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="300" value="<?= e($form['Remarks']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i> Send Notification</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card panel-card">
            <div class="card-header">Recent Notifications</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Title</th>
                            <th>Channel</th>
                            <th>Audience</th>
                            <th>Recipients</th>
                            <th>Sent</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recent): ?>
                            <tr><td colspan="5" class="empty-state">No notifications sent yet.</td></tr>
                        <?php else: foreach ($recent as $row): ?>
                            <tr>
                                <td><strong><?= e($row['Title']) ?></strong></td>
                                <td><?= e($row['Channel']) ?></td>
                                <td><?= e($row['TargetType']) ?></td>
                                <td><?= e((string) $row['RecipientCount']) ?></td>
                                <td>
                                    <?= e(format_datetime($row['SentDate'])) ?>
                                    <div class="text-muted small"><?= e($row['SentByName']) ?></div>
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
