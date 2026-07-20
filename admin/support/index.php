<?php
// admin/support/index.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============================================================
// GET ADMIN DATA
// ============================================================
$user_id = $_SESSION['user_id'];
$admin_res = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$user_id'");
$admin = mysqli_fetch_assoc($admin_res);

// ============================================================
// CREATE UNIFIED SUPPORT TABLES IF MISSING
// ============================================================
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS support_tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_no VARCHAR(20) NOT NULL UNIQUE,
    created_by_type ENUM('customer','business','delivery','admin') NOT NULL,
    created_by_id INT NOT NULL,
    assigned_to_type ENUM('customer','business','delivery','admin') NULL,
    assigned_to_id INT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    attachment VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_created (created_by_type, created_by_id),
    INDEX idx_assigned (assigned_to_type, assigned_to_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority)
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS support_replies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ticket_id INT NOT NULL,
    reply_by_type ENUM('customer','business','delivery','admin') NOT NULL,
    reply_by_id INT NOT NULL,
    message TEXT NOT NULL,
    attachment VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
)");

// ============================================================
// HANDLE TICKET ASSIGNMENT
// ============================================================
if (isset($_POST['assign_ticket']) && isset($_POST['ticket_id']) && isset($_POST['assign_to_type']) && isset($_POST['assign_to_id'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $assign_to_type = $_POST['assign_to_type'];
    $assign_to_id = (int)$_POST['assign_to_id'];
    
    // Get name and email of assignee
    $assignee_name = '';
    $assignee_email = '';
    
    if ($assign_to_type == 'admin') {
        $assignee_name = 'System Administrator';
        $assignee_email = 'admin@unksystem.com';
    } elseif ($assign_to_type == 'business') {
        $bus = mysqli_fetch_assoc(mysqli_query($conn, "SELECT business_name, u.email FROM businesses b JOIN users u ON b.user_id = u.user_id WHERE b.business_id = $assign_to_id"));
        if ($bus) {
            $assignee_name = $bus['business_name'];
            $assignee_email = $bus['email'];
        }
    } elseif ($assign_to_type == 'delivery') {
        $del = mysqli_fetch_assoc(mysqli_query($conn, "SELECT CONCAT(first_name,' ',last_name) as name, u.email FROM delivery_agents d JOIN users u ON d.user_id = u.user_id WHERE d.agent_id = $assign_to_id"));
        if ($del) {
            $assignee_name = $del['name'];
            $assignee_email = $del['email'];
        }
    } elseif ($assign_to_type == 'customer') {
        $cust = mysqli_fetch_assoc(mysqli_query($conn, "SELECT CONCAT(first_name,' ',last_name) as name, u.email FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.customer_id = $assign_to_id"));
        if ($cust) {
            $assignee_name = $cust['name'];
            $assignee_email = $cust['email'];
        }
    }
    
    $update_sql = "UPDATE support_tickets SET 
                    assigned_to_type = '$assign_to_type', 
                    assigned_to_id = $assign_to_id,
                    assigned_name = '$assignee_name',
                    assigned_email = '$assignee_email',
                    status = 'in_progress',
                    updated_at = NOW()
                   WHERE id = $ticket_id";
    mysqli_query($conn, $update_sql);
    
    // Add assignment reply
    $reply_msg = "Ticket assigned to: $assignee_name ($assign_to_type)";
    $esc_reply = mysqli_real_escape_string($conn, $reply_msg);
    mysqli_query($conn, "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, is_read) 
                         VALUES ($ticket_id, 'admin', " . (int)$admin['user_id'] . ", '$esc_reply', 1)");
    
    $_SESSION['flash_message'] = "Ticket #" . $ticket_id . " assigned to $assignee_name";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// ============================================================
// HANDLE TICKET STATUS UPDATE
// ============================================================
if (isset($_GET['status_update']) && isset($_GET['id']) && isset($_GET['status'])) {
    $ticket_id = (int)$_GET['id'];
    $status = $_GET['status'];
    $allowed_statuses = ['open', 'in_progress', 'resolved', 'closed'];
    
    if (in_array($status, $allowed_statuses)) {
        mysqli_query($conn, "UPDATE support_tickets SET status = '$status', updated_at = NOW() WHERE id = $ticket_id");
        $_SESSION['flash_message'] = "Ticket status updated to " . ucfirst(str_replace('_', ' ', $status));
        $_SESSION['flash_type'] = "success";
    }
    header("Location: index.php");
    exit();
}

// ============================================================
// HANDLE TICKET DELETE
// ============================================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $ticket_id = (int)$_GET['id'];
    mysqli_begin_transaction($conn);
    try {
        mysqli_query($conn, "DELETE FROM support_replies WHERE ticket_id = $ticket_id");
        mysqli_query($conn, "DELETE FROM support_tickets WHERE id = $ticket_id");
        mysqli_commit($conn);
        $_SESSION['flash_message'] = "Ticket deleted successfully.";
        $_SESSION['flash_type'] = "success";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = "Error deleting ticket.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: index.php");
    exit();
}

// ============================================================
// GET STATISTICS
// ============================================================
$stats = [];
$stats['total'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets"))['c'];
$stats['open'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE status = 'open'"))['c'];
$stats['in_progress'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE status = 'in_progress'"))['c'];
$stats['resolved'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE status = 'resolved'"))['c'];
$stats['closed'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE status = 'closed'"))['c'];
$stats['urgent'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE priority = 'urgent'"))['c'];
$stats['high'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE priority = 'high'"))['c'];
$stats['unassigned'] = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM support_tickets WHERE assigned_to_type IS NULL OR assigned_to_type = 'admin'"))['c'];

// ============================================================
// GET FILTERS
// ============================================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$priority_filter = isset($_GET['priority']) ? $_GET['priority'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "1=1";
if ($status_filter != 'all') {
    $where .= " AND status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}
if ($priority_filter != 'all') {
    $where .= " AND priority = '" . mysqli_real_escape_string($conn, $priority_filter) . "'";
}
if ($search) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (subject LIKE '%$search_esc%' OR ticket_no LIKE '%$search_esc%' OR message LIKE '%$search_esc%')";
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM support_tickets WHERE $where";
$count_res = mysqli_query($conn, $count_sql);
$total_tickets = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_tickets / $limit);

// Get tickets
$tickets = [];
$sql = "SELECT t.*,
        CASE 
            WHEN t.created_by_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.created_by_id)
            WHEN t.created_by_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.created_by_id)
            WHEN t.created_by_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.created_by_id)
            WHEN t.created_by_type = 'admin' THEN 'Admin'
            ELSE 'Unknown'
        END as created_by_name,
        CASE 
            WHEN t.assigned_to_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.assigned_to_id)
            WHEN t.assigned_to_type = 'admin' THEN 'System Admin'
            ELSE 'Unassigned'
        END as assigned_to_name,
        (SELECT COUNT(*) FROM support_replies WHERE ticket_id = t.id AND is_read = 0 AND reply_by_type != 'admin') as unread_replies
        FROM support_tickets t
        WHERE $where
        ORDER BY 
            CASE WHEN t.priority = 'urgent' THEN 1 
                 WHEN t.priority = 'high' THEN 2 
                 WHEN t.priority = 'medium' THEN 3 
                 ELSE 4 END,
            t.created_at DESC
        LIMIT $offset, $limit";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $tickets[] = $row;
}

// Get businesses for assignment dropdown
$businesses = [];
$bus_res = mysqli_query($conn, "SELECT business_id, business_name FROM businesses WHERE is_active = 1 ORDER BY business_name");
while ($row = mysqli_fetch_assoc($bus_res)) $businesses[] = $row;

// Get delivery agents for assignment dropdown
$delivery_agents = [];
$del_res = mysqli_query($conn, "SELECT agent_id, first_name, last_name, vehicle_type FROM delivery_agents WHERE is_available = 1");
while ($row = mysqli_fetch_assoc($del_res)) $delivery_agents[] = $row;

// Get customers for assignment dropdown
$customers = [];
$cust_res = mysqli_query($conn, "SELECT customer_id, first_name, last_name FROM customers ORDER BY first_name");
while ($row = mysqli_fetch_assoc($cust_res)) $customers[] = $row;

$flash = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 0.9rem; }
        }
        
        /* Page Header */
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 0.75rem;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            display: block;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.6rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 1rem;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .filter-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
        }
        .filter-btn {
            padding: 0.25rem 0.8rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.7rem;
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
            gap: 0.5rem;
        }
        .search-input {
            padding: 0.35rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 2rem;
            font-size: 0.75rem;
            width: 200px;
        }
        
        /* Tickets Container */
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
            padding: 0.75rem 0.9rem;
            text-align: left;
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        .ticket-table td {
            padding: 0.75rem 0.9rem;
            font-size: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .ticket-table tr:hover {
            background: #fffbeb;
            cursor: pointer;
        }
        
        /* Badges */
        .ticket-status {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-open { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-resolved { background: #d1fae5; color: #059669; }
        .status-closed { background: #e2e8f0; color: #64748b; }
        
        .priority-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .priority-urgent { background: #fee2e2; color: #dc2626; }
        .priority-high { background: #fef3c7; color: #d97706; }
        .priority-medium { background: #dbeafe; color: #2563eb; }
        .priority-low { background: #d1fae5; color: #059669; }
        
        .created-by {
            font-size: 0.7rem;
            color: #64748b;
        }
        .created-by i { font-size: 0.6rem; }
        
        .unread-badge {
            background: #e74c3c;
            color: white;
            border-radius: 20px;
            padding: 0.1rem 0.5rem;
            font-size: 0.6rem;
            font-weight: 600;
            margin-left: 0.3rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }
        .btn-sm {
            padding: 0.2rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.6rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }
        .btn-view { background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; }
        .btn-view:hover { background: #e67e22; color: white; }
        .btn-assign { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-assign:hover { background: #2563eb; color: white; }
        .btn-delete { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-delete:hover { background: #dc2626; color: white; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .modal-header h3 { font-size: 1.1rem; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        .modal-close:hover { color: #1f2937; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; font-size: 0.8rem; margin-bottom: 0.3rem; }
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8rem;
        }
        .form-control:focus { outline: none; border-color: #e67e22; }
        .btn-primary {
            background: #e67e22;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-primary:hover { background: #d35400; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
        }
        .pagination a, .pagination span {
            padding: 0.3rem 0.7rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-size: 0.75rem;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .pagination a:hover { background: #e67e22; color: white; }
        .pagination .active { background: #e67e22; color: white; border-color: #e67e22; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i { font-size: 3rem; display: block; margin-bottom: 0.5rem; opacity: 0.3; }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .search-box { width: 100%; }
            .search-input { width: 100%; }
            .ticket-table th, .ticket-table td { padding: 0.5rem; font-size: 0.65rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-headset"></i> Support Center</h1>
        <p>Manage all support tickets from customers, businesses, and delivery agents</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <a href="?status=all" class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total</div>
        </a>
        <a href="?status=open" class="stat-card">
            <div class="stat-number"><?php echo $stats['open']; ?></div>
            <div class="stat-label">Open</div>
        </a>
        <a href="?status=in_progress" class="stat-card">
            <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
        </a>
        <a href="?status=resolved" class="stat-card">
            <div class="stat-number"><?php echo $stats['resolved']; ?></div>
            <div class="stat-label">Resolved</div>
        </a>
        <a href="?status=closed" class="stat-card">
            <div class="stat-number"><?php echo $stats['closed']; ?></div>
            <div class="stat-label">Closed</div>
        </a>
        <a href="?priority=urgent" class="stat-card">
            <div class="stat-number"><?php echo $stats['urgent']; ?></div>
            <div class="stat-label">Urgent</div>
        </a>
        <a href="?priority=high" class="stat-card">
            <div class="stat-number"><?php echo $stats['high']; ?></div>
            <div class="stat-label">High</div>
        </a>
        <a href="?status=all&assigned=unassigned" class="stat-card">
            <div class="stat-number"><?php echo $stats['unassigned']; ?></div>
            <div class="stat-label">Unassigned</div>
        </a>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
        <div class="filter-group">
            <span class="filter-label">Status:</span>
            <a href="?status=all&priority=<?php echo $priority_filter; ?>" class="filter-btn <?php echo $status_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?status=open&priority=<?php echo $priority_filter; ?>" class="filter-btn <?php echo $status_filter == 'open' ? 'active' : ''; ?>">Open</a>
            <a href="?status=in_progress&priority=<?php echo $priority_filter; ?>" class="filter-btn <?php echo $status_filter == 'in_progress' ? 'active' : ''; ?>">In Progress</a>
            <a href="?status=resolved&priority=<?php echo $priority_filter; ?>" class="filter-btn <?php echo $status_filter == 'resolved' ? 'active' : ''; ?>">Resolved</a>
            <a href="?status=closed&priority=<?php echo $priority_filter; ?>" class="filter-btn <?php echo $status_filter == 'closed' ? 'active' : ''; ?>">Closed</a>
        </div>
        <div class="filter-group">
            <span class="filter-label">Priority:</span>
            <a href="?priority=all&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $priority_filter == 'all' ? 'active' : ''; ?>">All</a>
            <a href="?priority=urgent&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $priority_filter == 'urgent' ? 'active' : ''; ?>">Urgent</a>
            <a href="?priority=high&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $priority_filter == 'high' ? 'active' : ''; ?>">High</a>
            <a href="?priority=medium&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $priority_filter == 'medium' ? 'active' : ''; ?>">Medium</a>
            <a href="?priority=low&status=<?php echo $status_filter; ?>" class="filter-btn <?php echo $priority_filter == 'low' ? 'active' : ''; ?>">Low</a>
        </div>
        <form method="GET" class="search-box" onsubmit="return false;">
            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
            <input type="hidden" name="priority" value="<?php echo $priority_filter; ?>">
            <input type="text" name="search" class="search-input" placeholder="Search tickets..." value="<?php echo htmlspecialchars($search); ?>" id="searchInput">
            <button type="button" class="filter-btn" onclick="doSearch()" style="margin:0;">Search</button>
            <?php if ($search): ?>
                <a href="?status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>" class="filter-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="tickets-container">
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <i class="fas fa-ticket-alt"></i>
                <p>No tickets found matching your filters</p>
            </div>
        <?php else: ?>
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>Created By</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr onclick="window.location.href='ticket.php?id=<?php echo $ticket['id']; ?>'">
                        <td>
                            <strong>#<?php echo htmlspecialchars($ticket['ticket_no']); ?></strong>
                            <?php if ($ticket['unread_replies'] > 0): ?>
                                <span class="unread-badge"><?php echo $ticket['unread_replies']; ?> new</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(substr($ticket['subject'], 0, 35)); ?><?php echo strlen($ticket['subject']) > 35 ? '...' : ''; ?></td>
                        <td class="created-by">
                            <i class="fas fa-<?php echo $ticket['created_by_type'] == 'customer' ? 'user' : ($ticket['created_by_type'] == 'business' ? 'store' : ($ticket['created_by_type'] == 'delivery' ? 'truck' : 'crown')); ?>"></i>
                            <?php echo htmlspecialchars($ticket['created_by_name']); ?>
                        </td>
                        <td><span class="priority-badge priority-<?php echo $ticket['priority']; ?>"><?php echo ucfirst($ticket['priority']); ?></span></td>
                        <td><span class="ticket-status status-<?php echo $ticket['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?></span></td>
                        <td><?php echo htmlspecialchars($ticket['assigned_to_name'] ?? 'Unassigned'); ?></td>
                        <td><span style="font-size:0.65rem; color:#64748b;"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></span></td>
                        <td class="action-buttons" onclick="event.stopPropagation();">
                            <a href="ticket.php?id=<?php echo $ticket['id']; ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i></a>
                            <?php if ($ticket['assigned_to_type'] == 'admin' || $ticket['assigned_to_type'] == null): ?>
                                <button class="btn-sm btn-assign" onclick="openAssignModal(<?php echo $ticket['id']; ?>)"><i class="fas fa-user-plus"></i></button>
                            <?php endif; ?>
                            <a href="?delete=1&id=<?php echo $ticket['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this ticket permanently?')"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal" id="assignModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color: #e67e22;"></i> Assign Ticket</h3>
            <button class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="ticket_id" id="assignTicketId" value="">
            <div class="form-group">
                <label>Assign To <span style="color:red;">*</span></label>
                <select name="assign_to_type" class="form-control" id="assignTypeSelect" onchange="toggleAssignFields()" required>
                    <option value="admin">Admin (System Administrator)</option>
                    <option value="business">Business</option>
                    <option value="delivery">Delivery Agent</option>
                    <option value="customer">Customer</option>
                </select>
            </div>
            <div class="form-group" id="businessField" style="display:none;">
                <label>Select Business <span style="color:red;">*</span></label>
                <select name="assign_to_id" class="form-control">
                    <option value="">-- Choose business --</option>
                    <?php foreach ($businesses as $b): ?>
                        <option value="<?php echo $b['business_id']; ?>"><?php echo htmlspecialchars($b['business_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="deliveryField" style="display:none;">
                <label>Select Delivery Agent <span style="color:red;">*</span></label>
                <select name="assign_to_id" class="form-control">
                    <option value="">-- Choose agent --</option>
                    <?php foreach ($delivery_agents as $d): ?>
                        <option value="<?php echo $d['agent_id']; ?>"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?> (<?php echo ucfirst($d['vehicle_type']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="customerField" style="display:none;">
                <label>Select Customer <span style="color:red;">*</span></label>
                <select name="assign_to_id" class="form-control">
                    <option value="">-- Choose customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?php echo $c['customer_id']; ?>"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_ticket" class="btn-primary"><i class="fas fa-check"></i> Assign Ticket</button>
        </form>
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

function openAssignModal(ticketId) {
    document.getElementById('assignTicketId').value = ticketId;
    document.getElementById('assignModal').classList.add('active');
    toggleAssignFields();
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.remove('active');
}

function toggleAssignFields() {
    var type = document.getElementById('assignTypeSelect').value;
    document.getElementById('businessField').style.display = type === 'business' ? 'block' : 'none';
    document.getElementById('deliveryField').style.display = type === 'delivery' ? 'block' : 'none';
    document.getElementById('customerField').style.display = type === 'customer' ? 'block' : 'none';
}

// Close modal on outside click
document.getElementById('assignModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeAssignModal();
});

// Sidebar active link
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../support/index.php' || 
            links[i].getAttribute('href') === 'index.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>