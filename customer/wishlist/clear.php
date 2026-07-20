<?php
// customer/wishlist/clear.php - CLEAR ENTIRE WISHLIST
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

$delete_sql = "DELETE FROM wishlist WHERE customer_id = '$customer_id'";
mysqli_query($conn, $delete_sql);

$_SESSION['flash_message'] = "Wishlist cleared successfully";
$_SESSION['flash_type'] = "success";
header("Location: index.php");
exit();
?>