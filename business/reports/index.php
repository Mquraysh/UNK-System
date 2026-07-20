<?php
// business/reports/index.php - PROFESSIONAL REPORTS DASHBOARD
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business data
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$business_name = $business['business_name'];

// ============================================================
// GET REPORT STATISTICS
// ============================================================

// Total orders
$orders_sql = "SELECT COUNT(*) as total, 
                      SUM(grand_total) as total_revenue,
                      AVG(grand_total) as avg_order,
                      COUNT(DISTINCT customer_id) as unique_customers
               FROM orders 
               WHERE business_id = '$business_id'";
$orders_result = mysqli_query($conn, $orders_sql);
$stats = mysqli_fetch_assoc($orders_result);

$total_orders = $stats['total'] ?? 0;
$total_revenue = $stats['total_revenue'] ?? 0;
$avg_order = $stats['avg_order'] ?? 0;
$unique_customers = $stats['unique_customers'] ?? 0;

// Orders by status
$status_sql = "SELECT status, COUNT(*) as count FROM orders 
               WHERE business_id = '$business_id' 
               GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);
$status_counts = [];
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_counts[$row['status']] = $row['count'];
}

// Monthly revenue for chart (last 12 months)
$monthly_sql = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as month,
                    DATE_FORMAT(order_date, '%b %Y') as month_label,
                    COUNT(*) as orders_count,
                    SUM(grand_total) as revenue
                FROM orders 
                WHERE business_id = '$business_id' 
                AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                ORDER BY month ASC";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_data[] = $row;
}

// Current month orders
$current_month = date('Y-m');
$current_month_sql = "SELECT COUNT(*) as count, SUM(grand_total) as revenue 
                      FROM orders 
                      WHERE business_id = '$business_id' 
                      AND DATE_FORMAT(order_date, '%Y-%m') = '$current_month'";
$current_month_result = mysqli_query($conn, $current_month_sql);
$current_month_data = mysqli_fetch_assoc($current_month_result);
$current_month_orders = $current_month_data['count'] ?? 0;
$current_month_revenue = $current_month_data['revenue'] ?? 0;

// Get product count
$product_sql = "SELECT COUNT(*) as total FROM products WHERE business_id = '$business_id' AND is_available = 1";
$product_result = mysqli_query($conn, $product_sql);
$total_products = mysqli_fetch_assoc($product_result)['total'] ?? 0;

// Get low stock products
$low_stock_sql = "SELECT COUNT(*) as total FROM products WHERE business_id = '$business_id' AND quantity_in_stock < 10 AND quantity_in_stock > 0";
$low_stock_result = mysqli_query($conn, $low_stock_sql);
$low_stock_count = mysqli_fetch_assoc($low_stock_result)['total'] ?? 0;

// Recent orders (last 10)
$recent_sql = "SELECT o.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name
               FROM orders o
               JOIN customers c ON o.customer_id = c.customer_id
               WHERE o.business_id = '$business_id'
               ORDER BY o.order_date DESC
               LIMIT 10";
$recent_result = mysqli_query($conn, $recent_sql);
$recent_orders = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_orders[] = $row;
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Business Reports | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        .stat-change {
            font-size: 0.6rem;
            margin-top: 0.3rem;
            padding: 0.1rem 0.4rem;
            border-radius: 1rem;
        }
        .stat-change.positive {
            background: #d1fae5;
            color: #059669;
        }
        .stat-change.negative {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 1.25rem; }
        
        /* Chart */
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .chart-bars {
            display: flex;
            align-items: flex-end;
            height: 220px;
            gap: 0.5rem;
            padding-top: 1rem;
        }
        .chart-bar-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        .chart-bar {
            width: 100%;
            max-width: 40px;
            border-radius: 4px 4px 0 0;
            min-height: 4px;
            transition: height 0.6s ease;
            background: linear-gradient(180deg, #e67e22, #d35400);
            position: relative;
        }
        .chart-bar:hover {
            opacity: 0.8;
            transform: scaleY(1.02);
            transform-origin: bottom;
        }
        .chart-bar-label {
            font-size: 0.55rem;
            color: #64748b;
            margin-top: 0.3rem;
            text-align: center;
        }
        .chart-bar-value {
            font-size: 0.55rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }
        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.7rem;
            color: #64748b;
        }
        .chart-legend span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .chart-legend .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }
        
        /* Status Pills */
        .status-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-pill .count {
            background: rgba(255,255,255,0.3);
            padding: 0.1rem 0.3rem;
            border-radius: 1rem;
            font-size: 0.55rem;
        }
        .status-pill.pending { background: #fef3c7; color: #d97706; }
        .status-pill.confirmed { background: #dbeafe; color: #2563eb; }
        .status-pill.processing { background: #ede9fe; color: #7c3aed; }
        .status-pill.shipped { background: #fce7f3; color: #be185d; }
        .status-pill.delivered { background: #d1fae5; color: #059669; }
        .status-pill.cancelled { background: #fee2e2; color: #dc2626; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            background: #f8fafc;
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: #fffbeb;
        }
        
        .order-status {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .order-status.pending { background: #fef3c7; color: #d97706; }
        .order-status.confirmed { background: #dbeafe; color: #2563eb; }
        .order-status.processing { background: #ede9fe; color: #7c3aed; }
        .order-status.shipped { background: #fce7f3; color: #be185d; }
        .order-status.delivered { background: #d1fae5; color: #059669; }
        .order-status.cancelled { background: #fee2e2; color: #dc2626; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }
        .empty-state p { font-size: 0.85rem; }
        
        /* Report Links - Professional Grid */
        .report-links {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.75rem;
        }
        .report-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 0.5rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.3s;
            text-align: center;
            min-height: 120px;
        }
        .report-link:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .report-link i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }
        .report-link:hover i {
            transform: scale(1.1);
        }
        .report-link .title {
            font-weight: 700;
            font-size: 0.8rem;
            color: #0f172a;
        }
        .report-link .desc {
            font-size: 0.6rem;
            color: #64748b;
            margin-top: 0.2rem;
        }
        .report-link .badge {
            margin-top: 0.3rem;
            padding: 0.1rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.55rem;
            font-weight: 600;
        }
        .report-link .badge.orange { background: #fdf2e9; color: #e67e22; }
        .report-link .badge.green { background: #d1fae5; color: #059669; }
        .report-link .badge.blue { background: #dbeafe; color: #2563eb; }
        .report-link .badge.purple { background: #ede9fe; color: #7c3aed; }
        
        /* Color variants for report links */
        .report-link.analytics i { color: #8b5cf6; }
        .report-link.analytics:hover { border-color: #8b5cf6; background: #f5f3ff; }
        
        .report-link.sales i { color: #10b981; }
        .report-link.sales:hover { border-color: #10b981; background: #ecfdf5; }
        
        .report-link.stock i { color: #f59e0b; }
        .report-link.stock:hover { border-color: #f59e0b; background: #fffbeb; }
        
        .report-link.products i { color: #3b82f6; }
        .report-link.products:hover { border-color: #3b82f6; background: #eff6ff; }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .report-links { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .report-links { grid-template-columns: 1fr 1fr; }
            .report-link { min-height: 100px; padding: 1rem 0.5rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .chart-bars { gap: 0.3rem; }
            .chart-bar { max-width: 25px; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .report-links { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> Business Reports</h1>
            <p>Analytics and insights for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="export.php" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export Report
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> <?php echo $current_month_orders; ?> this month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up"></i> TSh <?php echo number_format($current_month_revenue); ?> this month
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-number">TSh <?php echo number_format($avg_order); ?></div>
            <div class="stat-label">Average Order Value</div>
            <div class="stat-change">Per transaction</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?php echo $unique_customers; ?></div>
            <div class="stat-label">Unique Customers</div>
            <div class="stat-change">Total customers</div>
        </div>
    </div>

    <!-- Monthly Revenue Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Monthly Revenue (Last 12 Months)</h3>
            <span class="badge-count"><?php echo count($monthly_data); ?> months</span>
        </div>
        <div class="card-body">
            <?php if (empty($monthly_data)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-bar"></i>
                    <h3>No Data Available</h3>
                    <p>Start making sales to see your revenue chart.</p>
                </div>
            <?php else: 
                $max_revenue = max(array_column($monthly_data, 'revenue')) ?: 1;
            ?>
                <div class="chart-bars">
                    <?php foreach ($monthly_data as $data): 
                        $height = ($data['revenue'] / $max_revenue) * 200;
                        $height = max($height, 10);
                    ?>
                    <div class="chart-bar-wrapper">
                        <div class="chart-bar-value">TSh <?php echo number_format($data['revenue'] / 1000); ?>k</div>
                        <div class="chart-bar" style="height: <?php echo $height; ?>px;"></div>
                        <div class="chart-bar-label"><?php echo $data['month_label']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="chart-legend">
                    <span><span class="dot" style="background:#e67e22;"></span> Revenue (TSh)</span>
                    <span><i class="fas fa-shopping-cart" style="color:#e67e22;"></i> <?php echo $total_orders; ?> total orders</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Status & Quick Reports -->
    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
        <!-- Order Status -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tasks"></i> Order Status</h3>
                <span class="badge-count"><?php echo $total_orders; ?> orders</span>
            </div>
            <div class="card-body">
                <?php if (empty($status_counts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No orders yet</p>
                    </div>
                <?php else: ?>
                    <div class="status-pills">
                        <?php 
                        $status_labels = [
                            'pending' => ['label' => 'Pending', 'icon' => 'fa-clock'],
                            'confirmed' => ['label' => 'Confirmed', 'icon' => 'fa-check-circle'],
                            'processing' => ['label' => 'Processing', 'icon' => 'fa-spinner'],
                            'shipped' => ['label' => 'Shipped', 'icon' => 'fa-truck'],
                            'delivered' => ['label' => 'Delivered', 'icon' => 'fa-check'],
                            'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-times']
                        ];
                        foreach ($status_counts as $status => $count):
                            $info = $status_labels[$status] ?? ['label' => ucfirst($status), 'icon' => 'fa-circle'];
                        ?>
                        <span class="status-pill <?php echo $status; ?>">
                            <i class="fas <?php echo $info['icon']; ?>"></i>
                            <?php echo $info['label']; ?>
                            <span class="count"><?php echo $count; ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Reports -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> Quick Reports</h3>
                <span class="badge-count">Reports</span>
            </div>
            <div class="card-body">
                <div class="report-links">
                    <!-- Analytics Report -->
                    <a href="analytics.php" class="report-link analytics">
                        <i class="fas fa-chart-pie"></i>
                        <div class="title">Analytics</div>
                        <div class="desc">Full business analytics</div>
                        <span class="badge orange">Insights</span>
                    </a>
                    
                    <!-- Sales Report -->
                    <a href="sales.php" class="report-link sales">
                        <i class="fas fa-chart-simple"></i>
                        <div class="title">Sales Report</div>
                        <div class="desc">Revenue & order trends</div>
                        <span class="badge green">Revenue</span>
                    </a>
                    
                    <!-- Stock History Report -->
                    <a href="stock_history.php" class="report-link stock">
                        <i class="fas fa-warehouse"></i>
                        <div class="title">Stock History</div>
                        <div class="desc">Inventory changes & history</div>
                        <span class="badge orange"><?php echo $low_stock_count; ?> low stock</span>
                    </a>
                    
                    <!-- Products Report -->
                    <a href="products.php" class="report-link products">
                        <i class="fas fa-box"></i>
                        <div class="title">Products</div>
                        <div class="desc">Top selling products</div>
                        <span class="badge blue"><?php echo $total_products; ?> products</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Recent Orders</h3>
            <a href="../orders/index.php" class="btn btn-secondary" style="padding:0.2rem 0.6rem; font-size:0.65rem;">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:2rem;">
                                <i class="fas fa-inbox" style="font-size:1.5rem; opacity:0.3;"></i>
                                <p style="color:#94a3b8; margin-top:0.3rem;">No recent orders</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><strong> <?php echo $order['order_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($order['grand_total']); ?></strong></td>
                            <td><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td style="font-size:0.7rem; color:#64748b;"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td>
                                <a href="../orders/details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-secondary" style="padding:0.2rem 0.5rem; font-size:0.6rem;">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/index.php' || 
            links[i].getAttribute('href') === 'index.php' ||
            links[i].getAttribute('href') === '../reports/analytics.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>