<?php
// business/notifications/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get business ID using prepared statement
$stmt = mysqli_prepare($conn, "SELECT business_id FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$business_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$business_data) {
    $_SESSION['flash_message'] = 'Business profile not found.';
    $_SESSION['flash_type'] = 'danger';
    header("Location: ../register.php");
    exit();
}
$business_id = (int)$business_data['business_id'];

// Get filter parameters
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$read_filter = isset($_GET['read']) ? trim($_GET['read']) : '';

// Build query with prepared statement dynamically
$sql = "SELECT * FROM business_notifications WHERE business_id = ?";
$params = [$business_id];
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

// Statistics using prepared statements
$total_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM business_notifications WHERE business_id = ?");
mysqli_stmt_bind_param($total_stmt, 'i', $business_id);
mysqli_stmt_execute($total_stmt);
$total_data = mysqli_fetch_assoc(mysqli_stmt_get_result($total_stmt));
$total = (int)$total_data['total'];
mysqli_stmt_close($total_stmt);

$unread_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as unread FROM business_notifications WHERE business_id = ? AND is_read = 0");
mysqli_stmt_bind_param($unread_stmt, 'i', $business_id);
mysqli_stmt_execute($unread_stmt);
$unread_data = mysqli_fetch_assoc(mysqli_stmt_get_result($unread_stmt));
$unread_count = (int)$unread_data['unread'];
mysqli_stmt_close($unread_stmt);

$order_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM business_notifications WHERE business_id = ? AND type = 'order'");
mysqli_stmt_bind_param($order_stmt, 'i', $business_id);
mysqli_stmt_execute($order_stmt);
$order_data = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));
$order_count = (int)$order_data['count'];
mysqli_stmt_close($order_stmt);

$inventory_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM business_notifications WHERE business_id = ? AND type = 'inventory'");
mysqli_stmt_bind_param($inventory_stmt, 'i', $business_id);
mysqli_stmt_execute($inventory_stmt);
$inventory_data = mysqli_fetch_assoc(mysqli_stmt_get_result($inventory_stmt));
$inventory_count = (int)$inventory_data['count'];
mysqli_stmt_close($inventory_stmt);

$review_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM business_notifications WHERE business_id = ? AND type = 'review'");
mysqli_stmt_bind_param($review_stmt, 'i', $business_id);
mysqli_stmt_execute($review_stmt);
$review_data = mysqli_fetch_assoc(mysqli_stmt_get_result($review_stmt));
$review_count = (int)$review_data['count'];
mysqli_stmt_close($review_stmt);

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/business_sidebar.php';
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
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i {
            color: #e67e22;
            background: none;
        }
        .btn-mark-all {
            background: #e67e22;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-mark-all:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #e67e22;
            box-shadow: 0 12px 28px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #e67e22;
            margin-bottom: 0.25rem;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
            /* text-transform: uppercase; */
            letter-spacing: 0.5px;
        }
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .filter-btn {
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            transition: 0.2s;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }
        .alert-success {
            background: #e6f7ec;
            color: #0a5c3e;
            border-left-color: #10b981;
        }
        .notifications-list {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #fffaf5;
        }
        .notification-item.unread {
            background: #fff5eb;
            border-left: 3px solid #e67e22;
        }
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
        .icon-inventory { background: rgba(243,156,18,0.1); color: #f39c12; }
        .icon-review { background: rgba(52,152,219,0.1); color: #3498db; }
        .icon-payment { background: rgba(39,174,96,0.1); color: #27ae60; }
        .icon-system { background: rgba(155,89,182,0.1); color: #9b59b6; }
        .notification-content { flex: 1; }
        .notification-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: #1e293b;
        }
        .notification-message {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .notification-time {
            font-size: 0.7rem;
            color: #94a3b8;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .read-badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.6rem;
            border-radius: 1rem;
            background: #e2e8f0;
            color: #64748b;
        }
        .read-badge.unread {
            background: #e67e22;
            color: white;
        }
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            color: #94a3b8;
            transition: 0.2s;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }
        .action-btn:hover {
            color: #e67e22;
        }
        .action-btn.delete:hover {
            color: #e74c3c;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .notification-item { flex-wrap: wrap; }
            .notification-actions { margin-top: 0.5rem; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <p style="color:#64748b; font-size:0.85rem; margin-top:0.25rem;">Stay updated with your business activities</p>
        </div>
        <?php if ($unread_count > 0): ?>
            <a href="mark-all.php" class="btn-mark-all"><i class="fas fa-check-double"></i> Mark All as Read</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card"><h3><?php echo $total; ?></h3><p><i class="fas fa-bell"></i> Total</p></div>
        <div class="stat-card"><h3><?php echo $unread_count; ?></h3><p><i class="fas fa-envelope"></i> Unread</p></div>
        <div class="stat-card"><h3><?php echo $order_count; ?></h3><p><i class="fas fa-shopping-cart"></i> Orders</p></div>
        <div class="stat-card"><h3><?php echo $inventory_count; ?></h3><p><i class="fas fa-warehouse"></i> Inventory</p></div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <a href="index.php" class="filter-btn <?php echo empty($type_filter) && empty($read_filter) ? 'active' : ''; ?>">All</a>
        <a href="?type=order" class="filter-btn <?php echo $type_filter == 'order' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="?type=inventory" class="filter-btn <?php echo $type_filter == 'inventory' ? 'active' : ''; ?>"><i class="fas fa-warehouse"></i> Inventory</a>
        <a href="?type=review" class="filter-btn <?php echo $type_filter == 'review' ? 'active' : ''; ?>"><i class="fas fa-star"></i> Reviews</a>
        <a href="?type=payment" class="filter-btn <?php echo $type_filter == 'payment' ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Payments</a>
        <a href="?read=unread" class="filter-btn <?php echo $read_filter == 'unread' ? 'active' : ''; ?>"><i class="fas fa-envelope"></i> Unread</a>
        <a href="?read=read" class="filter-btn <?php echo $read_filter == 'read' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> Read</a>
    </div>

    <!-- Notifications List -->
    <div class="notifications-list">
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>You don't have any notifications at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif):
                $icon_class = '';
                $icon_icon = '';
                switch ($notif['type']) {
                    case 'order': $icon_class = 'icon-order'; $icon_icon = 'fa-shopping-cart'; break;
                    case 'inventory': $icon_class = 'icon-inventory'; $icon_icon = 'fa-warehouse'; break;
                    case 'review': $icon_class = 'icon-review'; $icon_icon = 'fa-star'; break;
                    case 'payment': $icon_class = 'icon-payment'; $icon_icon = 'fa-credit-card'; break;
                    default: $icon_class = 'icon-system'; $icon_icon = 'fa-bell';
                }
            ?>
            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                <div class="notification-icon <?php echo $icon_class; ?>">
                    <i class="fas <?php echo $icon_icon; ?>"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                    <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                    <div class="notification-time">
                        <span><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($notif['created_at'])); ?></span>
                        <span class="read-badge <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                            <i class="fas fa-<?php echo $notif['is_read'] ? 'check-circle' : 'circle'; ?>"></i>
                            <?php echo $notif['is_read'] ? 'Read' : 'Unread'; ?>
                        </span>
                    </div>
                </div>
                <div class="notification-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="mark-read.php?id=<?php echo $notif['notification_id']; ?>" class="action-btn" title="Mark as read">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php endif; ?>
                    <a href="delete.php?id=<?php echo $notif['notification_id']; ?>" class="action-btn delete" title="Delete" onclick="return confirm('Delete this notification?')">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>