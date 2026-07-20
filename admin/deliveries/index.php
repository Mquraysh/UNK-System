<?php
    // admin/deliveries/index.php 
    require_once '../../config/database.php';
    require_once '../notifications/functions.php';

    session_start();

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit();
    }

    // Get filter parameters
    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $agent_filter = isset($_GET['agent']) ? (int)$_GET['agent'] : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

    // STATISTICS (all deliveries) 
    $status_counts = ['pending' => 0, 'assigned' => 0, 'picked_up' => 0, 'in_transit' => 0, 'delivered' => 0, 'failed' => 0];
    $total = 0;

    $stats_sql = "SELECT status, COUNT(*) as cnt FROM deliveries GROUP BY status";
    $stats_result = mysqli_query($conn, $stats_sql);
    if ($stats_result) {
        while ($row = mysqli_fetch_assoc($stats_result)) {
            $s = strtolower($row['status']);
            if (isset($status_counts[$s])) $status_counts[$s] = (int)$row['cnt'];
            $total += (int)$row['cnt'];
        }
    }

    // BUILD MAIN QUERY (prepared) 
    $sql = "SELECT d.*, o.order_id, o.grand_total, o.delivery_address, 
                b.business_name,
                a.first_name as agent_first_name, a.last_name as agent_last_name
            FROM deliveries d
            JOIN orders o ON d.order_id = o.order_id
            JOIN businesses b ON o.business_id = b.business_id
            LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($status_filter)) {
        $sql .= " AND LOWER(d.status) = LOWER(?)";
        $params[] = $status_filter;
        $types .= "s";
    }
    if ($agent_filter > 0) {
        $sql .= " AND d.agent_id = ?";
        $params[] = $agent_filter;
        $types .= "i";
    }
    if (!empty($search)) {
        $sql .= " AND (o.order_id LIKE ? OR b.business_name LIKE ? OR o.delivery_address LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
    if (!empty($date_from)) {
        $sql .= " AND DATE(d.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    if (!empty($date_to)) {
        $sql .= " AND DATE(d.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }
    $sql .= " ORDER BY d.created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $deliveries = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $deliveries[] = $row;
    }
    mysqli_stmt_close($stmt);

    // AGENTS FOR FILTER DROPDOWN 
    $agents = [];
    $agents_sql = "SELECT agent_id, first_name, last_name FROM delivery_agents WHERE status = 'active' ORDER BY first_name";
    $agents_res = mysqli_query($conn, $agents_sql);
    if ($agents_res) {
        while ($row = mysqli_fetch_assoc($agents_res)) {
            $agents[] = $row;
        }
    }

    // Flash message
    $flash_message = $_SESSION['flash_message'] ?? '';
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

    include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Deliveries | UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
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
        .page-header h1 i { color: #e67e22; }
        
        /* Improved Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem 0.5rem;
            text-align: center;
            border: 1px solid #eef2f8;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
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
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -12px rgba(0,0,0,0.1);
            border-color: rgba(230,126,34,0.3);
        }
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        .stat-card h3 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #1e293b, #2d3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 500;
            /* text-transform: uppercase; */
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .stat-card p i {
            font-size: 0.7rem;
            color: #e67e22;
        }
        
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
            min-width: 140px;
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
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }
        .alert-success {
            background: #e6f7ec;
            color: #0a5c3e;
            border-left-color: #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
        }
        .table-header {
            padding: 0.8rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
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
            font-size: 0.8rem;
            vertical-align: middle;
        }
        .data-table tr:hover td {
            background: #fffaf5;
            cursor: pointer;
        }
        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #c7d2fe; color: #3730a3; }
        .status-in_transit { background: #c7d2fe; color: #3730a3; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-failed { background: #fee2e2; color: #dc2626; }
        .btn-sm {
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-sm:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        .btn-update { background: #f59e0b; color: white; }
        .btn-assign { background: #10b981; color: white; }
        .btn-view { background: #3498db; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .empty-state td {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Manage Deliveries</h1>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards (clickable) -->
    <div class="stats-grid">
        <a href="?status=" class="stat-card">
            <h3><?= $total ?></h3>
            <p><i class="fas fa-box"></i> Total</p>
        </a>
        <a href="?status=pending" class="stat-card">
            <h3><?= $status_counts['pending'] ?? 0 ?></h3>
            <p><i class="fas fa-clock"></i> Pending</p>
        </a>
        <a href="?status=assigned" class="stat-card">
            <h3><?= $status_counts['assigned'] ?? 0 ?></h3>
            <p><i class="fas fa-user-check"></i> Assigned</p>
        </a>
        <a href="?status=picked_up" class="stat-card">
            <h3><?= ($status_counts['picked_up'] ?? 0) + ($status_counts['in_transit'] ?? 0) ?></h3>
            <p><i class="fas fa-truck"></i> In Progress</p>
        </a>
        <a href="?status=delivered" class="stat-card">
            <h3><?= $status_counts['delivered'] ?? 0 ?></h3>
            <p><i class="fas fa-check-circle"></i> Delivered</p>
        </a>
        <a href="?status=failed" class="stat-card">
            <h3><?= $status_counts['failed'] ?? 0 ?></h3>
            <p><i class="fas fa-times-circle"></i> Failed</p>
        </a>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; width: 100%; align-items: flex-end;">
            <div class="filter-group" style="flex: 2;">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Order ID, business, address..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-flag"></i> Status</label>
                <select name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="assigned" <?= $status_filter === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                    <option value="picked_up" <?= $status_filter === 'picked_up' ? 'selected' : '' ?>>Picked Up</option>
                    <option value="in_transit" <?= $status_filter === 'in_transit' ? 'selected' : '' ?>>In Transit</option>
                    <option value="delivered" <?= $status_filter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-user"></i> Delivery Agent</label>
                <select name="agent" class="filter-input">
                    <option value="0">All Agents</option>
                    <?php foreach ($agents as $a): ?>
                        <option value="<?= $a['agent_id'] ?>" <?= $agent_filter == $a['agent_id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> From</label>
                <input type="date" name="date_from" class="filter-input" value="<?= $date_from ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar"></i> To</label>
                <input type="date" name="date_to" class="filter-input" value="<?= $date_to ?>">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Deliveries Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> All Deliveries</h3>
            <span><?= count($deliveries) ?> record(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Delivery ID</th><th>Order ID</th><th>Business</th>
                        <th>Address</th><th>Agent</th><th>Status</th><th>Date</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($deliveries)): ?>
                        <tr class="empty-state"><td colspan="8">No deliveries found</a></tr>
                    <?php else: foreach ($deliveries as $d): ?>
                        <tr onclick="location.href='delivery-details.php?id=<?= $d['delivery_id'] ?>'">
                            <td><strong class="order-id"><?= $d['delivery_id'] ?></strong></td>
                            <td><?= $d['order_id'] ?></td>
                            <td><?= htmlspecialchars($d['business_name']) ?></td>
                            <td><?= htmlspecialchars(substr($d['delivery_address'], 0, 35)) ?>...</a>
                            <td>
                                <?php if ($d['agent_first_name']): ?>
                                    <?= htmlspecialchars($d['agent_first_name'] . ' ' . $d['agent_last_name']) ?>
                                <?php else: ?>
                                    <span style="color:#e74c3c;">Not assigned</span>
                                <?php endif; ?>
                             </a>
                            <td><span class="status-badge status-<?= strtolower($d['status']) ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></a>
                            <td><?= date('M d, Y', strtotime($d['created_at'])) ?></a>
                            <td class="action-btns" onclick="event.stopPropagation();">
                                <?php if (!$d['agent_id']): ?>
                                    <a href="assign.php?id=<?= $d['delivery_id'] ?>" class="btn-sm btn-assign"><i class="fas fa-user-plus"></i> Assign</a>
                                <?php endif; ?>
                                <a href="update-status.php?id=<?= $d['delivery_id'] ?>" class="btn-sm btn-update"><i class="fas fa-edit"></i> Update</a>
                                <a href="delivery-details.php?id=<?= $d['delivery_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                                <a href="delete.php?id=<?= $d['delivery_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this delivery record?')"><i class="fas fa-trash-alt"></i> Delete</a>
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