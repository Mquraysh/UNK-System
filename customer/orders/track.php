<?php
// customer/orders/track.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? 0;

// Get order ID from URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header("Location: index.php");
    exit();
}

// Get order details with customer verification
$sql = "SELECT o.*, 
               b.business_name, 
               b.address as business_address,
               b.latitude as business_lat, 
               b.longitude as business_lng,
               b.phone as business_phone,
               c.first_name, 
               c.last_name, 
               c.saved_address as customer_address,
               u.phone as customer_phone,
               d.status as delivery_status,
               d.delivery_id,
               d.pickup_address,
               o.delivery_address,
               d.estimated_distance,
               d.estimated_time,
               da.first_name as agent_first_name,
               da.last_name as agent_last_name,
               da.phone as agent_phone,
               da.vehicle_type,
               da.vehicle_registration,
               da.profile_image as agent_profile
        FROM orders o
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        LEFT JOIN deliveries d ON o.order_id = d.order_id
        LEFT JOIN delivery_agents da ON d.agent_id = da.agent_id
        WHERE o.order_id = ? AND o.customer_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Get order items
$items_sql = "SELECT oi.*, p.name, p.image_url 
              FROM order_items oi
              JOIN products p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
$order_items = [];
while ($row = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $row;
}
mysqli_stmt_close($stmt);

// Get tracking history
$tracking_sql = "SELECT * FROM order_tracking 
                 WHERE order_id = ? 
                 ORDER BY created_at ASC";
$stmt = mysqli_prepare($conn, $tracking_sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$tracking_result = mysqli_stmt_get_result($stmt);
$tracking_history = [];
while ($row = mysqli_fetch_assoc($tracking_result)) {
    $tracking_history[] = $row;
}
mysqli_stmt_close($stmt);

// If no tracking history, create default based on order status
if (empty($tracking_history)) {
    $default_tracking = [
        'pending' => ['status' => 'pending', 'note' => 'Order placed successfully'],
        'accepted' => ['status' => 'accepted', 'note' => 'Store has accepted your order'],
        'confirmed' => ['status' => 'confirmed', 'note' => 'Order confirmed and being prepared'],
        'preparing' => ['status' => 'preparing', 'note' => 'Your items are being packed'],
        'ready' => ['status' => 'ready', 'note' => 'Order ready for pickup'],
        'picked_up' => ['status' => 'picked_up', 'note' => 'Rider has picked up your order'],
        'in_transit' => ['status' => 'in_transit', 'note' => 'Your order is on the way'],
        'delivered' => ['status' => 'delivered', 'note' => 'Order delivered successfully!']
    ];
    
    $status_order = ['pending', 'accepted', 'confirmed', 'preparing', 'ready', 'picked_up', 'in_transit', 'delivered'];
    $current_status = $order['status'] ?? 'pending';
    $current_index = array_search($current_status, $status_order);
    
    if ($current_index !== false) {
        for ($i = 0; $i <= $current_index; $i++) {
            $status = $status_order[$i];
            $tracking_history[] = [
                'status' => $status,
                'note' => $default_tracking[$status]['note'],
                'created_at' => date('Y-m-d H:i:s', strtotime($order['order_date'] . ' +' . ($i * 5) . ' minutes'))
            ];
        }
    }
}

// TRACKING STATUSES WITH COLORS
$statuses = [
    'pending' => [
        'icon' => 'fa-clock',
        'color' => '#f39c12',
        'bg' => '#fef9e7',
        'label' => 'Order Placed',
        'description' => 'Your order has been received and is waiting for confirmation.'
    ],
    'accepted' => [
        'icon' => 'fa-check-circle',
        'color' => '#3498db',
        'bg' => '#e3f2fd',
        'label' => 'Order Accepted',
        'description' => 'The store has confirmed your order.'
    ],
    'confirmed' => [
        'icon' => 'fa-thumbs-up',
        'color' => '#9b59b6',
        'bg' => '#f3e5f5',
        'label' => 'Order Confirmed',
        'description' => 'Your order has been confirmed and is being prepared.'
    ],
    'preparing' => [
        'icon' => 'fa-box',
        'color' => '#e67e22',
        'bg' => '#fff3e0',
        'label' => 'Being Packed',
        'description' => 'Your items are being packed and prepared for delivery.'
    ],
    'ready' => [
        'icon' => 'fa-check',
        'color' => '#2ecc71',
        'bg' => '#e8f8f5',
        'label' => 'Ready for Pickup',
        'description' => 'Your order is ready and waiting for the rider.'
    ],
    'picked_up' => [
        'icon' => 'fa-truck',
        'color' => '#e67e22',
        'bg' => '#fff3e0',
        'label' => 'Picked Up',
        'description' => 'The rider has picked up your order from the store.'
    ],
    'in_transit' => [
        'icon' => 'fa-shipping-fast',
        'color' => '#2ecc71',
        'bg' => '#e8f8f5',
        'label' => 'On The Way',
        'description' => 'Your order is on the way to your location.'
    ],
    'delivered' => [
        'icon' => 'fa-check-double',
        'color' => '#27ae60',
        'bg' => '#d4edda',
        'label' => 'Delivered',
        'description' => 'Your order has been delivered successfully!'
    ],
    'cancelled' => [
        'icon' => 'fa-times-circle',
        'color' => '#e74c3c',
        'bg' => '#f8d7da',
        'label' => 'Cancelled',
        'description' => 'Your order has been cancelled.'
    ]
];

// Current status
$current_status = $order['delivery_status'] ?? $order['status'] ?? 'pending';
$status_info = $statuses[$current_status] ?? $statuses['pending'];

// Progress steps
$progress_steps = ['pending', 'accepted', 'confirmed', 'preparing', 'ready', 'picked_up', 'in_transit', 'delivered'];
$current_step = array_search($current_status, $progress_steps);
$total_steps = count($progress_steps) - 1;
$progress_percentage = ($current_step / $total_steps) * 100;

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order <?php echo $order_id; ?> | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 28px 32px;
            min-height: 100vh;
        }
        
        .track-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .track-header h1 {
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .track-header h1 i { color: #e67e22; }
        .track-header .order-id {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .track-grid {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
        }
        
        .track-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .track-card .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .track-card .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .track-card .card-header h3 i { color: #e67e22; }
        .track-card .card-body { padding: 24px; }
        
        /* Progress Bar */
        .progress-container {
            padding: 20px 0 30px;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 10px;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 20px;
            right: 20px;
            height: 4px;
            background: #e2e8f0;
            z-index: 0;
        }
        .progress-steps::after {
            content: '';
            position: absolute;
            top: 18px;
            left: 20px;
            height: 4px;
            background: linear-gradient(90deg, #e67e22, #27ae60);
            z-index: 1;
            transition: width 1s ease;
            /* width: <?php echo max(0, min(100, $progress_percentage)); ?>%; */
           
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            background: #e2e8f0;
            color: #94a3b8;
            transition: all 0.5s ease;
            border: 3px solid transparent;
        }
        .step-circle.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
            box-shadow: 0 0 0 6px rgba(230,126,34,0.2);
            transform: scale(1.1);
        }
        .step-circle.completed {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
        }
        .step-circle i { font-size: 14px; }
        .step-label {
            margin-top: 8px;
            font-size: 9px;
            font-weight: 600;
            color: #94a3b8;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .step-label.active-label {
            color: #e67e22;
        }
        .step-label.completed-label {
            color: #27ae60;
        }
        
        /* Status Info */
        .status-info {
            background: <?php echo $status_info['bg']; ?>;
            border: 1px solid <?php echo $status_info['color']; ?>40;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .status-info .icon {
            font-size: 32px;
            color: <?php echo $status_info['color']; ?>;
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .status-info .content h4 {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        .status-info .content p {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item {
            position: relative;
            padding: 12px 0 12px 20px;
            border-left: 2px solid transparent;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 16px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            border: 2px solid white;
        }
        .timeline-item.active::before {
            background: #e67e22;
            box-shadow: 0 0 0 4px rgba(230,126,34,0.2);
        }
        .timeline-item.completed::before {
            background: #27ae60;
        }
        .timeline-item .time {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
        }
        .timeline-item .title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .timeline-item .desc {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        
        /* Order Details Sidebar */
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eef2f6;
            font-size: 14px;
        }
        .detail-row .label { color: #64748b; font-weight: 500; }
        .detail-row .value { font-weight: 600; color: #1e293b; }
        
        .item-list .item-row {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #eef2f6;
            align-items: center;
        }
        .item-list .item-row:last-child { border-bottom: none; }
        .item-list .item-img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            background: #f8fafc;
        }
        .item-list .item-info {
            flex: 1;
        }
        .item-list .item-info .name {
            font-size: 13px;
            font-weight: 600;
        }
        .item-list .item-info .qty {
            font-size: 12px;
            color: #64748b;
        }
        .item-list .item-info .price {
            font-size: 13px;
            font-weight: 700;
            color: #e67e22;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            font-size: 16px;
            font-weight: 700;
            border-top: 2px solid #e2e8f0;
            margin-top: 10px;
        }
        .total-row .amount { color: #e67e22; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: 0.2s;
            margin-top: 16px;
        }
        .btn-back:hover {
            border-color: #e67e22;
            color: #e67e22;
        }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #27ae60;
        }
        .live-indicator .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #27ae60;
            animation: pulse-dot 1.5s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .auto-refresh-info {
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
        }
        .auto-refresh-info i { color: #3498db; }
        
        /* Agent Info */
        .agent-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-top: 12px;
        }
        .agent-info .agent-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e67e22;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }
        .agent-info .agent-details h5 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .agent-info .agent-details p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .track-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 640px) {
            .customer-content { padding: 16px; }
            .track-header h1 { font-size: 22px; }
            .progress-steps { padding: 0; }
            .step-label { font-size: 7px; }
            .step-circle { width: 28px; height: 28px; font-size: 10px; }
            .progress-steps::before,
            .progress-steps::after { top: 14px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="track-header">
        <div>
            <h1><i class="fas fa-truck"></i> Track Order</h1>
            <div class="order-id">Order <?php echo $order_id; ?> · Placed on <?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
        </div>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span class="status-badge" style="background: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                <i class="fas <?php echo $status_info['icon']; ?>"></i>
                <?php echo $status_info['label']; ?>
            </span>
            <?php if ($current_status != 'delivered' && $current_status != 'cancelled'): ?>
            <span class="live-indicator">
                <span class="dot"></span> Live Tracking
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="track-grid">
        <!-- Main Tracking -->
        <div>
            <!-- Progress Steps -->
            <div class="track-card">
                <div class="card-header">
                    <h3><i class="fas fa-route"></i> Delivery Progress</h3>
                    <span style="font-size: 13px; font-weight: 600; color: #e67e22;">
                        <?php echo round(max(0, min(100, $progress_percentage))); ?>% Complete
                    </span>
                </div>
                <div class="card-body">
                    <div class="progress-container">
                        <div class="progress-steps">
                            <?php foreach ($progress_steps as $index => $step): ?>
                                <?php 
                                    $stepStatus = $statuses[$step];
                                    $isActive = ($index == $current_step);
                                    $isCompleted = ($index < $current_step);
                                    $circleClass = $isCompleted ? 'completed' : ($isActive ? 'active' : '');
                                    $labelClass = $isCompleted ? 'completed-label' : ($isActive ? 'active-label' : '');
                                ?>
                                <div class="step-item">
                                    <div class="step-circle <?php echo $circleClass; ?>">
                                        <?php if ($isCompleted): ?>
                                            <i class="fas fa-check"></i>
                                        <?php elseif ($isActive): ?>
                                            <i class="fas fa-spinner fa-spin"></i>
                                        <?php else: ?>
                                            <span><?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="step-label <?php echo $labelClass; ?>">
                                        <?php echo $stepStatus['label']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="auto-refresh-info">
                        <i class="fas fa-sync-alt"></i>
                        Auto-refreshes every 30 seconds
                    </div>
                </div>
            </div>

            <!-- Status Info -->
            <div class="track-card" style="margin-top: 16px;">
                <div class="card-body">
                    <div class="status-info">
                        <div class="icon">
                            <i class="fas <?php echo $status_info['icon']; ?>"></i>
                        </div>
                        <div class="content">
                            <h4><?php echo $status_info['label']; ?></h4>
                            <p><?php echo $status_info['description']; ?></p>
                            
                            <?php if (!empty($order['agent_first_name']) && $current_status != 'delivered'): ?>
                            <div class="agent-info">
                                <div class="agent-avatar">
                                    <?php echo strtoupper(substr($order['agent_first_name'], 0, 1) . substr($order['agent_last_name'] ?? '', 0, 1)); ?>
                                </div>
                                <div class="agent-details">
                                    <h5><?php echo htmlspecialchars($order['agent_first_name'] . ' ' . ($order['agent_last_name'] ?? '')); ?></h5>
                                    <p>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['agent_phone'] ?? 'N/A'); ?>
                                        <?php if (!empty($order['vehicle_type'])): ?>
                                            · <i class="fas fa-motorcycle"></i> <?php echo htmlspecialchars($order['vehicle_type']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="track-card" style="margin-top: 16px;">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Order Timeline</h3>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($tracking_history as $entry): ?>
                            <?php 
                                $isActive = ($entry['status'] == $current_status);
                                $isCompleted = (array_search($entry['status'], $progress_steps) < $current_step);
                                $statusData = $statuses[$entry['status']] ?? $statuses['pending'];
                            ?>
                            <div class="timeline-item <?php echo $isActive ? 'active' : ($isCompleted ? 'completed' : ''); ?>">
                                <div class="time"><?php echo date('h:i A', strtotime($entry['created_at'])); ?></div>
                                <div class="title">
                                    <i class="fas <?php echo $statusData['icon']; ?>" style="color: <?php echo $statusData['color']; ?>; margin-right: 6px;"></i>
                                    <?php echo $statusData['label']; ?>
                                </div>
                                <div class="desc"><?php echo $entry['note'] ?? $statusData['description']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <a href="details.php?id=<?php echo $order_id; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Order Details
            </a>
        </div>

        <!-- Sidebar - Order Details -->
        <div>
            <div class="track-card">
                <div class="card-header">
                    <h3><i class="fas fa-receipt"></i> Order Details</h3>
                </div>
                <div class="card-body">
                    <div class="detail-row">
                        <span class="label">Order ID</span>
                        <span class="value"> <?php echo $order_id; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Store</span>
                        <span class="value"><?php echo htmlspecialchars($order['business_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Store Address</span>
                        <span class="value" style="font-size: 12px;"><?php echo htmlspecialchars($order['business_address'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Delivery Address</span>
                        <span class="value" style="font-size: 12px;"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Payment Method</span>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'cash')); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Payment Status</span>
                        <span class="value" style="color: <?php echo ($order['payment_status'] ?? 'pending') == 'paid' ? '#27ae60' : '#f39c12'; ?>;">
                            <?php echo ucfirst($order['payment_status'] ?? 'pending'); ?>
                        </span>
                    </div>
                    
                    <div style="margin-top: 16px; font-weight: 600; font-size: 14px; color: #334155;">
                        Items (<?php echo count($order_items); ?>)
                    </div>
                    <div class="item-list">
                        <?php foreach ($order_items as $item): ?>
                            <div class="item-row">
                                <img src="<?php echo !empty($item['image_url']) ? '../../' . $item['image_url'] : '../../assets/images/default-product.jpg'; ?>" 
                                     class="item-img" 
                                     onerror="this.src='../../assets/images/default-product.jpg'">
                                <div class="item-info">
                                    <div class="name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="qty">Qty: <?php echo $item['quantity']; ?></div>
                                </div>
                                <div class="price">TSh <?php echo number_format($item['unit_price'], 0, '.', ','); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="detail-row" style="margin-top: 8px;">
                        <span class="label">Subtotal</span>
                        <span class="value">TSh <?php echo number_format($order['total_amount'] ?? 0, 0, '.', ','); ?></span>
                    </div>
                    <?php if (!empty($order['delivery_fee'])): ?>
                    <div class="detail-row">
                        <span class="label">Delivery Fee</span>
                        <span class="value">TSh <?php echo number_format($order['delivery_fee'], 0, '.', ','); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row">
                        <span>Grand Total</span>
                        <span class="amount">TSh <?php echo number_format($order['grand_total'] ?? 0, 0, '.', ','); ?></span>
                    </div>
                    
                    <?php if (!empty($order['special_instructions'])): ?>
                        <div style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 10px; font-size: 13px; color: #64748b;">
                            <strong>Special Instructions:</strong><br>
                            <?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// AUTO-REFRESH TRACKING EVERY 30 SECONDS
// ============================================
let refreshInterval = null;

function refreshTracking() {
    fetch(window.location.href, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.text())
    .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Update progress steps
        const newProgress = doc.querySelector('.progress-steps');
        if (newProgress) {
            document.querySelector('.progress-steps').innerHTML = newProgress.innerHTML;
        }
        
        // Update progress percentage
        const newPercentage = doc.querySelector('.card-header span[style*="color: #e67e22;"]');
        if (newPercentage) {
            document.querySelector('.card-header span[style*="color: #e67e22;"]').textContent = newPercentage.textContent;
        }
        
        // Update status info
        const newStatus = doc.querySelector('.status-info');
        if (newStatus) {
            document.querySelector('.status-info').innerHTML = newStatus.innerHTML;
        }
        
        // Update status badge
        const newBadge = doc.querySelector('.status-badge');
        if (newBadge) {
            document.querySelector('.status-badge').innerHTML = newBadge.innerHTML;
        }
        
        // Update timeline
        const newTimeline = doc.querySelector('.timeline');
        if (newTimeline) {
            document.querySelector('.timeline').innerHTML = newTimeline.innerHTML;
        }
        
        // Update agent info
        const newAgent = doc.querySelector('.agent-info');
        if (newAgent) {
            const currentAgent = document.querySelector('.agent-info');
            if (currentAgent) {
                currentAgent.innerHTML = newAgent.innerHTML;
            }
        }
    })
    .catch(err => console.log('Auto-refresh error:', err));
}

// Start auto-refresh
function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    refreshInterval = setInterval(refreshTracking, 30000);
}

// Stop when tab is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (refreshInterval) clearInterval(refreshInterval);
    } else {
        refreshTracking();
        startAutoRefresh();
    }
});

startAutoRefresh();
</script>

</body>
</html>