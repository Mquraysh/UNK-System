<?php
// admin/notifications/functions.php - Notification Helper Functions
// ============================================================
// THIS FILE CONTAINS ALL NOTIFICATION RELATED FUNCTIONS
// ============================================================

/**
 * Add a notification for a specific admin
 */
function add_admin_notification($conn, $admin_id, $type, $title, $message, $link = null) {
    $type_esc = mysqli_real_escape_string($conn, $type);
    $title_esc = mysqli_real_escape_string($conn, $title);
    $message_esc = mysqli_real_escape_string($conn, $message);
    $link_esc = mysqli_real_escape_string($conn, $link);
    
    $sql = "INSERT INTO admin_notifications (admin_id, type, title, message, link) 
            VALUES ($admin_id, '$type_esc', '$title_esc', '$message_esc', '$link_esc')";
    return mysqli_query($conn, $sql);
}

/**
 * Add notification for all active admins
 */
function add_notification_all_admins($conn, $type, $title, $message, $link = null) {
    $admins_result = mysqli_query($conn, "SELECT user_id FROM users WHERE role = 'admin' AND status = 'active'");
    $success = true;
    $count = 0;
    
    while ($admin = mysqli_fetch_assoc($admins_result)) {
        if (add_admin_notification($conn, $admin['user_id'], $type, $title, $message, $link)) {
            $count++;
        } else {
            $success = false;
        }
    }
    return ['success' => $success, 'count' => $count];
}

/**
 * Get unread notification count for admin
 */
function get_unread_count($conn, $admin_id) {
    $sql = "SELECT COUNT(*) as count FROM admin_notifications 
            WHERE admin_id = $admin_id AND is_read = 0";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['count'] ?? 0;
}

/**
 * Get total notification count for admin
 */
function get_total_count($conn, $admin_id) {
    $sql = "SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = $admin_id";
    $result = mysqli_query($conn, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['count'] ?? 0;
}

/**
 * Get notifications by type count
 */
function get_type_counts($conn, $admin_id) {
    $counts = [];
    $sql = "SELECT type, COUNT(*) as count FROM admin_notifications 
            WHERE admin_id = $admin_id GROUP BY type";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $counts[$row['type']] = $row['count'];
    }
    return $counts;
}

/**
 * Get recent notifications
 */
function get_recent_notifications($conn, $admin_id, $limit = 10) {
    $sql = "SELECT * FROM admin_notifications 
            WHERE admin_id = $admin_id 
            ORDER BY created_at DESC 
            LIMIT $limit";
    $result = mysqli_query($conn, $sql);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    return $notifications;
}

/**
 * Mark notification as read
 */
function mark_notification_read($conn, $notification_id, $admin_id) {
    $sql = "UPDATE admin_notifications SET is_read = 1 
            WHERE notification_id = $notification_id AND admin_id = $admin_id";
    return mysqli_query($conn, $sql);
}

/**
 * Mark all notifications as read
 */
function mark_all_notifications_read($conn, $admin_id) {
    $sql = "UPDATE admin_notifications SET is_read = 1 WHERE admin_id = $admin_id";
    return mysqli_query($conn, $sql);
}

/**
 * Delete notification
 */
function delete_notification($conn, $notification_id, $admin_id) {
    $sql = "DELETE FROM admin_notifications 
            WHERE notification_id = $notification_id AND admin_id = $admin_id";
    return mysqli_query($conn, $sql);
}

/**
 * Delete all notifications for admin
 */
function delete_all_notifications($conn, $admin_id) {
    $sql = "DELETE FROM admin_notifications WHERE admin_id = $admin_id";
    return mysqli_query($conn, $sql);
}

// ============================================================
// ORDER NOTIFICATIONS
// ============================================================

/**
 * New order notification
 */
function notify_new_order($conn, $order_id, $customer_name, $amount) {
    $title = "🛒 New Order #$order_id";
    $message = "$customer_name placed an order worth TSh " . number_format($amount);
    $link = "../orders/details.php?id=$order_id";
    return add_notification_all_admins($conn, 'order', $title, $message, $link);
}

/**
 * Order status update notification
 */
function notify_order_status($conn, $order_id, $status, $customer_name) {
    $status_labels = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    $status_display = $status_labels[$status] ?? ucfirst($status);
    $title = "📦 Order #$order_id - $status_display";
    $message = "Order #$order_id from $customer_name is now $status_display";
    $link = "../orders/details.php?id=$order_id";
    return add_notification_all_admins($conn, 'order', $title, $message, $link);
}

// ============================================================
// DELIVERY NOTIFICATIONS
// ============================================================

/**
 * New delivery notification
 */
function notify_new_delivery($conn, $delivery_id, $order_id) {
    $title = "🚚 New Delivery #$delivery_id";
    $message = "New delivery created for order #$order_id";
    $link = "../deliveries/delivery-details.php?id=$delivery_id";
    return add_notification_all_admins($conn, 'delivery', $title, $message, $link);
}

/**
 * Delivery status update notification
 */
function notify_delivery_status($conn, $delivery_id, $status, $order_id) {
    $status_labels = [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'picked_up' => 'Picked Up',
        'in_transit' => 'In Transit',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    $status_display = $status_labels[$status] ?? ucfirst($status);
    $title = "🚚 Delivery #$delivery_id - $status_display";
    $message = "Delivery for order #$order_id is now $status_display";
    $link = "../deliveries/delivery-details.php?id=$delivery_id";
    return add_notification_all_admins($conn, 'delivery', $title, $message, $link);
}

// ============================================================
// PAYMENT NOTIFICATIONS
// ============================================================

/**
 * Payment received notification
 */
function notify_payment_received($conn, $order_id, $amount, $method) {
    $method_display = strtoupper($method ?? 'Unknown');
    $title = "💰 Payment Received - Order #$order_id";
    $message = "Payment of TSh " . number_format($amount) . " received via $method_display";
    $link = "../orders/details.php?id=$order_id";
    return add_notification_all_admins($conn, 'payment', $title, $message, $link);
}

/**
 * Payment failed notification
 */
function notify_payment_failed($conn, $order_id, $amount, $method) {
    $method_display = strtoupper($method ?? 'Unknown');
    $title = "⚠️ Payment Failed - Order #$order_id";
    $message = "Payment of TSh " . number_format($amount) . " failed via $method_display";
    $link = "../orders/details.php?id=$order_id";
    return add_notification_all_admins($conn, 'payment', $title, $message, $link);
}

// ============================================================
// PRODUCT NOTIFICATIONS
// ============================================================

/**
 * Low stock alert notification
 */
function notify_low_stock($conn, $product_id, $product_name, $stock) {
    $title = "📦 Low Stock Alert: $product_name";
    $message = "Product has only $stock units remaining. Please restock soon.";
    $link = "../products/edit.php?id=$product_id";
    return add_notification_all_admins($conn, 'alert', $title, $message, $link);
}

/**
 * Out of stock notification
 */
function notify_out_of_stock($conn, $product_id, $product_name) {
    $title = "🚫 Out of Stock: $product_name";
    $message = "Product is completely out of stock. Please restock immediately.";
    $link = "../products/edit.php?id=$product_id";
    return add_notification_all_admins($conn, 'alert', $title, $message, $link);
}

/**
 * New product added notification
 */
function notify_new_product($conn, $product_id, $product_name, $business_name) {
    $title = "🆕 New Product Added";
    $message = "$business_name added a new product: $product_name";
    $link = "../products/edit.php?id=$product_id";
    return add_notification_all_admins($conn, 'product', $title, $message, $link);
}

// ============================================================
// USER NOTIFICATIONS
// ============================================================

/**
 * New user registration notification
 */
function notify_new_user($conn, $user_id, $name, $role) {
    $role_display = ucfirst($role);
    $title = "👤 New $role_display Registered";
    $message = "$name has registered as a $role_display";
    $link = "../users/view.php?id=$user_id";
    return add_notification_all_admins($conn, 'user', $title, $message, $link);
}

/**
 * User status change notification
 */
function notify_user_status($conn, $user_id, $name, $status) {
    $status_display = ucfirst($status);
    $title = "👤 User $status_display";
    $message = "$name's account has been $status_display";
    $link = "../users/view.php?id=$user_id";
    return add_notification_all_admins($conn, 'user', $title, $message, $link);
}

// ============================================================
// SYSTEM NOTIFICATIONS
// ============================================================

/**
 * System maintenance notification
 */
function notify_system_maintenance($conn, $message) {
    $title = "🔧 System Maintenance";
    $message = $message ?? "System maintenance scheduled. Please save your work.";
    return add_notification_all_admins($conn, 'system', $title, $message, null);
}

/**
 * Backup completed notification
 */
function notify_backup_completed($conn, $backup_name, $size) {
    $title = "💾 Backup Completed";
    $size_display = $size ? number_format($size / 1024 / 1024, 1) . ' MB' : 'Unknown size';
    $message = "Database backup '$backup_name' completed successfully. Size: $size_display";
    return add_notification_all_admins($conn, 'system', $title, $message, null);
}

/**
 * Error notification
 */
function notify_system_error($conn, $error_message, $source = null) {
    $title = "⚠️ System Error";
    $message = $error_message . ($source ? " (Source: $source)" : '');
    return add_notification_all_admins($conn, 'alert', $title, $message, null);
}

// ============================================================
// BUSINESS NOTIFICATIONS
// ============================================================

/**
 * New business registration notification
 */
function notify_new_business($conn, $business_id, $business_name, $owner_name) {
    $title = "🏪 New Business Registered";
    $message = "$business_name registered by $owner_name";
    $link = "../business/view.php?id=$business_id";
    return add_notification_all_admins($conn, 'user', $title, $message, $link);
}

/**
 * Business verification notification
 */
function notify_business_verified($conn, $business_id, $business_name) {
    $title = "✅ Business Verified";
    $message = "$business_name has been verified and is now active";
    $link = "../business/view.php?id=$business_id";
    return add_notification_all_admins($conn, 'user', $title, $message, $link);
}

// ============================================================
// DELIVERY AGENT NOTIFICATIONS
// ============================================================

/**
 * New delivery agent registration
 */
function notify_new_delivery_agent($conn, $agent_id, $name) {
    $title = "🚚 New Delivery Agent";
    $message = "$name has registered as a delivery agent";
    $link = "../delivery_agent/details.php?id=$agent_id";
    return add_notification_all_admins($conn, 'user', $title, $message, $link);
}

// ============================================================
// REVIEW NOTIFICATIONS
// ============================================================

/**
 * New review notification
 */
function notify_new_review($conn, $review_id, $product_name, $rating, $customer_name) {
    $title = "⭐ New Review for $product_name";
    $message = "$customer_name gave $product_name a $rating-star rating";
    $link = "../products/reviews.php?id=$review_id";
    return add_notification_all_admins($conn, 'product', $title, $message, $link);
}

// ============================================================
// HELPER FUNCTIONS FOR DISPLAY
// ============================================================

/**
 * Get notification icon class
 */
function get_notification_icon($type) {
    $icons = [
        'order' => 'fa-shopping-cart',
        'delivery' => 'fa-truck',
        'payment' => 'fa-credit-card',
        'product' => 'fa-box',
        'user' => 'fa-user',
        'system' => 'fa-cog',
        'alert' => 'fa-exclamation-triangle'
    ];
    return $icons[$type] ?? 'fa-bell';
}

/**
 * Get notification color class
 */
function get_notification_color($type) {
    $colors = [
        'order' => '#2563eb',
        'delivery' => '#059669',
        'payment' => '#d97706',
        'product' => '#5b21b6',
        'user' => '#be185d',
        'system' => '#64748b',
        'alert' => '#dc2626'
    ];
    return $colors[$type] ?? '#64748b';
}

/**
 * Get notification type badge class
 */
function get_notification_badge_class($type) {
    return 'type-badge ' . $type;
}

/**
 * Format time ago
 */
function time_ago($timestamp) {
    $time_diff = time() - strtotime($timestamp);
    
    if ($time_diff < 60) {
        return $time_diff . ' seconds ago';
    } elseif ($time_diff < 3600) {
        return floor($time_diff / 60) . ' minutes ago';
    } elseif ($time_diff < 86400) {
        return floor($time_diff / 3600) . ' hours ago';
    } elseif ($time_diff < 604800) {
        return floor($time_diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', strtotime($timestamp));
    }
}

/**
 * Display notification bell with count
 */
function render_notification_bell($conn, $admin_id) {
    $count = get_unread_count($conn, $admin_id);
    $html = '<div class="notif-bell-wrapper">';
    $html .= '<a href="../notifications/index.php" class="notif-bell">';
    $html .= '<i class="fas fa-bell"></i>';
    if ($count > 0) {
        $html .= '<span class="notif-count">' . $count . '</span>';
    }
    $html .= '</a>';
    $html .= '</div>';
    return $html;
}

/**
 * Get notification summary for dashboard
 */
function get_notification_summary($conn, $admin_id) {
    return [
        'total' => get_total_count($conn, $admin_id),
        'unread' => get_unread_count($conn, $admin_id),
        'by_type' => get_type_counts($conn, $admin_id),
        'recent' => get_recent_notifications($conn, $admin_id, 5)
    ];
}

/**
 * Clean old notifications (older than 30 days)
 */
function clean_old_notifications($conn, $admin_id, $days = 30) {
    $sql = "DELETE FROM admin_notifications 
            WHERE admin_id = $admin_id 
            AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)
            AND is_read = 1";
    return mysqli_query($conn, $sql);
}

/**
 * Auto-create notifications for important events
 * This function can be called from various parts of the system
 */
function auto_create_notifications($conn, $event_type, $data) {
    switch ($event_type) {
        case 'order_created':
            return notify_new_order($conn, $data['order_id'], $data['customer_name'], $data['amount']);
            
        case 'order_status_changed':
            return notify_order_status($conn, $data['order_id'], $data['status'], $data['customer_name']);
            
        case 'payment_received':
            return notify_payment_received($conn, $data['order_id'], $data['amount'], $data['method']);
            
        case 'delivery_created':
            return notify_new_delivery($conn, $data['delivery_id'], $data['order_id']);
            
        case 'delivery_status_changed':
            return notify_delivery_status($conn, $data['delivery_id'], $data['status'], $data['order_id']);
            
        case 'low_stock':
            return notify_low_stock($conn, $data['product_id'], $data['product_name'], $data['stock']);
            
        case 'user_registered':
            return notify_new_user($conn, $data['user_id'], $data['name'], $data['role']);
            
        case 'product_added':
            return notify_new_product($conn, $data['product_id'], $data['product_name'], $data['business_name']);
            
        default:
            return false;
    }
}
?>