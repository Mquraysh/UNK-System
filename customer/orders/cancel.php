<?php
// customer/orders/cancel.php 
require_once '../../config/database.php';

session_start();

// Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    $_SESSION['flash_message'] = "Invalid order ID";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);
$customer_id = $customer['customer_id'];

// CHECK: Order must be 'pending' ONLY
$check_sql = "SELECT order_id, status FROM orders 
              WHERE order_id = '$order_id' 
              AND customer_id = '$customer_id' 
              AND status = 'pending'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) > 0) {
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Get all products in this order
        $items_sql = "SELECT product_id, quantity FROM order_items WHERE order_id = '$order_id'";
        $items_result = mysqli_query($conn, $items_sql);
        
        $restored_count = 0;
        
        while ($item = mysqli_fetch_assoc($items_result)) {
            $product_id = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            
            // Update product quantity - ADD back the cancelled quantity
            $update_sql = "UPDATE products 
                           SET quantity_in_stock = quantity_in_stock + $quantity 
                           WHERE product_id = $product_id";
            if (!mysqli_query($conn, $update_sql)) {
                throw new Exception("Failed to restore stock for product $product_id: " . mysqli_error($conn));
            }
            $restored_count++;
        }
        
        // 2. Update order status to cancelled
        $update_order = "UPDATE orders 
                         SET status = 'cancelled' 
                         WHERE order_id = '$order_id'";
        if (!mysqli_query($conn, $update_order)) {
            throw new Exception("Failed to update order status: " . mysqli_error($conn));
        }
        
        // 3. Insert into order_logs
        $note = "Order cancelled by customer. Stock restored for $restored_count product(s).";
        $log_sql = "INSERT INTO order_logs (order_id, action, user_id, note, created_at) 
                    VALUES ('$order_id', 'cancelled_by_customer', '$user_id', '$note', NOW())";
        if (!mysqli_query($conn, $log_sql)) {
            throw new Exception("Failed to log cancellation: " . mysqli_error($conn));
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        $_SESSION['flash_message'] = "Order $order_id cancelled successfully. " . $restored_count . " product(s) stock restored.";
        $_SESSION['flash_type'] = "success";
        
    } catch (Exception $e) {
        // Rollback if anything fails
        mysqli_rollback($conn);
        error_log("Order cancellation error: " . $e->getMessage());
        
        $_SESSION['flash_message'] = "Failed to cancel order: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
    
} else {
    // Check if order exists but not pending
    $check_status_sql = "SELECT status FROM orders WHERE order_id = '$order_id' AND customer_id = '$customer_id'";
    $status_result = mysqli_query($conn, $check_status_sql);
    
    if (mysqli_num_rows($status_result) > 0) {
        $status_row = mysqli_fetch_assoc($status_result);
        $current_status = $status_row['status'];
        
        if ($current_status == 'cancelled') {
            $_SESSION['flash_message'] = "This order has already been cancelled.";
        } else {
            $_SESSION['flash_message'] = "Order cannot be cancelled because it is already '" . ucfirst($current_status) . "'. Only pending orders can be cancelled.";
        }
        $_SESSION['flash_type'] = "danger";
    } else {
        $_SESSION['flash_message'] = "Order not found or does not belong to you.";
        $_SESSION['flash_type'] = "danger";
    }
}

header("Location: index.php");
exit();
?>