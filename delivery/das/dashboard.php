<?php
// delivery/dashboard.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch agent data using prepared statement
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

if (!$agent) {
    header("Location: register.php");
    exit();
}

$agent_id = $agent['agent_id'];
$first_name = $agent['first_name'];
$last_name = $agent['last_name'];
$is_available = $agent['is_available'];
$full_name = $first_name . ' ' . $last_name;

// Get agent rating from delivery_ratings table
$rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings 
               FROM delivery_ratings WHERE agent_id = $agent_id";
$rating_result = mysqli_query($conn, $rating_sql);
$rating_data = mysqli_fetch_assoc($rating_result);
$agent_rating = $rating_data['avg_rating'] ?? 0;
$total_ratings = $rating_data['total_ratings'] ?? 0;

// STATISTICS 
$total_sql = "SELECT COUNT(*) as count FROM deliveries WHERE agent_id = ?";
$stmt = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$total_result = mysqli_stmt_get_result($stmt);
$total_deliveries = (int)(mysqli_fetch_assoc($total_result)['count'] ?? 0);
mysqli_stmt_close($stmt);

$completed_sql = "SELECT COUNT(*) as count FROM deliveries WHERE agent_id = ? AND status = 'delivered'";
$stmt = mysqli_prepare($conn, $completed_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$completed_result = mysqli_stmt_get_result($stmt);
$completed_deliveries = (int)(mysqli_fetch_assoc($completed_result)['count'] ?? 0);
mysqli_stmt_close($stmt);

$pending_sql = "SELECT COUNT(*) as count FROM deliveries WHERE agent_id = ? AND status IN ('assigned', 'picked_up', 'in_transit')";
$stmt = mysqli_prepare($conn, $pending_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$pending_result = mysqli_stmt_get_result($stmt);
$pending_deliveries = (int)(mysqli_fetch_assoc($pending_result)['count'] ?? 0);
mysqli_stmt_close($stmt);

$earnings_sql = "SELECT SUM(delivery_fee) as total FROM deliveries WHERE agent_id = ? AND status = 'delivered'";
$stmt = mysqli_prepare($conn, $earnings_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$earnings_result = mysqli_stmt_get_result($stmt);
$total_earnings = (float)(mysqli_fetch_assoc($earnings_result)['total'] ?? 0);
mysqli_stmt_close($stmt);

$completion_rate = $total_deliveries > 0 ? round(($completed_deliveries / $total_deliveries) * 100) : 0;

// New Available Deliveries Count
if (!isset($_SESSION['last_requests_view'])) {
    $_SESSION['last_requests_view'] = date('Y-m-d H:i:s', strtotime('-24 hours'));
}
$last_view = $_SESSION['last_requests_view'];

$new_available_sql = "SELECT COUNT(*) as count FROM orders 
                      WHERE agent_id IS NULL 
                      AND status IN ('confirmed','ready', 'preparing')
                      AND order_date > ?";
$stmt = mysqli_prepare($conn, $new_available_sql);
mysqli_stmt_bind_param($stmt, "s", $last_view);
mysqli_stmt_execute($stmt);
$new_available_result = mysqli_stmt_get_result($stmt);
$new_available_count = (int)(mysqli_fetch_assoc($new_available_result)['count'] ?? 0);
mysqli_stmt_close($stmt);

$total_available_sql = "SELECT COUNT(*) as count FROM orders 
                        WHERE agent_id IS NULL 
                        AND status IN ('confirmed','ready', 'preparing')";
$total_available_result = mysqli_query($conn, $total_available_sql);
$total_available_count = (int)(mysqli_fetch_assoc($total_available_result)['count'] ?? 0);

// RECENT DELIVERIES
$recent_sql = "SELECT d.*, o.order_id, o.delivery_address, o.grand_total, b.business_name 
               FROM deliveries d 
               JOIN orders o ON d.order_id = o.order_id 
               JOIN businesses b ON o.business_id = b.business_id 
               WHERE d.agent_id = ?
               ORDER BY d.created_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$recent_res = mysqli_stmt_get_result($stmt);
$recent_deliveries = [];
while ($row = mysqli_fetch_assoc($recent_res)) {
    $recent_deliveries[] = $row;
}
mysqli_stmt_close($stmt);

// WEEKLY EARNINGS CHART
$weekly_sql = "SELECT DATE(created_at) as date, SUM(delivery_fee) as total 
               FROM deliveries 
               WHERE agent_id = ? AND status = 'delivered' 
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               GROUP BY DATE(created_at)";
$stmt = mysqli_prepare($conn, $weekly_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$weekly_res = mysqli_stmt_get_result($stmt);
$weekly_data = [];
while ($row = mysqli_fetch_assoc($weekly_res)) {
    $weekly_data[$row['date']] = $row['total'];
}
mysqli_stmt_close($stmt);

$dates = [];
$earnings_chart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('D', strtotime($date));
    $earnings_chart[] = $weekly_data[$date] ?? 0;
}

// RECENT ACTIVITY 
$activity_sql = "SELECT d.delivery_id, d.status, d.updated_at, o.order_id, b.business_name 
                 FROM deliveries d 
                 JOIN orders o ON d.order_id = o.order_id 
                 JOIN businesses b ON o.business_id = b.business_id 
                 WHERE d.agent_id = ? 
                 ORDER BY d.updated_at DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $activity_sql);
mysqli_stmt_bind_param($stmt, "i", $agent_id);
mysqli_stmt_execute($stmt);
$activity_res = mysqli_stmt_get_result($stmt);
$activities = [];
while ($row = mysqli_fetch_assoc($activity_res)) {
    $activities[] = $row;
}
mysqli_stmt_close($stmt);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Delivery Dashboard | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .dashboard-content { flex: 1; margin-left: 280px; padding: 32px 40px; background: #f5f7fa; transition: all 0.2s; }
        
        .page-header { margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .page-header h1 { font-size: 30px; font-weight: 800; background: linear-gradient(135deg, #1e293b, #2c3e50); -webkit-background-clip: text; background-clip: text; color: transparent; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { background: none; color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; font-size: 14px; margin-top: 6px; }
        
        /* Status Card */
        .status-card { background: white; border-radius: 28px; padding: 20px 28px; margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; border: 1px solid #eef2f8; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .status-info { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 16px; border-radius: 40px; font-weight: 600; font-size: 13px; }
        .status-online { background: #e0f2e9; color: #0a5c3e; }
        .status-offline { background: #fee2e2; color: #991b1b; }
        .status-text { font-size: 13px; color: #64748b; }
        
        /* Toggle Switch */
        .toggle-switch { position: relative; display: inline-block; width: 56px; height: 28px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: 0.3s; border-radius: 28px; }
        .toggle-slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; transition: 0.3s; border-radius: 50%; }
        input:checked + .toggle-slider { background-color: #e67e22; }
        input:checked + .toggle-slider:before { transform: translateX(28px); }
        
        /* Stats Grid - 6 Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 24px; padding: 20px; border: 1px solid #eef2f8; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -12px rgba(0,0,0,0.12); border-color: #e67e22; }
        .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .stat-header h3 { font-size: 28px; font-weight: 800; color: #0f172a; }
        .stat-icon { width: 48px; height: 48px; background: rgba(230,126,34,0.08); border-radius: 24px; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .stat-card:hover .stat-icon { background: rgba(230,126,34,0.15); transform: scale(1.05); }
        .stat-icon i { font-size: 24px; color: #e67e22; }
        .stat-label { color: #64748b; font-size: 13px; font-weight: 500; margin-top: 4px; }
        .stat-sub { font-size: 10px; color: #94a3b8; margin-top: 4px; display: block; }
        
        /* Rating Stars in Stat Card */
        .stat-rating-stars { color: #f39c12; font-size: 14px; letter-spacing: 1px; }
        .stat-rating-stars .empty { color: #d1d5db; }
        
        /* Quick Actions */
        .quick-actions { display: flex; gap: 16px; margin-bottom: 32px; flex-wrap: wrap; }
        .action-btn { display: inline-flex; align-items: center; gap: 10px; padding: 12px 24px; border-radius: 40px; font-weight: 600; text-decoration: none; transition: all 0.3s; font-size: 14px; }
        .btn-primary { background: #e67e22; color: white; box-shadow: 0 2px 6px rgba(230,126,34,0.2); }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(230,126,34,0.25); }
        .btn-secondary { background: #2c3e50; color: white; }
        .btn-secondary:hover { background: #1a252f; transform: translateY(-2px); }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #1e8449; transform: translateY(-2px); }
        .btn-badge { background: white; color: #e67e22; border-radius: 40px; padding: 2px 8px; font-size: 11px; font-weight: 700; margin-left: 4px; }
        
        /* Two Columns */
        .two-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 32px; }
        .card { background: white; border-radius: 28px; border: 1px solid #eef2f8; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); transition: all 0.3s; }
        .card:hover { box-shadow: 0 8px 20px -8px rgba(0,0,0,0.06); }
        .card-header { padding: 20px 28px; background: #fafcff; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
        .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #e67e22; }
        .card-header .view-all { color: #e67e22; font-size: 13px; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .card-header .view-all:hover { text-decoration: underline; }
        .card-body { padding: 24px 28px; }
        .chart-container { height: 260px; position: relative; }
        
        /* Timeline */
        .timeline { list-style: none; padding: 0; margin: 0; }
        .timeline-item { display: flex; gap: 14px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
        .timeline-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .timeline-icon { width: 40px; height: 40px; border-radius: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .timeline-icon.delivered { background: #d1fae5; color: #059669; }
        .timeline-icon.assigned { background: #dbeafe; color: #2563eb; }
        .timeline-icon.picked_up { background: #fef3c7; color: #d97706; }
        .timeline-icon.in_transit { background: #e0e7ff; color: #4338ca; }
        .timeline-icon.default { background: #f1f5f9; color: #64748b; }
        .timeline-content { flex: 1; }
        .timeline-title { font-weight: 600; margin-bottom: 2px; font-size: 14px; }
        .timeline-sub { font-size: 13px; color: #475569; }
        .timeline-time { font-size: 11px; color: #94a3b8; display: block; margin-top: 4px; }
        
        /* Table */
        .table-wrapper { background: white; border-radius: 28px; border: 1px solid #eef2f8; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
        .table-header { padding: 20px 28px; background: #fafcff; border-bottom: 1px solid #f0f2f5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .table-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 20px; background: #fafcff; font-weight: 600; font-size: 12px; color: #64748b; border-bottom: 1px solid #edf2f7; text-transform: uppercase; letter-spacing: 0.3px; }
        .data-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover td { background: #fefcf8; }
        .data-table tr:last-child td { border-bottom: none; }
        
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 40px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-assigned { background: #dbeafe; color: #2563eb; }
        .badge-picked_up { background: #d1fae5; color: #059669; }
        .badge-in_transit { background: #e0e7ff; color: #4338ca; }
        .badge-delivered { background: #d1fae5; color: #059669; }
        
        .btn-sm { background: #e67e22; color: white; padding: 6px 14px; border-radius: 30px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: 0.3s; }
        .btn-sm:hover { background: #d35400; transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 48px 20px; color: #94a3b8; }
        .empty-state i { font-size: 48px; margin-bottom: 12px; opacity: 0.4; }
        
        @media (max-width: 1200px) { 
            .stats-grid { grid-template-columns: repeat(3, 1fr); } 
        }
        @media (max-width: 1100px) { 
            .dashboard-content { margin-left: 0; padding: 24px; } 
            .two-columns { grid-template-columns: 1fr; } 
        }
        @media (max-width: 768px) { 
            .dashboard-content { padding: 16px; } 
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; } 
            .data-table th, .data-table td { padding: 12px; font-size: 12px; } 
            .status-card { flex-direction: column; align-items: flex-start; }
            .quick-actions { flex-direction: column; }
            .action-btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) { 
            .stats-grid { grid-template-columns: 1fr; } 
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-tachometer-alt"></i> Delivery Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>! Here's your performance overview</p>
            </div>
        </div>

        <!-- Status Card -->
        <div class="status-card">
            <div class="status-info">
                <div class="status-badge <?php echo $is_available ? 'status-online' : 'status-offline'; ?>">
                    <i class="fas fa-circle"></i>
                    <?php echo $is_available ? 'Online - Accepting Deliveries' : 'Offline - Not Accepting Deliveries'; ?>
                </div>
                <span class="status-text"><i class="fas fa-info-circle"></i> Toggle to change availability</span>
            </div>
            <form method="POST" action="../update_status/update_status.php" id="toggleForm">
                <label class="toggle-switch">
                    <input type="checkbox" id="availabilityToggle" name="is_available" value="1" <?php echo $is_available ? 'checked' : ''; ?>>
                    <span class="toggle-slider"></span>
                </label>
                <input type="hidden" name="is_available" id="hiddenAvailable" value="<?php echo $is_available; ?>">
            </form>
        </div>

        <!-- Stats Grid - 6 Cards including Rating -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <h3><?php echo number_format($total_available_count); ?></h3>
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                </div>
                <div class="stat-label">Available Requests</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3><?php echo number_format($total_deliveries); ?></h3>
                    <div class="stat-icon"><i class="fas fa-truck"></i></div>
                </div>
                <div class="stat-label">Total Deliveries</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3><?php echo number_format($completed_deliveries); ?></h3>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3><?php echo number_format($pending_deliveries); ?></h3>
                    <div class="stat-icon"><i class="fas fa-spinner fa-pulse"></i></div>
                </div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3><?php echo number_format($total_earnings); ?> TSh</h3>
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                </div>
                <div class="stat-label">Total Earnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <h3>
                        <?php if ($agent_rating > 0): ?>
                            <span class="stat-rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= round($agent_rating) ? '' : ' empty'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span style="font-size: 22px; margin-left: 4px;"><?php echo number_format($agent_rating, 1); ?></span>
                        <?php else: ?>
                            <span style="font-size: 20px; color: #94a3b8;">No ratings</span>
                        <?php endif; ?>
                    </h3>
                    <div class="stat-icon"><i class="fas fa-star"></i></div>
                </div>
                <div class="stat-label">Rating</div>
                <?php if ($total_ratings > 0): ?>
                    <span class="stat-sub"><?php echo $total_ratings; ?> reviews</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../requests/requests.php" class="action-btn btn-primary">
                <i class="fas fa-map-marker-alt"></i> Available Deliveries
                <?php if ($new_available_count > 0): ?>
                    <span class="btn-badge"><?php echo $new_available_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="../my-deliveries/my-deliveries.php" class="action-btn btn-secondary">
                <i class="fas fa-list"></i> My Deliveries
                <?php if ($pending_deliveries > 0): ?>
                    <span class="btn-badge" style="color:#2c3e50;"><?php echo $pending_deliveries; ?></span>
                <?php endif; ?>
            </a>
            <a href="../earnings/earnings.php" class="action-btn btn-success">
                <i class="fas fa-chart-line"></i> View Earnings
            </a>
        </div>

        <!-- Two Columns -->
        <div class="two-columns">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Weekly Earnings</h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <div class="empty-state">
                            <i class="fas fa-clock"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <ul class="timeline">
                            <?php foreach ($activities as $act): 
                                $icon_class = 'default';
                                $icon_icon = 'fa-info-circle';
                                if ($act['status'] == 'delivered') { $icon_class = 'delivered'; $icon_icon = 'fa-check-circle'; }
                                elseif ($act['status'] == 'assigned') { $icon_class = 'assigned'; $icon_icon = 'fa-clipboard-list'; }
                                elseif ($act['status'] == 'picked_up') { $icon_class = 'picked_up'; $icon_icon = 'fa-box'; }
                                elseif ($act['status'] == 'in_transit') { $icon_class = 'in_transit'; $icon_icon = 'fa-truck'; }
                            ?>
                            <li class="timeline-item">
                                <div class="timeline-icon <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon_icon; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title">Order <?php echo $act['order_id']; ?> - <?php echo htmlspecialchars($act['business_name']); ?></div>
                                    <div class="timeline-sub">Status changed to <strong><?php echo str_replace('_', ' ', ucfirst($act['status'])); ?></strong></div>
                                    <span class="timeline-time"><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($act['updated_at'])); ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Deliveries Table -->
        <div class="table-wrapper">
            <div class="table-header">
                <h3><i class="fas fa-clock"></i> Recent Deliveries</h3>
                <a href="../my-deliveries/my-deliveries.php" class="view-all">View all →</a>
            </div>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Delivery ID</th>
                            <th>Order ID</th>
                            <th>Business</th>
                            <th>Address</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_deliveries)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No deliveries yet</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_deliveries as $delivery): ?>
                            <tr>
                                <td><strong><?php echo $delivery['delivery_id']; ?></strong></td>
                                <td><?php echo $delivery['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($delivery['business_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($delivery['delivery_address'], 0, 35)); ?>…</td>
                                <td><strong>TSh <?php echo number_format($delivery['grand_total']); ?></strong></td>
                                <td>
                                    <span class="badge <?php 
                                        if ($delivery['status'] == 'assigned') echo 'badge-assigned'; 
                                        elseif ($delivery['status'] == 'picked_up') echo 'badge-picked_up'; 
                                        elseif ($delivery['status'] == 'in_transit') echo 'badge-in_transit'; 
                                        elseif ($delivery['status'] == 'delivered') echo 'badge-delivered'; 
                                        else echo 'badge-pending'; 
                                    ?>">
                                        <i class="fas <?php 
                                            echo $delivery['status'] == 'delivered' ? 'fa-check-circle' : 
                                                ($delivery['status'] == 'in_transit' ? 'fa-truck' : 
                                                ($delivery['status'] == 'picked_up' ? 'fa-box' : 'fa-clock')); 
                                        ?>"></i>
                                        <?php echo str_replace('_', ' ', ucfirst($delivery['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../details/delivery-details.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm">
                                        <i class="fas fa-eye"></i> View
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
    // Form submission for toggle 
    const toggleCheckbox = document.getElementById('availabilityToggle');
    const toggleForm = document.getElementById('toggleForm');
    toggleCheckbox.addEventListener('change', function() {
        document.getElementById('hiddenAvailable').value = this.checked ? '1' : '0';
        toggleForm.submit();
    });

    // Chart initialization
    new Chart(document.getElementById('earningsChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($dates); ?>,
            datasets: [{
                label: 'Earnings (TSh)',
                data: <?php echo json_encode($earnings_chart); ?>,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230,126,34,0.03)',
                borderWidth: 3,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#e67e22',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: { 
                    callbacks: { 
                        label: (ctx) => 'TSh ' + ctx.raw.toLocaleString() 
                    } 
                },
                legend: { 
                    position: 'top',
                    labels: { usePointStyle: true, boxWidth: 8 }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true, 
                    ticks: { callback: (v) => 'TSh ' + v.toLocaleString() },
                    grid: { color: '#eef2f8' }
                },
                x: { grid: { display: false } }
            }
        }
    });
</script>
</body>
</html>