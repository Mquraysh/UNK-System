<?php
// admin/support/ticket.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$admin_res = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$user_id'");
$admin = mysqli_fetch_assoc($admin_res);

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id <= 0) {
    header("Location: index.php");
    exit();
}

// ============================================================
// HANDLE TICKET STATUS UPDATE - RESOLVE
// ============================================================
if (isset($_GET['resolve']) && $_GET['resolve'] == '1') {
    $update_sql = "UPDATE support_tickets SET status = 'resolved', updated_at = NOW() WHERE id = $ticket_id";
    if (mysqli_query($conn, $update_sql)) {
        // Add system reply
        $reply_msg = "Ticket resolved by Admin.";
        $esc_reply = mysqli_real_escape_string($conn, $reply_msg);
        mysqli_query($conn, "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, is_read) 
                             VALUES ($ticket_id, 'admin', " . (int)$admin['user_id'] . ", '$esc_reply', 1)");
        $_SESSION['flash_message'] = "Ticket #" . $ticket_id . " marked as resolved.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error updating ticket status.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ticket.php?id=$ticket_id");
    exit();
}

// ============================================================
// HANDLE TICKET STATUS UPDATE - CLOSE
// ============================================================
if (isset($_GET['close']) && $_GET['close'] == '1') {
    $update_sql = "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = $ticket_id";
    if (mysqli_query($conn, $update_sql)) {
        $reply_msg = "Ticket closed by Admin.";
        $esc_reply = mysqli_real_escape_string($conn, $reply_msg);
        mysqli_query($conn, "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, is_read) 
                             VALUES ($ticket_id, 'admin', " . (int)$admin['user_id'] . ", '$esc_reply', 1)");
        $_SESSION['flash_message'] = "Ticket #" . $ticket_id . " closed.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error closing ticket.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ticket.php?id=$ticket_id");
    exit();
}

// ============================================================
// HANDLE TICKET STATUS UPDATE - REOPEN
// ============================================================
if (isset($_GET['reopen']) && $_GET['reopen'] == '1') {
    $update_sql = "UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = $ticket_id";
    if (mysqli_query($conn, $update_sql)) {
        $reply_msg = "Ticket reopened by Admin.";
        $esc_reply = mysqli_real_escape_string($conn, $reply_msg);
        mysqli_query($conn, "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, is_read) 
                             VALUES ($ticket_id, 'admin', " . (int)$admin['user_id'] . ", '$esc_reply', 1)");
        $_SESSION['flash_message'] = "Ticket #" . $ticket_id . " reopened.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error reopening ticket.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ticket.php?id=$ticket_id");
    exit();
}

// ============================================================
// HANDLE TICKET DELETE
// ============================================================
if (isset($_GET['delete']) && $_GET['delete'] == '1') {
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
// HANDLE ASSIGN TICKET
// ============================================================
if (isset($_POST['assign_ticket']) && isset($_POST['ticket_id']) && isset($_POST['assign_to_type']) && isset($_POST['assign_to_id'])) {
    $assign_ticket_id = (int)$_POST['ticket_id'];
    $assign_to_type = $_POST['assign_to_type'];
    $assign_to_id = (int)$_POST['assign_to_id'];
    
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
                   WHERE id = $assign_ticket_id";
    if (mysqli_query($conn, $update_sql)) {
        $reply_msg = "Ticket assigned to: $assignee_name ($assign_to_type)";
        $esc_reply = mysqli_real_escape_string($conn, $reply_msg);
        mysqli_query($conn, "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, is_read) 
                             VALUES ($assign_ticket_id, 'admin', " . (int)$admin['user_id'] . ", '$esc_reply', 1)");
        $_SESSION['flash_message'] = "Ticket assigned to $assignee_name";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error assigning ticket.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: ticket.php?id=$assign_ticket_id");
    exit();
}

// ============================================================
// GET TICKET DETAILS
// ============================================================
$ticket_sql = "SELECT t.*,
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
        CASE 
            WHEN t.created_by_type = 'customer' THEN (SELECT u.email FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.customer_id = t.created_by_id)
            WHEN t.created_by_type = 'business' THEN (SELECT u.email FROM businesses b JOIN users u ON b.user_id = u.user_id WHERE b.business_id = t.created_by_id)
            WHEN t.created_by_type = 'delivery' THEN (SELECT u.email FROM delivery_agents d JOIN users u ON d.user_id = u.user_id WHERE d.agent_id = t.created_by_id)
            ELSE 'admin@unksystem.com'
        END as created_by_email
        FROM support_tickets t
        WHERE t.id = $ticket_id";
$ticket_result = mysqli_query($conn, $ticket_sql);
if (mysqli_num_rows($ticket_result) == 0) {
    header("Location: index.php");
    exit();
}
$ticket = mysqli_fetch_assoc($ticket_result);

// Mark unread replies as read
mysqli_query($conn, "UPDATE support_replies SET is_read = 1 WHERE ticket_id = $ticket_id AND reply_by_type != 'admin'");

// ============================================================
// HANDLE REPLY SUBMISSION
// ============================================================
$reply_error = '';
$reply_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $reply_msg = trim($_POST['message'] ?? '');
    if (empty($reply_msg)) {
        $reply_error = "Message cannot be empty.";
    } else {
        $attachment = '';
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $target_dir = "../../assets/uploads/support/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx'];
            if ($_FILES['attachment']['size'] <= 5*1024*1024 && in_array($ext, $allowed)) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $filename)) {
                    $attachment = "/UNK-System/assets/uploads/support/" . $filename;
                }
            } else {
                $reply_error = "Invalid file or too large (max 5MB).";
            }
        }
        if (empty($reply_error)) {
            $esc_msg = mysqli_real_escape_string($conn, $reply_msg);
            $esc_att = mysqli_real_escape_string($conn, $attachment);
            $admin_id = (int)$admin['user_id'];
            
            $insert = "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, attachment, is_read)
                       VALUES ($ticket_id, 'admin', $admin_id, '$esc_msg', '$esc_att', 1)";
            
            if (mysqli_query($conn, $insert)) {
                mysqli_query($conn, "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = $ticket_id");
                $reply_success = "Your reply has been sent.";
                header("Location: ticket.php?id=$ticket_id");
                exit();
            } else {
                $reply_error = "Failed to send reply.";
            }
        }
    }
}

// ============================================================
// GET REPLIES
// ============================================================
$replies = [];
$rep_sql = "SELECT r.*,
            CASE 
                WHEN r.reply_by_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = r.reply_by_id)
                WHEN r.reply_by_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = r.reply_by_id)
                WHEN r.reply_by_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = r.reply_by_id)
                WHEN r.reply_by_type = 'admin' THEN 'UNK Support Team'
                ELSE 'Unknown'
            END as display_name
            FROM support_replies r
            WHERE r.ticket_id = $ticket_id
            ORDER BY r.created_at ASC";
$rep_res = mysqli_query($conn, $rep_sql);
while ($row = mysqli_fetch_assoc($rep_res)) {
    $replies[] = $row;
}

// ============================================================
// GET DROPDOWN DATA FOR ASSIGNMENT
// ============================================================
$businesses = [];
$bus_res = mysqli_query($conn, "SELECT business_id, business_name FROM businesses WHERE is_active = 1 ORDER BY business_name");
while ($row = mysqli_fetch_assoc($bus_res)) $businesses[] = $row;

$delivery_agents = [];
$del_res = mysqli_query($conn, "SELECT agent_id, first_name, last_name, vehicle_type FROM delivery_agents WHERE is_available = 1");
while ($row = mysqli_fetch_assoc($del_res)) $delivery_agents[] = $row;

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
    <title>Ticket #<?php echo $ticket['ticket_no']; ?> | Admin</title>
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
        @media (max-width: 1024px) { .admin-content { margin-left: 0; padding: 1.25rem; } }
        @media (max-width: 768px) { .admin-content { padding: 0.9rem; } }
        
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #e67e22;
            text-decoration: none;
            margin-bottom: 1rem;
        }
        .back-link:hover { text-decoration: underline; }
        
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
        
        /* Ticket Header */
        .ticket-header {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .ticket-header .top-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .ticket-no { font-size: 1.3rem; font-weight: 800; color: #e67e22; font-family: monospace; }
        
        .ticket-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.7rem;
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        .info-item { font-size: 0.8rem; color: #64748b; }
        .info-item strong { color: #1f293b; }
        
        .ticket-message {
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid #e67e22;
        }
        .ticket-message p { color: #475569; line-height: 1.6; white-space: pre-wrap; }
        
        .reply-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .reply-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .reply-avatar.admin { background: linear-gradient(135deg, #e67e22, #d35400); }
        .reply-avatar.support { background: linear-gradient(135deg, #8e44ad, #6c3483); }
        .reply-avatar i { color: white; }
        .reply-content {
            flex: 1;
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            border: 1px solid #e2e8f0;
        }
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .reply-sender { font-weight: 700; font-size: 0.85rem; }
        .reply-date { font-size: 0.7rem; color: #94a3b8; }
        .reply-message { font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap; }
        .reply-attachment a { color: #e67e22; font-size: 0.75rem; text-decoration: none; }
        
        /* Reply Form */
        .reply-form {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
            margin-top: 1.5rem;
        }
        .reply-form h3 { font-size: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { font-weight: 600; font-size: 0.8rem; display: block; margin-bottom: 0.3rem; }
        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            font-family: inherit;
            resize: vertical;
        }
        .form-control:focus { outline: none; border-color: #e67e22; }
        .help-text { font-size: 0.65rem; color: #94a3b8; display: block; margin-top: 0.25rem; }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; }
        .btn-secondary { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        
        .empty-state { text-align: center; padding: 2rem; color: #94a3b8; }
        .empty-state i { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; opacity: 0.3; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
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
        .modal.active { display: flex; }
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
        
        @media (max-width: 768px) {
            .ticket-header .top-row { flex-direction: column; align-items: flex-start; }
            .reply-item { flex-direction: column; }
            .reply-avatar { align-self: flex-start; }
            .info-grid { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Support Center</a>
    
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($reply_success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $reply_success; ?></div>
    <?php endif; ?>
    <?php if ($reply_error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $reply_error; ?></div>
    <?php endif; ?>
    
    <!-- Ticket Header -->
    <div class="ticket-header">
        <div class="top-row">
            <div>
                <span class="ticket-no">#<?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
                <span style="font-size:0.8rem; color:#64748b; margin-left:0.5rem;">
                    <i class="fas fa-<?php echo $ticket['created_by_type'] == 'customer' ? 'user' : ($ticket['created_by_type'] == 'business' ? 'store' : ($ticket['created_by_type'] == 'delivery' ? 'truck' : 'crown')); ?>"></i>
                    <?php echo htmlspecialchars($ticket['created_by_name']); ?>
                </span>
            </div>
            <div>
                <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                    <i class="fas <?php echo $ticket['status'] == 'open' ? 'fa-circle' : ($ticket['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : ($ticket['status'] == 'resolved' ? 'fa-check-circle' : 'fa-times-circle')); ?>"></i>
                    <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                </span>
                <span class="priority-badge priority-<?php echo $ticket['priority']; ?>" style="margin-left:0.5rem;">
                    <?php echo ucfirst($ticket['priority']); ?>
                </span>
            </div>
        </div>
        
        <h2 style="font-size:1.1rem; margin-top:0.5rem;"><?php echo htmlspecialchars($ticket['subject']); ?></h2>
        
        <div class="info-grid">
            <div class="info-item"><strong>Category:</strong> <?php echo ucfirst($ticket['category']); ?></div>
            <div class="info-item"><strong>Assigned to:</strong> <?php echo htmlspecialchars($ticket['assigned_to_name']); ?></div>
            <div class="info-item"><strong>Created:</strong> <?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?></div>
            <div class="info-item"><strong>Updated:</strong> <?php echo date('M d, Y g:i A', strtotime($ticket['updated_at'])); ?></div>
            <div class="info-item"><strong>From:</strong> <?php echo htmlspecialchars($ticket['created_by_email']); ?></div>
        </div>
        
        <div class="ticket-message">
            <p><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
            <?php if (!empty($ticket['attachment'])): ?>
                <a href="<?php echo $ticket['attachment']; ?>" target="_blank" style="color:#e67e22; font-size:0.8rem; margin-top:0.5rem; display:inline-block;">
                    <i class="fas fa-paperclip"></i> View Attachment
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Replies -->
    <?php if (!empty($replies)): ?>
        <div style="margin-bottom:1.5rem;">
            <h3 style="font-size:1rem; margin-bottom:1rem;"><i class="fas fa-comments" style="color:#e67e22;"></i> Replies (<?php echo count($replies); ?>)</h3>
            <?php foreach ($replies as $reply): 
                $isAdmin = ($reply['reply_by_type'] == 'admin');
            ?>
            <div class="reply-item">
                <div class="reply-avatar <?php echo $isAdmin ? 'admin' : 'support'; ?>">
                    <i class="fas <?php echo $isAdmin ? 'fa-crown' : 'fa-user'; ?>"></i>
                </div>
                <div class="reply-content">
                    <div class="reply-header">
                        <span class="reply-sender">
                            <?php echo htmlspecialchars($reply['display_name']); ?>
                            <?php if ($isAdmin): ?>
                                <span style="font-size:0.6rem; background:#e67e22; color:white; padding:0.1rem 0.4rem; border-radius:1rem; margin-left:0.3rem;">Admin</span>
                            <?php endif; ?>
                        </span>
                        <span class="reply-date"><?php echo date('M d, Y g:i A', strtotime($reply['created_at'])); ?></span>
                    </div>
                    <div class="reply-message"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></div>
                    <?php if (!empty($reply['attachment'])): ?>
                        <div class="reply-attachment">
                            <a href="<?php echo $reply['attachment']; ?>" target="_blank"><i class="fas fa-paperclip"></i> Download Attachment</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Reply Form -->
    <?php if ($ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
    <div class="reply-form">
        <h3><i class="fas fa-reply" style="color:#e67e22;"></i> Add Reply</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Message <span style="color:red;">*</span></label>
                <textarea name="message" class="form-control" rows="5" required placeholder="Type your reply..."></textarea>
            </div>
            <div class="form-group">
                <label>Attachment (optional)</label>
                <input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.doc,.docx">
                <small class="help-text"><i class="fas fa-info-circle"></i> Max 5MB (JPG, PNG, PDF, DOC)</small>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <button type="submit" name="send_reply" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send Reply</button>
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
            </div>
        </form>
    </div>
    <?php elseif ($ticket['status'] == 'resolved'): ?>
        <div class="alert alert-info">
            <i class="fas fa-check-circle"></i> This ticket is <strong>resolved</strong>. You can <strong>close</strong> it using the button below.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-lock"></i> This ticket is <strong>closed</strong>. You can <strong>reopen</strong> it using the button below.
        </div>
    <?php endif; ?>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <?php if ($ticket['status'] != 'closed' && $ticket['status'] != 'resolved'): ?>
            <a href="?id=<?php echo $ticket_id; ?>&resolve=1" class="btn btn-success" onclick="return confirm('Mark this ticket as resolved?')">
                <i class="fas fa-check-circle"></i> Resolve
            </a>
            <a href="?id=<?php echo $ticket_id; ?>&close=1" class="btn btn-danger" onclick="return confirm('Close this ticket?')">
                <i class="fas fa-times-circle"></i> Close
            </a>
        <?php endif; ?>
        
        <?php if ($ticket['status'] == 'resolved'): ?>
            <a href="?id=<?php echo $ticket_id; ?>&close=1" class="btn btn-danger" onclick="return confirm('Close this resolved ticket?')">
                <i class="fas fa-times-circle"></i> Close
            </a>
        <?php endif; ?>
        
        <?php if ($ticket['status'] == 'closed'): ?>
            <a href="?id=<?php echo $ticket_id; ?>&reopen=1" class="btn btn-primary" onclick="return confirm('Reopen this ticket?')">
                <i class="fas fa-folder-open"></i> Reopen
            </a>
        <?php endif; ?>
        
        <button class="btn btn-secondary" onclick="openAssignModal(<?php echo $ticket_id; ?>)">
            <i class="fas fa-user-plus"></i> Assign
        </button>
        
        <a href="?delete=1&id=<?php echo $ticket_id; ?>" class="btn btn-secondary" style="background:#fee2e2; color:#dc2626; border-color:#fecaca;" onclick="return confirm('Delete this ticket permanently? This action cannot be undone.')">
            <i class="fas fa-trash-alt"></i> Delete
        </a>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal" id="assignModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:#e67e22;"></i> Assign Ticket</h3>
            <button class="modal-close" onclick="closeAssignModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="ticket_id" value="<?php echo $ticket_id; ?>">
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
            <button type="submit" name="assign_ticket" class="btn btn-primary" style="width:100%; justify-content:center;">
                <i class="fas fa-check"></i> Assign Ticket
            </button>
        </form>
    </div>
</div>

<script>
function openAssignModal(ticketId) {
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