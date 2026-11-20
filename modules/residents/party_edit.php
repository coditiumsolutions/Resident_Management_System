<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Edit Resident';
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

$contact = db()->prepare('SELECT ContactNumber FROM PartyContacts WHERE PartyID = :id AND IsPrimary = 1 LIMIT 1');
$contact->execute(['id' => $id]);
$party['ContactNumber'] = (string) ($contact->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach (['FullName','CNIC','Email','DateOfBirth','Gender','PermanentAddress','EmergencyContactName','EmergencyContactNumber','Status','ContactNumber'] as $key) {
            $party[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        $errors = array_merge($errors, resident_validate_party($party));
        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                resident_save_party($pdo, $party, $id);
                resident_save_contact($pdo, $id, $party['ContactNumber']);
                $pdo->commit();
                flash_set('success', 'Resident updated successfully.');
                redirect('modules/residents/index.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Party update failed: ' . $e->getMessage());
                $errors[] = 'Could not update resident. CNIC may already exist.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Resident</h1><p class="text-muted mb-0"><?= e($party['FullName']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <?php require __DIR__ . '/_party_form.php'; ?>
        <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update Resident</button>
            <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
