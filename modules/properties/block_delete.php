<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Block';
$currentNav = 'blocks';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid block selected.');
    redirect('modules/properties/blocks.php');
}

$stmt = db()->prepare(
    "SELECT b.*, p.PropertyName,
            COUNT(DISTINCT f.FloorID) AS FloorCount,
            COUNT(DISTINCT u.UnitID) AS UnitCount
     FROM Blocks b
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     LEFT JOIN Floors f ON f.BlockID = b.BlockID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     WHERE b.BlockID = :id
     GROUP BY b.BlockID"
);
$stmt->execute(['id' => $id]);
$block = $stmt->fetch();

if (!$block) {
    flash_set('error', 'Block not found.');
    redirect('modules/properties/blocks.php');
}

$linked = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM Complaints c
            INNER JOIN Units u ON u.UnitID = c.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            WHERE f.BlockID = :id1) AS c1,
        (SELECT COUNT(*) FROM Invoices i
            INNER JOIN Units u ON u.UnitID = i.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            WHERE f.BlockID = :id2) AS c2,
        (SELECT COUNT(*) FROM UnitOccupancies o
            INNER JOIN Units u ON u.UnitID = o.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            WHERE f.BlockID = :id3) AS c3,
        (SELECT COUNT(*) FROM UnitOwnerships ow
            INNER JOIN Units u ON u.UnitID = ow.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            WHERE f.BlockID = :id4) AS c4,
        (SELECT COUNT(*) FROM Visits v
            INNER JOIN Units u ON u.UnitID = v.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            WHERE f.BlockID = :id5) AS c5"
);
$linked->execute(['id1' => $id, 'id2' => $id, 'id3' => $id, 'id4' => $id, 'id5' => $id]);
$counts = $linked->fetch() ?: [];
$hasOps = array_sum(array_map('intval', $counts)) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($hasOps) {
        $errors[] = 'This block has related operational records and cannot be deleted.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "DELETE u FROM Units u
                 INNER JOIN Floors f ON f.FloorID = u.FloorID
                 WHERE f.BlockID = :id"
            )->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Floors WHERE BlockID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Blocks WHERE BlockID = :id')->execute(['id' => $id]);
            $pdo->commit();
            flash_set('success', 'Block deleted successfully.');
            redirect('modules/properties/blocks.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Block delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete block. Please try again.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Delete Block</h1>
        <p class="text-muted mb-0"><?= e($block['BlockName']) ?> · <?= e($block['PropertyName']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="alert alert-warning">
            Deleting this block will also remove <?= e((string) $block['FloorCount']) ?> floor(s) and <?= e((string) $block['UnitCount']) ?> unit(s).
        </div>

        <?php if ($hasOps): ?>
            <div class="alert alert-danger mb-0">This block is linked to operational records and cannot be deleted.</div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Yes, Delete Block</button>
                <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
