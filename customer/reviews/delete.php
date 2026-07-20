<?php
// customer/reviews/delete.php - DELETE REVIEW
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Verify review belongs to customer
$check_sql = "SELECT review_id FROM reviews WHERE review_id = '$review_id' AND customer_id = '$customer_id'";
$check_result = mysqli_query($conn, $check_sql);

if(mysqli_num_rows($check_result) > 0) {
    $delete_sql = "DELETE FROM reviews WHERE review_id = '$review_id'";
    if(mysqli_query($conn, $delete_sql)) {
        $_SESSION['flash_message'] = "Review deleted successfully";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to delete review";
        $_SESSION['flash_type'] = "danger";
    }
} else {
    $_SESSION['flash_message'] = "Review not found";
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>