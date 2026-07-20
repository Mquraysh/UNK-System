<?php
// customer/orders/delete.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer ID
$customer_res = mysqli_query($conn, "SELECT customer_id FROM customers WHERE user_id = '$user_id'");
if (!$customer_res || mysqli_num_rows($customer_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer = mysqli_fetch_assoc($customer_res);
$customer_id = $customer['customer_id'];

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid order ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$order_id = (int)$_GET['id'];

// Verify that this order belongs to the logged-in customer
$check_sql = "SELECT order_id, status FROM orders WHERE order_id = $order_id AND customer_id = $customer_id";
$check_result = mysqli_query($conn, $check_sql);
if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['flash_message'] = "Order not found or you don't have permission.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($check_result);

// Only allow deletion of pending or cancelled orders
if (!in_array($order['status'], ['pending', 'cancelled'])) {
    $_SESSION['flash_message'] = "You can only delete pending or cancelled orders.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // 1. Get all products in this order (BEFORE deleting)
    $items_sql = "SELECT product_id, quantity FROM order_items WHERE order_id = $order_id";
    $items_result = mysqli_query($conn, $items_sql);
    
    if (!$items_result) {
        throw new Exception("Failed to get order items: " . mysqli_error($conn));
    }
    
    // 2. Restore stock for each product (if order was pending)
    $restored_count = 0;
    if ($order['status'] == 'pending') {
        while ($item = mysqli_fetch_assoc($items_result)) {
            $product_id = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            
            $update_sql = "UPDATE products 
                           SET quantity_in_stock = quantity_in_stock + $quantity 
                           WHERE product_id = $product_id";
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Failed to restore stock for product $product_id: " . mysqli_error($conn));
            }
            $restored_count++;
        }
    }
    
    // Check if order_logs table exists
    $table_check = "SHOW TABLES LIKE 'order_logs'";
    $table_result = mysqli_query($conn, $table_check);
    $log_table_exists = mysqli_num_rows($table_result) > 0;
    
    if ($log_table_exists) {
        $note = "Order #$order_id permanently deleted by customer. Stock restored for $restored_count product(s).";
        $log_sql = "INSERT INTO order_logs (order_id, action, user_id, note, created_at) 
                    VALUES ($order_id, 'deleted_by_customer', $user_id, '$note', NOW())";
        if (!mysqli_query($conn, $log_sql)) {
            throw new Exception("Failed to log deletion: " . mysqli_error($conn));
        }
    }
    
    // 3. Delete order items
    $delete_items = "DELETE FROM order_items WHERE order_id = $order_id";
    if (!mysqli_query($conn, $delete_items)) {
        throw new Exception("Failed to delete order items: " . mysqli_error($conn));
    }
    
    // 4. Delete the order
    $delete_order = "DELETE FROM orders WHERE order_id = $order_id";
    if (!mysqli_query($conn, $delete_order)) {
        throw new Exception("Failed to delete order: " . mysqli_error($conn));
    }
    
    // Commit transaction
    mysqli_commit($conn);
    
    if ($restored_count > 0) {
        $_SESSION['flash_message'] = "Order $order_id has been permanently deleted. Stock restored for $restored_count product(s).";
    } else {
        $_SESSION['flash_message'] = "Order $order_id has been permanently deleted.";
    }
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    // Rollback if anything fails
    mysqli_rollback($conn);
    error_log("Order deletion error: " . $e->getMessage());
    
    $_SESSION['flash_message'] = "Failed to delete order: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>