<?php
// admin/reports/financial.php - Financial Report
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Financial Report';

// ============================================================
// GET FILTERS
// ============================================================
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$period = isset($_GET['period']) ? $_GET['period'] : 'daily';

// ============================================================
// GET FINANCIAL DATA
// ============================================================

// Total Revenue (from delivered orders)
$revenue_sql = "SELECT 
                    SUM(grand_total) as total_revenue,
                    SUM(delivery_fee) as total_delivery_fee,
                    COUNT(*) as total_orders
                FROM orders
                WHERE status = 'delivered'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'";
$revenue_result = mysqli_query($conn, $revenue_sql);
$revenue_data = mysqli_fetch_assoc($revenue_result);

$total_revenue = $revenue_data['total_revenue'] ?? 0;
$total_delivery_fee = $revenue_data['total_delivery_fee'] ?? 0;
$delivered_orders = $revenue_data['total_orders'] ?? 0;

// Pending Revenue
$pending_sql = "SELECT 
                    SUM(grand_total) as pending_revenue,
                    COUNT(*) as pending_orders
                FROM orders
                WHERE status = 'pending'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'";
$pending_result = mysqli_query($conn, $pending_sql);
$pending_data = mysqli_fetch_assoc($pending_result);
$pending_revenue = $pending_data['pending_revenue'] ?? 0;
$pending_orders = $pending_data['pending_orders'] ?? 0;

// Cancelled Orders
$cancelled_sql = "SELECT 
                    SUM(grand_total) as cancelled_revenue,
                    COUNT(*) as cancelled_orders
                FROM orders
                WHERE status = 'cancelled'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'";
$cancelled_result = mysqli_query($conn, $cancelled_sql);
$cancelled_data = mysqli_fetch_assoc($cancelled_result);
$cancelled_revenue = $cancelled_data['cancelled_revenue'] ?? 0;
$cancelled_orders = $cancelled_data['cancelled_orders'] ?? 0;

// ============================================================
// GET PAYMENT METHOD BREAKDOWN
// ============================================================
$payment_sql = "SELECT 
                    payment_method,
                    COUNT(*) as order_count,
                    SUM(grand_total) as total_amount
                FROM orders
                WHERE status = 'delivered'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'
                GROUP BY payment_method
                ORDER BY total_amount DESC";
$payment_result = mysqli_query($conn, $payment_sql);
$payment_breakdown = [];
while ($row = mysqli_fetch_assoc($payment_result)) {
    $payment_breakdown[] = $row;
}

// ============================================================
// GET MONTHLY REVENUE FOR CHART
// ============================================================
if ($period === 'weekly') {
    $chart_sql = "SELECT 
                    CONCAT(YEAR(order_date), '-W', WEEK(order_date)) as period,
                    CONCAT('Week ', WEEK(order_date)) as label,
                    SUM(grand_total) as revenue,
                    COUNT(*) as orders
                FROM orders
                WHERE status = 'delivered'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'
                GROUP BY YEAR(order_date), WEEK(order_date)
                ORDER BY YEAR(order_date) ASC, WEEK(order_date) ASC";
} elseif ($period === 'monthly') {
    $chart_sql = "SELECT 
                    DATE_FORMAT(order_date, '%Y-%m') as period,
                    DATE_FORMAT(order_date, '%M %Y') as label,
                    SUM(grand_total) as revenue,
                    COUNT(*) as orders
                FROM orders
                WHERE status = 'delivered'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                ORDER BY DATE_FORMAT(order_date, '%Y-%m') ASC";
} else {
    $chart_sql = "SELECT 
                    DATE(order_date) as period,
                    DATE_FORMAT(order_date, '%Y-%m-%d') as label,
                    SUM(grand_total) as revenue,
                    COUNT(*) as orders
                FROM orders
                WHERE status = 'delivered'
                AND DATE(order_date) BETWEEN '$date_from' AND '$date_to'
                GROUP BY DATE(order_date)
                ORDER BY DATE(order_date) ASC";
}

$chart_result = mysqli_query($conn, $chart_sql);
$chart_data = [];
while ($row = mysqli_fetch_assoc($chart_result)) {
    $chart_data[] = $row;
}

// ============================================================
// GET TOP BUSINESSES BY REVENUE
// ============================================================
$top_businesses_sql = "SELECT 
                            b.business_name,
                            COUNT(o.order_id) as order_count,
                            SUM(o.grand_total) as revenue
                        FROM orders o
                        JOIN businesses b ON o.business_id = b.business_id
                        WHERE o.status = 'delivered'
                        AND DATE(o.order_date) BETWEEN '$date_from' AND '$date_to'
                        GROUP BY b.business_id
                        ORDER BY revenue DESC
                        LIMIT 5";
$top_businesses_result = mysqli_query($conn, $top_businesses_sql);
$top_businesses = [];
while ($row = mysqli_fetch_assoc($top_businesses_result)) {
    $top_businesses[] = $row;
}

// ============================================================
// GET DAILY AVERAGE
// ============================================================
$days_diff = (strtotime($date_to) - strtotime($date_from)) / (60 * 60 * 24) + 1;
$daily_avg = $days_diff > 0 ? $total_revenue / $days_diff : 0;

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Financial Report | Admin</title>
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
        
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .payment-card {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s;
        }
        .payment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .payment-card .p-name {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .payment-card .p-amount {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .payment-card .p-count {
            font-size: 11px;
            color: #94a3b8;
        }
        
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
        .top-info .name { font-weight: 600; font-size: 13px; }
        .top-info .detail { font-size: 11px; color: #64748b; }
        .top-amount { font-weight: 700; font-size: 14px; color: #e67e22; }
        
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
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
            .payment-grid { grid-template-columns: repeat(2, 1fr); }
            .top-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .payment-grid { grid-template-columns: repeat(2, 1fr); }
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
            .payment-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="report-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-coins"></i> Financial Report</h1>
            <p><?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
        </div>
        <div class="header-actions">
            <a href="export.php?type=financial&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" class="btn-export">
                <i class="fas fa-file-download"></i> Export CSV
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Total Revenue</div>
            <div class="value orange">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="sub-text">From delivered orders</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-truck"></i> Delivery Fees</div>
            <div class="value blue">TSh <?php echo number_format($total_delivery_fee); ?></div>
            <div class="sub-text">Total delivery charges</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-clock"></i> Pending Revenue</div>
            <div class="value purple">TSh <?php echo number_format($pending_revenue); ?></div>
            <div class="sub-text">Awaiting delivery</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-times-circle"></i> Cancelled</div>
            <div class="value red">TSh <?php echo number_format($cancelled_revenue); ?></div>
            <div class="sub-text">Cancelled orders</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-chart-line"></i> Daily Average</div>
            <div class="value green">TSh <?php echo number_format($daily_avg); ?></div>
            <div class="sub-text">Revenue per day</div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="chart-section">
        <div class="chart-header">
            <h3><i class="fas fa-chart-bar"></i> Revenue Trends</h3>
            <div class="period-btns">
                <a href="?period=daily&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" 
                   class="period-btn <?php echo $period === 'daily' ? 'active' : ''; ?>">Daily</a>
                <a href="?period=weekly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" 
                   class="period-btn <?php echo $period === 'weekly' ? 'active' : ''; ?>">Weekly</a>
                <a href="?period=monthly&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>" 
                   class="period-btn <?php echo $period === 'monthly' ? 'active' : ''; ?>">Monthly</a>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Payment Methods Breakdown -->
    <div style="margin-bottom: 25px;">
        <h3 style="font-size:16px; font-weight:700; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-credit-card" style="color:#e67e22;"></i> Payment Methods
        </h3>
        <?php if (!empty($payment_breakdown)): ?>
        <div class="payment-grid">
            <?php foreach ($payment_breakdown as $p): ?>
            <div class="payment-card">
                <div class="p-name"><?php echo strtoupper($p['payment_method'] ?? 'Unknown'); ?></div>
                <div class="p-amount">TSh <?php echo number_format($p['total_amount']); ?></div>
                <div class="p-count"><?php echo $p['order_count']; ?> orders</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="background:white; border-radius:16px; padding:20px; text-align:center; color:#94a3b8; border:1px solid #e2e8f0;">
            No payment data available
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Businesses -->
    <?php if (!empty($top_businesses)): ?>
    <div class="top-grid">
        <div class="top-card">
            <div class="top-card-header"><i class="fas fa-trophy"></i> Top Businesses by Revenue</div>
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
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="financial.php?period=<?php echo $period; ?>" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // REVENUE CHART
    // ============================================================
    var ctx = document.getElementById('revenueChart').getContext('2d');
    
    var chartData = <?php echo json_encode($chart_data); ?>;
    var labels = chartData.map(function(item) { return item.label; });
    var revenues = chartData.map(function(item) { return item.revenue; });
    var orders = chartData.map(function(item) { return item.orders; });
    
    if (chartData.length > 0) {
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
                        data: orders,
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
    }
});
</script>

</body>
</html>