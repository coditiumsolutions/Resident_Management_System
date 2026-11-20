<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Resident Details';
$currentNav = 'residents';

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

$contacts = db()->prepare('SELECT * FROM PartyContacts WHERE PartyID = :id ORDER BY IsPrimary DESC, ContactID');
$contacts->execute(['id' => $id]);
$contactRows = $contacts->fetchAll();

$ownerships = db()->prepare(
    "SELECT o.*, u.UnitNumber, b.BlockName, p.PropertyName
     FROM UnitOwnerships o
     INNER JOIN Units u ON u.UnitID = o.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     WHERE o.OwnerPartyID = :id
     ORDER BY o.StartDate DESC"
);
$ownerships->execute(['id' => $id]);
$ownershipRows = $ownerships->fetchAll();

$occupancies = db()->prepare(
    "SELECT oc.*, u.UnitNumber, b.BlockName, p.PropertyName
     FROM UnitOccupancies oc
     INNER JOIN Units u ON u.UnitID = oc.UnitID
     INNER JOIN Floors f ON f.FloorID = u.FloorID
     INNER JOIN Blocks b ON b.BlockID = f.BlockID
     INNER JOIN Properties p ON p.PropertyID = b.PropertyID
     WHERE oc.PartyID = :id
     ORDER BY oc.StartDate DESC"
);
$occupancies->execute(['id' => $id]);
$occupancyRows = $occupancies->fetchAll();

require ROOT_PATH . '/includes/header.php';
?>
<div class="page-header">
    <div>
        <h1><?= e($party['FullName']) ?></h1>
        <p class="text-muted mb-0">Resident profile and linked units</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('modules/residents/party_edit.php?id=' . $id)) ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
        <a href="<?= e(base_url('modules/residents/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5">
        <div class="card panel-card h-100">
            <div class="card-header">Profile</div>
            <div class="card-body">
                <dl class="row mb-0 detail-list">
                    <dt class="col-sm-4">CNIC</dt><dd class="col-sm-8"><?= e($party['CNIC'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8"><?= e($party['Email'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Gender</dt><dd class="col-sm-8"><?= e($party['Gender'] ?: '—') ?></dd>
                    <dt class="col-sm-4">DOB</dt><dd class="col-sm-8"><?= e(format_date($party['DateOfBirth'])) ?></dd>
                    <dt class="col-sm-4">Address</dt><dd class="col-sm-8"><?= e($party['PermanentAddress'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8"><span class="badge badge-soft <?= $party['Status'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($party['Status']) ?></span></dd>
                </dl>
                <?php if ($contactRows): ?>
                    <hr>
                    <h6>Contacts</h6>
                    <ul class="mb-0">
                        <?php foreach ($contactRows as $c): ?>
                            <li><?= e($c['ContactNumber']) ?> <span class="text-muted">(<?= e($c['ContactType'] ?: 'Mobile') ?><?= (int) $c['IsPrimary'] ? ', Primary' : '' ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card panel-card mb-3">
            <div class="card-header">Ownership</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Unit</th><th>Share</th><th>Start</th><th>End</th></tr></thead>
                        <tbody>
                        <?php if (!$ownershipRows): ?><tr><td colspan="4" class="empty-state">No ownership records.</td></tr>
                        <?php else: foreach ($ownershipRows as $row): ?>
                            <tr>
                                <td><?= e($row['PropertyName'] . ' / ' . $row['UnitNumber']) ?></td>
                                <td><?= e(number_format((float) $row['OwnershipShare'], 2)) ?>%</td>
                                <td><?= e(format_date($row['StartDate'])) ?></td>
                                <td><?= e($row['EndDate'] ? format_date($row['EndDate']) : 'Current') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card panel-card">
            <div class="card-header">Occupancy</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Unit</th><th>Type</th><th>Start</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$occupancyRows): ?><tr><td colspan="4" class="empty-state">No occupancy records.</td></tr>
                        <?php else: foreach ($occupancyRows as $row): ?>
                            <tr>
                                <td><?= e($row['PropertyName'] . ' / ' . $row['UnitNumber']) ?></td>
                                <td><?= e($row['OccupancyType']) ?></td>
                                <td><?= e(format_date($row['StartDate'])) ?></td>
                                <td><span class="badge badge-soft <?= $row['Status'] === 'Active' ? 'resolved' : 'hold' ?>"><?= e($row['Status']) ?></span></td>
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
