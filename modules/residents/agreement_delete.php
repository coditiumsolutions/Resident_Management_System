<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Rental Agreement';
$currentNav = 'residents-agreements';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid agreement selected.');
    redirect('modules/residents/agreements.php');
}

$stmt = db()->prepare(
    "SELECT ra.*, p.FullName, u.UnitNumber
     FROM RentalAgreements ra
     INNER JOIN UnitOccupancies oc ON oc.OccupancyID = ra.OccupancyID
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     WHERE ra.AgreementID = :id LIMIT 1"
);
$stmt->execute(['id' => $id]);
$agreement = $stmt->fetch();
if (!$agreement) {
    flash_set('error', 'Agreement not found.');
    redirect('modules/residents/agreements.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        try {
            db()->prepare('DELETE FROM RentalAgreements WHERE AgreementID = :id')->execute(['id' => $id]);
            flash_set('success', 'Agreement deleted successfully.');
            redirect('modules/residents/agreements.php');
        } catch (Throwable $e) {
            error_log('Agreement delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete agreement.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Rental Agreement</h1><p class="text-muted mb-0"><?= e($agreement['FullName']) ?> · Unit <?= e($agreement['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <div class="alert alert-warning">Delete agreement for rent <?= e(format_money($agreement['MonthlyRent'])) ?>?</div>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
        <a href="<?= e(base_url('modules/residents/agreements.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
