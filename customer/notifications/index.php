<?php
// customer/notifications/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id using prepared statement
$stmt = mysqli_prepare($conn, "SELECT customer_id FROM customers WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$customer_id = $customer_data['customer_id'];

// GENERATE NOTIFICATIONS FROM ALL SOURCES

// Function to add notification if not exists
function addNotification($conn, $customer_id, $title, $message, $type, $reference_id = null) {
    // Check if similar notification already exists (avoid duplicates)
    $check_sql = "SELECT notification_id FROM customer_notifications 
                  WHERE customer_id = ? AND title = ? AND type = ? AND reference_id = ? 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, 'issi', $customer_id, $title, $type, $reference_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $insert_sql = "INSERT INTO customer_notifications (customer_id, title, message, type, reference_id) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt2 = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt2, 'isssi', $customer_id, $title, $message, $type, $reference_id);
        mysqli_stmt_execute($stmt2);
        mysqli_stmt_close($stmt2);
    }
    mysqli_stmt_close($stmt);
}

// 1. GENERATE PRICE ALERT NOTIFICATIONS
$price_sql = "SELECT pa.*, p.name as product_name, p.price as current_price 
              FROM price_alerts pa
              JOIN products p ON pa.product_id = p.product_id
              WHERE pa.customer_id = $customer_id 
              AND pa.status = 'active'
              AND pa.desired_price >= p.price";
$price_result = mysqli_query($conn, $price_sql);
while ($alert = mysqli_fetch_assoc($price_result)) {
    $title = "💰 Price Drop Alert!";
    $message = "Your desired price for '{$alert['product_name']}' (TSh " . number_format($alert['desired_price']) . 
               ") is now available! Current price: TSh " . number_format($alert['current_price']);
    addNotification($conn, $customer_id, $title, $message, 'price_alert', $alert['alert_id']);
}

// ============================================================
// 2. GENERATE ORDER NOTIFICATIONS - FIXED: Using order_date instead of created_at
// ============================================================
$order_sql = "SELECT o.order_id, o.status, o.order_date, o.grand_total, b.business_name 
              FROM orders o
              JOIN businesses b ON o.business_id = b.business_id
              WHERE o.customer_id = $customer_id
              AND o.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              ORDER BY o.order_date DESC";
$order_result = mysqli_query($conn, $order_sql);
while ($order = mysqli_fetch_assoc($order_result)) {
    $status_labels = [
        'pending' => ' Pending',
        'accepted' => ' Accepted',
        'confirmed' => ' Confirmed',
        'preparing' => '  Preparing',
        'ready' => ' Ready for Pickup',
        'picked_up' => ' Picked Up',
        'in_transit' => ' In Transit',
        'delivered' => ' Delivered',
        'cancelled' => ' Cancelled'
    ];
    $status_display = $status_labels[$order['status']] ?? ucfirst($order['status']);
    
    $title = " Order {$order['order_id']} - {$status_display}";
    $message = "Your order from '{$order['business_name']}' (TSh " . number_format($order['grand_total']) . 
               ") is now {$status_display}.";
    addNotification($conn, $customer_id, $title, $message, 'order', $order['order_id']);
}


// 3. GENERATE DELIVERY NOTIFICATIONS
$delivery_sql = "SELECT d.delivery_id, d.status, d.updated_at, d.delivery_fee,
                        o.order_id, b.business_name,
                        CONCAT(da.first_name, ' ', da.last_name) as agent_name
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.order_id
                 JOIN businesses b ON o.business_id = b.business_id
                 JOIN delivery_agents da ON d.agent_id = da.agent_id
                 WHERE o.customer_id = $customer_id
                 AND d.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY d.updated_at DESC";
$delivery_result = mysqli_query($conn, $delivery_sql);
while ($delivery = mysqli_fetch_assoc($delivery_result)) {
    $status_labels = [
        'assigned' => ' Assigned',
        'picked_up' => ' Picked Up',
        'in_transit' => ' In Transit',
        'delivered' => ' Delivered',
        'cancelled' => ' Cancelled'
    ];
    $status_display = $status_labels[$delivery['status']] ?? ucfirst($delivery['status']);
    
    $title = " Delivery {$delivery['delivery_id']} - {$status_display}";
    $message = "Delivery for order {$delivery['order_id']} from '{$delivery['business_name']}' is now {$status_display}.";
    if (!empty($delivery['agent_name'])) {
        $message .= " Agent: {$delivery['agent_name']}";
    }
    addNotification($conn, $customer_id, $title, $message, 'delivery', $delivery['delivery_id']);
}

// HANDLE AJAX REQUESTS
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action == 'mark_read' && isset($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        $update_sql = "UPDATE customer_notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $customer_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $success]);
        exit();
    }
    
    if ($action == 'mark_all_read') {
        $update_sql = "UPDATE customer_notifications SET is_read = 1 WHERE customer_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'i', $customer_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $success]);
        exit();
    }
    
    if ($action == 'delete_all') {
        $delete_sql = "DELETE FROM customer_notifications WHERE customer_id = ?";
        $stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($stmt, 'i', $customer_id);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => $success]);
        exit();
    }
    
    echo json_encode(['success' => false]);
    exit();
}

// HANDLE GET REQUESTS (mark read, delete, etc.)
// Handle single delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM customer_notifications WHERE notification_id = ? AND customer_id = ?";
    $stmt = mysqli_prepare($conn, $delete_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $customer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = "Notification deleted successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Handle mark single read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $update_sql = "UPDATE customer_notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $notif_id, $customer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = "Notification marked as read!";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Handle mark all read
if (isset($_GET['mark_all'])) {
    $update_sql = "UPDATE customer_notifications SET is_read = 1 WHERE customer_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, 'i', $customer_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = "All notifications marked as read!";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// GET FILTERED NOTIFICATIONS
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$read_filter = isset($_GET['read']) ? trim($_GET['read']) : '';

$sql = "SELECT * FROM customer_notifications WHERE customer_id = ?";
$params = [$customer_id];
$types = "i";

if (!empty($type_filter)) {
    $sql .= " AND type = ?";
    $params[] = $type_filter;
    $types .= "s";
}
if ($read_filter == 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($read_filter == 'read') {
    $sql .= " AND is_read = 1";
}
$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$notifications_result = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($row = mysqli_fetch_assoc($notifications_result)) {
    $notifications[] = $row;
}
mysqli_stmt_close($stmt);

// STATISTICS
$total_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM customer_notifications WHERE customer_id = ?");
mysqli_stmt_bind_param($total_stmt, 'i', $customer_id);
mysqli_stmt_execute($total_stmt);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt));
$total = (int)$total_data['total'];
mysqli_stmt_close($total_stmt);

$order_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM customer_notifications WHERE customer_id = ? AND type = 'order'");
mysqli_stmt_bind_param($order_stmt, 'i', $customer_id);
mysqli_stmt_execute($order_stmt);
$order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));
$order_count = (int)$order_data['count'];
mysqli_stmt_close($order_stmt);

$delivery_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM customer_notifications WHERE customer_id = ? AND type = 'delivery'");
mysqli_stmt_bind_param($delivery_stmt, 'i', $customer_id);
mysqli_stmt_execute($delivery_stmt);
$delivery_data = mysqli_fetch_assoc(mysqli_stmt_get_result($delivery_stmt));
$delivery_count = (int)$delivery_data['count'];
mysqli_stmt_close($delivery_stmt);

$price_alert_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM customer_notifications WHERE customer_id = ? AND type = 'price_alert'");
mysqli_stmt_bind_param($price_alert_stmt, 'i', $customer_id);
mysqli_stmt_execute($price_alert_stmt);
$price_alert_data = mysqli_fetch_assoc(mysqli_stmt_get_result($price_alert_stmt));
$price_alert_count = (int)$price_alert_data['count'];
mysqli_stmt_close($price_alert_stmt);

$unread_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM customer_notifications WHERE customer_id = ? AND is_read = 0");
mysqli_stmt_bind_param($unread_stmt, 'i', $customer_id);
mysqli_stmt_execute($unread_stmt);
$unread_data = mysqli_fetch_assoc(mysqli_stmt_get_result($unread_stmt));
$unread_count = (int)$unread_data['unread'];
mysqli_stmt_close($unread_stmt);

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Helper function for time ago
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "Yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Notifications | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f7f9fc; color: #1e293b; }
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s;
        }
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .btn-mark-all {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            transition: 0.3s;
        }
        .btn-mark-all:hover { background: #d35400; transform: translateY(-2px); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: 0.3s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: #e67e22; }
        .stat-card h3 { font-size: 28px; font-weight: 700; color: #e67e22; }
        .stat-card p { color: #64748b; font-size: 13px; margin-top: 5px; }
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .filter-btn {
            padding: 6px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 13px;
            transition: 0.3s;
            background: #f8fafc;
            color: #475569;
        }
        .filter-btn:hover, .filter-btn.active { background: #e67e22; color: white; }
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .notifications-list {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 18px 24px;
            border-bottom: 1px solid #eef2f6;
            transition: 0.3s;
        }
        .notification-item:hover { background: #fffbeb; }
        .notification-item.unread { background: #fff5eb; border-left: 3px solid #e67e22; }
        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .icon-order { background: rgba(230,126,34,0.1); color: #e67e22; }
        .icon-delivery { background: rgba(52,152,219,0.1); color: #3498db; }
        .icon-price_alert { background: rgba(243,156,18,0.1); color: #f39c12; }
        .notification-content { flex: 1; }
        .notification-title { font-weight: 600; font-size: 15px; margin-bottom: 5px; color: #1e293b; }
        .notification-message { font-size: 13px; color: #64748b; margin-bottom: 8px; line-height: 1.4; }
        .notification-time {
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #94a3b8;
            transition: 0.3s;
            text-decoration: none;
            padding: 5px;
        }
        .action-btn:hover { color: #e67e22; }
        .action-btn.delete:hover { color: #e74c3c; }
        .read-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            background: #e2e8f0;
            color: #64748b;
        }
        .read-badge.unread { background: #e67e22; color: white; }
        .empty-state {
            text-align: center;
            padding: 60px;
            color: #94a3b8;
        }
        .empty-state i { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; }
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            z-index: 2000;
            opacity: 0;
            transition: 0.3s;
            font-size: 13px;
        }
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .notification-item { flex-direction: column; }
            .notification-actions { margin-top: 10px; }
            .filters-bar { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p>Stay updated with your order, delivery and price alerts</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <?php if($unread_count > 0): ?>
                <a href="?mark_all=1" class="btn-mark-all" onclick="return confirm('Mark all notifications as read?')">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            <?php if(!empty($notifications)): ?>
                <a href="#" class="btn-mark-all" style="background: #e74c3c;" onclick="deleteAllNotifications(event)">
                    <i class="fas fa-trash"></i> Clear All
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if(!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card" onclick="window.location.href='?type='">
            <h3><?php echo $total; ?></h3>
            <p><i class="fas fa-bell"></i> Total</p>
        </div>
        <div class="stat-card" onclick="window.location.href='?type=order'">
            <h3><?php echo $order_count; ?></h3>
            <p><i class="fas fa-shopping-cart"></i> Orders</p>
        </div>
        <div class="stat-card" onclick="window.location.href='?type=delivery'">
            <h3><?php echo $delivery_count; ?></h3>
            <p><i class="fas fa-truck"></i> Delivery</p>
        </div>
        <div class="stat-card" onclick="window.location.href='?type=price_alert'">
            <h3><?php echo $price_alert_count; ?></h3>
            <p><i class="fas fa-tag"></i> Price Alert</p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="filters-bar">
        <a href="index.php" class="filter-btn <?php echo empty($type_filter) && empty($read_filter) ? 'active' : ''; ?>">All</a>
        <a href="?type=order" class="filter-btn <?php echo $type_filter == 'order' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="?type=delivery" class="filter-btn <?php echo $type_filter == 'delivery' ? 'active' : ''; ?>"><i class="fas fa-truck"></i> Delivery</a>
        <a href="?type=price_alert" class="filter-btn <?php echo $type_filter == 'price_alert' ? 'active' : ''; ?>"><i class="fas fa-tag"></i> Price Alerts</a>
        <a href="?read=unread" class="filter-btn <?php echo $read_filter == 'unread' ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Unread</a>
        <a href="?read=read" class="filter-btn <?php echo $read_filter == 'read' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> Read</a>
    </div>
    
    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if(empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>You don't have any notifications at this time.<br>When your order status changes or price drops, you'll see updates here.</p>
                <a href="../products/index.php" style="display: inline-block; margin-top: 15px; background: #e67e22; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none;">Continue Shopping</a>
            </div>
        <?php else: ?>
            <?php foreach($notifications as $notif): 
                $icon_class = '';
                $icon_icon = '';
                if($notif['type'] == 'order') {
                    $icon_class = 'icon-order';
                    $icon_icon = 'fa-shopping-cart';
                } elseif($notif['type'] == 'delivery') {
                    $icon_class = 'icon-delivery';
                    $icon_icon = 'fa-truck';
                } elseif($notif['type'] == 'price_alert') {
                    $icon_class = 'icon-price_alert';
                    $icon_icon = 'fa-tag';
                } else {
                    $icon_class = 'icon-order';
                    $icon_icon = 'fa-bell';
                }
            ?>
            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notif['notification_id']; ?>">
                <div class="notification-icon <?php echo $icon_class; ?>">
                    <i class="fas <?php echo $icon_icon; ?>"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                    <div class="notification-message"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></div>
                    <div class="notification-time">
                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?></span>
                        <span class="read-badge <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <i class="fas fa-<?php echo $notif['is_read'] ? 'check-circle' : 'circle'; ?>"></i>
                            <?php echo $notif['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                    </div>
                </div>
                <div class="notification-actions">
                    <?php if(!$notif['is_read']): ?>
                        <a href="?mark_read=<?php echo $notif['notification_id']; ?>" class="action-btn" title="Mark as read">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $notif['notification_id']; ?>" class="action-btn delete" title="Delete" onclick="return confirm('Delete this notification?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="toastMessage" class="toast-message"></div>

<script>
function showToast(message, isError = false) {
    let toast = document.getElementById('toastMessage');
    toast.textContent = message;
    toast.style.background = isError ? '#dc2626' : '#27ae60';
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

function deleteAllNotifications(event) {
    event.preventDefault();
    if (!confirm('Delete all notifications? This cannot be undone.')) return;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=delete_all'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('All notifications deleted');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast('Failed to delete notifications', true);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error deleting notifications', true);
    });
}

// Mark as read via AJAX
document.querySelectorAll('.notification-item .action-btn').forEach(btn => {
    if (btn.classList.contains('delete')) return;
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        let url = this.getAttribute('href');
        if (url && url.includes('mark_read')) {
            fetch(url)
                .then(() => {
                    let item = this.closest('.notification-item');
                    if (item) {
                        item.classList.remove('unread');
                        let badge = item.querySelector('.read-badge');
                        if (badge) {
                            badge.classList.remove('unread');
                            badge.innerHTML = '<i class="fas fa-check-circle"></i> Read';
                        }
                        this.remove();
                        showToast('Marked as read');
                    }
                });
        }
    });
});
</script>

</body>
</html>
