<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'End Ownership';
$currentNav = 'residents-owners';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid ownership selected.');
    redirect('modules/residents/owners.php');
}

$stmt = db()->prepare(
    "SELECT o.*, p.FullName, u.UnitNumber
     FROM UnitOwnerships o
     INNER JOIN Parties p ON p.PartyID = o.OwnerPartyID
     INNER JOIN Units u ON u.UnitID = o.UnitID
     WHERE o.OwnershipID = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$ownership = $stmt->fetch();
if (!$ownership) {
    flash_set('error', 'Ownership not found.');
    redirect('modules/residents/owners.php');
}

$endDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $endDate = trim((string) ($_POST['EndDate'] ?? ''));
        if ($endDate === '') {
            $errors[] = 'End date is required.';
        } elseif ($endDate < $ownership['StartDate']) {
            $errors[] = 'End date cannot be before start date.';
        }

        if (!$errors) {
            db()->prepare('UPDATE UnitOwnerships SET EndDate = :end_date WHERE OwnershipID = :id')
                ->execute(['end_date' => $endDate, 'id' => $id]);
            flash_set('success', 'Ownership ended successfully.');
            redirect('modules/residents/ownership.php');
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>End Ownership</h1><p class="text-muted mb-0"><?= e($ownership['FullName']) ?> · Unit <?= e($ownership['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3" style="max-width:240px;">
            <label class="form-label" for="EndDate">End Date</label>
            <input type="date" class="form-control" id="EndDate" name="EndDate" value="<?= e($endDate) ?>" required>
        </div>
        <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle me-1"></i> End Ownership</button>
        <a href="<?= e(base_url('modules/residents/owners.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
