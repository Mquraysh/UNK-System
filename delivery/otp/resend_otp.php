<?php
// delivery/otp/resend_otp.php
require_once '../../config/database.php';
require_once '../../config/otp_helper.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id <= 0) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get agent info
$agent_sql = "SELECT agent_id FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$agent_id = $agent['agent_id'];

// Verify delivery belongs to agent
$sql = "SELECT customer_id FROM deliveries WHERE delivery_id = '$delivery_id' AND agent_id = '$agent_id'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// Resend OTP
$result = DeliveryOTP::resendOTP($conn, $delivery_id);

if ($result['success']) {
    $_SESSION['flash_message'] = 'OTP resent successfully to customer';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = $result['message'];
    $_SESSION['flash_type'] = 'danger';
}

header("Location: verify_otp.php?id=" . $delivery_id);
exit();
?>