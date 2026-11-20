<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Edit Floor';
$currentNav = 'floors';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid floor selected.');
    redirect('modules/properties/floors.php');
}

$blocks = db()->query(
    "SELECT b.BlockID, b.BlockName, p.PropertyName
     FROM Blocks b
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     ORDER BY p.PropertyName, b.BlockName"
)->fetchAll();

$stmt = db()->prepare('SELECT * FROM Floors WHERE FloorID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$floor = $stmt->fetch();

if (!$floor) {
    flash_set('error', 'Floor not found.');
    redirect('modules/properties/floors.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $floor['BlockID'] = (int) ($_POST['BlockID'] ?? 0);
        $floor['FloorName'] = trim((string) ($_POST['FloorName'] ?? ''));
        $floor['FloorNumber'] = (int) ($_POST['FloorNumber'] ?? 0);
        $floor['Status'] = trim((string) ($_POST['Status'] ?? 'Active'));

        if ($floor['BlockID'] <= 0) {
            $errors[] = 'Please select a block.';
        }
        if ($floor['FloorName'] === '') {
            $errors[] = 'Floor name is required.';
        }
        if (!in_array($floor['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE Floors
                     SET BlockID = :block_id, FloorName = :name, FloorNumber = :number, Status = :status
                     WHERE FloorID = :id'
                )->execute([
                    'block_id' => $floor['BlockID'],
                    'name' => $floor['FloorName'],
                    'number' => $floor['FloorNumber'],
                    'status' => $floor['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Floor updated successfully.');
                redirect('modules/properties/floors.php');
            } catch (Throwable $e) {
                error_log('Floor update failed: ' . $e->getMessage());
                $errors[] = 'Could not update floor. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Floor</h1>
        <p class="text-muted mb-0"><?= e($floor['FloorName']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php require __DIR__ . '/_floor_form.php'; ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Floor</button>
                <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
