<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Edit Visitor';
$currentNav = 'visitors-register';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid visitor selected.');
    redirect('modules/visitors/register.php');
}

$stmt = db()->prepare('SELECT * FROM Visitors WHERE VisitorID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$visitor = $stmt->fetch();
if (!$visitor) {
    flash_set('error', 'Visitor not found.');
    redirect('modules/visitors/register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $data = [
            'VisitorName' => trim((string) ($_POST['VisitorName'] ?? '')),
            'CNIC' => trim((string) ($_POST['CNIC'] ?? '')),
            'ContactNumber' => trim((string) ($_POST['ContactNumber'] ?? '')),
            'VehicleNumber' => trim((string) ($_POST['VehicleNumber'] ?? '')),
            'Remarks' => trim((string) ($_POST['Remarks'] ?? '')),
        ];
        if ($data['VisitorName'] === '') {
            $errors[] = 'Visitor name is required.';
        }
        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE Visitors
                     SET VisitorName = :name, CNIC = :cnic, ContactNumber = :contact,
                         VehicleNumber = :vehicle, Remarks = :remarks
                     WHERE VisitorID = :id'
                )->execute([
                    'name' => $data['VisitorName'],
                    'cnic' => $data['CNIC'] !== '' ? $data['CNIC'] : null,
                    'contact' => $data['ContactNumber'] !== '' ? $data['ContactNumber'] : null,
                    'vehicle' => $data['VehicleNumber'] !== '' ? $data['VehicleNumber'] : null,
                    'remarks' => $data['Remarks'] !== '' ? $data['Remarks'] : null,
                    'id' => $id,
                ]);
                flash_set('success', 'Visitor updated successfully.');
                redirect('modules/visitors/register.php');
            } catch (Throwable $e) {
                error_log('Visitor update failed: ' . $e->getMessage());
                $errors[] = 'Could not update visitor.';
            }
        }
        $visitor = array_merge($visitor, $data);
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>Edit Visitor</h1><p class="text-muted mb-0"><?= e($visitor['VisitorName']) ?></p></div>
    <a href="<?= e(base_url('modules/visitors/register.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body" style="max-width:640px;">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label" for="VisitorName">Full Name</label><input type="text" class="form-control" id="VisitorName" name="VisitorName" maxlength="150" value="<?= e($visitor['VisitorName']) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="CNIC">CNIC</label><input type="text" class="form-control" id="CNIC" name="CNIC" maxlength="25" value="<?= e($visitor['CNIC'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="ContactNumber">Contact Number</label><input type="text" class="form-control" id="ContactNumber" name="ContactNumber" maxlength="30" value="<?= e($visitor['ContactNumber'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="VehicleNumber">Vehicle Number</label><input type="text" class="form-control" id="VehicleNumber" name="VehicleNumber" maxlength="30" value="<?= e($visitor['VehicleNumber'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="Remarks">Remarks</label><input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="200" value="<?= e($visitor['Remarks'] ?? '') ?>"></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i> Update Visitor</button>
        <a href="<?= e(base_url('modules/visitors/register.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
