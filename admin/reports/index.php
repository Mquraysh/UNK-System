<?php
// admin/reports/index.php - Reports Dashboard
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============================================================
// GET DASHBOARD STATISTICS
// ============================================================

// Total orders - FIXED: delivered instead of completed
$orders_result = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered FROM orders");
$orders_stats = mysqli_fetch_assoc($orders_result);

// Total customers
$customers_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM customers");
$total_customers = mysqli_fetch_assoc($customers_result)['total'];

// Total revenue - FIXED: delivered instead of completed
$revenue_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE status = 'delivered'");
$total_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;

// Total products
$products_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE is_available = 1");
$total_products = mysqli_fetch_assoc($products_result)['total'];

// Total businesses
$businesses_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM businesses WHERE is_active = 1");
$total_businesses = mysqli_fetch_assoc($businesses_result)['total'];

// Today's orders
$today = date('Y-m-d');
$today_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE DATE(order_date) = '$today'");
$today_orders = mysqli_fetch_assoc($today_result)['total'] ?? 0;

// Monthly revenue - FIXED: delivered instead of completed
$month = date('Y-m');
$monthly_result = mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE DATE_FORMAT(order_date, '%Y-%m') = '$month' AND status = 'delivered'");
$monthly_revenue = mysqli_fetch_assoc($monthly_result)['total'] ?? 0;

// Pending orders
$pending_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE status = 'pending'");
$pending_orders = mysqli_fetch_assoc($pending_result)['total'] ?? 0;

// Pending deliveries
$deliveries_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM deliveries WHERE status IN ('assigned', 'picked_up', 'in_transit')");
$pending_deliveries = mysqli_fetch_assoc($deliveries_result)['total'] ?? 0;

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Reports Dashboard | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
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
        
        /* Stats Grid - Simple & Clean */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        .stat-card .stat-icon {
            float: right;
            font-size: 28px;
            color: #e67e22;
            opacity: 0.7;
        }
        .stat-card .stat-sub {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f1f5f9;
        }
        .stat-card .stat-sub .text-success { color: #10b981; }
        .stat-card .stat-sub .text-warning { color: #f59e0b; }
        .stat-card .stat-sub .text-danger { color: #ef4444; }
        
        /* Report Grid */
        .report-section-title {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .report-section-title i { color: #e67e22; }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .report-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            text-decoration: none;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .report-card i {
            font-size: 36px;
            color: #e67e22;
            margin-bottom: 12px;
            display: block;
        }
        .report-card h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .report-card p {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .report-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .report-grid { grid-template-columns: 1fr; }
            .page-header h1 { font-size: 22px; }
            .stat-card .stat-number { font-size: 22px; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="report-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-pie"></i> Reports Dashboard</h1>
            <p>Overview of your business performance and key metrics</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-number"><?php echo number_format($orders_stats['total']); ?></div>
            <div class="stat-label">Total Orders</div>
            <div class="stat-sub">
                <span class="text-success"><i class="fas fa-check-circle"></i> <?php echo number_format($orders_stats['delivered']); ?> delivered</span>
                <span class="text-warning" style="margin-left:10px;"><i class="fas fa-clock"></i> <?php echo number_format($pending_orders); ?> pending</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?php echo number_format($total_customers); ?></div>
            <div class="stat-label">Total Customers</div>
            <div class="stat-sub">Registered users on platform</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
            <div class="stat-sub">
                <span class="text-success"><i class="fas fa-arrow-up"></i> TSh <?php echo number_format($monthly_revenue); ?> this month</span>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-number"><?php echo number_format($total_products); ?></div>
            <div class="stat-label">Active Products</div>
            <div class="stat-sub">
                <span class="text-warning"><i class="fas fa-store"></i> <?php echo number_format($total_businesses); ?> businesses</span>
                <span class="text-danger" style="margin-left:10px;"><i class="fas fa-truck"></i> <?php echo number_format($pending_deliveries); ?> pending deliveries</span>
            </div>
        </div>
    </div>

    <!-- Report Quick Links -->
    <div class="report-section-title">
        <i class="fas fa-file-alt"></i> Report Modules
    </div>
    <div class="report-grid">
        <a href="sales.php" class="report-card">
            <i class="fas fa-chart-line"></i>
            <h3>Sales Report</h3>
            <p>Sales performance, revenue trends, and top products</p>
        </a>
        <a href="orders.php" class="report-card">
            <i class="fas fa-shopping-bag"></i>
            <h3>Orders Report</h3>
            <p>Order patterns, status distribution, and order values</p>
        </a>
        <a href="customers.php" class="report-card">
            <i class="fas fa-user-friends"></i>
            <h3>Customers Report</h3>
            <p>Customer demographics and purchasing behavior</p>
        </a>
        <a href="delivery.php" class="report-card">
            <i class="fas fa-truck"></i>
            <h3>Delivery Report</h3>
            <p>Delivery performance and agent efficiency</p>
        </a>
        <a href="inventory.php" class="report-card">
            <i class="fas fa-warehouse"></i>
            <h3>Inventory Report</h3>
            <p>Stock levels, low stock alerts, and product performance</p>
        </a>
        <a href="financial.php" class="report-card">
            <i class="fas fa-coins"></i>
            <h3>Financial Report</h3>
            <p>Revenue breakdown, profit analysis, and summaries</p>
        </a>
    </div>
</div>
</body>
</html>