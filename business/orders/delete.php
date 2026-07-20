<?php
// business/orders/delete.php - DELETE ORDER (SECURE)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify order ownership and deletable status
$check_sql = "SELECT o.order_id FROM orders o 
              JOIN businesses b ON o.business_id = b.business_id 
              WHERE o.order_id = ? AND b.user_id = ? AND o.status IN ('pending', 'cancelled')";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['flash_message'] = 'Order not found or cannot be deleted.';
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}
mysqli_stmt_close($stmt);

// Delete order items first (foreign key constraint)
$delete_items = "DELETE FROM order_items WHERE order_id = ?";
$stmt = mysqli_prepare($conn, $delete_items);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Delete the order
$delete_order = "DELETE FROM orders WHERE order_id = ?";
$stmt = mysqli_prepare($conn, $delete_order);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['flash_message'] = "Order #{$order_id} has been permanently deleted.";
$_SESSION['flash_type'] = 'success';
header("Location: index.php");
exit();
?>