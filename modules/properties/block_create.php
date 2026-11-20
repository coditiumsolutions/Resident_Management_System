<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Add Block';
$currentNav = 'blocks';
$errors = [];

$properties = db()->query("SELECT PropertyID, PropertyName FROM Properties WHERE Status = 'Active' ORDER BY PropertyName")->fetchAll();
$block = [
    'PropertyID' => filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: '',
    'BlockName' => '',
    'Description' => '',
    'Status' => 'Active',
];

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
                    'INSERT INTO Blocks (PropertyID, BlockName, Description, Status)
                     VALUES (:property_id, :name, :description, :status)'
                )->execute([
                    'property_id' => $block['PropertyID'],
                    'name' => $block['BlockName'],
                    'description' => $block['Description'] !== '' ? $block['Description'] : null,
                    'status' => $block['Status'],
                ]);
                flash_set('success', 'Block created successfully.');
                redirect('modules/properties/blocks.php');
            } catch (Throwable $e) {
                error_log('Block create failed: ' . $e->getMessage());
                $errors[] = 'Could not create block. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add Block</h1>
        <p class="text-muted mb-0">Create a new block under a property</p>
    </div>
    <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!$properties): ?>
            <div class="alert alert-warning mb-0">Create a property first before adding blocks.</div>
        <?php else: ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php require __DIR__ . '/_block_form.php'; ?>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Block</button>
                    <a href="<?= e(base_url('modules/properties/blocks.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
