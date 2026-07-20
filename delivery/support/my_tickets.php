<?php
// delivery/support/my_tickets.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get delivery agent data
$agent_res = mysqli_query($conn, "SELECT d.*, u.email FROM delivery_agents d JOIN users u ON d.user_id = u.user_id WHERE d.user_id = '$user_id'");
if (mysqli_num_rows($agent_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$agent = mysqli_fetch_assoc($agent_res);
$agent_id = $agent['agent_id'];
$agent_name = $agent['first_name'] . ' ' . $agent['last_name'];
$agent_email = $agent['email'];
$vehicle_type = $agent['vehicle_type'] ?? 'Unknown';

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_tickets'])) {
    $selected = array_map('intval', $_POST['selected_tickets']);
    $ids = implode(',', $selected);
    
    $check_sql = "SELECT COUNT(*) as count FROM support_tickets WHERE id IN ($ids) AND created_by_type = 'delivery' AND created_by_id = $agent_id";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_fetch_assoc($check_result)['count'] == count($selected)) {
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM support_replies WHERE ticket_id IN ($ids)");
            mysqli_query($conn, "DELETE FROM support_tickets WHERE id IN ($ids)");
            mysqli_commit($conn);
            $_SESSION['flash_message'] = count($selected) . " tickets deleted successfully.";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = "Error deleting tickets.";
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: my_tickets.php");
    exit();
}

// Handle individual delete
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $check_sql = "SELECT ticket_no FROM support_tickets WHERE id = $delete_id AND created_by_type = 'delivery' AND created_by_id = $agent_id";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        $ticket_data = mysqli_fetch_assoc($check_result);
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM support_replies WHERE ticket_id = $delete_id");
            mysqli_query($conn, "DELETE FROM support_tickets WHERE id = $delete_id");
            mysqli_commit($conn);
            $_SESSION['flash_message'] = "Ticket #" . $ticket_data['ticket_no'] . " deleted.";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = "Error deleting ticket.";
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: my_tickets.php");
    exit();
}

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause for unified table
$where = "(created_by_type = 'delivery' AND created_by_id = $agent_id) OR (assigned_to_type = 'delivery' AND assigned_to_id = $agent_id)";

if ($status_filter != 'all') {
    $status = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND status = '$status'";
}
if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (subject LIKE '%$search_esc%' OR ticket_no LIKE '%$search_esc%' OR message LIKE '%$search_esc%')";
}

// Add type filter (sent/received)
if ($type_filter == 'sent') {
    $where .= " AND created_by_type = 'delivery' AND created_by_id = $agent_id";
} elseif ($type_filter == 'received') {
    $where .= " AND assigned_to_type = 'delivery' AND assigned_to_id = $agent_id";
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM support_tickets WHERE $where";
$count_res = mysqli_query($conn, $count_sql);
$total_tickets = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_tickets / $limit);

// Fetch tickets
$tickets = [];
$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM support_replies WHERE ticket_id = t.id) as reply_count,
        CASE 
            WHEN t.assigned_to_type = 'admin' THEN 'Admin'
            WHEN t.assigned_to_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.assigned_to_id)
            ELSE 'Unknown'
        END as recipient_name,
        CASE 
            WHEN t.created_by_type = 'delivery' AND t.created_by_id = $agent_id THEN 'sent'
            ELSE 'received'
        END as direction
        FROM support_tickets t 
        WHERE $where 
        ORDER BY t.created_at DESC 
        LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $tickets[] = $row;
}

// Statistics
$stats_sent = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE created_by_type = 'delivery' AND created_by_id = $agent_id"))['c'];
$stats_received = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE assigned_to_type = 'delivery' AND assigned_to_id = $agent_id"))['c'];
$stats_open_sent = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE created_by_type = 'delivery' AND created_by_id = $agent_id AND status = 'open'"))['c'];
$stats_in_progress_sent = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE created_by_type = 'delivery' AND created_by_id = $agent_id AND status = 'in_progress'"))['c'];
$stats_resolved_sent = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE created_by_type = 'delivery' AND created_by_id = $agent_id AND status = 'resolved'"))['c'];
$stats_closed_sent = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE created_by_type = 'delivery' AND created_by_id = $agent_id AND status = 'closed'"))['c'];
$stats_open_received = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE assigned_to_type = 'delivery' AND assigned_to_id = $agent_id AND status = 'open'"))['c'];
$stats_high_priority = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE (created_by_type = 'delivery' AND created_by_id = $agent_id) AND priority = 'high'"))['c'];

$flash = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Support Tickets | UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
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
            margin-top: 0.3rem;
        }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
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
        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            display: block;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: #e67e22;
        }
        .stat-card.active {
            border-color: #e67e22;
            background: #fdf2e9;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
        }
        .stat-icon {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #e67e22;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 1rem;
            padding: 0.9rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.9rem;
            border: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
        }
        .filter-btn {
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .filter-btn:hover, .filter-btn.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .search-box {
            display: flex;
            gap: 0.6rem;
        }
        .search-input {
            padding: 0.45rem 0.9rem;
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            font-size: 0.8rem;
            width: 250px;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: #f8fafc;
            padding: 0.7rem 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.6rem;
            display: none;
        }
        .bulk-actions.show {
            display: flex;
        }
        .bulk-delete-btn {
            background: #dc2626;
            color: white;
            border: none;
            padding: 0.45rem 1.25rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
        }
        .bulk-delete-btn:hover {
            background: #b91c1c;
        }
        .select-all-btn {
            background: #e2e8f0;
            border: none;
            padding: 0.35rem 0.9rem;
            border-radius: 1.25rem;
            font-size: 0.65rem;
            cursor: pointer;
        }
        
        /* Tickets Table */
        .tickets-container {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ticket-table th {
            background: #fafcff;
            padding: 1rem 0.9rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .ticket-table td {
            padding: 1rem 0.9rem;
            font-size: 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .ticket-table tr:hover {
            background: #fffbeb;
            cursor: pointer;
        }
        .ticket-checkbox {
            width: 30px;
            text-align: center;
        }
        .ticket-checkbox input {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        .ticket-no {
            font-weight: 700;
            color: #e67e22;
            font-family: monospace;
            font-size: 0.8rem;
        }
        
        /* Direction Badge */
        .direction-badge {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 1.25rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .direction-sent {
            background: #dbeafe;
            color: #2563eb;
        }
        .direction-received {
            background: #fef3c7;
            color: #d97706;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .status-badge i {
            margin-right: 0.25rem;
            font-size: 0.55rem;
        }
        .status-open {
            background: #fef3c7;
            color: #d97706;
        }
        .status-in_progress {
            background: #dbeafe;
            color: #2563eb;
        }
        .status-resolved {
            background: #d1fae5;
            color: #059669;
        }
        .status-closed {
            background: #e2e8f0;
            color: #64748b;
        }
        
        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 1.25rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .priority-low {
            background: #d1fae5;
            color: #059669;
        }
        .priority-medium {
            background: #fef3c7;
            color: #d97706;
        }
        .priority-high {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .view-btn, .delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.65rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .view-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }
        .view-btn:hover {
            background: #e67e22;
            color: white;
        }
        .delete-btn {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .delete-btn:hover {
            background: #dc2626;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3.5rem 1.25rem;
        }
        .empty-state i {
            font-size: 3.5rem;
            color: #cbd5e1;
            margin-bottom: 0.9rem;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            color: #64748b;
            font-size: 0.8rem;
            margin-bottom: 1.25rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.6rem;
            margin-top: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .pagination a, .pagination span {
            padding: 0.45rem 0.85rem;
            border-radius: 0.6rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: 0.2s;
        }
        .pagination a {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .pagination a:hover {
            background: #e67e22;
            color: white;
            transform: translateY(-2px);
        }
        .pagination .active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .pagination .disabled {
            background: #f1f5f9;
            color: #cbd5e1;
            cursor: not-allowed;
        }
        
        .new-ticket-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            border-radius: 2.5rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .new-ticket-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230,126,34,0.3);
        }
        
        /* Mobile Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 1.25rem;
            }
        }
        
        @media (max-width: 768px) {
            .delivery-content {
                padding: 0.9rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .search-box {
                width: 100%;
            }
            .search-input {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
            }
            .ticket-table th,
            .ticket-table td {
                padding: 0.6rem 0.5rem;
                font-size: 0.65rem;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.9rem;">
            <div>
                <h1><i class="fas fa-ticket-alt"></i> My Support Tickets</h1>
                <p>View and manage all your support tickets</p>
            </div>
            <div style="display: flex; gap: 0.6rem;">
                <a href="index.php" class="new-ticket-btn"><i class="fas fa-plus-circle"></i> Create New Ticket</a>
            </div>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : ($flash_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i> 
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <a href="?type=all&status=all" class="stat-card">
            <div class="stat-icon"><i class="fas fa-list"></i></div>
            <div class="stat-number"><?php echo $stats_sent + $stats_received; ?></div>
            <div class="stat-label">Total Tickets</div>
        </a>
        <a href="?type=sent&status=all" class="stat-card">
            <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-number"><?php echo $stats_sent; ?></div>
            <div class="stat-label">Sent by You</div>
        </a>
        <a href="?type=received&status=all" class="stat-card">
            <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            <div class="stat-number"><?php echo $stats_received; ?></div>
            <div class="stat-label">Received</div>
        </a>
        <a href="?type=all&status=open" class="stat-card">
            <div class="stat-icon"><i class="fas fa-circle" style="color: #d97706;"></i></div>
            <div class="stat-number"><?php echo $stats_open_sent + $stats_open_received; ?></div>
            <div class="stat-label">Open</div>
        </a>
        <a href="?type=all&status=in_progress" class="stat-card">
            <div class="stat-icon"><i class="fas fa-spinner fa-pulse"></i></div>
            <div class="stat-number"><?php echo $stats_in_progress_sent; ?></div>
            <div class="stat-label">In Progress</div>
        </a>
        <a href="?type=all&priority=high" class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i></div>
            <div class="stat-number"><?php echo $stats_high_priority; ?></div>
            <div class="stat-label">High Priority</div>
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-filter"></i> Type:</span>
            <a href="?type=all&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $type_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?type=sent&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $type_filter == 'sent' ? 'active' : ''; ?>">Sent by Me</a>
            <a href="?type=received&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $type_filter == 'received' ? 'active' : ''; ?>">Received</a>
        </div>
        <div class="filter-group">
            <span class="filter-label"><i class="fas fa-flag"></i> Status:</span>
            <a href="?type=<?php echo $type_filter; ?>&status=all" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?type=<?php echo $type_filter; ?>&status=open" class="filter-btn <?php echo $status_filter == 'open' ? 'active' : ''; ?>">Open</a>
            <a href="?type=<?php echo $type_filter; ?>&status=in_progress" class="filter-btn <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?type=<?php echo $type_filter; ?>&status=resolved" class="filter-btn <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
            <a href="?type=<?php echo $type_filter; ?>&status=closed" class="filter-btn <?php echo $status_filter == 'closed' ? 'active' : ''; ?>">Closed</a>
        </div>
        <form method="GET" class="search-box" onsubmit="return false;">
            <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="text" name="search" class="search-input" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
            <button type="button" class="filter-btn" onclick="doSearch()" style="margin:0;">Search</button>
            <?php if ($search): ?>
                <a href="?type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>" class="filter-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bulk Actions -->
    <div class="bulk-actions" id="bulkActions">
        <div>
            <span id="selectedCount">0</span> tickets selected
            <button type="button" class="select-all-btn" onclick="selectAll()">Select All</button>
            <button type="button" class="select-all-btn" onclick="deselectAll()">Deselect All</button>
        </div>
        <form method="POST" onsubmit="return confirmBulkDelete()">
            <input type="hidden" name="selected_tickets" id="selectedTicketsInput" value="">
            <button type="submit" name="bulk_delete" class="bulk-delete-btn"><i class="fas fa-trash-alt"></i> Delete Selected</button>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="tickets-container">
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <i class="fas fa-ticket-alt"></i>
                <h3>No tickets found</h3>
                <p>You haven't created or received any support tickets yet.</p>
                <a href="index.php" class="new-ticket-btn" style="display: inline-flex;"><i class="fas fa-plus-circle"></i> Create Your First Ticket</a>
            </div>
        <?php else: ?>
            <form id="bulkForm" method="POST">
                <table class="ticket-table">
                    <thead>
                        <tr>
                            <th class="ticket-checkbox"><input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                            <th>Ticket #</th>
                            <th>Direction</th>
                            <th>Subject</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tickets as $ticket): ?>
                        <tr onclick="window.location.href='ticket.php?id=<?php echo $ticket['id']; ?>&source=<?php echo $ticket['direction']; ?>'" style="cursor: pointer;">
                            <td class="ticket-checkbox" onclick="event.stopPropagation();">
                                <input type="checkbox" class="ticket-select" value="<?php echo $ticket['id']; ?>" data-direction="<?php echo $ticket['direction']; ?>" onclick="updateBulkActions()">
                              </td>
                            <td><span class="ticket-no">#<?php echo htmlspecialchars($ticket['ticket_no']); ?></span></td>
                            <td>
                                <span class="direction-badge direction-<?php echo $ticket['direction']; ?>">
                                    <i class="fas fa-<?php echo $ticket['direction'] == 'sent' ? 'paper-plane' : 'inbox'; ?>"></i>
                                    <?php echo $ticket['direction'] == 'sent' ? 'Sent' : 'Received'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars(substr($ticket['subject'], 0, 45)); ?><?php echo strlen($ticket['subject']) > 45 ? '...' : ''; ?></td>
                            <td><?php echo ucfirst($ticket['category']); ?></td>
                            <td><span class="priority-badge priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                            <td><span class="status-badge status-<?php echo $ticket['status']; ?>"><i class="fas <?php echo $ticket['status'] == 'open' ? 'fa-circle' : ($ticket['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : ($ticket['status'] == 'resolved' ? 'fa-check-circle' : 'fa-times-circle')); ?>"></i> <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?></span></td>
                            <td><span style="font-size:0.7rem; color:#64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span></td>
                            <td class="action-buttons" onclick="event.stopPropagation();">
                                <a href="ticket.php?id=<?php echo $ticket['id']; ?>&source=<?php echo $ticket['direction']; ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
                                <?php if (!in_array($ticket['status'], ['resolved', 'closed']) && $ticket['direction'] == 'sent'): ?>
                                    <a href="?delete=<?php echo $ticket['id']; ?>" class="delete-btn" onclick="return confirm('Delete this ticket permanently?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                <?php endif; ?>
                            </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function doSearch() {
    var searchVal = document.getElementById('searchInput').value;
    var url = new URL(window.location.href);
    if (searchVal) {
        url.searchParams.set('search', searchVal);
    } else {
        url.searchParams.delete('search');
    }
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') doSearch();
});

function updateBulkActions() {
    var checkboxes = document.querySelectorAll('.ticket-select');
    var checked = Array.from(checkboxes).filter(cb => cb.checked);
    var bulkDiv = document.getElementById('bulkActions');
    var selectedCountSpan = document.getElementById('selectedCount');
    var selectedInput = document.getElementById('selectedTicketsInput');
    
    if (checked.length > 0) {
        bulkDiv.classList.add('show');
        selectedCountSpan.textContent = checked.length;
        var selectedIds = checked.map(cb => cb.value).join(',');
        selectedInput.value = selectedIds;
    } else {
        bulkDiv.classList.remove('show');
        selectedInput.value = '';
    }
}

function toggleSelectAll(checkbox) {
    var checkboxes = document.querySelectorAll('.ticket-select');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBulkActions();
}

function selectAll() {
    var checkboxes = document.querySelectorAll('.ticket-select');
    checkboxes.forEach(cb => cb.checked = true);
    var mainCheckbox = document.getElementById('selectAllCheckbox');
    if (mainCheckbox) mainCheckbox.checked = true;
    updateBulkActions();
}

function deselectAll() {
    var checkboxes = document.querySelectorAll('.ticket-select');
    checkboxes.forEach(cb => cb.checked = false);
    var mainCheckbox = document.getElementById('selectAllCheckbox');
    if (mainCheckbox) mainCheckbox.checked = false;
    updateBulkActions();
}

function confirmBulkDelete() {
    var selected = document.getElementById('selectedTicketsInput').value;
    if (selected && confirm('Are you sure you want to delete ' + selected.split(',').length + ' tickets? This action cannot be undone.')) {
        return true;
    }
    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    updateBulkActions();
    
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../my_tickets.php' || links[i].getAttribute('href') === 'my_tickets.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>