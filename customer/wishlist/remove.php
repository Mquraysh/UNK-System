<?php
// customer/wishlist/remove.php 
require_once '../../config/database.php';
session_start();

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
if (mysqli_num_rows($customer_result) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Get wishlist ID from URL
$wishlist_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($wishlist_id <= 0) {
    $_SESSION['flash_message'] = "Invalid wishlist item.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Verify that this wishlist item belongs to the logged-in customer
$check_sql = "SELECT wishlist_id FROM wishlist WHERE wishlist_id = $wishlist_id AND customer_id = $customer_id";
$check_result = mysqli_query($conn, $check_sql);
if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['flash_message'] = "You don't have permission to remove this item.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Delete the wishlist item
$delete_sql = "DELETE FROM wishlist WHERE wishlist_id = $wishlist_id AND customer_id = $customer_id";
if (mysqli_query($conn, $delete_sql)) {
    $_SESSION['flash_message'] = "Item removed from wishlist.";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Error removing item: " . mysqli_error($conn);
    $_SESSION['flash_type'] = "danger";
}

// Redirect back to wishlist page
header("Location: index.php");
exit();
?>