<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Delete Resident';
$currentNav = 'residents';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid resident selected.');
    redirect('modules/residents/index.php');
}

$stmt = db()->prepare('SELECT * FROM Parties WHERE PartyID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$party = $stmt->fetch();
if (!$party) {
    flash_set('error', 'Resident not found.');
    redirect('modules/residents/index.php');
}

$hasLinks = resident_has_party_links(db(), $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } elseif ($hasLinks) {
        $errors[] = 'This resident has ownership, occupancy or other linked records and cannot be deleted.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM PartyContacts WHERE PartyID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM Parties WHERE PartyID = :id')->execute(['id' => $id]);
            $pdo->commit();
            flash_set('success', 'Resident deleted successfully.');
            redirect('modules/residents/index.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            error_log('Party delete failed: ' . $e->getMessage());
            $errors[] = 'Could not delete resident.';
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Delete Resident</h1><p class="text-muted mb-0"><?= e($party['FullName']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($hasLinks): ?>
        <div class="alert alert-danger mb-0">This resident is linked to ownership/occupancy or other records and cannot be deleted.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <p>Are you sure you want to permanently delete this resident?</p>
            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i> Yes, Delete</button>
            <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
