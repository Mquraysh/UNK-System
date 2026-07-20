<?php
// config/notifications.php - Notification Functions

function addNotification($conn, $admin_id, $type, $title, $message, $link = null) {
    // Create table if not exists
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS admin_notifications (
        notification_id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        type ENUM('order','delivery','payment','product','user','system','alert') DEFAULT 'system',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_id (admin_id),
        INDEX idx_is_read (is_read),
        INDEX idx_type (type)
    )");
    
    $stmt = mysqli_prepare($conn, "INSERT INTO admin_notifications (admin_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'issss', $admin_id, $type, $title, $message, $link);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function notifyOrder($conn, $admin_id, $order_id, $customer_name) {
    return addNotification($conn, $admin_id, 'order', 'New Order #' . $order_id, 'New order placed by ' . $customer_name, '../orders/view.php?id=' . $order_id);
}

function notifyPayment($conn, $admin_id, $order_id, $amount) {
    return addNotification($conn, $admin_id, 'payment', 'Payment Received', 'Payment of TSh ' . number_format($amount) . ' received for order #' . $order_id, '../orders/view.php?id=' . $order_id);
}

function notifyDelivery($conn, $admin_id, $order_id, $status) {
    return addNotification($conn, $admin_id, 'delivery', 'Delivery Update', 'Order #' . $order_id . ' status: ' . $status, '../orders/view.php?id=' . $order_id);
}

function notifyProduct($conn, $admin_id, $product_id, $product_name) {
    return addNotification($conn, $admin_id, 'product', 'New Product Added', $product_name . ' has been added to inventory', '../products/edit.php?id=' . $product_id);
}

function notifyUser($conn, $admin_id, $user_name, $user_type) {
    return addNotification($conn, $admin_id, 'user', 'New ' . ucfirst($user_type) . ' Registered', $user_name . ' registered as ' . $user_type, '../users/view.php?type=' . $user_type);
}

function notifySystem($conn, $admin_id, $title, $message) {
    return addNotification($conn, $admin_id, 'system', $title, $message, null);
}

function notifyAlert($conn, $admin_id, $title, $message, $link = null) {
    return addNotification($conn, $admin_id, 'alert', $title, $message, $link);
}
?>