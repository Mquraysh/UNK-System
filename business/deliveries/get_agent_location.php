<?php
// business/deliveries/get_agent_location.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    http_response_code(403);
    exit();
}

$delivery_id = isset($_GET['delivery_id']) ? (int)$_GET['delivery_id'] : 0;
if (!$delivery_id) {
    echo json_encode(['error' => 'Invalid delivery ID']);
    exit();
}

// Get agent_id from deliveries table
$agent_sql = "SELECT agent_id FROM deliveries WHERE delivery_id = $delivery_id";
$agent_res = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_res);

if (!$agent || !$agent['agent_id']) {
    // Fallback to business pickup location
    $pickup_sql = "SELECT b.latitude, b.longitude 
                   FROM deliveries d
                   JOIN orders o ON d.order_id = o.order_id
                   JOIN businesses b ON o.business_id = b.business_id
                   WHERE d.delivery_id = $delivery_id";
    $pickup_res = mysqli_query($conn, $pickup_sql);
    $pickup = mysqli_fetch_assoc($pickup_res);
    if ($pickup && $pickup['latitude'] && $pickup['longitude']) {
        echo json_encode(['lat' => (float)$pickup['latitude'], 'lng' => (float)$pickup['longitude']]);
    } else {
        echo json_encode(['lat' => -6.8225, 'lng' => 39.2697]);
    }
    exit();
}

$agent_id = $agent['agent_id'];

// Fetch current location from delivery_agents table
$loc_sql = "SELECT current_latitude, current_longitude FROM delivery_agents WHERE agent_id = $agent_id";
$loc_res = mysqli_query($conn, $loc_sql);
$loc = mysqli_fetch_assoc($loc_res);

if ($loc && !is_null($loc['current_latitude']) && !is_null($loc['current_longitude'])) {
    echo json_encode([
        'lat' => (float)$loc['current_latitude'],
        'lng' => (float)$loc['current_longitude']
    ]);
} else {
    // Fallback to pickup location
    $pickup_sql = "SELECT b.latitude, b.longitude 
                   FROM deliveries d
                   JOIN orders o ON d.order_id = o.order_id
                   JOIN businesses b ON o.business_id = b.business_id
                   WHERE d.delivery_id = $delivery_id";
    $pickup_res = mysqli_query($conn, $pickup_sql);
    $pickup = mysqli_fetch_assoc($pickup_res);
    if ($pickup && $pickup['latitude'] && $pickup['longitude']) {
        echo json_encode(['lat' => (float)$pickup['latitude'], 'lng' => (float)$pickup['longitude']]);
    } else {
        echo json_encode(['lat' => -6.8225, 'lng' => 39.2697]);
    }
}
?>