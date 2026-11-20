<?php

declare(strict_types=1);

/**
 * Load all dashboard metrics and recent activity from existing RMS tables.
 */
function get_dashboard_data(PDO $pdo): array
{
    $billingMonth = date('Y-m');
    $monthStart = date('Y-m-01 00:00:00');
    $monthEnd = date('Y-m-t 23:59:59');

    $stats = [
        'total_properties' => (int) $pdo->query('SELECT COUNT(*) FROM Properties')->fetchColumn(),
        'total_units' => (int) $pdo->query('SELECT COUNT(*) FROM Units')->fetchColumn(),
        'occupied_units' => (int) $pdo->query(
            "SELECT COUNT(*) FROM Units WHERE Status = 'Occupied'"
        )->fetchColumn(),
        'vacant_units' => (int) $pdo->query(
            "SELECT COUNT(*) FROM Units WHERE Status = 'Vacant'"
        )->fetchColumn(),
        'total_owners' => (int) $pdo->query(
            "SELECT COUNT(DISTINCT OwnerPartyID)
             FROM UnitOwnerships
             WHERE EndDate IS NULL OR EndDate >= CURDATE()"
        )->fetchColumn(),
        'total_tenants' => (int) $pdo->query(
            "SELECT COUNT(DISTINCT PartyID)
             FROM UnitOccupancies
             WHERE Status = 'Active'
               AND (EndDate IS NULL OR EndDate >= CURDATE())
               AND OccupancyType = 'Tenant'"
        )->fetchColumn(),
        'month_bills' => 0.0,
        'month_collection' => 0.0,
        'outstanding' => 0.0,
        'pending_complaints' => (int) $pdo->query(
            "SELECT COUNT(*)
             FROM Complaints
             WHERE Status NOT IN ('Resolved', 'Closed')"
        )->fetchColumn(),
    ];

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(TotalAmount), 0)
         FROM Invoices
         WHERE BillingMonth = :billing_month
           AND InvoiceStatus NOT IN ('Cancelled', 'Void')"
    );
    $stmt->execute(['billing_month' => $billingMonth]);
    $stats['month_bills'] = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(Amount), 0)
         FROM Payments
         WHERE PaymentStatus = 'Completed'
           AND PaymentDate BETWEEN :start AND :end"
    );
    $stmt->execute(['start' => $monthStart, 'end' => $monthEnd]);
    $stats['month_collection'] = (float) $stmt->fetchColumn();

    $stats['outstanding'] = (float) $pdo->query(
        "SELECT COALESCE(SUM(i.TotalAmount - COALESCE(a.allocated, 0)), 0)
         FROM Invoices i
         LEFT JOIN (
             SELECT InvoiceID, SUM(AllocatedAmount) AS allocated
             FROM PaymentAllocations
             GROUP BY InvoiceID
         ) a ON a.InvoiceID = i.InvoiceID
         WHERE i.InvoiceStatus NOT IN ('Cancelled', 'Void', 'Paid')"
    )->fetchColumn();

    // Charts
    $collectionLabels = [];
    $collectionValues = [];
    $monthsMap = [];

    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-{$i} months"));
        $monthsMap[$key] = 0.0;
        $collectionLabels[] = date('M Y', strtotime($key . '-01'));
    }

    $from = date('Y-m-01 00:00:00', strtotime('-11 months'));
    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(PaymentDate, '%Y-%m') AS ym, SUM(Amount) AS total
         FROM Payments
         WHERE PaymentStatus = 'Completed'
           AND PaymentDate >= :from_date
         GROUP BY ym
         ORDER BY ym"
    );
    $stmt->execute(['from_date' => $from]);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($monthsMap[$row['ym']])) {
            $monthsMap[$row['ym']] = (float) $row['total'];
        }
    }
    $collectionValues = array_values($monthsMap);

    $billingLabels = ['Rent', 'Electricity', 'Maintenance', 'Water', 'Additional Charges'];
    $billingValues = array_fill(0, count($billingLabels), 0.0);
    $nameMap = [
        'rent' => 0,
        'electricity' => 1,
        'maintenance' => 2,
        'water' => 3,
        'additional' => 4,
        'additional charges' => 4,
        'additional charge' => 4,
    ];

    $stmt = $pdo->prepare(
        "SELECT ct.ChargeName, COALESCE(SUM(il.Amount), 0) AS total
         FROM ChargeTypes ct
         LEFT JOIN InvoiceLines il ON il.ChargeTypeID = ct.ChargeTypeID
         LEFT JOIN Invoices i ON i.InvoiceID = il.InvoiceID
            AND i.BillingMonth = :billing_month
            AND i.InvoiceStatus NOT IN ('Cancelled', 'Void')
         GROUP BY ct.ChargeTypeID, ct.ChargeName"
    );
    $stmt->execute(['billing_month' => $billingMonth]);
    foreach ($stmt->fetchAll() as $row) {
        $key = strtolower(trim((string) $row['ChargeName']));
        if (isset($nameMap[$key])) {
            $billingValues[$nameMap[$key]] = (float) $row['total'];
        } elseif (str_contains($key, 'rent')) {
            $billingValues[0] += (float) $row['total'];
        } elseif (str_contains($key, 'electric')) {
            $billingValues[1] += (float) $row['total'];
        } elseif (str_contains($key, 'mainten')) {
            $billingValues[2] += (float) $row['total'];
        } elseif (str_contains($key, 'water')) {
            $billingValues[3] += (float) $row['total'];
        } else {
            $billingValues[4] += (float) $row['total'];
        }
    }

    $complaintStatuses = ['New', 'Assigned', 'In Progress', 'On Hold', 'Resolved', 'Closed'];
    $complaintCounts = array_fill_keys($complaintStatuses, 0);
    $rows = $pdo->query(
        "SELECT Status, COUNT(*) AS total
         FROM Complaints
         GROUP BY Status"
    )->fetchAll();
    foreach ($rows as $row) {
        $status = $row['Status'];
        if (isset($complaintCounts[$status])) {
            $complaintCounts[$status] = (int) $row['total'];
        }
    }

    $occupancyLabels = ['Occupied', 'Vacant', 'Under Maintenance'];
    $occupancyValues = [
        (int) $pdo->query("SELECT COUNT(*) FROM Units WHERE Status = 'Occupied'")->fetchColumn(),
        (int) $pdo->query("SELECT COUNT(*) FROM Units WHERE Status = 'Vacant'")->fetchColumn(),
        (int) $pdo->query(
            "SELECT COUNT(*) FROM Units
             WHERE Status IN ('Under Maintenance', 'Maintenance')"
        )->fetchColumn(),
    ];

    $recentPayments = $pdo->query(
        "SELECT p.ReceiptNo,
                party.FullName AS ResidentName,
                (
                    SELECT u.UnitNumber
                    FROM PaymentAllocations pa
                    INNER JOIN Invoices i ON i.InvoiceID = pa.InvoiceID
                    INNER JOIN Units u ON u.UnitID = i.UnitID
                    WHERE pa.PaymentID = p.PaymentID
                    ORDER BY pa.AllocationID
                    LIMIT 1
                ) AS UnitNumber,
                p.PaymentDate,
                p.Amount,
                p.PaymentMethod
         FROM Payments p
         INNER JOIN Parties party ON party.PartyID = p.PayerPartyID
         ORDER BY p.PaymentDate DESC, p.PaymentID DESC
         LIMIT 8"
    )->fetchAll();

    $recentComplaints = $pdo->query(
        "SELECT c.ComplaintNo,
                party.FullName AS ResidentName,
                u.UnitNumber,
                c.Subject,
                c.Priority,
                c.Status,
                c.CreatedDate
         FROM Complaints c
         INNER JOIN Parties party ON party.PartyID = c.PartyID
         INNER JOIN Units u ON u.UnitID = c.UnitID
         ORDER BY c.CreatedDate DESC, c.ComplaintID DESC
         LIMIT 8"
    )->fetchAll();

    $recentVisitors = $pdo->query(
        "SELECT v.VisitorName,
                v.ContactNumber,
                u.UnitNumber,
                vis.CheckInTime,
                vis.VisitStatus
         FROM Visits vis
         INNER JOIN Visitors v ON v.VisitorID = vis.VisitorID
         INNER JOIN Units u ON u.UnitID = vis.UnitID
         ORDER BY vis.CheckInTime DESC, vis.VisitID DESC
         LIMIT 8"
    )->fetchAll();

    $recentAnnouncements = $pdo->query(
        "SELECT Title, PostedDate, Status
         FROM Announcements
         ORDER BY PostedDate DESC, AnnouncementID DESC
         LIMIT 8"
    )->fetchAll();

    return [
        'billing_month' => $billingMonth,
        'stats' => $stats,
        'charts' => [
            'collection' => [
                'labels' => $collectionLabels,
                'values' => $collectionValues,
            ],
            'billing' => [
                'labels' => $billingLabels,
                'values' => $billingValues,
            ],
            'complaints' => [
                'labels' => $complaintStatuses,
                'values' => array_values($complaintCounts),
            ],
            'occupancy' => [
                'labels' => $occupancyLabels,
                'values' => $occupancyValues,
            ],
        ],
        'recent_payments' => $recentPayments,
        'recent_complaints' => $recentComplaints,
        'recent_visitors' => $recentVisitors,
        'recent_announcements' => $recentAnnouncements,
    ];
}

function status_badge_class(string $status): string
{
    $map = [
        'New' => 'new',
        'Assigned' => 'assigned',
        'In Progress' => 'progress',
        'On Hold' => 'hold',
        'Resolved' => 'resolved',
        'Closed' => 'closed',
        'Published' => 'published',
        'Draft' => 'draft',
        'Checked In' => 'checked',
        'Checked Out' => 'closed',
        'High' => 'high',
        'Urgent' => 'urgent',
        'Normal' => 'normal',
        'Low' => 'low',
    ];

    return $map[$status] ?? 'hold';
}
