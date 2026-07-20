<?php
// admin/orders/view.php - VIEW ORDER DETAILS
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch order details with customer, business, delivery info
$stmt = mysqli_prepare($conn, "
    SELECT o.*, 
           c.first_name, c.last_name, c.saved_address as customer_address,
           u.email as customer_email, u.phone as customer_phone,
           b.business_name, b.address as business_address, b.phone as business_phone,
           d.status as delivery_status, d.assigned_at, d.picked_up_at, d.delivered_at,
           a.first_name as agent_first, a.last_name as agent_last, a.phone as agent_phone
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN users u ON c.user_id = u.user_id
    JOIN businesses b ON o.business_id = b.business_id
    LEFT JOIN deliveries d ON o.order_id = d.order_id
    LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
    WHERE o.order_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['flash_message'] = "Order not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Fetch order items
$items = [];
$stmt = mysqli_prepare($conn, "
    SELECT oi.*, p.name, p.image_url, p.unit
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$items_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($items_res)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch status history
$history = [];
$stmt = mysqli_prepare($conn, "
    SELECT h.*, u.full_name as changed_by_name
    FROM order_status_history h
    LEFT JOIN users u ON h.created_by = u.user_id
    WHERE h.order_id = ?
    ORDER BY h.created_at DESC
");
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$hist_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($hist_res)) {
    $history[] = $row;
}
mysqli_stmt_close($stmt);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Reuse styles from admin/businesses/view.php */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f1f5f9; }
        .admin-content { margin-left:280px; padding:2rem; min-height:100vh; transition:0.3s; }
        @media (max-width:1024px) { .admin-content { margin-left:0; padding:1.25rem; } }
        .page-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem; }
        .page-header h1 { font-size:1.8rem; font-weight:700; background:linear-gradient(135deg,#1e293b,#2c3e50); -webkit-background-clip:text; background-clip:text; color:transparent; display:flex; align-items:center; gap:0.75rem; }
        .btn-back { background:#64748b; color:white; padding:0.5rem 1rem; border-radius:2rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; }
        .info-card { background:white; border-radius:1.25rem; border:1px solid #eef2f8; overflow:hidden; margin-bottom:1.5rem; }
        .card-header { padding:1rem 1.5rem; background:#fafcff; border-bottom:1px solid #f0f2f5; font-weight:700; display:flex; align-items:center; gap:0.5rem; }
        .card-body { padding:1.25rem 1.5rem; }
        .info-row { display:flex; padding:0.6rem 0; border-bottom:1px solid #f1f5f9; }
        .info-label { width:140px; font-weight:600; color:#64748b; font-size:0.8rem; }
        .info-value { flex:1; color:#1e293b; font-size:0.9rem; }
        .badge { display:inline-block; padding:0.2rem 0.7rem; border-radius:2rem; font-size:0.7rem; font-weight:600; }
        .badge-pending { background:#fef3c7; color:#d97706; }
        .badge-processing { background:#dbeafe; color:#2563eb; }
        .badge-delivered { background:#d1fae5; color:#059669; }
        .badge-cancelled { background:#fee2e2; color:#dc2626; }
        .badge-paid { background:#d1fae5; color:#059669; }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th { text-align:left; padding:0.7rem; background:#f8fafc; font-size:0.7rem; font-weight:700; text-transform:uppercase; color:#475569; }
        .data-table td { padding:0.7rem; border-bottom:1px solid #f1f5f9; font-size:0.85rem; }
        .product-img { width:40px; height:40px; object-fit:cover; border-radius:0.5rem; background:#f1f5f9; }
        @media (max-width:640px) { .admin-content { padding:1rem; } .info-row { flex-direction:column; } .info-label { width:100%; margin-bottom:0.25rem; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Order <?= $order['order_id'] ?> Details</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Orders</a>
    </div>

    <!-- Order & Customer Info -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-info-circle"></i> Order Information</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">Order ID</div><div class="info-value"><?= $order['order_id'] ?></div></div>
            <div class="info-row"><div class="info-label">Order Date</div><div class="info-value"><?= date('F j, Y g:i A', strtotime($order['order_date'])) ?></div></div>
            <div class="info-row"><div class="info-label">Status</div><div class="info-value"><span class="badge badge-<?= $order['status'] === 'pending' ? 'pending' : (in_array($order['status'], ['delivered']) ? 'delivered' : ($order['status'] === 'cancelled' ? 'cancelled' : 'processing')) ?>"><?= ucfirst(str_replace('_', ' ', $order['status'])) ?></span></div></div>
            <div class="info-row"><div class="info-label">Payment Method</div><div class="info-value"><?= ucfirst($order['payment_method']) ?></div></div>
            <div class="info-row"><div class="info-label">Payment Status</div><div class="info-value"><span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'paid' : 'pending' ?>"><?= ucfirst($order['payment_status']) ?></span></div></div>
            <div class="info-row"><div class="info-label">Special Instructions</div><div class="info-value"><?= nl2br(htmlspecialchars($order['special_instructions'] ?: 'None')) ?></div></div>
        </div>
    </div>

    <!-- Customer & Business -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-user"></i> Customer & Business</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">Customer Name</div><div class="info-value"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></div></div>
            <div class="info-row"><div class="info-label">Customer Email</div><div class="info-value"><?= htmlspecialchars($order['customer_email']) ?></div></div>
            <div class="info-row"><div class="info-label">Customer Phone</div><div class="info-value"><?= htmlspecialchars($order['customer_phone']) ?></div></div>
            <div class="info-row"><div class="info-label">Delivery Address</div><div class="info-value"><?= nl2br(htmlspecialchars($order['delivery_address'] ?: $order['customer_address'])) ?></div></div>
            <div class="info-row"><div class="info-label">Business Name</div><div class="info-value"><?= htmlspecialchars($order['business_name']) ?></div></div>
            <div class="info-row"><div class="info-label">Business Address</div><div class="info-value"><?= nl2br(htmlspecialchars($order['business_address'])) ?></div></div>
            <div class="info-row"><div class="info-label">Business Phone</div><div class="info-value"><?= htmlspecialchars($order['business_phone']) ?></div></div>
        </div>
    </div>

    <!-- Delivery Info -->
    <?php if ($order['delivery_status']): ?>
    <div class="info-card">
        <div class="card-header"><i class="fas fa-truck"></i> Delivery Information</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">Delivery Status</div><div class="info-value"><span class="badge badge-<?= $order['delivery_status'] === 'delivered' ? 'delivered' : 'processing' ?>"><?= ucfirst(str_replace('_', ' ', $order['delivery_status'])) ?></span></div></div>
            <div class="info-row"><div class="info-label">Agent Name</div><div class="info-value"><?= htmlspecialchars($order['agent_first'] . ' ' . $order['agent_last']) ?></div></div>
            <div class="info-row"><div class="info-label">Agent Phone</div><div class="info-value"><?= htmlspecialchars($order['agent_phone']) ?></div></div>
            <div class="info-row"><div class="info-label">Assigned At</div><div class="info-value"><?= $order['assigned_at'] ? date('F j, Y g:i A', strtotime($order['assigned_at'])) : 'N/A' ?></div></div>
            <div class="info-row"><div class="info-label">Delivered At</div><div class="info-value"><?= $order['delivered_at'] ? date('F j, Y g:i A', strtotime($order['delivered_at'])) : 'Not yet' ?></div></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Order Items -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-box"></i> Order Items</div>
        <div class="card-body">
            <table class="data-table">
                <thead><tr><th>Image</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): 
                        $img = '../../assets/images/default-product.jpg';
                        if (!empty($item['image_url']) && file_exists('../../' . $item['image_url'])) $img = '../../' . $item['image_url'];
                    ?>
                    <tr>
                        <td><img src="<?= $img ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'"></td>
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong><br><small><?= $item['unit'] ?></small></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>TSh <?= number_format($item['unit_price']) ?></td>
                        <td>TSh <?= number_format($item['subtotal']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f8fafc; font-weight:700;">
                        <td colspan="4" style="text-align:right">Subtotal:</td>
                        <td>TSh <?= number_format($order['total_amount']) ?></td>
                    </tr>
                    <tr style="background:#f8fafc; font-weight:700;">
                        <td colspan="4" style="text-align:right">Delivery Fee:</td>
                        <td>TSh <?= number_format($order['delivery_fee']) ?></td>
                    </tr>
                    <tr style="background:#f8fafc; font-weight:700;">
                        <td colspan="4" style="text-align:right">Grand Total:</td>
                        <td><strong>TSh <?= number_format($order['grand_total']) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Status History -->
    <?php if (!empty($history)): ?>
    <div class="info-card">
        <div class="card-header"><i class="fas fa-history"></i> Status History</div>
        <div class="card-body">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Old Status</th><th>New Status</th><th>Notes</th><th>Changed By</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($h['created_at'])) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $h['old_status'])) ?></td>
                        <td><?= ucfirst(str_replace('_', ' ', $h['new_status'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($h['notes'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($h['changed_by_name'] ?? 'System') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>