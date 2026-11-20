<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Delete Property';
$currentNav = 'properties';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid property selected.');
    redirect('modules/properties/index.php');
}

$stmt = db()->prepare(
    "SELECT p.*,
            COUNT(DISTINCT b.BlockID) AS BlockCount,
            COUNT(DISTINCT f.FloorID) AS FloorCount,
            COUNT(DISTINCT u.UnitID) AS UnitCount
     FROM Properties p
     LEFT JOIN Blocks b ON b.PropertyID = p.PropertyID
     LEFT JOIN Floors f ON f.BlockID = b.BlockID
     LEFT JOIN Units u ON u.FloorID = f.FloorID
     WHERE p.PropertyID = :id
     GROUP BY p.PropertyID"
);
$stmt->execute(['id' => $id]);
$property = $stmt->fetch();

if (!$property) {
    flash_set('error', 'Property not found.');
    redirect('modules/properties/index.php');
}

// Block delete if units are referenced by operational data
$linkedCheck = db()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM Complaints c
            INNER JOIN Units u ON u.UnitID = c.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            WHERE b.PropertyID = :id1) AS ComplaintsCount,
        (SELECT COUNT(*) FROM Invoices i
            INNER JOIN Units u ON u.UnitID = i.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            WHERE b.PropertyID = :id2) AS InvoicesCount,
        (SELECT COUNT(*) FROM UnitOccupancies o
            INNER JOIN Units u ON u.UnitID = o.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            WHERE b.PropertyID = :id3) AS OccupanciesCount,
        (SELECT COUNT(*) FROM UnitOwnerships ow
            INNER JOIN Units u ON u.UnitID = ow.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            WHERE b.PropertyID = :id4) AS OwnershipsCount,
        (SELECT COUNT(*) FROM Visits v
            INNER JOIN Units u ON u.UnitID = v.UnitID
            INNER JOIN Floors f ON f.FloorID = u.FloorID
            INNER JOIN Blocks b ON b.BlockID = f.BlockID
            WHERE b.PropertyID = :id5) AS VisitsCount"
);
$linkedCheck->execute([
    'id1' => $id,
    'id2' => $id,
    'id3' => $id,
    'id4' => $id,
    'id5' => $id,
]);
$links = $linkedCheck->fetch() ?: [];
$hasOperationalData = ((int) ($links['ComplaintsCount'] ?? 0)
    + (int) ($links['InvoicesCount'] ?? 0)
    + (int) ($links['OccupanciesCount'] ?? 0)
    + (int) ($links['OwnershipsCount'] ?? 0)
    + (int) ($links['VisitsCount'] ?? 0)) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($hasOperationalData) {
        $errors[] = 'This property has related operational records and cannot be deleted.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $pdo->prepare(
                "DELETE u FROM Units u
                 INNER JOIN Floors f ON f.FloorID = u.FloorID
                 INNER JOIN Blocks b ON b.BlockID = f.BlockID
                 WHERE b.PropertyID = :id"
            )->execute(['id' => $id]);

            $pdo->prepare(
                "DELETE f FROM Floors f
                 INNER JOIN Blocks b ON b.BlockID = f.BlockID
                 WHERE b.PropertyID = :id"
            )->execute(['id' => $id]);

            $pdo->prepare('DELETE FROM Blocks WHERE PropertyID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Properties WHERE PropertyID = :id')->execute(['id' => $id]);

            $pdo->commit();
            flash_set('success', 'Property deleted successfully.');
            redirect('modules/properties/index.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Property delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete property. Please try again.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Delete Property</h1>
        <p class="text-muted mb-0">Confirm permanent deletion</p>
    </div>
    <a href="<?= e(base_url('modules/properties/index.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="alert alert-warning">
            <strong>Warning:</strong> Deleting <strong><?= e($property['PropertyName']) ?></strong> will also remove
            <?= e((string) $property['BlockCount']) ?> block(s),
            <?= e((string) $property['FloorCount']) ?> floor(s), and
            <?= e((string) $property['UnitCount']) ?> unit(s).
        </div>

        <?php if ($hasOperationalData): ?>
            <div class="alert alert-danger mb-0">
                This property is linked to operational records (occupancy, ownership, invoices, complaints, or visits) and cannot be deleted.
            </div>
            <a href="<?= e(base_url('modules/properties/index.php')) ?>" class="btn btn-outline-secondary mt-3">Back to Properties</a>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <p class="mb-4">Are you sure you want to permanently delete this property?</p>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i> Yes, Delete Property</button>
                <a href="<?= e(base_url('modules/properties/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
