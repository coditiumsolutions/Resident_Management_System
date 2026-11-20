<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'New Complaints';
$currentNav = 'complaints-new';
$pageHeading = 'New Complaints';
$pageSubheading = 'Register new complaints and review open New tickets';
$forcedWhere = " AND c.Status = 'New'";
$forcedParams = [];
$showCreate = true;
$lockStatusFilter = true;

require __DIR__ . '/_list.php';
