<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Complaint Details';
$currentNav = 'complaints';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid complaint selected.');
    redirect('modules/complaints/index.php');
}

$users = complaint_users(db());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'update');
        $pdo = db();
        $user = current_user();
        $userId = (int) ($user['UserID'] ?? 0);

        try {
            if ($action === 'update_status') {
                $newStatus = trim((string) ($_POST['Status'] ?? ''));
                $remarks = trim((string) ($_POST['Remarks'] ?? ''));
                $assigneeId = (int) ($_POST['AssignedToUserID'] ?? 0);

                if (!in_array($newStatus, complaint_statuses(), true)) {
                    $errors[] = 'Invalid status.';
                }
                if ($userId <= 0) {
                    $errors[] = 'Could not identify current user.';
                }

                if (!$errors) {
                    $pdo->beginTransaction();
                    $pdo->prepare(
                        'UPDATE Complaints SET AssignedToUserID = :assigned WHERE ComplaintID = :id'
                    )->execute([
                        'assigned' => $assigneeId > 0 ? $assigneeId : null,
                        'id' => $id,
                    ]);
                    complaint_apply_status($pdo, $id, $newStatus, $userId, $remarks !== '' ? $remarks : null);
                    $pdo->commit();
                    flash_set('success', 'Complaint updated.');
                    redirect('modules/complaints/view.php?id=' . $id);
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Complaint update failed: ' . $e->getMessage());
            $errors[] = 'Could not update complaint.';
        }
    }
}

$stmt = db()->prepare(complaint_list_sql(' AND c.ComplaintID = :id') . ' LIMIT 1');
$stmt->execute(['id' => $id]);
$complaint = $stmt->fetch();
if (!$complaint) {
    flash_set('error', 'Complaint not found.');
    redirect('modules/complaints/index.php');
}

$updates = db()->prepare(
    "SELECT u.*, usr.FullName AS UpdatedByName
     FROM ComplaintUpdates u
     INNER JOIN Users usr ON usr.UserID = u.UpdatedByUserID
     WHERE u.ComplaintID = :id
     ORDER BY u.UpdateDate DESC, u.UpdateID DESC"
);
$updates->execute(['id' => $id]);
$history = $updates->fetchAll();

$pageTitle = 'Complaint ' . $complaint['ComplaintNo'];

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($complaint['ComplaintNo']) ?></h1>
        <p class="text-muted mb-0"><?= e($complaint['Subject']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/complaints/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> All Complaints</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card panel-card mb-3">
            <div class="card-header">Complaint Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="text-muted small">Resident</div><strong><?= e($complaint['PartyName']) ?></strong></div>
                    <div class="col-md-6"><div class="text-muted small">Unit</div><strong><?= e($complaint['UnitNumber']) ?></strong> <span class="text-muted"><?= e($complaint['PropertyName']) ?></span></div>
                    <div class="col-md-6"><div class="text-muted small">Category</div><strong><?= e($complaint['CategoryName']) ?></strong></div>
                    <div class="col-md-6"><div class="text-muted small">Created</div><strong><?= e(format_datetime($complaint['CreatedDate'])) ?></strong></div>
                    <div class="col-md-6"><div class="text-muted small">Priority</div><span class="badge badge-soft <?= e(status_badge_class($complaint['Priority'])) ?>"><?= e($complaint['Priority']) ?></span></div>
                    <div class="col-md-6"><div class="text-muted small">Status</div><span class="badge badge-soft <?= e(status_badge_class($complaint['Status'])) ?>"><?= e($complaint['Status']) ?></span></div>
                    <div class="col-12"><div class="text-muted small">Description</div><p class="mb-0"><?= nl2br(e($complaint['Description'] ?: '—')) ?></p></div>
                    <?php if (!empty($complaint['ResolvedDate'])): ?>
                        <div class="col-md-6"><div class="text-muted small">Resolved</div><strong><?= e(format_datetime($complaint['ResolvedDate'])) ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card panel-card">
            <div class="card-header">Status History</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>When</th>
                            <th>By</th>
                            <th>Change</th>
                            <th>Remarks</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$history): ?>
                            <tr><td colspan="4" class="empty-state">No updates yet.</td></tr>
                        <?php else: foreach ($history as $row): ?>
                            <tr>
                                <td><?= e(format_datetime($row['UpdateDate'])) ?></td>
                                <td><?= e($row['UpdatedByName']) ?></td>
                                <td>
                                    <?= e(($row['PreviousStatus'] ?: '—') . ' → ' . $row['NewStatus']) ?>
                                </td>
                                <td><?= e($row['Remarks'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card panel-card">
            <div class="card-header">Update Status</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-3">
                        <label class="form-label" for="Status">Status</label>
                        <select class="form-select" id="Status" name="Status" required>
                            <?php foreach (complaint_statuses() as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $complaint['Status'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="AssignedToUserID">Assigned To</label>
                        <select class="form-select" id="AssignedToUserID" name="AssignedToUserID">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $userRow): ?>
                                <option value="<?= (int) $userRow['UserID'] ?>" <?= (int) ($complaint['AssignedToUserID'] ?? 0) === (int) $userRow['UserID'] ? 'selected' : '' ?>>
                                    <?= e($userRow['FullName'] ?: $userRow['Username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Remarks">Remarks</label>
                        <textarea class="form-control" id="Remarks" name="Remarks" rows="3" placeholder="Optional update note"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i> Save Update</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
