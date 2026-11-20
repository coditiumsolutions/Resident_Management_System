<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Resident Service';
$currentNav = 'services-resident';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid assignment selected.');
    redirect('modules/services/resident.php');
}

$stmt = db()->prepare(
    "SELECT us.*, s.ServiceName, u.UnitNumber, party.FullName AS PartyName
     FROM UnitServices us
     INNER JOIN ServiceCatalog s ON s.ServiceID = us.ServiceID
     INNER JOIN Units u ON u.UnitID = us.UnitID
     LEFT JOIN Parties party ON party.PartyID = us.PartyID
     WHERE us.UnitServiceID = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if (!$row) {
    flash_set('error', 'Assignment not found.');
    redirect('modules/services/resident.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        try {
            db()->prepare('DELETE FROM UnitServices WHERE UnitServiceID = :id')->execute(['id' => $id]);
            flash_set('success', 'Resident service removed.');
            redirect('modules/services/resident.php');
        } catch (Throwable $e) {
            error_log('Resident service delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete assignment.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1>Delete Resident Service</h1>
        <p class="text-muted mb-0"><?= e($row['ServiceName']) ?> · Unit <?= e($row['UnitNumber']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/services/resident.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <p>Remove this service assignment for <?= e($row['PartyName'] ?: 'resident') ?>?</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
        <a href="<?= e(base_url('modules/services/resident.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
