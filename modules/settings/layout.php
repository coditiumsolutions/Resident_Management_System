<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_login();

$pageTitle = 'Layout Style';
$currentNav = 'layout-style';
$themes = layout_themes();
$selectedId = layout_get_theme_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Invalid session token. Please try again.');
        redirect('modules/settings/layout.php');
    }

    $themeId = trim((string) ($_POST['theme_id'] ?? ''));
    if (!layout_set_theme($themeId)) {
        flash_set('error', 'Invalid layout style selected.');
        redirect('modules/settings/layout.php');
    }

    flash_set('success', 'Layout style applied successfully.');
    redirect('modules/settings/layout.php');
}

require ROOT_PATH . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Layout Style</h1>
        <p class="text-muted mb-0">Choose a color combination for top navbar, side navbar, and second top navbar</p>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($themes as $theme): ?>
        <?php $isActive = $selectedId === $theme['id']; ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card panel-card theme-card h-100 <?= $isActive ? 'theme-card-active' : '' ?>">
                <div class="card-body">
                    <div class="theme-preview mb-3" aria-hidden="true">
                        <div class="theme-preview-top" style="background: <?= e($theme['primary']) ?>;"></div>
                        <div class="theme-preview-second" style="background: <?= e($theme['secondary']) ?>;"></div>
                        <div class="theme-preview-body">
                            <div class="theme-preview-side" style="background: <?= e($theme['primary']) ?>;"></div>
                            <div class="theme-preview-main"></div>
                        </div>
                    </div>

                    <h5 class="mb-1"><?= e($theme['name']) ?></h5>
                    <p class="text-muted small mb-3">
                        Top / Side: <code><?= e($theme['primary']) ?></code><br>
                        Second Top: <code><?= e($theme['secondary']) ?></code>
                    </p>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="theme_id" value="<?= e($theme['id']) ?>">
                        <?php if ($isActive): ?>
                            <button type="button" class="btn btn-success w-100" disabled>
                                <i class="bi bi-check-circle me-1"></i> Selected
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-palette me-1"></i> Apply Style
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require ROOT_PATH . '/includes/footer.php'; ?>
