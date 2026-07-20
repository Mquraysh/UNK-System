<?php
// customer/cart/add.php - ADD TO CART
require_once '../../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    $_SESSION['flash_message'] = "Please login to add items to cart";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../login.php");
    exit();
}

// Get product ID and quantity
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($product_id <= 0) {
    $_SESSION['flash_message'] = "Invalid product";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/index.php");
    exit();
}

// Get customer ID
$user_id = $_SESSION['user_id'];
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = $user_id";
$customer_result = mysqli_query($conn, $customer_sql);

if (mysqli_num_rows($customer_result) == 0) {
    $_SESSION['flash_message'] = "Customer profile not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/index.php");
    exit();
}

$customer = mysqli_fetch_assoc($customer_result);
$customer_id = $customer['customer_id'];

// Check if product exists
$product_sql = "SELECT product_id, name, quantity_in_stock, price FROM products WHERE product_id = $product_id AND is_available = 1";
$product_result = mysqli_query($conn, $product_sql);

if (mysqli_num_rows($product_result) == 0) {
    $_SESSION['flash_message'] = "Product not available";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/index.php");
    exit();
}

$product = mysqli_fetch_assoc($product_result);

// Check stock
if ($quantity > $product['quantity_in_stock']) {
    $_SESSION['flash_message'] = "Only " . $product['quantity_in_stock'] . " units available";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/details.php?id=$product_id");
    exit();
}

// Check if product already in cart
$check_sql = "SELECT cart_id, quantity FROM cart WHERE customer_id = $customer_id AND product_id = $product_id";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    // Update existing cart item
    $existing = mysqli_fetch_assoc($check_result);
    $new_quantity = $existing['quantity'] + $quantity;
    
    if ($new_quantity > $product['quantity_in_stock']) {
        $_SESSION['flash_message'] = "Cannot add more. Only " . $product['quantity_in_stock'] . " units available";
        $_SESSION['flash_type'] = "danger";
    } else {
        $update_sql = "UPDATE cart SET quantity = $new_quantity WHERE cart_id = " . $existing['cart_id'];
        mysqli_query($conn, $update_sql);
        $_SESSION['flash_message'] = $product['name'] . " quantity updated in cart";
        $_SESSION['flash_type'] = "success";
    }
} else {
    // Add new item to cart
    $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity) VALUES ($customer_id, $product_id, $quantity)";
    mysqli_query($conn, $insert_sql);
    $_SESSION['flash_message'] = $product['name'] . " added to cart!";
    $_SESSION['flash_type'] = "success";
}

// Redirect back
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "index.php";
header("Location: $referer");
exit();
?>e