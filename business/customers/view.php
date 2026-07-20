<?php
// business/customers/view.php - PROFESSIONAL CUSTOMER DETAILS (FIXED)
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id == 0) {
    header("Location: index.php");
    exit();
}

// ============================================================
// GET CUSTOMER DETAILS
// ============================================================
$sql = "SELECT c.*, u.email, u.phone, u.created_at as registered_date
        FROM customers c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.customer_id = '$customer_id'";
$result = mysqli_query($conn, $sql);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    $_SESSION['flash_message'] = "Customer not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// ============================================================
// GET CUSTOMER ORDERS
// ============================================================
$orders_sql = "SELECT o.*, 
                      (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as total_items
               FROM orders o
               WHERE o.customer_id = '$customer_id' AND o.business_id = '$business_id'
               ORDER BY o.order_date DESC";
$orders_result = mysqli_query($conn, $orders_sql);
$orders = [];
while($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_orders = count($orders);
$total_spent = array_sum(array_column($orders, 'grand_total'));
$avg_order = $total_orders > 0 ? $total_spent / $total_orders : 0;

// ============================================================
// ORDER STATUS SUMMARY - MATCH WITH ORDER HISTORY
// ============================================================
// First, get all unique statuses from the orders
$status_counts = [];
$status_labels = [];

// Define all possible statuses with their labels and colors
$all_statuses = [
    'pending' => ['label' => 'Pending', 'icon' => 'fa-clock', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'confirmed' => ['label' => 'Confirmed', 'icon' => 'fa-check-circle', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'accepted' => ['label' => 'Accepted', 'icon' => 'fa-check-circle', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'processing' => ['label' => 'Processing', 'icon' => 'fa-spinner', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'shipped' => ['label' => 'Shipped', 'icon' => 'fa-truck', 'color' => '#ec4899', 'bg' => '#fce7f3'],
    'in_transit' => ['label' => 'In Transit', 'icon' => 'fa-truck', 'color' => '#ec4899', 'bg' => '#fce7f3'],
    'delivered' => ['label' => 'Delivered', 'icon' => 'fa-check', 'color' => '#10b981', 'bg' => '#d1fae5'],
    'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-times', 'color' => '#ef4444', 'bg' => '#fee2e2']
];

// Initialize counts for all statuses
foreach ($all_statuses as $key => $info) {
    $status_counts[$key] = 0;
}

// Count actual orders by status
foreach ($orders as $order) {
    $status = $order['status'];
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}

// Only show statuses that have counts > 0
$active_statuses = array_filter($status_counts, function($count) {
    return $count > 0;
});

// Last order date
$last_order_date = !empty($orders) ? $orders[0]['order_date'] : null;

// Profile image
$profile_image = 'https://ui-avatars.com/api/?name=' . urlencode($customer['first_name'] . '+' . $customer['last_name']) . '&background=e67e22&color=fff&size=120&bold=true';

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Customer Details - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
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
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        /* Customer Header with Profile Image */
        .customer-header {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .customer-header:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .customer-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            border: 4px solid #e67e22;
            box-shadow: 0 4px 15px rgba(230,126,34,0.25);
            background: #f8fafc;
        }
        .customer-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .customer-avatar .placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            font-size: 2.8rem;
            font-weight: 700;
        }
        .customer-info {
            flex: 1;
        }
        .customer-info h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.2rem;
        }
        .customer-info .customer-badge {
            display: inline-block;
            background: #e67e22;
            color: white;
            padding: 0.15rem 0.7rem;
            border-radius: 1rem;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 0.5rem;
            vertical-align: middle;
        }
        .customer-info p {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .customer-info p i { color: #e67e22; width: 1.2rem; }
        .customer-since {
            background: #f8fafc;
            border-radius: 2rem;
            padding: 0.25rem 0.8rem;
            font-size: 0.7rem;
            color: #64748b;
            border: 1px solid #e2e8f0;
            margin-top: 0.3rem;
            display: inline-block;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 1.25rem; }
        
        /* Info Rows */
        .info-row {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: #0f172a;
            font-weight: 500;
            word-break: break-word;
        }
        
        /* Order Status Summary */
        .status-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 0.75rem;
        }
        .status-item {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem 0.5rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .status-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .status-item .status-icon {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
        }
        .status-item .status-count {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
        }
        .status-item .status-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .status-item .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.3rem;
        }
        .status-item.active {
            border-color: #e67e22;
            background: #fdf2e9;
        }
        .status-item.active .status-label {
            color: #e67e22;
        }
        .status-item .status-percent {
            font-size: 0.55rem;
            color: #94a3b8;
            margin-top: 0.2rem;
        }
        
        /* Status Badges for Table - Match with order statuses */
        .order-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .order-status.pending { background: #fef3c7; color: #d97706; }
        .order-status.confirmed { background: #dbeafe; color: #2563eb; }
        .order-status.accepted { background: #dbeafe; color: #2563eb; }
        .order-status.processing { background: #ede9fe; color: #7c3aed; }
        .order-status.shipped { background: #fce7f3; color: #be185d; }
        .order-status.in_transit { background: #fce7f3; color: #be185d; }
        .order-status.delivered { background: #d1fae5; color: #059669; }
        .order-status.cancelled { background: #fee2e2; color: #dc2626; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            background: #f8fafc;
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: #fffbeb;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.6rem;
            border-radius: 0.4rem;
            font-weight: 600;
            font-size: 0.65rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-sm {
            background: #3498db;
            color: white;
        }
        .btn-sm:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }
        .empty-state p { font-size: 0.85rem; }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .customer-header { flex-direction: column; text-align: center; }
            .customer-info p { justify-content: center; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.2rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 0.4rem 0.5rem; }
            .status-summary { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .status-summary { grid-template-columns: 1fr 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-circle"></i> Customer Details</h1>
            <p>Complete profile and order history for this customer</p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Customers
        </a>
    </div>

    <!-- Customer Header with Profile Image -->
    <div class="customer-header">
        <div class="customer-avatar">
            <?php if (!empty($customer['profile_image']) && file_exists('../../' . $customer['profile_image'])): ?>
                <img src="<?php echo '../../' . $customer['profile_image']; ?>" alt="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>">
            <?php else: ?>
                <div class="placeholder">
                    <?php echo strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'] ?? '', 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="customer-info">
            <h2>
                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                <span class="customer-badge">Customer</span>
            </h2>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?></p>
            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?></p>
            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($customer['city'] ?: 'Not specified'); ?></p>
            <span class="customer-since">
                <i class="fas fa-calendar-alt"></i> Customer since <?php echo date('F Y', strtotime($customer['registered_date'])); ?>
            </span>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_spent); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calculator"></i></div>
            <div class="stat-number">TSh <?php echo number_format($avg_order); ?></div>
            <div class="stat-label">Average Order</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-number"><?php echo $last_order_date ? date('M d, Y', strtotime($last_order_date)) : 'Never'; ?></div>
            <div class="stat-label">Last Order</div>
        </div>
    </div>

    <!-- Address Information - Full -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-address-card"></i> Address Information</h3>
            <span class="badge-count"><i class="fas fa-check-circle" style="color:#10b981;"></i> Verified</span>
        </div>
        <div class="card-body">
            <div class="info-row">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone Number</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['phone']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email Address</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">City</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['city'] ?: 'Not specified'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Saved Address</span>
                <span class="info-value" style="background:#f8fafc; padding:0.3rem 0.6rem; border-radius:0.4rem; border-left:3px solid #e67e22;">
                    <?php echo nl2br(htmlspecialchars($customer['saved_address'] ?: 'No address saved')); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Order Status Summary - Only show statuses that exist in orders -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Order Status Summary</h3>
            <span class="badge-count"><?php echo $total_orders; ?> total orders</span>
        </div>
        <div class="card-body">
            <?php if ($total_orders > 0): ?>
            <div class="status-summary">
                <?php foreach ($active_statuses as $status => $count): 
                    $info = $all_statuses[$status] ?? ['label' => ucfirst($status), 'icon' => 'fa-circle', 'color' => '#64748b'];
                    $percent = $total_orders > 0 ? round(($count / $total_orders) * 100) : 0;
                ?>
                <div class="status-item active">
                    <div class="status-icon" style="color: <?php echo $info['color']; ?>;">
                        <i class="fas <?php echo $info['icon']; ?>"></i>
                    </div>
                    <div class="status-count"><?php echo $count; ?></div>
                    <div class="status-label">
                        <span class="status-dot <?php echo $status; ?>"></span>
                        <?php echo $info['label']; ?>
                    </div>
                    <div class="status-percent"><?php echo $percent; ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Orders Yet</h3>
                <p>This customer hasn't placed any orders yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order History -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shopping-cart"></i> Order History</h3>
            <span class="badge-count"><?php echo $total_orders; ?> orders</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding:3rem 1rem;">
                                <div style="color:#94a3b8;">
                                    <i class="fas fa-inbox" style="font-size:2rem; display:block; margin-bottom:0.5rem; opacity:0.3;"></i>
                                    <h3 style="font-size:1rem; color:#64748b;">No Orders Found</h3>
                                    <p style="font-size:0.85rem;">This customer hasn't placed any orders yet.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo $order['order_id']; ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td><?php echo $order['total_items']; ?> items</td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($order['grand_total']); ?></strong></td>
                            <td>
                                <?php 
                                $status = $order['status'];
                                $status_label = $all_statuses[$status]['label'] ?? ucfirst($status);
                                ?>
                                <span class="order-status <?php echo $status; ?>"><?php echo $status_label; ?></span>
                            </td>
                            <td>
                                <a href="../orders/details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../customers/view.php' || 
            links[i].getAttribute('href') === 'view.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>