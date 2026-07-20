<?php
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
                    * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                line-height: 1.6;
                color: #333;
                background: #f5f7fa;
            }
            
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
            
            .nav-links  i{
                color:  #2c3e50;;
                transition: color 0.3s;
            }
            .nav-links a:hover {
                color: #e67e22;
            }
            .nav-links a:hover i {
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

            @media (max-width: 768px) {
                .mobile-menu-btn {
                    display: block
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
                    <li><a href="../../index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="../../customer/products/index.php"><i class="fas fa-store"></i> Products</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                            <li><a href="../../customer/das/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="../../customer/cart/index.php"><i class="fas fa-shopping-cart"></i> Cart <?php if($cart_count > 0): ?><span class="cart-badge"><?php echo $cart_count; ?></span><?php endif; ?></a></li>
                            <li><a href="../../customer/orders/index.php"><i class="fas fa-list"></i> Orders</a></li>
                            <!-- <li><a href="../../customer/support/index.php"><i class="fas fa-headset"></i> Support</a></li> -->
                        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'business'): ?>
                            <li><a href="../../business/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="../../business/orders/index.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                            <li><a href="../../business/support.php"><i class="fas fa-headset"></i> Support</a></li>
                        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'delivery'): ?>
                            <li><a href="../../delivery/das/dashboard.php"><i class="fas fa-truck"></i> Deliveries</a></li>
                            <li><a href="../../delivery/earnings/earnings.php"><i class="fas fa-money-bill-wave"></i> Earnings</a></li>
                        <?php elseif(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <li><a href="../../admin/dashboard.php"><i class="fas fa-cog"></i> Admin Panel</a></li>
                        <?php endif; ?>
                        <li><a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    <?php else: ?>
                        <li><a href="../../customer/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="../../customer/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                        <li><a href="../../business/register.php"><i class="fas fa-store"></i> Sell on UNK</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>
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
        </script>
    </body>
</html>