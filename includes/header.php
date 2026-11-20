<?php

declare(strict_types=1);

$user = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$currentNav = $currentNav ?? 'dashboard';
$currentSection = resolve_nav_section($currentNav);
$activeLayoutTheme = layout_get_theme();
$layoutThemeCss = layout_theme_css_vars($activeLayoutTheme);

$topModules = [
    ['section' => 'resident', 'label' => 'Residents', 'href' => 'modules/residents/index.php'],
    ['section' => 'property', 'label' => 'Units Detail', 'href' => 'modules/properties/index.php'],
    ['section' => 'billing', 'label' => 'Billings', 'href' => 'modules/billing/index.php'],
    ['section' => 'service', 'label' => 'Services', 'href' => 'modules/services/index.php'],
];

$modulesMenu = [
    ['section' => 'visitor', 'label' => 'Visitors', 'href' => 'modules/visitors/register.php'],
    ['section' => 'complaint', 'label' => 'Complaints', 'href' => 'modules/complaints/index.php'],
    ['section' => 'coord', 'label' => 'Coordination', 'href' => 'modules/coordination/announcements.php'],
    ['section' => 'report', 'label' => 'Reports', 'href' => 'modules/reports/collection.php'],
    ['section' => 'user', 'label' => 'Users', 'href' => 'modules/users/index.php'],
];

$modulesMenuSections = array_column($modulesMenu, 'section');
$modulesMenuActive = in_array($currentSection, $modulesMenuSections, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_SHORT_NAME) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
    <style id="layout-theme-vars"><?= $layoutThemeCss ?></style>
</head>
<body class="app-body">
<header class="global-headers">
    <div class="navbar-primary">
        <div class="navbar-primary-inner">
            <span class="brand-mark brand-mark-sm">RP</span>
            <span class="project-name"><?= e(APP_NAME) ?></span>
        </div>
    </div>
    <div class="navbar-secondary">
        <div class="navbar-secondary-inner">
            <nav class="top-module-nav" aria-label="Main modules">
                <?php foreach ($topModules as $module): ?>
                    <a class="top-module-link <?= nav_section_active($module['section'], $currentNav) ?>"
                       href="<?= e(base_url($module['href'])) ?>">
                        <?= e($module['label']) ?>
                    </a>
                <?php endforeach; ?>

                <div class="top-module-dropdown <?= $modulesMenuActive ? 'active' : '' ?>">
                    <button type="button"
                            class="top-module-link top-module-dropdown-toggle <?= $modulesMenuActive ? 'active' : '' ?>"
                            id="modulesMenuToggle"
                            aria-expanded="<?= $modulesMenuActive ? 'true' : 'false' ?>"
                            aria-haspopup="true">
                        Modules <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="top-module-dropdown-menu" id="modulesMenu" role="menu" aria-label="Modules">
                        <?php foreach ($modulesMenu as $module): ?>
                            <a class="top-module-dropdown-item <?= nav_section_active($module['section'], $currentNav) ?>"
                               role="menuitem"
                               href="<?= e(base_url($module['href'])) ?>">
                                <?= e($module['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </nav>

            <div class="navbar-secondary-credit">
                developed by
                <a href="https://coditium.com" target="_blank" rel="noopener noreferrer">Coditium Solutions (coditium.com)</a>
                <a href="tel:03335191392">0333-5191392</a>
            </div>
        </div>
    </div>
</header>

<div class="app-wrapper" id="appWrapper">
    <?php require ROOT_PATH . '/includes/sidebar.php'; ?>

    <div class="app-main">
        <header class="app-topbar">
            <div class="topbar-left">
                <button type="button" class="btn btn-icon sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-page-title d-none d-sm-block">
                    <strong><?= e($pageTitle) ?></strong>
                </div>
            </div>

            <div class="topbar-right">
                <button type="button" class="btn btn-icon position-relative" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="notification-dot"></span>
                </button>

                <div class="dropdown">
                    <button class="btn user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar"><?= e(strtoupper(substr($user['FullName'] ?? 'U', 0, 1))) ?></span>
                        <span class="user-meta d-none d-sm-inline">
                            <strong><?= e($user['FullName'] ?? 'User') ?></strong>
                            <small><?= e($user['UserType'] ?? '') ?></small>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><h6 class="dropdown-header"><?= e($user['Username'] ?? '') ?></h6></li>
                        <li><a class="dropdown-item" href="<?= e(base_url('modules/users/index.php')) ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= e(base_url('modules/settings/index.php')) ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php $flash = flash_get(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
