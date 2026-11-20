<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once __DIR__ . '/_helpers.php';
require_login();

$pageTitle = 'Pending Complaints';
$currentNav = 'complaints-pending';
$pageHeading = 'Pending Complaints';
$pageSubheading = 'Complaints still open and awaiting resolution';
$forcedWhere = " AND c.Status NOT IN ('Resolved', 'Closed')";
$forcedParams = [];
$showCreate = false;
$lockStatusFilter = true;

require __DIR__ . '/_list.php';
