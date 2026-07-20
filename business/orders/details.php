<?php
// business/orders/details.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch business using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get order details with customer info
$stmt = mysqli_prepare($conn, "
    SELECT o.*, c.first_name, c.last_name, c.saved_address, u.email, u.phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN users u ON c.user_id = u.user_id
    WHERE o.order_id = ? AND o.business_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $business_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['flash_message'] = "Order not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Get order items
$items = [];
$stmt = mysqli_prepare($conn, "
    SELECT oi.*, p.name, p.image_url, p.unit
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($items_result)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// Get delivery info (including agent details)
$delivery = null;
$stmt = mysqli_prepare($conn, "
    SELECT d.*, a.first_name as agent_first, a.last_name as agent_last, a.phone as agent_phone, a.vehicle_type
    FROM deliveries d
    LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
    WHERE d.order_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$delivery_result = mysqli_stmt_get_result($stmt);
$delivery = mysqli_fetch_assoc($delivery_result);
mysqli_stmt_close($stmt);

// Status badge mapping
$status_labels = [
    'pending'    => 'Pending',
    'accepted'   => 'Accepted',
    'confirmed'  => 'Confirmed',
    'preparing'  => 'Preparing',
    'ready'      => 'Ready',
    'picked_up'  => 'Picked Up',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled'
];
$badge_classes = [
    'pending'    => 'badge-pending',
    'accepted'   => 'badge-accepted',
    'confirmed'  => 'badge-confirmed',
    'preparing'  => 'badge-preparing',
    'ready'      => 'badge-ready',
    'picked_up'  => 'badge-picked_up',
    'delivered'  => 'badge-delivered',
    'cancelled'  => 'badge-cancelled'
];
$status_icons = [
    'pending'    => 'fa-clock',
    'accepted'   => 'fa-check-circle',
    'confirmed'  => 'fa-check-double',
    'preparing'  => 'fa-cogs',
    'ready'      => 'fa-box-open',
    'picked_up'  => 'fa-truck',
    'delivered'  => 'fa-home',
    'cancelled'  => 'fa-times-circle'
];

// image path function
function getProductImage($imagePath) {
    // Default image (from project root)
    $default = '../../assets/images/default-product.jpg';
    
    if (empty($imagePath)) {
        return $default;
    }
    
    // If it's already an absolute URL (http:// or https://)
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    
    // If it's an absolute path starting with '/'
    if ($imagePath[0] === '/') {
        // Remove leading slash and prefix with '../..' for root-relative from this subfolder
        return '../..' . $imagePath;
    }
    
    // The stored path might be like 'assets/uploads/products/xyz.jpg'
    return '../../' . ltrim($imagePath, './');
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Order Details | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; background: none; }
        .btn-back, .btn-update, .btn-invoice {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-back { background: #2c3e50; color: white; }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        .btn-update { background: #e67e22; color: white; }
        .btn-update:hover { background: #d35400; transform: translateY(-2px); }
        .btn-invoice { background: #27ae60; color: white; }
        .btn-invoice:hover { background: #1e8449; transform: translateY(-2px); }

        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .info-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.25rem 1.5rem;
            border: 1px solid #eef2f8;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: box-shadow 0.2s;
        }
        .info-card:hover { box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .info-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e67e22;
            display: inline-block;
        }
        .info-card h3 i { color: #e67e22; margin-right: 0.5rem; }
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .info-label {
            width: 130px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.85rem;
            word-break: break-word;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-accepted { background: #ddd6fe; color: #5b21b6; }
        .badge-confirmed { background: #a7f3d0; color: #065f46; }
        .badge-preparing { background: #bfdbfe; color: #1e40af; }
        .badge-ready { background: #fed7aa; color: #9a3412; }
        .badge-picked_up { background: #fbcfe8; color: #9d174d; }
        .badge-delivered { background: #d1fae5; color: #047857; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }

        .table-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
        }
        .table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 0.8rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #edf2f7;
        }
        .data-table td {
            padding: 0.8rem 1rem;
            font-size: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .product-img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 10px;
            background: #f1f5f9;
        }
        .total-row {
            background: #f8fafc;
            font-weight: 700;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }
        @media (max-width: 768px) {
            .order-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.25rem; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Order Details <?php echo $order['order_id']; ?></h1>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="update-status.php?id=<?php echo $order_id; ?>" class="btn-update"><i class="fas fa-edit"></i> Update Status</a>
            <?php if ($order['status'] === 'delivered'): ?>
                <a href="invoice.php?id=<?php echo $order_id; ?>" class="btn-invoice" target="_blank"><i class="fas fa-print"></i> Invoice</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="order-grid">
        <!-- Order Information -->
        <div class="info-card">
            <h3><i class="fas fa-info-circle"></i> Order Information</h3>
            <div class="info-row"><div class="info-label">Order ID:</div><div class="info-value"><?php echo $order['order_id']; ?></div></div>
            <div class="info-row"><div class="info-label">Order Date:</div><div class="info-value"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></div></div>
            <div class="info-row"><div class="info-label">Status:</div><div class="info-value"><span class="badge <?php echo $badge_classes[$order['status']] ?? 'badge-pending'; ?>"><i class="fas <?php echo $status_icons[$order['status']] ?? 'fa-info-circle'; ?>"></i> <?php echo $status_labels[$order['status']] ?? ucfirst($order['status']); ?></span></div></div>
            <div class="info-row"><div class="info-label">Payment Method:</div><div class="info-value"><?php echo ucfirst($order['payment_method']); ?></div></div>
            <div class="info-row"><div class="info-label">Payment Status:</div><div class="info-value"><?php echo ucfirst($order['payment_status']); ?></div></div>
            <div class="info-row"><div class="info-label">Special Instructions:</div><div class="info-value"><?php echo nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')); ?></div></div>
        </div>

        <!-- Customer Information -->
        <div class="info-card">
            <h3><i class="fas fa-user"></i> Customer Information</h3>
            <div class="info-row"><div class="info-label">Name:</div><div class="info-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div></div>
            <div class="info-row"><div class="info-label">Email:</div><div class="info-value"><?php echo htmlspecialchars($order['email']); ?></div></div>
            <div class="info-row"><div class="info-label">Phone:</div><div class="info-value"><?php echo htmlspecialchars($order['phone']); ?></div></div>
            <div class="info-row"><div class="info-label">Delivery Address:</div><div class="info-value"><?php echo nl2br(htmlspecialchars($order['delivery_address'] ?: $order['saved_address'])); ?></div></div>
        </div>
    </div>

    <!-- Delivery Agent Information (if assigned) -->
    <?php if ($delivery && !empty($delivery['agent_id'])): ?>
    <div class="info-card" style="margin-bottom: 1.5rem;">
        <h3><i class="fas fa-truck"></i> Delivery Information</h3>
        <div class="info-row"><div class="info-label">Delivery Agent:</div><div class="info-value"><?php echo htmlspecialchars($delivery['agent_first'] . ' ' . $delivery['agent_last']); ?></div></div>
        <div class="info-row"><div class="info-label">Agent Phone:</div><div class="info-value"><?php echo htmlspecialchars($delivery['agent_phone']); ?></div></div>
        <div class="info-row"><div class="info-label">Vehicle:</div><div class="info-value"><?php echo htmlspecialchars($delivery['vehicle_type']); ?></div></div>
        <div class="info-row"><div class="info-label">Delivery Status:</div><div class="info-value"><span class="badge <?php echo $badge_classes[$delivery['status']] ?? 'badge-pending'; ?>"><i class="fas <?php echo $status_icons[$delivery['status']] ?? 'fa-truck'; ?>"></i> <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?></span></div></div>
        <?php if ($delivery['assigned_at']): ?>
        <div class="info-row"><div class="info-label">Assigned At:</div><div class="info-value"><?php echo date('F j, Y g:i A', strtotime($delivery['assigned_at'])); ?></div></div>
        <?php endif; ?>
        <?php if ($delivery['delivered_at']): ?>
        <div class="info-row"><div class="info-label">Delivered At:</div><div class="info-value"><?php echo date('F j, Y g:i A', strtotime($delivery['delivered_at'])); ?></div></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Order Items -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-box"></i> Order Items</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr><th>Image</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><img src="<?php echo getProductImage($item['image_url'] ?? ''); ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'" alt="<?php echo htmlspecialchars($item['name']); ?>"></td>
                        <td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small><?php echo $item['unit']; ?></small></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>TSh <?php echo number_format($item['unit_price']); ?></td>
                        <td>TSh <?php echo number_format($item['subtotal']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Subtotal:</strong></td><td><strong>TSh <?php echo number_format($order['total_amount']); ?></strong></td></tr>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Delivery Fee:</strong></td><td><strong>TSh <?php echo number_format($order['delivery_fee']); ?></strong></td></tr>
                    <tr class="total-row"><td colspan="4" style="text-align: right;"><strong>Grand Total:</strong></td><td><strong style="color: #e67e22;">TSh <?php echo number_format($order['grand_total']); ?></strong></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>