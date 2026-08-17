<?php
// business/reports/analytics.php - PROFESSIONAL ANALYTICS DASHBOARD
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
// GET ANALYTICS DATA
// ============================================================

// Sales Growth
$this_week_start = date('Y-m-d', strtotime('-7 days'));
$last_week_start = date('Y-m-d', strtotime('-14 days'));
$last_week_end = date('Y-m-d', strtotime('-7 days'));

$this_week_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = $business_id AND order_date >= '$this_week_start' AND status = 'delivered'");
$this_week_total = mysqli_fetch_assoc($this_week_result)['total'] ?? 0;

$last_week_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = $business_id AND order_date BETWEEN '$last_week_start' AND '$last_week_end' AND status = 'delivered'");
$last_week_total = mysqli_fetch_assoc($last_week_result)['total'] ?? 0;

$growth_percent = $last_week_total > 0 ? round((($this_week_total - $last_week_total) / $last_week_total) * 100) : 0;

// Average Order Value
$avg_order_result = mysqli_query($conn, "SELECT AVG(grand_total) as avg FROM orders WHERE business_id = $business_id AND status = 'delivered'");
$avg_order_value = round(mysqli_fetch_assoc($avg_order_result)['avg'] ?? 0);

// Top Products
$top_products_result = mysqli_query($conn, "SELECT p.name, p.image_url, p.product_id, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.business_id = $business_id AND o.status = 'delivered'
    GROUP BY oi.product_id
    ORDER BY total_sold DESC LIMIT 5");
$top_products = mysqli_fetch_all($top_products_result, MYSQLI_ASSOC);

// Monthly Revenue Chart (Last 6 Months)
$monthly_sql = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as month,
                    DATE_FORMAT(order_date, '%b') as month_label,
                    COUNT(*) as orders_count,
                    SUM(grand_total) as revenue
                FROM orders 
                WHERE business_id = '$business_id' 
                AND status = 'delivered'
                AND order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                ORDER BY month ASC";
$monthly_result = mysqli_query($conn, $monthly_sql);
$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_result)) {
    $monthly_data[] = $row;
}

// Order Status Distribution
$status_sql = "SELECT status, COUNT(*) as count FROM orders 
               WHERE business_id = '$business_id' 
               GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);
$status_data = [];
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_data[] = $row;
}

// Peak Hours
$peak_hours_result = mysqli_query($conn, "SELECT HOUR(order_date) as hour, COUNT(*) as orders 
    FROM orders WHERE business_id = $business_id AND status = 'delivered'
    GROUP BY HOUR(order_date) 
    ORDER BY orders DESC LIMIT 5");
$peak_hours = mysqli_fetch_all($peak_hours_result, MYSQLI_ASSOC);

// Customer Stats
$customer_result = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT customer_id) as total_customers,
    COUNT(DISTINCT CASE WHEN order_count = 1 THEN customer_id END) as new_customers,
    COUNT(DISTINCT CASE WHEN order_count > 1 THEN customer_id END) as returning_customers
    FROM (SELECT customer_id, COUNT(*) as order_count FROM orders WHERE business_id = $business_id AND status = 'delivered' GROUP BY customer_id) as t");
$customer_data = mysqli_fetch_assoc($customer_result);

// Low Stock Alert
$low_stock_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE business_id = $business_id AND quantity_in_stock < 10 AND quantity_in_stock > 0");
$low_stock = mysqli_fetch_assoc($low_stock_result)['count'];

// Out of Stock
$out_stock_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE business_id = $business_id AND quantity_in_stock <= 0 AND is_available = 1");
$out_stock = mysqli_fetch_assoc($out_stock_result)['count'];

// Pending Orders Alert
$pending_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE business_id = $business_id AND status = 'pending'");
$pending_orders = mysqli_fetch_assoc($pending_result)['count'];

// Total Revenue
$total_revenue_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = $business_id AND status = 'delivered'");
$total_revenue = mysqli_fetch_assoc($total_revenue_result)['total'] ?? 0;

// Today's Revenue
$today = date('Y-m-d');
$today_revenue_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = $business_id AND status = 'delivered' AND DATE(order_date) = '$today'");
$today_revenue = mysqli_fetch_assoc($today_revenue_result)['total'] ?? 0;

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Analytics Dashboard - UNK System</title>
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
        
        /* Two Columns */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
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
        .chart-container { height: 250px; position: relative; }
        
        /* Product List */
        .product-list { list-style: none; }
        .product-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-list li:last-child { border-bottom: none; }
        .product-list .product-info {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .product-name {
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #dbeafe; color: #2563eb; }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 4px solid;
            margin-bottom: 0.5rem;
        }
        .alert-warning { background: #fef3c7; color: #d97706; border-left-color: #f59e0b; }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left-color: #ef4444; }
        
        .alert-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        .alert-card h3 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-card h3 i { color: #e67e22; }
        
        .status-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
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
        
        @media (max-width: 1100px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .two-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .kpi-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
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
            <h1><i class="fas fa-chart-pie"></i> Analytics Dashboard</h1>
            <p>Business insights and performance metrics for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
        <!-- <a href="export_stock.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-export">
                    <i class="fas fa-file-export"></i> Export
        </a> -->
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($this_week_total); ?></div>
            <div class="kpi-label">This Week Revenue</div>
            <span class="kpi-change <?php echo $growth_percent >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-arrow-<?php echo $growth_percent >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo abs($growth_percent); ?>% vs last week
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($avg_order_value); ?></div>
            <div class="kpi-label">Average Order Value</div>
            <span class="kpi-change neutral"><i class="fas fa-chart-simple"></i> Per order</span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-users"></i></div>
            <div class="kpi-value"><?php echo number_format($customer_data['total_customers'] ?? 0); ?></div>
            <div class="kpi-label">Total Customers</div>
            <span class="kpi-change positive">
                <i class="fas fa-user-plus"></i>
                <?php echo $customer_data['new_customers'] ?? 0; ?> new
            </span>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon"><i class="fas fa-tachometer-alt"></i></div>
            <div class="kpi-value">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="kpi-label">Total Revenue</div>
            <span class="kpi-change positive">
                <i class="fas fa-calendar-day"></i>
                TSh <?php echo number_format($today_revenue); ?> today
            </span>
        </div>
    </div>

    <!-- Two Columns: Monthly Chart & Status -->
    <div class="two-columns">
        <!-- Monthly Revenue Chart -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-bar"></i> Monthly Revenue</h3>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
            <?php if (!empty($monthly_data)): ?>
            <div style="text-align:center; margin-top:0.5rem; font-size:0.7rem; color:#64748b;">
                <i class="fas fa-arrow-trend-up" style="color:#10b981;"></i> Showing last 6 months
            </div>
            <?php endif; ?>
        </div>

        <!-- Order Status Distribution -->
        <div class="chart-card">
            <h3><i class="fas fa-tasks"></i> Order Status Distribution</h3>
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
            <?php if (!empty($status_data)): ?>
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
                foreach ($status_data as $status):
                    $info = $status_labels[$status['status']] ?? ['label' => ucfirst($status['status']), 'icon' => 'fa-circle'];
                ?>
                <span class="status-pill <?php echo $status['status']; ?>">
                    <i class="fas <?php echo $info['icon']; ?>"></i>
                    <?php echo $info['label']; ?>
                    <span class="count"><?php echo $status['count']; ?></span>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Two Columns: Top Products & Peak Hours -->
    <div class="two-columns">
        <!-- Best Selling Products -->
        <div class="chart-card">
            <h3><i class="fas fa-trophy"></i> Best Selling Products</h3>
            <?php if (empty($top_products)): ?>
                <div style="text-align:center; padding:2rem; color:#94a3b8;">
                    <i class="fas fa-box-open" style="font-size:2rem; display:block; margin-bottom:0.5rem; opacity:0.3;"></i>
                    <p>No sales data available</p>
                </div>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach($top_products as $product): ?>
                    <li>
                        <div class="product-info">
                            <img src="<?php echo getProductImage($product['image_url'] ?? ''); ?>" 
                                 class="product-img" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='../../assets/images/default-product.jpg'">
                            <span class="product-name"><?php echo htmlspecialchars(substr($product['name'], 0, 25)); ?></span>
                        </div>
                        <span class="badge badge-success"><?php echo $product['total_sold']; ?> sold</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Peak Hours -->
        <div class="chart-card">
            <h3><i class="fas fa-clock"></i> Peak Ordering Hours</h3>
            <div class="chart-container">
                <canvas id="peakHoursChart"></canvas>
            </div>
            <?php if(!empty($peak_hours)): ?>
            <div style="margin-top: 1rem; text-align: center;">
                <span class="badge badge-warning">
                    <i class="fas fa-clock"></i> Best time: <?php echo $peak_hours[0]['hour']; ?>:00 
                    (<?php echo $peak_hours[0]['orders']; ?> orders)
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerts Section -->
    <div class="alert-card">
        <h3><i class="fas fa-bell"></i> Action Required</h3>
        <?php if($low_stock > 0 || $out_stock > 0): ?>
            <?php if($out_stock > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                You have <strong><?php echo $out_stock; ?></strong> product(s) out of stock. Restock immediately!
            </div>
            <?php endif; ?>
            <?php if($low_stock > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                You have <strong><?php echo $low_stock; ?></strong> product(s) with low stock. Restock soon!
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if($pending_orders > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock"></i>
            You have <strong><?php echo $pending_orders; ?></strong> pending order(s) waiting for confirmation.
        </div>
        <?php endif; ?>
        <?php if($low_stock == 0 && $out_stock == 0 && $pending_orders == 0): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            Great job! No pending actions required. Your business is running smoothly.
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // MONTHLY REVENUE CHART
    // ============================================================
    <?php if(!empty($monthly_data)): ?>
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthly_data, 'month_label')); ?>,
            datasets: [{
                label: 'Revenue (TSh)',
                data: <?php echo json_encode(array_column($monthly_data, 'revenue')); ?>,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230, 126, 34, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'TSh ' + (value / 1000) + 'k';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // ============================================================
    // ORDER STATUS CHART
    // ============================================================
    <?php if(!empty($status_data)): 
        $status_colors = [
            'pending' => '#f59e0b',
            'confirmed' => '#3b82f6',
            'processing' => '#8b5cf6',
            'shipped' => '#ec4899',
            'delivered' => '#10b981',
            'cancelled' => '#ef4444'
        ];
        $status_labels = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
        $labels = [];
        $values = [];
        $colors = [];
        foreach ($status_data as $s) {
            $labels[] = $status_labels[$s['status']] ?? $s['status'];
            $values[] = $s['count'];
            $colors[] = $status_colors[$s['status']] ?? '#94a3b8';
        }
    ?>
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                data: <?php echo json_encode($values); ?>,
                backgroundColor: <?php echo json_encode($colors); ?>,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
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
    // PEAK HOURS CHART
    // ============================================================
    <?php if(!empty($peak_hours)): 
        $peak_labels = array_map(function($h) { 
            return $h['hour'] . ':00'; 
        }, $peak_hours);
        $peak_values = array_column($peak_hours, 'orders');
    ?>
    const peakCtx = document.getElementById('peakHoursChart').getContext('2d');
    new Chart(peakCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($peak_labels); ?>,
            datasets: [{
                label: 'Orders',
                data: <?php echo json_encode($peak_values); ?>,
                backgroundColor: ['#e67e22', '#f39c12', '#f1c40f', '#e67e22', '#d35400'],
                borderRadius: 6,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    <?php endif; ?>

    // ============================================================
    // SIDEBAR ACTIVE LINK
    // ============================================================
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/analytics.php' || 
            links[i].getAttribute('href') === 'analytics.php') {
            links[i].classList.add('active');
        }
    }
});
</script>

</body>
</html>