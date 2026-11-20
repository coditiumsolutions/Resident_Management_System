<?php
/** @var array $complaints */
?>
<div class="card panel-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>Complaint #</th>
                    <th>Subject</th>
                    <th>Resident / Unit</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$complaints): ?>
                    <tr><td colspan="8" class="empty-state">No complaints found.</td></tr>
                <?php else: foreach ($complaints as $row): ?>
                    <tr>
                        <td><strong><?= e($row['ComplaintNo']) ?></strong></td>
                        <td>
                            <?= e($row['Subject']) ?>
                            <?php if (!empty($row['AssignedToName'])): ?>
                                <div class="text-muted small">Assigned: <?= e($row['AssignedToName']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($row['PartyName']) ?></strong>
                            <div class="text-muted small">Unit <?= e($row['UnitNumber']) ?></div>
                        </td>
                        <td><?= e($row['CategoryName']) ?></td>
                        <td><span class="badge badge-soft <?= e(status_badge_class($row['Priority'])) ?>"><?= e($row['Priority']) ?></span></td>
                        <td><span class="badge badge-soft <?= e(status_badge_class($row['Status'])) ?>"><?= e($row['Status']) ?></span></td>
                        <td><?= e(format_datetime($row['CreatedDate'])) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('modules/complaints/view.php?id=' . (int) $row['ComplaintID'])) ?>" title="View / Update"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
