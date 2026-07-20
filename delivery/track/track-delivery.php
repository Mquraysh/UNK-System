<?php
// delivery/track/track-delivery.php - COMPLETE DELIVERY TRACKING SYSTEM
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id <= 0) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// Get agent info
$agent_sql = "SELECT agent_id, first_name, last_name, phone, vehicle_type, vehicle_registration 
              FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$agent_id = $agent['agent_id'];

// ============================================
// GET DELIVERY DETAILS
// ============================================
$sql = "SELECT 
            d.delivery_id,
            d.order_id,
            d.status,
            d.delivery_fee,
            d.delivered_at,
            d.created_at,
            d.pickup_address,
            d.delivery_address,
            b.latitude,
            b.longitude,
            c.delivery_latitude,
            c.delivery_longitude,
            d.assigned_at,
            d.picked_up_at,
            d.estimated_distance,
            d.estimated_time,
            d.rating,
            d.rating_comment,
            d.rated_at,
            o.delivery_address as order_delivery_address,
            o.order_date,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.city,
            c.saved_address as customer_saved_address,
            u.phone as customer_phone,
            b.business_name,
            b.location as business_address,
            b.phone as business_phone,
            b.latitude as business_latitude,
            b.longitude as business_longitude
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        JOIN businesses b ON o.business_id = b.business_id
        WHERE d.delivery_id = '$delivery_id' 
        AND d.agent_id = '$agent_id'";

$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// ============================================
// FIX: SET ADDRESS FROM BUSINESS AND CUSTOMER
// ============================================
if (empty($delivery['pickup_address'])) {
    $delivery['pickup_address'] = $delivery['business_address'] ?? 'Business Location';
}

if (empty($delivery['delivery_address'])) {
    $delivery['delivery_address'] = $delivery['customer_saved_address'] ?? $delivery['order_delivery_address'] ?? 'Customer Address';
}

if (empty($delivery['business_latitude']) && !empty($delivery['pickup_latitude'])) {
    $delivery['business_latitude'] = $delivery['pickup_latitude'];
    $delivery['business_longitude'] = $delivery['pickup_longitude'];
}

// Default Dar es Salaam coordinates
$default_lat = -6.7924;
$default_lng = 39.2083;

// ============================================
// GET LATEST LOCATION FROM delivery_tracking
// ============================================
$current_lat = $delivery['pickup_latitude'] ?? $default_lat;
$current_lng = $delivery['pickup_longitude'] ?? $default_lng;

// Check if delivery_tracking table exists
$table_check = "SHOW TABLES LIKE 'delivery_tracking'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) > 0) {
    // Check if latitude column exists
    $col_check = "SHOW COLUMNS FROM delivery_tracking LIKE 'latitude'";
    $col_result = mysqli_query($conn, $col_check);
    $has_latitude = mysqli_num_rows($col_result) > 0;
    
    if ($has_latitude) {
        $latest_sql = "SELECT latitude, longitude, status, created_at 
                       FROM delivery_tracking 
                       WHERE delivery_id = '$delivery_id' 
                       ORDER BY created_at DESC 
                       LIMIT 1";
        $latest_result = mysqli_query($conn, $latest_sql);
        $latest_location = mysqli_fetch_assoc($latest_result);
        
        if ($latest_location) {
            $current_lat = $latest_location['latitude'] ?? $current_lat;
            $current_lng = $latest_location['longitude'] ?? $current_lng;
        }
    }
}

// ============================================
// UPDATE STATUS (AJAX HANDLER)
// ============================================
if (isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $allowed = ['assigned', 'picked_up', 'in_transit', 'nearby', 'delivered'];
    
    if (in_array($new_status, $allowed)) {
        $update_sql = "UPDATE deliveries SET status = '$new_status' WHERE delivery_id = '$delivery_id'";
        mysqli_query($conn, $update_sql);
        
        // Add tracking entry
        $track_sql = "INSERT INTO delivery_tracking (delivery_id, latitude, longitude, status, created_at) 
                      SELECT '$delivery_id', pickup_latitude, pickup_longitude, '$new_status', NOW()
                      FROM deliveries WHERE delivery_id = '$delivery_id'";
        mysqli_query($conn, $track_sql);
        
        if ($new_status == 'delivered') {
            $order_sql = "SELECT order_id FROM deliveries WHERE delivery_id = '$delivery_id'";
            $order_result = mysqli_query($conn, $order_sql);
            $order = mysqli_fetch_assoc($order_result);
            if ($order) {
                mysqli_query($conn, "UPDATE orders SET status = 'delivered' WHERE order_id = '{$order['order_id']}'");
            }
        }
        
        echo json_encode(['success' => true, 'status' => $new_status]);
        exit();
    }
}

// ============================================
// UPDATE LOCATION (AJAX HANDLER)
// ============================================
if (isset($_POST['update_location'])) {
    $lat = (float)$_POST['lat'];
    $lng = (float)$_POST['lng'];
    $status = $_POST['status'] ?? $delivery['status'];
    $notes = $_POST['notes'] ?? '';
    
    // Insert into delivery_tracking
    $track_sql = "INSERT INTO delivery_tracking (delivery_id, latitude, longitude, status, created_at) 
                  VALUES ('$delivery_id', '$lat', '$lng', '$status', NOW())";
    mysqli_query($conn, $track_sql);
    
    echo json_encode(['success' => true]);
    exit();
}

// ============================================
// GET LOCATION HISTORY (AJAX)
// ============================================
if (isset($_GET['get_locations'])) {
    $history_sql = "SELECT latitude, longitude, status, created_at as recorded_at 
                    FROM delivery_tracking 
                    WHERE delivery_id = '$delivery_id' 
                    ORDER BY created_at ASC";
    $history_result = mysqli_query($conn, $history_sql);
    $updates = [];
    while ($row = mysqli_fetch_assoc($history_result)) {
        $updates[] = $row;
    }
    echo json_encode($updates);
    exit();
}

// ============================================
// GET PRODUCTS
// ============================================
$products_sql = "SELECT 
                    oi.order_item_id,
                    oi.product_id,
                    oi.quantity,
                    p.name,
                    p.price as product_price,
                    p.image_url
                 FROM order_items oi
                 JOIN products p ON oi.product_id = p.product_id
                 WHERE oi.order_id = '{$delivery['order_id']}'";
$products_result = mysqli_query($conn, $products_sql);
$products = [];
if ($products_result) {
    while ($row = mysqli_fetch_assoc($products_result)) {
        $products[] = $row;
    }
}

// Calculate delivery progress
$progress = 0;
$status_steps = [
    'pending' => 0,
    'assigned' => 20,
    'picked_up' => 40,
    'in_transit' => 60,
    'nearby' => 80,
    'delivered' => 100
];
$progress = $status_steps[$delivery['status']] ?? 0;

$status_icons = [
    'pending' => 'fa-clock',
    'assigned' => 'fa-user-check',
    'picked_up' => 'fa-box-open',
    'in_transit' => 'fa-truck',
    'nearby' => 'fa-location-dot',
    'delivered' => 'fa-flag-checkered'
];

$status_colors = [
    'pending' => '#f39c12',
    'assigned' => '#3498db',
    'picked_up' => '#8e44ad',
    'in_transit' => '#4338ca',
    'nearby' => '#e67e22',
    'delivered' => '#27ae60'
];

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Delivery #<?php echo $delivery_id; ?> | UNK System</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        .page-header .delivery-id {
            background: #e67e22;
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .page-header .status-badge {
            padding: 0.3rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            background: <?php echo $status_colors[$delivery['status']] ?? '#64748b'; ?>;
        }
        
        .tracking-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        .map-container {
            background: white;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            min-height: 500px;
            position: relative;
        }
        .map-container #map {
            width: 100%;
            height: 500px;
            border: none;
            background: #f0f2f5;
        }
        
        .side-panel {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .info-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
        }
        .info-card h3 {
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-card h3 i { color: #e67e22; }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            font-size: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row .label { color: #64748b; }
        .info-row .value { font-weight: 600; color: #0f172a; }
        .info-row:last-child { border-bottom: none; }
        
        .progress-container {
            margin: 1rem 0;
        }
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #e67e22, #f39c12);
            border-radius: 4px;
            transition: width 1s ease;
            width: <?php echo $progress; ?>%;
        }
        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.65rem;
            color: #64748b;
            margin-top: 0.3rem;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item {
            position: relative;
            padding: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 0.7rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #e2e8f0;
        }
        .timeline-item.active::before {
            background: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.3);
        }
        .timeline-item.completed::before {
            background: #27ae60;
            box-shadow: 0 0 0 2px #27ae60;
        }
        .timeline-item .time {
            font-size: 0.6rem;
            color: #94a3b8;
        }
        .timeline-item .title {
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); }
        .btn-outline { background: transparent; border: 2px solid #e2e8f0; color: #64748b; }
        .btn-outline:hover { border-color: #e67e22; color: #e67e22; transform: translateY(-2px); }
        
        .products-list {
            margin-top: 0.5rem;
        }
        .product-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-item img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .product-item .name {
            font-size: 0.8rem;
            font-weight: 500;
            color: #0f172a;
        }
        .product-item .qty {
            font-size: 0.7rem;
            color: #64748b;
        }
        
        .status-select {
            padding: 0.3rem 0.6rem;
            border-radius: 0.4rem;
            border: 1px solid #e2e8f0;
            background: white;
            font-size: 0.7rem;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
        }
        .status-select:focus { outline: none; border-color: #e67e22; }
        
        .live-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .live-btn:hover { background: #c0392b; }
        
        .live-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.7rem;
            color: #27ae60;
            font-weight: 600;
        }
        .live-indicator .dot {
            width: 8px;
            height: 8px;
            background: #27ae60;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }
        
        .custom-marker {
            font-size: 28px;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            line-height: 40px;
        }
        
        .order-total {
            border-top: 2px solid #e2e8f0;
            padding-top: 0.75rem;
            margin-top: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 0.95rem;
        }
        .order-total .label { color: #0f172a; }
        .order-total .amount { color: #e67e22; font-size: 1.1rem; }
        
        .gps-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            border-radius: 0.5rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        .gps-status .dot-green {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #27ae60;
            display: inline-block;
            animation: blink 1.5s infinite;
        }
        .gps-status .dot-red {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e74c3c;
            display: inline-block;
        }
        
        @media (max-width: 1024px) {
            .tracking-grid { grid-template-columns: 1fr; }
            .map-container #map { height: 350px; }
            .map-container { min-height: 350px; }
        }
        @media (max-width: 768px) {
            .map-container #map { height: 280px; }
            .map-container { min-height: 280px; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-map-location-dot"></i>
                Track Delivery
                <span class="delivery-id">#<?php echo $delivery_id; ?></span>
            </h1>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <span class="live-indicator" id="liveIndicator" style="display: <?php echo ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed') ? 'inline-flex' : 'none'; ?>;">
                <span class="dot"></span> Live Tracking
            </span>
            <span class="gps-status" id="gpsStatus">
                <span class="dot-red" id="gpsDot"></span>
                <span id="gpsText">GPS: Off</span>
            </span>
            <span class="status-badge" style="background: <?php echo $status_colors[$delivery['status']] ?? '#64748b'; ?>;">
                <i class="fas <?php echo $status_icons[$delivery['status']] ?? 'fa-circle'; ?>"></i>
                <?php echo str_replace('_', ' ', ucfirst($delivery['status'])); ?>
            </span>
            <a href="../my-deliveries/my-deliveries.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Tracking Grid -->
    <div class="tracking-grid">
        <!-- Map Section -->
        <div class="map-container">
            <div id="map"></div>
        </div>

        <!-- Side Panel -->
        <div class="side-panel">
            <!-- Progress Card -->
            <div class="info-card">
                <h3><i class="fas fa-chart-line"></i> Delivery Progress</h3>
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="fill" id="progressFill" style="width: <?php echo $progress; ?>%;"></div>
                    </div>
                    <div class="progress-label">
                        <span>Order Placed</span>
                        <span><?php echo $progress; ?>%</span>
                        <span>Delivered</span>
                    </div>
                </div>
                <div style="margin-top: 0.5rem; text-align: center; font-size: 0.7rem; color: #64748b;">
                    <i class="fas fa-clock"></i> 
                    Status: <?php echo str_replace('_', ' ', ucfirst($delivery['status'])); ?>
                    <?php if ($delivery['estimated_distance']): ?>
                        | <i class="fas fa-road"></i> <?php echo $delivery['estimated_distance']; ?> km
                    <?php endif; ?>
                </div>
                <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed'): ?>
                <div style="margin-top: 0.75rem; text-align: center;">
                    <button class="live-btn" id="startTrackingBtn" onclick="startLiveTracking()">
                        <i class="fas fa-location-dot"></i> Start Live Tracking
                    </button>
                    <div style="font-size: 0.6rem; color: #94a3b8; margin-top: 0.3rem;">
                        <i class="fas fa-info-circle"></i> Enable GPS for real-time tracking
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Delivery Details -->
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Delivery Details</h3>
                <div class="info-row">
                    <span class="label">Order ID</span>
                    <span class="value">#<?php echo $delivery['order_id']; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Business</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Customer</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Delivery Fee</span>
                    <span class="value" style="color: #e67e22;">TSh <?php echo number_format($delivery['delivery_fee'], 0, '.', ','); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Phone</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['customer_phone'] ?? $delivery['contact_phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Pickup Address</span>
                    <span class="value" style="font-size: 0.75rem;"><?php echo htmlspecialchars($delivery['pickup_address']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Delivery Address</span>
                    <span class="value" style="font-size: 0.75rem;"><?php echo htmlspecialchars($delivery['delivery_address']); ?></span>
                </div>
            </div>

            <!-- Products Card -->
            <div class="info-card">
                <h3>
                    <i class="fas fa-shopping-bag"></i> 
                    Products 
                    <span style="font-size: 0.7rem; font-weight: 400; color: #94a3b8;">
                        (<?php echo count($products); ?> items)
                    </span>
                </h3>
                <div class="products-list">
                    <?php if (empty($products)): ?>
                        <div style="text-align: center; color: #94a3b8; padding: 1rem; font-size: 0.8rem;">
                            <i class="fas fa-box-open" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;"></i>
                            No products in this order
                        </div>
                    <?php else: ?>
                        <?php 
                        $total_price = 0;
                        foreach ($products as $product): 
                            $price = $product['product_price'] ?? 0;
                            $subtotal = $price * $product['quantity'];
                            $total_price += $subtotal;
                            $img_path = '../../assets/images/default-product.jpg';
                            if (!empty($product['image_url'])) {
                                $full_path = '../../' . $product['image_url'];
                                if (file_exists($full_path)) {
                                    $img_path = $full_path;
                                }
                            }
                        ?>
                        <div class="product-item">
                            <img src="<?php echo $img_path; ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                 onerror="this.src='../../assets/images/default-product.jpg'">
                            <div style="flex: 1; min-width: 0;">
                                <div class="name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                <div class="qty">
                                    <span style="font-weight: 500;"><?php echo $product['quantity']; ?> ×</span>
                                    <span style="color: #e67e22; font-weight: 600;">
                                        TSh <?php echo number_format($price, 0, '.', ','); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="font-weight: 700; color: #0f172a; min-width: 100px; text-align: right; font-size: 0.85rem;">
                                TSh <?php echo number_format($subtotal, 0, '.', ','); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="order-total">
                            <span class="label">Order Total</span>
                            <span class="amount">TSh <?php echo number_format($total_price, 0, '.', ','); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update Status -->
            <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed'): ?>
            <div class="info-card">
                <h3><i class="fas fa-sync-alt"></i> Update Status</h3>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <select class="status-select" id="statusSelect">
                        <option value="">Select Status</option>
                        <option value="assigned" <?php echo $delivery['status'] == 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="picked_up" <?php echo $delivery['status'] == 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                        <option value="in_transit" <?php echo $delivery['status'] == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                        <option value="nearby" <?php echo $delivery['status'] == 'nearby' ? 'selected' : ''; ?>>Nearby</option>
                        <option value="delivered">Delivered</option>
                    </select>
                    <button class="btn btn-primary" onclick="updateStatus()">
                        <i class="fas fa-check"></i> Update
                    </button>
                </div>
                <div id="statusMessage" style="margin-top: 0.5rem; font-size: 0.75rem;"></div>
            </div>
            <?php endif; ?>

            <!-- Status Timeline -->
            <div class="info-card">
                <h3><i class="fas fa-history"></i> Status Timeline</h3>
                <div class="timeline">
                    <?php
                    $statuses = ['assigned', 'picked_up', 'in_transit', 'nearby', 'delivered'];
                    $status_labels = [
                        'assigned' => 'Assigned to Delivery Agent',
                        'picked_up' => 'Picked Up from Shop',
                        'in_transit' => 'In Transit',
                        'nearby' => 'Nearby Customer Location',
                        'delivered' => 'Delivered Successfully'
                    ];
                    $status_icons_timeline = [
                        'assigned' => 'fa-user-check',
                        'picked_up' => 'fa-box-open',
                        'in_transit' => 'fa-truck',
                        'nearby' => 'fa-location-dot',
                        'delivered' => 'fa-flag-checkered'
                    ];
                    
                    $current_status = $delivery['status'];
                    
                    foreach ($statuses as $status):
                        $is_completed = false;
                        $is_active = ($status == $current_status);
                        
                        $status_index = array_search($status, $statuses);
                        $current_index = array_search($current_status, $statuses);
                        if ($status_index < $current_index) {
                            $is_completed = true;
                        }
                    ?>
                    <div class="timeline-item <?php echo $is_completed ? 'completed' : ''; ?> <?php echo $is_active ? 'active' : ''; ?>">
                        <div class="title">
                            <i class="fas <?php echo $status_icons_timeline[$status] ?? 'fa-circle'; ?>"></i>
                            <?php echo $status_labels[$status] ?? ucfirst($status); ?>
                        </div>
                        <?php if ($is_completed || $is_active): ?>
                        <div class="time">
                            <?php echo date('d M Y h:i A'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ============================================
// GLOBAL VARIABLES
// ============================================
const deliveryId = <?php echo $delivery_id; ?>;
const pickupLat = <?php echo $delivery['pickup_latitude'] ?? -6.7924; ?>;
const pickupLng = <?php echo $delivery['pickup_longitude'] ?? 39.2083; ?>;
const deliveryLat = <?php echo $delivery['delivery_latitude'] ?? -6.7924; ?>;
const deliveryLng = <?php echo $delivery['delivery_longitude'] ?? 39.2083; ?>;
const currentLat = <?php echo $current_lat ?? -6.7924; ?>;
const currentLng = <?php echo $current_lng ?? 39.2083; ?>;

let map;
let currentMarker = null;
let routeLine = null;
let trackingInterval = null;
let isTracking = false;

// ============================================
// INITIALIZE MAP
// ============================================
function initMap() {
    const centerLat = (pickupLat + deliveryLat) / 2;
    const centerLng = (pickupLng + deliveryLng) / 2;

    map = L.map('map').setView([centerLat, centerLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    // Pickup Marker (Shop)
    const pickupIcon = L.divIcon({
        html: '🏪',
        className: 'custom-marker',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });
    L.marker([pickupLat, pickupLng], { icon: pickupIcon })
        .addTo(map)
        .bindPopup('<strong><?php echo htmlspecialchars($delivery['business_name']); ?></strong><br>📦 Pickup Location');

    // Delivery Marker (Customer)
    const deliveryIcon = L.divIcon({
        html: '🏠',
        className: 'custom-marker',
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });
    L.marker([deliveryLat, deliveryLng], { icon: deliveryIcon })
        .addTo(map)
        .bindPopup('<strong><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></strong><br>📍 Delivery Location');

    // Draw initial route
    drawRoute(pickupLat, pickupLng, deliveryLat, deliveryLng);

    // Add current location marker if available
    if (currentLat && currentLng) {
        addCurrentMarker(currentLat, currentLng);
    }

    // Fit map bounds
    const bounds = L.latLngBounds([
        [pickupLat, pickupLng],
        [deliveryLat, deliveryLng]
    ]);
    map.fitBounds(bounds, { padding: [50, 50] });

    L.control.scale({ position: 'bottomright' }).addTo(map);
}

// ============================================
// DRAW ROUTE
// ============================================
function drawRoute(startLat, startLng, endLat, endLng) {
    if (routeLine) {
        map.removeLayer(routeLine);
    }
    
    routeLine = L.polyline([
        [startLat, startLng],
        [endLat, endLng]
    ], {
        color: '#e67e22',
        weight: 4,
        opacity: 0.8,
        dashArray: null,
        lineJoin: 'round'
    }).addTo(map);
}

// ============================================
// ADD CURRENT LOCATION MARKER
// ============================================
function addCurrentMarker(lat, lng) {
    const truckIcon = L.divIcon({
        html: '🚚',
        className: 'custom-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });
    
    if (currentMarker) {
        currentMarker.setLatLng([lat, lng]);
    } else {
        currentMarker = L.marker([lat, lng], { icon: truckIcon })
            .addTo(map)
            .bindPopup('📍 Current Location (Delivery Agent)')
            .openPopup();
    }
}

// ============================================
// START LIVE TRACKING
// ============================================
function startLiveTracking() {
    if (!navigator.geolocation) {
        alert('❌ Geolocation is not supported by your browser');
        return;
    }

    if (isTracking) {
        stopLiveTracking();
        return;
    }

    isTracking = true;
    document.getElementById('startTrackingBtn').innerHTML = '<i class="fas fa-stop"></i> Stop Tracking';
    document.getElementById('startTrackingBtn').style.background = '#e74c3c';
    document.getElementById('gpsDot').className = 'dot-green';
    document.getElementById('gpsText').textContent = 'GPS: Active';
    document.getElementById('liveIndicator').style.display = 'inline-flex';

    trackingInterval = setInterval(function() {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const status = document.getElementById('statusSelect')?.value || 'in_transit';

                // Update marker
                addCurrentMarker(lat, lng);

                // Update route
                drawRoute(pickupLat, pickupLng, lat, lng);

                // Send to server
                fetch('track-delivery.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'update_location=1&lat=' + lat + '&lng=' + lng + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgress(lat, lng);
                    }
                })
                .catch(error => console.error('Error updating location:', error));

                map.setView([lat, lng], 15);
                document.getElementById('gpsText').textContent = 'GPS: Active';
            },
            function(error) {
                console.error('GPS Error:', error);
                document.getElementById('gpsText').textContent = 'GPS: Error - ' + error.message;
                document.getElementById('gpsDot').className = 'dot-red';
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }, 5000);
}

// ============================================
// STOP LIVE TRACKING
// ============================================
function stopLiveTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
    }
    isTracking = false;
    document.getElementById('startTrackingBtn').innerHTML = '<i class="fas fa-location-dot"></i> Start Live Tracking';
    document.getElementById('startTrackingBtn').style.background = '#e74c3c';
    document.getElementById('gpsDot').className = 'dot-red';
    document.getElementById('gpsText').textContent = 'GPS: Off';
}

// ============================================
// UPDATE PROGRESS
// ============================================
function updateProgress(currentLat, currentLng) {
    const distance = calculateDistance(pickupLat, pickupLng, currentLat, currentLng);
    const totalDistance = calculateDistance(pickupLat, pickupLng, deliveryLat, deliveryLng);
    let progress = Math.min(100, Math.round((distance / totalDistance) * 100));
    
    if (<?php echo $delivery['status'] == 'delivered' ? 'true' : 'false'; ?>) {
        progress = 100;
    }
    
    document.getElementById('progressFill').style.width = progress + '%';
}

// ============================================
// CALCULATE DISTANCE
// ============================================
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// ============================================
// UPDATE STATUS
// ============================================
function updateStatus() {
    const statusSelect = document.getElementById('statusSelect');
    const status = statusSelect.value;
    const messageDiv = document.getElementById('statusMessage');
    
    if (!status) {
        messageDiv.innerHTML = '<span style="color: #e74c3c;">Please select a status</span>';
        return;
    }
    
    if (!confirm('Update status to "' + status.replace('_', ' ') + '"?')) {
        return;
    }
    
    fetch('track-delivery.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_status=1&status=' + status
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<span style="color: #27ae60;">✅ Status updated to: ' + status.replace('_', ' ') + '</span>';
            setTimeout(() => location.reload(), 1000);
        } else {
            messageDiv.innerHTML = '<span style="color: #e74c3c;">❌ Failed to update status</span>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '<span style="color: #e74c3c;">❌ Error updating status</span>';
    });
}

// ============================================
// TOAST NOTIFICATION
// ============================================
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '8px';
    toast.style.color = 'white';
    toast.style.fontWeight = '600';
    toast.style.zIndex = '9999';
    toast.style.animation = 'slideIn 0.3s ease';
    toast.style.background = type === 'success' ? '#27ae60' : '#e74c3c';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => toast.remove(), 500);
    }, 3000);
}

// ============================================
// AUTO START ON LOAD
// ============================================
window.addEventListener('load', function() {
    initMap();
    
    <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed'): ?>
    setTimeout(startLiveTracking, 2000);
    <?php endif; ?>
});

window.addEventListener('beforeunload', function() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
    }
});
</script>
</body>
</html>