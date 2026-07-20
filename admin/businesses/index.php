<?php
// admin/businesses/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$verify_filter = isset($_GET['verify']) ? trim($_GET['verify']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build query with prepared statement
$sql = "SELECT b.*, u.email, u.phone, u.status as user_status 
        FROM businesses b
        JOIN users u ON b.user_id = u.user_id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (b.business_name LIKE ? OR b.address LIKE ? OR b.city LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s, $s];
    $types = "sssss";
}
if (!empty($verify_filter)) {
    if ($verify_filter === 'verified') $sql .= " AND b.is_verified = 1";
    elseif ($verify_filter === 'pending') $sql .= " AND b.is_verified = 0";
}
if (!empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$businesses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $businesses[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics
$total = count($businesses);
$verified = 0;
$pending = 0;
$active = 0;
$inactive = 0;
foreach ($businesses as $b) {
    if ($b['is_verified']) $verified++;
    else $pending++;
    if ($b['user_status'] === 'active') $active++;
    else $inactive++;
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
    <title>Manage Businesses | UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global styles (unchanged) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
        
        /* Filters bar (unchanged) */
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
        .badge-verified { background: #d1fae5; color: #059669; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .action-btns {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-sm:hover {
            transform: translateY(-1px);
            opacity: 0.9;
        }
        .btn-view { background: #3498db; color: white; }
        .btn-verify { background: #10b981; color: white; }
        .btn-unverify { background: #f59e0b; color: white; }
        .btn-toggle-status { background: #8b5cf6; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .empty-row td {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
            .table-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-store"></i> Manage Businesses</h1>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Improved Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total ?></h3>
            <p><i class="fas fa-store"></i> Total Businesses</p>
        </div>
        <div class="stat-card">
            <h3><?= $verified ?></h3>
            <p><i class="fas fa-check-circle"></i> Verified</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending ?></h3>
            <p><i class="fas fa-clock"></i> Pending Verification</p>
        </div>
        <div class="stat-card">
            <h3><?= $active ?> / <?= $inactive ?></h3>
            <p><i class="fas fa-power-off"></i> Active / Inactive</p>
        </div>
    </div>

    <!-- Filters (unchanged) -->
    <div class="filters-bar">
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; width: 100%; align-items: flex-end;">
            <div class="filter-group" style="flex: 2;">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Business name, address, email, phone..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-flag"></i> Verification</label>
                <select name="verify" class="filter-input">
                    <option value="">All</option>
                    <option value="verified" <?= $verify_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="pending" <?= $verify_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
            </div>
            <div class="filter-group">
                <label><i class="fas fa-user-check"></i> Account Status</label>
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

    <!-- Businesses Table (unchanged) -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Business List</h3>
            <span><?= count($businesses) ?> record(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Business Name</th><th>Email / Phone</th><th>Location</th>
                        <th>Verification</th><th>Account Status</th><th>Registered</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($businesses)): ?>
                        <tr class="empty-row"><td colspan="8">No businesses found</a></td>
                    <?php else: foreach ($businesses as $b): ?>
                        <tr onclick="location.href='view.php?id=<?= $b['business_id'] ?>'">
                            <td><?= $b['business_id'] ?></td>
                            <td><strong><?= htmlspecialchars($b['business_name']) ?></strong></td>
                            <td><?= htmlspecialchars($b['email']) ?><br><small><?= htmlspecialchars($b['phone']) ?></small></td>
                            <td><?= htmlspecialchars($b['location'] ?: $b['location']) ?></td>
                            <td><span class="badge badge-<?= $b['is_verified'] ? 'verified' : 'pending' ?>"><?= $b['is_verified'] ? 'Verified' : 'Pending' ?></span></td>
                            <td><span class="badge badge-<?= $b['user_status'] ?>"><?= ucfirst($b['user_status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($b['created_at'])) ?></td>
                            <td class="action-btns" onclick="event.stopPropagation();">
                                <a href="view.php?id=<?= $b['business_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                                <?php if ($b['is_verified']): ?>
                                    <a href="verify.php?id=<?= $b['business_id'] ?>&action=unverify" class="btn-sm btn-unverify" onclick="return confirm('Unverify this business?')"><i class="fas fa-times-circle"></i> Unverify</a>
                                <?php else: ?>
                                    <a href="verify.php?id=<?= $b['business_id'] ?>&action=verify" class="btn-sm btn-verify" onclick="return confirm('Verify this business?')"><i class="fas fa-check-circle"></i> Verify</a>
                                <?php endif; ?>
                                <a href="toggle-status.php?id=<?= $b['business_id'] ?>" class="btn-sm btn-toggle-status" onclick="return confirm('Toggle account status?')"><i class="fas fa-power-off"></i> <?= $b['user_status'] === 'active' ? 'Deactivate' : 'Activate' ?></a>
                                <a href="delete.php?id=<?= $b['business_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this business? This will remove all products, orders, and reviews.')"><i class="fas fa-trash-alt"></i> Delete</a>
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