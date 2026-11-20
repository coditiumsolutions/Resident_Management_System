<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Add Resident';
$currentNav = 'residents';
$errors = [];
$party = [
    'FullName' => '', 'CNIC' => '', 'Email' => '', 'DateOfBirth' => '', 'Gender' => '',
    'PermanentAddress' => '', 'EmergencyContactName' => '', 'EmergencyContactNumber' => '',
    'Status' => 'Active', 'ContactNumber' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($party as $key => $_) {
            $party[$key] = trim((string) ($_POST[$key] ?? ($key === 'Status' ? 'Active' : '')));
        }
        $errors = array_merge($errors, resident_validate_party($party));

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $id = resident_save_party($pdo, $party);
                resident_save_contact($pdo, $id, $party['ContactNumber']);
                $pdo->commit();
                flash_set('success', 'Resident created successfully.');
                redirect('modules/residents/index.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Party create failed: ' . $e->getMessage());
                $errors[] = 'Could not create resident. CNIC may already exist.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Add Resident</h1><p class="text-muted mb-0">Create a new party profile</p></div>
    <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php require __DIR__ . '/_party_form.php'; ?>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Save Resident</button>
            <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
