<?php

declare(strict_types=1);

/** @var array $floor */
/** @var array $blocks */
$floor = $floor ?? [
    'BlockID' => '',
    'FloorName' => '',
    'FloorNumber' => '',
    'Status' => 'Active',
];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="BlockID">Block <span class="text-danger">*</span></label>
        <select class="form-select" id="BlockID" name="BlockID" required>
            <option value="">Select block</option>
            <?php foreach ($blocks as $item): ?>
                <option value="<?= (int) $item['BlockID'] ?>" <?= ((int) ($floor['BlockID'] ?? 0) === (int) $item['BlockID']) ? 'selected' : '' ?>>
                    <?= e($item['PropertyName'] . ' / ' . $item['BlockName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="Status">Status</label>
        <select class="form-select" id="Status" name="Status" required>
            <?php foreach (['Active', 'Inactive'] as $status): ?>
                <option value="<?= e($status) ?>" <?= (($floor['Status'] ?? '') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="FloorName">Floor Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="FloorName" name="FloorName" maxlength="50"
               value="<?= e($floor['FloorName'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="FloorNumber">Floor Number <span class="text-danger">*</span></label>
        <input type="number" class="form-control" id="FloorNumber" name="FloorNumber" min="-5" max="200"
               value="<?= e((string) ($floor['FloorNumber'] ?? '')) ?>" required>
    </div>
</div>
