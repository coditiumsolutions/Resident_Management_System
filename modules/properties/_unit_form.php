<?php

declare(strict_types=1);

/** @var array $unit */
/** @var array $floors */
$unit = $unit ?? [
    'FloorID' => '',
    'UnitNumber' => '',
    'UnitType' => 'Apartment',
    'AreaSqFt' => '',
    'Status' => 'Vacant',
    'Description' => '',
];
$unitTypes = ['Apartment', 'Shop', 'Office', 'Warehouse', 'Other'];
$unitStatuses = ['Vacant', 'Occupied', 'Under Maintenance', 'Reserved'];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="FloorID">Floor <span class="text-danger">*</span></label>
        <select class="form-select" id="FloorID" name="FloorID" required>
            <option value="">Select floor</option>
            <?php foreach ($floors as $item): ?>
                <option value="<?= (int) $item['FloorID'] ?>" <?= ((int) ($unit['FloorID'] ?? 0) === (int) $item['FloorID']) ? 'selected' : '' ?>>
                    <?= e($item['PropertyName'] . ' / ' . $item['BlockName'] . ' / ' . $item['FloorName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="UnitNumber">Unit Number <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="UnitNumber" name="UnitNumber" maxlength="50"
               value="<?= e($unit['UnitNumber'] ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="Status">Status</label>
        <select class="form-select" id="Status" name="Status" required>
            <?php foreach ($unitStatuses as $status): ?>
                <option value="<?= e($status) ?>" <?= (($unit['Status'] ?? '') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="UnitType">Unit Type <span class="text-danger">*</span></label>
        <select class="form-select" id="UnitType" name="UnitType" required>
            <?php foreach ($unitTypes as $type): ?>
                <option value="<?= e($type) ?>" <?= (($unit['UnitType'] ?? '') === $type) ? 'selected' : '' ?>><?= e($type) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="AreaSqFt">Area (sq ft)</label>
        <input type="number" class="form-control" id="AreaSqFt" name="AreaSqFt" min="0" step="0.01"
               value="<?= e((string) ($unit['AreaSqFt'] ?? '')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="Description">Description</label>
        <input type="text" class="form-control" id="Description" name="Description" maxlength="250"
               value="<?= e($unit['Description'] ?? '') ?>">
    </div>
</div>
