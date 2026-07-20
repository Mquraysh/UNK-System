<?php
// admin/deliveries/update-status.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id == 0) {
    header("Location: index.php");
    exit();
}

// Get delivery details with customer info
$delivery_sql = "SELECT d.*, o.order_id, o.grand_total, o.delivery_address, u.phone,
                        o.customer_id,
                        b.business_name,
                        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                        CONCAT(a.first_name, ' ', a.last_name) as agent_name
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.order_id
                 JOIN businesses b ON o.business_id = b.business_id
                 JOIN customers c ON o.customer_id = c.customer_id
                 LEFT JOIN users u ON u.user_id = u.user_id
                 LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
                 WHERE d.delivery_id = $delivery_id";
$delivery_res = mysqli_query($conn, $delivery_sql);
$delivery = mysqli_fetch_assoc($delivery_res);

if (!$delivery) {
    header("Location: index.php");
    exit();
}

// Status definitions
$statuses = [
    'pending' => ['label' => 'Pending', 'icon' => 'fa-clock', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
    'assigned' => ['label' => 'Assigned', 'icon' => 'fa-user-check', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
    'picked_up' => ['label' => 'Picked Up', 'icon' => 'fa-box', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
    'in_transit' => ['label' => 'In Transit', 'icon' => 'fa-truck', 'color' => '#ec4899', 'bg' => '#fce7f3'],
    'delivered' => ['label' => 'Delivered', 'icon' => 'fa-check-circle', 'color' => '#10b981', 'bg' => '#d1fae5'],
    'cancelled' => ['label' => 'Cancelled', 'icon' => 'fa-times-circle', 'color' => '#ef4444', 'bg' => '#fee2e2']
];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_status'])) {
    $new_status = $_POST['new_status'];
    
    if (!array_key_exists($new_status, $statuses)) {
        $_SESSION['flash_message'] = "Invalid status selected.";
        $_SESSION['flash_type'] = "danger";
        header("Location: update-status.php?id=$delivery_id");
        exit();
    }
    
    $update_sql = "UPDATE deliveries SET status = '$new_status'";
    if ($new_status == 'delivered') {
        $update_sql .= ", delivered_at = NOW()";
    }
    $update_sql .= " WHERE delivery_id = $delivery_id";
    
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['flash_message'] = "Delivery status updated to " . $statuses[$new_status]['label'];
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Failed to update status: " . mysqli_error($conn);
        $_SESSION['flash_type'] = "danger";
    }
    
    header("Location: index.php");
    exit();
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Update Delivery Status - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #2c3e50;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 0.6rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, #fafcff, #ffffff);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .card-header h2 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h2 i { color: #e67e22; }
        .card-header .delivery-id {
            font-family: monospace;
            color: #e67e22;
            font-weight: 800;
        }
        .card-body { padding: 1.5rem; }
        
        /* Delivery Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .info-item {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .info-item .label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .info-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #0f172a;
            margin-top: 0.2rem;
        }
        .info-item .value .customer-name {
            color: #e67e22;
        }
        
        /* Current Status Display */
        .current-status {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .current-status .status-icon {
            font-size: 2rem;
            width: 48px;
            text-align: center;
        }
        .current-status .status-label {
            font-size: 1.1rem;
            font-weight: 700;
        }
        .current-status .status-sub {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        /* Status Steps */
        .status-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            position: relative;
            padding: 0.5rem 0;
        }
        .status-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e2e8f0;
            transform: translateY(-50%);
            z-index: 0;
        }
        .status-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            position: relative;
            z-index: 1;
            flex: 1;
        }
        .status-step .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            color: #94a3b8;
            transition: 0.3s;
        }
        .status-step.active .step-circle {
            background: #e67e22;
            color: white;
            box-shadow: 0 0 0 4px rgba(230,126,34,0.2);
        }
        .status-step.done .step-circle {
            background: #10b981;
            color: white;
        }
        .status-step .step-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: #64748b;
            text-align: center;
        }
        .status-step.active .step-label { color: #e67e22; }
        .status-step.done .step-label { color: #10b981; }
        
        /* Form */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            color: #475569;
        }
        .form-group label .required { color: #ef4444; }
        .form-group select {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.6rem;
            font-size: 0.85rem;
            font-family: 'Inter', sans-serif;
            background: white;
            transition: 0.2s;
        }
        .form-group select:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .form-group .help-text {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 0.25rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.6rem;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            width: 100%;
        }
        .btn-primary {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .status-steps { flex-wrap: wrap; gap: 0.5rem; }
            .status-steps::before { display: none; }
            .status-step { flex: 0 0 calc(33.33% - 0.5rem); }
        }
        @media (max-width: 480px) {
            .card-body { padding: 1rem; }
            .status-step { flex: 0 0 calc(50% - 0.5rem); }
            .current-status { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-edit"></i> Update Delivery Status</h1>
            <p>Manage delivery status and track progress</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Deliveries</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-truck"></i> Delivery <span class="delivery-id"><?php echo $delivery_id; ?></span></h2>
            <span style="font-size: 0.75rem; color: #64748b;">Order <?php echo $delivery['order_id']; ?></span>
        </div>
        <div class="card-body">
            <!-- Delivery Info -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Business</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Customer</span>
                    <span class="value">
                        <span class="customer-name"><?php echo htmlspecialchars($delivery['customer_name'] ?? 'N/A'); ?></span>
                        <br><small style="font-weight:400; color:#94a3b8; font-size:0.75rem;"><?php echo htmlspecialchars($delivery['phone']); ?></small>
                    </span>
                </div>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="label">Delivery Address</span>
                    <span class="value" style="font-size: 0.85rem;"><?php echo htmlspecialchars($delivery['delivery_address']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Amount</span>
                    <span class="value" style="color: #e67e22;">TSh <?php echo number_format($delivery['grand_total']); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Assigned Agent</span>
                    <span class="value"><?php echo $delivery['agent_name'] ? htmlspecialchars($delivery['agent_name']) : '<span style="color:#94a3b8;">Not assigned</span>'; ?></span>
                </div>
            </div>
            
            <!-- Current Status -->
            <div class="current-status">
                <div class="status-icon">
                    <i class="fas <?php echo $statuses[$delivery['status']]['icon']; ?>" style="color: <?php echo $statuses[$delivery['status']]['color']; ?>;"></i>
                </div>
                <div>
                    <div class="status-label" style="color: <?php echo $statuses[$delivery['status']]['color']; ?>;">
                        <?php echo $statuses[$delivery['status']]['label']; ?>
                    </div>
                    <div class="status-sub">Current status • Updated <?php echo date('M d, Y h:i A', strtotime($delivery['updated_at'] ?? $delivery['created_at'])); ?></div>
                </div>
            </div>
            
            <!-- Status Steps -->
            <div class="status-steps">
                <?php 
                $step_labels = ['Pending', 'Assigned', 'Picked Up', 'In Transit', 'Delivered'];
                $step_icons = ['fa-clock', 'fa-user-check', 'fa-box', 'fa-truck', 'fa-check-circle'];
                $current_status = $delivery['status'];
                $status_keys = ['pending', 'assigned', 'picked_up', 'in_transit', 'delivered'];
                $current_index = array_search($current_status, $status_keys);
                ?>
                <?php foreach ($step_labels as $index => $label): 
                    $status_key = $status_keys[$index];
                    $is_done = $index <= $current_index;
                    $is_active = $index == $current_index;
                ?>
                <div class="status-step <?php echo $is_active ? 'active' : ($is_done ? 'done' : ''); ?>">
                    <div class="step-circle">
                        <i class="fas <?php echo $step_icons[$index]; ?>"></i>
                    </div>
                    <span class="step-label"><?php echo $label; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Update Form -->
            <form method="POST">
                <div class="form-group">
                    <label for="new_status">New Status <span class="required">*</span></label>
                    <select name="new_status" id="new_status" required>
                        <?php foreach ($statuses as $key => $status): ?>
                            <option value="<?php echo $key; ?>" <?php echo $key == $delivery['status'] ? 'selected' : ''; ?>>
                                <?php echo $status['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>