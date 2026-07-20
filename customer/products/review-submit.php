<?php
// customer/products/review-submit.php
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    $user_id = $_SESSION['user_id'];
    $customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
    $customer_result = mysqli_query($conn, $customer_sql);
    $customer_data = mysqli_fetch_assoc($customer_result);
    $customer_id = $customer_data['customer_id'];
    
    // Check if already reviewed
    $check_sql = "SELECT * FROM reviews WHERE product_id = '$product_id' AND customer_id = '$customer_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if(mysqli_num_rows($check_result) == 0) {
        $insert_sql = "INSERT INTO reviews (product_id, customer_id, rating, comment, status, created_at) 
                       VALUES ('$product_id', '$customer_id', '$rating', '$comment', 'pending', NOW())";
        mysqli_query($conn, $insert_sql);
        $_SESSION['flash_message'] = "Thank you for your review! It will appear after approval.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "You have already reviewed this product.";
        $_SESSION['flash_type'] = "danger";
    }
}

header("Location: details.php?id= $product_id");
exit();
?>