<?php
// business/orders/index.php 
require_once '../../config/database.php';
session_start();

// Authentication & authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business') {
    $_SESSION['flash_message'] = 'Please login as business owner';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch business details
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    $_SESSION['flash_message'] = 'Business profile not found. Please complete registration.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ../register.php');
    exit();
}

$business_id = (int)$business['business_id'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Define all possible statuses
$all_statuses = ['pending', 'accepted', 'confirmed', 'preparing', 'ready', 'picked_up', 'delivered', 'cancelled'];
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

$status_colors = [
    'pending'    => '#d97706',
    'accepted'   => '#6d28d9',
    'confirmed'  => '#047857',
    'preparing'  => '#1e40af',
    'ready'      => '#c2410c',
    'picked_up'  => '#be185d',
    'delivered'  => '#059669',
    'cancelled'  => '#dc2626'
];

// Build main query
$sql = "SELECT o.*, c.first_name, c.last_name, c.saved_address, u.phone, u.email,
               COUNT(oi.order_item_id) as items_count
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.business_id = ?";
$params = [$business_id];
$types = "i";

if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($search)) {
    $sql .= " AND (o.order_id LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}
if (!empty($date_from)) {
    $sql .= " AND DATE(o.order_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if (!empty($date_to)) {
    $sql .= " AND DATE(o.order_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}
$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$orders_result = mysqli_stmt_get_result($stmt);
$orders = [];
while ($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics counts
$counts = array_fill_keys($all_statuses, 0);
$total_revenue = 0;
$total_orders = 0;

$total_sql = "SELECT status, COUNT(*) as cnt, SUM(grand_total) as revenue FROM orders WHERE business_id = ? GROUP BY status";
$stmt_tot = mysqli_prepare($conn, $total_sql);
mysqli_stmt_bind_param($stmt_tot, 'i', $business_id);
mysqli_stmt_execute($stmt_tot);
$totals_result = mysqli_stmt_get_result($stmt_tot);
while ($row = mysqli_fetch_assoc($totals_result)) {
    $status = $row['status'];
    if (array_key_exists($status, $counts)) {
        $counts[$status] = (int)$row['cnt'];
    }
    if ($status === 'delivered') {
        $total_revenue += (float)$row['revenue'];
    }
}
mysqli_stmt_close($stmt_tot);
$total_orders = array_sum($counts);

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Orders Management | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .page-header h1 i {
            color: #e67e22;
            font-size: 1.8rem;
        }
        
        .page-header p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        /* Status Cards Row - Like Stats Grid from notifications */
        .status-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.9rem;
            margin-bottom: 1.5rem;
        }
        
        .status-card {
            background: white;
            border-radius: 1rem;
            padding: 0.9rem 0.5rem;
            text-align: center;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            display: block;
        }
        
        .status-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        
        .status-card.active {
            border-color: #e67e22;
            background: #fdf2e9;
        }
        
        .status-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
        }
        
        .status-icon i {
            font-size: 0.9rem;
        }
        
        .status-count {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.2rem;
        }
        
        .status-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        /* Quick Filters */
        .quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }
        
        .quick-filter {
            padding: 0.4rem 1rem;
            background: white;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            color: #334155;
            border: 1px solid #e2e8f0;
            transition: 0.2s;
        }
        
        .quick-filter:hover,
        .quick-filter.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        
        .filter-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-family: inherit;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .btn-filter {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.8rem;
        }
        
        .btn-reset:hover {
            background: #64748b;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .table-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .table-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
        }
        
        .table-header h3 i {
            color: #e67e22;
            margin-right: 0.5rem;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #475569;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .data-table td {
            padding: 0.85rem 1rem;
            font-size: 0.8rem;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }
        
        .data-table tr:hover td {
            background: #fffaf5;
        }
        
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-accepted { background: #ddd6fe; color: #5b21b6; }
        .badge-confirmed { background: #a7f3d0; color: #065f46; }
        .badge-preparing { background: #bfdbfe; color: #1e40af; }
        .badge-ready { background: #fed7aa; color: #9a3412; }
        .badge-picked_up { background: #fbcfe8; color: #9d174d; }
        .badge-delivered { background: #d1fae5; color: #047857; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }
        
        .order-id {
            font-weight: 700;
            color: #e67e22;
        }
        
        .amount {
            font-weight: 600;
            color: #0f172a;
        }
        
        /* Action Buttons */
        .btn-sm {
            padding: 0.3rem 0.7rem;
            border-radius: 0.4rem;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            margin: 0 0.2rem;
            transition: 0.2s;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-update {
            background: #e67e22;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-view:hover,
        .btn-update:hover,
        .btn-delete:hover {
            opacity: 0.85;
            transform: translateY(-1px);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .status-cards {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .business-content {
                margin-left: 0;
                padding: 1.25rem;
            }
            .status-cards {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .status-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters-form {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .status-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Orders Management</h1>
        <p>View and manage all customer orders for your business</p>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Status Cards Row -->
    <div class="status-cards">
        <a href="index.php" class="status-card <?php echo empty($status_filter) ? 'active' : ''; ?>">
            <div class="status-icon" style="background: rgba(230,126,34,0.1);">
                <i class="fas fa-list" style="color: #e67e22;"></i>
            </div>
            <div class="status-count"><?php echo $total_orders; ?></div>
            <div class="status-label">All Orders</div>
        </a>
        
        <?php foreach ($all_statuses as $status): ?>
            <a href="index.php?status=<?php echo $status; ?>" class="status-card <?php echo ($status_filter === $status) ? 'active' : ''; ?>">
                <div class="status-icon" style="background: <?php echo $status_colors[$status]; ?>20;">
                    <i class="fas <?php echo $status_icons[$status]; ?>" style="color: <?php echo $status_colors[$status]; ?>;"></i>
                </div>
                <div class="status-count"><?php echo $counts[$status]; ?></div>
                <div class="status-label"><?php echo $status_labels[$status]; ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Quick Filter Tabs -->
    <div class="quick-filters">
        <a href="index.php" class="quick-filter <?php echo empty($status_filter) ? 'active' : ''; ?>">All Orders</a>
        <a href="index.php?status=pending" class="quick-filter <?php echo ($status_filter === 'pending') ? 'active' : ''; ?>">Pending</a>
        <a href="index.php?status=accepted" class="quick-filter <?php echo ($status_filter === 'accepted') ? 'active' : ''; ?>">Accepted</a>
        <a href="index.php?status=confirmed" class="quick-filter <?php echo ($status_filter === 'confirmed') ? 'active' : ''; ?>">Confirmed</a>
        <a href="index.php?status=preparing" class="quick-filter <?php echo ($status_filter === 'preparing') ? 'active' : ''; ?>">Preparing</a>
        <a href="index.php?status=ready" class="quick-filter <?php echo ($status_filter === 'ready') ? 'active' : ''; ?>">Ready</a>
        <a href="index.php?status=picked_up" class="quick-filter <?php echo ($status_filter === 'picked_up') ? 'active' : ''; ?>">Picked Up</a>
        <a href="index.php?status=delivered" class="quick-filter <?php echo ($status_filter === 'delivered') ? 'active' : ''; ?>">Delivered</a>
        <a href="index.php?status=cancelled" class="quick-filter <?php echo ($status_filter === 'cancelled') ? 'active' : ''; ?>">Cancelled</a>
    </div>

    <!-- Advanced Filters -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Order ID, Customer, Phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> From Date</label>
                <input type="date" name="date_from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> To Date</label>
                <input type="date" name="date_to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
            <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <?php endif; ?>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Orders List</h3>
            <span style="font-size: 0.75rem; color: #64748b;"><?php echo count($orders); ?> order(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No orders match your criteria</p>
                             </a>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><span class="order-id"><?php echo $order['order_id']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['phone']); ?></td>
                                <td><span class="amount">TSh <?php echo number_format($order['grand_total']); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $badge_classes[$order['status']] ?? 'badge-pending'; ?>">
                                        <i class="fas <?php echo $status_icons[$order['status']] ?? 'fa-info-circle'; ?>"></i>
                                        <?php echo $status_labels[$order['status']] ?? ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: rgba(52,152,219,0.12); color: #3498db;">
                                        <i class="fas fa-<?php echo $order['payment_method'] === 'cash' ? 'money-bill' : 'mobile-alt'; ?>"></i>
                                        <?php echo ucfirst($order['payment_method']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?> </a>
                                <td>
                                    <a href="details.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-view" title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="update-status.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-update" title="Update Status">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                    <?php if (in_array($order['status'], ['pending', 'cancelled'])): ?>
                                        <a href="delete.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-delete" title="Delete Order" 
                                           onclick="return confirm('⚠️ Are you sure you want to permanently delete this order? This action cannot be undone!')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
             </a>
        </div>
    </div>
</div>
</body>
</html>