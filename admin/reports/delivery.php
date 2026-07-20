<?php
// admin/reports/delivery.php - Delivery Report
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Delivery Report';

// ============================================================
// GET FILTERS
// ============================================================
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$agent_filter = isset($_GET['agent']) ? (int)$_GET['agent'] : 0;
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

// ============================================================
// GET DELIVERY AGENTS FOR FILTER
// ============================================================
$agents_result = mysqli_query($conn, "SELECT agent_id, CONCAT(first_name, ' ', last_name) as name FROM delivery_agents ORDER BY first_name");
$agents = [];
while ($row = mysqli_fetch_assoc($agents_result)) {
    $agents[] = $row;
}

// ============================================================
// BUILD MAIN QUERY
// ============================================================
$where = "WHERE DATE(d.created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($status_filter)) {
    $where .= " AND d.status = '$status_filter'";
}
if ($agent_filter > 0) {
    $where .= " AND d.agent_id = $agent_filter";
}

// Get deliveries data
$sql = "SELECT 
            d.delivery_id,
            d.order_id,
            d.status as delivery_status,
            d.created_at,
            d.delivered_at,
            d.updated_at,
            d.agent_id,
            o.grand_total,
            o.delivery_fee,
            o.delivery_address,
            b.business_name,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            CONCAT(a.first_name, ' ', a.last_name) as agent_name,
            a.vehicle_type,
            TIMESTAMPDIFF(MINUTE, d.created_at, d.delivered_at) as delivery_time_minutes
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
        $where
        ORDER BY d.created_at DESC";

$result = mysqli_query($conn, $sql);
$deliveries = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Format delivery time
    if ($row['delivery_time_minutes'] !== null) {
        $hours = floor($row['delivery_time_minutes'] / 60);
        $minutes = $row['delivery_time_minutes'] % 60;
        $row['delivery_time_formatted'] = $hours > 0 ? $hours . 'h ' . $minutes . 'm' : $minutes . 'm';
    } else {
        $row['delivery_time_formatted'] = 'N/A';
    }
    $deliveries[] = $row;
}

// ============================================================
// CALCULATE TOTALS
// ============================================================
$total_deliveries = count($deliveries);
$total_delivery_fee = 0;
$completed_count = 0;
$pending_count = 0;
$cancelled_count = 0;
$assigned_count = 0;
$picked_up_count = 0;
$in_transit_count = 0;
$total_delivery_time = 0;
$completed_with_time = 0;

foreach ($deliveries as $d) {
    $total_delivery_fee += $d['delivery_fee'] ?? 0;
    
    if ($d['delivery_status'] === 'delivered') {
        $completed_count++;
        if ($d['delivery_time_minutes'] !== null) {
            $total_delivery_time += $d['delivery_time_minutes'];
            $completed_with_time++;
        }
    } elseif ($d['delivery_status'] === 'assigned') {
        $assigned_count++;
    } elseif ($d['delivery_status'] === 'picked_up') {
        $picked_up_count++;
    } elseif ($d['delivery_status'] === 'in_transit') {
        $in_transit_count++;
    } elseif ($d['delivery_status'] === 'pending') {
        $pending_count++;
    } elseif ($d['delivery_status'] === 'cancelled') {
        $cancelled_count++;
    }
}

$avg_delivery_time = $completed_with_time > 0 ? $total_delivery_time / $completed_with_time : 0;
$avg_delivery_time_formatted = $avg_delivery_time > 0 ? 
    floor($avg_delivery_time / 60) . 'h ' . round($avg_delivery_time % 60) . 'm' : 'N/A';
$completion_rate = $total_deliveries > 0 ? round(($completed_count / $total_deliveries) * 100, 1) : 0;

// ============================================================
// GET AGENT PERFORMANCE
// ============================================================
$agent_performance_sql = "SELECT 
                            a.agent_id,
                            CONCAT(a.first_name, ' ', a.last_name) as agent_name,
                            a.vehicle_type,
                            COUNT(d.delivery_id) as total_deliveries,
                            SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                            AVG(TIMESTAMPDIFF(MINUTE, d.created_at, d.delivered_at)) as avg_delivery_time,
                            SUM(d.delivery_fee) as total_earnings
                        FROM delivery_agents a
                        LEFT JOIN deliveries d ON a.agent_id = d.agent_id
                        AND DATE(d.created_at) BETWEEN '$date_from' AND '$date_to'
                        GROUP BY a.agent_id
                        HAVING total_deliveries > 0
                        ORDER BY total_deliveries DESC
                        LIMIT 10";
$agent_performance_result = mysqli_query($conn, $agent_performance_sql);
$agent_performance = [];
while ($row = mysqli_fetch_assoc($agent_performance_result)) {
    $row['completion_rate'] = $row['total_deliveries'] > 0 ? 
        round(($row['completed_deliveries'] / $row['total_deliveries']) * 100, 1) : 0;
    $row['avg_delivery_time'] = $row['avg_delivery_time'] ? 
        floor($row['avg_delivery_time'] / 60) . 'h ' . round($row['avg_delivery_time'] % 60) . 'm' : 'N/A';
    $agent_performance[] = $row;
}

// ============================================================
// GET DAILY DELIVERY CHART DATA
// ============================================================
if ($period === 'weekly') {
    $chart_sql = "SELECT 
                    CONCAT(YEAR(created_at), '-W', WEEK(created_at)) as period,
                    CONCAT('Week ', WEEK(created_at)) as label,
                    COUNT(*) as deliveries,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed
                FROM deliveries
                WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
                GROUP BY YEAR(created_at), WEEK(created_at)
                ORDER BY YEAR(created_at) ASC, WEEK(created_at) ASC";
} elseif ($period === 'monthly') {
    $chart_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as period,
                    DATE_FORMAT(created_at, '%M %Y') as label,
                    COUNT(*) as deliveries,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed
                FROM deliveries
                WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC";
} else {
    $chart_sql = "SELECT 
                    DATE(created_at) as period,
                    DATE_FORMAT(created_at, '%Y-%m-%d') as label,
                    COUNT(*) as deliveries,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed
                FROM deliveries
                WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE(created_at)
                ORDER BY DATE(created_at) ASC";
}

$chart_result = mysqli_query($conn, $chart_sql);
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_data[] = $row;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Delivery Report | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        
        .report-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .report-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .report-content { padding: 15px; }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-back {
            background: #64748b;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        .btn-export {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-export:hover { background: #059669; transform: translateY(-2px); }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .summary-card .label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .summary-card .value.orange { color: #e67e22; }
        .summary-card .value.green { color: #10b981; }
        .summary-card .value.blue { color: #3b82f6; }
        .summary-card .value.red { color: #ef4444; }
        .summary-card .value.purple { color: #8b5cf6; }
        .summary-card .sub-text {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .chart-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            margin-bottom: 25px;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .chart-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-header h3 i { color: #e67e22; }
        .chart-container {
            position: relative;
            height: 280px;
        }
        .period-btns {
            display: flex;
            gap: 6px;
        }
        .period-btn {
            padding: 6px 16px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }
        .period-btn:hover { background: #f1f5f9; }
        .period-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
        }
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .btn-filter {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-filter:hover { background: #d35400; }
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-reset:hover { background: #64748b; }
        
        .agent-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .agent-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 16px 20px;
            transition: all 0.3s;
        }
        .agent-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .agent-card .agent-name {
            font-weight: 700;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .agent-card .agent-vehicle {
            font-size: 11px;
            color: #64748b;
        }
        .agent-card .agent-stats {
            display: flex;
            gap: 15px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .agent-card .agent-stats .stat {
            font-size: 12px;
            color: #64748b;
        }
        .agent-card .agent-stats .stat strong {
            color: #0f172a;
            font-weight: 700;
        }
        .agent-card .completion-bar {
            margin-top: 8px;
            height: 4px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .agent-card .completion-bar .fill {
            height: 100%;
            background: #10b981;
            border-radius: 4px;
        }
        
        .table-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .table-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #f8fafc; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #ede9fe; color: #5b21b6; }
        .status-in_transit { background: #fce7f3; color: #be185d; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
            .agent-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .agent-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-buttons { display: flex; gap: 10px; }
            .filter-buttons .btn-filter, .filter-buttons .btn-reset { flex: 1; text-align: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .btn-back, .btn-export { flex: 1; justify-content: center; }
            .chart-container { height: 200px; }
        }
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
            .data-table td { font-size: 11px; padding: 8px 6px; }
            .data-table th { font-size: 9px; padding: 8px 6px; }
        }
    </style>
</head>
<body>
<div class="report-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> Delivery Report</h1>
            <p><?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
        </div>
        <div class="header-actions">
            <a href="export.php?type=delivery&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn-export">
                <i class="fas fa-file-download"></i> Export CSV
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label"><i class="fas fa-truck"></i> Total Deliveries</div>
            <div class="value"><?php echo number_format($total_deliveries); ?></div>
            <div class="sub-text">All deliveries</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-check-circle"></i> Completed</div>
            <div class="value green"><?php echo number_format($completed_count); ?></div>
            <div class="sub-text">Delivered successfully</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-clock"></i> Pending</div>
            <div class="value blue"><?php echo number_format($pending_count); ?></div>
            <div class="sub-text">Awaiting processing</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-chart-line"></i> Completion Rate</div>
            <div class="value purple"><?php echo $completion_rate; ?>%</div>
            <div class="sub-text">Success rate</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Total Delivery Fees</div>
            <div class="value orange">TSh <?php echo number_format($total_delivery_fee); ?></div>
            <div class="sub-text">Total earned</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-hourglass-half"></i> Avg Delivery Time</div>
            <div class="value"><?php echo $avg_delivery_time_formatted; ?></div>
            <div class="sub-text">From creation to delivery</div>
        </div>
    </div>

    <!-- Delivery Trends Chart -->
    <div class="chart-section">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Delivery Trends</h3>
            <div class="period-btns">
                <a href="?period=daily&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&agent=<?php echo $agent_filter; ?>" 
                   class="period-btn <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily</a>
                <a href="?period=weekly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&agent=<?php echo $agent_filter; ?>" 
                   class="period-btn <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                <a href="?period=monthly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&agent=<?php echo $agent_filter; ?>" 
                   class="period-btn <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly</a>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="deliveryChart"></canvas>
        </div>
    </div>

    <!-- Agent Performance -->
    <?php if (!empty($agent_performance)): ?>
    <div style="margin-bottom: 25px;">
        <h3 style="font-size:16px; font-weight:700; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-users" style="color:#e67e22;"></i> Agent Performance
        </h3>
        <div class="agent-grid">
            <?php foreach ($agent_performance as $agent): ?>
            <div class="agent-card">
                <div class="agent-name"><?php echo htmlspecialchars($agent['agent_name']); ?></div>
                <div class="agent-vehicle"><i class="fas fa-car"></i> <?php echo ucfirst($agent['vehicle_type'] ?? 'N/A'); ?></div>
                <div class="agent-stats">
                    <span class="stat"><strong><?php echo $agent['total_deliveries']; ?></strong> deliveries</span>
                    <span class="stat"><strong><?php echo $agent['completed_deliveries']; ?></strong> completed</span>
                    <span class="stat"><strong><?php echo $agent['completion_rate']; ?>%</strong> rate</span>
                    <span class="stat"><strong><?php echo $agent['avg_delivery_time']; ?></strong> avg time</span>
                    <span class="stat"><strong>TSh <?php echo number_format($agent['total_earnings']); ?></strong> earnings</span>
                </div>
                <div class="completion-bar">
                    <div class="fill" style="width: <?php echo $agent['completion_rate']; ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; width:100%; align-items:flex-end;">
            <input type="hidden" name="period" value="<?php echo $period; ?>">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="assigned" <?php echo $status_filter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                    <option value="picked_up" <?php echo $status_filter === 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                    <option value="in_transit" <?php echo $status_filter === 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Delivery Agent</label>
                <select name="agent" class="filter-input">
                    <option value="0">All Agents</option>
                    <?php foreach ($agents as $a): ?>
                    <option value="<?php echo $a['agent_id']; ?>" <?php echo $agent_filter == $a['agent_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="delivery.php?period=<?php echo $period; ?>" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Delivery Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Deliveries</h3>
            <span class="text-muted"><?php echo $total_deliveries; ?> record(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Business</th>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Delivery Time</th>
                        <th>Fee</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deliveries)): ?>
                        <tr class="empty-row">
                            <td colspan="9">
                                <i class="fas fa-inbox"></i>
                                No deliveries found for this period
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td><strong><?php echo $d['delivery_id']; ?></strong></td>
                            <td><?php echo $d['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($d['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['business_name']); ?></td>
                            <td><?php echo htmlspecialchars($d['agent_name'] ?? 'Unassigned'); ?></td>
                            <td><span class="status-badge status-<?php echo $d['delivery_status']; ?>"><?php echo str_replace('_', ' ', ucfirst($d['delivery_status'])); ?></span></td>
                            <td><?php echo $d['delivery_time_formatted']; ?></td>
                            <td>TSh <?php echo number_format($d['delivery_fee'] ?? 0); ?></td>
                            <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // DELIVERY CHART
    // ============================================================
    var ctx = document.getElementById('deliveryChart').getContext('2d');
    
    var chartData = <?php echo json_encode($chart_data); ?>;
    var labels = chartData.map(function(item) { return item.label; });
    var totalData = chartData.map(function(item) { return item.deliveries; });
    var completedData = chartData.map(function(item) { return item.completed; });
    
    if (chartData.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Deliveries',
                        data: totalData,
                        backgroundColor: 'rgba(230, 126, 34, 0.7)',
                        borderColor: '#e67e22',
                        borderWidth: 2,
                        borderRadius: 4,
                    },
                    {
                        label: 'Completed',
                        data: completedData,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { family: 'Inter', size: 11, weight: '600' },
                            boxWidth: 12,
                            padding: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                        }
                    },
                    tooltip: {
                        backgroundColor: 'white',
                        titleColor: '#0f172a',
                        bodyColor: '#64748b',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 12,
                        padding: 12,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { family: 'Inter', size: 10 }
                        },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Inter', size: 10 },
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 15
                        }
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>