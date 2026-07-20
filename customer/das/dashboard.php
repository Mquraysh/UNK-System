<?php
// customer/dashboard.php
require_once '../../config/database.php';
session_start();

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: das/login.php");
    exit();
}

// Get customer data
$user_id = $_SESSION['user_id'];
$customer_sql = "SELECT * FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

if (!$customer) {
    header("Location: register.php");
    exit();
}

$customer_id = $customer['customer_id'];
$customer_name = $customer['first_name'];

// Get statistics
$order_sql = "SELECT COUNT(*) as count FROM orders WHERE customer_id = '$customer_id'";
$order_result = mysqli_query($conn, $order_sql);
$order_count = mysqli_fetch_assoc($order_result)['count'] ?? 0;

$pending_sql = "SELECT COUNT(*) as count FROM orders WHERE customer_id = '$customer_id' AND status NOT IN ('delivered', 'cancelled')";
$pending_result = mysqli_query($conn, $pending_sql);
$pending_orders = mysqli_fetch_assoc($pending_result)['count'] ?? 0;

$spent_sql = "SELECT SUM(grand_total) as total FROM orders WHERE customer_id = '$customer_id' AND status = 'delivered'";
$spent_result = mysqli_query($conn, $spent_sql);
$total_spent = mysqli_fetch_assoc($spent_result)['total'] ?? 0;

$cart_sql = "SELECT SUM(quantity) as total FROM cart WHERE customer_id = '$customer_id'";
$cart_result = mysqli_query($conn, $cart_sql);
$cart_count = mysqli_fetch_assoc($cart_result)['total'] ?? 0;

$wishlist_sql = "SELECT COUNT(*) as count FROM wishlist WHERE customer_id = '$customer_id'";
$wishlist_result = mysqli_query($conn, $wishlist_sql);
$wishlist_count = mysqli_fetch_assoc($wishlist_result)['count'] ?? 0;

// Recent orders
$recent_sql = "SELECT o.*, b.business_name 
               FROM orders o 
               JOIN businesses b ON o.business_id = b.business_id 
               WHERE o.customer_id = '$customer_id' 
               ORDER BY o.order_date DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_sql);
$recent_orders = mysqli_fetch_all($recent_result, MYSQLI_ASSOC);

// User details
$user_res = mysqli_query($conn, "SELECT phone, email FROM users WHERE user_id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_res);
$user_phone = $user_data['phone'] ?? 'Not provided';
$user_email = $user_data['email'] ?? '';

include '../includes/customer_sidebar.php';
// include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Dashboard | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Welcome Header */
        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 35px;
        }
        .welcome-text h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
            letter-spacing: -0.3px;
        }
        .welcome-text h1 span { 
            color: #e67e22;
            position: relative;
        }
        .welcome-text p { 
            color: #64748b; 
            font-size: 15px;
        }
        .date-badge {
            background: white;
            padding: 10px 22px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 500;
            color: #e67e22;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        }
        
        /* Stats Grid  */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 35px;
        }
        .stat-card {
            background: white;
            border-radius: 24px;
            padding: 10px 12px;
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
            font-size: 36px;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 8px;
        }
        .stat-card p {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .stat-card p i { font-size: 13px; }
        
        /* Two Columns */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 28px;
            margin-bottom: 35px;
        }
        
        /* Cards  */
        .card {
            background: white;
            border-radius: 28px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 20px 24px;
            border-bottom: 2px solid #f0f2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h3 i { 
            color: #e67e22; 
            font-size: 20px;
        }
        .card-header a {
            color: #e67e22;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .card-header a:hover {
            text-decoration: underline;
        }
        .card-body { 
            padding: 20px 24px; 
        }
        
        /* Quick Actions  */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .action-btn {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
            color: white;
        }
        .action-btn:hover { 
            transform: translateY(-3px); 
            filter: brightness(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .action-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .action-text h4 { 
            font-size: 15px; 
            font-weight: 700; 
            margin-bottom: 5px; 
        }
        .action-text p { 
            font-size: 12px; 
            opacity: 0.9; 
        }
        
        /* Button Gradients */
        .btn-shop { background: linear-gradient(105deg, #e67e22, #d35400); }
        .btn-cart { background: linear-gradient(105deg, #2c3e50, #1a2632); }
        .btn-orders { background: linear-gradient(105deg, #27ae60, #1e8449); }
        .btn-wishlist { background: linear-gradient(105deg, #e74c3c, #c0392b); }
        .btn-track { background: linear-gradient(105deg, #3498db, #2980b9); }
        .btn-support { background: linear-gradient(105deg, #8e44ad, #6c3483); }
        
        /* Orders Table  */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .orders-table th {
            text-align: left;
            padding: 14px 8px;
            color: #64748b;
            font-weight: 600;
            background: #fafcff;
            font-size: 13px;
        }
        .orders-table td {
            padding: 14px 8px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        .orders-table tr { 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .orders-table tr:hover { 
            background: #fefaf5; 
        }
        .order-id {
            font-weight: 700;
            color: #e67e22;
            font-family: monospace;
            font-size: 13px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 14px;
            border-radius: 40px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-confirmed, .status-processing { background: #dbeafe; color: #2563eb; }
        .status-shipped { background: #fed7aa; color: #c2410c; }
        
        /* Profile Section  */
        .profile-grid { display: flex; flex-direction: column; gap: 14px; }
        .profile-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .profile-icon {
            width: 42px;
            height: 42px;
            background: rgba(230,126,34,0.08);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-icon i { 
            font-size: 18px; 
            color: #e67e22; 
        }
        .profile-label {
            width: 120px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }
        .profile-value {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #1e293b;
        }
        .profile-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        /* Buttons  */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(230,126,34,0.3);
        }
        .btn-outline {
            background: white;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }
        .btn-outline:hover {
            border-color: #e67e22;
            color: #e67e22;
            transform: translateY(-2px);
        }
        
        /* Empty State  */
        .empty-state {
            text-align: center;
            padding: 50px 30px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 32px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state p {
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        /* Responsive */
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .customer-content { padding: 25px; }
        }
        @media (max-width: 992px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .two-columns { grid-template-columns: 1fr; gap: 20px; }
            .stats-grid { gap: 15px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .actions-grid { grid-template-columns: 1fr; }
            .welcome-text h1 { font-size: 28px; }
            .profile-row { flex-wrap: wrap; gap: 10px; }
            .profile-label { width: auto; }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-header { flex-direction: column; align-items: flex-start; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        
        /* Utility */
        .text-muted { color: #64748b; }
        .fw-bold { font-weight: 700; }
        .mt-2 { margin-top: 8px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="customer-content">
    <!-- Header  -->
    <div class="welcome-header">
        <div class="welcome-text">
            <h1>Hello, <span><?php echo htmlspecialchars($customer_name); ?></span></h1>
            <p><i class="fas fa-chart-line"></i> Here's what's happening with your account today</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <!-- Stats Cards-->
    <div class="stats-grid">
        <div class="stat-card" onclick="location.href='../orders/index.php'">
            <h3><?php echo number_format($order_count); ?></h3>
            <p><i class="fas fa-shopping-bag"></i> Total Orders</p>
        </div>
        <div class="stat-card" onclick="location.href='../orders/index.php?status=pending'">
            <h3><?php echo number_format($pending_orders); ?></h3>
            <p><i class="fas fa-clock"></i> In Progress</p>
        </div>
        <div class="stat-card" onclick="location.href='../cart/index.php'">
            <h3><?php echo number_format($cart_count); ?></h3>
            <p><i class="fas fa-shopping-cart"></i> Cart Items</p>
        </div>
        <div class="stat-card" onclick="location.href='../wishlist/index.php'">
            <h3><?php echo number_format($wishlist_count); ?></h3>
            <p><i class="fas fa-heart"></i> Wishlist</p>
        </div>
        <div class="stat-card" onclick="location.href='../orders/index.php'">
            <h3>TSh <?php echo number_format($total_spent); ?></h3>
            <p><i class="fas fa-coins"></i> Lifetime Spent</p>
        </div>
    </div>

    <!-- Two Columns -->
    <div class="two-columns">
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="card-body">
                <div class="actions-grid">
                    <a href="../products/index.php" class="action-btn btn-shop">
                        <div class="action-icon"><i class="fas fa-store"></i></div>
                        <div class="action-text">
                            <h4>Shop Now</h4>
                            <p>Browse thousands of products</p>
                        </div>
                    </a>
                    <a href="../cart/index.php" class="action-btn btn-cart">
                        <div class="action-icon"><i class="fas fa-shopping-cart"></i></div>
                        <div class="action-text">
                            <h4>My Cart</h4>
                            <p><?php echo $cart_count; ?> item(s) waiting</p>
                        </div>
                    </a>
                    <a href="../orders/index.php" class="action-btn btn-orders">
                        <div class="action-icon"><i class="fas fa-list"></i></div>
                        <div class="action-text">
                            <h4>My Orders</h4>
                            <p>Track delivery status</p>
                        </div>
                    </a>
                    <a href="../wishlist/index.php" class="action-btn btn-wishlist">
                        <div class="action-icon"><i class="fas fa-heart"></i></div>
                        <div class="action-text">
                            <h4>Wishlist</h4>
                            <p><?php echo $wishlist_count; ?> saved items</p>
                        </div>
                    </a>
                    <a href="../orders/track.php" class="action-btn btn-track">
                        <div class="action-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="action-text">
                            <h4>Track Order</h4>
                            <p>Real-time location</p>
                        </div>
                    </a>
                    <a href="../support/index.php" class="action-btn btn-support">
                        <div class="action-icon"><i class="fas fa-headset"></i></div>
                        <div class="action-text">
                            <h4>Support Center</h4>
                            <p>24/7 customer care</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Orders</h3>
                <a href="../orders/index.php">View All Orders →</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>You haven't placed any orders yet</p>
                    <a href="../products/index.php" class="btn btn-primary" style="margin-top: 8px;">
                        <i class="fas fa-store"></i> Start Shopping
                    </a>
                </div>
                <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Business</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr onclick="location.href='../orders/details.php?id=<?php echo $order['order_id']; ?>'">
                                <td class="order-id"><?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                                <td><strong>TSh <?php echo number_format($order['grand_total']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <i class="fas <?php echo $order['status'] == 'delivered' ? 'fa-check-circle' : ($order['status'] == 'pending' ? 'fa-clock' : 'fa-info-circle'); ?>"></i>
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Account Information  -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-circle"></i> Account Information</h3>
            <a href="../settings/profile.php">Edit Profile →</a>
        </div>
        <div class="card-body">
            <div class="profile-grid">
                <div class="profile-row">
                    <div class="profile-icon"><i class="fas fa-user"></i></div>
                    <div class="profile-label">Full Name</div>
                    <div class="profile-value"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-icon"><i class="fas fa-envelope"></i></div>
                    <div class="profile-label">Email Address</div>
                    <div class="profile-value"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-icon"><i class="fas fa-phone"></i></div>
                    <div class="profile-label">Phone Number</div>
                    <div class="profile-value"><?php echo htmlspecialchars($user_phone); ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="profile-label">Delivery Address</div>
                    <div class="profile-value"><?php echo htmlspecialchars($customer['saved_address'] ?? 'Not provided'); ?></div>
                </div>
                <div class="profile-row">
                    <div class="profile-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="profile-label">Member Since</div>
                    <div class="profile-value"><?php echo !empty($customer['created_at']) ? date('F d, Y', strtotime($customer['created_at'])) : 'N/A'; ?></div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="../settings/profile.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="../settings/address.php" class="btn btn-outline">
                    <i class="fas fa-map-pin"></i> Manage Address
                </a>
                <a href="../settings/change-password.php" class="btn btn-outline">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="../orders/index.php" class="btn btn-outline">
                    <i class="fas fa-receipt"></i> Order History
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
