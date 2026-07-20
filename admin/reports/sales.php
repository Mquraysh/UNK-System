<?php
// admin/reports/sales.php - Professional Sales Report
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Sales Report';

// ============================================================
// GET FILTERS
// ============================================================
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

// ============================================================
// BUILD MAIN QUERY - Only Delivered Orders for Sales
// ============================================================
$where = "WHERE DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'";

// For sales data, we ONLY want delivered orders
$sales_where = $where . " AND o.status = 'delivered'";

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $sales_where .= " AND (o.order_id LIKE '%$search_esc%' 
                         OR c.first_name LIKE '%$search_esc%' 
                         OR c.last_name LIKE '%$search_esc%' 
                         OR b.business_name LIKE '%$search_esc%')";
}

// Get delivered orders only (for sales)
$sql = "SELECT 
            o.order_id,
            o.grand_total,
            o.status,
            o.order_date,
            o.payment_method,
            o.payment_status,
            o.delivery_fee,
            b.business_name,
            b.business_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.customer_id,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count
        FROM orders o
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        $sales_where
        ORDER BY o.order_date DESC";

$result = mysqli_query($conn, $sql);
$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

// ============================================================
// CALCULATE TOTALS
// ============================================================
$total_orders = count($orders);
$total_sales = 0;
$total_delivery_fee = 0;
$payment_methods = [];

foreach ($orders as $o) {
    $total_sales += $o['grand_total'];
    $total_delivery_fee += $o['delivery_fee'] ?? 0;
    
    $method = $o['payment_method'] ?? 'Unknown';
    if (!isset($payment_methods[$method])) {
        $payment_methods[$method] = 0;
    }
    $payment_methods[$method] += $o['grand_total'];
}

$avg_order_value = $total_orders > 0 ? $total_sales / $total_orders : 0;

// ============================================================
// STATUS COUNTS
// ============================================================
$status_counts = [];
$status_result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM orders 
                                       WHERE DATE(order_date) BETWEEN '$date_from' AND '$date_to' 
                                       GROUP BY status");
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_counts[$row['status']] = $row['count'];
}

$pending_count = $status_counts['pending'] ?? 0;
$cancelled_count = $status_counts['cancelled'] ?? 0;

// ============================================================
// GET DAILY/WEEKLY/MONTHLY DATA FOR CHART
// ============================================================
if ($period === 'weekly') {
    $chart_sql = "SELECT 
                    CONCAT(YEAR(o.order_date), '-W', WEEK(o.order_date)) as period,
                    CONCAT('Week ', WEEK(o.order_date)) as label,
                    COUNT(*) as order_count,
                    SUM(o.grand_total) as revenue
                  FROM orders o
                  WHERE DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                  AND o.status = 'delivered'
                  GROUP BY YEAR(o.order_date), WEEK(o.order_date)
                  ORDER BY YEAR(o.order_date) ASC, WEEK(o.order_date) ASC";
} elseif ($period === 'monthly') {
    $chart_sql = "SELECT 
                    DATE_FORMAT(o.order_date, '%Y-%m') as period,
                    DATE_FORMAT(o.order_date, '%M %Y') as label,
                    COUNT(*) as order_count,
                    SUM(o.grand_total) as revenue
                  FROM orders o
                  WHERE DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                  AND o.status = 'delivered'
                  GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
                  ORDER BY DATE_FORMAT(o.order_date, '%Y-%m') ASC";
} else {
    // Daily
    $chart_sql = "SELECT 
                    DATE(o.order_date) as period,
                    DATE_FORMAT(o.order_date, '%Y-%m-%d') as label,
                    COUNT(*) as order_count,
                    SUM(o.grand_total) as revenue
                  FROM orders o
                  WHERE DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                  AND o.status = 'delivered'
                  GROUP BY DATE(o.order_date)
                  ORDER BY DATE(o.order_date) ASC";
}

$chart_result = mysqli_query($conn, $chart_sql);
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_data[] = $row;
}

// ============================================================
// GET TOP BUSINESSES
// ============================================================
$top_businesses_result = mysqli_query($conn, "SELECT 
                                                    b.business_name,
                                                    COUNT(o.order_id) as order_count,
                                                    SUM(o.grand_total) as revenue
                                                FROM orders o
                                                JOIN businesses b ON o.business_id = b.business_id
                                                WHERE o.status = 'delivered'
                                                AND DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                                                GROUP BY b.business_id
                                                ORDER BY revenue DESC
                                                LIMIT 5");
$top_businesses = [];
while ($row = mysqli_fetch_assoc($top_businesses_result)) {
    $top_businesses[] = $row;
}

// ============================================================
// GET TOP PRODUCTS
// ============================================================
$top_products_result = mysqli_query($conn, "SELECT 
                                                p.name,
                                                SUM(oi.quantity) as total_sold,
                                                p.price * SUM(oi.quantity) as revenue
                                            FROM order_items oi
                                            JOIN products p ON oi.product_id = p.product_id
                                            JOIN orders o ON oi.order_id = o.order_id
                                            WHERE o.status = 'delivered'
                                            AND DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                                            GROUP BY p.product_id
                                            ORDER BY total_sold DESC
                                            LIMIT 5");
$top_products = [];
while ($row = mysqli_fetch_assoc($top_products_result)) {
    $top_products[] = $row;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Sales Report | Admin</title>
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
        
        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .top-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .top-card-header {
            padding: 14px 20px;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .top-card-header i { color: #e67e22; }
        .top-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .top-item:last-child { border-bottom: none; }
        .top-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            color: #64748b;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .top-rank.rank-1 { background: #fef3c7; color: #e67e22; }
        .top-rank.rank-2 { background: #e2e8f0; color: #64748b; }
        .top-rank.rank-3 { background: #fef3c7; color: #d97706; }
        .top-info { flex: 1; }
        .top-info .name { font-weight: 600; font-size: 14px; }
        .top-info .detail { font-size: 12px; color: #64748b; }
        .top-amount { font-weight: 700; font-size: 14px; color: #e67e22; }
        
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
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .payment-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            background: #e2e8f0;
            color: #64748b;
        }
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
            .top-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
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
            <h1><i class="fas fa-chart-line"></i> Sales Report</h1>
            <p><?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
        </div>
        <div class="header-actions">
            <a href="export.php?type=sales&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn-export">
                <i class="fas fa-file-download"></i> Export CSV
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label"><i class="fas fa-shopping-cart"></i> Delivered Orders</div>
            <div class="value green"><?php echo number_format($total_orders); ?></div>
            <div class="sub-text">Completed deliveries</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Total Sales</div>
            <div class="value orange">TSh <?php echo number_format($total_sales); ?></div>
            <div class="sub-text">Revenue from delivered orders</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-clock"></i> Pending Orders</div>
            <div class="value blue"><?php echo number_format($pending_count); ?></div>
            <div class="sub-text">Awaiting delivery</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-calculator"></i> Avg Order Value</div>
            <div class="value purple">TSh <?php echo number_format($avg_order_value); ?></div>
            <div class="sub-text">Average per delivered order</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-truck"></i> Delivery Fees</div>
            <div class="value orange">TSh <?php echo number_format($total_delivery_fee); ?></div>
            <div class="sub-text">Total delivery charges</div>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="chart-section">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Revenue Trends</h3>
            <div class="period-btns">
                <a href="?period=daily&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="period-btn <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily</a>
                <a href="?period=weekly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="period-btn <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                <a href="?period=monthly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                   class="period-btn <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly</a>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Top Businesses & Top Products -->
    <div class="top-grid">
        <div class="top-card">
            <div class="top-card-header"><i class="fas fa-trophy"></i> Top Businesses</div>
            <?php if (empty($top_businesses)): ?>
                <div style="padding:20px; text-align:center; color:#94a3b8;">No data available</div>
            <?php else: ?>
                <?php foreach ($top_businesses as $index => $b): ?>
                <div class="top-item">
                    <div class="top-rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                    <div class="top-info">
                        <div class="name"><?php echo htmlspecialchars($b['business_name']); ?></div>
                        <div class="detail"><?php echo $b['order_count']; ?> orders</div>
                    </div>
                    <div class="top-amount">TSh <?php echo number_format($b['revenue']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="top-card">
            <div class="top-card-header"><i class="fas fa-box"></i> Top Products</div>
            <?php if (empty($top_products)): ?>
                <div style="padding:20px; text-align:center; color:#94a3b8;">No data available</div>
            <?php else: ?>
                <?php foreach ($top_products as $index => $p): ?>
                <div class="top-item">
                    <div class="top-rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                    <div class="top-info">
                        <div class="name"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div class="detail"><?php echo $p['total_sold']; ?> units sold</div>
                    </div>
                    <div class="top-amount">TSh <?php echo number_format($p['revenue']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

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
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Order ID, Customer, Business..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="sales.php?period=<?php echo $period; ?>" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Sales Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Sales Transactions</h3>
            <span class="text-muted"><?php echo $total_orders; ?> record(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Business</th>
                        <th>Items</th>
                        <th>Amount</th>
                        <th>Delivery Fee</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr class="empty-row">
                            <td colspan="9">
                                <i class="fas fa-inbox"></i>
                                No orders found for this period
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><strong><?php echo $o['order_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($o['business_name']); ?></td>
                            <td><?php echo $o['items_count']; ?></td>
                            <td><strong>TSh <?php echo number_format($o['grand_total']); ?></strong></td>
                            <td>TSh <?php echo number_format($o['delivery_fee'] ?? 0); ?></td>
                            <td><span class="payment-badge"><?php echo ucfirst($o['payment_method'] ?? 'N/A'); ?></span></td>
                            <td><span class="status-badge status-delivered">Delivered</span></td>
                            <td><?php echo date('M d, Y', strtotime($o['order_date'])); ?></td>
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
    var ctx = document.getElementById('salesChart').getContext('2d');
    
    var chartData = <?php echo json_encode($chart_data); ?>;
    var labels = chartData.map(function(item) { return item.label; });
    var revenues = chartData.map(function(item) { return item.revenue; });
    var counts = chartData.map(function(item) { return item.order_count; });
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue (TSh)',
                    data: revenues,
                    backgroundColor: 'rgba(230, 126, 34, 0.7)',
                    borderColor: '#e67e22',
                    borderWidth: 2,
                    yAxisID: 'y',
                    borderRadius: 4,
                },
                {
                    label: 'Orders',
                    data: counts,
                    type: 'line',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    yAxisID: 'y1',
                    tension: 0.3,
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
                    callbacks: {
                        label: function(context) {
                            var label = context.dataset.label || '';
                            var value = context.raw;
                            if (context.dataset.label.includes('Revenue')) {
                                return label + ': TSh ' + value.toLocaleString();
                            }
                            return label + ': ' + value + ' orders';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + value.toLocaleString();
                        },
                        font: { family: 'Inter', size: 10 }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    ticks: {
                        font: { family: 'Inter', size: 10 }
                    }
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