<?php
// business/deliveries/index.php - Professional Deliveries Management (No Update)
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

// REMOVED: Status update via GET has been removed

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$sql = "SELECT d.*, 
               o.order_id, o.grand_total, o.delivery_address, o.order_date,
               c.first_name, c.last_name, c.city,
               a.first_name as agent_first_name, a.last_name as agent_last_name, a.vehicle_type, a.phone as agent_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
        WHERE o.business_id = '$business_id'";

if (!empty($status_filter)) {
    $sql .= " AND d.status = '$status_filter'";
}

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (o.order_id LIKE '%$search_escaped%' 
               OR c.first_name LIKE '%$search_escaped%' 
               OR c.last_name LIKE '%$search_escaped%')";
}

if (!empty($date_from)) {
    $sql .= " AND DATE(d.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $sql .= " AND DATE(d.created_at) <= '$date_to'";
}

$sql .= " ORDER BY d.created_at DESC";
$result = mysqli_query($conn, $sql);
$deliveries = [];
while($row = mysqli_fetch_assoc($result)) {
    $deliveries[] = $row;
}

// Statistics
$total_deliveries = count($deliveries);
$pending_deliveries = 0;
$assigned_deliveries = 0;
$picked_up_deliveries = 0;
$in_transit_deliveries = 0;
$delivered_deliveries = 0;
$failed_deliveries = 0;

foreach($deliveries as $d) {
    if($d['status'] == 'pending') $pending_deliveries++;
    elseif($d['status'] == 'assigned') $assigned_deliveries++;
    elseif($d['status'] == 'picked_up') $picked_up_deliveries++;
    elseif($d['status'] == 'in_transit') $in_transit_deliveries++;
    elseif($d['status'] == 'delivered') $delivered_deliveries++;
    elseif($d['status'] == 'failed') $failed_deliveries++;
}

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deliveries - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        .page-header h1 i { color: #e67e22; font-size: 1.8rem; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        /* Stats Grid - Professional Cards */
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: all 0.3s;
        }
        .stat-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-card h3 { 
            font-size: 1.75rem; 
            font-weight: 800; 
            margin-bottom: 0.25rem; 
        }
        .stat-card p { 
            font-size: 0.8rem; 
            color: #64748b; 
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .stat-card p i { font-size: 0.65rem; }
        .stat-card.total h3 { color: #2c3e50; }
        .stat-card.pending h3 { color: #f39c12; }
        .stat-card.assigned h3 { color: #3498db; }
        .stat-card.picked_up h3 { color: #8e44ad; }
        .stat-card.transit h3 { color: #4338ca; }
        .stat-card.delivered h3 { color: #27ae60; }
        .stat-card.failed h3 { color: #e74c3c; }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        .filters-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { 
            display: block; 
            font-size: 0.7rem; 
            font-weight: 600; 
            color: #64748b; 
            margin-bottom: 0.35rem; 
        }
        .filter-group label i { margin-right: 0.25rem; }
        .filter-input {
            width: 100%;
            padding: 0.6rem 0.9rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.6rem;
            font-size: 0.8rem;
            font-family: inherit;
        }
        .filter-input:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .btn-filter { 
            background: #e67e22; 
            color: white; 
            padding: 0.6rem 1.2rem; 
            border-radius: 0.6rem; 
            border: none; 
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-filter:hover { background: #d35400; transform: translateY(-2px); }
        .btn-reset { 
            background: #94a3b8; 
            color: white; 
            padding: 0.6rem 1.2rem; 
            border-radius: 0.6rem; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-reset:hover { background: #64748b; transform: translateY(-2px); }
        .filter-buttons { display: flex; gap: 0.6rem; }
        
        /* Quick Filters */
        .quick-filters {
            display: flex;
            gap: 0.6rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .quick-filter {
            padding: 0.4rem 1rem;
            background: #f1f5f9;
            border-radius: 2rem;
            text-decoration: none;
            color: #475569;
            font-size: 0.75rem;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
        }
        .quick-filter:hover, .quick-filter.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        .table-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .table-header h3 { font-size: 0.95rem; font-weight: 700; color: #0f172a; }
        .table-header h3 i { color: #e67e22; margin-right: 0.5rem; }
        .table-header span { font-size: 0.75rem; color: #64748b; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { 
            padding: 0.85rem 1rem; 
            text-align: left; 
            font-weight: 600; 
            color: #64748b; 
            font-size: 0.7rem; 
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            background: #fafcff;
        }
        .data-table td { 
            padding: 0.85rem 1rem; 
            border-bottom: 1px solid #f1f5f9; 
            font-size: 0.8rem; 
            vertical-align: middle; 
        }
        .data-table tr:hover td { background: #fff5eb; }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge i { margin-right: 0.25rem; font-size: 0.55rem; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-assigned { background: #dbeafe; color: #2563eb; }
        .badge-picked_up { background: #e0e7ff; color: #4338ca; }
        .badge-in_transit { background: #ede9fe; color: #6d28d9; }
        .badge-delivered { background: #d1fae5; color: #059669; }
        .badge-failed { background: #fee2e2; color: #dc2626; }
        
        .btn-sm { 
            padding: 0.35rem 0.8rem; 
            border-radius: 0.5rem; 
            text-decoration: none; 
            font-size: 0.7rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.35rem; 
            margin: 0.1rem;
            transition: all 0.2s;
        }
        .btn-view { background: #3498db; color: white; }
        .btn-view:hover { background: #2980b9; transform: translateY(-2px); }
        .btn-track { background: #27ae60; color: white; }
        .btn-track:hover { background: #219a52; transform: translateY(-2px); }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 3.5rem; margin-bottom: 0.9rem; opacity: 0.5; }
        .empty-state p { font-size: 0.85rem; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 1024px) { 
            .business-content { margin-left: 0; padding: 1.25rem; } 
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-group { min-width: 100%; }
            .filter-buttons { width: 100%; }
            .btn-filter, .btn-reset { flex: 1; text-align: center; justify-content: center; }
            .data-table th, .data-table td { padding: 0.6rem 0.8rem; font-size: 0.7rem; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-truck"></i> Deliveries Management</h1>
            <p>Track and manage all your deliveries</p>
        </div>
    </div>
    
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards - Professional Grid -->
    <div class="stats-grid">
        <a href="index.php" class="stat-card total">
            <h3><?php echo $total_deliveries; ?></h3>
            <p><i class="fas fa-truck"></i> Total</p>
        </a>
        <a href="?status=pending" class="stat-card pending">
            <h3><?php echo $pending_deliveries; ?></h3>
            <p><i class="fas fa-clock"></i> Pending</p>
        </a>
        <a href="?status=assigned" class="stat-card assigned">
            <h3><?php echo $assigned_deliveries; ?></h3>
            <p><i class="fas fa-user-check"></i> Assigned</p>
        </a>
        <a href="?status=picked_up" class="stat-card picked_up">
            <h3><?php echo $picked_up_deliveries; ?></h3>
            <p><i class="fas fa-box-open"></i> Picked Up</p>
        </a>
        <a href="?status=in_transit" class="stat-card transit">
            <h3><?php echo $in_transit_deliveries; ?></h3>
            <p><i class="fas fa-road"></i> In Transit</p>
        </a>
        <a href="?status=delivered" class="stat-card delivered">
            <h3><?php echo $delivered_deliveries; ?></h3>
            <p><i class="fas fa-check-circle"></i> Delivered</p>
        </a>
        <a href="?status=failed" class="stat-card failed">
            <h3><?php echo $failed_deliveries; ?></h3>
            <p><i class="fas fa-times-circle"></i> Failed</p>
        </a>
    </div>
    
    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="index.php" class="quick-filter <?php echo empty($status_filter) ? 'active' : ''; ?>">All</a>
        <a href="?status=pending" class="quick-filter <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=assigned" class="quick-filter <?php echo $status_filter == 'assigned' ? 'active' : ''; ?>">Assigned</a>
        <a href="?status=picked_up" class="quick-filter <?php echo $status_filter == 'picked_up' ? 'active' : ''; ?>">Picked Up</a>
        <a href="?status=in_transit" class="quick-filter <?php echo $status_filter == 'in_transit' ? 'active' : ''; ?>">In Transit</a>
        <a href="?status=delivered" class="quick-filter <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">Delivered</a>
        <a href="?status=failed" class="quick-filter <?php echo $status_filter == 'failed' ? 'active' : ''; ?>">Failed</a>
    </div>
    
    <!-- Filters Bar -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
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
        </form>
    </div>
    
    <!-- Deliveries Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Deliveries List</h3>
            <span><i class="fas fa-boxes"></i> <?php echo $total_deliveries; ?> deliveries found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Delivery ID</th>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Delivery Agent</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($deliveries)): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="fas fa-truck"></i>
                            <p>No deliveries found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach($deliveries as $delivery): ?>
                    <tr>
                        <td><strong><?php echo $delivery['delivery_id']; ?></strong></td>
                        <td><a href="../orders/details.php?id=<?php echo $delivery['order_id']; ?>" style="color: #e67e22; text-decoration: none; font-weight: 600;"><?php echo $delivery['order_id']; ?></a></td>
                        <td>
                            <strong><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></strong><br>
                        </td>
                        <td>
                            <?php if($delivery['agent_first_name']): ?>
                                <strong><?php echo htmlspecialchars($delivery['agent_first_name'] . ' ' . $delivery['agent_last_name']); ?></strong><br>
                            <?php else: ?>
                                <span style="color: #e74c3c; font-size: 0.7rem;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo htmlspecialchars(substr($delivery['delivery_address'], 0, 35)); ?>...</small></td>
                        <td>
                            <span class="badge badge-<?php echo $delivery['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo $delivery['status'] == 'pending' ? 'clock' : 
                                        ($delivery['status'] == 'assigned' ? 'user-check' : 
                                        ($delivery['status'] == 'picked_up' ? 'box-open' : 
                                        ($delivery['status'] == 'in_transit' ? 'road' : 
                                        ($delivery['status'] == 'delivered' ? 'check-circle' : 'times-circle')))); ?>"></i>
                                <?php echo str_replace('_', ' ', ucfirst($delivery['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                <a href="view.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="tracking.php?id=<?php echo $delivery['delivery_id']; ?>" class="btn-sm btn-track">
                                    <i class="fas fa-map-marker-alt"></i> Track
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
</body>
</html>