<?php
// customer/wishlist/add.php - ADD TO WISHLIST (FIXED & RELIABLE)
require_once '../../config/database.php';
session_start();

// 1. Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    $_SESSION['flash_message'] = "Please login to add items to wishlist";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 2. Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    $_SESSION['flash_message'] = "Invalid product ID";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/index.php");
    exit();
}

// 3. Check if product exists and is available
$product_check = mysqli_query($conn, "SELECT product_id FROM products WHERE product_id = $product_id AND is_available = 1");
if (mysqli_num_rows($product_check) == 0) {
    $_SESSION['flash_message'] = "Product not found or unavailable";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../products/index.php");
    exit();
}

// 4. Get customer_id from the customers table
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
if (mysqli_num_rows($customer_result) == 0) {
    // Customer profile not completed
    $_SESSION['flash_message'] = "Please complete your customer profile first";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../register.php");
    exit();
}
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// 5. Check if already in wishlist
$check_sql = "SELECT wishlist_id FROM wishlist WHERE customer_id = '$customer_id' AND product_id = '$product_id'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    $_SESSION['flash_message'] = "Product is already in your wishlist";
    $_SESSION['flash_type'] = "info";
} else {
    // Insert into wishlist
    $insert_sql = "INSERT INTO wishlist (customer_id, product_id, added_at) VALUES ('$customer_id', '$product_id', NOW())";
    if (mysqli_query($conn, $insert_sql)) {
        $_SESSION['flash_message'] = "Product added to wishlist successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Database error: " . mysqli_error($conn);
        $_SESSION['flash_type'] = "danger";
    }
}

// 6. Redirect back to where the user came from (or to wishlist page)
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "../products/index.php";
header("Location: $referrer");
exit();
?>