<?php
// customer/wishlist/move-to-cart.php - MOVE ITEM FROM WISHLIST TO CART
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$wishlist_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Get product_id from wishlist
$wishlist_sql = "SELECT product_id FROM wishlist WHERE wishlist_id = '$wishlist_id' AND customer_id = '$customer_id'";
$wishlist_result = mysqli_query($conn, $wishlist_sql);

if(mysqli_num_rows($wishlist_result) > 0) {
    $wishlist_item = mysqli_fetch_assoc($wishlist_result);
    $product_id = $wishlist_item['product_id'];
    
    // Check if product already in cart
    $cart_sql = "SELECT cart_id, quantity FROM cart WHERE customer_id = '$customer_id' AND product_id = '$product_id'";
    $cart_result = mysqli_query($conn, $cart_sql);
    
    if(mysqli_num_rows($cart_result) > 0) {
        $cart_item = mysqli_fetch_assoc($cart_result);
        $new_quantity = $cart_item['quantity'] + 1;
        $update_sql = "UPDATE cart SET quantity = '$new_quantity' WHERE cart_id = '{$cart_item['cart_id']}'";
        mysqli_query($conn, $update_sql);
    } else {
        $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity) VALUES ('$customer_id', '$product_id', 1)";
        mysqli_query($conn, $insert_sql);
    }
    
    // Remove from wishlist
    $delete_sql = "DELETE FROM wishlist WHERE wishlist_id = '$wishlist_id'";
    mysqli_query($conn, $delete_sql);
    
    $_SESSION['flash_message'] = "Item moved to cart successfully";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Item not found";
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>