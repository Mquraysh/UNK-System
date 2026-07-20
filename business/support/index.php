<?php
// business/support/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business data
$bus_res = mysqli_query($conn, "SELECT b.*, u.email FROM businesses b JOIN users u ON b.user_id = u.user_id WHERE b.user_id = '$user_id'");
if (mysqli_num_rows($bus_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$business = mysqli_fetch_assoc($bus_res);
$business_id = $business['business_id'];
$business_name = $business['business_name'];
$business_email = $business['email'];

// Fetch customers for dropdown
$customers = [];
$cust_res = mysqli_query($conn, "SELECT c.customer_id, c.first_name, c.last_name, u.email FROM customers c JOIN users u ON c.user_id = u.user_id ORDER BY c.first_name");
while ($row = mysqli_fetch_assoc($cust_res)) $customers[] = $row;

// Fetch delivery agents for dropdown
$delivery = [];
$del_res = mysqli_query($conn, "SELECT d.agent_id, d.first_name, d.last_name, d.vehicle_type, u.email FROM delivery_agents d JOIN users u ON d.user_id = u.user_id WHERE d.is_available = 1");
while ($row = mysqli_fetch_assoc($del_res)) $delivery[] = $row;

// Handle new ticket submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ticket'])) {
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $send_to = $_POST['send_to'];
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $delivery_id = (int)($_POST['delivery_id'] ?? 0);
    $attachment = '';

    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } elseif ($send_to == 'customer' && $customer_id == 0) {
        $error = "Please select a customer.";
    } elseif ($send_to == 'delivery' && $delivery_id == 0) {
        $error = "Please select a delivery agent.";
    } else {
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $target_dir = "../../assets/uploads/support/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','pdf','doc','docx'];
            if (in_array($ext, $allowed) && $_FILES['attachment']['size'] <= 5*1024*1024) {
                $filename = time() . '_' . uniqid() . '.' . $ext;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $filename)) {
                    $attachment = "/UNK-System/assets/uploads/support/" . $filename;
                }
            }
        }

        $ticket_no = 'BIZ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $assigned_to_type = 'admin';
        $assigned_to_id = 'NULL';

        if ($send_to == 'customer' && $customer_id > 0) {
            $assigned_to_type = 'customer';
            $assigned_to_id = $customer_id;
        } elseif ($send_to == 'delivery' && $delivery_id > 0) {
            $assigned_to_type = 'delivery';
            $assigned_to_id = $delivery_id;
        }

        function esc($v) {
            global $conn;
            return mysqli_real_escape_string($conn, $v);
        }

        $sql = "INSERT INTO support_tickets 
                (ticket_no, created_by_type, created_by_id, assigned_to_type, assigned_to_id, subject, message, category, priority, attachment) 
                VALUES ('" . esc($ticket_no) . "', 'business', $business_id, '$assigned_to_type', $assigned_to_id,
                        '" . esc($subject) . "', '" . esc($message) . "', '" . esc($category) . "', '" . esc($priority) . "', '" . esc($attachment) . "')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['flash_message'] = "Ticket #$ticket_no created! Sent to: " . ucfirst($assigned_to_type);
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to create ticket. Please try again.";
        }
    }
}

// Get recent tickets
$recent_tickets = [];
$rec_sql = "SELECT t.*, 
            (SELECT message FROM support_replies WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT reply_by_type FROM support_replies WHERE ticket_id = t.id ORDER BY created_at DESC LIMIT 1) as last_sender_type,
            CASE 
                WHEN t.assigned_to_type = 'admin' THEN 'Admin'
                WHEN t.assigned_to_type = 'business' THEN (SELECT business_name FROM businesses WHERE business_id = t.assigned_to_id)
                WHEN t.assigned_to_type = 'delivery' THEN (SELECT CONCAT(first_name,' ',last_name) FROM delivery_agents WHERE agent_id = t.assigned_to_id)
                WHEN t.assigned_to_type = 'customer' THEN (SELECT CONCAT(first_name,' ',last_name) FROM customers WHERE customer_id = t.assigned_to_id)
                ELSE 'Unknown'
            END as recipient_name
            FROM support_tickets t
            WHERE (t.created_by_type = 'business' AND t.created_by_id = $business_id)
               OR (t.assigned_to_type = 'business' AND t.assigned_to_id = $business_id)
            ORDER BY GREATEST(t.created_at, IFNULL((SELECT MAX(created_at) FROM support_replies WHERE ticket_id = t.id), t.created_at)) DESC
            LIMIT 10";
$rec_res = mysqli_query($conn, $rec_sql);
while ($row = mysqli_fetch_assoc($rec_res)) {
    if (empty($row['last_message'])) {
        $row['last_message'] = $row['message'];
        $row['last_sender_type'] = $row['created_by_type'];
    }
    $recent_tickets[] = $row;
}

$flash = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Business Support | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content {
                margin-left: 0;
                padding: 1.25rem;
            }
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-header h1 {
            font-size: 1.9rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i {
            color: #e67e22;
            background: none;
        }
        .page-header p {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        /* Stats Cards */
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
        
        /* Alerts */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
            background: white;
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
        
        /* Contact Cards */
        .contact-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .contact-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
            transition: all 0.25s;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .contact-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: #e67e22;
        }
        .contact-card i {
            font-size: 2rem;
            color: #e67e22;
            margin-bottom: 0.75rem;
        }
        .contact-card h3 {
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .contact-card p {
            font-size: 0.75rem;
            color: #64748b;
            margin: 0.25rem 0;
        }
        
        /* Support Layout */
        .support-layout {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .card-header h3 i {
            color: #e67e22;
        }
        .card-body {
            padding: 1.25rem 1.5rem;
        }
        
        /* FAQ */
        .faq-item {
            margin-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.75rem;
        }
        .faq-question {
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.25rem 0;
            transition: color 0.2s;
            font-size: 0.85rem;
        }
        .faq-question:hover {
            color: #e67e22;
        }
        .faq-question i {
            transition: transform 0.2s;
            color: #e67e22;
        }
        .faq-question.active i {
            transform: rotate(180deg);
        }
        .faq-answer {
            display: none;
            padding: 0.5rem 0 0.25rem;
            color: #64748b;
            font-size: 0.75rem;
            line-height: 1.5;
        }
        .faq-answer.show {
            display: block;
        }
        
        /* Form */
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.75rem;
            margin-bottom: 0.35rem;
            color: #1e293b;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-control, select.form-control {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus, select.form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .help-text {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 0.25rem;
            display: block;
        }
        
        /* Priority Buttons */
        .priority-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .priority-btn {
            flex: 1;
            padding: 0.5rem;
            border: 2px solid #e2e8f0;
            border-radius: 2rem;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.75rem;
            transition: all 0.2s;
            background: white;
        }
        .priority-btn.low.selected {
            background: #e0f2e9;
            border-color: #27ae60;
            color: #27ae60;
        }
        .priority-btn.medium.selected {
            background: #fef3c7;
            border-color: #f39c12;
            color: #d97706;
        }
        .priority-btn.high.selected {
            background: #fee2e2;
            border-color: #e74c3c;
            color: #e74c3c;
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            width: 100%;
        }
        .btn-primary {
            background: linear-gradient(105deg, #e67e22, #d35400);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230,126,34,0.3);
        }
        .btn-outline {
            background: white;
            border: 1.5px solid #e2e8f0;
            color: #64748b;
            text-decoration: none;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .btn-outline:hover {
            border-color: #e67e22;
            color: #e67e22;
            background: #fdf2e9;
        }
        
        /* Tickets Section */
        .tickets-section {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-top: 2rem;
        }
        .tickets-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .tickets-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .ticket-table {
            width: 100%;
            border-collapse: collapse;
        }
        .ticket-table th, .ticket-table td {
            padding: 0.8rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
        }
        .ticket-table th {
            background: #fafcff;
            font-weight: 600;
            color: #64748b;
        }
        .ticket-status {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
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
            color: #27ae60;
        }
        .status-closed {
            background: #e2e8f0;
            color: #64748b;
        }
        
        .priority-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .priority-dot.high { background: #e74c3c; }
        .priority-dot.medium { background: #f39c12; }
        .priority-dot.low { background: #27ae60; }
        
        .last-message-preview {
            font-size: 0.7rem;
            color: #64748b;
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .last-sender {
            font-size: 0.6rem;
            font-weight: 600;
            display: inline-block;
            margin-right: 5px;
        }
        .last-sender.business { color: #e67e22; }
        .last-sender.admin { color: #8e44ad; }
        .last-sender.customer { color: #2c3e50; }
        .last-sender.delivery { color: #3498db; }
        
        /* Resources */
        .resources-section {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            padding: 1.25rem 1.5rem;
            margin-top: 2rem;
        }
        .resources-section h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
        }
        .resources-section h3 i {
            color: #e67e22;
        }
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }
        .resource-link {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.6rem 1rem;
            background: #fafcff;
            border-radius: 0.75rem;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
            font-size: 0.75rem;
        }
        .resource-link i {
            font-size: 0.9rem;
            color: #e67e22;
            width: 1.2rem;
        }
        .resource-link:hover {
            background: #fdf2e9;
            border-color: #e67e22;
            transform: translateX(4px);
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            opacity: 0.5;
        }
        
        @media (max-width: 1100px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
            .contact-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; }
            .support-layout { grid-template-columns: 1fr; gap: 1.5rem; }
        }
        @media (max-width: 768px) {
            .contact-grid { grid-template-columns: 1fr; }
            .ticket-table th, .ticket-table td { padding: 0.6rem 0.8rem; }
            .last-message-preview { max-width: 150px; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-headset"></i> Business Support Center</h1>
        <p>Get help from Admin, contact your customers, or reach out to delivery agents</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Contact Cards -->
    <div class="contact-grid">
        <div class="contact-card"><i class="fas fa-phone-alt"></i><h3>Call Admin</h3><p>+255 615 215 404</p><p>Mon–Fri, 9am–6pm</p></div>
        <div class="contact-card"><i class="fas fa-envelope"></i><h3>Email Support</h3><p>support@unksystem.com</p><p>24/7 response</p></div>
        <div class="contact-card"><i class="fab fa-whatsapp"></i><h3>WhatsApp</h3><p>+255 615 215 404</p><p>Quick replies</p></div>
        <div class="contact-card"><i class="fas fa-map-marker-alt"></i><h3>Office</h3><p>Kariakoo Market, Dar es Salaam</p></div>
    </div>

    <div class="support-layout">
        <!-- FAQ Section -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3></div>
            <div class="card-body">
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>📦 How do I contact Admin?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Select "Admin" in the Send To dropdown when creating a ticket. Admin will respond within 24 hours.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>👤 How do I contact a Customer?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Choose "Customer" in Send To, then pick the specific customer from the list.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>🚚 How do I contact a Delivery Agent?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Choose "Delivery Agent" in Send To, then select the agent from the list.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>💰 How do I resolve a payment issue?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Select "Payment Problem" category and send to Admin. Our team will assist you.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>🔄 What if a customer cancels an order?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Contact the customer directly via the "Customer" option, or open a ticket to Admin for assistance.</div></div>
                <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)"><span>📊 How do I update my business profile?</span><i class="fas fa-chevron-down"></i></div><div class="faq-answer">Go to "Business Profile" page where you can update your business name, location, logo, and description.</div></div>
            </div>
        </div>

        <!-- Ticket Submission Form -->
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-paper-plane"></i> Open a New Ticket</h3></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group"><label>Your Business</label><input type="text" class="form-control" value="<?php echo htmlspecialchars($business_name); ?>" readonly style="background:#f8fafc;"></div>
                    <div class="form-group"><label>Email</label><input type="email" class="form-control" value="<?php echo htmlspecialchars($business_email); ?>" readonly style="background:#f8fafc;"></div>
                    <div class="form-group"><label>Send To <span class="required">*</span></label>
                        <select name="send_to" class="form-control" id="sendToSelect" required>
                            <option value="admin">👨‍💼 Admin (System Administrator)</option>
                            <option value="customer">👤 Customer</option>
                            <option value="delivery">🚚 Delivery Agent</option>
                        </select>
                    </div>
                    <div class="form-group" id="customerGroup" style="display: none;">
                        <label>🏪 Select Customer <span class="required">*</span></label>
                        <select name="customer_id" class="form-control">
                            <option value="">-- Choose customer --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?php echo $c['customer_id']; ?>"><?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?> (<?php echo $c['email']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="deliveryGroup" style="display: none;">
                        <label>🚚 Select Delivery Agent <span class="required">*</span></label>
                        <select name="delivery_id" class="form-control">
                            <option value="">-- Choose agent --</option>
                            <?php foreach ($delivery as $d): ?>
                            <option value="<?php echo $d['agent_id']; ?>"><?php echo htmlspecialchars($d['first_name'] . ' ' . $d['last_name']); ?> (<?php echo ucfirst($d['vehicle_type']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Category <span class="required">*</span></label>
                        <select name="category" class="form-control" required>
                            <option value="general">📋 General Inquiry</option>
                            <option value="order">📦 Order Issue</option>
                            <option value="payment">💰 Payment Problem</option>
                            <option value="delivery">🚚 Delivery Issue</option>
                            <option value="product">🛍️ Product Issue</option>
                            <option value="account">👤 Account Issue</option>
                            <option value="technical">⚙️ Technical Problem</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Subject <span class="required">*</span></label><input type="text" name="subject" class="form-control" required placeholder="Brief summary of your issue"></div>
                    <div class="form-group"><label>Priority <span class="required">*</span></label>
                        <div class="priority-buttons">
                            <div class="priority-btn low" data-priority="low" onclick="selectPriority('low')">Low</div>
                            <div class="priority-btn medium selected" data-priority="medium" onclick="selectPriority('medium')">Medium</div>
                            <div class="priority-btn high" data-priority="high" onclick="selectPriority('high')">High</div>
                        </div>
                        <input type="hidden" name="priority" id="priorityInput" value="medium">
                    </div>
                    <div class="form-group"><label>Message <span class="required">*</span></label><textarea name="message" class="form-control" rows="5" required placeholder="Please describe your issue in detail..."></textarea></div>
                    <div class="form-group"><label>Attachment (optional)</label><input type="file" name="attachment" class="form-control" accept="image/*,.pdf,.doc,.docx"><small class="help-text">Max 5MB (JPG, PNG, PDF, DOC)</small></div>
                    <button type="submit" name="submit_ticket" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Tickets -->
    <?php if (!empty($recent_tickets)): ?>
    <div class="tickets-section">
        <div class="tickets-header">
            <h3><i class="fas fa-ticket-alt"></i> Recent Tickets</h3>
            <a href="my_tickets.php" class="btn-outline"><i class="fas fa-arrow-right"></i> View all tickets</a>
        </div>
        <div style="overflow-x: auto;">
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Subject</th>
                        <th>From / Sent To</th>
                        <th>Priority</th>
                        <th>Latest Activity</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $t):
                        if ($t['created_by_type'] == 'business' && $t['created_by_id'] == $business_id) {
                            $from_to = ($t['assigned_to_type'] == 'admin') ? '📤 Sent to Admin' : (($t['assigned_to_type'] == 'customer') ? '📤 Sent to Customer' : '📤 Sent to Delivery');
                        } else {
                            $from_to = '📥 From Customer';
                        }
                        $last_sender = '';
                        if ($t['last_sender_type'] == 'business') $last_sender = 'You';
                        elseif ($t['last_sender_type'] == 'admin') $last_sender = 'Admin';
                        elseif ($t['last_sender_type'] == 'customer') $last_sender = 'Customer';
                        elseif ($t['last_sender_type'] == 'delivery') $last_sender = 'Delivery';
                        else $last_sender = 'System';
                        $sender_class = $t['last_sender_type'] ?? 'business';
                        $recipient = $t['recipient_name'] ?? 'Unknown';
                    ?>
                    <tr onclick="window.location.href='ticket.php?id=<?php echo $t['id']; ?>&source=<?php echo $t['created_by_type']; ?>'" style="cursor: pointer;">
                        <td><strong>#<?php echo htmlspecialchars($t['ticket_no']); ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($t['subject'], 0, 40)); ?>…</td>
                        <td><?php echo $from_to; ?></td>
                        <td>
                            <span class="priority-dot <?php echo $t['priority']; ?>"></span>
                            <?php echo ucfirst($t['priority']); ?>
                        </td>
                        <td>
                            <div class="last-message-preview">
                                <span class="last-sender <?php echo $sender_class; ?>"><?php echo $last_sender; ?>:</span>
                                <?php echo htmlspecialchars(substr($t['last_message'], 0, 50)); ?>...
                            </div>
                        </td>
                        <td><span class="ticket-status status-<?php echo $t['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($t['status'])); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                        <td><a href="ticket.php?id=<?php echo $t['id']; ?>&source=<?php echo $t['created_by_type']; ?>" class="btn-outline" style="padding: 0.2rem 0.6rem; font-size: 0.65rem;" onclick="event.stopPropagation();">View →</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="tickets-section">
        <div class="tickets-header">
            <h3><i class="fas fa-ticket-alt"></i> Recent Tickets</h3>
            <a href="my_tickets.php" class="btn-outline">View all tickets</a>
        </div>
        <div class="empty-state">
            <i class="fas fa-ticket-alt"></i>
            <p>No tickets yet. Create your first support ticket.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Resources -->
    <div class="resources-section">
        <h3><i class="fas fa-life-ring"></i> Quick Resources</h3>
        <div class="resources-grid">
            <a href="../das/dashboard.php" class="resource-link"><i class="fas fa-tachometer-alt"></i> Business Dashboard</a>
            <a href="../orders/index.php" class="resource-link"><i class="fas fa-shopping-bag"></i> Manage Orders</a>
            <a href="../products/index.php" class="resource-link"><i class="fas fa-box"></i> Manage Products</a>
            <a href="../inventory/index.php" class="resource-link"><i class="fas fa-warehouse"></i> Inventory</a>
            <a href="../reports/sales.php" class="resource-link"><i class="fas fa-chart-line"></i> Sales Reports</a>
            <a href="../settings/general.php" class="resource-link"><i class="fas fa-user-edit"></i> Update Profile</a>
            <a href="my_tickets.php" class="resource-link"><i class="fas fa-ticket-alt"></i> My Tickets</a>
            <a href="index.php" class="resource-link"><i class="fas fa-headset"></i> Support Center</a>
        </div>
    </div>
</div>

<script>
function toggleFAQ(elem) {
    elem.classList.toggle('active');
    elem.nextElementSibling.classList.toggle('show');
}

function selectPriority(prio) {
    document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('selected'));
    document.querySelector(`.priority-btn.${prio}`).classList.add('selected');
    document.getElementById('priorityInput').value = prio;
}

const sendTo = document.getElementById('sendToSelect');
const customerGroup = document.getElementById('customerGroup');
const deliveryGroup = document.getElementById('deliveryGroup');

function toggleGroups() {
    let val = sendTo.value;
    customerGroup.style.display = (val === 'customer') ? 'block' : 'none';
    deliveryGroup.style.display = (val === 'delivery') ? 'block' : 'none';
    if (customerGroup.querySelector('select')) customerGroup.querySelector('select').required = (val === 'customer');
    if (deliveryGroup.querySelector('select')) deliveryGroup.querySelector('select').required = (val === 'delivery');
}

sendTo.addEventListener('change', toggleGroups);
toggleGroups();
</script>
</body>
</html>