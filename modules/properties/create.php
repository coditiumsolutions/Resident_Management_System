<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Add Property';
$currentNav = 'properties';
$errors = [];
$property = [
    'PropertyName' => '',
    'Address' => '',
    'City' => '',
    'ContactNumber' => '',
    'Status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $property['PropertyName'] = trim((string) ($_POST['PropertyName'] ?? ''));
        $property['Address'] = trim((string) ($_POST['Address'] ?? ''));
        $property['City'] = trim((string) ($_POST['City'] ?? ''));
        $property['ContactNumber'] = trim((string) ($_POST['ContactNumber'] ?? ''));
        $property['Status'] = trim((string) ($_POST['Status'] ?? 'Active'));

        if ($property['PropertyName'] === '') {
            $errors[] = 'Property name is required.';
        } elseif (mb_strlen($property['PropertyName']) > 150) {
            $errors[] = 'Property name must be 150 characters or less.';
        }

        if (!in_array($property['Status'], ['Active', 'Inactive'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (!$errors) {
            try {
                $stmt = db()->prepare(
                    'INSERT INTO Properties (PropertyName, Address, City, ContactNumber, Status)
                     VALUES (:name, :address, :city, :contact, :status)'
                );
                $stmt->execute([
                    'name' => $property['PropertyName'],
                    'address' => $property['Address'] !== '' ? $property['Address'] : null,
                    'city' => $property['City'] !== '' ? $property['City'] : null,
                    'contact' => $property['ContactNumber'] !== '' ? $property['ContactNumber'] : null,
                    'status' => $property['Status'],
                ]);

                flash_set('success', 'Property created successfully.');
                redirect('modules/properties/index.php');
            } catch (Throwable $e) {
                error_log('Property create failed: ' . $e->getMessage());
                $errors[] = 'Could not create property. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add Property</h1>
        <p class="text-muted mb-0">Create a new property record</p>
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

        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php require __DIR__ . '/_form.php'; ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Property</button>
                <a href="<?= e(base_url('modules/properties/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
