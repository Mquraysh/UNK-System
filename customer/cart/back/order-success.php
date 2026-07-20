<?php
// customer/cart/order-success.php - Order Success Page (Professional Design)
require_once '../../config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

// Get customer ID
$customer_id_query = "SELECT customer_id FROM customers WHERE user_id = " . intval($_SESSION['user_id']);
$customer_result = mysqli_query($conn, $customer_id_query);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'] ?? 0;

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id > 0 && $customer_id > 0) {
    // Get specific order
    $sql = "SELECT o.*, b.business_name, b.business_id 
            FROM orders o 
            JOIN businesses b ON o.business_id = b.business_id 
            WHERE o.order_id = $order_id 
            AND o.customer_id = $customer_id";
    $result = mysqli_query($conn, $sql);
    $order = mysqli_fetch_assoc($result);
} else {
    // Get most recent order if no order_id provided
    $sql = "SELECT o.*, b.business_name, b.business_id 
            FROM orders o 
            JOIN businesses b ON o.business_id = b.business_id 
            WHERE o.customer_id = $customer_id 
            ORDER BY o.order_id DESC 
            LIMIT 1";
    $result = mysqli_query($conn, $sql);
    $order = mysqli_fetch_assoc($result);
    if ($order) {
        $order_id = $order['order_id'];
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 28px 32px;
            min-height: 100vh;
            transition: all 0.3s;

        }
        
        .page-header { margin-bottom: 28px; }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header h1 i { color: #27ae60; font-size: 32px; }
        .page-header p { 
            color: #64748b; 
            font-size: 16px; 
            margin-top: 8px;
            font-weight: 500;
        }
        
        .container { max-width: 1200px; margin: 0 auto; }
        .card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.12); 
            overflow: hidden; 
            text-align: center; 
            padding: 48px 40px; 
            border: 1px solid #e2e8f0; 
        }
        
        .success-icon { 
            font-size: 80px; 
            color: #27ae60; 
            margin-bottom: 16px;
            background: #ecfdf5;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .card h2 { 
            font-size: 28px; 
            margin-bottom: 8px; 
            color: #1e293b; 
            font-weight: 800;
        }
        .card .sub-text { 
            color: #64748b; 
            font-size: 16px; 
            margin-bottom: 24px;
        }
        
        .order-details { 
            background: #f8fafc; 
            border-radius: 16px; 
            padding: 24px; 
            margin: 20px 0; 
            text-align: left; 
            border: 1px solid #eef2f6;
        }
        .order-details .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        .order-details .row { 
            display: flex; 
            justify-content: space-between; 
            padding: 10px 0; 
            border-bottom: 1px solid #eef2f6; 
            font-size: 14px;
        }
        .order-details .row:last-child { 
            border-bottom: none; 
            font-weight: 700; 
            font-size: 18px; 
            color: #e67e22;
            padding-top: 14px;
            margin-top: 4px;
            border-top: 2px solid #e2e8f0;
        }
        .order-details .row .label { color: #64748b; }
        .order-details .row .value { font-weight: 600; color: #1e293b; }
        .order-details .row:last-child .value { color: #e67e22; font-size: 20px; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.pending_verification { background: #dbeafe; color: #1e40af; }
        .status-badge.processing { background: #dbeafe; color: #1e40af; }
        .status-badge.shipped { background: #d1fae5; color: #065f46; }
        .status-badge.delivered { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }
        
        .order-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 24px;
        }
        
        .btn { 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            padding: 12px 28px; 
            background: #e67e22; 
            color: white; 
            border: none; 
            border-radius: 40px; 
            font-size: 14px; 
            font-weight: 600; 
            text-decoration: none; 
            transition: all 0.2s; 
            cursor: pointer;
        }
        .btn:hover { 
            background: #d35400; 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(230,126,34,0.25);
        }
        .btn-outline { 
            background: transparent; 
            border: 2px solid #e2e8f0; 
            color: #64748b; 
        }
        .btn-outline:hover { 
            border-color: #e67e22; 
            color: #e67e22; 
            background: transparent; 
            transform: translateY(-2px);
            box-shadow: none;
        }
        
        .delivery-info {
            background: #ecfdf5;
            padding: 16px 20px;
            border-radius: 12px;
            margin: 16px 0;
            border: 1px solid #a7f3d0;
            text-align: left;
        }
        .delivery-info i { color: #27ae60; margin-right: 8px; }
        .delivery-info .label { color: #64748b; font-size: 13px; }
        .delivery-info .value { font-weight: 600; color: #1e293b; }
        
        .verification-notice {
            background: #fef9e7;
            padding: 12px 16px;
            border-radius: 12px;
            margin: 16px 0;
            border: 1px solid #fde3b6;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: left;
        }
        .verification-notice i { 
            font-size: 20px; 
            color: #e67e22; 
            flex-shrink: 0;
        }
        .verification-notice .text { 
            font-size: 13px; 
            color: #8d6e63; 
        }
        .verification-notice .text strong { color: #92400e; }
        .verification-notice .text a { 
            color: #e67e22; 
            font-weight: 600; 
            text-decoration: underline; 
        }
        
        /* Order Confirmation Badge */
        .confirmation-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #ecfdf5;
            color: #065f46;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin: 8px 0 16px;
            border: 1px solid #a7f3d0;
        }
        .confirmation-badge i { color: #27ae60; }
        
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 640px) {
            .customer-content { padding: 16px; }
            .card { padding: 24px 20px; }
            .order-actions { flex-direction: column; }
            .btn { justify-content: center; }
            .verification-notice { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-check-circle"></i> Order Successful</h1>
        <p>Your order has been placed successfully</p>
    </div>

    <div class="container">
        <div class="card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2>Thank You for Your Order! </h2>
            <p class="sub-text">Your order has been confirmed and is being processed by the seller.</p>
            
            <!-- Confirmation Badge -->
            <div class="confirmation-badge">
                <i class="fas fa-check-circle"></i> Order Confirmed
            </div>
            
            <?php if (isset($order) && $order): ?>
            <div class="order-details">
                <div class="section-title">
                    <i class="fas fa-receipt" style="color: #e67e22;"></i> Order Details
                </div>
                <div class="row">
                    <span class="label">Order Number</span>
                    <span class="value"><strong> <?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong></span>
                </div>
                <div class="row">
                    <span class="label">Business</span>
                    <span class="value"><?php echo htmlspecialchars($order['business_name']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Total Amount</span>
                    <span class="value">TSh <?php echo number_format($order['total_amount'], 0, '.', ','); ?></span>
                </div>
                <div class="row">
                    <span class="label">Delivery Fee</span>
                    <span class="value">TSh <?php echo number_format($order['delivery_fee'], 0, '.', ','); ?></span>
                </div>
                <div class="row">
                    <span class="label">Payment Method</span>
                    <span class="value"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value">
                        <span class="status-badge <?php echo $order['status']; ?>">
                            <?php 
                            $status_labels = [
                                'pending' => 'Pending',
                                'pending_verification' => 'Verification',
                                'processing' => 'Processing',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled'
                            ];
                            echo $status_labels[$order['status']] ?? ucfirst($order['status']); 
                            ?>
                        </span>
                    </span>
                </div>
                <div class="row">
                    <span class="label">Grand Total</span>
                    <span class="value">TSh <?php echo number_format($order['grand_total'], 0, '.', ','); ?></span>
                </div>
            </div>
            
            <!-- Delivery Address -->
            <?php if (!empty($order['delivery_address'])): ?>
            <div class="delivery-info">
                <i class="fas fa-map-marker-alt"></i>
                <span class="label">Delivery Address:</span>
                <span class="value"><?php echo htmlspecialchars($order['delivery_address']); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Verification Notice for pending_verification status -->
            <?php if ($order['status'] == 'pending_verification'): ?>
            <div class="verification-notice">
                <i class="fas fa-clock"></i>
                <div class="text">
                    <strong>Verification Pending:</strong> 
                    Please check your email for the OTP to verify your order. 
                    If you haven't received it, <a href="../cart/verify-order.php">click here</a> to verify now.
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
            
            <!-- Order Actions -->
            <div class="order-actions">
                <a href="../orders/index.php" class="btn">
                    <i class="fas fa-box"></i> View My Orders
                </a>
                <a href="../products/index.php" class="btn btn-outline">
                    <i class="fas fa-store"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
