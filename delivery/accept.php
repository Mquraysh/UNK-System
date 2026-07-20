<?php
// delivery/accept.php - ACCEPT DELIVERY REQUEST
require_once '../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get agent info
$agent_sql = "SELECT agent_id FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);
$agent_id = $agent['agent_id'];

// Check if delivery is still available
$check_sql = "SELECT * FROM deliveries WHERE delivery_id = '$delivery_id' AND status = 'pending' AND agent_id IS NULL";
$check_result = mysqli_query($conn, $check_sql);

if(mysqli_num_rows($check_result) > 0) {
    $update_sql = "UPDATE deliveries SET agent_id = '$agent_id', status = 'assigned', assigned_at = NOW() WHERE delivery_id = '$delivery_id'";
    if(mysqli_query($conn, $update_sql)) {
        $_SESSION['flash_message'] = "Delivery accepted successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to accept delivery";
        $_SESSION['flash_type'] = "danger";
    }
} else {
    $_SESSION['flash_message'] = "Delivery request no longer available";
    $_SESSION['flash_type'] = "danger";
}

header("Location: my-deliveries/my-deliveries.php");
exit();
?>