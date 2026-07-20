<?php
// business/reviews/delete.php - DELETE REVIEW
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify review belongs to business product
$check_sql = "SELECT r.review_id FROM reviews r
              JOIN products p ON r.product_id = p.product_id
              WHERE r.review_id = '$review_id' AND p.business_id = '$business_id'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    // Delete response first if exists
    $delete_response = "DELETE FROM review_responses WHERE review_id = '$review_id'";
    mysqli_query($conn, $delete_response);
    
    // Delete review
    $delete_sql = "DELETE FROM reviews WHERE review_id = '$review_id'";
    if (mysqli_query($conn, $delete_sql)) {
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