<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/functions.php';
require_login();

$moduleTitle = $moduleTitle ?? 'Module';
$moduleDescription = $moduleDescription ?? 'This module is under development and will be available in a later phase.';
$pageTitle = $moduleTitle;
$currentNav = $currentNav ?? 'dashboard';

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><?= e($moduleTitle) ?></h1>
        <p class="text-muted mb-0">Ready Plaza · Module placeholder</p>
    </div>
</div>

<div class="card panel-card">
    <div class="card-body text-center py-5">
        <div class="placeholder-icon mb-3"><i class="bi bi-tools"></i></div>
        <h4 class="mb-2"><?= e($moduleTitle) ?></h4>
        <p class="text-muted mb-4 mx-auto" style="max-width: 520px;"><?= e($moduleDescription) ?></p>
        <a href="<?= e(base_url('dashboard/')) ?>" class="btn btn-primary">
            <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
