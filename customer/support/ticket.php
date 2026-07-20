<?php
require_once '../../config/database.php';
session_start();

// AUTHENTICATION - Check if user is logged in as customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// GET CUSTOMER DATA
$cust_res = mysqli_query($conn, "SELECT c.*, u.email, u.phone FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.user_id = '$user_id'");
if (mysqli_num_rows($cust_res) == 0) {
    header("Location: register.php");
    exit();
}
$customer = mysqli_fetch_assoc($cust_res);
$customer_id = $customer['customer_id'];

// GET TICKET ID AND VERIFY OWNERSHIP
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ticket_id <= 0) {
    header("Location: my_tickets.php");
    exit();
}

// Get ticket details from unified support_tickets table
$ticket_sql = "SELECT t.*, 
               CASE 
                   WHEN t.assigned_to_type = 'admin' THEN 'System Admin'
                   WHEN t.assigned_to_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.assigned_to_id)
                   WHEN t.assigned_to_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.assigned_to_id)
                   WHEN t.assigned_to_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.assigned_to_id)
                   ELSE 'Admin'
               END as assigned_display,
               CASE 
                   WHEN t.assigned_to_type = 'admin' THEN 'admin@unksystem.com'
                   WHEN t.assigned_to_type = 'business' THEN (SELECT u.email FROM businesses b JOIN users u ON b.user_id = u.user_id WHERE b.business_id = t.assigned_to_id)
                   WHEN t.assigned_to_type = 'delivery' THEN (SELECT u.email FROM delivery_agents d JOIN users u ON d.user_id = u.user_id WHERE d.agent_id = t.assigned_to_id)
                   WHEN t.assigned_to_type = 'customer' THEN (SELECT u.email FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.customer_id = t.assigned_to_id)
                   ELSE 'admin@unksystem.com'
               END as assigned_email,
               CASE 
                   WHEN t.created_by_type = 'customer' AND t.created_by_id = $customer_id THEN 'sent'
                   ELSE 'received'
               END as direction
               FROM support_tickets t
               WHERE t.id = $ticket_id AND (t.created_by_type = 'customer' AND t.created_by_id = $customer_id)";

$ticket_res = mysqli_query($conn, $ticket_sql);
if (mysqli_num_rows($ticket_res) == 0) {
    $_SESSION['flash_message'] = "Ticket not found.";
    $_SESSION['flash_type'] = "danger";
    header("Location: my_tickets.php");
    exit();
}
$ticket = mysqli_fetch_assoc($ticket_res);

// HANDLE TICKET STATUS CHANGE - Close Ticket
if (isset($_POST['close_ticket']) && $ticket['status'] == 'resolved') {
    mysqli_query($conn, "UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = $ticket_id");
    $_SESSION['flash_message'] = "Ticket " . $ticket['ticket_no'] . " has been closed.";
    $_SESSION['flash_type'] = "success";
    header("Location: ticket.php?id=$ticket_id");
    exit();
}

// HANDLE TICKET STATUS CHANGE - Reopen Ticket
if (isset($_POST['reopen_ticket']) && $ticket['status'] == 'closed') {
    mysqli_query($conn, "UPDATE support_tickets SET status = 'open', updated_at = NOW() WHERE id = $ticket_id");
    $_SESSION['flash_message'] = "Ticket " . $ticket['ticket_no'] . " has been reopened.";
    $_SESSION['flash_type'] = "success";
    header("Location: ticket.php?id=$ticket_id");
    exit();
}

// HANDLE TICKET DELETE (Only if open/in_progress)
if (isset($_GET['delete']) && $_GET['delete'] == '1' && in_array($ticket['status'], ['open', 'in_progress'])) {
    // Verify ticket belongs to this customer
    $check_sql = "SELECT ticket_no FROM support_tickets WHERE id = $ticket_id AND created_by_type = 'customer' AND created_by_id = $customer_id";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_begin_transaction($conn);
        try {
            // Delete replies first (foreign key constraint)
            mysqli_query($conn, "DELETE FROM support_replies WHERE ticket_id = $ticket_id");
            // Delete the ticket
            mysqli_query($conn, "DELETE FROM support_tickets WHERE id = $ticket_id");
            mysqli_commit($conn);
            $_SESSION['flash_message'] = "Ticket " . $ticket['ticket_no'] . " deleted.";
            $_SESSION['flash_type'] = "success";
            header("Location: my_tickets.php");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = "Error deleting ticket.";
            $_SESSION['flash_type'] = "danger";
            header("Location: ticket.php?id=$ticket_id");
            exit();
        }
    }
}

// HANDLE REPLY SUBMISSION
$reply_error = '';
$reply_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply'])) {
    $reply_msg = trim($_POST['message'] ?? '');
    if (empty($reply_msg)) {
        $reply_error = "Message cannot be empty.";
    } else {
        $attachment = '';
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $target_dir = "../../assets/uploads/support/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
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
            $sender_name = mysqli_real_escape_string($conn, $customer['first_name'] . ' ' . $customer['last_name']);
            
            // Insert reply into unified support_replies table
            $insert = "INSERT INTO support_replies (ticket_id, reply_by_type, reply_by_id, message, attachment)
                       VALUES ($ticket_id, 'customer', $customer_id, '$esc_msg', '$esc_att')";
            
            if (mysqli_query($conn, $insert)) {
                // Update ticket status to in_progress
                mysqli_query($conn, "UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = $ticket_id");
                $reply_success = "Your reply has been sent.";
                $ticket['status'] = 'in_progress';
                // Refresh page to show new reply
                header("Location: ticket.php?id=$ticket_id");
                exit();
            } else {
                $reply_error = "Failed to send reply: " . mysqli_error($conn);
            }
        }
    }
}

// FETCH ALL REPLIES
$replies = [];

// Original ticket message as first "reply"
$replies[] = [
    'reply_by_type' => 'customer',
    'display_name' => 'You',
    'message' => $ticket['message'],
    'attachment' => $ticket['attachment'],
    'created_at' => $ticket['created_at']
];

// Fetch replies from unified support_replies table
$rep_sql = "SELECT r.*, 
            CASE 
                WHEN r.reply_by_type = 'customer' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM customers WHERE customer_id = r.reply_by_id)
                WHEN r.reply_by_type = 'admin' THEN 'UNK Support Team'
                WHEN r.reply_by_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = r.reply_by_id)
                WHEN r.reply_by_type = 'delivery' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM delivery_agents WHERE agent_id = r.reply_by_id)
                ELSE 'Support'
            END as display_name
            FROM support_replies r
            WHERE r.ticket_id = $ticket_id
            ORDER BY r.created_at ASC";

$rep_res = mysqli_query($conn, $rep_sql);
while ($row = mysqli_fetch_assoc($rep_res)) {
    $replies[] = $row;
}

// GET ASSIGNMENT DETAILS
$assigned_to = $ticket['assigned_to_type'] ?? 'admin';
$assigned_name = $ticket['assigned_display'] ?? 'System Administrator';
$assigned_email = $ticket['assigned_email'] ?? 'admin@unksystem.com';

// Set icon based on assignment type
if ($assigned_to == 'business') $assigned_icon = '🏪';
elseif ($assigned_to == 'delivery') $assigned_icon = '🚚';
else $assigned_icon = '👨‍💼';

// Set response time based on assignment type
if ($assigned_to == 'business') $response_time = '12-24 hours';
elseif ($assigned_to == 'delivery') $response_time = '4-8 hours';
else $response_time = '24 hours';

// CALCULATE RESOLVE TIME (if resolved or closed)
$resolve_time = '';
if ($ticket['status'] == 'resolved' || $ticket['status'] == 'closed') {
    $created = strtotime($ticket['created_at']);
    $updated = strtotime($ticket['updated_at']);
    $hours = round(($updated - $created) / 3600, 1);
    $resolve_time = $hours . ' hours';
}

// GET FLASH MESSAGES
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// INCLUDE SIDEBAR
include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo htmlspecialchars($ticket['ticket_no']); ?> - UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .dashboard-wrapper { display: flex; }
        .dashboard-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        
        /* PAGE HEADER */
        .page-header { margin-bottom: 25px; }
        .page-header h1 { 
            font-size: 24px; 
            font-weight: 700; 
            color: #0f172a; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            flex-wrap: wrap; 
        }
        .page-header h1 i { color: #e67e22; }
        
        
        /* ALERT MESSAGES */
        .alert { 
            padding: 14px 18px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            animation: slideIn 0.3s ease; 
        }
        @keyframes slideIn { 
            from { transform: translateY(-20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
       
        /* TICKET INFO CARD */
        .ticket-info-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .ticket-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .ticket-no { 
            font-size: 20px; 
            font-weight: 800; 
            color: #e67e22; 
            font-family: monospace; 
        }
        .ticket-status {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-open { background: #fef3c7; color: #d97706; }
        .status-in_progress { background: #dbeafe; color: #2563eb; }
        .status-resolved { background: #d1fae5; color: #059669; }
        .status-closed { background: #e2e8f0; color: #64748b; }
        
        .ticket-body { padding: 25px; }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-item { display: flex; align-items: center; gap: 12px; }
        .info-icon {
            width: 40px;
            height: 40px;
            background: rgba(230,126,34,0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .info-icon i { font-size: 18px; color: #e67e22; }
        .info-label { 
            font-size: 11px; 
            color: #94a3b8; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }
        .info-value { 
            font-size: 14px; 
            font-weight: 600; 
            color: #1e293b; 
            margin-top: 3px; 
        }
        
        /* Priority Badge */
        .priority-badge { 
            padding: 3px 10px; 
            border-radius: 20px; 
            font-size: 11px; 
            font-weight: 600; 
            display: inline-block; 
        }
        .priority-low { background: #d1fae5; color: #059669; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        
        .ticket-subject { 
            font-size: 18px; 
            font-weight: 700; 
            color: #1e293b; 
            margin-bottom: 15px; 
        }
        .ticket-message {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
            margin-top: 15px;
            border-left: 4px solid #e67e22;
        }
        .ticket-message p { 
            color: #475569; 
            line-height: 1.6; 
            font-size: 14px; 
            white-space: pre-wrap; 
        }
        
        /* Assignment Info */
        .assignment-info {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border-radius: 12px;
            padding: 15px 20px;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            border: 1px solid #fde68a;
        }
        .assignment-icon {
            width: 45px;
            height: 45px;
            background: #e67e22;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .assignment-icon i { font-size: 22px; color: white; }
        .assignment-text strong { 
            font-size: 14px; 
            color: #1e293b; 
            display: block; 
        }
        .assignment-text p { 
            font-size: 12px; 
            color: #64748b; 
            margin-top: 4px; 
        }
        
        .resolve-time {
            background: #e0f2fe;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            color: #0369a1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Direction Badge */
        .direction-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        .direction-sent { background: #dbeafe; color: #2563eb; }
        .direction-received { background: #fef3c7; color: #d97706; }
        
        /* REPLIES SECTION */
        .replies-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        .replies-header {
            padding: 18px 25px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .replies-header h3 { 
            font-size: 16px; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        .replies-header h3 i { color: #e67e22; }
        
        .replies-list { 
            padding: 20px 25px; 
            max-height: 500px; 
            overflow-y: auto; 
        }
        .reply-item { 
            margin-bottom: 25px; 
            display: flex; 
            gap: 15px; 
            animation: fadeIn 0.3s ease; 
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        .reply-item:last-child { margin-bottom: 0; }
        
        .reply-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .reply-avatar.customer { background: linear-gradient(135deg, #2c3e50, #1a2632); }
        .reply-avatar.support { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .reply-avatar i { font-size: 20px; color: white; }
        
        .reply-content { flex: 1; }
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px;
        }
        .reply-sender { 
            font-weight: 700; 
            color: #1e293b; 
            font-size: 14px; 
        }
        .reply-sender i { color: #e67e22; margin-right: 5px; }
        .reply-sender .badge { 
            font-size: 11px; 
            background: #e2e8f0; 
            padding: 2px 8px; 
            border-radius: 20px; 
            margin-left: 8px; 
            font-weight: 500; 
        }
        .reply-sender .badge.support { background: #e67e22; color: white; }
        .reply-date { font-size: 11px; color: #94a3b8; }
        .reply-message {
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
            background: #f8fafc;
            padding: 12px 15px;
            border-radius: 12px;
            margin-top: 5px;
            white-space: pre-wrap;
        }
        .reply-attachment { margin-top: 10px; }
        .reply-attachment a { 
            color: #e67e22; 
            text-decoration: none; 
            font-size: 12px; 
        }
        .reply-attachment a:hover { text-decoration: underline; }
    
        /* REPLY FORM */
        .reply-form {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .reply-form-header {
            padding: 18px 25px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .reply-form-header h3 { 
            font-size: 16px; 
            font-weight: 700; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        .reply-form-header h3 i { color: #e67e22; }
        .reply-form-body { padding: 25px; }
        
        /* Form Elements */
        .form-group { margin-bottom: 18px; }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #475569; 
            font-size: 13px; 
        }
        .form-group label .required { color: #e74c3c; }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            transition: all 0.2s;
        }
        .form-control:focus { 
            outline: none; 
            border-color: #e67e22; 
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1); 
        }
        .help-text { 
            font-size: 11px; 
            color: #94a3b8; 
            margin-top: 5px; 
            display: block; 
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: linear-gradient(135deg, #e67e22, #d35400); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,126,34,0.3); }
        .btn-secondary { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e67e22; color: white; border-color: #e67e22; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 40px 20px; }
        .empty-state i { font-size: 50px; color: #cbd5e1; margin-bottom: 15px; }
        .empty-state p { color: #94a3b8; }
        
        /* TOP BUTTONS & ACTION BUTTONS */
        .top-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* MOBILE RESPONSIVE */
        /* Tablets and smaller laptops */
        @media (max-width: 1024px) { 
            .dashboard-content { 
                margin-left: 0; 
                padding: 20px; 
            } 
        }
        
        /* Mobile phones */
        @media (max-width: 768px) {
            .dashboard-content { 
                padding: 15px; 
            }
            .ticket-header { 
                flex-direction: column; 
                align-items: flex-start; 
            }
            .info-grid { 
                grid-template-columns: 1fr; 
            }
            .reply-item { 
                flex-direction: column; 
            }
            .reply-avatar { 
                align-self: flex-start; 
            }
            .top-buttons { 
                flex-direction: column; 
                align-items: stretch; 
            }
            .top-buttons .btn { 
                width: 100%; 
                justify-content: center; 
            }
            .action-buttons { 
                width: 100%; 
                flex-direction: column; 
            }
            .action-buttons .btn { 
                width: 100%; 
                justify-content: center; 
            }
        }
        
        /* Small phones */
        @media (max-width: 480px) {
            .dashboard-content { 
                padding: 10px; 
            }
            .ticket-header { 
                padding: 15px; 
            }
            .ticket-body { 
                padding: 15px; 
            }
            .ticket-no { 
                font-size: 16px; 
            }
            .ticket-subject { 
                font-size: 15px; 
            }
            .reply-message { 
                font-size: 12px; 
            }
            .replies-list { 
                padding: 15px; 
            }
            .reply-form-body { 
                padding: 15px; 
            }
        }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <div class="dashboard-content">
     
        <!-- TOP BUTTONS -->
        <div class="top-buttons">
            <a href="my_tickets.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to My Tickets
            </a>
            <div class="action-buttons">
                <?php if ($ticket['status'] == 'resolved'): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="close_ticket" class="btn btn-success" 
                                onclick="return confirm('Close this ticket? You can reopen it later if needed.')">
                            <i class="fas fa-check-circle"></i> Close Ticket
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($ticket['status'] == 'closed'): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="reopen_ticket" class="btn btn-warning" 
                                onclick="return confirm('Reopen this ticket?')">
                            <i class="fas fa-folder-open"></i> Reopen Ticket
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if (in_array($ticket['status'], ['open', 'in_progress'])): ?>
                    <a href="?id=<?php echo $ticket_id; ?>&delete=1" class="btn btn-danger" 
                       onclick="return confirm('⚠️ Delete this ticket permanently? This action cannot be undone.')">
                        <i class="fas fa-trash-alt"></i> Delete Ticket
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- FLASH MESSAGES -->
        <?php if ($flash_message): ?>
            <div class="alert alert-<?php echo $flash_type; ?>">
                <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : ($flash_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($reply_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $reply_success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($reply_error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $reply_error; ?>
            </div>
        <?php endif; ?>

        <!-- TICKET INFO CARD -->
        <div class="ticket-info-card">
            <div class="ticket-header">
                <div>
                    <span class="ticket-no"><?php echo htmlspecialchars($ticket['ticket_no']); ?></span>
                    <span class="direction-badge direction-<?php echo $ticket['direction']; ?>">
                        <i class="fas fa-<?php echo $ticket['direction'] == 'sent' ? 'paper-plane' : 'inbox'; ?>"></i>
                        <?php echo $ticket['direction'] == 'sent' ? 'Sent by You' : 'Received'; ?>
                    </span>
                    <?php if ($resolve_time): ?>
                        <span class="resolve-time">
                            <i class="fas fa-clock"></i> Resolved in <?php echo $resolve_time; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="ticket-status status-<?php echo $ticket['status']; ?>">
                        <i class="fas <?php echo $ticket['status'] == 'open' ? 'fa-circle' : ($ticket['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : ($ticket['status'] == 'resolved' ? 'fa-check-circle' : 'fa-times-circle')); ?>"></i>
                        <?php echo str_replace('_', ' ', ucfirst($ticket['status'])); ?>
                    </span>
                </div>
            </div>
            <div class="ticket-body">
                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div>
                            <div class="info-label">Created</div>
                            <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($ticket['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-tag"></i></div>
                        <div>
                            <div class="info-label">Category</div>
                            <div class="info-value"><?php echo ucfirst($ticket['category']); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-flag"></i></div>
                        <div>
                            <div class="info-label">Priority</div>
                            <div class="info-value">
                                <span class="priority-badge priority-<?php echo $ticket['priority']; ?>">
                                    <?php echo ucfirst($ticket['priority']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon"><i class="fas fa-sync-alt"></i></div>
                        <div>
                            <div class="info-label">Last Updated</div>
                            <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($ticket['updated_at'])); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Subject & Message -->
                <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                <div class="ticket-message">
                    <p><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                    <?php if (!empty($ticket['attachment'])): ?>
                        <div style="margin-top: 15px;">
                            <a href="<?php echo $ticket['attachment']; ?>" target="_blank" 
                               class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">
                                <i class="fas fa-paperclip"></i> View Attachment
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Assignment Info -->
                <div class="assignment-info">
                    <div class="assignment-icon"><i class="fas fa-paper-plane"></i></div>
                    <div class="assignment-text">
                        <strong><?php echo $assigned_icon; ?> <?php echo htmlspecialchars($assigned_name); ?></strong>
                        <p>
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($assigned_email); ?> 
                            • <i class="fas fa-clock"></i> Response time: <?php echo $response_time; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>


        <!-- REPLIES SECTION -->
        <div class="replies-section">
            <div class="replies-header">
                <h3><i class="fas fa-comments"></i> Conversation (<?php echo count($replies)-1; ?> replies)</h3>
                <span style="font-size: 11px; color: #94a3b8;">
                    <i class="fas fa-info-circle"></i> <?php echo $response_time; ?> response time
                </span>
            </div>
            <div class="replies-list">
                <?php if (count($replies) == 1): ?>
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <p>No replies yet. Our support team will respond shortly.</p>
                        <p style="font-size: 12px; margin-top: 10px;">
                            Response time: <?php echo $response_time; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $idx => $rep): 
                        if ($idx == 0) continue; 
                        
                        // Determine if reply is from customer or support
                        $isCustomer = ($rep['reply_by_type'] == 'customer');
                        $author = $isCustomer ? 'You' : ($rep['display_name'] ?? 'Support');
                        $userType = $rep['reply_by_type'] ?? 'support';
                        
                        // Set icon based on user type
                        if ($userType == 'admin') $icon = '👨‍💼';
                        elseif ($userType == 'business') $icon = '🏪';
                        elseif ($userType == 'delivery') $icon = '🚚';
                        elseif ($userType == 'customer') $icon = '👤';
                        else $icon = '💬';
                    ?>
                    <div class="reply-item">
                        <div class="reply-avatar <?php echo $isCustomer ? 'customer' : 'support'; ?>">
                            <i class="fas <?php echo $isCustomer ? 'fa-user' : 'fa-headset'; ?>"></i>
                        </div>
                        <div class="reply-content">
                            <div class="reply-header">
                                <span class="reply-sender">
                                    <?php echo $icon; ?> <?php echo htmlspecialchars($author); ?>
                                    <span class="badge <?php echo $isCustomer ? '' : 'support'; ?>">
                                        <?php echo $isCustomer ? 'You' : 'Support Team'; ?>
                                    </span>
                                </span>
                                <span class="reply-date">
                                    <?php echo date('M d, Y h:i A', strtotime($rep['created_at'])); ?>
                                </span>
                            </div>
                            <div class="reply-message"><?php echo nl2br(htmlspecialchars($rep['message'])); ?></div>
                            <?php if (!empty($rep['attachment'])): ?>
                                <div class="reply-attachment">
                                    <a href="<?php echo $rep['attachment']; ?>" target="_blank">
                                        <i class="fas fa-paperclip"></i> Download Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
     
        <!-- REPLY FORM (if not closed or resolved) -->
        <?php if (!in_array($ticket['status'], ['closed', 'resolved'])): ?>
        <div class="reply-form">
            <div class="reply-form-header">
                <h3><i class="fas fa-reply"></i> Add Your Reply</h3>
            </div>
            <div class="reply-form-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Your Message <span class="required">*</span></label>
                        <textarea name="message" class="form-control" rows="5" 
                                  required placeholder="Type your reply here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Attachment (Optional)</label>
                        <input type="file" name="attachment" class="form-control" 
                               accept="image/*,.pdf,.doc,.docx">
                        <small class="help-text">
                            <i class="fas fa-info-circle"></i> Max 5MB (JPG, PNG, PDF, DOC, DOCX)
                        </small>
                    </div>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" name="send_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                        <a href="my_tickets.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($ticket['status'] == 'resolved'): ?>
            <div class="alert alert-info" style="margin-top: 0;">
                <i class="fas fa-check-circle"></i> 
                This ticket has been resolved. If you have further questions, you can 
                <strong>close</strong> this ticket or <a href="index.php" style="color: #e67e22;">create a new ticket</a>.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-lock"></i> 
                This ticket is <strong>closed</strong>. You cannot add new replies. 
                <a href="?id=<?php echo $ticket_id; ?>&reopen=1" 
                   onclick="event.preventDefault(); document.getElementById('reopenForm').submit();" 
                   style="color: #e67e22;">Reopen this ticket</a> if you need further assistance.
                <form id="reopenForm" method="POST" style="display: none;">
                    <input type="hidden" name="reopen_ticket" value="1">
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        // SIDEBAR - Highlight active link
        var links = document.querySelectorAll('.sidebar-menu a');
        for (var i = 0; i < links.length; i++) {
            if (links[i].getAttribute('href') === '../my_tickets.php' || 
                links[i].getAttribute('href') === 'my_tickets.php') {
                links[i].classList.add('active');
            }
        }
        
        // AUTO-SCROLL to bottom of replies (most recent)
        var repliesList = document.querySelector('.replies-list');
        if (repliesList) {
            repliesList.scrollTop = repliesList.scrollHeight;
        }
        
        // CHARACTER COUNTER for reply message
        var textarea = document.querySelector('textarea[name="message"]');
        if (textarea) {
            // Create counter element
            var counter = document.createElement('small');
            counter.style.cssText = 'display: block; margin-top: 5px; font-size: 11px; color: #94a3b8;';
            counter.innerHTML = '<span id="charCount">0</span> characters';
            textarea.parentNode.appendChild(counter);
            
            // Update counter on input
            function updateCharCount() {
                var count = textarea.value.length;
                document.getElementById('charCount').innerHTML = count;
                
                // Change color if over 1000 characters
                if (count > 1000) {
                    document.getElementById('charCount').style.color = '#dc2626';
                } else {
                    document.getElementById('charCount').style.color = '#94a3b8';
                }
            }
            
            textarea.addEventListener('input', updateCharCount);
            updateCharCount();
        }
    });
</script>
</body>
</html>