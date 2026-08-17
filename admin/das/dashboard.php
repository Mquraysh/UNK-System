<?php
// admin/dashboard.php - 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = htmlspecialchars($_SESSION['full_name'] ?? 'Admin');

// STATISTICS (prepared statements)
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM users");
mysqli_stmt_execute($stmt);
$total_users = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM customers");
mysqli_stmt_execute($stmt);
$total_customers = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM businesses");
mysqli_stmt_execute($stmt);
$total_businesses = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM businesses WHERE is_verified = 0");
mysqli_stmt_execute($stmt);
$pending_businesses = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM delivery_agents");
mysqli_stmt_execute($stmt);
$total_delivery_agents = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM products");
mysqli_stmt_execute($stmt);
$total_products = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM orders");
mysqli_stmt_execute($stmt);
$total_orders = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
mysqli_stmt_execute($stmt);
$pending_orders = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM deliveries");
mysqli_stmt_execute($stmt);
$total_deliveries = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['count'] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT SUM(grand_total) as total FROM orders WHERE status = 'delivered'");
mysqli_stmt_execute($stmt);
$total_revenue = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

// Monthly revenue for current year
$year = date('Y');
$stmt = mysqli_prepare($conn, "
    SELECT DATE_FORMAT(order_date, '%M') as month, SUM(grand_total) as total
    FROM orders
    WHERE status = 'delivered' AND YEAR(order_date) = ?
    GROUP BY MONTH(order_date)
    ORDER BY MONTH(order_date) ASC
");
mysqli_stmt_bind_param($stmt, 'i', $year);
mysqli_stmt_execute($stmt);
$revenue_result = mysqli_stmt_get_result($stmt);
$months = [];
$revenues = [];
while ($row = mysqli_fetch_assoc($revenue_result)) {
    $months[] = $row['month'];
    $revenues[] = (float)$row['total'];
}
mysqli_stmt_close($stmt);

// Top selling products
$top_products = [];
$stmt = mysqli_prepare($conn, "
    SELECT p.name, p.price, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");
mysqli_stmt_execute($stmt);
$top_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($top_res)) {
    $top_products[] = $row;
}
mysqli_stmt_close($stmt);

// Recent orders
$recent_orders = [];
$stmt = mysqli_prepare($conn, "
    SELECT o.order_id, o.grand_total, o.status, o.order_date,
           c.first_name, c.last_name, b.business_name
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN businesses b ON o.business_id = b.business_id
    ORDER BY o.order_date DESC
    LIMIT 10
");
mysqli_stmt_execute($stmt);
$orders_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($orders_res)) {
    $recent_orders[] = $row;
}
mysqli_stmt_close($stmt);

// Recent businesses
$recent_businesses = [];
$stmt = mysqli_prepare($conn, "
    SELECT b.business_id, b.business_name, b.is_verified, b.created_at,
           u.email, u.phone
    FROM businesses b
    JOIN users u ON b.user_id = u.user_id
    ORDER BY b.created_at DESC
    LIMIT 5
");
mysqli_stmt_execute($stmt);
$biz_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($biz_res)) {
    $recent_businesses[] = $row;
}
mysqli_stmt_close($stmt);

// Include admin sidebar
include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Admin Dashboard | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        /* Main content area – adapts to sidebar */
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2rem;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        /* Welcome section – no background, clean border */
        .welcome-section {
            background: transparent;
            padding: 0 0 1.5rem 0;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .welcome-text h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .welcome-text h1 span { color: #e67e22; }
        .welcome-text p { color: #64748b; font-size: 0.85rem; }
        .date-badge {
            background: #f8fafc;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        /* Stats grid – responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s;
            border: 1px solid #eef2f8;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .stat-info h3 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }
        .stat-info p {
            color: #64748b;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(230,126,34,0.1);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon i { font-size: 1.5rem; color: #e67e22; }
        /* Pending alert */
        .pending-card {
            background: #fffbeb;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-left: 4px solid #f59e0b;
        }
        .pending-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #d97706;
        }
        .pending-info p { color: #92400e; font-size: 0.8rem; }
        .pending-btn {
            background: #f59e0b;
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .pending-btn:hover { background: #e67e22; transform: translateY(-2px); }
        /* Charts row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }
        .chart-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
        }
        .chart-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
        }
        .chart-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .chart-header h3 i { color: #e67e22; }
        .chart-body { padding: 1rem; }
        canvas { max-height: 260px; width: 100%; }
        /* Tables */
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-header a {
            color: #e67e22;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .data-table tr:hover td { background: #fffaf5; cursor: pointer; }
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-active { background: #d1fae5; color: #059669; }
        .rank-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #f8fafc;
            border-radius: 0.5rem;
            text-align: center;
            line-height: 24px;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .rank-1 { background: #fef3c7; color: #d97706; }
        .rank-2 { background: #e5e7eb; color: #6b7280; }
        .rank-3 { background: #fed7aa; color: #c2410c; }
        /* Mobile adjustments */
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 640px) {
            .admin-content { padding: 1rem; }
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .date-badge { white-space: normal; }
            .stats-grid { grid-template-columns: 1fr; }
            .charts-row { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
            .data-table th, .data-table td { padding: 0.6rem; font-size: 0.75rem; }
        }
        .empty-row td { text-align: center; padding: 2rem; color: #94a3b8; }
    </style>
</head>
<body>
<div class="admin-content">
    <!-- Welcome Section (no background) -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Welcome back, <span><?= $admin_name ?></span></h1>
            <p>Here's what's happening with your platform today</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i> <?= date('l, F d, Y') ?>
        </div>
    </div>

    <!-- Stats Row 1 -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='../users/index.php'">
            <div class="stat-info"><h3><?= number_format($total_users) ?></h3><p><i class="fas fa-users"></i> Total Users</p></div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../customers/index.php'">
            <div class="stat-info"><h3><?= number_format($total_customers) ?></h3><p><i class="fas fa-user"></i> Customers</p></div>
            <div class="stat-icon"><i class="fas fa-user"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../businesses/index.php'">
            <div class="stat-info"><h3><?= number_format($total_businesses) ?></h3><p><i class="fas fa-store"></i> Businesses</p></div>
            <div class="stat-icon"><i class="fas fa-store"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../delivery_agent/agents.php'">
            <div class="stat-info"><h3><?= number_format($total_delivery_agents) ?></h3><p><i class="fas fa-truck"></i> Delivery Agents</p></div>
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
        </div>
    </div>

    <!-- Stats Row 2 -->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='../products/index.php'">
            <div class="stat-info"><h3><?= number_format($total_products) ?></h3><p><i class="fas fa-box"></i> Total Products</p></div>
            <div class="stat-icon"><i class="fas fa-box"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../orders/index.php'">
            <div class="stat-info"><h3><?= number_format($total_orders) ?></h3><p><i class="fas fa-shopping-cart"></i> Total Orders</p></div>
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../orders/index.php?status=pending'">
            <div class="stat-info"><h3><?= number_format($pending_orders) ?></h3><p><i class="fas fa-clock"></i> Pending Orders</p></div>
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
        </div>
        <div class="stat-card" onclick="location.href='../reports/index.php'">
            <div class="stat-info"><h3>TSh <?= number_format($total_revenue) ?></h3><p><i class="fas fa-chart-line"></i> Total Revenue</p></div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    <!-- Pending Businesses Alert -->
    <?php if ($pending_businesses > 0): ?>
    <div class="pending-card">
        <div class="pending-info">
            <h3><i class="fas fa-clock"></i> <?= $pending_businesses ?> Businesses Pending Verification</h3>
            <p>Review and verify new business registrations</p>
        </div>
        <a href="../businesses/index.php?filter=pending" class="pending-btn"><i class="fas fa-check-circle"></i> Review Now</a>
    </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="charts-row">
        <div class="chart-card">
            <div class="chart-header"><h3><i class="fas fa-chart-line"></i> Monthly Revenue (<?= $year ?>)</h3></div>
            <div class="chart-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-header"><h3><i class="fas fa-fire"></i> Top Selling Products</h3></div>
            <div class="table-container">
                <table class="data-table">
                    <thead><tr><th>#</th><th>Product Name</th><th>Units Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                        <?php if (empty($top_products)): ?>
                            <tr><td colspan="4" class="empty-row">No sales data yet</a></tr>
                        <?php else: $rank = 1; foreach ($top_products as $p): ?>
                            <tr>
                                <td><span class="rank-badge <?= $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : '')) ?>"><?= $rank ?></span></td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><?= $p['total_sold'] ?> units</a>
                                <td>TSh <?= number_format($p['price'] * $p['total_sold']) ?></td>
                            </tr>
                        <?php $rank++; endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-clock"></i> Recent Orders</h3>
            <a href="../orders/index.php">View All Orders <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Order ID</th><th>Customer</th><th>Business</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_orders)): ?>
                        <td><td colspan="6" class="empty-row">No orders found</a></tr>
                    <?php else: foreach ($recent_orders as $o): ?>
                        <tr onclick="location.href='../orders/index.php?id=<?= $o['order_id'] ?>'">
                            <td style="font-weight:600; color:#e67e22;"><?= $o['order_id'] ?></td>
                            <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                            <td><?= htmlspecialchars($o['business_name']) ?></td>
                            <td>TSh <?= number_format($o['grand_total']) ?></td>
                            <td><span class="status-badge status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($o['order_date'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Businesses Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-store"></i> Recently Registered Businesses</h3>
            <a href="../businesses/index.php">View All Businesses <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Business Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Registered</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_businesses)): ?>
                        <tr><td colspan="5" class="empty-row">No businesses found</a></tr>
                    <?php else: foreach ($recent_businesses as $b): ?>
                        <tr onclick="location.href='../businesses/index.php?id=<?= $b['business_id'] ?>'">
                            <td><strong><?= htmlspecialchars($b['business_name']) ?></strong></td>
                            <td><?= htmlspecialchars($b['email']) ?></td>
                            <td><?= htmlspecialchars($b['phone']) ?></td>
                            <td><span class="status-badge <?= $b['is_verified'] ? 'status-active' : 'status-pending' ?>"><?= $b['is_verified'] ? 'Verified' : 'Pending' ?></span></td>
                            <td><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
<?php if (!empty($months) && !empty($revenues)): ?>
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Revenue (TSh)',
                data: <?= json_encode($revenues) ?>,
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
            plugins: {
                tooltip: { callbacks: { label: ctx => 'TSh ' + ctx.raw.toLocaleString() } }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => 'TSh ' + v.toLocaleString() } } }
        }
    });
<?php endif; ?>
</script>
</body>
</html>