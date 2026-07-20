<?php
// customer/orders/details.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Handle AJAX refresh request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax && isset($_GET['refresh'])) {
    header('Content-Type: application/json');
    
    $order_sql = "SELECT o.*, b.business_name, b.phone as business_phone, b.address as business_address,
                         c.first_name, c.last_name, c.saved_address
                  FROM orders o
                  JOIN businesses b ON o.business_id = b.business_id
                  JOIN customers c ON o.customer_id = c.customer_id
                  WHERE o.order_id = '$order_id' AND o.customer_id = '$customer_id'";
    $order_result = mysqli_query($conn, $order_sql);
    $order = mysqli_fetch_assoc($order_result);
    
    $delivery_sql = "SELECT * FROM deliveries WHERE order_id = '$order_id'";
    $delivery_result = mysqli_query($conn, $delivery_sql);
    $delivery = mysqli_fetch_assoc($delivery_result);
    
    echo json_encode([
        'status' => $order['status'],
        'delivery_status' => $delivery ? $delivery['status'] : null,
        'payment_status' => $order['payment_status'],
        'order_date' => date('F j, Y g:i A', strtotime($order['order_date']))
    ]);
    exit();
}

// Get order details
$order_sql = "SELECT o.*, b.business_name, b.phone as business_phone, b.address as business_address,
                     c.first_name, c.last_name, c.saved_address
              FROM orders o
              JOIN businesses b ON o.business_id = b.business_id
              JOIN customers c ON o.customer_id = c.customer_id
              WHERE o.order_id = '$order_id' AND o.customer_id = '$customer_id'";
$order_result = mysqli_query($conn, $order_sql);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Get order items
$items_sql = "SELECT oi.*, p.name, p.image_url, p.unit
              FROM order_items oi
              JOIN products p ON oi.product_id = p.product_id
              WHERE oi.order_id = '$order_id'";
$items_result = mysqli_query($conn, $items_sql);

// Get delivery info
$delivery_sql = "SELECT * FROM deliveries WHERE order_id = '$order_id'";
$delivery_result = mysqli_query($conn, $delivery_sql);
$delivery = mysqli_fetch_assoc($delivery_result);

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Define tracking steps based on real order flow
$status_flow = [
    'pending'   => ['label' => 'Order Placed',  'icon' => 'shopping-cart', 'order' => 1],
    'accepted'  => ['label' => 'Accepted',      'icon' => 'check',         'order' => 2],
    'confirmed' => ['label' => 'Confirmed',     'icon' => 'check-double',  'order' => 3],
    'preparing' => ['label' => 'Preparing',     'icon' => 'cogs',          'order' => 4],
    'ready'     => ['label' => 'Ready',         'icon' => 'box-open',      'order' => 5],
    'picked_up' => ['label' => 'Picked Up',     'icon' => 'truck',         'order' => 6],
    'delivered' => ['label' => 'Delivered',     'icon' => 'home',          'order' => 7],
    'cancelled' => ['label' => 'Cancelled',     'icon' => 'times-circle',  'order' => 8]
];
$current_status = $order['status'];
$current_order = isset($status_flow[$current_status]) ? $status_flow[$current_status]['order'] : 0;

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .customer-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; transition: all 0.3s; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; color: #1e293b; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-back:hover { background: #1a252f; }
        .alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        /* Tracking Progress */
        .tracking-progress { display: flex; justify-content: space-between; margin-bottom: 25px; background: transparent; padding: 0; border: none; flex-wrap: wrap; }
        .tracking-step { text-align: center; flex: 1; min-width: 80px; position: relative; }
        .tracking-step .step-icon { width: 45px; height: 45px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; color: #64748b; transition: all 0.2s; }
        .tracking-step.active .step-icon { background: #e67e22; color: white; box-shadow: 0 4px 10px rgba(230,126,34,0.3); }
        .tracking-step.completed .step-icon { background: #27ae60; color: white; }
        .tracking-step .step-label { font-size: 11px; color: #64748b; }
        .tracking-step.active .step-label { color: #e67e22; font-weight: 600; }
        
        .order-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        .info-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
        .info-card-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .info-card-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .info-card-header h3 i { color: #e67e22; }
        .info-card-body { padding: 24px; }
        .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #eef2f6; }
        .info-row:last-child { border-bottom: none; }
        .info-label { width: 130px; font-weight: 600; color: #64748b; }
        .info-value { flex: 1; color: #1e293b; }
        
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted, .status-confirmed, .status-preparing, .status-ready, .status-picked_up { background: #dbeafe; color: #2563eb; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        .table-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 25px; }
        .table-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .table-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #eef2f6; }
        .data-table th { background: #f8fafc; font-weight: 600; color: #64748b; font-size: 12px; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .total-row { background: #f8fafc; font-weight: 700; }
        
        .action-buttons { display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; }
        .toast-message { position: fixed; bottom: 30px; right: 30px; background: #1e293b; color: white; padding: 12px 24px; border-radius: 50px; z-index: 2000; opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        
        @media (max-width: 1024px) { .customer-content { margin-left: 0; padding: 20px; } .order-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .info-row { flex-direction: column; } .info-label { width: 100%; margin-bottom: 5px; } .tracking-progress { gap: 10px; } }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-receipt"></i> Order Details</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Orders</a>
    </div>
    
    <?php if(!empty($flash_message)): ?>
    <div class="alert alert-<?php echo $flash_type; ?>">
        <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($flash_message); ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Tracking Progress -->
    <div class="tracking-progress" id="trackingProgress">
        <?php foreach($status_flow as $key => $step): 
            $step_order = $step['order'];
        ?>
        <div class="tracking-step 
            <?php echo ($step_order <= $current_order) ? 'completed' : ''; ?> 
            <?php echo $key == $current_status ? 'active' : ''; ?>"
            data-status="<?php echo $key; ?>">
            <div class="step-icon">
                <i class="fas fa-<?php echo $step['icon']; ?>"></i>
            </div>
            <div class="step-label"><?php echo $step['label']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="order-grid">
        <!-- Order Information -->
        <div class="info-card">
            <div class="info-card-header">
                <h3><i class="fas fa-info-circle"></i> Order Information</h3>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <div class="info-label">Order ID:</div>
                    <div class="info-value"><?php echo $order['order_id']; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Order Date:</div>
                    <div class="info-value" id="orderDate"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $order['status']; ?>" id="orderStatusBadge">
                            <i class="fas fa-<?php 
                                if ($order['status'] == 'pending') echo 'clock';
                                elseif (in_array($order['status'], ['accepted','confirmed','preparing','ready','picked_up'])) echo 'check-circle';
                                elseif ($order['status'] == 'delivered') echo 'home';
                                else echo 'times-circle';
                            ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Method:</div>
                    <div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Payment Status:</div>
                    <div class="info-value" id="paymentStatus"><?php echo ucfirst($order['payment_status']); ?></div>
                </div>
                <?php if(!empty($order['special_instructions'])): ?>
                <div class="info-row">
                    <div class="info-label">Special Instructions:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delivery Information -->
        <div class="info-card">
            <div class="info-card-header">
                <h3><i class="fas fa-truck"></i> Delivery Information</h3>
            </div>
            <div class="info-card-body">
                <div class="info-row">
                    <div class="info-label">Customer Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Delivery Address:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Business:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['business_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Business Phone:</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['business_phone']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Business Address:</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['business_address'])); ?></div>
                </div>
                <?php if($delivery): ?>
                <div class="info-row">
                    <div class="info-label">Delivery Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?php echo $delivery['status']; ?>" id="deliveryStatusBadge">
                            <?php echo ucfirst($delivery['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-box"></i> Order Items</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>Image</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <?php while($item = mysqli_fetch_assoc($items_result)): 
                        $img_src = '../../assets/images/default-product.jpg';
                        if(!empty($item['image_url']) && file_exists('../../' . $item['image_url'])) {
                            $img_src = '../../' . $item['image_url'];
                        }
                    ?>
                    <tr>
                        <td><img src="<?php echo $img_src; ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'"></td>
                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['unit']; ?></small></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>TSh <?php echo number_format($item['unit_price']); ?></td>
                        <td>TSh <?php echo number_format($item['subtotal']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td><td><strong>TSh <?php echo number_format($order['total_amount']); ?></strong></td></tr>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Delivery Fee:</strong></td><td><strong>TSh <?php echo number_format($order['delivery_fee']); ?></strong></td></tr>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Grand Total:</strong></td><td><strong style="color: #e67e22;">TSh <?php echo number_format($order['grand_total']); ?></strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <?php if(in_array($order['status'], ['pending'])): ?>
            <a href="cancel.php?id=<?php echo $order['order_id']; ?>" class="btn-back" style="background: #e74c3c;" onclick="return confirm('Are you sure you want to cancel this order?')">
                <i class="fas fa-times"></i> Cancel Order
            </a>
        <?php endif; ?>
        
        <?php 
        // Show Track button for all statuses from confirmed to picked_up
        $track_statuses = ['confirmed', 'preparing', 'ready', 'picked_up', 'processing', 'shipped'];
        if(in_array($order['status'], $track_statuses) || $order['status'] == 'accepted'): ?>
            <a href="track.php?id=<?php echo $order['order_id']; ?>" class="btn-back" style="background: #27ae60;">
                <i class="fas fa-map-marker-alt"></i> Track Order
            </a>
        <?php endif; ?>
        
        <?php if($order['status'] == 'delivered'): ?>
            <a href="invoice.php?id=<?php echo $order['order_id']; ?>" class="btn-back" style="background: #27ae60;" target="_blank">
                <i class="fas fa-print"></i> Download Invoice
            </a>
        <?php endif; ?>
    </div>
</div>

<div id="toastMessage" class="toast-message"></div>

<script>
function showToast(message, isError = false) {
    const toast = document.getElementById('toastMessage');
    toast.textContent = message;
    toast.style.backgroundColor = isError ? '#dc2626' : '#10b981';
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}

// Auto-refresh for non-final statuses
const finalStatuses = ['delivered', 'cancelled'];
const currentStatus = '<?php echo $order['status']; ?>';
if (!finalStatuses.includes(currentStatus)) {
    setInterval(async () => {
        try {
            const response = await fetch(window.location.href + '?refresh=1', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            
            const badge = document.getElementById('orderStatusBadge');
            if (badge && data.status !== currentStatus) {
                let icon = 'check-circle';
                if (data.status === 'pending') icon = 'clock';
                else if (data.status === 'delivered') icon = 'home';
                else if (data.status === 'cancelled') icon = 'times-circle';
                badge.innerHTML = `<i class="fas fa-${icon}"></i> ${data.status.replace(/_/g, ' ').charAt(0).toUpperCase() + data.status.slice(1)}`;
                badge.className = `status-badge status-${data.status}`;
                
                // Update tracking steps
                const statusOrder = <?php echo json_encode(array_keys($status_flow)); ?>;
                const newIndex = statusOrder.indexOf(data.status);
                document.querySelectorAll('.tracking-step').forEach((step, idx) => {
                    if (idx <= newIndex) step.classList.add('completed');
                    else step.classList.remove('completed');
                    if (step.getAttribute('data-status') === data.status) {
                        step.classList.add('active');
                    } else {
                        step.classList.remove('active');
                    }
                });
                showToast(`Order status updated to ${data.status.replace(/_/g, ' ')}`);
                
                if (finalStatuses.includes(data.status)) {
                    setTimeout(() => window.location.reload(), 2000);
                }
            }
        } catch (err) { console.error(err); }
    }, 30000);
}
</script>
</body>
</html>