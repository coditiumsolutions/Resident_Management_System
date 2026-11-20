<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'End Tenancy';
$currentNav = 'residents-tenants';
$errors = [];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    flash_set('error', 'Invalid tenancy selected.');
    redirect('modules/residents/tenants.php');
}

$stmt = db()->prepare(
    "SELECT oc.*, p.FullName, u.UnitNumber
     FROM UnitOccupancies oc
     INNER JOIN Parties p ON p.PartyID = oc.PartyID
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     WHERE oc.OccupancyID = :id AND oc.OccupancyType = 'Tenant'
     LIMIT 1"
);
$stmt->execute(['id' => $id]);
$occupancy = $stmt->fetch();
if (!$occupancy) {
    flash_set('error', 'Tenant occupancy not found.');
    redirect('modules/residents/tenants.php');
}

$endDate = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        $endDate = trim((string) ($_POST['EndDate'] ?? ''));
        if ($endDate === '') {
            $errors[] = 'End date is required.';
        }

        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $pdo->prepare(
                    "UPDATE UnitOccupancies SET EndDate = :end_date, Status = 'Ended' WHERE OccupancyID = :id"
                )->execute(['end_date' => $endDate, 'id' => $id]);
                $pdo->prepare(
                    "UPDATE RentalAgreements SET AgreementStatus = 'Expired', AgreementEndDate = COALESCE(AgreementEndDate, :end_date)
                     WHERE OccupancyID = :id AND AgreementStatus = 'Active'"
                )->execute(['end_date' => $endDate, 'id' => $id]);
                resident_sync_unit_status($pdo, (int) $occupancy['UnitID']);
                $pdo->commit();
                flash_set('success', 'Tenancy ended successfully.');
                redirect('modules/residents/tenants.php');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                error_log('Tenant end failed: ' . $e->getMessage());
                $errors[] = 'Could not end tenancy.';
            }
        }
    }
}

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div><h1>End Tenancy</h1><p class="text-muted mb-0"><?= e($occupancy['FullName']) ?> · Unit <?= e($occupancy['UnitNumber']) ?></p></div>
    <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
</div>
<div class="card panel-card"><div class="card-body">
    <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="mb-3" style="max-width:240px;">
            <label class="form-label" for="EndDate">End Date</label>
            <input type="date" class="form-control" id="EndDate" name="EndDate" value="<?= e($endDate) ?>" required>
        </div>
        <button class="btn btn-danger" type="submit"><i class="bi bi-x-circle me-1"></i> End Tenancy</button>
        <a href="<?= e(base_url('modules/residents/tenants.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div></div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
