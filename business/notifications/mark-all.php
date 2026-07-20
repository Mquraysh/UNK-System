<?php
// business/notifications/mark-all.php - MARK ALL NOTIFICATIONS AS READ 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business_id
$business_sql = "SELECT business_id FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business_data = mysqli_fetch_assoc($business_result);
$business_id = $business_data['business_id'];

// Update all notifications
$update_sql = "UPDATE business_notifications SET is_read = 1 WHERE business_id = '$business_id'";
mysqli_query($conn, $update_sql);

$_SESSION['flash_message'] = "All notifications marked as read";
$_SESSION['flash_type'] = "success";
header("Location: index.php");
exit();
?>