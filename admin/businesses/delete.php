<?php
// admin/businesses/delete.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id <= 0) {
    $_SESSION['flash_message'] = "Invalid business ID.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Fetch business and user_id
$stmt = mysqli_prepare($conn, "SELECT user_id FROM businesses WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$business) {
    $_SESSION['flash_message'] = "Business not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}
$user_id = $business['user_id'];

// Check for dependencies
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM products WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$products_cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
mysqli_stmt_close($stmt);

if ($products_cnt > 0) {
    $_SESSION['flash_message'] = "Cannot delete business with existing products. Delete products first.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$orders_cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
mysqli_stmt_close($stmt);

if ($orders_cnt > 0) {
    $_SESSION['flash_message'] = "Cannot delete business with existing orders.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// (Optional) Check for reviews – reviews are tied to products, but if products are gone, reviews are also gone.
// However, we can also check directly if needed, but product check already covers it.

// Begin transaction
mysqli_begin_transaction($conn);

// Delete business record
$stmt = mysqli_prepare($conn, "DELETE FROM businesses WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
$biz_ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($biz_ok) {
    // Delete associated user account
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $user_ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($user_ok) {
        mysqli_commit($conn);
        $_SESSION['flash_message'] = "Business permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = "Failed to delete user account.";
        $_SESSION['flash_type'] = 'danger';
    }
} else {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "Failed to delete business.";
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>