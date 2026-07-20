<?php
// admin/orders/delete.php - DELETE ORDER PERMANENTLY (ADMIN)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    $_SESSION['flash_message'] = "Invalid order ID.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Only allow deletion if order status is pending or cancelled
$stmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['flash_message'] = "Order not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

if (!in_array($order['status'], ['pending', 'cancelled'])) {
    $_SESSION['flash_message'] = "Only pending or cancelled orders can be deleted.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Delete order items first (foreign key)
$del_items = mysqli_prepare($conn, "DELETE FROM order_items WHERE order_id = ?");
mysqli_stmt_bind_param($del_items, 'i', $order_id);
$items_ok = mysqli_stmt_execute($del_items);
mysqli_stmt_close($del_items);

if ($items_ok) {
    $del_order = mysqli_prepare($conn, "DELETE FROM orders WHERE order_id = ?");
    mysqli_stmt_bind_param($del_order, 'i', $order_id);
    $order_ok = mysqli_stmt_execute($del_order);
    mysqli_stmt_close($del_order);
    if ($order_ok) {
        $_SESSION['flash_message'] = "Order #$order_id permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Failed to delete order.";
        $_SESSION['flash_type'] = 'danger';
    }
} else {
    $_SESSION['flash_message'] = "Failed to delete order items.";
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>