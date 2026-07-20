<?php
// business/das/dashboard.php 
require_once dirname(__DIR__, 2) . '/config/database.php'; 
session_start();

// Adjust base URL to your 
$base_url = '/UNK-System2'; // Example: '/UNK-System2' or '' if in root

// Access control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch business data using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$businessResult = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($businessResult);
mysqli_stmt_close($stmt);

if (!$business) {
    header("Location: register.php");
    exit();
}

$businessId = $business['business_id'];

// safe image URL using absolute path 
function getProductImage($imagePath, $base_url) {
    if (empty($imagePath)) return $base_url . '/assets/images/default-product.jpg';
    if (preg_match('/^https?:\/\//i', $imagePath)) return $imagePath;
    if ($imagePath[0] === '/') return $imagePath;
    // If path starts with 'assets/' or similar, prepend base URL
    return $base_url . '/' . ltrim($imagePath, './');
}

// 1. STATISTICS (using prepared statements)
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM products WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$productCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM orders WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$orderCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM orders WHERE business_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$pendingCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = ? AND status = 'delivered'");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$totalRevenue = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

// Last month revenue for growth
$lastMonth = date('Y-m', strtotime('-1 month'));
$stmt = mysqli_prepare($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = ? AND status = 'delivered' AND DATE_FORMAT(order_date, '%Y-%m') = ?");
mysqli_stmt_bind_param($stmt, "is", $businessId, $lastMonth);
mysqli_stmt_execute($stmt);
$lastMonthRevenue = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);
$revenueGrowth = $lastMonthRevenue > 0 ? round((($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

// Stock alerts
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM products WHERE business_id = ? AND quantity_in_stock < 10 AND quantity_in_stock > 0");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$lowStock = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM products WHERE business_id = ? AND quantity_in_stock = 0");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$outStock = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
mysqli_stmt_close($stmt);

// 2. RECENT ORDERS (limit 10)
$recentOrders = [];
$stmt = mysqli_prepare($conn, "
    SELECT o.*, c.first_name, c.last_name 
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE o.business_id = ?
    ORDER BY o.order_date DESC LIMIT 10
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$ordersRes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($ordersRes)) {
    $recentOrders[] = $row;
}
mysqli_stmt_close($stmt);

// 3. RECENT PRODUCTS (limit 5)
$recentProducts = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE business_id = ? ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$productsRes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($productsRes)) {
    $recentProducts[] = $row;
}
mysqli_stmt_close($stmt);

// 4. TOP SELLING PRODUCTS (from order_items)
$topProducts = [];
$stmt = mysqli_prepare($conn, "
    SELECT p.product_id, p.name, p.price, p.image_url, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.business_id = ? AND o.status = 'delivered'
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$topRes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($topRes)) {
    $topProducts[] = $row;
}
mysqli_stmt_close($stmt);

// 5. LOW STOCK PRODUCTS (quick list)
$lowStockProducts = [];
$stmt = mysqli_prepare($conn, "
    SELECT product_id, name, quantity_in_stock, price, unit
    FROM products
    WHERE business_id = ? AND quantity_in_stock < 10 AND quantity_in_stock > 0
    ORDER BY quantity_in_stock ASC LIMIT 5
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$lowRes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($lowRes)) {
    $lowStockProducts[] = $row;
}
mysqli_stmt_close($stmt);

// 6. CUSTOMER RATING SUMMARY (if reviews table exists)
$avgRating = 0;
$totalReviews = 0;
$ratingTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'reviews'");
if (mysqli_num_rows($ratingTableCheck) > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT AVG(r.rating) as avg_rating, COUNT(r.review_id) as total_reviews
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        WHERE p.business_id = ? AND r.status = 'approved'
    ");
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($stmt);
    $ratingRes = mysqli_stmt_get_result($stmt);
    if ($ratingData = mysqli_fetch_assoc($ratingRes)) {
        $avgRating = round($ratingData['avg_rating'], 1);
        $totalReviews = (int)$ratingData['total_reviews'];
    }
    mysqli_stmt_close($stmt);
}

// 7. MONTHLY SALES FOR CHART (last 6 delivered months)
$monthlySales = [];
$stmt = mysqli_prepare($conn, "
    SELECT DATE_FORMAT(order_date, '%M') as month, SUM(grand_total) as total
    FROM orders
    WHERE business_id = ? AND status = 'delivered'
    GROUP BY MONTH(order_date)
    ORDER BY MONTH(order_date) DESC LIMIT 6
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$salesRes = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($salesRes)) {
    $monthlySales[] = $row;
}
mysqli_stmt_close($stmt);
$months = array_reverse(array_column($monthlySales, 'month'));
$sales  = array_reverse(array_column($monthlySales, 'total'));

// Flash messages
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType    = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Include sidebar with corrected path
require_once '../includes/business_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Business Dashboard | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* (CSS unchanged – same as provided) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        @import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700&display=swap');
        .dashboard-main {
            margin-left: 280px;
            padding: 32px 36px;
            transition: all 0.2s;
        }
        .alert {
            padding: 16px 24px;
            border-radius: 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            border-left: 5px solid;
        }
        .alert-success { background: #e6f7ec; color: #0e6b2f; border-left-color: #10b981; }
        .alert-danger { background: #fee9e6; color: #b91c1c; border-left-color: #ef4444; }
        .stock-banner {
            background: linear-gradient(105deg, #fffbeb 0%, #fef3c7 100%);
            border-radius: 24px;
            padding: 18px 28px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            border: 1px solid #fed7aa;
        }
        .stock-banner .message { display: flex; align-items: center; gap: 14px; font-weight: 500; color: #92400e; }
        .btn-warning { background: #f59e0b; color: white; padding: 10px 24px; border-radius: 40px; text-decoration: none; font-size: 13px; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .page-header { margin-bottom: 32px; }
        .page-header h1 { font-size: 28px; font-weight: 700; background: linear-gradient(135deg, #1e293b, #2c3e50); -webkit-background-clip: text; background-clip: text; color: transparent; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; background: none; }
        .page-header p { color: #6b7280; margin-top: 6px; font-size: 14px; }

        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 40px; }
        .stat-card {
            background: white; border-radius: 28px; padding: 20px; display: flex; justify-content: space-between;
            align-items: center; cursor: pointer; transition: all 0.3s; box-shadow: 0 8px 20px rgba(0,0,0,0.02);
            border: 1px solid rgba(230,126,34,0.1); position: relative; overflow: hidden;
        }
        
        .stat-card:hover { transform: translateY(-6px); box-shadow: 0 25px 35px -12px rgba(0,0,0,0.15); border-color:  #e67e22; }
        .stat-info h3 { font-size: 34px; font-weight: 800; background: linear-gradient(135deg, #1e293b, #2d3e50); -webkit-background-clip: text; background-clip: text; color: transparent; margin-bottom: 6px; }
        .stat-info p { color: #6c757d; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 6px; }
        .stat-info p i { color: #e67e22; }
        .stat-icon { width: 60px; height: 60px; background: linear-gradient(145deg, #fff7f0, #fff0e6); border-radius: 32px; display: flex; align-items: center; justify-content: center; transition: 0.3s; }
        .stat-card:hover .stat-icon { background: linear-gradient(145deg, #e67e22, #d35400); transform: scale(1.05); }
        .stat-card:hover .stat-icon i { color: white; transform: scale(1.1); }
        .stat-icon i { font-size: 30px; color: #e67e22; }
        .revenue-trend { font-size: 12px; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .two-columns { display: grid; grid-template-columns: 360px 1fr; gap: 28px; margin-bottom: 40px; }
        .card { background: white; border-radius: 28px; border: 1px solid #eef2f8; padding: 24px; }
        .card-header { font-size: 18px; font-weight: 700; padding-bottom: 14px; border-bottom: 2px solid #e67e22; display: inline-block; margin-bottom: 20px; }
        .card-header i { color: #e67e22; margin-right: 10px; }
        .quick-actions { display: flex; flex-direction: column; gap: 14px; }
        .action-btn {
            display: flex; align-items: center; gap: 16px; padding: 16px 20px; border-radius: 20px;
            text-decoration: none; transition: 0.2s; color: white;
        }
        .action-btn .btn-icon { width: 44px; height: 44px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .action-btn .btn-text { flex: 1; }
        .action-btn .btn-title { font-weight: 700; font-size: 15px; display: block; }
        .action-btn .btn-desc { font-size: 12px; opacity: 0.8; }
        .action-btn:hover { transform: translateX(6px); }
        .btn-add { background: linear-gradient(105deg, #059669, #047857); }
        .btn-orders { background: linear-gradient(105deg, #2563eb, #1d4ed8); }
        .btn-inventory { background: linear-gradient(105deg, #ea580c, #c2410c); }
        .btn-reports { background: linear-gradient(105deg, #7c3aed, #6d28d9); }
        .three-columns { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; margin-bottom: 40px; }
        .compact-card { background: white; border-radius: 24px; border: 1px solid #eef2f8; padding: 20px; }
        .compact-card h4 { font-size: 14px; font-weight: 600; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border-left: 3px solid #e67e22; padding-left: 12px; }
        .product-list { list-style: none; }
        .product-list li { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .product-list li:last-child { border-bottom: none; }
        .rating-big { text-align: center; padding: 16px 0; }
        .rating-number { font-size: 48px; font-weight: 800; color: #f39c12; }
        .rating-stars { color: #f39c12; margin: 8px 0; }
        .view-link { color: #e67e22; text-decoration: none; font-size: 12px; font-weight: 500; }
        .table-wrapper { background: white; border-radius: 28px; border: 1px solid #eef2f8; overflow: hidden; margin-bottom: 32px; }
        .table-header { padding: 20px 28px; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .table-header a { color: #e67e22; text-decoration: none; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 16px 20px; background: #fafcff; font-weight: 600; font-size: 12px; color: #4b5563; border-bottom: 1px solid #edf2f7; }
        .data-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover { background: #fefcf8; }
        .product-thumb { width: 60px; height: 60px; background: #f3f4f6; border-radius: 16px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .product-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 40px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #e0f2e9; color: #0a5c3e; }
        .badge-warning { background: #fff3e3; color: #b45309; }
        .badge-danger { background: #fee9e6; color: #b91c1c; }
        .btn-sm { background: #e67e22; color: white; padding: 6px 16px; border-radius: 30px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-sm:hover { background: #c2410c; transform: translateY(-2px); }
        .empty-row td { text-align: center; padding: 48px 20px; color: #9ca3af; }
        @media (max-width: 1100px) { .two-columns, .three-columns { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .dashboard-main { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: 1fr 1fr; gap: 16px; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="dashboard-main">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType ?>">
            <i class="fas fa-<?= $flashType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flashMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($lowStock > 0 || $outStock > 0): ?>
        <div class="stock-banner">
            <div class="message"><i class="fas fa-exclamation-triangle"></i> <span><strong>Stock alert</strong> – <?= $lowStock ?> low stock, <?= $outStock ?> out of stock.</span></div>
            <a href="<?= $base_url ?>/business/inventory/index.php" class="btn-warning"><i class="fas fa-warehouse"></i> Manage inventory</a>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-store"></i> <?= htmlspecialchars($business['business_name']) ?></h1>
        <p><i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y') ?> – Business performance at a glance</p>
    </div>

    <!-- Stats Cards (absolute links) -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='<?= $base_url ?>/business/products/index.php'">
            <div class="stat-info"><h3><?= $productCount ?></h3><p><i class="fas fa-box"></i> Total Products</p></div>
            <div class="stat-icon"><i class="fas fa-box"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='<?= $base_url ?>/business/orders/index.php'">
            <div class="stat-info"><h3><?= number_format($orderCount) ?></h3><p><i class="fas fa-shopping-cart"></i> Total Orders</p></div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='<?= $base_url ?>/business/orders/index.php?status=pending'">
            <div class="stat-info"><h3><?= $pendingCount ?></h3><p><i class="fas fa-clock"></i> Pending Orders</p></div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='<?= $base_url ?>/business/reports/index.php'">
            <div class="stat-info">
                <h3><?= number_format($totalRevenue) ?> TSh</h3>
                <p><i class="fas fa-money-bill-wave"></i> Total Revenue</p>
                <div class="revenue-trend <?= $revenueGrowth >= 0 ? 'trend-up' : 'trend-down' ?>">
                    <i class="fas fa-arrow-<?= $revenueGrowth >= 0 ? 'up' : 'down' ?>"></i> <?= abs($revenueGrowth) ?>% vs last month
                </div>
            </div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    <!-- Quick Actions + Monthly Sales -->
    <div class="two-columns">
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick actions</div>
            <div class="quick-actions">
                <a href="<?= $base_url ?>/business/products/add.php" class="action-btn btn-add"><span class="btn-icon"><i class="fas fa-plus"></i></span><span class="btn-text"><span class="btn-title">Add product</span><span class="btn-desc">List new item</span></span><i class="fas fa-arrow-right"></i></a>
                <a href="<?= $base_url ?>/business/orders/index.php" class="action-btn btn-orders"><span class="btn-icon"><i class="fas fa-shopping-cart"></i></span><span class="btn-text"><span class="btn-title">View orders</span><span class="btn-desc">Manage customer purchases</span></span><i class="fas fa-arrow-right"></i></a>
                <a href="<?= $base_url ?>/business/inventory/index.php" class="action-btn btn-inventory"><span class="btn-icon"><i class="fas fa-warehouse"></i></span><span class="btn-text"><span class="btn-title">Inventory</span><span class="btn-desc">Update stock levels</span></span><i class="fas fa-arrow-right"></i></a>
                <a href="<?= $base_url ?>/business/reports/index.php" class="action-btn btn-reports"><span class="btn-icon"><i class="fas fa-chart-bar"></i></span><span class="btn-text"><span class="btn-title">Reports</span><span class="btn-desc">Sales analytics</span></span><i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line"></i> Monthly sales (delivered)</div>
            <?php if (!empty($months) && !empty($sales)): ?>
                <canvas id="salesChart" style="height: 260px; width: 100%;"></canvas>
            <?php else: ?>
                <div style="text-align:center; padding: 48px 20px; color:#9ca3af;"><i class="fas fa-chart-simple"></i> No sales data yet</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Extra widgets -->
    <div class="three-columns">
        <div class="compact-card">
            <h4><i class="fas fa-trophy"></i> Best Sellers</h4>
            <?php if (empty($topProducts)): ?>
                <p class="empty-row" style="padding: 20px; text-align:center;">No sales yet</p>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach ($topProducts as $tp): ?>
                        <li><span><?= htmlspecialchars($tp['name']) ?></span><span><strong><?= $tp['total_sold'] ?> sold</strong></span></li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top: 12px; text-align:right;"><a href="<?= $base_url ?>/business/reports/sales.php" class="view-link">View all reports →</a></div>
            <?php endif; ?>
        </div>
        <div class="compact-card">
            <h4><i class="fas fa-exclamation-triangle"></i> Low Stock ( < 10 )</h4>
            <?php if (empty($lowStockProducts)): ?>
                <p class="empty-row" style="padding: 20px; text-align:center;">All products have sufficient stock</p>
            <?php else: ?>
                <ul class="product-list">
                    <?php foreach ($lowStockProducts as $lsp): ?>
                        <li><span><?= htmlspecialchars($lsp['name']) ?></span><span><strong><?= $lsp['quantity_in_stock'] ?> <?= $lsp['unit'] ?>s</strong></span></li>
                    <?php endforeach; ?>
                </ul>
                <div style="margin-top: 12px; text-align:right;"><a href="<?= $base_url ?>/business/inventory/index.php" class="view-link">Manage inventory →</a></div>
            <?php endif; ?>
        </div>
        <div class="compact-card">
            <h4><i class="fas fa-star"></i> Customer Feedback</h4>
            <?php if ($totalReviews > 0): ?>
                <div class="rating-big">
                    <div class="rating-number"><?= $avgRating ?></div>
                    <div class="rating-stars"><?php for ($i = 1; $i <= 5; $i++): ?><i class="fas fa-star<?= $i <= round($avgRating) ? '' : '-o' ?>"></i><?php endfor; ?></div>
                    <p style="font-size: 13px; color:#64748b;">Based on <?= $totalReviews ?> reviews</p>
                </div>
                <div style="text-align:center; margin-top: 8px;"><a href="<?= $base_url ?>/business/reviews/index.php" class="view-link">Read all reviews →</a></div>
            <?php else: ?>
                <div class="rating-big"><div class="rating-number">—</div><p style="font-size: 13px; color:#64748b;">No reviews yet</p></div>
                <div style="text-align:center;"><a href="<?= $base_url ?>/business/reviews/index.php" class="view-link">Manage reviews →</a></div>
            <?php endif; ?>
        </div>
    </div>

   <!-- Recent Orders Table -->
<div class="table-wrapper">
    <div class="table-header">
        <h3><i class="fas fa-clock"></i> Recent orders</h3>
        <a href="<?= $base_url ?>/business/orders/index.php">All orders <i class="fas fa-arrow-right"></i></a>
    </div>
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
            <?php if (count($recentOrders)): ?>
                <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><strong class="order-id"><?= $order['order_id'] ?></strong></td>
                        <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></td>
                        <td><?= number_format($order['grand_total']) ?> TSh</td>
                        <td>
                            <span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                <i class="fas fa-<?= $order['status'] === 'delivered' ? 'check-circle' : ($order['status'] === 'pending' ? 'clock' : 'times-circle') ?>"></i>
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, H:i', strtotime($order['order_date'])) ?></td>
                        <td><a href="<?= $base_url ?>/business/orders/details.php?id=<?= $order['order_id'] ?>" class="btn-sm"><i class="fas fa-eye"></i> View</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr class="empty-row"><td colspan="6"><i class="fas fa-inbox"></i> No orders yet</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

    <!-- Recent Products Table -->
    <div class="table-wrapper">
        <div class="table-header">
            <h3><i class="fas fa-box"></i> Recent products</h3>
            <a href="<?= $base_url ?>/business/products/index.php">All products <i class="fas fa-arrow-right"></i></a>
        </div>
        <table class="data-table">
            <thead><tr><th>Image</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php if (count($recentProducts)): ?>
                    <?php foreach ($recentProducts as $product): ?>
                        <tr>
                            <td><div class="product-thumb"><img src="<?= getProductImage($product['image_url'] ?? '', $base_url) ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='<?= $base_url ?>/assets/images/default-product.jpg'"></div></td>
                            <td><strong><?= htmlspecialchars($product['name']) ?></strong></td>
                            <td style="color:#e67e22; font-weight:600;"><?= number_format($product['price']) ?> TSh</a>
                            <td>
                                <?= $product['quantity_in_stock'] ?> <?= $product['unit'] ?>s
                                <?php if ($product['quantity_in_stock'] < 10 && $product['quantity_in_stock'] > 0): ?>
                                    <span class="badge badge-warning" style="margin-left:6px;"><i class="fas fa-exclamation-triangle"></i> Low</span>
                                <?php elseif ($product['quantity_in_stock'] <= 0): ?>
                                    <span class="badge badge-danger" style="margin-left:6px;"><i class="fas fa-times-circle"></i> Out</span>
                                <?php endif; ?>
                             </a>
                            <td><span class="badge badge-<?= $product['is_available'] ? 'success' : 'danger' ?>"><i class="fas fa-<?= $product['is_available'] ? 'check-circle' : 'times-circle' ?>"></i> <?= $product['is_available'] ? 'Active' : 'Inactive' ?></span></td>
                            <td><a href="<?= $base_url ?>/business/products/edit.php?id=<?= $product['product_id'] ?>" class="btn-sm"><i class="fas fa-edit"></i> Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="empty-row"><td colspan="6"><i class="fas fa-box-open"></i> No products yet. <a href="<?= $base_url ?>/business/products/add.php" style="color:#e67e22;">Add your first product</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    <?php if (!empty($months) && !empty($sales)): ?>
    new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales (TSh)',
                data: <?= json_encode($sales) ?>,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230,126,34,0.03)',
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: '#fff',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { tooltip: { callbacks: { label: ctx => 'TSh ' + ctx.raw.toLocaleString() } } },
            scales: { y: { beginAtZero: true, ticks: { callback: val => 'TSh ' + val.toLocaleString() } } }
        }
    });
    <?php endif; ?>
</script>
</body>
</html>