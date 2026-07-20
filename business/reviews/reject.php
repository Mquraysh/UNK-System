<?php
// business/reviews/reject.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($review_id <= 0) {
    $_SESSION['flash_message'] = "Invalid review ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Verify permission
$user_id = $_SESSION['user_id'];
$check_sql = "SELECT r.review_id FROM reviews r
              JOIN products p ON r.product_id = p.product_id
              JOIN businesses b ON p.business_id = b.business_id
              WHERE b.user_id = '$user_id' AND r.review_id = '$review_id'";
$check_result = mysqli_query($conn, $check_sql);
if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['flash_message'] = "You don't have permission to reject this review.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$update_sql = "UPDATE reviews SET status = 'rejected' WHERE review_id = '$review_id'";
if (mysqli_query($conn, $update_sql)) {
    $_SESSION['flash_message'] = "Review rejected and hidden from customers.";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Error rejecting review: " . mysqli_error($conn);
    $_SESSION['flash_type'] = "danger";
}
header("Location: index.php");
exit();
?>