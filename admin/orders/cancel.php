<?php
// admin/orders/cancel.php 
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

// Check if order can be cancelled (status not delivered/cancelled and not in transit)
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

$current_status = $order['status'];
$allowed_cancel = ['pending', 'accepted', 'confirmed', 'preparing', 'ready'];
if (!in_array($current_status, $allowed_cancel)) {
    $_SESSION['flash_message'] = "Order cannot be cancelled at this stage (status: $current_status).";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Begin transaction to cancel order and restore stock
mysqli_begin_transaction($conn);

// Update order status to cancelled
$stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'cancelled' WHERE order_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
$order_ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($order_ok) {
    // Restore stock (if any order items)
    $items_stmt = mysqli_prepare($conn, "SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
    mysqli_stmt_execute($items_stmt);
    $items_res = mysqli_stmt_get_result($items_stmt);
    while ($item = mysqli_fetch_assoc($items_res)) {
        $restore = mysqli_prepare($conn, "UPDATE products SET quantity_in_stock = quantity_in_stock + ? WHERE product_id = ?");
        mysqli_stmt_bind_param($restore, 'ii', $item['quantity'], $item['product_id']);
        mysqli_stmt_execute($restore);
        mysqli_stmt_close($restore);
    }
    mysqli_stmt_close($items_stmt);
    
    // Insert a history record (optional)
    $hist_stmt = mysqli_prepare($conn, "INSERT INTO order_status_history (order_id, old_status, new_status, notes, created_by) VALUES (?, ?, 'cancelled', 'Order cancelled by admin', ?)");
    mysqli_stmt_bind_param($hist_stmt, 'isi', $order_id, $current_status, $_SESSION['user_id']);
    mysqli_stmt_execute($hist_stmt);
    mysqli_stmt_close($hist_stmt);
    
    mysqli_commit($conn);
    $_SESSION['flash_message'] = "Order #$order_id has been cancelled and stock restored.";
    $_SESSION['flash_type'] = 'success';
} else {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "Failed to cancel order.";
    $_SESSION['flash_type'] = 'danger';
}

header("Location: index.php");
exit();
?>