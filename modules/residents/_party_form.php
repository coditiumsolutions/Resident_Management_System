<?php

declare(strict_types=1);

/** @var array $party */
$party = $party ?? [
    'FullName' => '',
    'CNIC' => '',
    'Email' => '',
    'DateOfBirth' => '',
    'Gender' => '',
    'PermanentAddress' => '',
    'EmergencyContactName' => '',
    'EmergencyContactNumber' => '',
    'Status' => 'Active',
    'ContactNumber' => '',
];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="FullName">Full Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="FullName" name="FullName" maxlength="150"
               value="<?= e($party['FullName'] ?? '') ?>" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="CNIC">CNIC</label>
        <input type="text" class="form-control" id="CNIC" name="CNIC" maxlength="25"
               value="<?= e($party['CNIC'] ?? '') ?>" placeholder="35202-1234567-1">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="Status">Status</label>
        <select class="form-select" id="Status" name="Status" required>
            <?php foreach (['Active', 'Inactive'] as $status): ?>
                <option value="<?= e($status) ?>" <?= (($party['Status'] ?? '') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="Email">Email</label>
        <input type="email" class="form-control" id="Email" name="Email" maxlength="150"
               value="<?= e($party['Email'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="ContactNumber">Mobile</label>
        <input type="text" class="form-control" id="ContactNumber" name="ContactNumber" maxlength="30"
               value="<?= e($party['ContactNumber'] ?? '') ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label" for="Gender">Gender</label>
        <select class="form-select" id="Gender" name="Gender">
            <option value="">—</option>
            <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                <option value="<?= e($gender) ?>" <?= (($party['Gender'] ?? '') === $gender) ? 'selected' : '' ?>><?= e($gender) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label" for="DateOfBirth">Date of Birth</label>
        <input type="date" class="form-control" id="DateOfBirth" name="DateOfBirth"
               value="<?= e($party['DateOfBirth'] ?? '') ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="PermanentAddress">Permanent Address</label>
        <textarea class="form-control" id="PermanentAddress" name="PermanentAddress" rows="2" maxlength="300"><?= e($party['PermanentAddress'] ?? '') ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="EmergencyContactName">Emergency Contact Name</label>
        <input type="text" class="form-control" id="EmergencyContactName" name="EmergencyContactName" maxlength="150"
               value="<?= e($party['EmergencyContactName'] ?? '') ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="EmergencyContactNumber">Emergency Contact Number</label>
        <input type="text" class="form-control" id="EmergencyContactNumber" name="EmergencyContactNumber" maxlength="30"
               value="<?= e($party['EmergencyContactNumber'] ?? '') ?>">
    </div>
</div>
