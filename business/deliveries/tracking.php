<?php
// business/deliveries/tracking.php - Fixed Display
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
// GET DELIVERY DETAILS WITH LOCATIONS
// ============================================================
$sql = "SELECT 
    d.*,
    o.order_id,
    o.delivery_address,
    c.delivery_latitude as customer_lat,
    c.delivery_longitude as customer_lng,
    o.order_date,
    o.status as order_status,
    c.customer_id,
    c.first_name,
    c.last_name,
    u.phone as customer_phone,
    u.email as customer_email,
    a.agent_id,
    a.first_name as agent_first_name,
    a.last_name as agent_last_name,
    a.phone as agent_phone,
    a.vehicle_type,
    a.vehicle_registration,
    a.current_latitude as agent_lat,
    a.current_longitude as agent_lng,
    b.business_id,
    b.business_name,
    b.location as business_location,
    b.latitude as business_lat,
    b.longitude as business_lng
FROM deliveries d
JOIN orders o ON d.order_id = o.order_id
JOIN customers c ON o.customer_id = c.customer_id
JOIN users u ON c.user_id = u.user_id
LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
JOIN businesses b ON o.business_id = b.business_id
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
// GET DELIVERY HISTORY / TIMELINE
// ============================================================
$history_sql = "SELECT * FROM delivery_history WHERE delivery_id = '$delivery_id' ORDER BY created_at ASC";
$history_result = mysqli_query($conn, $history_sql);
$history = [];
if ($history_result) {
    while ($row = mysqli_fetch_assoc($history_result)) {
        $history[] = $row;
    }
}

// If no history, create default entries based on status
if (empty($history)) {
    $default_statuses = ['pending', 'assigned', 'picked_up', 'in_transit', 'delivered'];
    $status_index = array_search($delivery['status'], $default_statuses);
    if ($status_index === false) $status_index = 0;
    
    for ($i = 0; $i <= $status_index; $i++) {
        $history[] = [
            'status' => $default_statuses[$i],
            'notes' => ucfirst($default_statuses[$i]) . ' status',
            'created_at' => date('Y-m-d H:i:s', strtotime($delivery['order_date'] . ' +' . ($i * 5) . ' minutes'))
        ];
    }
}

// ============================================================
// CHECK IF LOCATIONS ARE SET
// ============================================================
$hasBusinessLocation = ($delivery['business_lat'] && $delivery['business_lng']);
$hasAgentLocation = ($delivery['agent_lat'] && $delivery['agent_lng']);
$hasCustomerLocation = ($delivery['customer_lat'] && $delivery['customer_lng']);
$hasAnyLocation = $hasBusinessLocation || $hasAgentLocation || $hasCustomerLocation;

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

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Track Delivery - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        
        .tracking-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width { grid-column: 1 / -1; }
        
        #map {
            height: 450px;
            width: 100%;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            z-index: 1;
        }
        
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
        .card-body { padding: 1.25rem; }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            padding: 0.4rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 120px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.75rem;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: #0f172a;
            font-weight: 500;
        }
        
        .location-point {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.2s;
        }
        .location-point:hover {
            background: #fdf2e9;
            border-color: #e67e22;
        }
        .location-point .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: white;
            font-size: 1rem;
        }
        .location-point .icon.business { background: #e67e22; }
        .location-point .icon.agent { background: #3b82f6; }
        .location-point .icon.customer { background: #10b981; }
        .location-point .info { flex: 1; }
        .location-point .info .name { font-weight: 700; font-size: 0.9rem; }
        .location-point .info .detail { font-size: 0.75rem; color: #64748b; }
        .location-point .coords { 
            font-size: 0.65rem; 
            color: #94a3b8; 
            font-family: monospace;
            background: white;
            padding: 0.2rem 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .location-point .coords.has-location {
            color: #10b981;
            border-color: #10b981;
            background: #ecfdf5;
        }
        
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 0.3rem;
            animation: pulse-dot 1.5s infinite;
        }
        .status-dot.active { background: #10b981; }
        .status-dot.inactive { background: #94a3b8; animation: none; }
        
        @keyframes pulse-dot {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .distance-info {
            background: #e0f2fe;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            color: #0369a1;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
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
            padding: 0.6rem 0 0.6rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 1rem;
            width: 14px;
            height: 14px;
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
            gap: 0.3rem;
        }
        .timeline-item .tl-time {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 0.1rem;
        }
        .timeline-item .tl-note {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.2rem;
            padding: 0.2rem 0.5rem;
            background: #f8fafc;
            border-radius: 0.25rem;
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
        
        .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        
        @media (max-width: 1100px) {
            .tracking-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
        }
        @media (max-width: 768px) {
            .info-row { flex-direction: column; gap: 0.2rem; }
            .info-label { width: 100%; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .location-point { flex-wrap: wrap; }
            #map { height: 300px; }
            .location-point .coords { width: 100%; text-align: center; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-map-marked-alt"></i> Track Delivery</h1>
            <p>Real-time tracking for delivery #<?php echo $delivery_id; ?> · Order #<?php echo $delivery['order_id']; ?></p>
        </div>
        <a href="view.php?id=<?php echo $delivery_id; ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Details
        </a>
    </div>

    <!-- Tracking Grid -->
    <div class="tracking-grid">
        <!-- Map -->
        <div class="card full-width">
            <div class="card-header">
                <h3><i class="fas fa-map"></i> Live Tracking</h3>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                    <span class="status-badge" style="background: <?php echo $status_info['bg']; ?>; color: <?php echo $status_info['color']; ?>;">
                        <i class="fas <?php echo $status_info['icon']; ?>"></i>
                        <?php echo $status_info['label']; ?>
                    </span>
                    <?php if(!$hasAnyLocation): ?>
                        <span style="font-size:0.6rem; background:#fee2e2; color:#dc2626; padding:0.2rem 0.5rem; border-radius:1rem;">
                            <i class="fas fa-exclamation-triangle"></i> No locations set
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="map"></div>
            </div>
        </div>

        <!-- Delivery Info -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Delivery Information</h3>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Delivery ID</span>
                    <span class="info-value"><strong>#<?php echo $delivery_id; ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order ID</span>
                    <span class="info-value"><a href="../orders/view.php?id=<?php echo $delivery['order_id']; ?>">#<?php echo $delivery['order_id']; ?></a></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-dot <?php echo $delivery['status'] == 'delivered' || $delivery['status'] == 'cancelled' ? 'inactive' : 'active'; ?>"></span>
                        <span style="color: <?php echo $status_info['color']; ?>; font-weight:700;">
                            <?php echo $status_info['label']; ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Distance</span>
                    <span class="info-value">
                        <span class="distance-info" id="distanceDisplay">
                            <i class="fas fa-route"></i> Calculating...
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($delivery['order_date'])); ?></span>
                </div>
                <?php if ($delivery['delivered_at']): ?>
                <div class="info-row">
                    <span class="info-label">Delivered At</span>
                    <span class="info-value" style="color:#10b981;"><?php echo date('M d, Y h:i A', strtotime($delivery['delivered_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($delivery['agent_first_name']): ?>
                <div class="info-row">
                    <span class="info-label">Delivery Agent</span>
                    <span class="info-value">
                        <i class="fas fa-user" style="color:#3b82f6;"></i>
                        <?php echo htmlspecialchars($delivery['agent_first_name'] . ' ' . $delivery['agent_last_name']); ?>
                        <?php if ($delivery['vehicle_type']): ?>
                            <span style="font-size:0.7rem; color:#64748b;">(<?php echo ucfirst($delivery['vehicle_type']); ?>)</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Points -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-location-dot"></i> Locations</h3>
                <?php if($hasAnyLocation): ?>
                    <span class="badge-count" style="background:#d1fae5; color:#059669;">
                        <i class="fas fa-check-circle"></i> <?php echo ($hasBusinessLocation?1:0) + ($hasAgentLocation?1:0) + ($hasCustomerLocation?1:0); ?>/3 set
                    </span>
                <?php else: ?>
                    <span class="badge-count" style="background:#fee2e2; color:#dc2626;">
                        <i class="fas fa-times-circle"></i> No locations
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Business -->
                <div class="location-point">
                    <div class="icon business"><i class="fas fa-store"></i></div>
                    <div class="info">
                        <div class="name">🏪 <?php echo htmlspecialchars($delivery['business_name']); ?></div>
                        <div class="detail"><?php echo htmlspecialchars($delivery['business_location'] ?: 'Business location not set'); ?></div>
                    </div>
                    <div class="coords <?php echo $hasBusinessLocation ? 'has-location' : ''; ?>">
                        <?php if ($hasBusinessLocation): ?>
                            <i class="fas fa-check-circle" style="color:#10b981;"></i>
                            <?php echo number_format($delivery['business_lat'], 6); ?>, <?php echo number_format($delivery['business_lng'], 6); ?>
                        <?php else: ?>
                            <i class="fas fa-times-circle" style="color:#dc2626;"></i> Not set
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Agent -->
                <div class="location-point">
                    <div class="icon agent"><i class="fas fa-motorcycle"></i></div>
                    <div class="info">
                        <div class="name">
                            <?php if ($delivery['agent_first_name']): ?>
                                🛵 <?php echo htmlspecialchars($delivery['agent_first_name'] . ' ' . $delivery['agent_last_name']); ?>
                                <span style="font-size:0.6rem; color:#64748b; font-weight:400;">
                                    (<?php echo ucfirst($delivery['vehicle_type'] ?? 'N/A'); ?>)
                                </span>
                            <?php else: ?>
                                🛵 No agent assigned
                            <?php endif; ?>
                        </div>
                        <div class="detail">
                            <?php if ($delivery['agent_phone']): ?>
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['agent_phone']); ?>
                            <?php endif; ?>
                            <?php if ($delivery['vehicle_registration']): ?>
                                • <i class="fas fa-id-card"></i> <?php echo strtoupper($delivery['vehicle_registration']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="coords <?php echo $hasAgentLocation ? 'has-location' : ''; ?>">
                        <?php if ($hasAgentLocation): ?>
                            <i class="fas fa-check-circle" style="color:#10b981;"></i>
                            <?php echo number_format($delivery['agent_lat'], 6); ?>, <?php echo number_format($delivery['agent_lng'], 6); ?>
                        <?php else: ?>
                            <i class="fas fa-times-circle" style="color:#dc2626;"></i> Not set
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Customer -->
                <div class="location-point">
                    <div class="icon customer"><i class="fas fa-user"></i></div>
                    <div class="info">
                        <div class="name">👤 <?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></div>
                        <div class="detail">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($delivery['customer_phone']); ?>
                            <span style="margin:0 0.3rem;">•</span>
                            <?php echo htmlspecialchars(substr($delivery['delivery_address'] ?: 'No address', 0, 40)); ?>
                            <?php if (strlen($delivery['delivery_address'] ?? '') > 40): ?>...<?php endif; ?>
                        </div>
                    </div>
                    <div class="coords <?php echo $hasCustomerLocation ? 'has-location' : ''; ?>">
                        <?php if ($hasCustomerLocation): ?>
                            <i class="fas fa-check-circle" style="color:#10b981;"></i>
                            <?php echo number_format($delivery['customer_lat'], 6); ?>, <?php echo number_format($delivery['customer_lng'], 6); ?>
                        <?php else: ?>
                            <i class="fas fa-times-circle" style="color:#dc2626;"></i> Not set
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Delivery Address Full -->
                <?php if (!empty($delivery['delivery_address'])): ?>
                <div style="margin-top:0.8rem; padding:0.75rem; background:#f0fdf4; border-radius:0.5rem; border:1px solid #bbf7d0;">
                    <div style="font-size:0.7rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">
                        <i class="fas fa-map-pin"></i> Delivery Address
                    </div>
                    <div style="font-size:0.85rem; font-weight:500; color:#0f172a; margin-top:0.2rem;">
                        <?php echo htmlspecialchars($delivery['delivery_address']); ?>
                    </div>
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
                        <p>No timeline events available</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($history as $item): 
                            $is_active = ($item['status'] == $delivery['status']);
                            $status_class = $is_active ? 'active' : 'done';
                            $item_status = $statuses[$item['status']] ?? $statuses['pending'];
                        ?>
                        <div class="timeline-item <?php echo $status_class; ?>">
                            <div class="tl-status">
                                <i class="fas <?php echo $item_status['icon']; ?>" style="color: <?php echo $item_status['color']; ?>;"></i>
                                <?php echo $item_status['label']; ?>
                                <?php if ($is_active): ?>
                                    <span style="font-size:0.55rem; background:#e67e22; color:white; padding:0.1rem 0.5rem; border-radius:1rem; margin-left:0.3rem;">Current</span>
                                <?php endif; ?>
                            </div>
                            <div class="tl-time">
                                <i class="far fa-clock"></i> 
                                <?php echo date('M d, Y h:i A', strtotime($item['created_at'] ?? date('Y-m-d H:i:s'))); ?>
                            </div>
                            <?php if (!empty($item['notes'])): ?>
                                <div class="tl-note">
                                    <i class="fas fa-sticky-note" style="color:#e67e22; opacity:0.5;"></i>
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

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // LOCATION DATA FROM PHP
    // ============================================================
    const locations = {
        business: {
            name: "<?php echo addslashes($delivery['business_name']); ?>",
            lat: <?php echo $delivery['business_lat'] ?? 'null'; ?>,
            lng: <?php echo $delivery['business_lng'] ?? 'null'; ?>,
            icon: 'fa-store',
            color: '#e67e22',
            hasLocation: <?php echo $hasBusinessLocation ? 'true' : 'false'; ?>
        },
        agent: {
            name: "<?php echo addslashes($delivery['agent_first_name'] . ' ' . $delivery['agent_last_name']); ?>",
            lat: <?php echo $delivery['agent_lat'] ?? 'null'; ?>,
            lng: <?php echo $delivery['agent_lng'] ?? 'null'; ?>,
            icon: 'fa-motorcycle',
            color: '#3b82f6',
            hasLocation: <?php echo $hasAgentLocation ? 'true' : 'false'; ?>
        },
        customer: {
            name: "<?php echo addslashes($delivery['first_name'] . ' ' . $delivery['last_name']); ?>",
            lat: <?php echo $delivery['customer_lat'] ?? 'null'; ?>,
            lng: <?php echo $delivery['customer_lng'] ?? 'null'; ?>,
            icon: 'fa-user',
            color: '#10b981',
            hasLocation: <?php echo $hasCustomerLocation ? 'true' : 'false'; ?>
        }
    };

    const hasAnyLocation = <?php echo $hasAnyLocation ? 'true' : 'false'; ?>;

    // ============================================================
    // CREATE CUSTOM ICONS
    // ============================================================
    function createIcon(color, icon) {
        return L.divIcon({
            html: `<i class="fas ${icon}" style="color:white; font-size:16px;"></i>`,
            className: 'custom-marker',
            iconSize: [40, 40],
            iconAnchor: [20, 40],
            popupAnchor: [0, -40]
        });
    }

    // ============================================================
    // INITIALIZE MAP
    // ============================================================
    const map = L.map('map', {
        zoomControl: true,
        attributionControl: true
    });

    // Tile Layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // ============================================================
    // ADD MARKERS AND ROUTE
    // ============================================================
    const validLocations = [];
    const markers = [];

    // Business Marker
    if (locations.business.hasLocation && locations.business.lat && locations.business.lng) {
        const marker = L.marker([locations.business.lat, locations.business.lng], {
            icon: createIcon('#e67e22', 'fa-store')
        }).addTo(map);
        marker.bindPopup(`
            <strong>🏪 Business</strong><br>
            ${locations.business.name}
        `);
        markers.push(marker);
        validLocations.push({ lat: locations.business.lat, lng: locations.business.lng, type: 'business' });
    }

    // Agent Marker
    if (locations.agent.hasLocation && locations.agent.lat && locations.agent.lng) {
        const marker = L.marker([locations.agent.lat, locations.agent.lng], {
            icon: createIcon('#3b82f6', 'fa-motorcycle')
        }).addTo(map);
        marker.bindPopup(`
            <strong>🛵 Delivery Agent</strong><br>
            ${locations.agent.name}
        `);
        markers.push(marker);
        validLocations.push({ lat: locations.agent.lat, lng: locations.agent.lng, type: 'agent' });
    }

    // Customer Marker
    if (locations.customer.hasLocation && locations.customer.lat && locations.customer.lng) {
        const marker = L.marker([locations.customer.lat, locations.customer.lng], {
            icon: createIcon('#10b981', 'fa-user')
        }).addTo(map);
        marker.bindPopup(`
            <strong>👤 Customer</strong><br>
            ${locations.customer.name}
        `);
        markers.push(marker);
        validLocations.push({ lat: locations.customer.lat, lng: locations.customer.lng, type: 'customer' });
    }

    // ============================================================
    // DRAW ROUTE
    // ============================================================
    if (validLocations.length >= 2) {
        const routePoints = validLocations.map(loc => [loc.lat, loc.lng]);
        const polyline = L.polyline(routePoints, {
            color: '#e67e22',
            weight: 3,
            opacity: 0.7,
            dashArray: '8, 8',
            smoothFactor: 1
        }).addTo(map);

        const bounds = L.latLngBounds(routePoints);
        map.fitBounds(bounds, { padding: [50, 50] });

        function calculateTotalDistance(points) {
            let total = 0;
            for (let i = 1; i < points.length; i++) {
                const p1 = L.latLng(points[i-1][0], points[i-1][1]);
                const p2 = L.latLng(points[i][0], points[i][1]);
                total += p1.distanceTo(p2);
            }
            return total / 1000;
        }

        const totalKm = calculateTotalDistance(routePoints);
        if (totalKm > 0) {
            document.getElementById('distanceDisplay').innerHTML = `
                <i class="fas fa-route"></i> ${totalKm.toFixed(1)} km total
            `;
        } else {
            document.getElementById('distanceDisplay').innerHTML = `
                <i class="fas fa-route"></i> Distance not available
            `;
        }

        const routeLabel = L.control({ position: 'bottomleft' });
        routeLabel.onAdd = function() {
            const div = L.DomUtil.create('div', 'route-info');
            div.innerHTML = `
                <div style="background:white; padding:8px 14px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); font-size:12px;">
                    <strong>📍 Route</strong><br>
                    ${validLocations.length} locations • ${totalKm.toFixed(1)} km
                </div>
            `;
            return div;
        };
        routeLabel.addTo(map);
    } else if (validLocations.length == 1) {
        map.setView([validLocations[0].lat, validLocations[0].lng], 13);
        document.getElementById('distanceDisplay').innerHTML = `
            <i class="fas fa-route"></i> Only one location available
        `;
    } else {
        map.setView([-6.792354, 39.208328], 13);
        document.getElementById('distanceDisplay').innerHTML = `
            <i class="fas fa-route"></i> No location data available
        `;
        
        const noLocationControl = L.control({ position: 'topright' });
        noLocationControl.onAdd = function() {
            const div = L.DomUtil.create('div', 'no-location-info');
            div.innerHTML = `
                <div style="background:#fee2e2; padding:10px 14px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); border-left:4px solid #dc2626; max-width:250px;">
                    <strong><i class="fas fa-exclamation-triangle"></i> No Locations Set</strong>
                    <p style="font-size:12px; margin-top:4px; color:#991b1b;">
                        Please set locations for Business, Agent, and Customer to enable tracking.
                    </p>
                </div>
            `;
            return div;
        };
        noLocationControl.addTo(map);
    }

    // ============================================================
    // ADD LEGEND
    // ============================================================
    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = function() {
        const div = L.DomUtil.create('div', 'legend');
        div.style.background = 'white';
        div.style.padding = '8px 12px';
        div.style.borderRadius = '10px';
        div.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        div.style.fontSize = '11px';
        div.innerHTML = `
            <div><span style="display:inline-block; width:12px; height:12px; background:#e67e22; border-radius:50%; margin-right:6px;"></span> Business</div>
            <div><span style="display:inline-block; width:12px; height:12px; background:#3b82f6; border-radius:50%; margin-right:6px;"></span> Delivery Agent</div>
            <div><span style="display:inline-block; width:12px; height:12px; background:#10b981; border-radius:50%; margin-right:6px;"></span> Customer</div>
            ${validLocations.length >= 2 ? `
                <div style="margin-top:4px; border-top:1px solid #e2e8f0; padding-top:4px;">
                    <span style="display:inline-block; width:20px; height:2px; background:#e67e22; margin-right:6px;"></span> Route (${validLocations.length} points)
                </div>
            ` : ''}
        `;
        return div;
    };
    legend.addTo(map);

    // ============================================================
    // REFRESH MAP ON RESIZE
    // ============================================================
    setTimeout(() => {
        map.invalidateSize();
    }, 500);

    // ============================================================
    // SIDEBAR ACTIVE LINK
    // ============================================================
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../deliveries/tracking.php' || 
            links[i].getAttribute('href') === 'tracking.php') {
            links[i].classList.add('active');
        }
    }
});
</script>

<style>
    .custom-marker {
        background: none !important;
        border: none !important;
    }
    .custom-marker i {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        background: #e67e22;
    }
    .custom-marker i.fa-store { background: #e67e22; }
    .custom-marker i.fa-motorcycle { background: #3b82f6; }
    .custom-marker i.fa-user { background: #10b981; }
    
    .leaflet-popup-content-wrapper {
        border-radius: 10px !important;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
    }
    .leaflet-popup-content {
        font-family: 'Inter', sans-serif !important;
        font-size: 13px !important;
        padding: 8px 12px !important;
    }
    .route-info {
        font-family: 'Inter', sans-serif;
    }
    .no-location-info {
        font-family: 'Inter', sans-serif;
    }
</style>

</body>
</html>