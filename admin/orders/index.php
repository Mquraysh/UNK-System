<?php
// admin/orders/index.php 
require_once '../../config/database.php';
require_once '../notifications/functions.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$business_filter = isset($_GET['business']) ? trim($_GET['business']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Build query with prepared statement
$sql = "SELECT o.order_id, o.grand_total, o.status, o.payment_status, o.order_date,
               c.first_name, c.last_name, c.customer_id,
               b.business_name, b.business_id
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN businesses b ON o.business_id = b.business_id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (o.order_id LIKE ? OR b.business_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = "sss";
}
if (!empty($status_filter)) {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
if (!empty($business_filter)) {
    $sql .= " AND b.business_name LIKE ?";
    $params[] = "%$business_filter%";
    $types .= "s";
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
$sql .= " ORDER BY o.order_date DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics
$total_orders = count($orders);
$pending_count = 0;
$processing_count = 0;
$delivered_count = 0;
$cancelled_count = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'pending') $pending_count++;
    elseif (in_array($o['status'], ['accepted','confirmed','preparing','ready','picked_up','in_transit'])) $processing_count++;
    elseif ($o['status'] === 'delivered') $delivered_count++;
    elseif ($o['status'] === 'cancelled') $cancelled_count++;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Orders | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global styles (unchanged) */
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f1f5f9; }
        .admin-content {
            margin-left:280px;
            padding:2rem;
            min-height:100vh;
            transition:all 0.3s;
        }
        @media (max-width:1024px) {
            .admin-content { margin-left:0; padding:1.25rem; }
        }
        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:1rem;
            margin-bottom:1.5rem;
            border-bottom:1px solid #e2e8f0;
            padding-bottom:0.75rem;
        }
        .page-header h1 {
            font-size:1.8rem;
            font-weight:700;
            background:linear-gradient(135deg,#1e293b,#2c3e50);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
            display:flex;
            align-items:center;
            gap:0.75rem;
        }
        .page-header h1 i { color:#e67e22; }
        
        /* Improved Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem 1rem;
            text-align: center;
            border: 1px solid #eef2f8;
            transition: all 0.25s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e67e22, #f39c12);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.1);
            border-color: rgba(230,126,34,0.3);
        }
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #1e293b, #2d3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
            /* text-transform: uppercase; */
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .stat-card p i {
            font-size: 0.8rem;
            color: #e67e22;
        }
        
        /* Filters (unchanged) */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            border: 1px solid #eef2f8;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            background: white;
        }
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
        }
        .btn-filter, .btn-reset {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            border: none;
        }
        .btn-filter {
            background: #e67e22;
            color: white;
        }
        .btn-filter:hover {
            background: #d35400;
            transform: translateY(-1px);
        }
        .btn-reset {
            background: #94a3b8;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .btn-reset:hover {
            background: #64748b;
            transform: translateY(-1px);
        }
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
        }
        .table-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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
            text-align: left;
            padding: 0.8rem 1rem;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #fffaf5;
            cursor: pointer;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-processing { background: #dbeafe; color: #2563eb; }
        .badge-delivered { background: #d1fae5; color: #059669; }
        .badge-cancelled { background: #fee2e2; color: #dc2626; }
        .action-btns { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-sm {
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-sm:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-view { background: #3498db; color: white; }
        .btn-cancel { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .empty-row td { text-align:center; padding:2rem; color:#94a3b8; }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Manage Orders</h1>
    </div>

    <!-- Improved Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_orders ?></h3>
            <p><i class="fas fa-shopping-cart"></i> Total Orders</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending_count ?></h3>
            <p><i class="fas fa-clock"></i> Pending</p>
        </div>
        <div class="stat-card">
            <h3><?= $processing_count ?></h3>
            <p><i class="fas fa-cogs"></i> Processing</p>
        </div>
        <div class="stat-card">
            <h3><?= $delivered_count ?></h3>
            <p><i class="fas fa-check-circle"></i> Delivered</p>
        </div>
    </div>

    <!-- Filters (unchanged) -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:1rem; width:100%; align-items:flex-end;">
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Order ID, Business, Customer..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Business Name</label>
                <input type="text" name="business" class="filter-input" placeholder="Business name" value="<?= htmlspecialchars($business_filter) ?>">
            </div>
            <div class="filter-group">
                <label>From Date</label>
                <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="filter-group">
                <label>To Date</label>
                <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Orders Table (unchanged) -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Order List</h3>
            <span><?= count($orders) ?> record(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th><th>Customer</th><th>Business</th><th>Amount</th>
                        <th>Order Status</th><th>Payment Status</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr class="empty-row"><td colspan="8">No orders found</a></td>
                    <?php else: foreach ($orders as $o): ?>
                        <tr onclick="location.href='view.php?id=<?= $o['order_id'] ?>'">
                            <td><?= $o['order_id'] ?></td>
                            <td><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                            <td><?= htmlspecialchars($o['business_name']) ?></td>
                            <td>TSh <?= number_format($o['grand_total']) ?></td>
                            <td><span class="badge badge-<?= $o['status'] === 'pending' ? 'pending' : (in_array($o['status'], ['delivered']) ? 'delivered' : ($o['status'] === 'cancelled' ? 'cancelled' : 'processing')) ?>"><?= ucfirst(str_replace('_', ' ', $o['status'])) ?></span></td>
                            <td><span class="badge <?= $o['payment_status'] === 'paid' ? 'badge-delivered' : 'badge-pending' ?>"><?= ucfirst($o['payment_status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($o['order_date'])) ?></td>
                            <td class="action-btns" onclick="event.stopPropagation();">
                                <a href="view.php?id=<?= $o['order_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                                <?php if (in_array($o['status'], ['pending', 'accepted', 'confirmed', 'preparing', 'ready'])): ?>
                                    <a href="cancel.php?id=<?= $o['order_id'] ?>" class="btn-sm btn-cancel" onclick="return confirm('Cancel this order? This action may restore stock.')"><i class="fas fa-times-circle"></i> Cancel</a>
                                <?php endif; ?>
                                <?php if (in_array($o['status'], ['pending', 'cancelled'])): ?>
                                    <a href="delete.php?id=<?= $o['order_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this order? This action cannot be undone.')"><i class="fas fa-trash-alt"></i> Delete</a>
                                <?php endif; ?>
                            </a>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
             </a>
        </div>
    </div>
</div>
</body>
</html>