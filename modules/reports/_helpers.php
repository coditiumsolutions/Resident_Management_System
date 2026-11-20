<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/dashboard_data.php';
require_once dirname(__DIR__) . '/billing/_helpers.php';

function report_month_options(int $months = 18): array
{
    $opts = [];
    for ($i = 0; $i < $months; $i++) {
        $key = date('Y-m', strtotime("-{$i} months"));
        $opts[$key] = date('M Y', strtotime($key . '-01'));
    }

    return $opts;
}
