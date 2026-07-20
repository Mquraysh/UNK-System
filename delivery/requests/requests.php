<?php
// delivery/requests.php - FIXED: Order status stays 'confirmed'
require_once '../../config/database.php';
session_start();

// Record last view timestamp
$_SESSION['last_requests_view'] = date('Y-m-d H:i:s');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch agent data
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
$is_available = $agent['is_available'];
$vehicle_type = $agent['vehicle_type'] ?? 'motorcycle';

// Get total deliveries from deliveries table
$total_sql = "SELECT COUNT(*) as total FROM deliveries WHERE agent_id = '$agent_id'";
$total_res = mysqli_query($conn, $total_sql);
$total_deliveries = (int)(mysqli_fetch_assoc($total_res)['total'] ?? 0);

// Get available deliveries count (confirmed, ready and preparing)
$available_sql = "SELECT COUNT(*) as count FROM orders 
                  WHERE agent_id IS NULL 
                  AND status IN ('ready', 'preparing', 'confirmed')";
$available_res = mysqli_query($conn, $available_sql);
$available_count = (int)(mysqli_fetch_assoc($available_res)['count'] ?? 0);

// Get today's deliveries count
$today_sql = "SELECT COUNT(*) as count FROM deliveries 
              WHERE agent_id = '$agent_id' 
              AND DATE(created_at) = CURDATE()";
$today_res = mysqli_query($conn, $today_sql);
$today_count = (int)(mysqli_fetch_assoc($today_res)['count'] ?? 0);

// Get total earnings
$earnings_sql = "SELECT SUM(delivery_fee) as total FROM deliveries 
                 WHERE agent_id = '$agent_id' AND status = 'delivered'";
$earnings_res = mysqli_query($conn, $earnings_sql);
$total_earnings = (int)(mysqli_fetch_assoc($earnings_res)['total'] ?? 0);

// ============================================
// HANDLE ACCEPT DELIVERY - FIXED: Order status stays 'confirmed'
// ============================================
if (isset($_GET['accept'])) {
    $order_id = (int)$_GET['accept'];
    
    $check_sql = "SELECT order_id, delivery_fee FROM orders 
                  WHERE order_id = ? 
                  AND agent_id IS NULL 
                  AND status IN ('ready', 'preparing', 'confirmed')";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    $order_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($stmt);

    if ($order_data) {
        $delivery_fee = $order_data['delivery_fee'];

        mysqli_begin_transaction($conn);
        try {
            // ============================================
            // FIXED: Order status stays 'confirmed' (DO NOT change)
            // Only assign agent_id
            // ============================================
            $update_order = "UPDATE orders SET agent_id = ? WHERE order_id = ?";
            $stmt = mysqli_prepare($conn, $update_order);
            mysqli_stmt_bind_param($stmt, "ii", $agent_id, $order_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // Create delivery record with status 'assigned'
            $delivery_sql = "INSERT INTO deliveries (order_id, agent_id, delivery_fee, status, assigned_at) 
                             VALUES (?, ?, ?, 'assigned', NOW())";
            $stmt = mysqli_prepare($conn, $delivery_sql);
            mysqli_stmt_bind_param($stmt, "iid", $order_id, $agent_id, $delivery_fee);
            mysqli_stmt_execute($stmt);
            $delivery_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            $_SESSION['flash_message'] = "Delivery accepted successfully!";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = "Error accepting delivery: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Delivery no longer available!";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: requests.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$area = isset($_GET['area']) ? trim($_GET['area']) : '';

// BUILD QUERY FOR AVAILABLE DELIVERIES
// SHOW: confirmed, preparing, ready (agent_id IS NULL)
$sql = "SELECT o.order_id, o.delivery_address, o.grand_total, o.delivery_fee, o.status,
               b.business_name, b.location as pickup_location, b.phone as business_phone,
               u.phone as customer_phone, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name
        FROM orders o
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE o.agent_id IS NULL
        AND o.status IN ('confirmed', 'preparing', 'ready')";

if (!empty($search)) {
    $search_escaped = '%' . $search . '%';
    $sql .= " AND (o.order_id LIKE ? 
               OR b.business_name LIKE ? 
               OR o.delivery_address LIKE ?)";
}
if (!empty($area)) {
    $area_escaped = '%' . $area . '%';
    $sql .= " AND o.delivery_address LIKE ?";
}

// Order by status: confirmed -> preparing -> ready
$sql .= " ORDER BY 
            CASE o.status 
                WHEN 'confirmed' THEN 1 
                WHEN 'preparing' THEN 2 
                WHEN 'ready' THEN 3 
                ELSE 4 
            END,
            o.order_date ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($search) && !empty($area)) {
    mysqli_stmt_bind_param($stmt, "ssss", $search_escaped, $search_escaped, $search_escaped, $area_escaped);
} elseif (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "sss", $search_escaped, $search_escaped, $search_escaped);
} elseif (!empty($area)) {
    mysqli_stmt_bind_param($stmt, "s", $area_escaped);
}
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);
$available_orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $available_orders[] = $row;
}
mysqli_stmt_close($stmt);

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Available Deliveries | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .delivery-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-header h1 { 
            font-size: 1.75rem; 
            font-weight: 700; 
            color: #0f172a; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem; 
        }
        .page-header h1 i { color: #e67e22; font-size: 1.8rem; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        .alert { 
            padding: 0.85rem 1.1rem; 
            border-radius: 0.75rem; 
            margin-bottom: 1.25rem; 
            display: flex; 
            align-items: center; 
            gap: 0.6rem; 
            font-size: 0.85rem;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.65rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-info h3 { 
            font-size: 1.75rem; 
            font-weight: 800; 
            margin-bottom: 0.25rem; 
        }
        .stat-info p { 
            font-size: 0.7rem; 
            color: #64748b; 
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .stat-info p i { font-size: 0.65rem; }
        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(230,126,34,0.1);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .stat-card:hover .stat-icon { background: #e67e22; }
        .stat-card:hover .stat-icon i { color: white; }
        .stat-icon i { font-size: 1.5rem; color: #e67e22; transition: all 0.2s; }
        
        .stat-card.available h3 { color: #2563eb; }
        .stat-card.today h3 { color: #10b981; }
        .stat-card.total h3 { color: #e67e22; }
        .stat-card.earnings h3 { color: #8b5cf6; }
        
        .status-notice {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .status-notice i { color: #f59e0b; }
        .btn-toggle {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 0.5rem;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-toggle:hover { background: #d35400; transform: translateY(-2px); }
        
        .filter-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .filter-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            width: 100%;
        }
        .filter-form input {
            flex: 1;
            min-width: 180px;
            padding: 0.6rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.6rem;
            font-size: 0.8rem;
            font-family: 'Inter', sans-serif;
        }
        .filter-form input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .filter-form button {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.6rem 1.25rem;
            border-radius: 0.6rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-form button:hover { background: #d35400; transform: translateY(-2px); }
        .filter-form a {
            padding: 0.6rem 1.25rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 0.8rem;
            border-radius: 0.6rem;
            transition: all 0.2s;
        }
        .filter-form a:hover { background: #f1f5f9; color: #e67e22; }
        
        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.25rem;
        }
        .delivery-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        .delivery-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.12);
            border-color: #e67e22;
        }
        .delivery-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .delivery-id {
            font-weight: 700;
            color: #e67e22;
            font-size: 0.85rem;
        }
        .delivery-fee {
            font-size: 1rem;
            font-weight: 800;
            color: #e67e22;
        }
        .delivery-body {
            padding: 1rem 1.25rem;
        }
        .info-row {
            display: flex;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            flex-wrap: wrap;
        }
        .info-label {
            width: 110px;
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            word-break: break-word;
            font-weight: 500;
        }
        .delivery-footer {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-top: 1px solid #e2e8f0;
        }
        .btn {
            padding: 0.6rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            width: 100%;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-primary.disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-confirmed { background: #dbeafe; color: #1d4ed8; }
        .badge-preparing { background: #fef3c7; color: #d97706; }
        .badge-ready { background: #d1fae5; color: #059669; }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 1rem;
        }
        .empty-state i { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 1rem; opacity: 0.5; }
        .empty-state h3 { font-size: 1.1rem; color: #1e293b; margin-bottom: 0.5rem; }
        .empty-state p { color: #64748b; margin-bottom: 1.25rem; font-size: 0.85rem; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
        }
        @media (max-width: 1024px) { 
            .delivery-content { margin-left: 0; padding: 1.25rem; } 
            .deliveries-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) { 
            .delivery-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form input { width: 100%; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-map-marker-alt"></i> Available Deliveries</h1>
            <p>Browse and accept deliveries that are ready for pickup</p>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card available">
            <div class="stat-info">
                <h3><?php echo number_format($available_count); ?></h3>
                <p><i class="fas fa-map-marker-alt"></i> Available Deliveries</p>
            </div>
            <div class="stat-icon"><i class="fas fa-truck"></i></div>
        </div>
        <div class="stat-card today">
            <div class="stat-info">
                <h3><?php echo number_format($today_count); ?></h3>
                <p><i class="fas fa-calendar-day"></i> Today's Deliveries</p>
            </div>
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        </div>
        <div class="stat-card total">
            <div class="stat-info">
                <h3><?php echo number_format($total_deliveries); ?></h3>
                <p><i class="fas fa-check-circle"></i> My Total Deliveries</p>
            </div>
            <div class="stat-icon"><i class="fas fa-route"></i></div>
        </div>
        <div class="stat-card earnings">
            <div class="stat-info">
                <h3>TSh <?php echo number_format($total_earnings); ?></h3>
                <p><i class="fas fa-money-bill-wave"></i> Total Earnings</p>
            </div>
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>

    <!-- Status Notice -->
    <?php if (!$is_available): ?>
        <div class="status-notice">
            <div><i class="fas fa-circle"></i> <strong>You are currently offline.</strong> Go online to accept deliveries.</div>
            <form method="POST" action="../update_status/update_status.php" style="display: inline;">
                <input type="hidden" name="is_available" value="1">
                <button type="submit" class="btn-toggle"><i class="fas fa-power-off"></i> Go Online</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" class="filter-form">
            <input type="text" name="search" placeholder="Search by Order ID, Business or Address..." value="<?php echo htmlspecialchars($search); ?>">
            <input type="text" name="area" placeholder="Filter by area (e.g., Kariakoo, Posta, Mbezi)" value="<?php echo htmlspecialchars($area); ?>">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <a href="../requests/requests.php"><i class="fas fa-sync-alt"></i> Reset</a>
        </form>
    </div>

    <!-- Deliveries Grid -->
    <?php if (empty($available_orders)): ?>
        <div class="empty-state">
            <i class="fas fa-truck"></i>
            <h3>No deliveries available</h3>
            <p>Check back later – new deliveries appear when businesses mark orders as 'Ready'.</p>
            <a href="../das/dashboard.php" style="display: inline-block; padding: 0.6rem 1.25rem; background: #e67e22; color: white; border-radius: 0.6rem; text-decoration: none; font-size: 0.8rem; font-weight: 600;">Back to Dashboard</a>
        </div>
    <?php else: ?>
        <div class="deliveries-grid">
            <?php foreach ($available_orders as $order): ?>
                <div class="delivery-card">
                    <div class="delivery-header">
                        <div class="delivery-id"><i class="fas fa-hashtag"></i> Order <?php echo $order['order_id']; ?></div>
                        <div class="delivery-fee"><i class="fas fa-tag"></i> TSh <?php echo number_format($order['delivery_fee']); ?></div>
                    </div>
                    <div class="delivery-body">
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-store"></i> Business:</span>
                            <span class="info-value"><strong><?php echo htmlspecialchars($order['business_name']); ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-map-pin"></i> Pickup:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['pickup_location'] ?? $order['business_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-location-dot"></i> Delivery:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-user"></i> Customer:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-phone"></i> Contact:</span>
                            <span class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? $order['business_phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-shopping-cart"></i> Order Total:</span>
                            <span class="info-value">TSh <?php echo number_format($order['grand_total']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label"><i class="fas fa-flag-checkered"></i> Status:</span>
                            <span class="info-value">
                                <span class="badge 
                                    <?php 
                                        echo $order['status'] == 'confirmed' ? 'badge-confirmed' : 
                                            ($order['status'] == 'preparing' ? 'badge-preparing' : 
                                            ($order['status'] == 'ready' ? 'badge-ready' : '')); 
                                    ?>">
                                    <i class="fas 
                                        <?php 
                                            echo $order['status'] == 'confirmed' ? 'fa-check-circle' : 
                                                ($order['status'] == 'preparing' ? 'fa-cogs' : 
                                                ($order['status'] == 'ready' ? 'fa-box-open' : 'fa-circle')); 
                                        ?>"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="delivery-footer">
                        <?php if ($is_available): ?>
                            <a href="?accept=<?php echo $order['order_id']; ?>" class="btn btn-primary" onclick="return confirm('Accept this delivery?')">
                                <i class="fas fa-check-circle"></i> Accept Delivery
                            </a>
                        <?php else: ?>
                            <button class="btn btn-primary disabled" disabled>
                                <i class="fas fa-circle"></i> Go Online to Accept
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>