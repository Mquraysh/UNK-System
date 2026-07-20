<?php
// admin/settings/logs.php - Complete System Logs
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'System Logs';

// ============================================================
// CREATE TABLE IF NOT EXISTS
// ============================================================
$create_table = "CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    user_type ENUM('admin','business','customer','delivery') NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_table);

// ============================================================
// FUNCTION: addLog - Available everywhere
// ============================================================
if (!function_exists('addLog')) {
    function addLog($conn, $user_id, $user_type, $action, $details = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = mysqli_prepare($conn, "INSERT INTO system_logs (user_id, user_type, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $user_type, $action, $details, $ip, $user_agent);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
}

// ============================================================
// RECORD THIS PAGE VIEW
// ============================================================
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['role'] ?? 'admin';
addLog($conn, $user_id, $user_type, 'view', 'Viewed system logs page');

// ============================================================
// HANDLE CLEAR LOGS
// ============================================================
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    addLog($conn, $user_id, $user_type, 'delete', 'Cleared all system logs');
    mysqli_query($conn, "TRUNCATE TABLE system_logs");
    $_SESSION['flash_message'] = "All logs cleared successfully.";
    $_SESSION['flash_type'] = "success";
    header("Location: logs.php");
    exit();
}

// ============================================================
// GET FILTERS
// ============================================================
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['page']) ? ((int)$_GET['page'] - 1) * $limit : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Build WHERE clause
$where = "WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'";

if (!empty($type_filter)) {
    $where .= " AND action = '" . mysqli_real_escape_string($conn, $type_filter) . "'";
}
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (details LIKE '%$search_esc%' OR action LIKE '%$search_esc%' OR ip_address LIKE '%$search_esc%' OR user_agent LIKE '%$search_esc%')";
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM system_logs $where";
$count_result = mysqli_query($conn, $count_sql);
$total_logs = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_logs / $limit);

// Get logs with user details
$sql = "SELECT 
            l.*,
            CASE 
                WHEN l.user_type = 'admin' THEN (SELECT full_name FROM users WHERE user_id = l.user_id)
                WHEN l.user_type = 'business' THEN (SELECT business_name FROM businesses WHERE user_id = l.user_id)
                WHEN l.user_type = 'customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE user_id = l.user_id)
                WHEN l.user_type = 'delivery' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM delivery_agents WHERE user_id = l.user_id)
                ELSE NULL
            END as user_display_name
        FROM system_logs l
        $where
        ORDER BY l.created_at DESC
        LIMIT $offset, $limit";

$result = mysqli_query($conn, $sql);
$logs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats_sql = "SELECT action, COUNT(*) as count FROM system_logs WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to' GROUP BY action ORDER BY count DESC LIMIT 10";
$stats_result = mysqli_query($conn, $stats_sql);
$action_stats = [];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $action_stats[] = $row;
}

$today = date('Y-m-d');
$today_sql = "SELECT COUNT(*) as today_count FROM system_logs WHERE DATE(created_at) = '$today'";
$today_result = mysqli_query($conn, $today_sql);
$today_count = mysqli_fetch_assoc($today_result)['today_count'];

$types_result = mysqli_query($conn, "SELECT DISTINCT action FROM system_logs ORDER BY action");
$log_types = [];
while ($row = mysqli_fetch_assoc($types_result)) {
    $log_types[] = $row['action'];
}

$user_stats_sql = "SELECT user_type, COUNT(*) as count FROM system_logs WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to' AND user_type IS NOT NULL GROUP BY user_type ORDER BY count DESC";
$user_stats_result = mysqli_query($conn, $user_stats_sql);
$user_stats = [];
while ($row = mysqli_fetch_assoc($user_stats_result)) {
    $user_stats[] = $row;
}

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
    <title>System Logs | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        .report-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; transition: all 0.3s; }
        @media (max-width: 1024px) { .report-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { .report-content { padding: 15px; } }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
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
        
        .btn-back {
            background: #64748b;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        
        .btn-clear {
            background: #ef4444;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-clear:hover { background: #dc2626; transform: translateY(-2px); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .stat-card .label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .stat-card .value.orange { color: #e67e22; }
        .stat-card .value.blue { color: #3b82f6; }
        .stat-card .value.green { color: #10b981; }
        .stat-card .value.purple { color: #8b5cf6; }
        
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
        
        .action-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        .action-tag {
            background: #f1f5f9;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .action-tag .count {
            font-weight: 700;
            color: #e67e22;
            margin-left: 4px;
        }
        
        .user-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .user-stat {
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
        }
        .user-stat .badge {
            font-weight: 600;
        }
        
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
        .table-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 12px 16px;
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
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #f8fafc; }
        
        .log-action {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .log-action.login { background: #dbeafe; color: #2563eb; }
        .log-action.logout { background: #fef3c7; color: #d97706; }
        .log-action.create { background: #d1fae5; color: #059669; }
        .log-action.update { background: #ede9fe; color: #5b21b6; }
        .log-action.delete { background: #fee2e2; color: #dc2626; }
        .log-action.view { background: #e0f2fe; color: #0284c7; }
        .log-action.error { background: #fecaca; color: #dc2626; }
        .log-action.export { background: #fef3c7; color: #d97706; }
        .log-action.import { background: #d1fae5; color: #059669; }
        
        .user-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }
        .user-badge.admin { background: #e0e7ff; color: #3730a3; }
        .user-badge.business { background: #fed7aa; color: #c2410c; }
        .user-badge.customer { background: #d1fae5; color: #059669; }
        .user-badge.delivery { background: #dbeafe; color: #2563eb; }
        .user-badge.system { background: #e2e8f0; color: #64748b; }
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
        }
        .pagination a:hover { background: #e67e22; color: white; border-color: #e67e22; }
        .pagination .active { background: #e67e22; color: white; border-color: #e67e22; }
        .pagination .disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }
        
        .text-muted { color: #94a3b8; }
        .fw-bold { font-weight: 700; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-buttons { display: flex; gap: 10px; }
            .filter-buttons .btn-filter, .filter-buttons .btn-reset { flex: 1; text-align: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .data-table td { font-size: 12px; padding: 8px 6px; }
            .data-table th { font-size: 10px; padding: 8px 6px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="report-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-alt"></i> System Logs</h1>
            <p>View and monitor all system activities and events</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <button onclick="if(confirm('⚠️ Clear all logs? This action cannot be undone.')) { window.location.href='?clear=1'; }" class="btn-clear">
                <i class="fas fa-trash-alt"></i> Clear Logs
            </button>
        </div>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label"><i class="fas fa-list"></i> Total Logs</div>
            <div class="value orange"><?php echo number_format($total_logs); ?></div>
        </div>
        <div class="stat-card">
            <div class="label"><i class="fas fa-calendar-day"></i> Today's Activity</div>
            <div class="value blue"><?php echo number_format($today_count); ?></div>
        </div>
        <div class="stat-card">
            <div class="label"><i class="fas fa-clock"></i> Date Range</div>
            <div class="value" style="font-size:14px;"><?php echo date('M d', strtotime($date_from)); ?> - <?php echo date('M d', strtotime($date_to)); ?></div>
        </div>
        <div class="stat-card">
            <div class="label"><i class="fas fa-tags"></i> Unique Actions</div>
            <div class="value purple"><?php echo count($log_types); ?></div>
        </div>
    </div>

    <?php if (!empty($user_stats)): ?>
    <div class="user-stats">
        <span style="font-weight:600; font-size:13px; color:#64748b; margin-right:8px;">Activity by role:</span>
        <?php foreach ($user_stats as $u): ?>
        <span class="user-stat">
            <span class="user-badge <?php echo $u['user_type']; ?>"><?php echo ucfirst($u['user_type']); ?></span>
            <span class="badge"><?php echo $u['count']; ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($action_stats)): ?>
    <div class="action-stats">
        <?php foreach ($action_stats as $stat): ?>
        <span class="action-tag">
            <?php echo ucfirst(str_replace('_', ' ', $stat['action'])); ?>
            <span class="count"><?php echo $stat['count']; ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="filters-bar">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; width:100%; align-items:flex-end;">
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="from" class="filter-input" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="to" class="filter-input" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label>Action Type</label>
                <select name="type" class="filter-input">
                    <option value="">All Actions</option>
                    <?php foreach ($log_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group" style="min-width:100px;">
                <label>Per Page</label>
                <select name="limit" class="filter-input" onchange="this.form.submit()">
                    <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                </select>
            </div>
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="logs.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> System Logs</h3>
            <span class="text-muted"><?php echo $total_logs; ?> record(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr class="empty-row">
                            <td colspan="6">
                                <i class="fas fa-inbox"></i>
                                No logs found for this period
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): 
                            $action_class = 'view';
                            if (strpos($log['action'], 'login') !== false) $action_class = 'login';
                            elseif (strpos($log['action'], 'logout') !== false) $action_class = 'logout';
                            elseif (strpos($log['action'], 'create') !== false) $action_class = 'create';
                            elseif (strpos($log['action'], 'update') !== false) $action_class = 'update';
                            elseif (strpos($log['action'], 'delete') !== false) $action_class = 'delete';
                            elseif (strpos($log['action'], 'error') !== false) $action_class = 'error';
                            elseif (strpos($log['action'], 'export') !== false) $action_class = 'export';
                            elseif (strpos($log['action'], 'import') !== false) $action_class = 'import';
                        ?>
                        <tr>
                            <td><?php echo $log['log_id']; ?></td>
                            <td>
                                <?php if ($log['user_id']): ?>
                                    <span class="user-badge <?php echo $log['user_type']; ?>">
                                        <?php echo $log['user_type']; ?>
                                    </span>
                                    <?php echo htmlspecialchars($log['user_display_name'] ?? 'User #' . $log['user_id']); ?>
                                <?php else: ?>
                                    <span class="user-badge system">System</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="log-action <?php echo $action_class; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $details = $log['details'] ?? '-';
                                if (strlen($details) > 100) {
                                    echo htmlspecialchars(substr($details, 0, 100)) . '...';
                                } else {
                                    echo htmlspecialchars($details);
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&type=<?php echo $type_filter; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
        <?php else: ?>
            <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
        <?php endif; ?>
        
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&type=<?php echo $type_filter; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&from=<?php echo $date_from; ?>&to=<?php echo $date_to; ?>&type=<?php echo $type_filter; ?>&search=<?php echo urlencode($search); ?>&limit=<?php echo $limit; ?>">
                Next <i class="fas fa-chevron-right"></i>
            </a>
        <?php else: ?>
            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>