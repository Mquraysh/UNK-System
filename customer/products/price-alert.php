<?php
// customer/products/price-alert.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Handle cancel/remove alert
if (isset($_GET['remove'])) {
    $alert_id = (int)$_GET['remove'];
    $update_sql = "UPDATE price_alerts SET status = 'cancelled' WHERE alert_id = '$alert_id' AND customer_id = '$customer_id'";
    mysqli_query($conn, $update_sql);
    $_SESSION['flash_message'] = "Price alert cancelled successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: price-alert.php");
    exit();
}

// Handle adding new alert from POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['product_id'])) {
    $product_id = (int)$_POST['product_id'];
    $desired_price = (float)$_POST['desired_price'];
    
    $check_sql = "SELECT * FROM price_alerts WHERE customer_id = '$customer_id' AND product_id = '$product_id' AND status = 'active'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if(mysqli_num_rows($check_result) == 0) {
        $insert_sql = "INSERT INTO price_alerts (customer_id, product_id, desired_price) VALUES ('$customer_id', '$product_id', '$desired_price')";
        mysqli_query($conn, $insert_sql);
        $_SESSION['flash_message'] = "Price alert set! We'll notify you when price drops to TSh " . number_format($desired_price);
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "You already have an active alert for this product.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: details.php?id=$product_id");
    exit();
}

// Get flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Get user's active alerts
$alerts_sql = "SELECT a.*, p.name, p.price, p.image_url, p.product_id, b.business_name
               FROM price_alerts a
               JOIN products p ON a.product_id = p.product_id
               JOIN businesses b ON p.business_id = b.business_id
               WHERE a.customer_id = '$customer_id' AND a.status = 'active'
               ORDER BY a.created_at DESC";
$alerts_result = mysqli_query($conn, $alerts_sql);
$alerts = [];
while($row = mysqli_fetch_assoc($alerts_result)) {
    // Check if price has dropped to desired price
    $row['price_dropped'] = $row['price'] <= $row['desired_price'];
    $alerts[] = $row;
}

include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Price Alerts - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        
        .customer-content {
            margin-left: 0;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s ease;
        }
        
        /* Header */
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
        .page-header h1 i {
            color: #e67e22;
            font-size: 28px;
        }
        .page-header p {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }
        
        /* Buttons */
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .btn-browse {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-browse:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h3 i {
            color: #e67e22;
        }
        
        /* Alert Item */
        .alert-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 24px;
            border-bottom: 1px solid #eef2f6;
            transition: all 0.3s;
        }
        .alert-item:last-child {
            border-bottom: none;
        }
        .alert-item:hover {
            background: #fffbeb;
        }
        .alert-item img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
        }
        .alert-item .alert-info {
            flex: 1;
        }
        .alert-item .product-name {
            font-weight: 700;
            font-size: 15px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .alert-item .product-business {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .alert-item .price-info {
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .alert-item .current-price {
            color: #e67e22;
            font-weight: 700;
        }
        .alert-item .desired-price {
            color: #27ae60;
            font-weight: 700;
        }
        
        /* Price Dropped Badge */
        .price-dropped-badge {
            background: #dc2626;
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .btn-remove {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-remove:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        .btn-view-product {
            background: #e67e22;
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-left: 10px;
        }
        .btn-view-product:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
        }
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        /* Info Card */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .info-item i {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #e67e22;
        }
        .info-item .info-text {
            flex: 1;
        }
        .info-item .info-text strong {
            display: block;
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .info-item .info-text span {
            font-size: 12px;
            color: #64748b;
        }
        
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 1024px) {
            .customer-content {
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            .alert-item {
                flex-direction: column;
                text-align: center;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
            }
            .price-info {
                justify-content: center;
            }
            .action-buttons {
                margin-top: 10px;
                justify-content: center;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .text-muted { color: #64748b; }
        .fw-bold { font-weight: 700; }
        .mt-3 { margin-top: 16px; }
        .mb-2 { margin-bottom: 8px; }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-bell"></i> Price Drop Alerts</h1>
            <p>Get notified when your favorite products go on sale</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Shop</a>
    </div>
    
    <!-- Flash Message -->
    <?php if(!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Active Alerts Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Your Active Alerts</h3>
        </div>
        
        <?php if(empty($alerts)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No active price alerts</h3>
                <p>Set alerts on product pages to get notified when prices drop</p>
                <a href="index.php" class="btn-browse"><i class="fas fa-store"></i> Browse Products</a>
            </div>
        <?php else: ?>
            <?php foreach($alerts as $alert): ?>
            <div class="alert-item">
                <img src="<?php 
                    $img = '../../assets/images/default-product.jpg';
                    if(!empty($alert['image_url'])) {
                        if(file_exists('../../' . $alert['image_url'])) {
                            $img = '../../' . $alert['image_url'];
                        } elseif(file_exists($alert['image_url'])) {
                            $img = $alert['image_url'];
                        }
                    }
                    echo $img;
                ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                <div class="alert-info">
                    <div class="product-name"><?php echo htmlspecialchars($alert['name']); ?></div>
                    <div class="product-business">
                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($alert['business_name']); ?>
                    </div>
                    <div class="price-info">
                        <span>Current: <span class="current-price">TSh <?php echo number_format($alert['price']); ?></span></span>
                        <span>Target: <span class="desired-price">TSh <?php echo number_format($alert['desired_price']); ?></span></span>
                        <span style="font-size: 11px; color: #64748b;">
                            <i class="fas fa-clock"></i> Since <?php echo date('M d, Y', strtotime($alert['created_at'])); ?>
                        </span>
                    </div>
                    <?php if($alert['price_dropped']): ?>
                        <div class="price-dropped-badge mt-2">
                            <i class="fas fa-arrow-down"></i> Price Dropped! Buy Now
                        </div>
                    <?php endif; ?>
                </div>
                <div class="action-buttons">
                    <a href="details.php?id=<?php echo $alert['product_id']; ?>" class="btn-view-product">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="?remove=<?php echo $alert['alert_id']; ?>" class="btn-remove" onclick="return confirm('Cancel this price alert?')">
                        <i class="fas fa-trash"></i> Cancel
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- How It Works Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> How Price Alerts Work</h3>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-tag"></i>
                <div class="info-text">
                    <strong>1. Set Your Target Price</strong>
                    <span>Choose a product and set the price you want to pay</span>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-chart-line"></i>
                <div class="info-text">
                    <strong>2. We Monitor Daily</strong>
                    <span>Our system checks product prices every day</span>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-bell"></i>
                <div class="info-text">
                    <strong>3. Get Notified</strong>
                    <span>We'll alert you immediately when price drops</span>
                </div>
            </div>
            <div class="info-item">
                <i class="fas fa-shopping-cart"></i>
                <div class="info-text">
                    <strong>4. Buy at Best Price</strong>
                    <span>Purchase instantly when you receive the alert</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tips Card -->
    <!-- <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lightbulb"></i> Pro Tips</h3>
        </div>
        <div style="padding: 20px;">
            <ul style="color: #64748b; line-height: 1.8; margin-left: 20px;">
                <li>Set realistic target prices (10-20% below current price)</li>
                <li>Create alerts for seasonal items before prices go up</li>
                <li>Multiple alerts? We'll notify you for each product separately</li>
                <li>Cancel alerts anytime - no questions asked</li>
            </ul>
        </div>
    </div> -->
</div>
</body>
</html>