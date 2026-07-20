<?php
// delivery/earnings/earnings.php - PROFESSIONAL EARNINGS WITH CHART.JS (FIXED)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get agent data - REMOVED total_deliveries from query
$agent_sql = "SELECT agent_id, first_name, last_name, phone, vehicle_type, vehicle_registration, is_available
              FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}
$agent_id = $agent['agent_id'];
$agent_name = $agent['first_name'] . ' ' . $agent['last_name'];

// ============================================================
// GET TOTAL DELIVERIES FROM DELIVERIES TABLE
// ============================================================
$total_deliveries_sql = "SELECT COUNT(*) as total FROM deliveries WHERE agent_id = '$agent_id'";
$total_deliveries_result = mysqli_query($conn, $total_deliveries_sql);
$total_deliveries = (int)(mysqli_fetch_assoc($total_deliveries_result)['total'] ?? 0);

// ============================================================
// GET AGENT RATING SUMMARY FROM DELIVERY_RATINGS TABLE
// ============================================================
$rating_sql = "SELECT 
                    AVG(rating) as avg_rating, 
                    COUNT(*) as total_ratings,
                    SUM(CASE WHEN rating >= 4 THEN 1 ELSE 0 END) as positive_ratings,
                    SUM(CASE WHEN rating <= 2 THEN 1 ELSE 0 END) as negative_ratings
                FROM delivery_ratings 
                WHERE agent_id = '$agent_id'";
$rating_result = mysqli_query($conn, $rating_sql);
$rating_data = mysqli_fetch_assoc($rating_result);
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$total_ratings = $rating_data['total_ratings'] ?? 0;
$positive_ratings = $rating_data['positive_ratings'] ?? 0;
$negative_ratings = $rating_data['negative_ratings'] ?? 0;

// ============================================================
// GET EARNINGS STATISTICS
// ============================================================

// Total earnings
$total_sql = "SELECT 
                    SUM(delivery_fee) as total, 
                    COUNT(*) as count,
                    AVG(delivery_fee) as avg_fee
                FROM deliveries 
                WHERE agent_id = '$agent_id' AND status = 'delivered'";
$total_result = mysqli_query($conn, $total_sql);
$total_data = mysqli_fetch_assoc($total_result);
$total_earnings = $total_data['total'] ?? 0;
$total_deliveries = $total_data['count'] ?? 0;
$avg_fee = $total_data['avg_fee'] ?? 0;

// Today's earnings
$today_sql = "SELECT SUM(delivery_fee) as total, COUNT(*) as count 
              FROM deliveries 
              WHERE agent_id = '$agent_id' AND status = 'delivered' AND DATE(delivered_at) = CURDATE()";
$today_result = mysqli_query($conn, $today_sql);
$today_data = mysqli_fetch_assoc($today_result);
$today_earnings = $today_data['total'] ?? 0;
$today_count = $today_data['count'] ?? 0;

// This month earnings
$month_sql = "SELECT SUM(delivery_fee) as total, COUNT(*) as count 
              FROM deliveries 
              WHERE agent_id = '$agent_id' AND status = 'delivered' 
              AND MONTH(delivered_at) = MONTH(CURDATE()) AND YEAR(delivered_at) = YEAR(CURDATE())";
$month_result = mysqli_query($conn, $month_sql);
$month_data = mysqli_fetch_assoc($month_result);
$month_earnings = $month_data['total'] ?? 0;
$month_count = $month_data['count'] ?? 0;

// Pending deliveries
$pending_sql = "SELECT COUNT(*) as count FROM deliveries 
                WHERE agent_id = '$agent_id' AND status IN ('assigned', 'picked_up', 'in_transit')";
$pending_result = mysqli_query($conn, $pending_sql);
$pending_count = mysqli_fetch_assoc($pending_result)['count'] ?? 0;

// ============================================================
// GET DELIVERY HISTORY
// ============================================================
$where = "d.agent_id = '$agent_id'";

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$period_filter = isset($_GET['period']) ? $_GET['period'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($status_filter == 'delivered') {
    $where .= " AND d.status = 'delivered'";
} elseif ($status_filter == 'pending') {
    $where .= " AND d.status IN ('assigned', 'picked_up', 'in_transit')";
} elseif ($status_filter == 'rated') {
    $where .= " AND d.rating IS NOT NULL";
} elseif ($status_filter == 'unrated') {
    $where .= " AND d.rating IS NULL";
} elseif ($status_filter != 'all') {
    $where .= " AND d.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

if ($period_filter == 'today') {
    $where .= " AND DATE(d.created_at) = CURDATE()";
} elseif ($period_filter == 'week') {
    $where .= " AND YEARWEEK(d.created_at) = YEARWEEK(CURDATE())";
} elseif ($period_filter == 'month') {
    $where .= " AND MONTH(d.created_at) = MONTH(CURDATE()) AND YEAR(d.created_at) = YEAR(CURDATE())";
}
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (o.order_id LIKE '%$search_esc%' OR b.business_name LIKE '%$search_esc%')";
}

$sql = "SELECT 
            d.delivery_id,
            d.order_id,
            d.delivery_fee,
            d.delivered_at,
            d.created_at,
            d.status,
            d.rating,
            d.rating_comment,
            d.rated_at,
            b.business_name
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN businesses b ON o.business_id = b.business_id
        WHERE $where
        ORDER BY d.created_at DESC
        LIMIT 50";
$result = mysqli_query($conn, $sql);
$deliveries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $deliveries[] = $row;
}

// ============================================================
// GET MONTHLY EARNINGS FOR CHART
// ============================================================
$monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%b') as month_name,
                    DATE_FORMAT(created_at, '%Y-%m') as month_key,
                    SUM(delivery_fee) as total,
                    COUNT(*) as count
                FROM deliveries 
                WHERE agent_id = '$agent_id' AND status = 'delivered'
                    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY created_at ASC
                LIMIT 12";
$monthly_result = mysqli_query($conn, $monthly_sql);
$chart_labels = [];
$chart_data = [];
$chart_counts = [];

while ($row = mysqli_fetch_assoc($monthly_result)) {
    $chart_labels[] = $row['month_name'] . ' ' . date('Y', strtotime($row['month_key'] . '-01'));
    $chart_data[] = (float)$row['total'];
    $chart_counts[] = (int)$row['count'];
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Earnings | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .delivery-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .delivery-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
            font-size: 0.65rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        .rating-stars {
            color: #f59e0b;
            font-size: 0.8rem;
            letter-spacing: 0.1rem;
        }
        .rating-stars .empty { color: #d1d5db; }
        
        .filter-bar {
            background: white;
            border-radius: 1rem;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
        }
        .filter-btn {
            padding: 0.25rem 0.8rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .search-box {
            display: flex;
            gap: 0.5rem;
        }
        .search-input {
            padding: 0.3rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            font-size: 0.75rem;
            width: 180px;
        }
        
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
        .card-body { padding: 1.25rem; }
        
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
        
        .earnings-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .earnings-table th {
            background: #f8fafc;
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .earnings-table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .earnings-table tr:hover {
            background: #fffbeb;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #ede9fe; color: #5b21b6; }
        .status-in_transit { background: #fce7f3; color: #be185d; }
        .status-pending { background: #fef3c7; color: #d97706; }
        
        .amount { font-weight: 700; color: #10b981; }
        .rating-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .rating-badge.rated {
            background: #d1fae5;
            color: #059669;
        }
        .rating-badge.unrated {
            background: #fef3c7;
            color: #d97706;
        }
        .rating-comment {
            font-size: 0.7rem;
            color: #64748b;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
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
        
        .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 1024px) {
            .delivery-content { margin-left: 0; padding: 1.25rem; }
            .chart-container { height: 220px; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .search-input { width: 100%; }
            .earnings-table { font-size: 0.7rem; }
            .earnings-table th, .earnings-table td { padding: 0.4rem 0.5rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .chart-container { height: 200px; }
        }
        @media (max-width: 480px) {
            .delivery-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-money-bill-wave"></i> My Earnings</h1>
            <p>Track your delivery earnings, ratings, and performance</p>
        </div>
        <a href="../das/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_earnings); ?></div>
            <div class="stat-label">Total Earnings</div>
            <div style="font-size:0.6rem; color:#94a3b8;"><?php echo $total_deliveries; ?> delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-number">TSh <?php echo number_format($today_earnings); ?></div>
            <div class="stat-label">Today's Earnings</div>
            <div style="font-size:0.6rem; color:#94a3b8;"><?php echo $today_count; ?> deliveries</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-number">TSh <?php echo number_format($month_earnings); ?></div>
            <div class="stat-label">This Month</div>
            <div style="font-size:0.6rem; color:#94a3b8;"><?php echo $month_count; ?> deliveries</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-star"></i></div>
            <div class="stat-number"><?php echo number_format($avg_rating, 1); ?></div>
            <div class="stat-label">Average Rating</div>
            <div style="font-size:0.6rem; color:#94a3b8;">
                <?php echo $total_ratings; ?> ratings • <?php echo $positive_ratings; ?> positive
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
            <div class="stat-number"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending Deliveries</div>
            <div style="font-size:0.6rem; color:#94a3b8;">In progress</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
            <a href="?status=all&period=<?php echo $period_filter; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=delivered&period=<?php echo $period_filter; ?>" class="filter-btn <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">Delivered</a>
            <a href="?status=pending&period=<?php echo $period_filter; ?>" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=rated&period=<?php echo $period_filter; ?>" class="filter-btn <?php echo $status_filter == 'rated' ? 'active' : ''; ?>">Rated</a>
            <a href="?status=unrated&period=<?php echo $period_filter; ?>" class="filter-btn <?php echo $status_filter == 'unrated' ? 'active' : ''; ?>">Unrated</a>
        </div>
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-calendar"></i> Period:</span>
            <a href="?status=<?php echo $status_filter; ?>&period=all" class="filter-btn <?php echo $period_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=<?php echo $status_filter; ?>&period=today" class="filter-btn <?php echo $period_filter == 'today' ? 'active' : ''; ?>">Today</a>
            <a href="?status=<?php echo $status_filter; ?>&period=week" class="filter-btn <?php echo $period_filter == 'week' ? 'active' : ''; ?>">This Week</a>
            <a href="?status=<?php echo $status_filter; ?>&period=month" class="filter-btn <?php echo $period_filter == 'month' ? 'active' : ''; ?>">This Month</a>
        </div>
        <form method="GET" class="search-box" onsubmit="return false;">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="hidden" name="period" value="<?php echo $period_filter; ?>">
            <input type="text" name="search" class="search-input" placeholder="Search orders or business..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
            <button type="button" class="filter-btn" onclick="doSearch()" style="margin:0;">Search</button>
            <?php if ($search): ?>
                <a href="?status=<?php echo $status_filter; ?>&period=<?php echo $period_filter; ?>" class="filter-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Monthly Earnings Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Monthly Earnings</h3>
            <span class="badge-count">Last 12 months</span>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="earningsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Delivery History -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Delivery History</h3>
            <span class="badge-count"><?php echo count($deliveries); ?> records</span>
        </div>
        <div class="card-body">
            <?php if (empty($deliveries)): ?>
                <div class="empty-state">
                    <i class="fas fa-truck"></i>
                    <h3>No Deliveries Found</h3>
                    <p>You haven't accepted any deliveries yet.</p>
                    <a href="../requests/requests.php" class="btn btn-primary" style="margin-top:0.5rem; display:inline-flex;">
                        <i class="fas fa-search"></i> Find Deliveries
                    </a>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="earnings-table">
                        <thead>
                            <tr>
                                <th>Delivery</th>
                                <th>Order</th>
                                <th>Business</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $delivery): ?>
                            <tr>
                                <td><strong><?php echo $delivery['delivery_id']; ?></strong></td>
                                <td><a href="../my-deliveries/my-deliveries.php?id=<?php echo $delivery['delivery_id']; ?>" style="color:#e67e22;"><?php echo $delivery['order_id']; ?></a></td>
                                <td><?php echo htmlspecialchars(substr($delivery['business_name'], 0, 20)); ?></td>
                                <td class="amount">TSh <?php echo number_format($delivery['delivery_fee'] ?? 0); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $delivery['status']; ?>">
                                        <i class="fas fa-<?php echo $delivery['status'] == 'delivered' ? 'check-circle' : ($delivery['status'] == 'assigned' ? 'clipboard-list' : ($delivery['status'] == 'picked_up' ? 'box' : ($delivery['status'] == 'in_transit' ? 'truck' : 'clock'))); ?>"></i>
                                        <?php echo str_replace('_', ' ', ucfirst($delivery['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($delivery['rating']): ?>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $delivery['rating'] ? '' : ' empty'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-badge rated">
                                            <i class="fas fa-check-circle"></i> <?php echo number_format($delivery['rating'], 1); ?>
                                        </span>
                                        <?php if (!empty($delivery['rating_comment'])): ?>
                                            <div class="rating-comment" title="<?php echo htmlspecialchars($delivery['rating_comment']); ?>">
                                                "<?php echo htmlspecialchars(substr($delivery['rating_comment'], 0, 20)); ?>"
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="rating-badge unrated">
                                            <i class="fas fa-clock"></i> Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:0.7rem; color:#64748b;">
                                    <?php echo date('M d, Y', strtotime($delivery['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function doSearch() {
    var searchVal = document.getElementById('searchInput').value;
    var url = new URL(window.location.href);
    if (searchVal) {
        url.searchParams.set('search', searchVal);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') doSearch();
});

document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../earnings/earnings.php' || 
            links[i].getAttribute('href') === 'earnings.php') {
            links[i].classList.add('active');
        }
    }

    // Chart.js - Monthly Earnings Chart
    const ctx = document.getElementById('earningsChart').getContext('2d');
    
    const chartLabels = <?php echo json_encode($chart_labels); ?>;
    const chartData = <?php echo json_encode($chart_data); ?>;
    const chartCounts = <?php echo json_encode($chart_counts); ?>;

    if (chartLabels.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Earnings (TSh)',
                    data: chartData,
                    backgroundColor: 'rgba(230, 126, 34, 0.7)',
                    borderColor: 'rgba(230, 126, 34, 1)',
                    borderWidth: 2,
                    borderRadius: 6,
                    barPercentage: 0.6,
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
                            font: { family: 'Inter', size: 12, weight: '600' },
                            color: '#64748b',
                            boxWidth: 12,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'white',
                        titleColor: '#1e293b',
                        bodyColor: '#475569',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        cornerRadius: 10,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                const amount = context.parsed.y;
                                const count = chartCounts[context.dataIndex] || 0;
                                return [
                                    'Amount: TSh ' + amount.toLocaleString(),
                                    'Deliveries: ' + count
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false },
                        ticks: {
                            font: { family: 'Inter', size: 11 },
                            color: '#94a3b8',
                            callback: function(value) {
                                return 'TSh ' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { family: 'Inter', size: 10 },
                            color: '#94a3b8',
                            maxRotation: 45,
                            minRotation: 30
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