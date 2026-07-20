<?php
// customer/cart/update_cart.php
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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cart_id'], $_POST['quantity'])) {
    $cart_id = (int)$_POST['cart_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Validate quantity
    if ($quantity < 1) {
        $_SESSION['flash_message'] = 'Quantity must be at least 1';
        $_SESSION['flash_type'] = 'danger';
        header("Location: index.php");
        exit();
    }
    
    // Check if cart item belongs to this customer and get product stock
    $check_sql = "SELECT c.cart_id, p.quantity_in_stock, p.product_id 
                  FROM cart c 
                  JOIN products p ON c.product_id = p.product_id 
                  WHERE c.cart_id = $cart_id AND c.customer_id = $customer_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
        $_SESSION['flash_message'] = 'Item not found in your cart';
        $_SESSION['flash_type'] = 'danger';
        header("Location: index.php");
        exit();
    }
    
    $cart_item = mysqli_fetch_assoc($check_result);
    
    // Check if quantity exceeds stock
    if ($quantity > $cart_item['quantity_in_stock']) {
        $_SESSION['flash_message'] = 'Quantity exceeds available stock (Max: ' . $cart_item['quantity_in_stock'] . ')';
        $_SESSION['flash_type'] = 'danger';
        header("Location: index.php");
        exit();
    }
    
    // Update quantity
    $update_sql = "UPDATE cart SET quantity = $quantity WHERE cart_id = $cart_id AND customer_id = $customer_id";
    $update_result = mysqli_query($conn, $update_sql);
    
    if ($update_result) {
        $_SESSION['flash_message'] = 'Cart updated successfully!';
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = 'Failed to update cart: ' . mysqli_error($conn);
        $_SESSION['flash_type'] = 'danger';
    }
    
    header("Location: index.php");
    exit();
} else {
    // Invalid request
    header("Location: index.php");
    exit();
}
?>