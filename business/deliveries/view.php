<?php
// business/deliveries/view.php - PROFESSIONAL DELIVERY DETAILS (NO PRINT)
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

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id == 0) {
    header("Location: index.php");
    exit();
}

// ============================================================
// GET DELIVERY DETAILS
// ============================================================
$sql = "SELECT 
    d.*, 
    o.order_id, 
    o.grand_total, 
    o.delivery_address, 
    u.phone, 
    o.order_date, 
    o.special_instructions,
    o.status as order_status,
    o.payment_status,
    o.delivery_fee,
    c.customer_id,
    c.first_name, 
    c.last_name, 
    c.city, 
    c.saved_address,
    u.email, 
    u.phone as user_phone,
    a.agent_id,
    a.first_name as agent_first_name, 
    a.last_name as agent_last_name, 
    a.vehicle_type, 
    a.vehicle_registration, 
    u.phone as agent_phone,
    a.license_number,
    (SELECT COUNT(*) FROM deliveries WHERE agent_id = a.agent_id AND status = 'delivered') as agent_total_deliveries,
    (SELECT AVG(rating) FROM delivery_ratings WHERE delivery_id IN (SELECT delivery_id FROM deliveries WHERE agent_id = a.agent_id)) as agent_avg_rating
FROM deliveries d
JOIN orders o ON d.order_id = o.order_id
JOIN customers c ON o.customer_id = c.customer_id
JOIN users u ON c.user_id = u.user_id
LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
WHERE d.delivery_id = '$delivery_id' AND o.business_id = '$business_id'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    $_SESSION['flash_message'] = "Delivery not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// ============================================================
// GET DELIVERY HISTORY
// ============================================================
$history_sql = "SELECT * FROM delivery_history WHERE delivery_id = '$delivery_id' ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);
$history = [];
if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
}

// ============================================================
// IF HISTORY TABLE IS EMPTY, CREATE FROM DELIVERY DATA
// ============================================================
if (empty($history)) {
    if ($delivery['created_at']) {
        $history[] = [
            'status' => $delivery['status'],
            'notes' => 'Delivery created',
            'created_at' => $delivery['created_at']
        ];
    }
    if ($delivery['assigned_at']) {
        $history[] = [
            'status' => 'assigned',
            'notes' => 'Agent assigned',
            'created_at' => $delivery['assigned_at']
        ];
    }
    if ($delivery['picked_up_at']) {
        $history[] = [
            'status' => 'picked_up',
            'notes' => 'Package picked up',
            'created_at' => $delivery['picked_up_at']
        ];
    }
    if ($delivery['delivered_at']) {
        $history[] = [
            'status' => 'delivered',
            'notes' => 'Delivered to customer',
            'created_at' => $delivery['delivered_at']
        ];
    }
    usort($history, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}

// ============================================================
// GET DELIVERY RATING
// ============================================================
$rating_sql = "SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
               FROM delivery_ratings r
               JOIN customers c ON r.customer_id = c.customer_id
               WHERE r.delivery_id = '$delivery_id'";
$rating_result = mysqli_query($conn, $rating_sql);
$rating = mysqli_fetch_assoc($rating_result);

// ============================================================
// STATUS CONFIGURATIONS
// ============================================================
$statuses = [
    'pending' => ['label' => 'Pending', 'icon' => 'fa-clock', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'assigned' => ['label' => 'Assigned', 'icon' => 'fa-user-check', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'picked_up' => ['label' => 'Picked Up', 'icon' => 'fa-box', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'in_transit' => ['label' => 'In Transit', 'icon' => 'fa-road', 'color' => '#ec4899', 'bg' => '#fce7f3'],
    'delivered' => ['label' => 'Delivered', 'icon' => 'fa-check-circle', 'color' => '#10b981', 'bg' => '#d1fae5'],
    'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-times-circle', 'color' => '#ef4444', 'bg' => '#fee2e2']
];

$status = $delivery['status'];
$status_info = $statuses[$status] ?? $statuses['pending'];

// Calculate delivery time if delivered
$delivery_time = '';
if ($delivery['status'] == 'delivered' && $delivery['delivered_at']) {
    $created = strtotime($delivery['created_at']);
    $delivered = strtotime($delivery['delivered_at']);
    $hours = round(($delivered - $created) / 3600, 1);
    $delivery_time = $hours . ' hours';
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Delivery Details - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
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
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(230,126,34,0.3); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-info { background: #3b82f6; color: white; }
        .btn-info:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-back {
            background: #2c3e50;
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
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        .delivery-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width { grid-column: 1 / -1; }
        
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
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
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 1.25rem; }
        
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: #0f172a;
            font-size: 0.85rem;
            font-weight: 500;
            word-break: break-word;
        }
        .info-value a { color: #e67e22; text-decoration: none; }
        .info-value a:hover { text-decoration: underline; }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item {
            position: relative;
            padding: 0.5rem 0 0.5rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.9rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #e2e8f0;
            z-index: 1;
        }
        .timeline-item.active::before {
            background: #e67e22;
            box-shadow: 0 0 0 2px #e67e22;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(230,126,34,0.4); }
            70% { box-shadow: 0 0 0 6px rgba(230,126,34,0); }
            100% { box-shadow: 0 0 0 0 rgba(230,126,34,0); }
        }
        .timeline-item.done::before {
            background: #10b981;
            box-shadow: 0 0 0 2px #10b981;
        }
        .timeline-item.cancelled::before {
            background: #dc2626;
            box-shadow: 0 0 0 2px #dc2626;
        }
        .timeline-item .tl-status {
            font-weight: 600;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .timeline-item .tl-time {
            font-size: 0.65rem;
            color: #94a3b8;
        }
        .timeline-item .tl-notes {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.2rem;
            background: #f8fafc;
            padding: 0.3rem 0.6rem;
            border-radius: 0.4rem;
            display: inline-block;
        }
        
        .agent-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .agent-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e67e22, #d35400);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .agent-info { flex: 1; }
        .agent-info .name { font-weight: 700; font-size: 0.9rem; }
        .agent-info .details { font-size: 0.75rem; color: #64748b; }
        .agent-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.3rem;
            font-size: 0.7rem;
            color: #64748b;
        }
        .agent-stats span { display: flex; align-items: center; gap: 0.3rem; }
        .agent-stats i { color: #e67e22; }
        
        .rating-stars {
            color: #f59e0b;
            font-size: 0.9rem;
            letter-spacing: 0.1rem;
        }
        .rating-stars .empty { color: #d1d5db; }
        
        .delivery-time {
            background: #e0f2fe;
            border-radius: 0.5rem;
            padding: 0.4rem 0.8rem;
            font-size: 0.7rem;
            color: #0369a1;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }
        .empty-state p { font-size: 0.85rem; }
        
        @media (max-width: 1100px) {
            .delivery-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .info-row { flex-direction: column; gap: 0.2rem; }
            .info-label { width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; flex-wrap: wrap; }
            .header-actions .btn { flex: 1; justify-content: center; min-width: 120px; }
            .agent-card { flex-direction: column; text-align: center; }
            .agent-stats { justify-content: center; flex-wrap: wrap; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
            .header-actions .btn { min-width: 100%; }
        }
        
        @media print {
            .sidebar, .header-actions, .btn-back {
                display: none !important;
            }
            .business-content { margin-left: 0 !important; padding: 0 !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> Delivery Details</h1>
            <p>Complete information for delivery <?php echo $delivery_id; ?></p>
        </div>
        <div class="header-actions">
            <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'cancelled'): ?>
                <!-- <a href="../update-status.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Update Status
                </a> -->
            <?php endif; ?>
            <?php if (!$delivery['agent_id'] && $delivery['status'] != 'cancelled' && $delivery['status'] != 'delivered'): ?>
                <a href="assign-agent.php?id=<?php echo $delivery_id; ?>" class="btn btn-success">
                    <i class="fas fa-user-plus"></i> Assign Agent
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Delivery Grid -->
    <div class="delivery-grid">
        <!-- Delivery Information -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Delivery Information</h3>
                <span class="status-badge" style="background: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                    <i class="fas <?php echo $status_info['icon']; ?>"></i>
                    <?php echo $status_info['label']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Delivery ID</span>
                    <span class="info-value" style="color:#e67e22; font-weight:700;"><?php echo $delivery_id; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order ID</span>
                    <span class="info-value"><a href="../orders/details.php?id=<?php echo $delivery['order_id']; ?>"><?php echo $delivery['order_id']; ?></a></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Status</span>
                    <span class="info-value"><?php echo ucfirst($delivery['order_status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status</span>
                    <span class="info-value">
                        <span style="color: <?php echo $delivery['payment_status'] == 'paid' ? '#10b981' : '#f59e0b'; ?>;">
                            <?php echo ucfirst($delivery['payment_status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Amount</span>
                    <span class="info-value" style="font-weight:700; color:#e67e22;">TSh <?php echo number_format($delivery['grand_total']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Delivery Fee</span>
                    <span class="info-value">TSh <?php echo number_format($delivery['delivery_fee'] ?? 0); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?php echo date('F d, Y g:i A', strtotime($delivery['order_date'])); ?></span>
                </div>
                <?php if ($delivery_time): ?>
                <div class="info-row">
                    <span class="info-label">Delivery Time</span>
                    <span class="info-value">
                        <span class="delivery-time">
                            <i class="fas fa-clock"></i> <?php echo $delivery_time; ?>
                        </span>
                    </span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Delivery Address</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($delivery['delivery_address'])); ?></span>
                </div>
                <?php if($delivery['special_instructions']): ?>
                <div class="info-row">
                    <span class="info-label">Special Instructions</span>
                    <span class="info-value" style="background:#f8fafc; padding:0.3rem 0.6rem; border-radius:0.4rem;"><?php echo nl2br(htmlspecialchars($delivery['special_instructions'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
                <span class="badge-count"><i class="fas fa-user"></i> Customer</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Full Name</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number</span>
                    <span class="info-value">
                        <i class="fas fa-phone" style="color:#e67e22;"></i>
                        <?php echo htmlspecialchars($delivery['user_phone']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address</span>
                    <span class="info-value">
                        <i class="fas fa-envelope" style="color:#e67e22;"></i>
                        <?php echo htmlspecialchars($delivery['email']); ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">City</span>
                    <span class="info-value"><?php echo htmlspecialchars($delivery['city'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Saved Address</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($delivery['saved_address'] ?? 'N/A')); ?></span>
                </div>
            </div>
        </div>

        <!-- Delivery Agent Information -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-motorcycle"></i> Delivery Agent</h3>
                <?php if ($delivery['agent_id']): ?>
                    <span class="badge-count"><i class="fas fa-check-circle" style="color:#10b981;"></i> Assigned</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($delivery['agent_first_name']): ?>
                    <div class="agent-card">
                        <div class="agent-avatar">
                            <?php echo strtoupper(substr($delivery['agent_first_name'], 0, 1) . substr($delivery['agent_last_name'] ?? '', 0, 1)); ?>
                        </div>
                        <div class="agent-info">
                            <div class="name"><?php echo htmlspecialchars($delivery['agent_first_name'] . ' ' . $delivery['agent_last_name']); ?></div>
                            <div class="details">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['agent_phone'] ?? 'N/A'); ?>
                                <span style="margin:0 0.3rem;">•</span>
                                <i class="fas fa-car"></i> <?php echo ucfirst($delivery['vehicle_type'] ?? 'N/A'); ?>
                                <?php if ($delivery['vehicle_registration']): ?>
                                    <span style="margin:0 0.3rem;">•</span>
                                    <i class="fas fa-id-card"></i> <?php echo strtoupper($delivery['vehicle_registration']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($delivery['agent_total_deliveries'] > 0): ?>
                            <div class="agent-stats">
                                <span><i class="fas fa-tasks"></i> <?php echo $delivery['agent_total_deliveries']; ?> deliveries completed</span>
                                <?php if ($delivery['agent_avg_rating']): ?>
                                    <span><i class="fas fa-star" style="color:#f59e0b;"></i> <?php echo number_format($delivery['agent_avg_rating'], 1); ?> avg rating</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($delivery['license_number']): ?>
                    <div style="margin-top:0.5rem; font-size:0.7rem; color:#64748b;">
                        <i class="fas fa-id-card"></i> License: <?php echo htmlspecialchars($delivery['license_number']); ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No agent assigned yet</p>
                        <?php if ($delivery['status'] != 'cancelled' && $delivery['status'] != 'delivered'): ?>
                            <a href="assign-agent.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary" style="margin-top:0.5rem; display:inline-flex;">
                                <i class="fas fa-user-plus"></i> Assign Agent Now
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Delivery Timeline -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Delivery Timeline</h3>
                <span class="badge-count"><?php echo count($history); ?> events</span>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <p>No status history available</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($history as $item): 
                            $is_active = $item['status'] == $delivery['status'];
                            $is_cancelled = $item['status'] == 'cancelled';
                            $status_class = $is_cancelled ? 'cancelled' : ($is_active ? 'active' : 'done');
                            $item_status = $statuses[$item['status']] ?? $statuses['pending'];
                        ?>
                        <div class="timeline-item <?php echo $status_class; ?>">
                            <div class="tl-status">
                                <i class="fas <?php echo $item_status['icon']; ?>" style="color: <?php echo $item_status['color']; ?>;"></i>
                                <?php echo $item_status['label']; ?>
                                <?php if ($is_active): ?>
                                    <span style="font-size:0.6rem; background:#e67e22; color:white; padding:0.1rem 0.4rem; border-radius:1rem; margin-left:0.4rem;">Current</span>
                                <?php endif; ?>
                            </div>
                            <div class="tl-time">
                                <i class="far fa-clock"></i> 
                                <?php echo date('M d, Y h:i A', strtotime($item['created_at'])); ?>
                            </div>
                            <?php if (!empty($item['notes'])): ?>
                                <div class="tl-notes">
                                    <i class="fas fa-sticky-note" style="color: #e67e22; opacity: 0.5;"></i>
                                    <?php echo htmlspecialchars($item['notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Rating (if available) -->
        <?php if ($rating): ?>
        <div class="card full-width">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Customer Rating</h3>
                <span style="font-size:0.75rem; color:#64748b;">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($rating['customer_name']); ?>
                </span>
            </div>
            <div class="card-body">
                <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
                    <div style="text-align:center;">
                        <div style="font-size:2.5rem; font-weight:800; color:#e67e22;"><?php echo number_format($rating['rating'], 1); ?></div>
                        <div class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $rating['rating'] ? '' : ' empty'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div style="font-size:0.75rem; color:#94a3b8;">out of 5 stars</div>
                    </div>
                    <?php if (!empty($rating['comment'])): ?>
                    <div style="flex:1; background:#f8fafc; padding:0.75rem 1rem; border-radius:0.75rem; border-left:3px solid #e67e22;">
                        <i class="fas fa-quote-left" style="color:#e67e22; opacity:0.5;"></i>
                        <?php echo htmlspecialchars($rating['comment']); ?>
                        <i class="fas fa-quote-right" style="color:#e67e22; opacity:0.5;"></i>
                    </div>
                    <?php endif; ?>
                    <div style="font-size:0.65rem; color:#94a3b8;">
                        <i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($rating['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../deliveries/view.php' || 
            links[i].getAttribute('href') === 'view.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>