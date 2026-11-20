<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Edit Unit';
$currentNav = 'units';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid unit selected.');
    redirect('modules/properties/units.php');
}

$floors = db()->query(
    "SELECT f.FloorID, f.FloorName, b.BlockName, p.PropertyName
     FROM Floors f
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     ORDER BY p.PropertyName, b.BlockName, f.FloorNumber"
)->fetchAll();

$stmt = db()->prepare('SELECT * FROM Units WHERE UnitID = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$unit = $stmt->fetch();

if (!$unit) {
    flash_set('error', 'Unit not found.');
    redirect('modules/properties/units.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $unit['FloorID'] = (int) ($_POST['FloorID'] ?? 0);
        $unit['UnitNumber'] = trim((string) ($_POST['UnitNumber'] ?? ''));
        $unit['UnitType'] = trim((string) ($_POST['UnitType'] ?? ''));
        $unit['AreaSqFt'] = trim((string) ($_POST['AreaSqFt'] ?? ''));
        $unit['Status'] = trim((string) ($_POST['Status'] ?? 'Vacant'));
        $unit['Description'] = trim((string) ($_POST['Description'] ?? ''));

        if ($unit['FloorID'] <= 0) {
            $errors[] = 'Please select a floor.';
        }
        if ($unit['UnitNumber'] === '') {
            $errors[] = 'Unit number is required.';
        }
        if ($unit['UnitType'] === '') {
            $errors[] = 'Unit type is required.';
        }
        if (!in_array($unit['Status'], ['Vacant', 'Occupied', 'Under Maintenance', 'Reserved'], true)) {
            $errors[] = 'Invalid status selected.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'UPDATE Units
                     SET FloorID = :floor_id, UnitNumber = :number, UnitType = :type,
                         AreaSqFt = :area, Status = :status, Description = :description
                     WHERE UnitID = :id'
                )->execute([
                    'floor_id' => $unit['FloorID'],
                    'number' => $unit['UnitNumber'],
                    'type' => $unit['UnitType'],
                    'area' => $unit['AreaSqFt'] !== '' ? $unit['AreaSqFt'] : 0,
                    'status' => $unit['Status'],
                    'description' => $unit['Description'] !== '' ? $unit['Description'] : null,
                    'id' => $id,
                ]);
                flash_set('success', 'Unit updated successfully.');
                redirect('modules/properties/units.php');
            } catch (Throwable $e) {
                error_log('Unit update failed: ' . $e->getMessage());
                $errors[] = 'Could not update unit. Please try again.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Unit</h1>
        <p class="text-muted mb-0"><?= e($unit['UnitNumber']) ?></p>
    </div>
    <a href="<?= e(base_url('modules/properties/units.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>

<div class="card panel-card">
    <div class="card-body">
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php require __DIR__ . '/_unit_form.php'; ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Update Unit</button>
                <a href="<?= e(base_url('modules/properties/units.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
