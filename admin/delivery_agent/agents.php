<?php
// admin/delivery_agent/agents.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle status toggle (activate, deactivate, suspend)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $agent_id = (int)$_GET['id'];
    $action = $_GET['toggle'];
    
    if ($action === 'activate') {
        $new_status = 'active';
    } elseif ($action === 'deactivate') {
        $new_status = 'inactive';
    } elseif ($action === 'suspend') {
        $new_status = 'suspended';
    } else {
        $_SESSION['flash_message'] = "Invalid action.";
        $_SESSION['flash_type'] = 'danger';
        header("Location: agents.php");
        exit();
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE delivery_agents SET status = ? WHERE agent_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_status, $agent_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['flash_message'] = "Agent " . ($action === 'activate' ? 'activated' : ($action === 'deactivate' ? 'deactivated' : 'suspended')) . " successfully.";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Failed to update agent status.";
        $_SESSION['flash_type'] = 'danger';
    }
    mysqli_stmt_close($stmt);
    header("Location: agents.php");
    exit();
}

// Handle delete agent
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $agent_id = (int)$_GET['delete'];
    
    // Check if agent has pending deliveries
    $check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM deliveries WHERE agent_id = ? AND status IN ('assigned', 'picked_up', 'in_transit')");
    mysqli_stmt_bind_param($check_stmt, 'i', $agent_id);
    mysqli_stmt_execute($check_stmt);
    $pending = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))['cnt'];
    mysqli_stmt_close($check_stmt);
    
    if ($pending > 0) {
        $_SESSION['flash_message'] = "Cannot delete agent with pending deliveries. Please reassign or complete those deliveries first.";
        $_SESSION['flash_type'] = 'danger';
        header("Location: agents.php");
        exit();
    }
    
    // Get user_id before deleting
    $user_stmt = mysqli_prepare($conn, "SELECT user_id FROM delivery_agents WHERE agent_id = ?");
    mysqli_stmt_bind_param($user_stmt, 'i', $agent_id);
    mysqli_stmt_execute($user_stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
    $user_id = $user['user_id'] ?? 0;
    mysqli_stmt_close($user_stmt);
    
    // Delete agent
    $del_stmt = mysqli_prepare($conn, "DELETE FROM delivery_agents WHERE agent_id = ?");
    mysqli_stmt_bind_param($del_stmt, 'i', $agent_id);
    if (mysqli_stmt_execute($del_stmt)) {
        // Delete associated user
        if ($user_id) {
            $user_del = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($user_del, 'i', $user_id);
            mysqli_stmt_execute($user_del);
            mysqli_stmt_close($user_del);
        }
        $_SESSION['flash_message'] = "Delivery agent permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        $_SESSION['flash_message'] = "Failed to delete agent.";
        $_SESSION['flash_type'] = 'danger';
    }
    mysqli_stmt_close($del_stmt);
    header("Location: agents.php");
    exit();
}

// Search & filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build main query with delivery stats - FIXED: a.email removed from WHERE
$sql = "SELECT 
            a.*, 
            u.email,
            (SELECT COUNT(*) FROM deliveries WHERE agent_id = a.agent_id) as total_deliveries,
            (SELECT AVG(rating) FROM delivery_ratings WHERE agent_id = a.agent_id) as avg_rating,
            (SELECT COUNT(*) FROM delivery_ratings WHERE agent_id = a.agent_id) as total_ratings
        FROM delivery_agents a
        JOIN users u ON a.user_id = u.user_id
        WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    // FIXED: Removed a.email from WHERE clause (email is in users table)
    $sql .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.phone LIKE ? OR a.vehicle_registration LIKE ? OR a.license_number LIKE ? OR u.email LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s, $s, $s, $s];
    $types = "ssssss";
}
if (!empty($status_filter)) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}
$sql .= " ORDER BY a.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$agents_result = mysqli_stmt_get_result($stmt);
$agents = [];
while ($row = mysqli_fetch_assoc($agents_result)) {
    // Format rating
    $row['avg_rating'] = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
    $agents[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics
$total_agents = count($agents);
$active_count = 0;
$inactive_count = 0;
$suspended_count = 0;
foreach ($agents as $a) {
    if ($a['status'] === 'active') $active_count++;
    elseif ($a['status'] === 'inactive') $inactive_count++;
    elseif ($a['status'] === 'suspended') $suspended_count++;
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
    <title>Delivery Agents | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        .admin-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 15px; }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .btn-add {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add:hover { background: #d35400; transform: translateY(-2px); }
        
        /* Stats Cards */
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .stat-card p i {
            font-size: 0.8rem;
            color: #e67e22;
        }
        
        /* Filters */
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
        }
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .btn-filter {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-filter:hover { background: #d35400; }
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-reset:hover { background: #64748b; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .table-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .table-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #fffbeb; cursor: pointer; }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        .status-suspended { background: #fee2e2; color: #dc2626; }
        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 5px 10px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 600;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-sm:hover { transform: translateY(-1px); }
        .btn-view { background: #3b82f6; color: white; }
        .btn-view:hover { background: #2563eb; }
        .btn-activate { background: #10b981; color: white; }
        .btn-activate:hover { background: #059669; }
        .btn-deactivate { background: #f59e0b; color: white; }
        .btn-deactivate:hover { background: #d97706; }
        .btn-suspend { background: #ef4444; color: white; }
        .btn-suspend:hover { background: #dc2626; }
        .btn-delete { background: #6b7280; color: white; }
        .btn-delete:hover { background: #4b5563; }
        .rating-stars { color: #f59e0b; font-size: 0.8rem; }
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-buttons { display: flex; gap: 10px; }
            .filter-buttons .btn-filter, .filter-buttons .btn-reset { flex: 1; text-align: center; }
            .data-table td { font-size: 12px; padding: 10px 8px; }
            .data-table th { font-size: 10px; padding: 10px 8px; }
            .action-btns { flex-wrap: wrap; }
            .btn-sm { font-size: 10px; padding: 4px 8px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .page-header h1 { font-size: 22px; }
            .btn-add { width: 100%; justify-content: center; }
            .data-table td, .data-table th { padding: 8px 6px; font-size: 10px; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Delivery Agents</h1>
        <a href="add.php" class="btn-add"><i class="fas fa-plus"></i> Add New Agent</a>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_agents ?></h3>
            <p><i class="fas fa-users"></i> Total Agents</p>
        </div>
        <div class="stat-card">
            <h3><?= $active_count ?></h3>
            <p><i class="fas fa-check-circle" style="color: #10b981;"></i> Active</p>
        </div>
        <div class="stat-card">
            <h3><?= $inactive_count ?></h3>
            <p><i class="fas fa-pause-circle" style="color: #f59e0b;"></i> Inactive</p>
        </div>
        <div class="stat-card">
            <h3><?= $suspended_count ?></h3>
            <p><i class="fas fa-ban" style="color: #ef4444;"></i> Suspended</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; width: 100%; align-items: flex-end;">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Name, phone, vehicle, license..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-filter"></i> Status</label>
                <select name="status" class="filter-input">
                    <option value="">All</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div class="filter-buttons" style="display: flex; gap: 10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="agents.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Agents Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> All Delivery Agents</h3>
            <span style="font-size: 0.85rem; color: #64748b;"><?= count($agents) ?> record(s) found</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Vehicle</th>
                        <th>Deliveries</th>
                        <th>Rating</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                        <tr class="empty-row">
                            <td colspan="9">
                                <i class="fas fa-truck"></i>
                                No delivery agents found<br>
                                <span style="font-size: 0.8rem; color: #94a3b8;">Click "Add New Agent" to get started</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agents as $agent): ?>
                            <tr onclick="location.href='details.php?id=<?= $agent['agent_id'] ?>'">
                                <td><strong><?= $agent['agent_id'] ?></strong></td>
                                <td><strong><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></strong></td>
                                <td><?= htmlspecialchars($agent['phone']) ?></td>
                                <td><?= htmlspecialchars($agent['email']) ?></td>
                                <td><?= htmlspecialchars($agent['vehicle_type']) ?><br><small style="color:#94a3b8;"><?= htmlspecialchars($agent['vehicle_registration']) ?></small></td>
                                <td><?= $agent['total_deliveries'] ?? 0 ?></td>
                                <td>
                                    <?php if ($agent['avg_rating'] > 0): ?>
                                        <span class="rating-stars">
                                            <?php 
                                            $stars = round($agent['avg_rating']);
                                            for ($i=1; $i<=5; $i++):
                                                if ($i <= $stars):
                                                    echo '<i class="fas fa-star"></i>';
                                                else:
                                                    echo '<i class="far fa-star"></i>';
                                                endif;
                                            endfor;
                                            ?>
                                        </span>
                                        <span style="font-size:0.75rem; color:#64748b; margin-left:4px;"><?= number_format($agent['avg_rating'], 1) ?></span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:0.75rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="status-badge status-<?= $agent['status'] ?>"><?= ucfirst($agent['status']) ?></span></td>
                                <td class="action-btns" onclick="event.stopPropagation();">
                                    <a href="details.php?id=<?= $agent['agent_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                                    <?php if ($agent['status'] !== 'active'): ?>
                                        <a href="?toggle=activate&id=<?= $agent['agent_id'] ?>" class="btn-sm btn-activate" onclick="return confirm('Activate this agent? They will start receiving delivery requests.')"><i class="fas fa-play"></i> Activate</a>
                                    <?php endif; ?>
                                    <?php if ($agent['status'] !== 'inactive' && $agent['status'] !== 'suspended'): ?>
                                        <a href="?toggle=deactivate&id=<?= $agent['agent_id'] ?>" class="btn-sm btn-deactivate" onclick="return confirm('Deactivate this agent? They will not receive new deliveries.')"><i class="fas fa-pause"></i> Deactivate</a>
                                    <?php endif; ?>
                                    <?php if ($agent['status'] !== 'suspended'): ?>
                                        <a href="?toggle=suspend&id=<?= $agent['agent_id'] ?>" class="btn-sm btn-suspend" onclick="return confirm('⚠️ Suspend this agent? They will be blocked from the system until reactivated.')"><i class="fas fa-ban"></i> Suspend</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?= $agent['agent_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this agent? This action cannot be undone.')"><i class="fas fa-trash"></i> Delete</a>
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