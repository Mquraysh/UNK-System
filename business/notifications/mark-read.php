<?php
// business/notifications/mark-read.php - MARK NOTIFICATION AS READ 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get business_id
$business_sql = "SELECT business_id FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business_data = mysqli_fetch_assoc($business_result);
$business_id = $business_data['business_id'];

// Update notification
$update_sql = "UPDATE business_notifications SET is_read = 1 
               WHERE notification_id = '$notification_id' AND business_id = '$business_id'";
mysqli_query($conn, $update_sql);

header("Location: index.php");
exit();
?>