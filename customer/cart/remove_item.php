<?php
// customer/cart/remove_item.php
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

// Check if cart_id is provided
if (isset($_GET['id'])) {
    $cart_id = (int)$_GET['id'];
    
    // Verify item belongs to this customer
    $check_sql = "SELECT cart_id FROM cart WHERE cart_id = $cart_id AND customer_id = $customer_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete item
        $delete_sql = "DELETE FROM cart WHERE cart_id = $cart_id AND customer_id = $customer_id";
        $delete_result = mysqli_query($conn, $delete_sql);
        
        if ($delete_result) {
            $_SESSION['flash_message'] = 'Item removed from cart!';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to remove item: ' . mysqli_error($conn);
            $_SESSION['flash_type'] = 'danger';
        }
    } else {
        $_SESSION['flash_message'] = 'Item not found in your cart';
        $_SESSION['flash_type'] = 'danger';
    }
} else {
    $_SESSION['flash_message'] = 'Invalid request';
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>