<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Edit Block';
$currentNav = 'blocks';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid block selected.');
    redirect('modules/properties/blocks.php');
}

$properties = db()->query('SELECT PropertyID, PropertyName FROM Properties ORDER BY PropertyName')->fetchAll();
$stmt = db()->prepare('SELECT * FROM Blocks WHERE BlockID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$block = $stmt->fetch();

if (!$block) {
    flash_set('error', 'Block not found.');
    redirect('modules/properties/blocks.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $block['PropertyID'] = (int) ($_POST['PropertyID'] ?? 0);
        $block['BlockName'] = trim((string) ($_POST['BlockName'] ?? ''));
        $block['Description'] = trim((string) ($_POST['Description'] ?? ''));
        $block['Status'] = trim((string) ($_POST['Status'] ?? 'Active'));

        if ($block['PropertyID'] <= 0) {
            $errors[] = 'Please select a property.';
        }
        if ($block['BlockName'] === '') {
            $errors[] = 'Block name is required.';
        }
        if (!in_array($block['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE Blocks
                     SET PropertyID = :property_id, BlockName = :name, Description = :description, Status = :status
                     WHERE BlockID = :id'
                )->execute([
                    'property_id' => $block['PropertyID'],
                    'name' => $block['BlockName'],
                    'description' => $block['Description'] !== '' ? $block['Description'] : null,
                    'status' => $block['Status'],
                    'id' => $id,
                ]);
                flash_set('success', 'Block updated successfully.');
                redirect('modules/properties/blocks.php');
            } catch (Throwable $e) {
                error_log('Block update failed: ' . $e->getMessage());
                $errors[] = 'Could not update block. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Block</h1>
        <p class="text-muted mb-0"><?= e($block['BlockName']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php require __DIR__ . '/_block_form.php'; ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Block</button>
                <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
