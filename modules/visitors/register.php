<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Visitor Registration';
$currentNav = 'visitors-register';
$errors = [];

$form = [
    'VisitorName' => '',
    'CNIC' => '',
    'ContactNumber' => '',
    'VehicleNumber' => '',
    'Remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid session token. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        if ($form['VisitorName'] === '') {
            $errors[] = 'Visitor name is required.';
        }

        if (!$errors) {
            try {
                db()->prepare(
                    'INSERT INTO Visitors (VisitorName, CNIC, ContactNumber, VehicleNumber, Remarks)
                     VALUES (:name, :cnic, :contact, :vehicle, :remarks)'
                )->execute([
                    'name' => $form['VisitorName'],
                    'cnic' => $form['CNIC'] !== '' ? $form['CNIC'] : null,
                    'contact' => $form['ContactNumber'] !== '' ? $form['ContactNumber'] : null,
                    'vehicle' => $form['VehicleNumber'] !== '' ? $form['VehicleNumber'] : null,
                    'remarks' => $form['Remarks'] !== '' ? $form['Remarks'] : null,
                ]);
                flash_set('success', 'Visitor registered successfully.');
                redirect('modules/visitors/register.php');
            } catch (Throwable $e) {
                error_log('Visitor register failed: ' . $e->getMessage());
                $errors[] = 'Could not register visitor.';
            }
        }
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT v.*,
               (SELECT COUNT(*) FROM Visits vis WHERE vis.VisitorID = v.VisitorID) AS VisitCount,
               (SELECT COUNT(*) FROM Visits vis WHERE vis.VisitorID = v.VisitorID AND vis.VisitStatus = \'Checked In\') AS ActiveVisits
        FROM Visitors v WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (v.VisitorName LIKE :q OR v.CNIC LIKE :q2 OR v.ContactNumber LIKE :q3 OR v.VehicleNumber LIKE :q4)';
    $like = '%' . $search . '%';
    $params = ['q' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
}
$sql .= ' ORDER BY v.CreatedAt DESC, v.VisitorID DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$visitors = $stmt->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Visitor Registration</h1>
        <p class="text-muted mb-0">Register and manage visitor profiles</p>
    </div>
    <a href="<?= e(base_url('modules/visitors/entry.php')) ?>" class="btn btn-primary">
        <i class="bi bi-door-open me-1"></i> Entry / Exit
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card panel-card">
            <div class="card-header">Register Visitor</div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label" for="VisitorName">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="VisitorName" name="VisitorName" maxlength="150" value="<?= e($form['VisitorName']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="CNIC">CNIC</label>
                        <input type="text" class="form-control" id="CNIC" name="CNIC" maxlength="25" value="<?= e($form['CNIC']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ContactNumber">Contact Number</label>
                        <input type="text" class="form-control" id="ContactNumber" name="ContactNumber" maxlength="30" value="<?= e($form['ContactNumber']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="VehicleNumber">Vehicle Number</label>
                        <input type="text" class="form-control" id="VehicleNumber" name="VehicleNumber" maxlength="30" value="<?= e($form['VehicleNumber']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="Remarks">Remarks</label>
                        <input type="text" class="form-control" id="Remarks" name="Remarks" maxlength="200" value="<?= e($form['Remarks']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus me-1"></i> Save Visitor</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card panel-card mb-3">
            <div class="card-body py-3">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label" for="q">Search</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, CNIC, phone, vehicle">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-outline-primary w-100" type="submit">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card panel-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>CNIC</th>
                            <th>Contact</th>
                            <th>Vehicle</th>
                            <th>Visits</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$visitors): ?>
                            <tr><td colspan="6" class="empty-state">No visitors registered yet.</td></tr>
                        <?php else: foreach ($visitors as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['VisitorName']) ?></strong>
                                    <?php if ((int) $row['ActiveVisits'] > 0): ?>
                                        <span class="badge badge-soft resolved ms-1">Inside</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['CNIC'] ?: '—') ?></td>
                                <td><?= e($row['ContactNumber'] ?: '—') ?></td>
                                <td><?= e($row['VehicleNumber'] ?: '—') ?></td>
                                <td><?= e((string) $row['VisitCount']) ?></td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/visitors/visitor_edit.php?id=' . (int) $row['VisitorID'])) ?>"><i class="bi bi-pencil"></i></a>
                                    <a class="btn btn-sm btn-outline-success" href="<?= e(base_url('modules/visitors/entry.php?visitor_id=' . (int) $row['VisitorID'])) ?>" title="Check In"><i class="bi bi-box-arrow-in-right"></i></a>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(base_url('modules/visitors/visitor_delete.php?id=' . (int) $row['VisitorID'])) ?>"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
