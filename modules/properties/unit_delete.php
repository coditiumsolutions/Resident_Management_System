<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Unit';
$currentNav = 'units';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid unit selected.');
    redirect('modules/properties/units.php');
}

$stmt = db()->prepare(
    "SELECT u.*, f.FloorName, b.BlockName, p.PropertyName
     FROM Units u
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     WHERE u.UnitID = :id
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$unit = $stmt->fetch();

if (!$unit) {
    flash_set('error', 'Unit not found.');
    redirect('modules/properties/units.php');
}

$linked = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM Complaints WHERE UnitID = :id1) AS c1,
        (SELECT COUNT(*) FROM Invoices WHERE UnitID = :id2) AS c2,
        (SELECT COUNT(*) FROM UnitOccupancies WHERE UnitID = :id3) AS c3,
        (SELECT COUNT(*) FROM UnitOwnerships WHERE UnitID = :id4) AS c4,
        (SELECT COUNT(*) FROM Visits WHERE UnitID = :id5) AS c5,
        (SELECT COUNT(*) FROM UnitServices WHERE UnitID = :id6) AS c6,
        (SELECT COUNT(*) FROM Announcements WHERE TargetUnitID = :id7) AS c7"
);
$linked->execute([
    'id1' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id,
    'id5' => $id, 'id6' => $id, 'id7' => $id,
]);
$hasOps = array_sum(array_map('intval', $linked->fetch() ?: [])) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($hasOps) {
        $errors[] = 'This unit has related operational records and cannot be deleted.';
    } else {
        try {
            db()->prepare('DELETE FROM Units WHERE UnitID = :id')->execute(['id' => $id]);
            flash_set('success', 'Unit deleted successfully.');
            redirect('modules/properties/units.php');
        } catch (Throwable $e) {
            error_log('Unit delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete unit. Please try again.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Delete Unit</h1>
        <p class="text-muted mb-0"><?= e($unit['UnitNumber']) ?> · <?= e($unit['PropertyName']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/units.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="alert alert-warning">
            You are about to permanently delete unit <strong><?= e($unit['UnitNumber']) ?></strong>
            (<?= e($unit['FloorName']) ?> / <?= e($unit['BlockName']) ?>).
        </div>

        <?php if ($hasOps): ?>
            <div class="alert alert-danger mb-0">This unit is linked to operational records and cannot be deleted.</div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Yes, Delete Unit</button>
                <a href="<?= e(base_url('modules/properties/units.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
