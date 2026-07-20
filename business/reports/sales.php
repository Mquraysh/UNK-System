<?php
// business/reports/sales.php - PROFESSIONAL SALES REPORT
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);
$business_id = $business['business_id'];
$business_name = $business['business_name'];

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Helper image path
function getProductImage($imagePath) {
    if (empty($imagePath)) {
        return '../../assets/images/default-product.jpg';
    }
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    if ($imagePath[0] === '/') {
        return $imagePath;
    }
    return '../../' . ltrim($imagePath, './');
}

// ============================================================
// GET SALES DATA
// ============================================================

// Daily sales data
$daily_sales_result = mysqli_query($conn, "SELECT DATE(order_date) as date, COUNT(*) as orders, SUM(grand_total) as total, SUM(delivery_fee) as delivery_fee
    FROM orders WHERE business_id = $business_id AND order_date BETWEEN '$start_date' AND '$end_date' AND status = 'delivered'
    GROUP BY DATE(order_date) ORDER BY date");
$daily_sales = mysqli_fetch_all($daily_sales_result, MYSQLI_ASSOC);

// Summary statistics
$summary_result = mysqli_query($conn, "SELECT COUNT(*) as total_orders, SUM(grand_total) as total_revenue, SUM(delivery_fee) as total_delivery_fee, AVG(grand_total) as avg_order_value
    FROM orders WHERE business_id = $business_id AND order_date BETWEEN '$start_date' AND '$end_date' AND status = 'delivered'");
$summary = mysqli_fetch_assoc($summary_result);

// Top products in period
$top_products_result = mysqli_query($conn, "SELECT p.name, p.image_url, p.product_id, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue
    FROM order_items oi JOIN orders o ON oi.order_id = o.order_id JOIN products p ON oi.product_id = p.product_id
    WHERE o.business_id = $business_id AND o.order_date BETWEEN '$start_date' AND '$end_date' AND o.status = 'delivered'
    GROUP BY oi.product_id ORDER BY total_sold DESC LIMIT 10");
$top_products = mysqli_fetch_all($top_products_result, MYSQLI_ASSOC);

// Payment method distribution
$payment_sql = "SELECT payment_method, COUNT(*) as count, SUM(grand_total) as total 
                FROM orders WHERE business_id = $business_id AND order_date BETWEEN '$start_date' AND '$end_date' AND status = 'delivered'
                GROUP BY payment_method";
$payment_result = mysqli_query($conn, $payment_sql);
$payment_data = mysqli_fetch_all($payment_result, MYSQLI_ASSOC);

// Weekly sales trend (for current period)
$weekly_sql = "SELECT 
                    WEEK(order_date) as week_num,
                    DATE_FORMAT(order_date, '%b %d') as week_label,
                    COUNT(*) as orders,
                    SUM(grand_total) as revenue
                FROM orders 
                WHERE business_id = $business_id 
                AND order_date BETWEEN '$start_date' AND '$end_date' 
                AND status = 'delivered'
                GROUP BY WEEK(order_date)
                ORDER BY week_num ASC";
$weekly_result = mysqli_query($conn, $weekly_sql);
$weekly_data = mysqli_fetch_all($weekly_result, MYSQLI_ASSOC);

// Previous period comparison
$prev_start = date('Y-m-d', strtotime('-30 days', strtotime($start_date)));
$prev_end = date('Y-m-d', strtotime('-1 day', strtotime($start_date)));
$prev_result = mysqli_query($conn, "SELECT COUNT(*) as orders, SUM(grand_total) as revenue 
                                    FROM orders WHERE business_id = $business_id 
                                    AND order_date BETWEEN '$prev_start' AND '$prev_end' AND status = 'delivered'");
$prev_data = mysqli_fetch_assoc($prev_result);

$prev_orders = $prev_data['orders'] ?? 0;
$prev_revenue = $prev_data['revenue'] ?? 0;
$current_orders = $summary['total_orders'] ?? 0;
$current_revenue = $summary['total_revenue'] ?? 0;

$orders_change = $prev_orders > 0 ? round((($current_orders - $prev_orders) / $prev_orders) * 100) : 0;
$revenue_change = $prev_revenue > 0 ? round((($current_revenue - $prev_revenue) / $prev_revenue) * 100) : 0;

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Sales Report - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
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
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-reset:hover {
            background: #64748b;
            transform: translateY(-2px);
        }
        
        /* Sub Tabs */
        .sub-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .sub-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            color: #64748b;
            transition: all 0.2s;
        }
        .sub-tab i { font-size: 0.9rem; }
        .sub-tab:hover { background: #fef3c7; color: #e67e22; }
        .sub-tab.active { background: #e67e22; color: white; }
        
        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-group input {
            padding: 0.5rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            background: white;
        }
        .filter-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .filter-info {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-left: auto;
            align-self: center;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .kpi-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .kpi-card .kpi-icon {
            font-size: 1.5rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        .kpi-card .kpi-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
        }
        .kpi-card .kpi-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .kpi-card .kpi-change {
            font-size: 0.6rem;
            margin-top: 0.3rem;
            padding: 0.1rem 0.4rem;
            border-radius: 1rem;
            display: inline-block;
        }
        .kpi-card .kpi-change.positive {
            background: #d1fae5;
            color: #059669;
        }
        .kpi-card .kpi-change.negative {
            background: #fee2e2;
            color: #dc2626;
        }
        .kpi-card .kpi-change.neutral {
            background: #f1f5f9;
            color: #64748b;
        }
        
        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .chart-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .chart-card h3 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .chart-card h3 i { color: #e67e22; }
        .chart-container { height: 300px; position: relative; }
        
        /* Tables */
        .table-wrapper { overflow-x: auto; }
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
        .data-table tr:hover td {
            background: #fffbeb;
        }
        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .product-name {
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-info { background: #dbeafe; color: #2563eb; }
        
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
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 1100px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-group input { width: 100%; }
            .filter-buttons { width: 100%; }
            .filter-buttons .btn, .filter-buttons .btn-reset { flex: 1; justify-content: center; }
            .filter-info { display: none; }
            .sub-tabs { justify-content: center; }
            .sub-tab { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .kpi-grid { grid-template-columns: 1fr; }
            .chart-card { padding: 0.9rem; }
            .chart-container { height: 200px; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> Sales Report</h1>
            <p>Track your sales performance and revenue for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                <a href="sales.php" class="btn-reset"><i class="fas fa-redo-alt"></i> Reset</a>
            </div>
            <div class="filter-info">
                <i class="fas fa-info-circle"></i> 
                <?php 
                $total_days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
                echo $total_days . ' days • ' . ($summary['total_orders'] ?? 0) . ' orders';
                ?>
            </div>
        </form>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="kpi-value"><?php echo number_format($summary['total_orders'] ?? 0); ?></div>
            <div class="kpi-label">Total Orders</div>
            <span class="kpi-change <?php echo $orders_change >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-arrow-<?php echo $orders_change >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo abs($orders_change); ?>% vs previous
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($summary['total_revenue'] ?? 0); ?></div>
            <div class="kpi-label">Total Revenue</div>
            <span class="kpi-change <?php echo $revenue_change >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-arrow-<?php echo $revenue_change >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo abs($revenue_change); ?>% vs previous
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-truck"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($summary['total_delivery_fee'] ?? 0); ?></div>
            <div class="kpi-label">Delivery Fees</div>
            <span class="kpi-change neutral"><i class="fas fa-receipt"></i> Total collected</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($summary['avg_order_value'] ?? 0); ?></div>
            <div class="kpi-label">Average Order Value</div>
            <span class="kpi-change neutral"><i class="fas fa-calculator"></i> Per transaction</span>
        </div>
    </div>

    <!-- Daily Sales Chart -->
    <div class="chart-card">
        <h3><i class="fas fa-chart-line"></i> Daily Sales Trend</h3>
        <div class="chart-container">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Two Columns: Weekly Trend & Payment Methods -->
    <div class="two-columns">
        <!-- Weekly Sales Trend -->
        <div class="chart-card">
            <h3><i class="fas fa-calendar-week"></i> Weekly Sales Trend</h3>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="chart-card">
            <h3><i class="fas fa-credit-card"></i> Payment Methods</h3>
            <div class="chart-container">
                <canvas id="paymentChart"></canvas>
            </div>
            <?php if (!empty($payment_data)): ?>
            <div style="margin-top:0.5rem; text-align:center; font-size:0.7rem; color:#64748b;">
                <?php foreach ($payment_data as $p): ?>
                    <span class="badge badge-info"><?php echo ucfirst($p['payment_method']); ?>: <?php echo $p['count']; ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Products -->
    <div class="chart-card">
        <h3><i class="fas fa-trophy"></i> Top Selling Products</h3>
        <?php if (empty($top_products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Products Sold</h3>
                <p>No sales data available for this period</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity Sold</th>
                            <th>Revenue</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $max_sold = max(array_column($top_products, 'total_sold')) ?: 1;
                        foreach ($top_products as $product): 
                            $percent = round(($product['total_sold'] / $max_sold) * 100);
                        ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <img src="<?php echo getProductImage($product['image_url'] ?? ''); ?>" 
                                         class="product-img" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.src='../../assets/images/default-product.jpg'">
                                    <span class="product-name"><?php echo htmlspecialchars(substr($product['name'], 0, 30)); ?></span>
                                </div>
                            </td>
                            <td><strong><?php echo $product['total_sold']; ?></strong> units</td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($product['revenue'], 0, '.', ','); ?></strong></td>
                            <td>
                                <div style="width:100px; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                    <div style="height:100%; width:<?php echo $percent; ?>%; background:linear-gradient(90deg, #e67e22, #f39c12); border-radius:3px;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Daily Sales Breakdown -->
    <div class="chart-card">
        <h3><i class="fas fa-table"></i> Daily Sales Breakdown</h3>
        <?php if (empty($daily_sales)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Data Available</h3>
                <p>No sales recorded for the selected period</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Delivery Fees</th>
                            <th>Revenue</th>
                            <th>Avg Order</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_sales as $day): 
                            $avg = $day['orders'] > 0 ? $day['total'] / $day['orders'] : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo date('M d, Y', strtotime($day['date'])); ?></strong></td>
                            <td><span class="badge badge-info"><?php echo $day['orders']; ?> orders</span></td>
                            <td><span style="color:#64748b;">TSh <?php echo number_format($day['delivery_fee'], 0, '.', ','); ?></span></td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($day['total'], 0, '.', ','); ?></strong></td>
                            <td style="font-size:0.7rem; color:#64748b;">TSh <?php echo number_format($avg, 0, '.', ','); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8fafc; font-weight:700;">
                            <td><strong>Total</strong></td>
                            <td><strong><?php echo $summary['total_orders'] ?? 0; ?> orders</strong></td>
                            <td><strong>TSh <?php echo number_format($summary['total_delivery_fee'] ?? 0, 0, '.', ','); ?></strong></td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($summary['total_revenue'] ?? 0, 0, '.', ','); ?></strong></td>
                            <td><strong>TSh <?php echo number_format($summary['avg_order_value'] ?? 0, 0, '.', ','); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // DAILY SALES CHART
    // ============================================================
    <?php if(!empty($daily_sales)): ?>
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($daily_sales, 'date')); ?>,
            datasets: [{
                label: 'Daily Sales (TSh)',
                data: <?php echo json_encode(array_column($daily_sales, 'total')); ?>,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230, 126, 34, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_column($daily_sales, 'orders')); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.08)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 11 },
                        boxWidth: 12,
                        padding: 15
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + (value / 1000).toFixed(0) + 'k';
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // ============================================================
    // WEEKLY SALES CHART
    // ============================================================
    <?php if(!empty($weekly_data)): ?>
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($weekly_data, 'week_label')); ?>,
            datasets: [{
                label: 'Revenue (TSh)',
                data: <?php echo json_encode(array_column($weekly_data, 'revenue')); ?>,
                backgroundColor: 'rgba(230, 126, 34, 0.7)',
                borderColor: '#e67e22',
                borderWidth: 2,
                borderRadius: 6,
                order: 1
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_column($weekly_data, 'orders')); ?>,
                type: 'line',
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 4,
                order: 0,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 10 },
                        boxWidth: 12,
                        padding: 10
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + (value / 1000).toFixed(0) + 'k';
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // ============================================================
    // PAYMENT METHODS CHART
    // ============================================================
    <?php if(!empty($payment_data)): 
        $payment_colors = [
            'cash' => '#10b981',
            'mpesa' => '#8b5cf6',
            'tigo_pesa' => '#f59e0b',
            'airtel_money' => '#ec4899',
            'bank_transfer' => '#3b82f6',
            'card' => '#ef4444'
        ];
        $payment_labels = [];
        $payment_values = [];
        $payment_colors_used = [];
        foreach ($payment_data as $p) {
            $method = strtolower($p['payment_method']);
            $payment_labels[] = ucfirst(str_replace('_', ' ', $p['payment_method']));
            $payment_values[] = $p['count'];
            $payment_colors_used[] = $payment_colors[$method] ?? '#94a3b8';
        }
    ?>
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($payment_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($payment_values); ?>,
                backgroundColor: <?php echo json_encode($payment_colors_used); ?>,
                borderWidth: 3,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { size: 10 },
                        boxWidth: 12,
                        padding: 10
                    }
                }
            },
            cutout: '65%'
        }
    });
    <?php endif; ?>

    // ============================================================
    // SIDEBAR ACTIVE LINK
    // ============================================================
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/sales.php' || 
            links[i].getAttribute('href') === 'sales.php') {
            links[i].classList.add('active');
        }
    }
});
</script>

</body>
</html>