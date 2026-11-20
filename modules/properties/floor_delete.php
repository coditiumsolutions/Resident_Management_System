<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Floor';
$currentNav = 'floors';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid floor selected.');
    redirect('modules/properties/floors.php');
}

$stmt = db()->prepare(
    "SELECT f.*, b.BlockName, p.PropertyName, COUNT(u.UnitID) AS UnitCount
     FROM Floors f
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     WHERE f.FloorID = :id
     GROUP BY f.FloorID"
);
$stmt->execute(['id' => $id]);
$floor = $stmt->fetch();

if (!$floor) {
    flash_set('error', 'Floor not found.');
    redirect('modules/properties/floors.php');
}

$linked = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM Complaints WHERE UnitID IN (SELECT UnitID FROM Units WHERE FloorID = :id1)) AS c1,
        (SELECT COUNT(*) FROM Invoices WHERE UnitID IN (SELECT UnitID FROM Units WHERE FloorID = :id2)) AS c2,
        (SELECT COUNT(*) FROM UnitOccupancies WHERE UnitID IN (SELECT UnitID FROM Units WHERE FloorID = :id3)) AS c3,
        (SELECT COUNT(*) FROM UnitOwnerships WHERE UnitID IN (SELECT UnitID FROM Units WHERE FloorID = :id4)) AS c4,
        (SELECT COUNT(*) FROM Visits WHERE UnitID IN (SELECT UnitID FROM Units WHERE FloorID = :id5)) AS c5"
);
$linked->execute(['id1' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id, 'id5' => $id]);
$hasOps = array_sum(array_map('intval', $linked->fetch() ?: [])) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($hasOps) {
        $errors[] = 'This floor has related operational records and cannot be deleted.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM Units WHERE FloorID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Floors WHERE FloorID = :id')->execute(['id' => $id]);
            $pdo->commit();
            flash_set('success', 'Floor deleted successfully.');
            redirect('modules/properties/floors.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Floor delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete floor. Please try again.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Delete Floor</h1>
        <p class="text-muted mb-0"><?= e($floor['FloorName']) ?> · <?= e($floor['BlockName']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <div class="alert alert-warning">Deleting this floor will also remove <?= e((string) $floor['UnitCount']) ?> unit(s).</div>
        <?php if ($hasOps): ?>
            <div class="alert alert-danger mb-0">This floor is linked to operational records and cannot be deleted.</div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Yes, Delete Floor</button>
                <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
