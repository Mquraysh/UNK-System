<?php
// admin/notifications/index.php - Professional Admin Notifications
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];

// Create notifications table if not exists
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

// Handle mark as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = (int)$_GET['id'];
    mysqli_query($conn, "UPDATE admin_notifications SET is_read = 1 WHERE notification_id = $notif_id AND admin_id = $admin_id");
    $_SESSION['flash_message'] = "Notification marked as read.";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all'])) {
    mysqli_query($conn, "UPDATE admin_notifications SET is_read = 1 WHERE admin_id = $admin_id");
    $_SESSION['flash_message'] = "All notifications marked as read.";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $notif_id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM admin_notifications WHERE notification_id = $notif_id AND admin_id = $admin_id");
    $_SESSION['flash_message'] = "Notification deleted.";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Get filters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$read_filter = isset($_GET['read']) ? $_GET['read'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where = "WHERE admin_id = $admin_id";
if (!empty($type_filter)) {
    $type_esc = mysqli_real_escape_string($conn, $type_filter);
    $where .= " AND type = '$type_esc'";
}
if ($read_filter === 'unread') {
    $where .= " AND is_read = 0";
} elseif ($read_filter === 'read') {
    $where .= " AND is_read = 1";
}
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (title LIKE '%$search_esc%' OR message LIKE '%$search_esc%')";
}

// Get notifications
$sql = "SELECT * FROM admin_notifications $where ORDER BY 
        CASE WHEN is_read = 0 THEN 0 ELSE 1 END, 
        created_at DESC 
        LIMIT 100";
$result = mysqli_query($conn, $sql);
$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

// Get statistics
$total_sql = "SELECT COUNT(*) as total FROM admin_notifications WHERE admin_id = $admin_id";
$total_result = mysqli_query($conn, $total_sql);
$total = mysqli_fetch_assoc($total_result)['total'];

$unread_sql = "SELECT COUNT(*) as unread FROM admin_notifications WHERE admin_id = $admin_id AND is_read = 0";
$unread_result = mysqli_query($conn, $unread_sql);
$unread_count = mysqli_fetch_assoc($unread_result)['unread'];

// Count by type
$type_counts = [];
$type_sql = "SELECT type, COUNT(*) as count FROM admin_notifications WHERE admin_id = $admin_id GROUP BY type";
$type_result = mysqli_query($conn, $type_sql);
while ($row = mysqli_fetch_assoc($type_result)) {
    $type_counts[$row['type']] = $row['count'];
}

// Flash messages
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Notifications | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 0.9rem; }
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header h1 .badge {
            background: #ef4444;
            color: white;
            font-size: 0.65rem;
            padding: 0.1rem 0.6rem;
            border-radius: 2rem;
            font-weight: 700;
        }
        .page-header p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        .header-actions {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); }
        .btn-secondary { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-back { background: #64748b; color: white; }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        
        /* Alert */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 4px solid;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .stat-card .number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
        }
        .stat-card .label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .icon {
            float: right;
            font-size: 1.5rem;
            color: #e67e22;
            opacity: 0.7;
        }
        .stat-card .sub-text {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        
        /* Filters */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 0.9rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-btn {
            padding: 0.25rem 0.8rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: 0.2s;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .search-input {
            padding: 0.35rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            font-size: 0.8rem;
            width: 200px;
        }
        .search-input:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        /* Notifications List */
        .notifications-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .notifications-header {
            padding: 0.8rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: #64748b;
        }
        .notifications-header strong { color: #0f172a; }
        
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            transition: 0.2s;
        }
        .notif-item:hover {
            background: #fafcff;
        }
        .notif-item.unread {
            background: #fff8f0;
            border-left: 3px solid #e67e22;
        }
        .notif-item.unread:hover {
            background: #fff5eb;
        }
        .notif-item:last-child { border-bottom: none; }
        
        .notif-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-icon.order { background: #dbeafe; color: #2563eb; }
        .notif-icon.delivery { background: #d1fae5; color: #059669; }
        .notif-icon.payment { background: #fef3c7; color: #d97706; }
        .notif-icon.product { background: #ede9fe; color: #5b21b6; }
        .notif-icon.user { background: #fce7f3; color: #be185d; }
        .notif-icon.system { background: #e2e8f0; color: #64748b; }
        .notif-icon.alert { background: #fee2e2; color: #dc2626; }
        
        .notif-content { flex: 1; min-width: 0; }
        .notif-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }
        .notif-message {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 0.3rem;
            word-wrap: break-word;
        }
        .notif-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.65rem;
            color: #94a3b8;
            flex-wrap: wrap;
            align-items: center;
        }
        .notif-meta .type-badge {
            padding: 0.1rem 0.5rem;
            border-radius: 1rem;
            font-weight: 600;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .type-badge.order { background: #dbeafe; color: #2563eb; }
        .type-badge.delivery { background: #d1fae5; color: #059669; }
        .type-badge.payment { background: #fef3c7; color: #d97706; }
        .type-badge.product { background: #ede9fe; color: #5b21b6; }
        .type-badge.user { background: #fce7f3; color: #be185d; }
        .type-badge.system { background: #e2e8f0; color: #64748b; }
        .type-badge.alert { background: #fee2e2; color: #dc2626; }
        
        .notif-actions {
            display: flex;
            gap: 0.3rem;
            flex-shrink: 0;
            align-items: center;
        }
        .notif-actions .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            padding: 0.2rem 0.4rem;
            border-radius: 0.25rem;
            transition: 0.2s;
            font-size: 0.8rem;
        }
        .notif-actions .action-btn:hover {
            color: #e67e22;
            background: #f1f5f9;
        }
        .notif-actions .action-btn.delete:hover {
            color: #ef4444;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3.5rem 1.5rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .empty-state p {
            font-size: 0.85rem;
        }
        
        /* Load More */
        .load-more {
            text-align: center;
            padding: 1rem;
            background: #fafcff;
            border-top: 1px solid #e2e8f0;
        }
        .load-more a {
            color: #e67e22;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .load-more a:hover { text-decoration: underline; }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { justify-content: center; }
            .search-input { width: 100%; }
            .notif-item { flex-wrap: wrap; }
            .notif-actions { width: 100%; justify-content: flex-end; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .btn { flex: 1; justify-content: center; }
            .notifications-header { flex-direction: column; text-align: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .admin-content { padding: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?> new</span>
                <?php endif; ?>
            </h1>
            <p>Stay updated with system activities and alerts</p>
        </div>
        <div class="header-actions">
            <?php if ($unread_count > 0): ?>
                <a href="?mark_all=1" class="btn btn-primary" onclick="return confirm('Mark all notifications as read?')">
                    <i class="fas fa-check-double"></i> Mark All Read
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-back"><i class="fas fa-undo-alt"></i> Refresh</a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($flash_message): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="icon"><i class="fas fa-bell"></i></div>
            <div class="number"><?php echo $total; ?></div>
            <div class="label">Total Notifications</div>
            <div class="sub-text">All time</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-envelope"></i></div>
            <div class="number"><?php echo $unread_count; ?></div>
            <div class="label">Unread</div>
            <div class="sub-text">Needs attention</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="number"><?php echo $total - $unread_count; ?></div>
            <div class="label">Read</div>
            <div class="sub-text">Already viewed</div>
        </div>
        <div class="stat-card">
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="number"><?php echo $type_counts['alert'] ?? 0; ?></div>
            <div class="label">Alerts</div>
            <div class="sub-text">Requires action</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-filter"></i> Type:</span>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => ''])); ?>" class="filter-btn <?php echo empty($type_filter) ? 'active' : ''; ?>">All</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'order'])); ?>" class="filter-btn <?php echo $type_filter === 'order' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'delivery'])); ?>" class="filter-btn <?php echo $type_filter === 'delivery' ? 'active' : ''; ?>"><i class="fas fa-truck"></i> Delivery</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'payment'])); ?>" class="filter-btn <?php echo $type_filter === 'payment' ? 'active' : ''; ?>"><i class="fas fa-credit-card"></i> Payment</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'product'])); ?>" class="filter-btn <?php echo $type_filter === 'product' ? 'active' : ''; ?>"><i class="fas fa-box"></i> Products</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'user'])); ?>" class="filter-btn <?php echo $type_filter === 'user' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Users</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'alert'])); ?>" class="filter-btn <?php echo $type_filter === 'alert' ? 'active' : ''; ?>"><i class="fas fa-exclamation-triangle"></i> Alerts</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['type' => 'system'])); ?>" class="filter-btn <?php echo $type_filter === 'system' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> System</a>
        </div>
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-check-circle"></i> Status:</span>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['read' => ''])); ?>" class="filter-btn <?php echo empty($read_filter) ? 'active' : ''; ?>">All</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['read' => 'unread'])); ?>" class="filter-btn <?php echo $read_filter === 'unread' ? 'active' : ''; ?>">Unread</a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['read' => 'read'])); ?>" class="filter-btn <?php echo $read_filter === 'read' ? 'active' : ''; ?>">Read</a>
        </div>
        <div class="filter-group" style="flex:1; min-width:150px;">
            <form method="GET" style="display:flex; gap:0.5rem; width:100%;">
                <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
                <input type="hidden" name="read" value="<?php echo $read_filter; ?>">
                <input type="text" name="search" class="search-input" placeholder="Search notifications..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="filter-btn" style="background:#e67e22; color:white; border-color:#e67e22;">Search</button>
                <?php if ($search): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['search' => ''])); ?>" class="filter-btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="notifications-container">
        <div class="notifications-header">
            <span><strong><?php echo count($notifications); ?></strong> notifications found</span>
            <span>
                <?php if ($unread_count > 0): ?>
                    <i class="fas fa-circle" style="color:#e67e22; font-size:0.5rem;"></i>
                    <?php echo $unread_count; ?> unread
                <?php endif; ?>
            </span>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications found</h3>
                <p>You're all caught up! Check back later for new updates.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): 
                $icon_map = [
                    'order' => 'fa-shopping-cart',
                    'delivery' => 'fa-truck',
                    'payment' => 'fa-credit-card',
                    'product' => 'fa-box',
                    'user' => 'fa-user',
                    'system' => 'fa-cog',
                    'alert' => 'fa-exclamation-triangle'
                ];
                $icon = $icon_map[$notif['type']] ?? 'fa-bell';
                $time_ago = '';
                $time_diff = time() - strtotime($notif['created_at']);
                if ($time_diff < 60) {
                    $time_ago = $time_diff . ' seconds ago';
                } elseif ($time_diff < 3600) {
                    $time_ago = floor($time_diff / 60) . ' minutes ago';
                } elseif ($time_diff < 86400) {
                    $time_ago = floor($time_diff / 3600) . ' hours ago';
                } elseif ($time_diff < 604800) {
                    $time_ago = floor($time_diff / 86400) . ' days ago';
                } else {
                    $time_ago = date('M d, Y', strtotime($notif['created_at']));
                }
            ?>
            <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                <div class="notif-icon <?php echo $notif['type']; ?>">
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">
                        <?php echo htmlspecialchars($notif['title']); ?>
                        <?php if (!$notif['is_read']): ?>
                            <span style="font-size:0.5rem; background:#e67e22; color:white; padding:0.1rem 0.4rem; border-radius:1rem; margin-left:0.4rem;">New</span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                    <div class="notif-meta">
                        <span class="type-badge <?php echo $notif['type']; ?>"><?php echo ucfirst($notif['type']); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo $time_ago; ?></span>
                        <?php if ($notif['is_read']): ?>
                            <span style="color:#10b981;"><i class="fas fa-check-circle"></i> Read</span>
                        <?php else: ?>
                            <span style="color:#e67e22;"><i class="fas fa-circle"></i> Unread</span>
                        <?php endif; ?>
                        <?php if (!empty($notif['link'])): ?>
                            <a href="<?php echo $notif['link']; ?>" style="color:#e67e22; font-weight:600;">
                                <i class="fas fa-arrow-right"></i> View
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="?mark_read=1&id=<?php echo $notif['notification_id']; ?>" class="action-btn" title="Mark as read">
                            <i class="fas fa-check"></i>
                        </a>
                    <?php endif; ?>
                    <a href="?delete=1&id=<?php echo $notif['notification_id']; ?>" class="action-btn delete" title="Delete" onclick="return confirm('Delete this notification?')">
                        <i class="fas fa-trash-alt"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (count($notifications) >= 50): ?>
            <div class="load-more">
                <a href="#"><i class="fas fa-chevron-down"></i> Load more</a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>