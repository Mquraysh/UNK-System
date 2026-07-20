<?php
// admin/users/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? trim($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query with prepared statement
$sql = "SELECT user_id, full_name, email, phone, role, status, created_at FROM users WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = "sss";
}
if (!empty($role_filter)) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$sql .= " ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$users_result = mysqli_stmt_get_result($stmt);
$users = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $users[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics
$total_users = count($users);
$role_counts = ['admin' => 0, 'business' => 0, 'customer' => 0, 'delivery' => 0];
$status_counts = ['active' => 0, 'inactive' => 0];
foreach ($users as $u) {
    $role_counts[$u['role']] = ($role_counts[$u['role']] ?? 0) + 1;
    $status_counts[$u['status']] = ($status_counts[$u['status']] ?? 0) + 1;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Users | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb;  }
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
        .stats-grid:last-of-type {
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
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
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
        .role-badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .role-admin { background: #e0e7ff; color: #3730a3; }
        .role-business { background: #fed7aa; color: #c2410c; }
        .role-customer { background: #d1fae5; color: #059669; }
        .role-delivery { background: #dbeafe; color: #2563eb; }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        .action-btns { display: flex; gap: 0.5rem; }
        .btn-sm {
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: 0.2s;
        }
        .btn-sm:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-view { background: #3498db; color: white; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete.disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            opacity: 0.6;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
        <h1><i class="fas fa-users"></i> Manage Users</h1>
    </div>

    <!-- Improved Statistics Cards (Row 1) -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_users ?></h3>
            <p><i class="fas fa-users"></i> Total Users</p>
        </div>
        <div class="stat-card">
            <h3><?= $role_counts['admin'] ?></h3>
            <p><i class="fas fa-user-shield"></i> Admins</p>
        </div>
        <div class="stat-card">
            <h3><?= $role_counts['business'] ?></h3>
            <p><i class="fas fa-store"></i> Businesses</p>
        </div>
        <div class="stat-card">
            <h3><?= $role_counts['customer'] ?></h3>
            <p><i class="fas fa-user"></i> Customers</p>
        </div>
    </div>
    <!-- Row 2 -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $role_counts['delivery'] ?></h3>
            <p><i class="fas fa-truck"></i> Delivery Agents</p>
        </div>
        <div class="stat-card">
            <h3><?= $status_counts['active'] ?></h3>
            <p><i class="fas fa-check-circle"></i> Active</p>
        </div>
        <div class="stat-card">
            <h3><?= $status_counts['inactive'] ?></h3>
            <p><i class="fas fa-times-circle"></i> Inactive</p>
        </div>
    </div>

    <!-- Filters (unchanged) -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:1rem; width:100%; align-items:flex-end;">
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Name, email, phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label>Role</label>
                <select name="role" class="filter-input">
                    <option value="">All</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="business" <?= $role_filter === 'business' ? 'selected' : '' ?>>Business</option>
                    <option value="customer" <?= $role_filter === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="delivery" <?= $role_filter === 'delivery' ? 'selected' : '' ?>>Delivery</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Users Table (unchanged) -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> User List</h3>
            <span><?= count($users) ?> record(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th>
                        <th>Role</th><th>Status</th><th>Registered</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr class="empty-row"><td colspan="8">No users found</a></td>
                    <?php else: foreach ($users as $u): ?>
                        <tr onclick="location.href='view.php?id=<?= $u['user_id'] ?>'">
                            <td><?= $u['user_id'] ?></td>
                            <td><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['phone']) ?></td>
                            <td><span class="role-badge role-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td><span class="role-badge status-<?= $u['status'] ?>"><?= ucfirst($u['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                            <td class="action-btns" onclick="event.stopPropagation();">
                                <a href="view.php?id=<?= $u['user_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                                <a href="edit.php?id=<?= $u['user_id'] ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <?php if ($u['user_id'] != $current_user_id): ?>
                                    <a href="delete.php?id=<?= $u['user_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this user? This action cannot be undone.')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                <?php else: ?>
                                    <span class="btn-sm btn-delete disabled" title="You cannot delete your own account">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </span>
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