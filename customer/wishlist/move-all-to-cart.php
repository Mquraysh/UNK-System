<?php
// customer/wishlist/move-all-to-cart.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cust_res = mysqli_query($conn, "SELECT customer_id FROM customers WHERE user_id = '$user_id'");
$cust = mysqli_fetch_assoc($cust_res);
$customer_id = $cust['customer_id'];

$wishlist = mysqli_query($conn, "SELECT w.wishlist_id, w.product_id FROM wishlist w JOIN products p ON w.product_id = p.product_id WHERE w.customer_id = $customer_id AND p.quantity_in_stock > 0");
$moved = 0;
while ($row = mysqli_fetch_assoc($wishlist)) {
    $product_id = $row['product_id'];
    $check = mysqli_query($conn, "SELECT cart_id FROM cart WHERE customer_id = $customer_id AND product_id = $product_id");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($conn, "INSERT INTO cart (customer_id, product_id, quantity) VALUES ($customer_id, $product_id, 1)");
    } else {
        mysqli_query($conn, "UPDATE cart SET quantity = quantity + 1 WHERE customer_id = $customer_id AND product_id = $product_id");
    }
    $moved++;
}
$_SESSION['flash_message'] = "$moved item(s) moved to cart. Out-of-stock items remain in wishlist.";
$_SESSION['flash_type'] = "success";
header("Location: index.php");
exit();
?>