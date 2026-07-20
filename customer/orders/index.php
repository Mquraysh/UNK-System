<?php
// customer/orders/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id using prepared statement
$stmt = mysqli_prepare($conn, "SELECT customer_id FROM customers WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$customer_id = $customer['customer_id'];

// Get status filter from URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query for orders with delivery info
$sql = "SELECT o.*, b.business_name,
               d.delivery_id, d.status as delivery_status, d.rating as delivery_rating,
               da.agent_id, da.first_name as agent_first_name, da.last_name as agent_last_name
        FROM orders o
        JOIN businesses b ON o.business_id = b.business_id
        LEFT JOIN deliveries d ON o.order_id = d.order_id
        LEFT JOIN delivery_agents da ON d.agent_id = da.agent_id
        WHERE o.customer_id = ?";
$params = [$customer_id];
$types = "i";

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$sql .= " ORDER BY o.order_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($order = mysqli_fetch_assoc($orders_result)) {
    // Get item count
    $item_sql = "SELECT SUM(quantity) as total_items FROM order_items WHERE order_id = ?";
    $item_stmt = mysqli_prepare($conn, $item_sql);
    mysqli_stmt_bind_param($item_stmt, 'i', $order['order_id']);
    mysqli_stmt_execute($item_stmt);
    $item_data = mysqli_fetch_assoc(mysqli_stmt_get_result($item_stmt));
    mysqli_stmt_close($item_stmt);
    $order['item_count'] = (int)($item_data['total_items'] ?? 0);
    
    // Check if delivery has been rated
    $order['is_rated'] = false;
    if (!empty($order['delivery_id'])) {
        $check_rated = mysqli_query($conn, "SELECT rating_id FROM delivery_ratings WHERE delivery_id = {$order['delivery_id']} AND customer_id = $customer_id");
        $order['is_rated'] = mysqli_num_rows($check_rated) > 0;
    }
    
    $orders[] = $order;
}
mysqli_stmt_close($stmt);

// Get statistics for all statuses
$stats_sql = "SELECT status, COUNT(*) as cnt FROM orders WHERE customer_id = ? GROUP BY status";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, 'i', $customer_id);
mysqli_stmt_execute($stats_stmt);
$stats_res = mysqli_stmt_get_result($stats_stmt);
$stats = [
    'total' => 0,
    'pending' => 0, 'accepted' => 0, 'confirmed' => 0,
    'preparing' => 0, 'ready' => 0, 'picked_up' => 0,
    'in_transit' => 0, 'delivered' => 0, 'cancelled' => 0
];
while ($row = mysqli_fetch_assoc($stats_res)) {
    $status = $row['status'];
    if (isset($stats[$status])) $stats[$status] = (int)$row['cnt'];
    $stats['total'] += (int)$row['cnt'];
}
mysqli_stmt_close($stats_stmt);

// Flash message from session
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Orders - UNK System</title>
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
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 24px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            color: #e67e22;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
        }
        .btn-shop {
            background: #e67e22;
            color: white;
            padding: 10px 22px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-shop:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
            font-size: 14px;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        
        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 14px 10px;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            border: 2px solid #eef2f8;
            display: block;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            border-color: #e67e22;
        }
        .stat-card h3 {
            font-size: 22px;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 2px;
        }
        .stat-card p {
            color: #64748b;
            font-size: 11px;
            margin: 0;
            font-weight: 500;
        }
        .stat-card.active {
            border-color: #e67e22;
            background: #fffaf5;
        }
        
        /* Quick Filters */
        .quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .quick-filter {
            padding: 8px 18px;
            background: white;
            border-radius: 30px;
            text-decoration: none;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .quick-filter:hover {
            background: #f1f5f9;
        }
        .quick-filter.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .quick-filter .badge {
            background: #eef2f8;
            color: #475569;
            padding: 1px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }
        .quick-filter.active .badge {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        
        /* Card */
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1e293b;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .count {
            background: #eef2f8;
            padding: 2px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #eef2f6;
            background: #fafcff;
        }
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f6;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #fafcff;
        }
        
        .order-id {
            font-weight: 700;
            color: #e67e22;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-accepted { background: #dbeafe; color: #2563eb; }
        .status-confirmed { background: #dbeafe; color: #2563eb; }
        .status-preparing { background: #e0e7ff; color: #4f46e5; }
        .status-ready { background: #c7d2fe; color: #4338ca; }
        .status-picked_up { background: #a7f3d0; color: #059669; }
        .status-in_transit { background: #fde68a; color: #b45309; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        /* Buttons */
        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-view { background: #3498db; color: white; }
        .btn-view:hover { background: #2980b9; }
        .btn-track { background: #27ae60; color: white; }
        .btn-track:hover { background: #219a52; }
        .btn-cancel { background: #e74c3c; color: white; }
        .btn-cancel:hover { background: #c0392b; }
        .btn-delete { background: #7f8c8d; color: white; }
        .btn-delete:hover { background: #6c757d; }
        .btn-rate { background: #f59e0b; color: white; }
        .btn-rate:hover { background: #d97706; }
        .btn-rated { background: #10b981; color: white; cursor: default; }
        .btn-rated:hover { background: #10b981; }
        .btn-invoice { background: #8b5cf6; color: white; }
        .btn-invoice:hover { background: #7c3aed; }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 56px;
            margin-bottom: 16px;
            opacity: 0.4;
        }
        .empty-state h4 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 14px;
        }
        .empty-state a {
            color: #e67e22;
            text-decoration: none;
            font-weight: 600;
        }
        .empty-state a:hover {
            text-decoration: underline;
        }
        
        /* Toast */
        .toast-message {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #1e293b;
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); }
            .stat-card h3 { font-size: 18px; }
            .stat-card p { font-size: 9px; }
            .data-table th, .data-table td { padding: 10px 12px; font-size: 12px; }
            .action-buttons { flex-direction: column; gap: 4px; }
            .btn-sm { justify-content: center; }
            .quick-filters { gap: 5px; }
            .quick-filter { font-size: 11px; padding: 6px 12px; }
            .page-header h1 { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: repeat(5, 1fr); }
            .stat-card { padding: 10px 6px; }
            .stat-card h3 { font-size: 16px; }
            .customer-content { padding: 12px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-box"></i> My Orders</h1>
            <p><i class="fas fa-clock"></i> Track and manage all your orders</p>
        </div>
        <a href="../products/index.php" class="btn-shop"><i class="fas fa-store"></i> Continue Shopping</a>
    </div>
    
    <!-- Flash Message -->
    <?php if(!empty($flash_message)): ?>
    <div class="alert alert-<?php echo $flash_type; ?>">
        <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($flash_message); ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <a href="?status=" class="stat-card <?php echo empty($status_filter) ? 'active' : ''; ?>">
            <h3><?php echo $stats['total']; ?></h3>
            <p>All Orders</p>
        </a>
        <a href="?status=pending" class="stat-card <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            <h3><?php echo $stats['pending']; ?></h3>
            <p>Pending</p>
        </a>
        <a href="?status=accepted" class="stat-card <?php echo $status_filter == 'accepted' ? 'active' : ''; ?>">
            <h3><?php echo $stats['accepted']; ?></h3>
            <p>Accepted</p>
        </a>
        <a href="?status=confirmed" class="stat-card <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
            <h3><?php echo $stats['confirmed']; ?></h3>
            <p>Confirmed</p>
        </a>
        <a href="?status=preparing" class="stat-card <?php echo $status_filter == 'preparing' ? 'active' : ''; ?>">
            <h3><?php echo $stats['preparing']; ?></h3>
            <p>Preparing</p>
        </a>
        <a href="?status=ready" class="stat-card <?php echo $status_filter == 'ready' ? 'active' : ''; ?>">
            <h3><?php echo $stats['ready']; ?></h3>
            <p>Ready</p>
        </a>
        <a href="?status=picked_up" class="stat-card <?php echo $status_filter == 'picked_up' ? 'active' : ''; ?>">
            <h3><?php echo $stats['picked_up']; ?></h3>
            <p>Picked Up</p>
        </a>
        <a href="?status=in_transit" class="stat-card <?php echo $status_filter == 'in_transit' ? 'active' : ''; ?>">
            <h3><?php echo $stats['in_transit']; ?></h3>
            <p>In Transit</p>
        </a>
        <a href="?status=delivered" class="stat-card <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
            <h3><?php echo $stats['delivered']; ?></h3>
            <p>Delivered</p>
        </a>
        <a href="?status=cancelled" class="stat-card <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
            <h3><?php echo $stats['cancelled']; ?></h3>
            <p>Cancelled</p>
        </a>
    </div>
    
    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="?status=" class="quick-filter <?php echo empty($status_filter) ? 'active' : ''; ?>">
            All <span class="badge"><?php echo $stats['total']; ?></span>
        </a>
        <a href="?status=pending" class="quick-filter <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pending <span class="badge"><?php echo $stats['pending']; ?></span>
        </a>
        <a href="?status=accepted" class="quick-filter <?php echo $status_filter == 'accepted' ? 'active' : ''; ?>">
            <i class="fas fa-check"></i> Accepted <span class="badge"><?php echo $stats['accepted']; ?></span>
        </a>
        <a href="?status=confirmed" class="quick-filter <?php echo $status_filter == 'confirmed' ? 'active' : ''; ?>">
            <i class="fas fa-check-double"></i> Confirmed <span class="badge"><?php echo $stats['confirmed']; ?></span>
        </a>
        <a href="?status=preparing" class="quick-filter <?php echo $status_filter == 'preparing' ? 'active' : ''; ?>">
            <i class="fas fa-cogs"></i> Preparing <span class="badge"><?php echo $stats['preparing']; ?></span>
        </a>
        <a href="?status=ready" class="quick-filter <?php echo $status_filter == 'ready' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i> Ready <span class="badge"><?php echo $stats['ready']; ?></span>
        </a>
        <a href="?status=picked_up" class="quick-filter <?php echo $status_filter == 'picked_up' ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding"></i> Picked Up <span class="badge"><?php echo $stats['picked_up']; ?></span>
        </a>
        <a href="?status=in_transit" class="quick-filter <?php echo $status_filter == 'in_transit' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i> In Transit <span class="badge"><?php echo $stats['in_transit']; ?></span>
        </a>
        <a href="?status=delivered" class="quick-filter <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Delivered <span class="badge"><?php echo $stats['delivered']; ?></span>
        </a>
        <a href="?status=cancelled" class="quick-filter <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
            <i class="fas fa-times-circle"></i> Cancelled <span class="badge"><?php echo $stats['cancelled']; ?></span>
        </a>
    </div>
    
    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Order History</h3>
            <span class="count"><?php echo count($orders); ?> orders</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Business</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No orders found</h4>
                            <p>You haven't placed any orders yet.</p>
                            <a href="../products/index.php">Start Shopping →</a>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): 
                            $statusClass = $order['status'];
                            $statusIcons = [
                                'pending' => 'clock',
                                'accepted' => 'check',
                                'confirmed' => 'check-double',
                                'preparing' => 'cogs',
                                'ready' => 'box-open',
                                'picked_up' => 'hand-holding',
                                'in_transit' => 'truck',
                                'delivered' => 'check-circle',
                                'cancelled' => 'times-circle'
                            ];
                            $statusIcon = $statusIcons[$order['status']] ?? 'circle';
                            
                            $displayStatus = str_replace('_', ' ', $order['status']);
                            $displayStatus = ucfirst($displayStatus);
                        ?>
                        <tr>
                            <td class="order-id"><?php echo $order['order_id']; ?></td>
                            <td><?php echo htmlspecialchars($order['business_name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td><?php echo $order['item_count']; ?> items</td>
                            <td><strong>TSh <?php echo number_format($order['grand_total'], 0, '.', ','); ?></strong></td>
                            <td>
                                <span class="status-badge status-<?php echo $statusClass; ?>">
                                    <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                    <?php echo $displayStatus; ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <!-- View Details -->
                                <a href="details.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                
                                <!-- Track - for in progress orders -->
                                <?php if ($order['status'] != 'pending' && $order['status'] != 'delivered' && $order['status'] != 'cancelled'): ?>
                                <a href="track.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-track">
                                    <i class="fas fa-truck"></i> Track
                                </a>
                                <?php endif; ?>
                                
                                <!-- Rate Delivery - for delivered orders -->
                                <?php if ($order['status'] == 'delivered' && !empty($order['delivery_id'])): ?>
                                    <?php if (!$order['is_rated']): ?>
                                    <a href="../rate/rate-delivery.php?id=<?php echo $order['delivery_id']; ?>" class="btn-sm btn-rate">
                                        <i class="fas fa-star"></i> Rate
                                    </a>
                                    <?php else: ?>
                                    <span class="btn-sm btn-rated">
                                        <i class="fas fa-check-circle"></i> Rated
                                    </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Invoice - for delivered orders -->
                                <?php if ($order['status'] == 'delivered'): ?>
                                <a href="invoice.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-invoice" target="_blank">
                                    <i class="fas fa-print"></i> Invoice
                                </a>
                                <?php endif; ?>
                                
                                <!-- Cancel - for pending orders -->
                                <?php if ($order['status'] == 'pending'): ?>
                                <a href="cancel.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-cancel" onclick="return confirm('Cancel this order?')">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <?php endif; ?>
                                
                                <!-- Delete - for pending or cancelled orders -->
                                <?php if ($order['status'] == 'pending' || $order['status'] == 'cancelled'): ?>
                                <a href="delete.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this order? This action cannot be undone.')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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

// Auto-hide flash message
const flashDiv = document.querySelector('.alert');
if (flashDiv) {
    setTimeout(() => { flashDiv.style.display = 'none'; }, 5000);
}
</script>
</body>
</html>