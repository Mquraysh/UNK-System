<?php
// admin/users/delete.php - 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_admin_id = (int)$_SESSION['user_id'];
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    $_SESSION['flash_message'] = "Invalid user ID.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Prevent self-deletion
if ($user_id == $current_admin_id) {
    $_SESSION['flash_message'] = "You cannot delete your own account.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Fetch user role
$stmt = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['flash_message'] = "User not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$role = $user['role'];
$can_delete = true;
$error_msg = "";

// Check dependencies based on role
if ($role === 'customer') {
    // Check for orders
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE customer_id IN (SELECT customer_id FROM customers WHERE user_id = ?)");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    mysqli_stmt_close($stmt);
    if ($cnt > 0) {
        $can_delete = false;
        $error_msg = "Cannot delete customer with existing orders. Delete or reassign orders first.";
    }
} elseif ($role === 'business') {
    // Check for products, orders
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM products WHERE business_id IN (SELECT business_id FROM businesses WHERE user_id = ?)");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $products_cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    mysqli_stmt_close($stmt);
    
    if ($products_cnt > 0) {
        $can_delete = false;
        $error_msg = "Cannot delete business with existing products. Delete products first.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE business_id IN (SELECT business_id FROM businesses WHERE user_id = ?)");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $orders_cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
        mysqli_stmt_close($stmt);
        if ($orders_cnt > 0) {
            $can_delete = false;
            $error_msg = "Cannot delete business with existing orders.";
        }
    }
} elseif ($role === 'delivery') {
    // Check for pending deliveries
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM deliveries WHERE agent_id IN (SELECT agent_id FROM delivery_agents WHERE user_id = ?) AND status IN ('assigned', 'picked_up', 'in_transit')");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    mysqli_stmt_close($stmt);
    if ($cnt > 0) {
        $can_delete = false;
        $error_msg = "Cannot delete delivery agent with pending deliveries. Complete or reassign them first.";
    }
}
// For role 'admin', we allow deletion (but there should be at least one admin left – optional check, we can skip for simplicity)

if (!$can_delete) {
    $_SESSION['flash_message'] = $error_msg;
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);
$all_ok = true;

// Delete role-specific record first
if ($role === 'customer') {
    $stmt = mysqli_prepare($conn, "DELETE FROM customers WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (!mysqli_stmt_execute($stmt)) $all_ok = false;
    mysqli_stmt_close($stmt);
} elseif ($role === 'business') {
    $stmt = mysqli_prepare($conn, "DELETE FROM businesses WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (!mysqli_stmt_execute($stmt)) $all_ok = false;
    mysqli_stmt_close($stmt);
} elseif ($role === 'delivery') {
    $stmt = mysqli_prepare($conn, "DELETE FROM delivery_agents WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (!mysqli_stmt_execute($stmt)) $all_ok = false;
    mysqli_stmt_close($stmt);
}
// For admin, no extra table to delete

if ($all_ok) {
    // Delete from users table
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_commit($conn);
        $_SESSION['flash_message'] = "User has been permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = "Failed to delete user.";
        $_SESSION['flash_type'] = 'danger';
    }
    mysqli_stmt_close($stmt);
} else {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "Failed to delete user record.";
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>