<?php
// delivery/track/track-delivery.php - Tracking Only (No Status Update)
require_once '../../config/database.php';
session_start();

// Check if user is logged in as delivery agent
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Redirect if no delivery ID provided
if ($delivery_id <= 0) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// Get delivery agent information
$agent_sql = "SELECT agent_id, first_name, last_name, phone, vehicle_type, vehicle_registration 
              FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$agent_id = $agent['agent_id'];

// Get delivery details with join to orders, customers, users, businesses
$sql = "SELECT 
            d.delivery_id,
            d.order_id,
            d.status,
            d.delivery_fee,
            d.delivered_at,
            d.created_at,
            d.pickup_address,
            d.delivery_address,
            d.assigned_at,
            d.picked_up_at,
            d.estimated_distance,
            d.estimated_time,
            d.rating,
            d.rating_comment,
            d.rated_at,
            o.delivery_address as order_delivery_address,
            o.order_date,
            o.status as order_status,
            c.first_name as customer_first_name,
            c.last_name as customer_last_name,
            c.city,
            c.saved_address as customer_saved_address,
            u.phone as customer_phone,
            b.business_name,
            b.location as business_address,
            b.phone as business_phone,
            b.latitude as business_latitude,
            b.longitude as business_longitude,
            c.delivery_latitude as customer_latitude,
            c.delivery_longitude as customer_longitude
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        JOIN businesses b ON o.business_id = b.business_id
        WHERE d.delivery_id = '$delivery_id' 
        AND d.agent_id = '$agent_id'";

$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

// Redirect if delivery not found or not assigned to this agent
if (!$delivery) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// ============================================
// SEQUENTIAL STATUS ORDER
// ============================================
$status_order = ['assigned', 'picked_up', 'in_transit', 'nearby', 'delivered'];
$current_status = $delivery['status'];
$current_index = array_search($current_status, $status_order);

// Get next statuses
$next_statuses = [];
if ($current_index !== false && $current_index < count($status_order) - 1) {
    $next_statuses = array_slice($status_order, $current_index + 1);
}

// Default coordinates for Dar es Salaam
$default_lat = -6.7924;
$default_lng = 39.2083;

// Get pickup and delivery coordinates
$pickup_lat = $delivery['business_latitude'] ?? $default_lat;
$pickup_lng = $delivery['business_longitude'] ?? $default_lng;
$delivery_lat = $delivery['customer_latitude'] ?? $default_lat;
$delivery_lng = $delivery['customer_longitude'] ?? $default_lng;

// Set current location to pickup initially
$current_lat = $pickup_lat;
$current_lng = $pickup_lng;

// Check if delivery_tracking table exists
$table_check = "SHOW TABLES LIKE 'delivery_tracking'";
$table_result = mysqli_query($conn, $table_check);

if (mysqli_num_rows($table_result) > 0) {
    // Check if latitude column exists
    $col_check = "SHOW COLUMNS FROM delivery_tracking LIKE 'latitude'";
    $col_result = mysqli_query($conn, $col_check);
    $has_latitude = mysqli_num_rows($col_result) > 0;
    
    if ($has_latitude) {
        // Get latest location from tracking
        $latest_sql = "SELECT latitude, longitude, status, created_at 
                       FROM delivery_tracking 
                       WHERE delivery_id = '$delivery_id' 
                       ORDER BY created_at DESC 
                       LIMIT 1";
        $latest_result = mysqli_query($conn, $latest_sql);
        $latest_location = mysqli_fetch_assoc($latest_result);
        
        if ($latest_location) {
            $current_lat = $latest_location['latitude'] ?? $pickup_lat;
            $current_lng = $latest_location['longitude'] ?? $pickup_lng;
        }
    }
}

// ============================================
// HANDLE LOCATION UPDATE ONLY (NO STATUS UPDATE)
// ============================================
if (isset($_POST['update_location'])) {
    $lat = (float)$_POST['lat'];
    $lng = (float)$_POST['lng'];
    $status = $_POST['status'] ?? $delivery['status'];
    
    // Insert location into tracking
    $track_sql = "INSERT INTO delivery_tracking (delivery_id, latitude, longitude, status, created_at) 
                  VALUES ('$delivery_id', '$lat', '$lng', '$status', NOW())";
    mysqli_query($conn, $track_sql);
    
    echo json_encode(['success' => true]);
    exit();
}

// Status color mapping for UI
$status_colors = [
    'pending' => '#f39c12',
    'assigned' => '#3498db',
    'picked_up' => '#8e44ad',
    'in_transit' => '#4338ca',
    'nearby' => '#e67e22',
    'delivered' => '#27ae60'
];

// Status label mapping for UI
$status_labels = [
    'assigned' => 'Assigned to Agent',
    'picked_up' => 'Picked Up',
    'in_transit' => 'In Transit',
    'nearby' => 'Nearby',
    'delivered' => 'Delivered ✅'
];

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Delivery #<?php echo $delivery_id; ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1f2937; }
        
        .delivery-content {
            margin-left: 280px;
            padding: 1.5rem 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .delivery-content { margin-left: 0; padding: 1rem; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.5rem; }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 1rem;
            background: white;
            padding: 0.8rem 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
        }
        .page-header h1 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header .delivery-id {
            background: #e67e22;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .page-header .status-badge {
            padding: 0.2rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: white;
            background: <?php echo $status_colors[$delivery['status']] ?? '#64748b'; ?>;
        }
        .page-header .btn-back {
            background: #64748b;
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .page-header .btn-back:hover { background: #475569; transform: translateY(-2px); }
        
        .page-header .btn-update-status {
            background: #e67e22;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .page-header .btn-update-status:hover { background: #d35400; transform: translateY(-2px); }
        
        /* Progress Steps */
        .progress-container {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem 2rem;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            max-width: 800px;
            margin: 0 auto;
        }
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 30px;
            right: 30px;
            height: 3px;
            background: #e2e8f0;
            z-index: 0;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            background: #e2e8f0;
            color: #94a3b8;
            border: 3px solid #e2e8f0;
            transition: all 0.3s;
        }
        .step.active .step-circle {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        .step.completed .step-circle {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
        }
        .step-label {
            font-size: 10px;
            margin-top: 6px;
            color: #94a3b8;
            font-weight: 500;
            text-align: center;
        }
        .step.active .step-label {
            color: #e67e22;
            font-weight: 700;
        }
        .step.completed .step-label {
            color: #27ae60;
        }
        
        .map-container {
            background: white;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        .map-container #map {
            width: 100%;
            height: 70vh;
            min-height: 500px;
            border: none;
            background: #e8ecf1;
        }
        
        .map-controls {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 1000;
            background: rgba(255,255,255,0.95);
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            flex-wrap: wrap;
            justify-content: center;
        }
        .map-controls .btn {
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            border: none;
            font-weight: 600;
            font-size: 0.7rem;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .map-controls .btn-primary { background: #e67e22; color: white; }
        .map-controls .btn-primary:hover { background: #d35400; transform: scale(1.05); }
        .map-controls .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .map-controls .btn-danger { background: #e74c3c; color: white; }
        .map-controls .btn-danger:hover { background: #c0392b; transform: scale(1.05); }
        .map-controls .btn-outline { background: transparent; border: 1px solid #e2e8f0; color: #64748b; }
        .map-controls .btn-outline:hover { border-color: #e67e22; color: #e67e22; }
        
        .live-indicator {
            display: <?php echo ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed') ? 'flex' : 'none'; ?>;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.65rem;
            color: #27ae60;
            font-weight: 600;
        }
        .live-indicator .dot {
            width: 6px;
            height: 6px;
            background: #27ae60;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }
        
        .gps-status {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.6rem;
            color: #64748b;
        }
        .gps-status .dot-green {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #27ae60;
            display: inline-block;
            animation: blink 1.5s infinite;
        }
        .gps-status .dot-red {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e74c3c;
            display: inline-block;
        }
        
        .custom-marker { font-size: 28px; text-align: center; text-shadow: 0 2px 8px rgba(0,0,0,0.3); line-height: 36px; }
        .leaflet-popup-content { font-family: 'Inter', sans-serif; }
        
        #statusMessage {
            font-size: 0.7rem;
            margin-top: 0.3rem;
            text-align: center;
        }
        .status-message-success { color: #27ae60; }
        .status-message-error { color: #e74c3c; }
        .status-message-info { color: #e67e22; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 10px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 14px; font-weight: 600; color: #0f172a; }
        
        @media (max-width: 768px) {
            .progress-container { padding: 1rem; }
            .progress-steps { flex-wrap: wrap; gap: 8px; }
            .step { flex: 1; min-width: 40px; }
            .step-label { font-size: 8px; }
            .map-container #map { height: 55vh; min-height: 350px; }
            .map-controls { bottom: 10px; padding: 0.4rem 0.8rem; width: 95%; border-radius: 1rem; gap: 0.4rem; }
            .map-controls .btn { font-size: 0.6rem; padding: 0.2rem 0.6rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header .header-right { width: 100%; flex-wrap: wrap; }
            .info-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .map-container #map { height: 45vh; min-height: 280px; }
            .delivery-content { padding: 0.3rem; }
            .page-header { padding: 0.5rem 0.8rem; }
            .page-header h1 { font-size: 1rem; }
            .progress-steps::before { left: 10px; right: 10px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-map-location-dot"></i>
                Track Delivery
                <span class="delivery-id"><?php echo $delivery_id; ?></span>
            </h1>
        </div>
        <div class="header-right" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
            <span class="live-indicator" id="liveIndicator">
                <span class="dot"></span> Live
            </span>
            <span class="gps-status" id="gpsStatus">
                <span class="dot-red" id="gpsDot"></span>
                <span id="gpsText">GPS: Off</span>
            </span>
            <span class="status-badge" style="background: <?php echo $status_colors[$delivery['status']] ?? '#64748b'; ?>;">
                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                <?php echo $status_labels[$delivery['status']] ?? ucfirst($delivery['status']); ?>
            </span>
            <!-- Button to go to update status page -->
            <a href="../update_status/update_status.php?id=<?php echo $delivery_id; ?>" class="btn-update-status">
                <i class="fas fa-edit"></i> Update Status
            </a>
            <a href="../my-deliveries/my-deliveries.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Delivery Info -->
    <!-- <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Order ID</span>
            <span class="info-value"> <?php echo $delivery['order_id']; ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Business</span>
            <span class="info-value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Customer</span>
            <span class="info-value"><?php echo htmlspecialchars($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Delivery Fee</span>
            <span class="info-value">TSh <?php echo number_format($delivery['delivery_fee'] ?? 0); ?></span>
        </div>
    </div> -->

    <!-- Progress Steps -->
    <div class="progress-container">
        <div class="progress-steps">
            <?php 
            $steps = ['assigned', 'picked_up', 'in_transit', 'nearby', 'delivered'];
            $current_idx = array_search($current_status, $steps);
            foreach ($steps as $idx => $step):
                $status_class = '';
                if ($idx < $current_idx) $status_class = 'completed';
                elseif ($idx == $current_idx) $status_class = 'active';
                
                $label = str_replace('_', ' ', ucfirst($step));
                $icon = '';
                if ($step == 'assigned') $icon = 'fa-clipboard-list';
                elseif ($step == 'picked_up') $icon = 'fa-box';
                elseif ($step == 'in_transit') $icon = 'fa-truck';
                elseif ($step == 'nearby') $icon = 'fa-location-dot';
                elseif ($step == 'delivered') $icon = 'fa-check-circle';
            ?>
            <div class="step <?php echo $status_class; ?>">
                <div class="step-circle"><i class="fas <?php echo $icon; ?>"></i></div>
                <span class="step-label"><?php echo $label; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 10px; font-size: 12px; color: #64748b;">
            <?php if (!empty($next_statuses)): ?>
                <i class="fas fa-info-circle"></i> 
                Next: <strong><?php echo ucfirst(str_replace('_', ' ', $next_statuses[0])); ?></strong>
                <span style="margin-left: 10px; font-size: 11px;">
                    <a href="../update_status/update_status.php?id=<?php echo $delivery_id; ?>" style="color: #e67e22; text-decoration: none;">
                        <i class="fas fa-edit"></i> Update now
                    </a>
                </span>
            <?php else: ?>
                <i class="fas fa-check-circle" style="color: #27ae60;"></i> 
                <strong style="color: #27ae60;">All steps completed!</strong>
            <?php endif; ?>
        </div>
    </div>

    <div class="map-container">
        <div id="map"></div>
        
        <div class="map-controls">
            <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed'): ?>
                <button class="btn btn-primary" id="startTrackingBtn" onclick="toggleTracking()">
                    <i class="fas fa-location-dot"></i> <span id="trackingBtnText">Start Tracking</span>
                </button>
            <?php else: ?>
                <span style="font-size: 0.75rem; color: #27ae60; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> Delivery Completed
                </span>
            <?php endif; ?>
            <button class="btn btn-outline" onclick="fitMapToBounds()">
                <i class="fas fa-crosshairs"></i> Center
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Delivery tracking data from PHP
const deliveryId = <?php echo $delivery_id; ?>;
const pickupLat = <?php echo $pickup_lat; ?>;
const pickupLng = <?php echo $pickup_lng; ?>;
const deliveryLat = <?php echo $delivery_lat; ?>;
const deliveryLng = <?php echo $delivery_lng; ?>;
const currentLat = <?php echo $current_lat; ?>;
const currentLng = <?php echo $current_lng; ?>;
const businessName = '<?php echo addslashes($delivery['business_name']); ?>';
const customerName = '<?php echo addslashes($delivery['customer_first_name'] . ' ' . $delivery['customer_last_name']); ?>';
const currentStatus = '<?php echo $current_status; ?>';
const nextStatuses = <?php echo json_encode($next_statuses); ?>;

// Map variables
let map;
let currentMarker = null;
let routeLine = null;
let trackingInterval = null;
let isTracking = false;

// Initialize the map
function initMap() {
    const centerLat = (pickupLat + deliveryLat) / 2;
    const centerLng = (pickupLng + deliveryLng) / 2;

    map = L.map('map', {
        center: [centerLat, centerLng],
        zoom: 14,
        zoomControl: true,
        attributionControl: true
    });

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
        minZoom: 3
    }).addTo(map);

    // Pickup marker
    const pickupIcon = L.divIcon({
        html: '🏪',
        className: 'custom-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    });
    L.marker([pickupLat, pickupLng], { icon: pickupIcon })
        .addTo(map)
        .bindPopup(`<strong>🏪 ${businessName}</strong><br><small>📦 Pickup Location</small>`);

    // Delivery marker
    const deliveryIcon = L.divIcon({
        html: '🏠',
        className: 'custom-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 32]
    });
    L.marker([deliveryLat, deliveryLng], { icon: deliveryIcon })
        .addTo(map)
        .bindPopup(`<strong>🏠 ${customerName}</strong><br><small>📍 Delivery Location</small>`);

    // Draw route line
    drawRoute(pickupLat, pickupLng, deliveryLat, deliveryLng);

    // Add current location marker if available
    if (currentLat && currentLng && !isNaN(currentLat) && !isNaN(currentLng)) {
        addCurrentMarker(currentLat, currentLng);
    }

    // Fit map to show all markers
    fitMapToBounds();

    // Add scale control
    L.control.scale({ position: 'bottomright' }).addTo(map);
}

// Draw route line between two points
function drawRoute(startLat, startLng, endLat, endLng) {
    if (routeLine) {
        map.removeLayer(routeLine);
    }
    
    routeLine = L.polyline([
        [startLat, startLng],
        [endLat, endLng]
    ], {
        color: '#e67e22',
        weight: 5,
        opacity: 0.8,
        lineJoin: 'round',
        dashArray: '10, 10'
    }).addTo(map);
}

// Add or update current location marker
function addCurrentMarker(lat, lng) {
    const truckIcon = L.divIcon({
        html: '🚚',
        className: 'custom-marker',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });
    
    if (currentMarker) {
        currentMarker.setLatLng([lat, lng]);
        currentMarker.openPopup();
    } else {
        currentMarker = L.marker([lat, lng], { icon: truckIcon })
            .addTo(map)
            .bindPopup('<strong>🚚 Delivery Agent</strong><br><small>📍 Current Location</small>')
            .openPopup();
    }
}

// Fit map to show all important locations
function fitMapToBounds() {
    const bounds = L.latLngBounds([
        [pickupLat, pickupLng],
        [deliveryLat, deliveryLng]
    ]);
    
    if (currentMarker) {
        const pos = currentMarker.getLatLng();
        bounds.extend([pos.lat, pos.lng]);
    }
    
    map.fitBounds(bounds, { padding: [80, 80], maxZoom: 15 });
}

// Toggle tracking on/off
function toggleTracking() {
    if (isTracking) {
        stopTracking();
    } else {
        startTracking();
    }
}

// Start GPS tracking
function startTracking() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }

    isTracking = true;
    document.getElementById('trackingBtnText').textContent = 'Stop Tracking';
    document.getElementById('startTrackingBtn').className = 'btn btn-danger';
    document.getElementById('gpsDot').className = 'dot-green';
    document.getElementById('gpsText').textContent = 'GPS: Active';

    // Get initial position
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            if (!isNaN(lat) && !isNaN(lng)) {
                addCurrentMarker(lat, lng);
                drawRoute(pickupLat, pickupLng, lat, lng);
            }
        },
        function(error) {
            console.error('GPS Error:', error);
        },
        { enableHighAccuracy: true, timeout: 5000 }
    );

    // Start periodic tracking
    trackingInterval = setInterval(function() {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const status = currentStatus || 'in_transit';

                if (isNaN(lat) || isNaN(lng)) return;

                // Update marker and route
                addCurrentMarker(lat, lng);
                drawRoute(pickupLat, pickupLng, lat, lng);

                // Send location to server
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'update_location=1&lat=' + lat + '&lng=' + lng + '&status=' + status
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('gpsText').textContent = 'GPS: Active ✓';
                    }
                })
                .catch(error => console.error('Error updating location:', error));

                // Pan map to current location
                map.panTo([lat, lng]);
            },
            function(error) {
                console.error('GPS Error:', error);
                document.getElementById('gpsText').textContent = 'GPS: Error';
                document.getElementById('gpsDot').className = 'dot-red';
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 5000
            }
        );
    }, 6000);
}

// Stop GPS tracking
function stopTracking() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
        trackingInterval = null;
    }
    isTracking = false;
    document.getElementById('trackingBtnText').textContent = 'Start Tracking';
    document.getElementById('startTrackingBtn').className = 'btn btn-primary';
    document.getElementById('gpsDot').className = 'dot-red';
    document.getElementById('gpsText').textContent = 'GPS: Off';
}

// Auto-start tracking when page loads
window.addEventListener('load', function() {
    initMap();
    
    <?php if ($delivery['status'] != 'delivered' && $delivery['status'] != 'failed'): ?>
    setTimeout(function() {
        startTracking();
    }, 3000);
    <?php endif; ?>
});

// Clean up tracking when leaving page
window.addEventListener('beforeunload', function() {
    if (trackingInterval) {
        clearInterval(trackingInterval);
    }
});
</script>
</body>
</html>