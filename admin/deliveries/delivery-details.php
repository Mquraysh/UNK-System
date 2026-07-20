<?php
// admin/deliveries/delivery-details.php 
require_once '../../config/database.php';
require_once '../notifications/functions.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id == 0) {
    header("Location: index.php");
    exit();
}


// CUSTOMER PHONE AND EMAIL 
$delivery_sql = "SELECT 
    d.*, 
    o.order_id, 
    o.grand_total, 
    o.delivery_address, 
     
    o.order_date, 
    o.status as order_status, 
    o.delivery_fee,
    b.business_name, 
    b.location, 
    b.phone as business_phone,
    CONCAT(a.first_name, ' ', a.last_name) as agent_name,
    a.phone as agent_phone, 
    a.vehicle_type,
    c.first_name, 
    c.last_name,
    u.phone as customer_phone,
    u.email as customer_email
FROM deliveries d
JOIN orders o ON d.order_id = o.order_id
JOIN businesses b ON o.business_id = b.business_id
LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
WHERE d.delivery_id = $delivery_id";
$delivery_result = mysqli_query($conn, $delivery_sql);
$delivery = mysqli_fetch_assoc($delivery_result);

if (!$delivery) {
    header("Location: index.php");
    exit();
}

// Get delivery history
$history_sql = "SELECT * FROM delivery_history WHERE delivery_id = $delivery_id ORDER BY created_at DESC";
$history_result = mysqli_query($conn, $history_sql);
$history = [];
while ($row = mysqli_fetch_assoc($history_result)) {
    $history[] = $row;
}

// Get delivery ratings if any
$rating_sql = "SELECT r.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
               FROM delivery_ratings r
               JOIN customers c ON r.customer_id = c.customer_id
               WHERE r.delivery_id = $delivery_id";
$rating_result = mysqli_query($conn, $rating_sql);
$rating = mysqli_fetch_assoc($rating_result);

// Get delivery statistics
$stats_sql = "SELECT COUNT(*) as total_deliveries,
                     AVG(TIMESTAMPDIFF(MINUTE, created_at, delivered_at)) as avg_delivery_time
              FROM deliveries 
              WHERE agent_id = " . ($delivery['agent_id'] ?? 0) . " 
              AND status = 'delivered'";
$stats_result = mysqli_query($conn, $stats_sql);
$agent_stats = mysqli_fetch_assoc($stats_result);

// STATUS DEFINITIONS
$statuses = ['pending', 'assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled'];
$status_labels = [
    'pending' => 'Pending',
    'assigned' => 'Assigned',
    'picked_up' => 'Picked Up',
    'in_transit' => 'In Transit',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];
$status_icons = [
    'pending' => 'fa-clock',
    'assigned' => 'fa-user-check',
    'picked_up' => 'fa-box',
    'in_transit' => 'fa-truck',
    'delivered' => 'fa-check-circle',
    'cancelled' => 'fa-times-circle'
];
$status_colors = [
    'pending' => '#f59e0b',
    'assigned' => '#3b82f6',
    'picked_up' => '#8b5cf6',
    'in_transit' => '#ec4899',
    'delivered' => '#10b981',
    'cancelled' => '#ef4444'
];
$status_bg = [
    'pending' => '#fef3c7',
    'assigned' => '#dbeafe',
    'picked_up' => '#ede9fe',
    'in_transit' => '#fce7f3',
    'delivered' => '#d1fae5',
    'cancelled' => '#fee2e2'
];

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Delivery Details <?php echo $delivery_id; ?> | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
  
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        /* ============================================================
           LAYOUT - Main content with sidebar offset
           ============================================================ */
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 0.9rem; }
        }
        
        /* ============================================================
           PAGE HEADER
           ============================================================ */
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
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #2c3e50;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.6rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        /* ============================================================
           BUTTONS
           ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 0.6rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-info { background: #3b82f6; color: white; }
        .btn-info:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-sm { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
        
        /* ============================================================
           ALERTS
           ============================================================ */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        .alert-warning { background: #fffbeb; color: #92400e; border-left: 4px solid #f59e0b; }
        
        /* ============================================================
           DETAILS GRID
           ============================================================ */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        /* ============================================================
           CARDS
           ============================================================ */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
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
        
        /* ============================================================
           INFO ROWS
           ============================================================ */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { 
            color: #64748b; 
            font-weight: 500; 
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .info-row .label i { 
            color: #e67e22; 
            font-size: 0.75rem;
            width: 1rem;
        }
        .info-row .value { 
            font-weight: 600; 
            color: #0f172a; 
            text-align: right; 
            word-break: break-word;
            max-width: 60%;
        }
        .info-row .value .highlight { color: #e67e22; }
        .info-row .value .success { color: #10b981; }
        .info-row .value .danger { color: #ef4444; }
        
        /* ============================================================
           STATUS BADGES
           ============================================================ */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #ede9fe; color: #5b21b6; }
        .status-in_transit { background: #fce7f3; color: #be185d; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        /* ============================================================
           AGENT CARD
           ============================================================ */
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
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e67e22, #d35400);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .agent-info { flex: 1; }
        .agent-info .name { font-weight: 700; font-size: 0.9rem; }
        .agent-info .details { font-size: 0.75rem; color: #64748b; }
        .agent-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            font-size: 0.7rem;
            color: #64748b;
        }
        .agent-stats span { display: flex; align-items: center; gap: 0.3rem; }
        .agent-stats i { color: #e67e22; }
        
        /* ============================================================
           TIMELINE
           ============================================================ */
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
        
        /* ============================================================
           RATING DISPLAY
           ============================================================ */
        .rating-display {
            text-align: center;
            padding: 1rem 0;
        }
        .rating-display .stars {
            font-size: 2.5rem;
            color: #f59e0b;
            letter-spacing: 0.25rem;
        }
        .rating-display .stars .empty { color: #d1d5db; }
        .rating-display .rating-number {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
        }
        .rating-display .rating-label {
            color: #64748b;
            font-size: 0.85rem;
        }
        .rating-display .rating-comment {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            font-size: 0.85rem;
            color: #475569;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        
        /* ============================================================
           UTILITY CLASSES
           ============================================================ */
        .full-width { grid-column: 1 / -1; }
        .text-center { text-align: center; }
        .text-muted { color: #94a3b8; }
        .mt-1 { margin-top: 0.5rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .gap-1 { gap: 0.5rem; }
        .flex { display: flex; }
        .flex-wrap { flex-wrap: wrap; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        
        /* ============================================================
           EMPTY STATE
           ============================================================ */
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
        
        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .details-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .info-row { flex-direction: column; gap: 0.2rem; }
            .info-row .value { text-align: left; max-width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header h1 { font-size: 1.4rem; }
            .agent-card { flex-direction: column; text-align: center; }
            .agent-stats { justify-content: center; flex-wrap: wrap; }
            .rating-display .stars { font-size: 2rem; }
        }
        @media (max-width: 480px) {
            .admin-content { padding: 0.5rem; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> Delivery Details</h1>
            <p>Complete information for delivery <?php echo $delivery_id; ?></p>
        </div>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'cancelled'): ?>
            <a href="update-status.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Update Status
            </a>
            <?php endif; ?>
            <?php if ($delivery['agent_id'] == null && $delivery['status'] != 'cancelled'): ?>
            <a href="assign-agent.php?id=<?php echo $delivery_id; ?>" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Assign Agent
            </a>
            <?php endif; ?>
            <?php if ($delivery['status'] == 'delivered'): ?>
            <span class="btn btn-info" style="cursor: default;">
                <i class="fas fa-check-circle"></i> Completed
            </span>
            <?php endif; ?>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>
    

    <!-- DETAILS GRID -->
    <div class="details-grid">
       
        <!-- DELIVERY INFORMATION CARD -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Delivery Information</h3>
                <span class="status-badge status-<?php echo $delivery['status']; ?>">
                    <i class="fas <?php echo $status_icons[$delivery['status']]; ?>"></i>
                    <?php echo $status_labels[$delivery['status']]; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-hashtag"></i> Delivery ID</span>
                    <span class="value highlight"><?php echo $delivery_id; ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-shopping-cart"></i> Order ID</span>
                    <span class="value"><?php echo $delivery['order_id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-box"></i> Order Status</span>
                    <span class="value"><?php echo ucfirst($delivery['order_status']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-money-bill-wave"></i> Delivery Fee</span>
                    <span class="value highlight">TSh <?php echo number_format($delivery['delivery_fee'] ?? 0); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-receipt"></i> Order Total</span>
                    <span class="value">TSh <?php echo number_format($delivery['grand_total']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Created At</span>
                    <span class="value"><?php echo date('M d, Y h:i A', strtotime($delivery['created_at'])); ?></span>
                </div>
                <?php if ($delivery['delivered_at']): ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-calendar-check"></i> Delivered At</span>
                    <span class="value success"><?php echo date('M d, Y h:i A', strtotime($delivery['delivered_at'])); ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="label"><i class="fas fa-sync-alt"></i> Last Updated</span>
                    <span class="value"><?php echo date('M d, Y h:i A', strtotime($delivery['updated_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- BUSINESS INFORMATION CARD -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-store"></i> Business Information</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-store"></i> Business Name</span>
                    <span class="value"><strong><?php echo htmlspecialchars($delivery['business_name']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-marker-alt"></i> Location</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['location'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['business_phone'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- CUSTOMER INFORMATION CARD -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="label"><i class="fas fa-user"></i> Name</span>
                    <span class="value"><strong><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['customer_phone'] ?? $delivery['contact_phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['customer_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label"><i class="fas fa-map-pin"></i> Delivery Address</span>
                    <span class="value" style="font-size: 0.8rem;"><?php echo htmlspecialchars($delivery['delivery_address']); ?></span>
                </div>
            </div>
        </div>

        <!-- DELIVERY AGENT CARD -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-check"></i> Delivery Agent</h3>
                <?php if ($delivery['agent_id']): ?>
                <span class="badge-count"><i class="fas fa-check-circle" style="color: #10b981;"></i> Assigned</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($delivery['agent_name']): ?>
                    <div class="agent-card">
                        <div class="agent-avatar">
                            <?php echo strtoupper(substr($delivery['agent_name'], 0, 1)); ?>
                        </div>
                        <div class="agent-info">
                            <div class="name"><?php echo htmlspecialchars($delivery['agent_name']); ?></div>
                            <div class="details">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['agent_phone'] ?? 'N/A'); ?>
                                <span style="margin: 0 0.3rem;">•</span>
                                <i class="fas fa-car"></i> <?php echo ucfirst($delivery['vehicle_type'] ?? 'N/A'); ?>
                            </div>
                            <?php if ($agent_stats && $agent_stats['total_deliveries'] > 0): ?>
                            <div class="agent-stats">
                                <span><i class="fas fa-tasks"></i> <?php echo $agent_stats['total_deliveries']; ?> deliveries</span>
                                <?php if ($agent_stats['avg_delivery_time']): ?>
                                <span><i class="fas fa-clock"></i> Avg <?php echo round($agent_stats['avg_delivery_time']); ?> min</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <p>No agent assigned yet</p>
                        <?php if ($delivery['status'] != 'cancelled' && $delivery['status'] != 'delivered'): ?>
                        <a href="assign-agent.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary btn-sm" style="margin-top: 0.5rem; display: inline-flex;">
                            <i class="fas fa-user-plus"></i> Assign Agent Now
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CUSTOMER RATING CARD -->
        <?php if ($rating): ?>
        <div class="card full-width">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> Customer Rating</h3>
                <span style="font-size: 0.75rem; color: #64748b;">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($rating['customer_name']); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="rating-display">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $rating['rating'] ? '' : ' empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-number"><?php echo number_format($rating['rating'], 1); ?></div>
                    <div class="rating-label">out of 5 stars</div>
                    <?php if (!empty($rating['comment'])): ?>
                        <div class="rating-comment">
                            <i class="fas fa-quote-left" style="color: #e67e22; opacity: 0.5;"></i>
                            <?php echo htmlspecialchars($rating['comment']); ?>
                            <i class="fas fa-quote-right" style="color: #e67e22; opacity: 0.5;"></i>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 0.7rem; color: #94a3b8; margin-top: 0.5rem;">
                        <i class="far fa-clock"></i> Rated on <?php echo date('M d, Y h:i A', strtotime($rating['created_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DELIVERY TIMELINE CARD -->
        <div class="card full-width">
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
                        ?>
                        <div class="timeline-item <?php echo $is_active ? 'active' : 'done'; ?>">
                            <div class="tl-status">
                                <i class="fas <?php echo $status_icons[$item['status']]; ?>" style="color: <?php echo $status_colors[$item['status']]; ?>;"></i>
                                <?php echo $status_labels[$item['status']]; ?>
                                <?php if ($is_active): ?>
                                    <span style="font-size: 0.6rem; background: #e67e22; color: white; padding: 0.1rem 0.4rem; border-radius: 1rem; margin-left: 0.4rem;">Current</span>
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
    </div>
</div>
</body>
</html>