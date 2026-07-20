<?php
// admin/delivery_agent/details.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($agent_id <= 0) {
    header("Location: agents.php");
    exit();
}

// Fetch agent details including all columns from delivery_agents and user email
$stmt = mysqli_prepare($conn, "
    SELECT a.*, u.email 
    FROM delivery_agents a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.agent_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $agent_id);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$agent) {
    $_SESSION['flash_message'] = "Delivery agent not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: agents.php");
    exit();
}

// Get delivery statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
        SUM(CASE WHEN status IN ('assigned', 'picked_up', 'in_transit') THEN 1 ELSE 0 END) as pending_deliveries
    FROM deliveries WHERE agent_id = ?
";
$stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stmt, 'i', $agent_id);
mysqli_stmt_execute($stmt);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$total_deliveries   = (int)($stats['total_deliveries'] ?? 0);
$completed          = (int)($stats['completed_deliveries'] ?? 0);
$pending            = (int)($stats['pending_deliveries'] ?? 0);
$completion_rate    = $total_deliveries > 0 ? round(($completed / $total_deliveries) * 100) : 0;

// Get recent deliveries (last 5)
$recent_sql = "
    SELECT d.delivery_id, d.order_id, d.status as delivery_status, d.created_at, d.delivered_at,
           o.grand_total, o.delivery_address,
           b.business_name,
           CONCAT(c.first_name, ' ', c.last_name) as customer_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.order_id
    JOIN businesses b ON o.business_id = b.business_id
    JOIN customers c ON o.customer_id = c.customer_id
    WHERE d.agent_id = ?
    ORDER BY d.created_at DESC
    LIMIT 5
";
$stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($stmt, 'i', $agent_id);
mysqli_stmt_execute($stmt);
$recent_result = mysqli_stmt_get_result($stmt);
$recent_deliveries = [];
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_deliveries[] = $row;
}
mysqli_stmt_close($stmt);

// Get rating breakdown (if ratings table exists)
$rating_stats = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
$avg_rating = 0;
$total_ratings = 0;
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'delivery_ratings'");
if (mysqli_num_rows($table_check) > 0) {
    $rating_sql = "SELECT rating, COUNT(*) as cnt FROM delivery_ratings WHERE agent_id = ? GROUP BY rating";
    $stmt = mysqli_prepare($conn, $rating_sql);
    mysqli_stmt_bind_param($stmt, 'i', $agent_id);
    mysqli_stmt_execute($stmt);
    $rating_res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($rating_res)) {
        $rating_stats[(string)$row['rating']] = (int)$row['cnt'];
        $total_ratings += $row['cnt'];
        $avg_rating += $row['rating'] * $row['cnt'];
    }
    mysqli_stmt_close($stmt);
    if ($total_ratings > 0) $avg_rating = round($avg_rating / $total_ratings, 1);
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Agent Details | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS unchanged – same as previous version */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .btn-back {
            background: #64748b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }
        .btn-edit {
            background: #f59e0b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-body { padding: 1.25rem 1.5rem; }
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        .status-suspended { background: #fee2e2; color: #dc2626; }
        .availability-online { background: #d1fae5; color: #059669; }
        .availability-offline { background: #fee2e2; color: #dc2626; }
        .rating-stars {
            color: #f39c12;
            font-size: 0.9rem;
            letter-spacing: 2px;
        }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 0.75rem;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
        }
        .data-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .delivery-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #c7d2fe; color: #3730a3; }
        .status-in_transit { background: #fed7aa; color: #c2410c; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .rating-bar {
            flex: 1;
            height: 6px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .rating-bar-fill {
            height: 100%;
            background: #f39c12;
            border-radius: 4px;
        }
        @media (max-width: 640px) {
            .admin-content { padding: 1rem; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.25rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> Agent Details</h1>
        <div style="display: flex; gap: 0.75rem;">
            <a href="agents.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Agents</a>
            <div class="action-buttons">
                <a href="edit.php?id=<?= $agent_id ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                <a href="delete.php?id=<?= $agent_id ?>" class="btn-delete" onclick="return confirm('⚠️ Permanently delete this agent? This action cannot be undone.')">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            </div>
        </div>
    </div>

    <!-- Personal & Account Info -->
    <div class="info-grid">
        <div class="info-card">
            <div class="card-header"><i class="fas fa-user"></i> Personal Information</div>
            <div class="card-body">
                <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Phone Number</div><div class="info-value"><?= htmlspecialchars($agent['phone']) ?></div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($agent['email']) ?></div></div>
                <div class="info-row"><div class="info-label">ID Number</div><div class="info-value"><?= htmlspecialchars($agent['id_number'] ?: 'Not provided') ?></div></div>
                <div class="info-row"><div class="info-label">Location (Base)</div><div class="info-value"><?= htmlspecialchars($agent['location'] ?: 'Not set') ?></div></div>
                <div class="info-row"><div class="info-label">Account Status</div><div class="info-value"><span class="status-badge status-<?= $agent['status'] ?>"><?= ucfirst($agent['status']) ?></span></div></div>
                <div class="info-row"><div class="info-label">Availability</div><div class="info-value">
                    <span class="status-badge <?= $agent['is_available'] ? 'availability-online' : 'availability-offline' ?>">
                        <i class="fas fa-<?= $agent['is_available'] ? 'circle' : 'times-circle' ?>"></i>
                        <?= $agent['is_available'] ? 'Online (Accepting deliveries)' : 'Offline (Not accepting)' ?>
                    </span>
                </div></div>
                <div class="info-row"><div class="info-label">Registered</div><div class="info-value"><?= date('F j, Y', strtotime($agent['created_at'])) ?></div></div>
                <?php if ($agent['current_latitude'] && $agent['current_longitude']): ?>
                <div class="info-row"><div class="info-label">Current Location</div><div class="info-value">Lat: <?= $agent['current_latitude'] ?>, Lon: <?= $agent['current_longitude'] ?></div></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="info-card">
            <div class="card-header"><i class="fas fa-motorcycle"></i> Vehicle Information</div>
            <div class="card-body">
                <div class="info-row"><div class="info-label">Vehicle Type</div><div class="info-value"><?= htmlspecialchars($agent['vehicle_type']) ?></div></div>
                <div class="info-row"><div class="info-label">Registration Number</div><div class="info-value"><strong><?= htmlspecialchars($agent['vehicle_registration']) ?></strong></div></div>
                <div class="info-row"><div class="info-label">Vehicle Model</div><div class="info-value"><?= htmlspecialchars($agent['vehicle_model'] ?: 'Not specified') ?></div></div>
                <div class="info-row"><div class="info-label">Vehicle Color</div><div class="info-value"><?= htmlspecialchars($agent['vehicle_color'] ?: 'Not specified') ?></div></div>
                <div class="info-row"><div class="info-label">License Number</div><div class="info-value"><?= htmlspecialchars($agent['license_number'] ?: 'N/A') ?></div></div>
                <div class="info-row"><div class="info-label">Insurance Expiry</div><div class="info-value"><?= $agent['insurance_expiry'] ? date('F j, Y', strtotime($agent['insurance_expiry'])) : 'Not set' ?></div></div>
            </div>
        </div>
    </div>

    <!-- Performance & Ratings -->
    <div class="info-grid">
        <div class="info-card">
            <div class="card-header"><i class="fas fa-chart-line"></i> Delivery Performance</div>
            <div class="card-body">
                <div class="info-row"><div class="info-label">Total Deliveries</div><div class="info-value"><?= $total_deliveries ?></div></div>
                <div class="info-row"><div class="info-label">Completed</div><div class="info-value"><?= $completed ?></div></div>
                <div class="info-row"><div class="info-label">Pending</div><div class="info-value"><?= $pending ?></div></div>
                <div class="info-row"><div class="info-label">Completion Rate</div><div class="info-value"><?= $completion_rate ?>%</div></div>
                <div class="info-row"><div class="info-label">Overall Rating</div><div class="info-value">
                    <span class="rating-stars">
                        <?php for ($i=1; $i<=5; $i++): ?>
                            <i class="fas fa-star<?= $i <= round($avg_rating) ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                    </span> <?= $avg_rating ?> (<?= $total_ratings ?> reviews)
                </div></div>
            </div>
        </div>
        <div class="info-card">
            <div class="card-header"><i class="fas fa-star"></i> Rating Breakdown</div>
            <div class="card-body">
                <?php for ($i=5; $i>=1; $i--): 
                    $count = $rating_stats[(string)$i];
                    $percent = $total_ratings > 0 ? round(($count / $total_ratings) * 100) : 0;
                ?>
                <div class="rating-bar-item">
                    <span style="width: 30px;"><?= $i ?> ★</span>
                    <div class="rating-bar"><div class="rating-bar-fill" style="width: <?= $percent ?>%"></div></div>
                    <span style="width: 40px;"><?= $count ?></span>
                </div>
                <?php endfor; ?>
                <?php if ($total_ratings == 0): ?>
                    <p class="text-muted">No ratings yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Deliveries -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-history"></i> Recent Deliveries (Last 5)</div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Delivery ID</th><th>Order ID</th><th>Business</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_deliveries)): ?>
                        <tr><td colspan="7" style="text-align:center;">No deliveries yet</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_deliveries as $d): ?>
                        <tr onclick="location.href='../deliveries/delivery-details.php?id=<?= $d['delivery_id'] ?>'" style="cursor:pointer;">
                            <td><?= $d['delivery_id'] ?></td>
                            <td><?= $d['order_id'] ?></td>
                            <td><?= htmlspecialchars($d['business_name']) ?></td>
                            <td><?= htmlspecialchars($d['customer_name']) ?></td>
                            <td>TSh <?= number_format($d['grand_total']) ?></td>
                            <td><span class="delivery-status status-<?= $d['delivery_status'] ?>"><?= str_replace('_', ' ', ucfirst($d['delivery_status'])) ?></span></td>
                            <td><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>