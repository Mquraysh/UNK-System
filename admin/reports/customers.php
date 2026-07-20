<?php
// admin/reports/customers.php - Customers Report
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Customers Report';

// ============================================================
// GET FILTERS
// ============================================================
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_orders = isset($_GET['min_orders']) ? (int)$_GET['min_orders'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'total_spent';

// ============================================================
// BUILD CUSTOMER QUERY
// ============================================================
$where = "WHERE DATE(c.created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (c.first_name LIKE '%$search_esc%' 
                     OR c.last_name LIKE '%$search_esc%' 
                     OR u.email LIKE '%$search_esc%' 
                     OR u.phone LIKE '%$search_esc%')";
}

$order_by = "total_spent DESC";
if ($sort_by === 'orders') {
    $order_by = "order_count DESC";
} elseif ($sort_by === 'newest') {
    $order_by = "c.created_at DESC";
} elseif ($sort_by === 'oldest') {
    $order_by = "c.created_at ASC";
}

// Get customers with order statistics
$sql = "SELECT 
            c.customer_id,
            c.first_name,
            c.last_name,
            c.city,
            c.saved_address,
            c.created_at,
            u.email,
            u.phone,
            u.status as user_status,
            COUNT(o.order_id) as order_count,
            COUNT(DISTINCT o.business_id) as businesses_ordered,
            SUM(CASE WHEN o.status = 'delivered' THEN o.grand_total ELSE 0 END) as total_spent,
            SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            MAX(o.order_date) as last_order_date,
            DATEDIFF(NOW(), MAX(o.order_date)) as days_since_last_order
        FROM customers c
        JOIN users u ON c.user_id = u.user_id
        LEFT JOIN orders o ON c.customer_id = o.customer_id
        $where
        GROUP BY c.customer_id
        HAVING order_count >= $min_orders
        ORDER BY $order_by
        LIMIT 50";

$result = mysqli_query($conn, $sql);
$customers = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Calculate customer segment
    if ($row['total_spent'] > 1000000) {
        $row['segment'] = 'VIP';
        $row['segment_color'] = '#8b5cf6';
    } elseif ($row['total_spent'] > 500000) {
        $row['segment'] = 'Premium';
        $row['segment_color'] = '#3b82f6';
    } elseif ($row['total_spent'] > 100000) {
        $row['segment'] = 'Regular';
        $row['segment_color'] = '#10b981';
    } else {
        $row['segment'] = 'New';
        $row['segment_color'] = '#94a3b8';
    }
    
    // Active status
    if ($row['days_since_last_order'] !== null && $row['days_since_last_order'] <= 30) {
        $row['activity_status'] = 'Active';
        $row['activity_color'] = '#10b981';
    } elseif ($row['days_since_last_order'] !== null && $row['days_since_last_order'] <= 90) {
        $row['activity_status'] = 'Inactive';
        $row['activity_color'] = '#f59e0b';
    } elseif ($row['days_since_last_order'] !== null) {
        $row['activity_status'] = 'Dormant';
        $row['activity_color'] = '#ef4444';
    } else {
        $row['activity_status'] = 'New';
        $row['activity_color'] = '#3b82f6';
    }
    
    $customers[] = $row;
}

// ============================================================
// CALCULATE TOTALS
// ============================================================
$total_customers = count($customers);
$total_revenue = 0;
$total_orders = 0;
$vip_count = 0;
$premium_count = 0;
$regular_count = 0;
$new_count = 0;
$active_count = 0;

foreach ($customers as $c) {
    $total_revenue += $c['total_spent'];
    $total_orders += $c['order_count'];
    
    if ($c['segment'] === 'VIP') $vip_count++;
    elseif ($c['segment'] === 'Premium') $premium_count++;
    elseif ($c['segment'] === 'Regular') $regular_count++;
    else $new_count++;
    
    if ($c['activity_status'] === 'Active') $active_count++;
}

$avg_order_per_customer = $total_customers > 0 ? $total_orders / $total_customers : 0;
$avg_spent_per_customer = $total_customers > 0 ? $total_revenue / $total_customers : 0;

// ============================================================
// GET MONTHLY CUSTOMER GROWTH
// ============================================================
$growth_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_customers
                FROM customers
                WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC";
$growth_result = mysqli_query($conn, $growth_sql);
$growth_data = [];
while ($row = mysqli_fetch_assoc($growth_result)) {
    $growth_data[] = $row;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Customers Report | Admin</title>
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
            grid-template-columns: repeat(5, 1fr);
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
        .summary-card .value.purple { color: #8b5cf6; }
        .summary-card .sub-text {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        /* Segment Cards */
        .segment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .segment-card {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s;
        }
        .segment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .segment-card .count {
            font-size: 28px;
            font-weight: 800;
        }
        .segment-card .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .segment-card .dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
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
        
        .segment-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            color: white;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        .status-dormant { background: #fee2e2; color: #dc2626; }
        .status-new { background: #dbeafe; color: #2563eb; }
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
            .segment-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .segment-grid { grid-template-columns: repeat(2, 1fr); }
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
            .segment-grid { grid-template-columns: 1fr; }
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
            <h1><i class="fas fa-user-friends"></i> Customers Report</h1>
            <p><?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
        </div>
        <div class="header-actions">
            <a href="export.php?type=customers&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn-export">
                <i class="fas fa-file-download"></i> Export CSV
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label"><i class="fas fa-users"></i> Total Customers</div>
            <div class="value"><?php echo number_format($total_customers); ?></div>
            <div class="sub-text">Active customers in period</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Total Revenue</div>
            <div class="value orange">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="sub-text">From all customers</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-shopping-cart"></i> Total Orders</div>
            <div class="value blue"><?php echo number_format($total_orders); ?></div>
            <div class="sub-text">Orders placed</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-calculator"></i> Avg Orders/Customer</div>
            <div class="value purple"><?php echo number_format($avg_order_per_customer, 1); ?></div>
            <div class="sub-text">Average orders per customer</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-chart-line"></i> Avg Spend/Customer</div>
            <div class="value green">TSh <?php echo number_format($avg_spent_per_customer); ?></div>
            <div class="sub-text">Average spend per customer</div>
        </div>
    </div>

    <!-- Customer Segments -->
    <div class="segment-grid">
        <div class="segment-card">
            <div class="count" style="color:#8b5cf6;"><?php echo $vip_count; ?></div>
            <div class="label"><span class="dot" style="background:#8b5cf6;"></span> VIP Customers</div>
        </div>
        <div class="segment-card">
            <div class="count" style="color:#3b82f6;"><?php echo $premium_count; ?></div>
            <div class="label"><span class="dot" style="background:#3b82f6;"></span> Premium</div>
        </div>
        <div class="segment-card">
            <div class="count" style="color:#10b981;"><?php echo $regular_count; ?></div>
            <div class="label"><span class="dot" style="background:#10b981;"></span> Regular</div>
        </div>
        <div class="segment-card">
            <div class="count" style="color:#94a3b8;"><?php echo $new_count; ?></div>
            <div class="label"><span class="dot" style="background:#94a3b8;"></span> New Customers</div>
        </div>
    </div>

    <!-- Customer Growth Chart -->
    <div class="chart-section">
        <div class="chart-header">
            <h3><i class="fas fa-chart-line"></i> Customer Growth</h3>
            <span style="font-size:12px; color:#64748b;">New customers per month</span>
        </div>
        <div class="chart-container">
            <canvas id="growthChart"></canvas>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; width:100%; align-items:flex-end;">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label>Min Orders</label>
                <input type="number" name="min_orders" class="filter-input" placeholder="0" value="<?php echo $min_orders; ?>" min="0">
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" class="filter-input">
                    <option value="total_spent" <?php echo $sort_by === 'total_spent' ? 'selected' : ''; ?>>Total Spent</option>
                    <option value="orders" <?php echo $sort_by === 'orders' ? 'selected' : ''; ?>>Order Count</option>
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                </select>
            </div>
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Name, Email, Phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="customers.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Customers</h3>
            <span class="text-muted"><?php echo $total_customers; ?> record(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Last Order</th>
                        <th>Segment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr class="empty-row">
                            <td colspan="9">
                                <i class="fas fa-users"></i>
                                No customers found for this period
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($c['email']); ?></td>
                            <td><?php echo htmlspecialchars($c['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($c['city'] ?? 'N/A'); ?></td>
                            <td><?php echo $c['order_count']; ?></td>
                            <td><strong>TSh <?php echo number_format($c['total_spent']); ?></strong></td>
                            <td><?php echo $c['last_order_date'] ? date('M d, Y', strtotime($c['last_order_date'])) : 'Never'; ?></td>
                            <td>
                                <span class="segment-badge" style="background:<?php echo $c['segment_color']; ?>;">
                                    <?php echo $c['segment']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($c['activity_status']); ?>">
                                    <?php echo $c['activity_status']; ?>
                                </span>
                            </td>
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
    // CUSTOMER GROWTH CHART
    // ============================================================
    var ctx = document.getElementById('growthChart').getContext('2d');
    
    var growthData = <?php echo json_encode($growth_data); ?>;
    var labels = growthData.map(function(item) { return item.month; });
    var values = growthData.map(function(item) { return item.new_customers; });
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'New Customers',
                data: values,
                backgroundColor: 'rgba(230, 126, 34, 0.2)',
                borderColor: '#e67e22',
                borderWidth: 3,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: 'white',
                pointBorderWidth: 2,
                pointRadius: 5,
                fill: true,
                tension: 0.3,
            }]
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
});
</script>

</body>
</html>