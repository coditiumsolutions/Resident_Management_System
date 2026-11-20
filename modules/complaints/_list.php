<?php

declare(strict_types=1);

/**
 * Shared complaint list bootstrap.
 * Expected vars before include: $pageTitle, $currentNav, $pageHeading, $pageSubheading, $forcedWhere, $showCreate (bool)
 */

$errors = [];
$categories = complaint_categories(db(), true);
$units = complaint_unit_options(db());
$users = complaint_users(db());

$form = [
    'UnitKey' => '',
    'CategoryID' => '',
    'Subject' => '',
    'Description' => '',
    'Priority' => 'Normal',
    'AssignedToUserID' => '',
];

if (!empty($showCreate) && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
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
        $categoryId = (int) $form['CategoryID'];
        $assigneeId = (int) $form['AssignedToUserID'];

        if ($unitId <= 0 || $partyId <= 0) {
            $errors[] = 'Please select a resident unit.';
        }
        if ($categoryId <= 0) {
            $errors[] = 'Please select a category.';
        }
        if ($form['Subject'] === '') {
            $errors[] = 'Subject is required.';
        }
        if (!in_array($form['Priority'], complaint_priorities(), true)) {
            $errors[] = 'Invalid priority.';
        }

        if (!$errors) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $complaintNo = complaint_next_no($pdo);
                $status = $assigneeId > 0 ? 'Assigned' : 'New';
                $pdo->prepare(
                    'INSERT INTO Complaints
                        (ComplaintNo, PartyID, UnitID, CategoryID, Subject, Description, Priority, Status, AssignedToUserID, CreatedDate, ResolvedDate)
                     VALUES
                        (:no, :party_id, :unit_id, :category_id, :subject, :description, :priority, :status, :assigned_to, NOW(), NULL)'
                )->execute([
                    'no' => $complaintNo,
                    'party_id' => $partyId,
                    'unit_id' => $unitId,
                    'category_id' => $categoryId,
                    'subject' => $form['Subject'],
                    'description' => $form['Description'] !== '' ? $form['Description'] : null,
                    'priority' => $form['Priority'],
                    'status' => $status,
                    'assigned_to' => $assigneeId > 0 ? $assigneeId : null,
                ]);
                $complaintId = (int) $pdo->lastInsertId();
                $user = current_user();
                $actorId = (int) ($user['UserID'] ?? 0);
                if ($actorId <= 0) {
                    throw new RuntimeException('Missing authenticated user for complaint update trail.');
                }
                complaint_add_update(
                    $pdo,
                    $complaintId,
                    $actorId,
                    null,
                    $status,
                    'Complaint registered'
                );
                $pdo->commit();
                flash_set('success', 'Complaint ' . $complaintNo . ' created.');
                redirect('modules/complaints/view.php?id=' . $complaintId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Complaint create failed: ' . $e->getMessage());
                $errors[] = 'Could not create complaint.';
            }
        }
    }
}

$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;

$where = $forcedWhere ?? '';
$params = $forcedParams ?? [];

if ($status !== '' && empty($lockStatusFilter)) {
    $where .= ' AND c.Status = :status';
    $params['status'] = $status;
}
if ($priority !== '') {
    $where .= ' AND c.Priority = :priority';
    $params['priority'] = $priority;
}
if ($categoryFilter > 0) {
    $where .= ' AND c.CategoryID = :category_id';
    $params['category_id'] = $categoryFilter;
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
$complaints = $stmt->fetchAll();

$counts = [
    'total' => count($complaints),
    'new' => count(array_filter($complaints, static fn ($r) => $r['Status'] === 'New')),
    'pending' => count(array_filter($complaints, static fn ($r) => in_array($r['Status'], complaint_pending_statuses(), true))),
    'resolved' => count(array_filter($complaints, static fn ($r) => in_array($r['Status'], complaint_resolved_statuses(), true))),
];

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($pageHeading) ?></h1>
        <p class="text-muted mb-0"><?= e($pageSubheading) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/complaints/categories.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-folder2 me-1"></i> Categories</a>
        <?php if (empty($showCreate)): ?>
            <a href="<?= e(base_url('modules/complaints/new.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New Complaint</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Shown</span><strong><?= e((string) $counts['total']) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>New</span><strong><?= e((string) $counts['new']) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Pending</span><strong><?= e((string) $counts['pending']) ?></strong></div></div>
    <div class="col-6 col-md-3"><div class="stat-mini"><span>Resolved</span><strong><?= e((string) $counts['resolved']) ?></strong></div></div>
</div>

<?php if (!empty($showCreate)): ?>
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Register Complaint</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if (!$categories || !$units): ?>
                    <div class="alert alert-warning mb-0">
                        <?= !$categories ? 'Create a complaint category first.' : 'No occupied resident units found.' ?>
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
                                    <option value="<?= e($key) ?>" <?= $form['UnitKey'] === $key ? 'selected' : '' ?>><?= e(complaint_format_unit_label($unit)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="CategoryID">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="CategoryID" name="CategoryID" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= (int) $cat['ComplaintCategoryID'] ?>" <?= (int) $form['CategoryID'] === (int) $cat['ComplaintCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Subject">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="Subject" name="Subject" maxlength="200" value="<?= e($form['Subject']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Description">Description</label>
                            <textarea class="form-control" id="Description" name="Description" rows="3"><?= e($form['Description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="Priority">Priority</label>
                            <select class="form-select" id="Priority" name="Priority">
                                <?php foreach (complaint_priorities() as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $form['Priority'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="AssignedToUserID">Assign To</label>
                            <select class="form-select" id="AssignedToUserID" name="AssignedToUserID">
                                <option value="">Unassigned</option>
                                <?php foreach ($users as $userRow): ?>
                                    <option value="<?= (int) $userRow['UserID'] ?>" <?= (int) $form['AssignedToUserID'] === (int) $userRow['UserID'] ? 'selected' : '' ?>><?= e($userRow['FullName'] ?: $userRow['Username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i> Submit Complaint</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
<?php endif; ?>

<div class="card panel-card mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <?php if (empty($lockStatusFilter)): ?>
            <div class="col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <?php foreach (complaint_statuses() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label" for="priority">Priority</label>
                <select class="form-select" id="priority" name="priority">
                    <option value="">All</option>
                    <?php foreach (complaint_priorities() as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $priority === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All</option>
                    <?php foreach (complaint_categories(db()) as $cat): ?>
                        <option value="<?= (int) $cat['ComplaintCategoryID'] ?>" <?= $categoryFilter === (int) $cat['ComplaintCategoryID'] ? 'selected' : '' ?>><?= e($cat['CategoryName']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="q">Search</label>
                <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="No, subject, resident, unit">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/_table.php'; ?>

<?php if (!empty($showCreate)): ?>
    </div>
</div>
<?php endif; ?>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
