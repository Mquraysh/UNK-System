<?php
// delivery/my-deliveries/my-deliveries.php - Professional Delivery Management
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: register.php");
    exit();
}

$agent_id = $agent['agent_id'];
$agent_name = $agent['first_name'] . ' ' . $agent['last_name'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT d.*, o.order_id, o.delivery_address, o.contact_phone, o.grand_total, o.order_date, o.delivery_fee,
               b.business_name, b.location as business_location, b.business_id
        FROM deliveries d 
        JOIN orders o ON d.order_id = o.order_id 
        JOIN businesses b ON o.business_id = b.business_id 
        WHERE d.agent_id = '$agent_id'";

if (!empty($status_filter)) {
    $sql .= " AND d.status = '$status_filter'";
}
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (o.order_id LIKE '%$search_escaped%' 
               OR b.business_name LIKE '%$search_escaped%' 
               OR o.delivery_address LIKE '%$search_escaped%')";
}
$sql .= " ORDER BY 
            CASE d.status 
                WHEN 'assigned' THEN 1 
                WHEN 'picked_up' THEN 2 
                WHEN 'in_transit' THEN 3 
                WHEN 'delivered' THEN 4 
                WHEN 'cancelled' THEN 5 
                ELSE 6 
            END ASC,
            d.created_at DESC";
$result = mysqli_query($conn, $sql);
$deliveries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $deliveries[] = $row;
}

// Get statistics for all statuses
$total_sql = "SELECT COUNT(*) as total FROM deliveries WHERE agent_id = '$agent_id'";
$total_result = mysqli_query($conn, $total_sql);
$total_deliveries = (int)(mysqli_fetch_assoc($total_result)['total'] ?? 0);

// Get counts for each status
$status_counts = [];
$status_list = ['assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled'];
foreach ($status_list as $status) {
    $count_sql = "SELECT COUNT(*) as count FROM deliveries WHERE agent_id = '$agent_id' AND status = '$status'";
    $count_result = mysqli_query($conn, $count_sql);
    $status_counts[$status] = (int)(mysqli_fetch_assoc($count_result)['count'] ?? 0);
}

// Get earnings summary
$earnings_sql = "SELECT SUM(delivery_fee) as total_earnings FROM deliveries 
                 WHERE agent_id = '$agent_id' AND status = 'delivered'";
$earnings_result = mysqli_query($conn, $earnings_sql);
$total_earnings = (int)(mysqli_fetch_assoc($earnings_result)['total_earnings'] ?? 0);

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Deliveries | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        
        .delivery-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 1.25rem;
            }
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            letter-spacing: -0.02em;
        }
        
        .page-header h1 i {
            color: #e67e22;
            font-size: 1.8rem;
        }
        
        .page-header p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.3rem;
            font-weight: 400;
        }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
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
        
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem 0.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -8px rgba(0,0,0,0.12);
            border-color: #e67e22;
        }
        
        .stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
        }
        
        .stat-card p {
            font-size: .85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
            letter-spacing: 0.3px;
        }
        
        .stat-card p i {
            font-size: 0.6rem;
        }
        
        /* Stat Card Colors */
        .stat-card.total h3 { color: #1e293b; }
        .stat-card.total p { color: #64748b; }
        .stat-card.assigned h3 { color: #2563eb; }
        .stat-card.assigned p { color: #1e40af; }
        .stat-card.picked_up h3 { color: #7c3aed; }
        .stat-card.picked_up p { color: #5b21b6; }
        .stat-card.in_transit h3 { color: #db2777; }
        .stat-card.in_transit p { color: #be185d; }
        .stat-card.delivered h3 { color: #10b981; }
        .stat-card.delivered p { color: #047857; }
        .stat-card.earnings h3 { color: #e67e22; }
        .stat-card.earnings p { color: #d35400; }
        
        /* Quick Filters */
        .quick-filters {
            display: flex;
            gap: 0.6rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        
        .quick-filter {
            padding: 0.45rem 1.1rem;
            background: #f8fafc;
            border-radius: 2rem;
            text-decoration: none;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .quick-filter i {
            font-size: 0.7rem;
        }
        
        .quick-filter:hover {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
            transform: translateY(-2px);
        }
        
        .quick-filter.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
            box-shadow: 0 2px 8px rgba(230,126,34,0.2);
        }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input {
            width: 100%;
            padding: 0.65rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.6rem;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .filter-group input::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }
        
        .btn-filter {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 0.6rem;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.8rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-filter:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            padding: 0.65rem 1.5rem;
            border-radius: 0.6rem;
            text-decoration: none;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.8rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-reset:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .table-header {
            padding: 1.25rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        
        .table-header h3 {
            font-size: 1rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
            letter-spacing: -0.01em;
        }
        
        .table-header h3 i {
            color: #e67e22;
        }
        
        .table-header span {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            padding: 1rem 1rem;
            text-align: left;
            font-weight: 700;
            color: #64748b;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            background: #fafcff;
        }
        
        .data-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
            font-weight: 500;
            vertical-align: middle;
        }
        
        .data-table tr:hover td {
            background: #fffbeb;
        }
        
        /* Business Name Style */
        .business-name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #1e293b;
            letter-spacing: -0.01em;
        }
        
        /* Price Style */
        .price-amount {
            font-weight: 800;
            color: #e67e22;
            font-size: 0.9rem;
        }
        
        /* ID Style */
        .id-number {
            font-weight: 800;
            color: #e67e22;
            font-family: 'Inter', monospace;
            letter-spacing: -0.01em;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.9rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        .status-badge i {
            font-size: 0.65rem;
        }
        
        .status-assigned {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-picked_up {
            background: #ede9fe;
            color: #5b21b6;
        }
        
        .status-in_transit {
            background: #fce7f3;
            color: #be185d;
        }
        
        .status-delivered {
            background: #d1fae5;
            color: #047857;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        /* Buttons - Links unchanged */
        .btn-sm {
            padding: 0.4rem 0.9rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: all 0.2s;
        }
        
        .btn-update {
            background: #e67e22;
            color: white;
            border: none;
        }
        
        .btn-update:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(230,126,34,0.3);
        }
        
        .btn-view {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        
        .btn-view:hover {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
            transform: translateY(-2px);
        }
        
        .btn-track {
            background: #10b981;
            color: white;
            border: none;
        }
        
        .btn-track:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        
        /* Button disabled state */
        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 3.5rem;
            margin-bottom: 0.9rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }
        }
        
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 1.25rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .delivery-content {
                padding: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
        
            .quick-filters {
                justify-content: center;
            }
            
            .data-table th,
            .data-table td {
                padding: 0.75rem 0.8rem;
                font-size: 0.7rem;
            }
            
            .btn-sm {
                padding: 0.3rem 0.6rem;
                font-size: 0.6rem;
            }
            
            .business-name {
                font-size: 0.75rem;
            }
            
            .price-amount {
                font-size: 0.75rem;
            }
            
            /* Table becomes card on mobile */
            .data-table thead {
                display: none;
            }
            
            .data-table tr {
                display: block;
                border-bottom: 2px solid #e2e8f0;
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                background: white;
                border-radius: 0.5rem;
            }
            
            .data-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f1f5f9;
                font-size: 0.75rem;
            }
            
            .data-table td:last-child {
                border-bottom: none;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .data-table td::before {
                content: attr(data-label);
                font-weight: 700;
                color: #64748b;
                font-size: 0.65rem;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.5rem;
            }
            
            .stat-card h3 {
                font-size: 20px;
            }
            
            .stat-card {
                padding: 1rem 0.25rem;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> My Deliveries</h1>
            <p>Manage and track all your assigned deliveries</p>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <a href="?status=" class="stat-card total">
            <h3><?php echo number_format($total_deliveries); ?></h3>
            <p><i class="fas fa-chart-line"></i> Total</p>
        </a>
        <a href="?status=assigned" class="stat-card assigned">
            <h3><?php echo number_format($status_counts['assigned']); ?></h3>
            <p><i class="fas fa-user-check"></i> Assigned</p>
        </a>
        <a href="?status=picked_up" class="stat-card picked_up">
            <h3><?php echo number_format($status_counts['picked_up']); ?></h3>
            <p><i class="fas fa-box-open"></i> Picked</p>
        </a>
        <a href="?status=in_transit" class="stat-card in_transit">
            <h3><?php echo number_format($status_counts['in_transit']); ?></h3>
            <p><i class="fas fa-truck"></i> Transit</p>
        </a>
        <a href="?status=delivered" class="stat-card delivered">
            <h3><?php echo number_format($status_counts['delivered']); ?></h3>
            <p><i class="fas fa-check-circle"></i> Done</p>
        </a>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="?status=" class="quick-filter <?php echo empty($status_filter) ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> All
        </a>
        <a href="?status=assigned" class="quick-filter <?php echo $status_filter == 'assigned' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i> Assigned
        </a>
        <a href="?status=picked_up" class="quick-filter <?php echo $status_filter == 'picked_up' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i> Picked Up
        </a>
        <a href="?status=in_transit" class="quick-filter <?php echo $status_filter == 'in_transit' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i> In Transit
        </a>
        <a href="?status=delivered" class="quick-filter <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Delivered
        </a>
        <a href="?status=cancelled" class="quick-filter <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">
            <i class="fas fa-times-circle"></i> Cancelled
        </a>
    </div>

    <!-- Search Bar -->
    <div class="filters-bar">
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" placeholder="Order ID, Business or Address..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <div style="display: flex; gap: 0.6rem;">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Search</button>
                <a href="my-deliveries.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Deliveries Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Delivery List</h3>
            <span><i class="fas fa-boxes"></i> <?php echo count($deliveries); ?> deliveries found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Order ID</th>
                        <th>Business</th>
                        <th>Address</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($deliveries)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fas fa-truck"></i>
                                <p>No deliveries found</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($deliveries as $delivery): 
                        $statusClass = 'status-' . $delivery['status'];
                        $statusIcon = '';
                        $statusText = '';
                        if ($delivery['status'] == 'assigned') { $statusIcon = 'fa-user-check'; $statusText = 'Assigned'; }
                        elseif ($delivery['status'] == 'picked_up') { $statusIcon = 'fa-box-open'; $statusText = 'Picked Up'; }
                        elseif ($delivery['status'] == 'in_transit') { $statusIcon = 'fa-truck'; $statusText = 'In Transit'; }
                        elseif ($delivery['status'] == 'delivered') { $statusIcon = 'fa-check-circle'; $statusText = 'Delivered'; }
                        elseif ($delivery['status'] == 'cancelled') { $statusIcon = 'fa-times-circle'; $statusText = 'Cancelled'; }
                        else { $statusIcon = 'fa-clock'; $statusText = 'Pending'; }
                    ?>
                    <tr>
                        <td data-label="Delivery ID"><span class="id-number"><?php echo $delivery['delivery_id']; ?></span></td>
                        <td data-label="Order ID"><span class="id-number"><?php echo $delivery['order_id']; ?></span></td>
                        <td data-label="Business"><span class="business-name"><?php echo htmlspecialchars($delivery['business_name']); ?></span></td>
                        <td data-label="Address"><span><?php echo htmlspecialchars(substr($delivery['delivery_address'], 0, 35)); ?><?php echo strlen($delivery['delivery_address']) > 35 ? '...' : ''; ?></span></td>
                        <td data-label="Fee"><span class="price-amount">TSh <?php echo number_format($delivery['delivery_fee'] ?? 0); ?></span></td>
                        <td data-label="Status"><span class="status-badge <?php echo $statusClass; ?>"><i class="fas <?php echo $statusIcon; ?>"></i> <?php echo $statusText; ?></span></td>
                        <td data-label="Date"><small><?php echo date('M d, H:i', strtotime($delivery['created_at'])); ?></small></td>
                        <td data-label="Action">
                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                <?php if($delivery['status'] != 'delivered' && $delivery['status'] != 'cancelled'): ?>
                                <a href="../update_status/update_status.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm btn-update" onclick="return confirm('Update this delivery status?')">
                                    <i class="fas fa-edit"></i> Update
                                </a>
                                <?php else: ?>
                                <span class="btn-sm btn-disabled">
                                    <i class="fas fa-lock"></i> Done
                                </span>
                                <?php endif; ?>
                                <a href="../track/track-delivery.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm btn-track">
                                    <i class="fas fa-map-marked-alt"></i> Track
                                </a>
                                <a href="../details/delivery-details.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm btn-view">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-hide flash message after 3 seconds
const flashDiv = document.querySelector('.alert');
if (flashDiv) {
    setTimeout(() => { 
        flashDiv.style.transition = 'opacity 0.5s';
        flashDiv.style.opacity = '0';
        setTimeout(() => { flashDiv.style.display = 'none'; }, 500);
    }, 3000);
}
</script>
</body>
</html>