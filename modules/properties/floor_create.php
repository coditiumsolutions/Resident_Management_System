<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Add Floor';
$currentNav = 'floors';
$errors = [];

$blocks = db()->query(
    "SELECT b.BlockID, b.BlockName, p.PropertyName
     FROM Blocks b
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     WHERE b.Status = 'Active'
     ORDER BY p.PropertyName, b.BlockName"
)->fetchAll();

$floor = [
    'BlockID' => filter_input(INPUT_GET, 'block_id', FILTER_VALIDATE_INT) ?: '',
    'FloorName' => '',
    'FloorNumber' => '',
    'Status' => 'Active',
];

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
                    'INSERT INTO Floors (BlockID, FloorName, FloorNumber, Status)
                     VALUES (:block_id, :name, :number, :status)'
                )->execute([
                    'block_id' => $floor['BlockID'],
                    'name' => $floor['FloorName'],
                    'number' => $floor['FloorNumber'],
                    'status' => $floor['Status'],
                ]);
                flash_set('success', 'Floor created successfully.');
                redirect('modules/properties/floors.php');
            } catch (Throwable $e) {
                error_log('Floor create failed: ' . $e->getMessage());
                $errors[] = 'Could not create floor. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add Floor</h1>
        <p class="text-muted mb-0">Create a new floor under a block</p>
    </div>
    <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!$blocks): ?>
            <div class="alert alert-warning mb-0">Create a block first before adding floors.</div>
        <?php else: ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php require __DIR__ . '/_floor_form.php'; ?>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Floor</button>
                    <a href="<?= e(base_url('modules/properties/floors.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
