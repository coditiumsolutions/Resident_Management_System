<?php

declare(strict_types=1);

$currentNav = $currentNav ?? 'dashboard';
$currentSection = resolve_nav_section($currentNav);

$sidebarMenus = [
    'property' => [
        ['key' => 'properties', 'label' => 'Properties', 'icon' => 'bi-building', 'href' => 'modules/properties/index.php'],
        ['key' => 'blocks', 'label' => 'Blocks', 'icon' => 'bi-grid-3x3-gap', 'href' => 'modules/properties/blocks.php'],
        ['key' => 'floors', 'label' => 'Floors', 'icon' => 'bi-layers', 'href' => 'modules/properties/floors.php'],
        ['key' => 'units', 'label' => 'Units', 'icon' => 'bi-door-closed', 'href' => 'modules/properties/units.php'],
    ],
    'resident' => [
        ['key' => 'residents-owners', 'label' => 'Owners', 'icon' => 'bi-person-badge', 'href' => 'modules/residents/owners.php'],
        ['key' => 'residents-tenants', 'label' => 'Tenants', 'icon' => 'bi-person-vcard', 'href' => 'modules/residents/tenants.php'],
        ['key' => 'residents', 'label' => 'Residents', 'icon' => 'bi-people-fill', 'href' => 'modules/residents/index.php'],
        ['key' => 'residents-agreements', 'label' => 'Rental Agreements', 'icon' => 'bi-file-earmark-text', 'href' => 'modules/residents/agreements.php'],
        ['key' => 'residents-ownership', 'label' => 'Ownership History', 'icon' => 'bi-clock-history', 'href' => 'modules/residents/ownership.php'],
    ],
    'billing' => [
        ['key' => 'billing-generate', 'label' => 'Generate Bills', 'icon' => 'bi-plus-circle', 'href' => 'modules/billing/generate.php'],
        ['key' => 'billing-all', 'label' => 'All Bills', 'icon' => 'bi-files', 'href' => 'modules/billing/index.php'],
        ['key' => 'billing-rent', 'label' => 'Rent Collection', 'icon' => 'bi-cash-stack', 'href' => 'modules/billing/rent.php'],
        ['key' => 'billing-electricity', 'label' => 'Electricity Bills', 'icon' => 'bi-lightning-charge', 'href' => 'modules/billing/electricity.php'],
        ['key' => 'billing-maintenance', 'label' => 'Maintenance Bills', 'icon' => 'bi-tools', 'href' => 'modules/billing/maintenance.php'],
        ['key' => 'billing-water', 'label' => 'Water Bills', 'icon' => 'bi-droplet', 'href' => 'modules/billing/water.php'],
        ['key' => 'billing-additional', 'label' => 'Additional Charges', 'icon' => 'bi-plus-square', 'href' => 'modules/billing/additional.php'],
        ['key' => 'billing-payments', 'label' => 'Payment Collection', 'icon' => 'bi-wallet2', 'href' => 'modules/billing/payments.php'],
        ['key' => 'billing-history', 'label' => 'Payment History', 'icon' => 'bi-journal-text', 'href' => 'modules/billing/history.php'],
        ['key' => 'billing-dues', 'label' => 'Outstanding Dues', 'icon' => 'bi-exclamation-triangle', 'href' => 'modules/billing/dues.php'],
    ],
    'visitor' => [
        ['key' => 'visitors-register', 'label' => 'Visitor Registration', 'icon' => 'bi-person-plus', 'href' => 'modules/visitors/register.php'],
        ['key' => 'visitors-entry', 'label' => 'Visitor Entry / Exit', 'icon' => 'bi-door-open', 'href' => 'modules/visitors/entry.php'],
        ['key' => 'visitors-history', 'label' => 'Visitor History', 'icon' => 'bi-clock-history', 'href' => 'modules/visitors/history.php'],
    ],
    'service' => [
        ['key' => 'services-categories', 'label' => 'Service Categories', 'icon' => 'bi-tags', 'href' => 'modules/services/categories.php'],
        ['key' => 'services', 'label' => 'Available Services', 'icon' => 'bi-list-check', 'href' => 'modules/services/index.php'],
        ['key' => 'services-resident', 'label' => 'Resident Services', 'icon' => 'bi-person-gear', 'href' => 'modules/services/resident.php'],
    ],
    'complaint' => [
        ['key' => 'complaints', 'label' => 'All Complaints', 'icon' => 'bi-inbox', 'href' => 'modules/complaints/index.php'],
        ['key' => 'complaints-new', 'label' => 'New Complaints', 'icon' => 'bi-plus-lg', 'href' => 'modules/complaints/new.php'],
        ['key' => 'complaints-pending', 'label' => 'Pending Complaints', 'icon' => 'bi-hourglass-split', 'href' => 'modules/complaints/pending.php'],
        ['key' => 'complaints-resolved', 'label' => 'Resolved Complaints', 'icon' => 'bi-check2-circle', 'href' => 'modules/complaints/resolved.php'],
        ['key' => 'complaints-categories', 'label' => 'Complaint Categories', 'icon' => 'bi-folder2', 'href' => 'modules/complaints/categories.php'],
    ],
    'coord' => [
        ['key' => 'coord-announcements', 'label' => 'Announcements', 'icon' => 'bi-newspaper', 'href' => 'modules/coordination/announcements.php'],
        ['key' => 'coord-notify', 'label' => 'Send Notifications', 'icon' => 'bi-send', 'href' => 'modules/coordination/notify.php'],
        ['key' => 'coord-history', 'label' => 'Communication History', 'icon' => 'bi-chat-square-text', 'href' => 'modules/coordination/history.php'],
    ],
    'report' => [
        ['key' => 'report-collection', 'label' => 'Collection Report', 'icon' => 'bi-graph-up-arrow', 'href' => 'modules/reports/collection.php'],
        ['key' => 'report-outstanding', 'label' => 'Outstanding Report', 'icon' => 'bi-pie-chart', 'href' => 'modules/reports/outstanding.php'],
        ['key' => 'report-resident', 'label' => 'Resident Report', 'icon' => 'bi-people', 'href' => 'modules/reports/residents.php'],
        ['key' => 'report-visitor', 'label' => 'Visitor Report', 'icon' => 'bi-person-lines-fill', 'href' => 'modules/reports/visitors.php'],
        ['key' => 'report-complaint', 'label' => 'Complaint Report', 'icon' => 'bi-clipboard-data', 'href' => 'modules/reports/complaints.php'],
    ],
    'user' => [
        ['key' => 'users', 'label' => 'Users', 'icon' => 'bi-person-gear', 'href' => 'modules/users/index.php'],
        ['key' => 'users-roles', 'label' => 'User Roles', 'icon' => 'bi-key', 'href' => 'modules/users/roles.php'],
    ],
];

$sectionTitles = [
    'property' => 'Property',
    'resident' => 'Resident',
    'billing' => 'Billing',
    'visitor' => 'Visitors',
    'service' => 'Services',
    'complaint' => 'Complaints',
    'coord' => 'Coordination',
    'report' => 'Reports',
    'user' => 'Users',
];
?>
<aside class="app-sidebar" id="appSidebar">
    <nav class="sidebar-nav">
        <a class="nav-link <?= nav_active('dashboard', $currentNav) ?>" href="<?= e(base_url('dashboard/')) ?>">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>

        <?php if (isset($sidebarMenus[$currentSection])): ?>
            <div class="sidebar-section-label"><?= e($sectionTitles[$currentSection] ?? 'Menu') ?></div>
            <?php foreach ($sidebarMenus[$currentSection] as $item): ?>
                <a class="nav-link <?= nav_active($item['key'], $currentNav) ?>" href="<?= e(base_url($item['href'])) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="sidebar-section-label">System</div>
        <a class="nav-link <?= nav_active('settings', $currentNav) ?>" href="<?= e(base_url('modules/settings/index.php')) ?>">
            <i class="bi bi-gear"></i><span>Settings</span>
        </a>
        <a class="nav-link <?= nav_active('layout-style', $currentNav) ?>" href="<?= e(base_url('modules/settings/layout.php')) ?>">
            <i class="bi bi-palette"></i><span>Layout Style</span>
        </a>
    </nav>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
