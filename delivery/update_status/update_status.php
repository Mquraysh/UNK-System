<?php
// delivery/update_status.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$agent_sql = "SELECT agent_id FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
if (mysqli_num_rows($agent_result) == 0) {
    header("Location: register.php");
    exit();
}
$agent = mysqli_fetch_assoc($agent_result);
$agent_id = $agent['agent_id'];

// Helper: send JSON response
function sendJsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// UPDATE AGENT AVAILABILITY (AJAX/JSON) 
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
if ($is_ajax && isset($_POST['is_available'])) {
    $is_available = (int)$_POST['is_available'];
    $update_sql = "UPDATE delivery_agents SET is_available = '$is_available' WHERE agent_id = '$agent_id'";
    if (mysqli_query($conn, $update_sql)) {
        sendJsonResponse(true, $is_available ? "You are now online" : "You are now offline");
    } else {
        sendJsonResponse(false, "Failed to update status");
    }
}

// Handle non-AJAX POST for agent availability (fallback)
if (!$is_ajax && isset($_POST['is_available'])) {
    $is_available = (int)$_POST['is_available'];
    mysqli_query($conn, "UPDATE delivery_agents SET is_available = '$is_available' WHERE agent_id = '$agent_id'");
    $_SESSION['flash_message'] = $is_available ? "You are now online" : "You are now offline";
    $_SESSION['flash_type'] = "success";
    header("Location: ../das/dashboard.php");
    exit();
}

// ========== UPDATE DELIVERY STATUS (AJAX/JSON) ==========
if (isset($_GET['id'])) {
    $delivery_id = (int)$_GET['id'];
    
    // Verify delivery belongs to agent
    $check_sql = "SELECT d.*, o.order_id, o.grand_total, o.delivery_address, o.status as order_status, b.business_name
                  FROM deliveries d 
                  JOIN orders o ON d.order_id = o.order_id
                  JOIN businesses b ON o.business_id = b.business_id
                  WHERE d.delivery_id = '$delivery_id' AND d.agent_id = '$agent_id'";
    $check_result = mysqli_query($conn, $check_sql);
    if (mysqli_num_rows($check_result) == 0) {
        if ($is_ajax) sendJsonResponse(false, "Delivery not found or not assigned to you.");
        $_SESSION['flash_message'] = "Delivery not found or not assigned to you.";
        $_SESSION['flash_type'] = "danger";
        header("Location: ../my-deliveries/my-deliveries.php");
        exit();
    }
    $delivery = mysqli_fetch_assoc($check_result);
    $current_status = $delivery['status'];
    $order_id = $delivery['order_id'];
    $order_current_status = $delivery['order_status'];
    
    // Define allowed progression
    $status_order = ['assigned', 'picked_up', 'in_transit', 'delivered'];
    $current_index = array_search($current_status, $status_order);
    
    // ========== NEW: Check if trying to set 'picked_up' but order is not ready ==========
    function isOrderReadyForPickup($order_status) {
        return in_array($order_status, ['ready', 'preparing']);
    }
    
    // Handle AJAX POST (JSON)
    if ($is_ajax && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        $new_status = isset($input['new_status']) ? $input['new_status'] : '';
        $new_index = array_search($new_status, $status_order);
        
        // Validation
        if (!in_array($new_status, $status_order)) {
            sendJsonResponse(false, "Invalid status selected.");
        }
        if ($new_index === false || $new_index <= $current_index) {
            sendJsonResponse(false, "You cannot go back to a previous status.");
        }
        if ($new_index > $current_index + 1) {
            sendJsonResponse(false, "Please update step by step. Next allowed status: " . ($status_order[$current_index+1] ?? 'delivered'));
        }
        
        // Special check: if trying to set 'picked_up', ensure order is ready/preparing
        if ($new_status == 'picked_up' && !isOrderReadyForPickup($order_current_status)) {
            sendJsonResponse(false, "Cannot pick up order because it is not yet ready. Business must mark order as 'Ready' first. Current order status: " . $order_current_status);
        }
        
        // Update deliveries table
        $update_delivery = "UPDATE deliveries SET status = '$new_status', updated_at = NOW()";
        if ($new_status == 'delivered') {
            $update_delivery .= ", delivered_at = NOW()";
        }
        mysqli_query($conn, $update_delivery . " WHERE delivery_id = '$delivery_id'");
        
        // Map delivery status to order status (matching customer expectations)
        $order_status_map = [
            'assigned'   => 'confirmed',
            'picked_up'  => 'picked_up',
            'in_transit' => 'in_transit',
            'delivered'  => 'delivered'
        ];
        $new_order_status = $order_status_map[$new_status];
        mysqli_query($conn, "UPDATE orders SET status = '$new_order_status' WHERE order_id = '$order_id'");
        
        // Also update delivery_updates table
        $update_note = "Status updated by agent to " . str_replace('_', ' ', $new_status);
        mysqli_query($conn, "INSERT INTO delivery_updates (delivery_id, status, notes, update_time) 
                              VALUES ('$delivery_id', '$new_status', '$update_note', NOW())");
        
        sendJsonResponse(true, "Status updated to " . str_replace('_', ' ', ucfirst($new_status)), [
            'new_status' => $new_status,
            'order_status' => $new_order_status
        ]);
    }
    
    // Handle non-AJAX POST (fallback)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_ajax && isset($_POST['new_status'])) {
        $new_status = $_POST['new_status'];
        $new_index = array_search($new_status, $status_order);
        
        if (!in_array($new_status, $status_order)) {
            $_SESSION['flash_message'] = "Invalid status selected.";
            $_SESSION['flash_type'] = "danger";
            header("Location: update_status.php?id=$delivery_id");
            exit();
        }
        if ($new_index === false || $new_index <= $current_index) {
            $_SESSION['flash_message'] = "You cannot go back to a previous status.";
            $_SESSION['flash_type'] = "danger";
            header("Location: update_status.php?id=$delivery_id");
            exit();
        }
        
        // Check: if picking up, order must be ready/preparing
        if ($new_status == 'picked_up' && !isOrderReadyForPickup($order_current_status)) {
            $_SESSION['flash_message'] = "Cannot pick up order because it is not yet ready. Business must mark order as 'Ready' first.";
            $_SESSION['flash_type'] = "danger";
            header("Location: update_status/update_status.php?id=$delivery_id");
            exit();
        }
        
        // Update deliveries
        $update_delivery = "UPDATE deliveries SET status = '$new_status', updated_at = NOW()";
        if ($new_status == 'delivered') $update_delivery .= ", delivered_at = NOW()";
        mysqli_query($conn, $update_delivery . " WHERE delivery_id = '$delivery_id'");
        
        // Map order status
        $order_status_map = ['assigned'=>'confirmed', 'picked_up'=>'picked_up', 'in_transit'=>'in_transit', 'delivered'=>'delivered'];
        $new_order_status = $order_status_map[$new_status];
        mysqli_query($conn, "UPDATE orders SET status = '$new_order_status' WHERE order_id = '$order_id'");
        
        mysqli_query($conn, "INSERT INTO delivery_updates (delivery_id, status, notes, update_time) 
                              VALUES ('$delivery_id', '$new_status', 'Status updated by agent', NOW())");
        
        $_SESSION['flash_message'] = "Delivery status updated to " . str_replace('_', ' ', ucfirst($new_status));
        $_SESSION['flash_type'] = "success";
        header("Location: ../my-deliveries/my-deliveries.php");
        exit();
    }
    
    // Display the HTML form (non-AJAX, or initial load)
    include '../includes/delivery_sidebar.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Update Delivery Status | UNK System</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            /* Same styles as before – unchanged */
            * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; line-height: 1.5; }
            .delivery-content { margin-left: 280px; padding: 32px 40px; min-height: 100vh; background: #f0f2f5; }
            .page-header { margin-bottom: 28px; }
            .page-header h1 { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #1e293b, #2c3e50); -webkit-background-clip: text; background-clip: text; color: transparent; display: flex; align-items: center; gap: 12px; }
            .page-header h1 i { color: #e67e22; }
            .card { background: white; border-radius: 28px; border: 1px solid #eef2f8; overflow: hidden; max-width: 1200px; margin: 0 auto; }
            .card-header { padding: 24px 28px; background: #fafcff; border-bottom: 1px solid #f0f2f5; }
            .card-header h3 { font-size: 18px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
            .card-header h3 i { color: #e67e22; }
            .card-body { padding: 28px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; background: #f8fafc; padding: 20px; border-radius: 20px; }
            .info-item { display: flex; flex-direction: column; }
            .info-label { font-size: 12px; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
            .info-value { font-size: 15px; font-weight: 600; color: #1e293b; }
            .form-group { margin-bottom: 24px; }
            .form-group label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
            .form-control { width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 20px; font-size: 14px; background: white; }
            .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
            .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; }
            .btn-primary { background: linear-gradient(105deg, #e67e22, #d35400); color: white; }
            .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,126,34,0.3); }
            .btn-secondary { background: #f8fafc; border: 1px solid #e2e8f0; color: #1e293b; text-decoration: none; }
            .btn-secondary:hover { background: #e67e22; color: white; border-color: #e67e22; }
            .status-badge { display: inline-block; padding: 4px 12px; border-radius: 40px; font-size: 12px; font-weight: 600; }
            .status-assigned { background: #fef3c7; color: #d97706; }
            .status-picked_up { background: #dbeafe; color: #2563eb; }
            .status-in_transit { background: #c7d2fe; color: #4338ca; }
            .status-delivered { background: #d1fae5; color: #059669; }
            .action-buttons { display: flex; gap: 16px; margin-top: 24px; }
            .toast { position: fixed; bottom: 20px; right: 20px; background: #1e293b; color: white; padding: 12px 24px; border-radius: 40px; z-index: 2000; display: none; }
            @media (max-width: 1024px) { .delivery-content { margin-left: 0; padding: 24px; } }
            @media (max-width: 640px) { .info-grid { grid-template-columns: 1fr; gap: 12px; } .action-buttons { flex-direction: column; } .btn { justify-content: center; } }
        </style>
    </head>
    <body>
    <div class="delivery-content">
        <div class="page-header">
            <h1><i class="fas fa-truck-fast"></i> Update Delivery Status</h1>
            <p>Update the progress of your assigned delivery</p>
        </div>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Delivery Information</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Delivery ID</span><span class="info-value"> <?php echo $delivery_id; ?></span></div>
                    <div class="info-item"><span class="info-label">Order ID</span><span class="info-value"> <?php echo $order_id; ?></span></div>
                    <div class="info-item"><span class="info-label">Business</span><span class="info-value"><?php echo htmlspecialchars($delivery['business_name']); ?></span></div>
                    <div class="info-item"><span class="info-label">Delivery Address</span><span class="info-value"><?php echo htmlspecialchars($delivery['delivery_address']); ?></span></div>
                    <div class="info-item"><span class="info-label">Order Amount</span><span class="info-value">TSh <?php echo number_format($delivery['grand_total']); ?></span></div>
                    <div class="info-item"><span class="info-label">Current Status</span><span class="info-value"><span class="status-badge status-<?php echo $current_status; ?>"><?php echo str_replace('_', ' ', ucfirst($current_status)); ?></span></span></div>
                </div>
                <form id="statusForm" method="POST">
                    <div class="form-group">
                        <label>Update Status To:</label>
                        <select name="new_status" id="newStatus" class="form-control" required>
                            <option value="assigned" <?php echo $current_status == 'assigned' ? 'selected' : ''; ?>>Assigned (Waiting for pickup)</option>
                            <option value="picked_up" <?php echo $current_status == 'picked_up' ? 'selected' : ''; ?>>Picked Up</option>
                            <option value="in_transit" <?php echo $current_status == 'in_transit' ? 'selected' : ''; ?>>In Transit</option>
                            <option value="delivered" <?php echo $current_status == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        </select>
                    </div>
                    <?php if ($current_status == 'assigned' && !in_array($order_current_status, ['ready', 'preparing'])): ?>
                        <div style="background: #fee2e2; padding: 12px; border-radius: 12px; margin-bottom: 20px; color: #991b1b; font-size: 13px;">
                            <i class="fas fa-exclamation-triangle"></i> Note: This order is not yet marked as 'Ready' by the business. You cannot pick it up until the business updates the status.
                        </div>
                    <?php endif; ?>
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Status</button>
                        <a href="../my-deliveries/my-deliveries.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="toast" class="toast"></div>

    <script>
        const form = document.getElementById('statusForm');
        const toast = document.getElementById('toast');
        function showToast(message, isError = false) {
            toast.textContent = message;
            toast.style.backgroundColor = isError ? '#dc2626' : '#10b981';
            toast.style.display = 'block';
            setTimeout(() => { toast.style.display = 'none'; }, 3000);
        }
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newStatus = document.getElementById('newStatus').value;
            // If trying to pick up, verify with server
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ new_status: newStatus })
                });
                const result = await response.json();
                if (result.success) {
                    showToast(result.message);
                    setTimeout(() => { window.location.href = '../my-deliveries/my-deliveries.php'; }, 1500);
                } else {
                    showToast(result.message, true);
                }
            } catch (err) {
                showToast('Network error. Please try again.', true);
            }
        });
    </script>
    </body>
    </html>
    <?php
    exit();
}

header("Location: das/dashboard.php");
exit();
?>