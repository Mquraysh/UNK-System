<?php
// customer/cart/clear_cart.php
require_once '../../config/database.php';
session_start();

// Check if user is logged in and is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_res = mysqli_query($conn, "SELECT customer_id FROM customers WHERE user_id = '$user_id'");
if (mysqli_num_rows($customer_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer_data = mysqli_fetch_assoc($customer_res);
$customer_id = $customer_data['customer_id'];

// Delete all items from cart
$delete_sql = "DELETE FROM cart WHERE customer_id = $customer_id";
$delete_result = mysqli_query($conn, $delete_sql);

if ($delete_result) {
    $_SESSION['flash_message'] = 'Cart cleared successfully!';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Failed to clear cart: ' . mysqli_error($conn);
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>