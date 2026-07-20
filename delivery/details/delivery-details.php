<?php
// delivery/delivery-details.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$agent_sql = "SELECT agent_id, first_name, last_name, phone, vehicle_type, vehicle_registration, current_latitude, current_longitude FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);
if (!$agent) {
    header("Location: register.php");
    exit();
}
$agent_id = $agent['agent_id'];
$agent_name = $agent['first_name'] . ' ' . $agent['last_name'];

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($delivery_id <= 0) {
    header("Location: requests/requests.php");
    exit();
}

// Fetch delivery details with all necessary data
$sql = "SELECT d.*, 
               o.order_id, o.grand_total, o.delivery_address, o.special_instructions, o.order_date,
               o.delivery_fee,
               b.business_id,
               b.business_name, 
               b.location as business_location, 
               b.address as business_address,
               b.phone as business_phone, 
               b.business_hours,
               b.latitude as business_lat,
               b.longitude as business_lng,
               c.customer_id, 
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               u.phone as customer_phone,
               u.email as customer_email,
               c.delivery_latitude as customer_lat,
               c.delivery_longitude as customer_lng
        FROM deliveries d 
        JOIN orders o ON d.order_id = o.order_id 
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE d.delivery_id = '$delivery_id'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    $_SESSION['flash_message'] = "Delivery not found.";
    $_SESSION['flash_type'] = "danger";
    header("Location: requests/requests.php");
    exit();
}

// Determine if this delivery is still pending (can be accepted)
$is_pending = ($delivery['status'] == 'pending' && (int)$delivery['agent_id'] == 0);

// Handle accept action via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accept_delivery'])) {
    if (!$is_pending) {
        $_SESSION['flash_message'] = "This delivery is no longer available.";
        $_SESSION['flash_type'] = "danger";
    } else {
        $update_delivery = "UPDATE deliveries SET agent_id = '$agent_id', status = 'assigned', assigned_at = NOW() WHERE delivery_id = '$delivery_id'";
        if (mysqli_query($conn, $update_delivery)) {
            mysqli_query($conn, "UPDATE delivery_agents SET total_deliveries = total_deliveries + 1 WHERE agent_id = '$agent_id'");
            
            // Add to history
            $history_sql = "INSERT INTO delivery_history (delivery_id, status, notes, created_at) VALUES ('$delivery_id', 'assigned', 'Delivery accepted by $agent_name', NOW())";
            mysqli_query($conn, $history_sql);
            
            $_SESSION['flash_message'] = "✅ Delivery accepted successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: my-deliveries/my-deliveries.php");
            exit();
        } else {
            $_SESSION['flash_message'] = "Error accepting delivery: " . mysqli_error($conn);
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: delivery-details.php?id=$delivery_id");
    exit();
}

// Handle reject action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_delivery'])) {
    if ($is_pending) {
        $_SESSION['flash_message'] = "You have rejected this delivery.";
        $_SESSION['flash_type'] = "warning";
        header("Location: requests/requests.php");
        exit();
    }
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Delivery Details | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .delivery-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .delivery-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
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
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        /* Alerts */
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        .alert-warning { background: #fffbeb; color: #92400e; border-left-color: #f59e0b; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 1.25rem; }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }
        
        .info-section {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
        }
        .info-section h4 {
            font-size: 0.7rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .info-section h4 i { color: #e67e22; }
        .info-row {
            display: flex;
            padding: 0.3rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.8rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 100px;
            color: #64748b;
            font-weight: 500;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            font-weight: 500;
            word-break: break-word;
        }
        .info-value strong { color: #e67e22; }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #d1fae5; color: #059669; }
        .status-in_transit { background: #e0e7ff; color: #4338ca; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        
        /* Action Box */
        .action-box {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        .btn-accept {
            background: linear-gradient(105deg, #27ae60, #1e8449);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 2.5rem;
            border: none;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: 0 4px 12px rgba(39,174,96,0.2);
        }
        .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39,174,96,0.3);
        }
        .btn-reject {
            background: #fee2e2;
            color: #dc2626;
            padding: 0.8rem 2rem;
            border-radius: 2.5rem;
            border: none;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            margin-left: 0.5rem;
        }
        .btn-reject:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }
        .btn-disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            box-shadow: none;
        }
        .btn-disabled:hover { transform: none; }
        
        .status-info {
            padding: 0.8rem 1.2rem;
            border-radius: 0.75rem;
            display: inline-block;
        }
        .status-info.pending { background: #fef3c7; color: #d97706; }
        .status-info.in-progress { background: #dbeafe; color: #2563eb; }
        .status-info.completed { background: #d1fae5; color: #059669; }
        .status-info.unavailable { background: #f1f5f9; color: #64748b; }
        
        /* Responsive */
        @media (max-width: 1100px) {
            .delivery-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
            .info-grid { grid-template-columns: 1fr; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.2rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; justify-content: center; }
            .action-box { display: flex; flex-direction: column; gap: 0.5rem; }
            .btn-accept, .btn-reject { width: 100%; justify-content: center; margin-left: 0; }
        }
        @media (max-width: 480px) {
            .delivery-content { padding: 0.5rem; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> Delivery Details</h1>
            <p>Review delivery information before accepting</p>
        </div>
        <div class="header-actions">
            <a href="../requests/requests.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Requests
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?>">
            <i class="fas fa-<?php echo $_SESSION['flash_type'] == 'success' ? 'check-circle' : ($_SESSION['flash_type'] == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-receipt"></i> Order <?php echo $delivery['order_id']; ?>
                <span style="font-size:0.65rem; color:#94a3b8; font-weight:400; margin-left:0.3rem;">
                    Delivery ID: <?php echo $delivery_id; ?>
                </span>
            </h3>
            <span class="status-badge status-<?php echo $delivery['status']; ?>">
                <i class="fas <?php echo $delivery['status'] == 'pending' ? 'fa-clock' : ($delivery['status'] == 'assigned' ? 'fa-clipboard-list' : ($delivery['status'] == 'picked_up' ? 'fa-box' : ($delivery['status'] == 'in_transit' ? 'fa-truck' : ($delivery['status'] == 'delivered' ? 'fa-check-circle' : 'fa-times-circle')))); ?>"></i>
                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
            </span>
        </div>
        <div class="card-body">
            <!-- Info Grid -->
            <div class="info-grid">
                <!-- Business Information -->
                <div class="info-section">
                    <h4><i class="fas fa-store"></i> Business Information</h4>
                    <div class="info-row">
                        <span class="info-label">Business Name</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($delivery['business_name']); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Pickup Location</span>
                        <span class="info-value"><?php echo htmlspecialchars($delivery['business_location'] ?: $delivery['business_address']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><a href="tel:<?php echo $delivery['business_phone']; ?>" style="color:#e67e22;"><?php echo htmlspecialchars($delivery['business_phone']); ?></a></span>
                    </div>
                    <?php if (!empty($delivery['business_hours'])): ?>
                    <div class="info-row">
                        <span class="info-label">Business Hours</span>
                        <span class="info-value"><?php echo htmlspecialchars($delivery['business_hours']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Customer Information -->
                <div class="info-section">
                    <h4><i class="fas fa-user"></i> Customer Information</h4>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><strong><?php echo htmlspecialchars($delivery['customer_name']); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><a href="tel:<?php echo $delivery['customer_phone']; ?>" style="color:#e67e22;"><?php echo htmlspecialchars($delivery['customer_phone']); ?></a></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($delivery['customer_email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Delivery Address</span>
                        <span class="info-value"><?php echo nl2br(htmlspecialchars($delivery['delivery_address'])); ?></span>
                    </div>
                </div>

                <!-- Delivery Details -->
                <div class="info-section">
                    <h4><i class="fas fa-truck"></i> Delivery Details</h4>
                    <div class="info-row">
                        <span class="info-label">Delivery Fee</span>
                        <span class="info-value"><strong>TSh <?php echo number_format($delivery['delivery_fee']); ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Amount</span>
                        <span class="info-value">TSh <?php echo number_format($delivery['grand_total']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Date</span>
                        <span class="info-value"><?php echo date('M d, Y h:i A', strtotime($delivery['order_date'])); ?></span>
                    </div>
                    <?php if (!empty($delivery['special_instructions'])): ?>
                    <div class="info-row">
                        <span class="info-label">Instructions</span>
                        <span class="info-value" style="background:#fef3c7; padding:0.2rem 0.5rem; border-radius:0.3rem;"><?php echo nl2br(htmlspecialchars($delivery['special_instructions'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Box -->
            <div class="action-box">
                <?php if ($is_pending): ?>
                    <form method="POST" onsubmit="return confirm('Accept this delivery? You will be responsible for delivering this order.');" style="display:inline;">
                        <button type="submit" name="accept_delivery" class="btn-accept">
                            <i class="fas fa-check-circle"></i> Accept Delivery
                        </button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this delivery?');">
                        <button type="submit" name="reject_delivery" class="btn-reject">
                            <i class="fas fa-times-circle"></i> Reject
                        </button>
                    </form>
                    <div style="margin-top:0.5rem; font-size:0.7rem; color:#94a3b8;">
                        <i class="fas fa-info-circle"></i> By accepting, you agree to deliver this order
                    </div>
                <?php elseif ($delivery['status'] == 'assigned' || $delivery['status'] == 'picked_up' || $delivery['status'] == 'in_transit'): ?>
                    <div class="status-info in-progress">
                        <i class="fas fa-spinner fa-pulse"></i> This delivery is already in progress
                    </div>
                <?php elseif ($delivery['status'] == 'delivered'): ?>
                    <div class="status-info completed">
                        <i class="fas fa-check-circle"></i> This delivery has been completed
                    </div>
                <?php elseif ($delivery['status'] == 'cancelled'): ?>
                    <div class="status-info unavailable" style="background:#fee2e2; color:#dc2626;">
                        <i class="fas fa-times-circle"></i> This delivery has been cancelled
                    </div>
                <?php else: ?>
                    <div class="status-info unavailable">
                        <i class="fas fa-lock"></i> This delivery is no longer available
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>