<?php
// customer/otp/resend_otp.php - Customer Resend OTP
require_once '../../config/database.php';
require_once '../../config/otp_helper.php';
session_start();

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id <= 0) {
    header("Location: ../orders/my-orders.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? 0;

// Verify this delivery belongs to the customer
$sql = "SELECT d.agent_id, o.customer_id 
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        WHERE d.delivery_id = '$delivery_id' 
        AND o.customer_id = '$customer_id'
        AND d.status != 'delivered'
        AND d.status != 'failed'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    $_SESSION['flash_message'] = 'Delivery not found or already completed.';
    $_SESSION['flash_type'] = 'warning';
    header("Location: ../orders/my-orders.php");
    exit();
}

// Regenerate OTP
$agent_id = $delivery['agent_id'];
$result = DeliveryOTP::generateDeliveryOTP($conn, $delivery_id, $customer_id, $agent_id);

if ($result['success']) {
    $_SESSION['flash_message'] = '✅ New OTP has been sent to your email!';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = '❌ Failed to resend OTP. Please try again.';
    $_SESSION['flash_type'] = 'danger';
}

header("Location: verify_otp.php?id=" . $delivery_id);
exit();
?>