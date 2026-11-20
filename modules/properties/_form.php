<?php

declare(strict_types=1);

/** @var array $property */
$property = $property ?? [
    'PropertyName' => '',
    'Address' => '',
    'City' => '',
    'ContactNumber' => '',
    'Status' => 'Active',
];
?>
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="PropertyName">Property Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="PropertyName" name="PropertyName" maxlength="150"
               value="<?= e($property['PropertyName'] ?? '') ?>" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="Status">Status</label>
        <select class="form-select" id="Status" name="Status" required>
            <?php foreach (['Active', 'Inactive'] as $status): ?>
                <option value="<?= e($status) ?>" <?= (($property['Status'] ?? '') === $status) ? 'selected' : '' ?>>
                    <?= e($status) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="Address">Address</label>
        <textarea class="form-control" id="Address" name="Address" rows="2" maxlength="300"><?= e($property['Address'] ?? '') ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="City">City</label>
        <input type="text" class="form-control" id="City" name="City" maxlength="100"
               value="<?= e($property['City'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="ContactNumber">Contact Number</label>
        <input type="text" class="form-control" id="ContactNumber" name="ContactNumber" maxlength="30"
               value="<?= e($property['ContactNumber'] ?? '') ?>">
    </div>
</div>
