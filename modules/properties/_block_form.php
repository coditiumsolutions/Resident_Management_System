<?php

declare(strict_types=1);

/** @var array $block */
/** @var array $properties */
$block = $block ?? [
    'PropertyID' => '',
    'BlockName' => '',
    'Description' => '',
    'Status' => 'Active',
];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="PropertyID">Property <span class="text-danger">*</span></label>
        <select class="form-select" id="PropertyID" name="PropertyID" required>
            <option value="">Select property</option>
            <?php foreach ($properties as $property): ?>
                <option value="<?= (int) $property['PropertyID'] ?>" <?= ((int) ($block['PropertyID'] ?? 0) === (int) $property['PropertyID']) ? 'selected' : '' ?>>
                    <?= e($property['PropertyName']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="Status">Status</label>
        <select class="form-select" id="Status" name="Status" required>
            <?php foreach (['Active', 'Inactive'] as $status): ?>
                <option value="<?= e($status) ?>" <?= (($block['Status'] ?? '') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="BlockName">Block Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="BlockName" name="BlockName" maxlength="50"
               value="<?= e($block['BlockName'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="Description">Description</label>
        <input type="text" class="form-control" id="Description" name="Description" maxlength="200"
               value="<?= e($block['Description'] ?? '') ?>">
    </div>
</div>
