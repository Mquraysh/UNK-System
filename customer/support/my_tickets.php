<?php
// customer/my_tickets.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer data
$cust_res = mysqli_query($conn, "SELECT c.*, u.email, u.phone FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.user_id = '$user_id'");
if (mysqli_num_rows($cust_res) == 0) {
    header("Location: register.php");
    exit();
}
$customer = mysqli_fetch_assoc($cust_res);
$customer_id = $customer['customer_id'];

// Handle delete requests
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    $check_sql = "SELECT ticket_no FROM support_tickets WHERE id = $delete_id AND created_by_type = 'customer' AND created_by_id = $customer_id";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        $ticket_data = mysqli_fetch_assoc($check_result);
        mysqli_begin_transaction($conn);
        try {
            mysqli_query($conn, "DELETE FROM support_replies WHERE ticket_id = $delete_id");
            mysqli_query($conn, "DELETE FROM support_tickets WHERE id = $delete_id");
            mysqli_commit($conn);
            $_SESSION['flash_message'] = "Ticket " . $ticket_data['ticket_no'] . " deleted successfully.";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = "Error deleting ticket.";
            $_SESSION['flash_type'] = "danger";
        }
        header("Location: my_tickets.php");
        exit();
    }
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_tickets'])) {
    $selected = array_map('intval', $_POST['selected_tickets']);
    $ids = implode(',', $selected);
    $check_sql = "SELECT COUNT(*) as count FROM support_tickets WHERE id IN ($ids) AND created_by_type = 'customer' AND created_by_id = $customer_id";
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

// Handle flash messages
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// PAGINATION AND FILTERS - FIXED
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filter values with proper defaults
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Build WHERE clause
$where = "(created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)";

if ($status_filter != 'all') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND status = '$status_esc'";
}
if ($priority_filter != 'all') {
    $priority_esc = mysqli_real_escape_string($conn, $priority_filter);
    $where .= " AND priority = '$priority_esc'";
}
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (subject LIKE '%$search_esc%' OR message LIKE '%$search_esc%' OR ticket_no LIKE '%$search_esc%')";
}

// Sorting
$order_by = "created_at DESC";
if ($sort == 'oldest') $order_by = "created_at ASC";
if ($sort == 'priority_high') $order_by = "FIELD(priority, 'high', 'medium', 'low'), created_at DESC";
if ($sort == 'priority_low') $order_by = "FIELD(priority, 'low', 'medium', 'high'), created_at DESC";
if ($sort == 'status') $order_by = "status ASC, created_at DESC";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM support_tickets WHERE $where";
$count_res = mysqli_query($conn, $count_sql);
$total_tickets = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_tickets / $limit);

// Fetch tickets
$tickets = [];
$sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM support_replies WHERE ticket_id = t.id AND is_read = 0 AND reply_by_type != 'customer') as unread_replies,
        CASE 
            WHEN t.assigned_to_type = 'admin' THEN 'Admin'
            WHEN t.assigned_to_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.assigned_to_id)
            ELSE 'System'
        END as assigned_name_display
        FROM support_tickets t 
        WHERE $where 
        ORDER BY $order_by 
        LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $tickets[] = $row;
}

// Statistics
$stats = [];
$stats['all'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE (created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)"))['c'];
$stats['open'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE ((created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)) AND status = 'open'"))['c'];
$stats['in_progress'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE ((created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)) AND status = 'in_progress'"))['c'];
$stats['resolved'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE ((created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)) AND status = 'resolved'"))['c'];
$stats['closed'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE ((created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)) AND status = 'closed'"))['c'];
$stats['high_priority'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE ((created_by_type = 'customer' AND created_by_id = $customer_id) OR (assigned_to_type = 'customer' AND assigned_to_id = $customer_id)) AND priority = 'high'"))['c'];

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Support Tickets - UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .dashboard-wrapper { display: flex; }
        .dashboard-content { flex: 1; margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; font-size: 14px; margin-top: 5px; }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 16px; padding: 18px; text-align: center; transition: all 0.3s; border: 1px solid #e2e8f0; text-decoration: none; display: block; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1); border-color: #e67e22; }
        .stat-card.active { border-color: #e67e22; background: #fff7ed; }
        .stat-number { font-size: 28px; font-weight: 800; color: #e67e22; margin-bottom: 5px; }
        .stat-label { font-size: 12px; color: #64748b; font-weight: 500; }
        .stat-icon { font-size: 20px; margin-bottom: 8px; color: #e67e22; }
        
        .filter-bar { background: white; border-radius: 16px; padding: 15px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; border: 1px solid #e2e8f0; }
        .filter-group { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-label { font-size: 12px; font-weight: 600; color: #64748b; }
        .filter-btn { padding: 6px 16px; border-radius: 30px; text-decoration: none; font-size: 12px; font-weight: 500; transition: all 0.2s; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .filter-btn:hover, .filter-btn.active { background: #e67e22; color: white; border-color: #e67e22; }
        .sort-select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 30px; font-size: 12px; background: white; cursor: pointer; }
        .search-box { display: flex; gap: 10px; }
        .search-input { padding: 8px 15px; border: 1px solid #e2e8f0; border-radius: 30px; font-size: 13px; width: 250px; }
        
        .bulk-actions { background: #f8fafc; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; display: none; }
        .bulk-actions.show { display: flex; }
        .bulk-delete-btn { background: #dc2626; color: white; border: none; padding: 8px 20px; border-radius: 30px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .bulk-delete-btn:hover { background: #b91c1c; }
        .select-all-btn { background: #e2e8f0; border: none; padding: 6px 15px; border-radius: 20px; font-size: 11px; cursor: pointer; }
        
        .tickets-container { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
        .tickets-table { width: 100%; border-collapse: collapse; }
        .tickets-table th { background: #f8fafc; padding: 16px 15px; text-align: left; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .tickets-table td { padding: 16px 15px; font-size: 13px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .tickets-table tr:hover { background: #fffbeb; cursor: pointer; }
        .ticket-checkbox { width: 30px; text-align: center; }
        .ticket-checkbox input { cursor: pointer; width: 16px; height: 16px; }
        .ticket-no { font-weight: 700; color: #e67e22; font-family: monospace; font-size: 13px; }
        .unread-badge { background: #e74c3c; color: white; border-radius: 20px; padding: 2px 8px; font-size: 9px; font-weight: 600; margin-left: 8px; }
        
        .direction-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .direction-sent { background: #dbeafe; color: #2563eb; }
        .direction-received { background: #fef3c7; color: #d97706; }
        
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .status-badge i { margin-right: 5px; font-size: 9px; }
        .status-open { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-resolved { background: #d1fae5; color: #059669; }
        .status-closed { background: #e2e8f0; color: #64748b; }
        
        .priority-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .priority-low { background: #d1fae5; color: #059669; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .view-btn, .delete-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 30px; text-decoration: none; font-size: 11px; font-weight: 600; }
        .view-btn { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; }
        .view-btn:hover { background: #e67e22; color: white; }
        .delete-btn { background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; }
        .delete-btn:hover { background: #dc2626; color: white; }
        
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 15px; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; }
        .empty-state p { color: #64748b; font-size: 13px; margin-bottom: 20px; }
        
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 25px; margin-bottom: 20px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 500; }
        .pagination a { background: white; color: #64748b; border: 1px solid #e2e8f0; }
        .pagination a:hover { background: #e67e22; color: white; transform: translateY(-2px); }
        .pagination .active { background: #e67e22; color: white; border-color: #e67e22; }
        .pagination .disabled { background: #f1f5f9; color: #cbd5e1; cursor: not-allowed; }
        
        .new-ticket-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: linear-gradient(135deg, #e67e22, #d35400); color: white; border-radius: 40px; text-decoration: none; font-size: 13px; font-weight: 600; }
        .new-ticket-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,126,34,0.3); }
        
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1024px) { .dashboard-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { 
            .dashboard-content { padding: 15px; } 
            .stats-grid { grid-template-columns: repeat(2, 1fr); } 
            .filter-bar { flex-direction: column; align-items: stretch; } 
            .search-box { width: 100%; } 
            .search-input { width: 100%; } 
            .action-buttons { flex-direction: column; }
            .tickets-table th, .tickets-table td { padding: 10px 8px; font-size: 11px; }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-content">
        <div class="page-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h1><i class="fas fa-ticket-alt"></i> My Support Tickets</h1>
                    <p>View, track, and manage all your support requests</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="index.php" class="new-ticket-btn"><i class="fas fa-plus-circle"></i> Create New Ticket</a>
                </div>
            </div>
        </div>

        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo $flash_type; ?>">
                <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : ($flash_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i> 
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="?status=all&priority=all&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $status_filter == 'all' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-number"><?php echo $stats['all']; ?></div>
                <div class="stat-label">All Tickets</div>
            </a>
            <a href="?status=open&priority=all&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $status_filter == 'open' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-circle" style="color: #d97706;"></i></div>
                <div class="stat-number"><?php echo $stats['open']; ?></div>
                <div class="stat-label">Open</div>
            </a>
            <a href="?status=in_progress&priority=all&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-spinner fa-pulse"></i></div>
                <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                <div class="stat-label">In Progress</div>
            </a>
            <a href="?status=resolved&priority=all&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: #059669;"></i></div>
                <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </a>
            <a href="?status=closed&priority=all&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $status_filter == 'closed' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-times-circle" style="color: #64748b;"></i></div>
                <div class="stat-number"><?php echo $stats['closed']; ?></div>
                <div class="stat-label">Closed</div>
            </a>
            <a href="?priority=high&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="stat-card <?php echo $priority_filter == 'high' ? 'active' : ''; ?>">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i></div>
                <div class="stat-number"><?php echo $stats['high_priority']; ?></div>
                <div class="stat-label">High Priority</div>
            </a>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label"><i class="fas fa-filter"></i> Status:</span>
                <a href="?status=all&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="?status=open&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'open' ? 'active' : ''; ?>">Open</a>
                <a href="?status=in_progress&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
                <a href="?status=resolved&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
                <a href="?status=closed&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $status_filter == 'closed' ? 'active' : ''; ?>">Closed</a>
            </div>
            <div class="filter-group">
                <span class="filter-label"><i class="fas fa-flag"></i> Priority:</span>
                <a href="?priority=all&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $priority_filter == 'all' ? 'active' : ''; ?>">All</a>
                <a href="?priority=high&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $priority_filter == 'high' ? 'active' : ''; ?>">High</a>
                <a href="?priority=medium&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $priority_filter == 'medium' ? 'active' : ''; ?>">Medium</a>
                <a href="?priority=low&status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="filter-btn <?php echo $priority_filter == 'low' ? 'active' : ''; ?>">Low</a>
            </div>
            <div class="filter-group">
                <span class="filter-label"><i class="fas fa-sort"></i> Sort:</span>
                <select class="sort-select" onchange="window.location.href='?status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&sort=' + this.value + '<?php echo $search ? '&search='.urlencode($search) : ''; ?>'">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="priority_high" <?php echo $sort == 'priority_high' ? 'selected' : ''; ?>>Priority (High to Low)</option>
                    <option value="priority_low" <?php echo $sort == 'priority_low' ? 'selected' : ''; ?>>Priority (Low to High)</option>
                    <option value="status" <?php echo $sort == 'status' ? 'selected' : ''; ?>>Status</option>
                </select>
            </div>
            <form method="GET" class="search-box" onsubmit="return false;">
                <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <input type="hidden" name="priority" value="<?php echo $priority_filter; ?>">
                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                <input type="text" name="search" class="search-input" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
                <button type="button" class="filter-btn" onclick="doSearch()" style="margin:0;">Search</button>
                <?php if ($search): ?>
                    <a href="?status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&sort=<?php echo $sort; ?>" class="filter-btn">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions">
            <div>
                <span id="selectedCount">0</span> tickets selected
                <button class="select-all-btn" onclick="selectAll()">Select All</button>
                <button class="select-all-btn" onclick="deselectAll()">Deselect All</button>
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
                    <i class="fas fa-inbox"></i>
                    <h3>No tickets found</h3>
                    <p>You haven't created or received any support tickets yet.</p>
                    <a href="index.php" class="new-ticket-btn" style="display: inline-flex;"><i class="fas fa-plus-circle"></i> Create Your First Ticket</a>
                </div>
            <?php else: ?>
                <form id="bulkForm" method="POST">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th class="ticket-checkbox"><input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                                <th>Ticket</th>
                                <th>Direction</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): 
                                $direction = ($ticket['created_by_type'] == 'customer' && $ticket['created_by_id'] == $customer_id) ? 'sent' : 'received';
                            ?>
                            <tr onclick="window.location.href='ticket.php?id=<?php echo $ticket['id']; ?>'" style="cursor: pointer;">
                                <td class="ticket-checkbox" onclick="event.stopPropagation();">
                                    <input type="checkbox" class="ticket-select" value="<?php echo $ticket['id']; ?>" onclick="updateBulkActions()">
                                </td>
                                <td>
                                    <span class="ticket-no"><?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
                                    <?php if ($ticket['unread_replies'] > 0): ?>
                                        <span class="unread-badge"><?php echo $ticket['unread_replies']; ?> new</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="direction-badge direction-<?php echo $direction; ?>">
                                        <i class="fas fa-<?php echo $direction == 'sent' ? 'paper-plane' : 'inbox'; ?>"></i>
                                        <?php echo $direction == 'sent' ? 'Sent by You' : 'Received'; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars(substr($ticket['subject'], 0, 40)); ?><?php echo strlen($ticket['subject']) > 40 ? '...' : ''; ?></td>
                                <td><?php echo ucfirst($ticket['category']); ?></td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                        <i class="fas <?php echo $ticket['status'] == 'open' ? 'fa-circle' : ($ticket['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : ($ticket['status'] == 'resolved' ? 'fa-check-circle' : 'fa-times-circle')); ?>"></i>
                                        <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($ticket['assigned_name_display'] ?? 'System'); ?></td>
                                <td><span style="font-size:12px; color:#64748b;"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span></td>
                                <td class="action-buttons" onclick="event.stopPropagation();">
                                    <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="view-btn"><i class="fas fa-eye"></i> View</a>
                                    <?php if ($direction == 'sent' && !in_array($ticket['status'], ['resolved', 'closed'])): ?>
                                        <a href="?delete=1&id=<?php echo $ticket['id']; ?>" class="delete-btn" onclick="return confirm('Delete this ticket permanently?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
</div>

<script>
function doSearch() {
    var searchVal = document.getElementById('searchInput').value;
    var params = new URLSearchParams();
    
    var status = '<?php echo $status_filter; ?>';
    var priority = '<?php echo $priority_filter; ?>';
    var sort = '<?php echo $sort; ?>';
    
    if (status && status != 'all') params.set('status', status);
    if (priority && priority != 'all') params.set('priority', priority);
    if (sort) params.set('sort', sort);
    if (searchVal) params.set('search', searchVal);
    
    var url = '?' + params.toString();
    window.location.href = url;
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