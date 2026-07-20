<?php
// business/notifications/delete.php - DELETE NOTIFICATION 
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

// Delete notification
$delete_sql = "DELETE FROM business_notifications WHERE notification_id = '$notification_id' AND business_id = '$business_id'";
mysqli_query($conn, $delete_sql);

header("Location: index.php");
exit();
?>