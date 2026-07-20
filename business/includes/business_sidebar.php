<?php
// business/includes/business_sidebar.php 
$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get business data for profile info
$business_id = isset($_SESSION['business_id']) ? $_SESSION['business_id'] : 0;
$business_name = isset($_SESSION['business_name']) ? $_SESSION['business_name'] : 'My Business';
$business_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'business@example.com';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// Get business verification and logo
$is_verified = 0;
$user_status = 'active';
$business_logo = '';

if ($business_id > 0) {
    $sql = "SELECT b.is_verified, b.logo_url, u.status 
            FROM businesses b 
            JOIN users u ON b.user_id = u.user_id 
            WHERE b.business_id = '$business_id'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $is_verified = $row['is_verified'];
        $user_status = $row['status'];
        $business_logo = $row['logo_url'];
    }
}

// Status display
if ($user_status == 'suspended') {
    $status_text = 'Suspended';
    $status_color = '#dc2626';
    $status_bg = '#fee2e2';
    $status_icon = 'fa-ban';
} elseif ($is_verified == 1) {
    $status_text = 'Verified';
    $status_color = '#10b981';
    $status_bg = '#d1fae5';
    $status_icon = 'fa-check-circle';
} else {
    $status_text = 'Pending';
    $status_color = '#f59e0b';
    $status_bg = '#fef3c7';
    $status_icon = 'fa-clock';
}

// Get notification counts
$pending_orders = 0;
$low_stock = 0;
$pending_deliveries = 0;
$unread_notifications = 0;

if ($business_id > 0) {
    $order_sql = "SELECT COUNT(*) as count FROM orders WHERE business_id = '$business_id' AND status = 'pending'";
    $order_result = mysqli_query($conn, $order_sql);
    if ($order_result) $pending_orders = mysqli_fetch_assoc($order_result)['count'];
    
    $stock_sql = "SELECT COUNT(*) as count FROM products WHERE business_id = '$business_id' AND quantity_in_stock < 10 AND quantity_in_stock > 0";
    $stock_result = mysqli_query($conn, $stock_sql);
    if ($stock_result) $low_stock = mysqli_fetch_assoc($stock_result)['count'];
    
    $delivery_sql = "SELECT COUNT(*) as count FROM orders WHERE business_id = '$business_id' AND status IN ('confirmed', 'processing')";
    $delivery_res = mysqli_query($conn, $delivery_sql);
    if ($delivery_res) $pending_deliveries = mysqli_fetch_assoc($delivery_res)['count'];
    
    $notif_sql = "SELECT COUNT(*) as count FROM business_notifications WHERE business_id = '$business_id' AND is_read = 0";
    $notif_res = mysqli_query($conn, $notif_sql);
    if ($notif_res) $unread_notifications = mysqli_fetch_assoc($notif_res)['count'];
}

// Active menu detection (added inventory)
$active_dashboard   = ($current_file == 'dashboard.php') ? 'active' : '';
$active_products    = ($current_dir == 'products') ? 'active' : '';
$active_inventory   = ($current_dir == 'inventory') ? 'active' : '';
$active_orders      = ($current_dir == 'orders') ? 'active' : '';
$active_customers   = ($current_dir == 'customers') ? 'active' : '';
$active_reviews     = ($current_dir == 'reviews') ? 'active' : '';
$active_reports     = ($current_dir == 'reports') ? 'active' : '';
$active_support     = ($current_dir == 'support') ? 'active' : '';
$active_notifications = ($current_dir == 'notifications') ? 'active' : '';
$active_deliveries  = ($current_dir == 'deliveries') ? 'active' : '';
$active_settings    = ($current_dir == 'settings') ? 'active' : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* (all CSS remains exactly the same as provided) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-sidebar {
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
        .business-sidebar::-webkit-scrollbar { width: 4px; }
        .business-sidebar::-webkit-scrollbar-track { background: #1e293b; }
        .business-sidebar::-webkit-scrollbar-thumb { background: #e67e22; border-radius: 4px; }
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
        .business-name {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }
        .business-email {
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
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
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
        .nav-badge-warning {
            background: #f39c12;
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
        .business-sidebar.collapsed {
            width: 75px;
        }
        .business-sidebar.collapsed .sidebar-profile {
            padding: 20px 10px;
        }
        .business-sidebar.collapsed .profile-avatar {
            width: 45px;
            height: 45px;
        }
        .business-sidebar.collapsed .profile-avatar i {
            font-size: 20px;
        }
        .business-sidebar.collapsed .business-name,
        .business-sidebar.collapsed .business-email,
        .business-sidebar.collapsed .status-badge,
        .business-sidebar.collapsed .nav-item span,
        .business-sidebar.collapsed .nav-title {
            display: none;
        }
        .business-sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 12px;
        }
        .business-sidebar.collapsed .nav-item i {
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
            .sidebar-toggle { display: flex; }
            .business-sidebar { transform: translateX(-100%); }
            .business-sidebar.open { transform: translateX(0); }
            .dashboard-main { margin-left: 0; padding-top: 70px; }
            .mobile-overlay.active { display: block; }
        }
        @media (min-width: 1025px) {
            .sidebar-toggle { display: none; }
            .business-sidebar { transform: translateX(0); }
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
<aside class="business-sidebar" id="businessSidebar">
    
    <!-- Profile Section -->
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if(!empty($business_logo) && file_exists("../../" . $business_logo)): ?>
                <img src="../../<?php echo $business_logo; ?>" alt="Logo">
            <?php elseif(!empty($business_logo) && file_exists($business_logo)): ?>
                <img src="<?php echo $business_logo; ?>" alt="Logo">
            <?php else: ?>
                <i class="fas fa-store"></i>
            <?php endif; ?>
        </div>
        <div class="business-name">
            <?php echo htmlspecialchars(substr($business_name, 0, 22)); ?>
            <?php if($is_verified == 1): ?>
                <i class="fas fa-check-circle" style="color: #10b981; font-size: 12px;"></i>
            <?php endif; ?>
        </div>
        <div class="business-email">
            <?php echo htmlspecialchars(substr($business_email, 0, 22)); ?>
        </div>
        <div class="status-badge" style="background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>;">
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
        
        <!-- Store Management -->
        <div class="nav-title">Store Management</div>
        
        <!-- Products -->
        <a href="../products/index.php" class="nav-item <?php echo $active_products; ?>">
            <i class="fas fa-box"></i>
            <span>Products</span>
            <?php if($low_stock > 0): ?>
                <span class="nav-badge nav-badge-warning"><?php echo $low_stock; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Inventory (NEW) -->
        <a href="../inventory/index.php" class="nav-item <?php echo $active_inventory; ?>">
            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>
            <?php if($low_stock > 0): ?>
                <span class="nav-badge nav-badge-warning"><?php echo $low_stock; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Orders -->
        <a href="../orders/index.php" class="nav-item <?php echo $active_orders; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Orders</span>
            <?php if($pending_orders > 0): ?>
                <span class="nav-badge"><?php echo $pending_orders; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Deliveries -->
        <a href="../deliveries/index.php" class="nav-item <?php echo $active_deliveries; ?>">
            <i class="fas fa-truck"></i>
            <span>Deliveries</span>
            <?php if($pending_deliveries > 0): ?>
                <span class="nav-badge"><?php echo $pending_deliveries; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- Customers -->
        <a href="../customers/index.php" class="nav-item <?php echo $active_customers; ?>">
            <i class="fas fa-users"></i>
            <span>Customers</span>
        </a>
        
        <!-- Reviews -->
        <a href="../reviews/index.php" class="nav-item <?php echo $active_reviews; ?>">
            <i class="fas fa-star"></i>
            <span>Reviews</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Analytics & Reports -->
        <div class="nav-title">Analytics & Reports</div>
        
        <!-- Reports -->
        <a href="../reports/index.php" class="nav-item <?php echo $active_reports; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Support & Notifications -->
        <div class="nav-title">Support</div>
        
        <!-- Support Tickets -->
        <a href="../support/index.php" class="nav-item <?php echo $active_support; ?>">
            <i class="fas fa-headset"></i>
            <span>Support Tickets</span>
        </a>
        
        <!-- Notifications -->
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
        
        <!-- Settings -->
        <a href="../settings/index.php" class="nav-item <?php echo $active_settings; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        
        <!-- Logout -->
        <a href="../../logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        
    </div>
</aside>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('businessSidebar');
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
    var sidebar = document.getElementById('businessSidebar');
    var overlay = document.getElementById('mobileOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth > 1024 && localStorage.getItem('sidebarCollapsed') === 'true') {
        document.getElementById('businessSidebar').classList.add('collapsed');
    }
    
    window.addEventListener('resize', function() {
        var sidebar = document.getElementById('businessSidebar');
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