<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'All Complaints';
$currentNav = 'complaints';
$pageHeading = 'All Complaints';
$pageSubheading = 'Complete complaint register across all statuses';
$forcedWhere = '';
$forcedParams = [];
$showCreate = false;
$lockStatusFilter = false;

require __DIR__ . '/_list.php';
