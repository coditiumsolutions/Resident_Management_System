<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Resolved Complaints';
$currentNav = 'complaints-resolved';
$pageHeading = 'Resolved Complaints';
$pageSubheading = 'Resolved and closed complaint history';
$forcedWhere = " AND c.Status IN ('Resolved', 'Closed')";
$forcedParams = [];
$showCreate = false;
$lockStatusFilter = true;

require __DIR__ . '/_list.php';
