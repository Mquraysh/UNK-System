<?php
// admin/includes/admin_sidebar.php - PROFESSIONAL ADMIN SIDEBAR (MATCHES BUSINESS STYLE)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Ensure database connection is available
if (!isset($conn)) {
    require_once dirname(__DIR__, 2) . '/config/database.php';
}

// Admin user info
$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_email = $_SESSION['email'] ?? 'admin@unksystem.com';

// Count pending items (using prepared statements)
$pending_businesses = 0;
$pending_orders = 0;
$pending_deliveries = 0;
$unread_notifications = 0;

if (isset($conn)) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM businesses WHERE is_verified = 0");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $pending_businesses);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $pending_orders);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM deliveries WHERE status IN ('assigned', 'picked_up', 'in_transit')");
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $pending_deliveries);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Admin notifications (optional – create table if needed)
    $admin_id = $_SESSION['user_id'] ?? 0;
    if ($admin_id) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM admin_notifications WHERE admin_id = ? AND is_read = 0");
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $unread_notifications);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Active menu detection
$active_dashboard    = ($current_file == 'dashboard.php' || ($current_dir == 'das' && $current_file == 'dashboard.php')) ? 'active' : '';
$active_users        = ($current_dir == 'users') ? 'active' : '';
$active_businesses   = ($current_dir == 'businesses') ? 'active' : '';
$active_products     = ($current_dir == 'products') ? 'active' : '';
$active_orders       = ($current_dir == 'orders') ? 'active' : '';
$active_deliveries   = ($current_dir == 'deliveries') ? 'active' : '';
$active_agents       = ($current_dir == 'delivery_agent') ? 'active' : '';
$active_categories   = ($current_dir == 'categories') ? 'active' : '';
$active_reports      = ($current_dir == 'reports') ? 'active' : '';
$active_notifications= ($current_dir == 'notifications') ? 'active' : '';
$active_settings     = ($current_dir == 'settings') ? 'active' : '';

// Admin status (always active)
$status_text = 'Administrator';
$status_color = '#10b981';
$status_bg = '#d1fae5';
$status_icon = 'fa-crown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Same styling as business sidebar – just renamed class */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }
        
        .admin-sidebar::-webkit-scrollbar { width: 4px; }
        .admin-sidebar::-webkit-scrollbar-track { background: #1e293b; }
        .admin-sidebar::-webkit-scrollbar-thumb { background: #e67e22; border-radius: 4px; }
        
        .sidebar-profile {
            padding: 30px 20px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.15);
        }
        
        .profile-avatar {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #e67e22, #f39c12);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.25);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar i { font-size: 32px; color: white; }
        
        .admin-name {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }
        
        .admin-email {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 10px;
            font-weight: 600;
            background: <?php echo $status_bg; ?>;
            color: <?php echo $status_color; ?>;
            border: 1px solid <?php echo $status_color; ?>20;
        }
        
        .sidebar-nav {
            padding: 20px 16px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            margin: 2px 0;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.2s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .nav-item i {
            width: 22px;
            font-size: 16px;
            text-align: center;
        }
        
        .nav-item span {
            flex: 1;
        }
        
        .nav-item:hover {
            background: rgba(230,126,34,0.12);
            color: #e67e22;
        }
        
        .nav-item.active {
            background: linear-gradient(105deg, rgba(230,126,34,0.15), rgba(230,126,34,0.05));
            color: #e67e22;
            border-left: 3px solid #e67e22;
        }
        
        .nav-badge {
            background: #e67e22;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 40px;
        }
        
        .nav-divider {
            height: 1px;
            background: rgba(255,255,255,0.08);
            margin: 16px 0;
        }
        
        .nav-title {
            padding: 8px 16px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
        }
        
        .admin-sidebar.collapsed {
            width: 75px;
        }
        
        .admin-sidebar.collapsed .sidebar-profile {
            padding: 20px 10px;
        }
        
        .admin-sidebar.collapsed .profile-avatar {
            width: 45px;
            height: 45px;
        }
        
        .admin-sidebar.collapsed .profile-avatar i {
            font-size: 20px;
        }
        
        .admin-sidebar.collapsed .admin-name,
        .admin-sidebar.collapsed .admin-email,
        .admin-sidebar.collapsed .status-badge,
        .admin-sidebar.collapsed .nav-item span,
        .admin-sidebar.collapsed .nav-title {
            display: none;
        }
        
        .admin-sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 12px;
        }
        
        .admin-sidebar.collapsed .nav-item i {
            width: auto;
            font-size: 18px;
        }
        
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            background: #e67e22;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            border: none;
            transition: 0.2s;
        }
        
        .sidebar-toggle:hover {
            background: #d35400;
            transform: scale(1.02);
        }
        
        .dashboard-main {
            margin-left: 280px;
            transition: margin-left 0.3s;
            min-height: 100vh;
            background: #f1f5f9;
        }
        
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        
        @media (max-width: 1024px) {
            .sidebar-toggle {
                display: flex;
            }
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .dashboard-main {
                margin-left: 0;
                padding-top: 70px;
            }
            .mobile-overlay.active {
                display: block;
            }
        }
        
        @media (min-width: 1025px) {
            .sidebar-toggle {
                display: none;
            }
            .admin-sidebar {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>

<!-- Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    
    <!-- Profile Section -->
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if(!empty($admin_avatar) && file_exists("../../" . $admin_avatar)): ?>
                <img src="../../<?php echo $admin_avatar; ?>" alt="Avatar">
            <?php else: ?>
                <i class="fas fa-user-shield"></i>
            <?php endif; ?>
        </div>
        <div class="admin-name">
            <?php echo htmlspecialchars(substr($admin_name, 0, 22)); ?>
        </div>
        <div class="admin-email">
            <?php echo htmlspecialchars(substr($admin_email, 0, 22)); ?>
        </div>
        <div class="status-badge">
            <i class="fas <?php echo $status_icon; ?>"></i>
            <?php echo $status_text; ?>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <div class="sidebar-nav">
        
        <!-- Dashboard -->
        <a href="../das/dashboard.php" class="nav-item <?php echo $active_dashboard; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Management -->
        <div class="nav-title">Management</div>
        
        <a href="../users/index.php" class="nav-item <?php echo $active_users; ?>">
            <i class="fas fa-users"></i>
            <span>Users</span>
        </a>
        
        <a href="../businesses/index.php" class="nav-item <?php echo $active_businesses; ?>">
            <i class="fas fa-store"></i>
            <span>Businesses</span>
            <?php if($pending_businesses > 0): ?>
                <span class="nav-badge"><?php echo $pending_businesses; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../products/index.php" class="nav-item <?php echo $active_products; ?>">
            <i class="fas fa-box"></i>
            <span>Products</span>
        </a>
        
        <a href="../orders/index.php" class="nav-item <?php echo $active_orders; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
            <?php if($pending_orders > 0): ?>
                <span class="nav-badge"><?php echo $pending_orders; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../deliveries/index.php" class="nav-item <?php echo $active_deliveries; ?>">
            <i class="fas fa-truck"></i>
            <span>Deliveries</span>
            <?php if($pending_deliveries > 0): ?>
                <span class="nav-badge"><?php echo $pending_deliveries; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../delivery_agent/agents.php" class="nav-item <?php echo $active_agents; ?>">
            <i class="fas fa-user-check"></i>
            <span>Delivery Agents</span>
        </a>
        
        <a href="../categories/index.php" class="nav-item <?php echo $active_categories; ?>">
            <i class="fas fa-tags"></i>
            <span>Categories</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Reports & Notifications -->
        <div class="nav-title">Insights</div>
        
        <a href="../reports/index.php" class="nav-item <?php echo $active_reports; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
        
        <a href="../notifications/index.php" class="nav-item <?php echo $active_notifications; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <?php if($unread_notifications > 0): ?>
                <span class="nav-badge"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Account Settings -->
        <div class="nav-title">Account</div>
        
        <a href="../settings/index.php" class="nav-item <?php echo $active_settings; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        
        <a href="../../logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        
    </div>
</aside>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('mobileOverlay');
    var isMobile = window.innerWidth <= 1024;
    
    if (isMobile) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    } else {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? 'true' : 'false');
    }
}

function closeSidebar() {
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('mobileOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    // On desktop, restore collapsed state
    if (window.innerWidth > 1024 && localStorage.getItem('sidebarCollapsed') === 'true') {
        document.getElementById('adminSidebar').classList.add('collapsed');
    }
    
    // Handle resize: when switching between mobile and desktop
    window.addEventListener('resize', function() {
        var sidebar = document.getElementById('adminSidebar');
        if (window.innerWidth <= 1024) {
            sidebar.classList.remove('collapsed');
            sidebar.classList.remove('open');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('open');
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.classList.add('collapsed');
            } else {
                sidebar.classList.remove('collapsed');
            }
        }
    });
    
    // Close sidebar when a link is clicked (on mobile)
    var navLinks = document.querySelectorAll('.nav-item');
    if (window.innerWidth <= 1024) {
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                setTimeout(closeSidebar, 150);
            });
        });
    }
});
</script>

</body>
</html>