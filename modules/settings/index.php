<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Settings';
$currentNav = 'settings';

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Settings</h1>
        <p class="text-muted mb-0">System configuration and preferences</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-xl-4">
        <a href="<?= e(base_url('modules/settings/layout.php')) ?>" class="card panel-card h-100 text-decoration-none">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon blue"><i class="bi bi-palette"></i></div>
                    <div>
                        <h5 class="mb-1 text-dark">Layout Style</h5>
                        <p class="text-muted mb-0 small">Choose navbar and sidebar color combinations</p>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
