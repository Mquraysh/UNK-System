<?php
// customer/includes/customer_sidebar.php - PROFESSIONAL CUSTOMER SIDEBAR
// WITH PROFILE IMAGE, WISHLIST COUNT, CART COUNT, NOTIFICATION COUNT

// Get current page info
$current_file = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Get customer data from session or database
$user_id = $_SESSION['user_id'] ?? 0;
$customer_id = $_SESSION['customer_id'] ?? 0;
$customer_name = $_SESSION['full_name'] ?? 'Customer';
$customer_email = $_SESSION['email'] ?? 'customer@example.com';

// Get profile image from database
$profile_image = null;
if ($customer_id > 0) {
    $img_sql = "SELECT profile_image FROM customers WHERE customer_id = ?";
    $stmt = mysqli_prepare($conn, $img_sql);
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $profile_image = $row['profile_image'];
    }
    mysqli_stmt_close($stmt);
}

// Get initials from name
$initials = '';
if (!empty($customer_name) && $customer_name != 'Customer') {
    $name_parts = explode(' ', $customer_name);
    foreach ($name_parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }
}
if (empty($initials)) {
    $initials = 'U';
}

// --- COUNTS ---
$cart_count = 0;
$wishlist_count = 0;
$unread_notifications = 0;

if ($customer_id > 0) {
    // Cart count
    $cart_sql = "SELECT SUM(quantity) as total FROM cart WHERE customer_id = ?";
    $stmt = mysqli_prepare($conn, $cart_sql);
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $cart_count = (int)($row['total'] ?? 0);
    }
    mysqli_stmt_close($stmt);

    // Wishlist count
    $wish_sql = "SELECT COUNT(*) as total FROM wishlist WHERE customer_id = ?";
    $stmt = mysqli_prepare($conn, $wish_sql);
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $wishlist_count = (int)($row['total'] ?? 0);
    }
    mysqli_stmt_close($stmt);

    // Unread notifications count
    $notif_sql = "SELECT COUNT(*) as total FROM customer_notifications WHERE customer_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $notif_sql);
    mysqli_stmt_bind_param($stmt, "i", $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $unread_notifications = (int)($row['total'] ?? 0);
    }
    mysqli_stmt_close($stmt);
}

// Active menu states
$active_dashboard   = ($current_file == 'dashboard.php') ? 'active' : '';
$active_shop        = ($current_dir == 'products') ? 'active' : '';
$active_cart        = ($current_dir == 'cart') ? 'active' : '';
$active_orders      = ($current_dir == 'orders') ? 'active' : '';
$active_wishlist    = ($current_dir == 'wishlist') ? 'active' : '';
$active_reviews     = ($current_dir == 'reviews') ? 'active' : '';
$active_notifications = ($current_dir == 'notifications') ? 'active' : '';
$active_compare     = ($current_file == 'compare.php' && $current_dir == 'products') ? 'active' : '';
$active_support     = ($current_dir == 'support') ? 'active' : '';
$active_tickets     = ($current_file == 'my_tickets.php' && $current_dir == 'support') ? 'active' : '';
$active_track       = ($current_file == 'track.php' && $current_dir == 'orders') ? 'active' : '';
$active_settings    = ($current_dir == 'settings') ? 'active' : '';

// Determine avatar source
$avatar_url = '';
if (!empty($profile_image) && file_exists("../../" . $profile_image)) {
    $avatar_url = "../../" . $profile_image;
} else {
    $avatar_url = ''; // Will use initials fallback
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f7f9fc; color: #1e293b; }
        
        /* Sidebar Container */
        .customer-sidebar {
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
        
        .customer-sidebar::-webkit-scrollbar { width: 4px; }
        .customer-sidebar::-webkit-scrollbar-track { background: #1e293b; }
        .customer-sidebar::-webkit-scrollbar-thumb { background: #e67e22; border-radius: 4px; }
        
        /* Profile Section */
        .sidebar-profile {
            padding: 30px 20px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(0,0,0,0.15);
        }
        
        /* Profile Image / Avatar */
        .profile-avatar {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(255,255,255,0.3);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            background: linear-gradient(135deg, #e67e22, #f39c12);
            flex-shrink: 0;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(230,126,34,0.4);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-initials {
            font-size: 32px;
            font-weight: 700;
            color: white;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Profile Image with border */
        .profile-avatar.has-image {
            background: transparent;
            border-color: rgba(255,255,255,0.4);
        }
        
        .customer-name {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }
        
        .customer-email {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 10px;
            word-break: break-all;
        }
        
        /* Navigation Menu */
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
        
        /* Badge */
        .nav-badge {
            background: #e67e22;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 40px;
            min-width: 22px;
            text-align: center;
        }
        
        /* Divider */
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
        
        /* Toggle Button */
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
        
        /* Mobile Overlay */
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
        
        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar-toggle {
                display: flex;
            }
            .customer-sidebar {
                transform: translateX(-100%);
            }
            .customer-sidebar.open {
                transform: translateX(0);
            }
            .mobile-overlay.active {
                display: block;
            }
        }
        
        @media (min-width: 1025px) {
            .sidebar-toggle {
                display: none;
            }
            .customer-sidebar {
                transform: translateX(0);
            }
        }
        
        @media (max-width: 480px) {
            .profile-avatar {
                width: 65px;
                height: 65px;
            }
            .avatar-initials {
                font-size: 26px;
            }
            .customer-name {
                font-size: 14px;
            }
            .customer-email {
                font-size: 10px;
            }
            .nav-item {
                padding: 10px 14px;
                font-size: 13px;
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
<aside class="customer-sidebar" id="customerSidebar">
    
    <!-- Profile Section with Image or Initials -->
    <div class="sidebar-profile">
        <div class="profile-avatar <?php echo !empty($avatar_url) ? 'has-image' : ''; ?>" id="profileAvatar">
            <?php if (!empty($avatar_url)): ?>
                <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                     alt="<?php echo htmlspecialchars($customer_name); ?>" 
                     id="profileImage"
                     onerror="handleImageError()">
            <?php else: ?>
                <span class="avatar-initials" id="avatarInitials"><?php echo htmlspecialchars($initials); ?></span>
            <?php endif; ?>
        </div>
        <div class="customer-name">
            <?php echo htmlspecialchars(substr($customer_name, 0, 22)); ?>
        </div>
        <div class="customer-email">
            <?php echo htmlspecialchars(substr($customer_email, 0, 22)); ?>
        </div>
    </div>
    
    <!-- Navigation Menu -->
    <div class="sidebar-nav">
        
        <!-- Shopping Section -->
        <div class="nav-title">Shopping</div>
        
        <a href="../das/dashboard.php" class="nav-item <?php echo $active_dashboard; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="../products/index.php" class="nav-item <?php echo $active_shop; ?>">
            <i class="fas fa-store"></i>
            <span>Shop Now</span>
        </a>
        
        <a href="../cart/index.php" class="nav-item <?php echo $active_cart; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>My Cart</span>
            <?php if($cart_count > 0): ?>
                <span class="nav-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../orders/index.php" class="nav-item <?php echo $active_orders; ?>">
            <i class="fas fa-shopping-bag"></i>
            <span>My Orders</span>
        </a>
        
        <a href="../wishlist/index.php" class="nav-item <?php echo $active_wishlist; ?>">
            <i class="fas fa-heart"></i>
            <span>Wishlist</span>
            <?php if($wishlist_count > 0): ?>
                <span class="nav-badge"><?php echo $wishlist_count; ?></span>
            <?php endif; ?>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Reviews & Notifications -->
        <div class="nav-title">Activity</div>
        
        <a href="../reviews/index.php" class="nav-item <?php echo $active_reviews; ?>">
            <i class="fas fa-star"></i>
            <span>My Reviews</span>
        </a>
        
        <a href="../notifications/index.php" class="nav-item <?php echo $active_notifications; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <?php if($unread_notifications > 0): ?>
                <span class="nav-badge"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="../products/compare.php" class="nav-item <?php echo $active_compare; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Compare Products</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Support Section -->
        <div class="nav-title">Support</div>
        
        <a href="../support/index.php" class="nav-item <?php echo $active_support; ?>">
            <i class="fas fa-headset"></i>
            <span>Help & Support</span>
        </a>
        
        <a href="../support/my_tickets.php" class="nav-item <?php echo $active_tickets; ?>">
            <i class="fas fa-ticket-alt"></i>
            <span>My Tickets</span>
        </a>
        
        <a href="../orders/track.php" class="nav-item <?php echo $active_track; ?>">
            <i class="fas fa-map-marker-alt"></i>
            <span>Track Order</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Account Settings -->
        <div class="nav-title">Account</div>
        
        <a href="../settings/index.php" class="nav-item <?php echo $active_settings; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <!-- Logout -->
        <a href="../../logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
        
    </div>
</aside>

<script>
// ============================================================
// FIXED: Handle image load error properly
// ============================================================
function handleImageError() {
    var img = document.getElementById('profileImage');
    var avatar = document.getElementById('profileAvatar');
    var initials = '<?php echo htmlspecialchars($initials); ?>';
    
    if (img) {
        // Remove the image
        img.style.display = 'none';
        // Remove has-image class
        avatar.classList.remove('has-image');
        // Add initials
        var span = document.createElement('span');
        span.className = 'avatar-initials';
        span.id = 'avatarInitials';
        span.textContent = initials;
        avatar.appendChild(span);
    }
}

// ============================================================
// Sidebar toggle functions
// ============================================================
function toggleSidebar() {
    var sidebar = document.getElementById('customerSidebar');
    var overlay = document.getElementById('mobileOverlay');
    var isMobile = window.innerWidth <= 1024;
    
    if (isMobile) {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
    }
}

function closeSidebar() {
    var sidebar = document.getElementById('customerSidebar');
    var overlay = document.getElementById('mobileOverlay');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// DOM Ready
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // On desktop, ensure sidebar is not hidden
    if (window.innerWidth > 1024) {
        document.getElementById('customerSidebar').classList.remove('open');
        document.body.style.overflow = '';
    }
    
    // Handle resize
    window.addEventListener('resize', function() {
        var sidebar = document.getElementById('customerSidebar');
        if (window.innerWidth <= 1024) {
            sidebar.classList.remove('open');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('open');
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