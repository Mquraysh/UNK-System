<?php
// index.php 
require_once 'config/database.php';

session_start();

// Handle add to cart via POST 
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = (int)$_POST['product_id'];
    $quantity = 1;
    
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
        $_SESSION['cart_error'] = 'Please login to add items to cart';
        header('Location: customer/login.php');
        exit;
    }
    
    // Get customer_id
    $user_id = $_SESSION['user_id'];
    $cust_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
    $cust_result = mysqli_query($conn, $cust_sql);
    if ($cust_result && mysqli_num_rows($cust_result) > 0) {
        $cust_data = mysqli_fetch_assoc($cust_result);
        $customer_id = $cust_data['customer_id'];
        
        // Check if product already in cart
        $check_sql = "SELECT cart_id, quantity FROM cart WHERE customer_id = '$customer_id' AND product_id = '$product_id'";
        $check_result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_result) > 0) {
            // Update quantity
            $cart_item = mysqli_fetch_assoc($check_result);
            $new_qty = $cart_item['quantity'] + $quantity;
            $update_sql = "UPDATE cart SET quantity = '$new_qty' WHERE cart_id = '{$cart_item['cart_id']}'";
            mysqli_query($conn, $update_sql);
        } else {
            // Insert new
            $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity) VALUES ('$customer_id', '$product_id', '$quantity')";
            mysqli_query($conn, $insert_sql);
        }
        
        $_SESSION['cart_success'] = 'Product added to cart successfully!';
    }
    
    // Redirect back to same page
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Handle search
$search_results = null;
$search_query = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_query = mysqli_real_escape_string($conn, $_GET['search']);
    
    $search_sql = "SELECT p.*, b.business_name 
                   FROM products p 
                   JOIN businesses b ON p.business_id = b.business_id 
                   WHERE (p.name LIKE '%$search_query%' OR p.description LIKE '%$search_query%') 
                   AND p.is_available = 1 
                   AND b.is_active = 1
                   ORDER BY p.created_at DESC 
                   LIMIT 20";
    $search_result = mysqli_query($conn, $search_sql);
    if ($search_result) {
        $search_results = mysqli_fetch_all($search_result, MYSQLI_ASSOC);
    } else {
        $search_results = array();
    }
}

// Get featured products - 10 products
$featured_sql = "SELECT p.*, b.business_name, b.business_id 
                 FROM products p 
                 JOIN businesses b ON p.business_id = b.business_id 
                 WHERE p.is_available = 1 
                 AND b.is_active = 1
                 ORDER BY p.created_at DESC 
                 LIMIT 10";
$featured_result = mysqli_query($conn, $featured_sql);
if ($featured_result) {
    $featured_products = mysqli_fetch_all($featured_result, MYSQLI_ASSOC);
} else {
    $featured_products = array();
}

// Get product IDs for ratings
$product_ids = array_column($featured_products, 'product_id');
$ratings = array();
if (!empty($product_ids)) {
    $ids_string = implode(',', array_map('intval', $product_ids));
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'reviews'");
    if (mysqli_num_rows($table_check) > 0) {
        $rating_sql = "SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count 
                       FROM reviews 
                       WHERE product_id IN ($ids_string) AND status = 'approved' 
                       GROUP BY product_id";
        $rating_res = mysqli_query($conn, $rating_sql);
        if ($rating_res) {
            while ($row = mysqli_fetch_assoc($rating_res)) {
                $ratings[$row['product_id']] = [
                    'avg' => round($row['avg_rating'], 1),
                    'count' => (int)$row['review_count']
                ];
            }
        }
    }
}

// Get statistics
$business_sql = "SELECT COUNT(*) as count FROM businesses WHERE is_active = 1";
$business_result = mysqli_query($conn, $business_sql);
$total_businesses = ($business_result) ? mysqli_fetch_assoc($business_result)['count'] : 0;

$product_sql = "SELECT COUNT(*) as count FROM products WHERE is_available = 1";
$product_result = mysqli_query($conn, $product_sql);
$total_products = ($product_result) ? mysqli_fetch_assoc($product_result)['count'] : 0;

$customer_sql = "SELECT COUNT(*) as count FROM customers";
$customer_result = mysqli_query($conn, $customer_sql);
$total_customers = ($customer_result) ? mysqli_fetch_assoc($customer_result)['count'] : 0;

// Get cart count for logged in customers
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $get_customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
    $customer_result = mysqli_query($conn, $get_customer_sql);
    if ($customer_result && mysqli_num_rows($customer_result) > 0) {
        $customer_data = mysqli_fetch_assoc($customer_result);
        $customer_id = $customer_data['customer_id'];
        $cart_sql = "SELECT SUM(quantity) as total FROM cart WHERE customer_id = '$customer_id'";
        $cart_result = mysqli_query($conn, $cart_sql);
        if ($cart_result) {
            $cart_data = mysqli_fetch_assoc($cart_result);
            $cart_count = $cart_data['total'] ?? 0;
        }
    }
}

// Show cart messages
$cart_message = '';
$cart_message_type = '';
if (isset($_SESSION['cart_success'])) {
    $cart_message = $_SESSION['cart_success'];
    $cart_message_type = 'success';
    unset($_SESSION['cart_success']);
}
if (isset($_SESSION['cart_error'])) {
    $cart_message = $_SESSION['cart_error'];
    $cart_message_type = 'error';
    unset($_SESSION['cart_error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UNK System - Ulipo ni Kariakoo | Best Online Marketplace in Tanzania</title>
    <meta name="description" content="Shop from Kariakoo's best businesses. Compare prices, order online, and get fast delivery in Dar es Salaam, Tanzania.">
    <meta name="keywords" content="Kariakoo, online shopping, Tanzania, e-commerce, marketplace, Dar es Salaam">
    <meta name="author" content="UNK System">
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        /* Navigation */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 20px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .logo span {
            color: #e67e22;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #333;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #e67e22;
        }
        
        .cart-badge {
            background: #e67e22;
            color: white;
            border-radius: 50%;
            padding: 0.125rem 0.5rem;
            font-size: 0.75rem;
            margin-left: 0.25rem;
        }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #2c3e50;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            text-align: center;
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }
        
        .hero-section h1 {
            font-size: 48px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }
        
        .hero-section p {
            font-size: 18px;
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        /* fix button */
        .hero-section .btn {
            position: relative;
            z-index: 10;
            display: inline-block;
            pointer-events: auto;
        }

        .hero-section div[style*="margin-top"] {
            position: relative;
            z-index: 10;
        }
        
        /* Search Box */
        .search-box {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 15px 20px;
            font-size: 16px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .search-input:focus {
            outline: none;
            box-shadow: 0 5px 25px rgba(230,126,34,0.3);
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Section */
        .section {
            padding: 60px 0;
        }
        
        .section-title {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
            color: #2c3e50;
        }
        
        .section-title span {
            color: #e67e22;
        }
        
        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 3px;
            background: #e67e22;
            margin: 15px auto 0;
            border-radius: 2px;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 13px;
        }
        
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        .btn-secondary {
            background: #2c3e50;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44,62,80,0.3);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #2c3e50;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
        }
        
        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .product-image {
            position: relative;
            height: 160px;
            overflow: hidden;
            background: #f0f0f0;
            cursor: pointer;
        }
        
        .product-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s;
        }
        
        .product-card:hover img {
            transform: scale(1.05);
        }
        
        .product-badge {
            position: absolute;
            top: 8px;
            left: 8px;
            background: #e67e22;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: bold;
            z-index: 2;
        }
        
        /* Rating Stars inside product card */
        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 6px 0;
            font-size: 11px;
        }
        
        .rating-stars {
            color: #f39c12;
            font-size: 10px;
        }
        
        .rating-stars i {
            margin-right: 1px;
        }
        
        .rating-count {
            color: #7f8c8d;
            font-size: 10px;
        }
        
        .product-info {
            padding: 12px;
        }
        
        .product-info h3 {
            font-size: 13px;
            margin-bottom: 6px;
            color: #2c3e50;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            height: 36px;
        }
        
        .product-price {
            font-size: 15px;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 6px;
        }
        
        .product-business {
            font-size: 10px;
            color: #7f8c8d;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-business i {
            margin-right: 3px;
        }
        
        .product-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #e67e22;
        }
        
        .stat-card p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* How It Works Cards */
        .how-it-works-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .work-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            text-align: center;
            padding: 30px 20px;
        }
        
        .work-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .work-card i {
            font-size: 48px;
            color: #e67e22;
            margin-bottom: 15px;
        }
        
        .work-card h3 {
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .work-card p {
            color: #7f8c8d;
            font-size: 13px;
            line-height: 1.5;
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Text Center */
        .text-center {
            text-align: center;
        }
        
        /* Footer */
        .footer {
            background: #2c3e50;
            color: #bdc3c7;
            padding: 50px 0 20px;
            margin-top: 50px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-section h4 {
            color: white;
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-section h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: #e67e22;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section ul li a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section ul li a:hover {
            color: #e67e22;
            padding-left: 5px;
        }
        
        .footer-section ul li i {
            margin-right: 8px;
            width: 20px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #34495e;
            font-size: 12px;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1100;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .toast-notification.error {
            background: #e74c3c;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .product-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .product-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .stats-grid, .how-it-works-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 20px;
                gap: 15px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .nav-links.active {
                display: flex;
            }
            
            .hero-section {
                padding: 50px 0;
            }
            
            .hero-section h1 {
                font-size: 28px;
            }
            
            .hero-section p {
                font-size: 14px;
            }
            
            .product-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .product-image {
                height: 140px;
            }
            
            .stats-grid, .how-it-works-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                gap: 20px;
            }
            
            .section {
                padding: 40px 0;
            }
            
            .section-title {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            .hero-section {
                padding: 40px 0;
            }
            
            .hero-section h1 {
                font-size: 22px;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .product-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .footer-section h4::after {
                left: 50%;
                transform: translateX(-50%);
            }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo">
            UNK <span>System</span>
        </a>
        <div class="mobile-menu-btn" onclick="toggleMenu()">
            <i class="fas fa-bars"></i>
        </div>
        <ul class="nav-links" id="navLinks">
            <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="customer/products/index.php"><i class="fas fa-store"></i> Products</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                    <li><a href="customer/das/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="customer/cart/index.php"><i class="fas fa-shopping-cart"></i> Cart <?php if($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?></a></li>
                    <li><a href="customer/orders/index.php"><i class="fas fa-list"></i> Orders</a></li>
                    <li><a href="customer/support/index.php"><i class="fas fa-headset"></i> Support</a></li>
                <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'business'): ?>
                    <li><a href="business/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="business/orders/index.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                    <li><a href="business/support.php"><i class="fas fa-headset"></i> Support</a></li>
                <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'delivery'): ?>
                    <li><a href="delivery/das/dashboard.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                    <li><a href="delivery/earnings/index.php"><i class="fas fa-money-bill-wave"></i> Earnings</a></li>
                <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li><a href="admin/das/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            <?php else: ?>
                <li><a href="customer/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <li><a href="customer/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                <li><a href="business/register.php"><i class="fas fa-store"></i> Sell on UNK</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Cart Message -->
<?php if ($cart_message): ?>
<div class="container" style="margin-top: 20px;">
    <div class="alert alert-<?php echo $cart_message_type; ?> text-center">
        <i class="fas fa-<?php echo $cart_message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $cart_message; ?>
    </div>
</div>
<?php endif; ?>

<!-- Hero Section -->
<section class="hero-section">
    <div class="container">
        <h1>Welcome to <span style="color: #e67e22;">UNK System</span></h1>
        <p>Ulipo ni Kariakoo - Tanzania's Leading Online Marketplace</p>
        
        <div class="search-box">
            <form class="search-form" method="GET" action="index.php" id="searchForm">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       placeholder="Search products in Kariakoo..."
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="customer/products/index.php" class="btn btn-outline">Browse All Products</a>
            <a href="business/register.php" class="btn btn-secondary" style="margin-left: 10px;">Start Selling</a>
        </div>
    </div>
</section>

<?php if ($search_results !== null) : ?>
    <!-- Search Results -->
    <section class="section">
        <div class="container">
            <h2 class="section-title">Search Results for "<span><?php echo htmlspecialchars($search_query); ?></span>"</h2>
            <?php if (empty($search_results)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> No products found for "<strong><?php echo htmlspecialchars($search_query); ?></strong>". Try different keywords.
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach($search_results as $product): ?>
                    <div class="product-card">
                        <div class="product-image" onclick="window.location.href='customer/products/details.php?id=<?php echo $product['product_id']; ?>'">
                            <?php 
                            $product_image = isset($product['image_url']) && !empty($product['image_url']) 
                                        ? $product['image_url'] 
                                        : 'assets/images/default-product.jpg';
                            ?>
                            <img src="<?php echo $product_image; ?>" 
                                alt="<?php echo htmlspecialchars($product['name']); ?>">
                        </div>
                        <div class="product-info">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">TSh <?php echo number_format($product['price'], 0, '.', ','); ?></div>
                            <div class="product-business"><i class="fas fa-store"></i> <?php echo htmlspecialchars($product['business_name']); ?></div>
                            <div class="product-actions">
                                <a href="customer/products/details.php?id=<?php echo $product['product_id']; ?>" 
                                class="btn btn-primary btn-sm">View Details</a>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <input type="hidden" name="add_to_cart" value="1">
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-cart-plus"></i> Add
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<!-- Featured Products - 10 products with ratings -->
<section class="section" style="background: #f8f9fa;">
    <div class="container">
        <h2 class="section-title">Featured <span>Products</span></h2>
        <?php if (empty($featured_products)): ?>
            <div class="alert alert-info text-center">No products available at the moment. Check back soon!</div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach($featured_products as $product):
                    $rating = $ratings[$product['product_id']] ?? ['avg' => 0, 'count' => 0];
                ?>
                <div class="product-card">
                    <div class="product-image" onclick="window.location.href='customer/products/details.php?id=<?php echo $product['product_id']; ?>'">
                        <?php if(isset($product['quantity_in_stock']) && $product['quantity_in_stock'] < 10 && $product['quantity_in_stock'] > 0): ?>
                            <div class="product-badge">Low Stock</div>
                        <?php endif; ?>
                        <?php 
                        $product_image = isset($product['image_url']) && !empty($product['image_url']) 
                                       ? $product['image_url'] 
                                       : 'assets/images/default-product.jpg';
                        ?>
                        <img src="<?php echo $product_image; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="product-price">TSh <?php echo number_format($product['price'], 0, '.', ','); ?></div>
                        <div class="product-business"><i class="fas fa-store"></i> <?php echo htmlspecialchars($product['business_name']); ?></div>
                        
                        <!-- Rating Stars inside product card -->
                        <div class="product-rating">
                            <span class="rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <?php if($i <= round($rating['avg'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </span>
                            <span class="rating-count">(<?php echo $rating['count']; ?> reviews)</span>
                        </div>
                        
                        <div class="product-actions">
                            <a href="customer/products/details.php?id=<?php echo $product['product_id']; ?>" 
                               class="btn btn-primary btn-sm">View Details</a>
                            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="hidden" name="add_to_cart" value="1">
                                    <button type="submit" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center" style="margin-top: 40px;">
            <a href="customer/products/index.php" class="btn btn-primary">View All Products <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- Statistics -->
<section style="padding: 60px 0; background: linear-gradient(135deg, #2c3e50, #1a252f); color: white;">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($total_businesses); ?>+</h3>
                <p>Active Businesses</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_products); ?>+</h3>
                <p>Products Listed</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_customers); ?>+</h3>
                <p>Happy Customers</p>
            </div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="section">
    <div class="container">
        <h2 class="section-title">How It <span>Works</span></h2>
        <div class="how-it-works-grid">
            <div class="work-card">
                <i class="fas fa-user-plus"></i>
                <h3>1. Register</h3>
                <p>Create a free account as a customer, business, or delivery agent</p>
            </div>
            <div class="work-card">
                <i class="fas fa-shopping-cart"></i>
                <h3>2. Shop or Sell</h3>
                <p>Browse products, compare prices, or list your products for sale</p>
            </div>
            <div class="work-card">
                <i class="fas fa-truck"></i>
                <h3>3. Get Delivery</h3>
                <p>Receive your orders or deliver to customers across Kariakoo</p>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-section">
                <h4>UNK System</h4>
                <p>Ulipo ni Kariakoo - Your trusted online marketplace for quality products and reliable delivery services in Tanzania.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="customer/products/index.php"><i class="fas fa-store"></i> Browse Products</a></li>
                    <li><a href="business/register.php"><i class="fas fa-chart-line"></i> Sell on UNK</a></li>
                    <li><a href="delivery/register.php"><i class="fas fa-truck"></i> Become a Driver</a></li>
                    <li><a href="customer/login.php"><i class="fas fa-sign-in-alt"></i> Customer Login</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <ul>
                    <li><i class="fas fa-map-marker-alt"></i> Kariakoo, Dar es Salaam</li>
                    <li><i class="fas fa-phone"></i> +255 615 215 404</li>
                    <li><i class="fas fa-envelope"></i> info@unksystem.com</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> UNK System. All rights reserved. | <a href="#" style="color: #e67e22;">Privacy Policy</a> | <a href="#" style="color: #e67e22;">Terms of Service</a></p>
        </div>
    </div>
</footer>

<script>
// Mobile menu toggle
function toggleMenu() {
    var navLinks = document.getElementById('navLinks');
    if (navLinks.classList.contains('active')) {
        navLinks.classList.remove('active');
    } else {
        navLinks.classList.add('active');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    var navLinks = document.getElementById('navLinks');
    var menuBtn = document.querySelector('.mobile-menu-btn');
    
    if (navLinks && navLinks.classList.contains('active')) {
        if (!navLinks.contains(event.target) && !menuBtn.contains(event.target)) {
            navLinks.classList.remove('active');
        }
    }
});
</script>

</body>
</html>