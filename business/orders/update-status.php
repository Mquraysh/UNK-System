<?php 
// business/orders/update-status.php 
require_once '../../config/database.php';
session_start();

// Authentication & authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business') {
    $_SESSION['flash_message'] = 'Please login as business owner';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch business details
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    $_SESSION['flash_message'] = 'Business profile not found. Please complete registration.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: ../register.php');
    exit();
}

$business_id = (int)$business['business_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order with customer details and delivery agent info
$order_sql = "SELECT o.*, c.first_name, c.last_name, c.customer_id, c.user_id AS customer_user_id,
                     da.first_name AS agent_first, da.last_name AS agent_last, da.phone AS agent_phone
              FROM orders o
              JOIN customers c ON o.customer_id = c.customer_id
              LEFT JOIN delivery_agents da ON o.agent_id = da.agent_id
              WHERE o.order_id = ? AND o.business_id = ?";
$stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $business_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['flash_message'] = 'Order not found or access denied.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit();
}

// Ensure missing columns exist
$check_old_payment = mysqli_query($conn, "SHOW COLUMNS FROM order_status_history LIKE 'old_payment'");
if (mysqli_num_rows($check_old_payment) == 0) {
    mysqli_query($conn, "ALTER TABLE order_status_history ADD COLUMN old_payment VARCHAR(20) DEFAULT NULL");
}
$check_new_payment = mysqli_query($conn, "SHOW COLUMNS FROM order_status_history LIKE 'new_payment'");
if (mysqli_num_rows($check_new_payment) == 0) {
    mysqli_query($conn, "ALTER TABLE order_status_history ADD COLUMN new_payment VARCHAR(20) DEFAULT NULL");
}

// Restore product stock when order is cancelled and create inventory notifications
function restoreStock($conn, $order_id, $business_id) {
    $items_sql = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt);
    
    $success = true;
    $restored_items = [];
    while ($item = mysqli_fetch_assoc($items)) {
        // Get product name and current stock before update
        $prod_sql = "SELECT name, quantity_in_stock, business_id FROM products WHERE product_id = ?";
        $prod_stmt = mysqli_prepare($conn, $prod_sql);
        mysqli_stmt_bind_param($prod_stmt, 'i', $item['product_id']);
        mysqli_stmt_execute($prod_stmt);
        $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($prod_stmt));
        mysqli_stmt_close($prod_stmt);
        
        $old_stock = $prod['quantity_in_stock'];
        $new_stock = $old_stock + $item['quantity'];
        
        $update = "UPDATE products SET quantity_in_stock = ? WHERE product_id = ?";
        $upd_stmt = mysqli_prepare($conn, $update);
        mysqli_stmt_bind_param($upd_stmt, 'ii', $new_stock, $item['product_id']);
        if (!mysqli_stmt_execute($upd_stmt)) {
            $success = false;
        }
        mysqli_stmt_close($upd_stmt);
        
        if ($success) {
            $restored_items[] = [
                'name' => $prod['name'],
                'quantity' => $item['quantity'],
                'business_id' => $prod['business_id']
            ];
        }
    }
    mysqli_stmt_close($stmt);
    
    // Insert inventory notifications for each restored product
    foreach ($restored_items as $restore) {
        $title = "Stock Restored";
        $message = "Product '{$restore['name']}' stock increased by {$restore['quantity']} units due to order cancellation.";
        $type = "inventory";
        $notif_sql = "INSERT INTO business_notifications (business_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
        $notif_stmt = mysqli_prepare($conn, $notif_sql);
        mysqli_stmt_bind_param($notif_stmt, 'isss', $restore['business_id'], $title, $message, $type);
        mysqli_stmt_execute($notif_stmt);
        mysqli_stmt_close($notif_stmt);
    }
    
    return $success;
}

// Create a notification for customer (user notifications)
function createCustomerNotification($conn, $customer_user_id, $order_id, $old_status, $new_status, $old_payment = null, $new_payment = null) {
    
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE 'order_id'");
    if (mysqli_num_rows($check_col) == 0) {
        mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN order_id INT NULL AFTER user_id");
    }
    
    $title = "Order {$order_id} Updated";
    $message = "";
    if ($new_status && $old_status != $new_status) {
        $message .= "Order status changed from " . ucfirst(str_replace('_', ' ', $old_status)) . 
                    " to " . ucfirst(str_replace('_', ' ', $new_status)) . ". ";
    }
    if ($new_payment && $old_payment != $new_payment) {
        $message .= "Payment status changed from " . ucfirst($old_payment) . " to " . ucfirst($new_payment) . ".";
    }
    if (empty($message)) return;
    
    $insert = "INSERT INTO notifications (user_id, order_id, title, message, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, 'iiss', $customer_user_id, $order_id, $title, $message);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Create a notification for the business owner (business_notifications)
 function createBusinessNotification($conn, $business_id, $order_id, $change_type, $old_value, $new_value, $notes = '') {
    if ($change_type == 'order') {
        $title = "Order Status Updated";
        $message = "Order {$order_id} status changed from " . ucfirst(str_replace('_', ' ', $old_value)) . 
                   " to " . ucfirst(str_replace('_', ' ', $new_value)) . ".";
        $type = 'order';
        if (!empty($notes)) {
            $message .= " Note: {$notes}";
        }
    } else {
        $title = "Payment Status Updated";
        $message = "Order {$order_id} payment status changed from " . ucfirst($old_value) . " to " . ucfirst($new_value) . ".";
        $type = 'payment';
        if (!empty($notes)) {
            $message .= " Note: {$notes}";
        }
    }
    
    $insert = "INSERT INTO business_notifications (business_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $insert);
    mysqli_stmt_bind_param($stmt, 'isss', $business_id, $title, $message, $type);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ==================== STATUS CONFIGURATION ====================
$all_statuses = ['pending', 'accepted', 'confirmed', 'preparing', 'ready', 'picked_up', 'delivered', 'cancelled'];

$allowed_transitions = [
    'pending'    => ['accepted', 'cancelled'],
    'accepted'   => ['confirmed', 'cancelled'],
    'confirmed'  => ['preparing', 'cancelled'],
    'preparing'  => ['ready', 'cancelled'],
    'ready'      => ['picked_up', 'cancelled'],
    'picked_up'  => ['delivered', 'cancelled'],
    'delivered'  => [],
    'cancelled'  => []
];

$status_labels = [
    'pending'    => 'Pending',
    'accepted'   => 'Accepted',
    'confirmed'  => 'Confirmed',
    'preparing'  => 'Preparing',
    'ready'      => 'Ready',
    'picked_up'  => 'Picked Up',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled'
];

$badge_classes = [
    'pending'    => 'badge-pending',
    'accepted'   => 'badge-accepted',
    'confirmed'  => 'badge-confirmed',
    'preparing'  => 'badge-preparing',
    'ready'      => 'badge-ready',
    'picked_up'  => 'badge-picked_up',
    'delivered'  => 'badge-delivered',
    'cancelled'  => 'badge-cancelled'
];

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'] ?? '';
    $new_payment = $_POST['payment_status'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $old_status = $order['status'];
    $old_payment = $order['payment_status'];
    
    $error = false;
    $update_status = false;
    $update_payment = false;
    
    // Prevent cancellation if a delivery agent is already assigned
    if ($new_status === 'cancelled' && !empty($order['agent_id']) && $old_status !== 'cancelled') {
        $error = true;
        $message = 'Cannot cancel this order because a delivery agent has already been assigned. Please contact support if necessary.';
        $message_type = 'danger';
    }
    
    // Validate order status if changed
    if (!$error && $new_status !== $old_status) {
        if (!in_array($new_status, $all_statuses)) {
            $message = 'Invalid order status selected.';
            $message_type = 'danger';
            $error = true;
        } elseif (!in_array($new_status, $allowed_transitions[$old_status])) {
            $message = "Status transition from '" . ucfirst($old_status) . "' to '" . ucfirst($new_status) . "' is not allowed.";
            $message_type = 'danger';
            $error = true;
        } else {
            $update_status = true;
        }
    }
    
    // Validate payment status
    if (!$error && $new_payment !== $old_payment) {
        if (!in_array($new_payment, ['pending', 'paid'])) {
            $message = 'Invalid payment status selected.';
            $message_type = 'danger';
            $error = true;
        } else {
            $update_payment = true;
        }
    }
    
    if (!$error && ($update_status || $update_payment)) {
        mysqli_begin_transaction($conn);
        $all_ok = true;
        
        // If cancelling order and status is changing to cancelled, restore stock and create inventory notifications
        if ($update_status && $new_status === 'cancelled' && $old_status !== 'cancelled') {
            if (!restoreStock($conn, $order_id, $business_id)) {
                $all_ok = false;
                $message = 'Failed to restore product stock. Please try again.';
                $message_type = 'danger';
            }
        }
        
        if ($all_ok) {
            // Update order status
            if ($update_status) {
                $update_sql = "UPDATE orders SET status = ? WHERE order_id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, 'si', $new_status, $order_id);
                if (!mysqli_stmt_execute($stmt)) $all_ok = false;
                mysqli_stmt_close($stmt);
            }
            
            // Update payment status
            if ($update_payment) {
                $update_pay_sql = "UPDATE orders SET payment_status = ? WHERE order_id = ?";
                $stmt = mysqli_prepare($conn, $update_pay_sql);
                mysqli_stmt_bind_param($stmt, 'si', $new_payment, $order_id);
                if (!mysqli_stmt_execute($stmt)) $all_ok = false;
                mysqli_stmt_close($stmt);
            }
            
            if ($all_ok) {
                $final_new_status = $update_status ? $new_status : $old_status;
                $final_new_payment = $update_payment ? $new_payment : $old_payment;
                
                // Record history
                $history_sql = "INSERT INTO order_status_history 
                                (order_id, old_status, new_status, old_payment, new_payment, notes, created_by) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_hist = mysqli_prepare($conn, $history_sql);
                mysqli_stmt_bind_param($stmt_hist, 'isssssi', 
                    $order_id, 
                    $old_status, 
                    $final_new_status,
                    $old_payment,
                    $final_new_payment,
                    $notes,
                    $user_id
                );
                mysqli_stmt_execute($stmt_hist);
                mysqli_stmt_close($stmt_hist);
                
                // Notify customer
                createCustomerNotification($conn, $order['customer_user_id'], $order_id, 
                    $old_status, $update_status ? $new_status : $old_status,
                    $old_payment, $update_payment ? $new_payment : $old_payment);
                
                // Notify business (business_notifications) for order/payment
                if ($update_status) {
                    createBusinessNotification($conn, $business_id, $order_id, 'order', $old_status, $new_status, $notes);
                }
                if ($update_payment) {
                    createBusinessNotification($conn, $business_id, $order_id, 'payment', $old_payment, $new_payment, $notes);
                }
                
                mysqli_commit($conn);
                $_SESSION['flash_message'] = "Order {$order_id} updated successfully.";
                $_SESSION['flash_type'] = 'success';
                header("Location: details.php?id={$order_id}");
                exit();
            } else {
                mysqli_rollback($conn);
                if (empty($message)) {
                    $message = 'Database error while updating.';
                    $message_type = 'danger';
                }
            }
        } else {
            mysqli_rollback($conn);
        }
    } elseif (!$error && !$update_status && !$update_payment) {
        $message = 'No changes were made.';
        $message_type = 'warning';
    }
}

// Fetch status history
$history_query = "SELECT h.*, u.full_name 
                  FROM order_status_history h
                  LEFT JOIN users u ON h.created_by = u.user_id
                  WHERE h.order_id = ?
                  ORDER BY h.created_at DESC";
$stmt = mysqli_prepare($conn, $history_query);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$history_result = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Update Order Status | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.5rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; background: none; }
        .btn-back {
            background: #475569;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-back:hover { background: #334155; transform: translateY(-2px); }
        .card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03), 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
            margin-bottom: 2rem;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .card:hover { box-shadow: 0 12px 24px -12px rgba(0,0,0,0.1); }
        .card-header {
            padding: 1.25rem 1.75rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #1e293b;
        }
        .card-header i { color: #e67e22; font-size: 1.2rem; }
        .card-body { padding: 1.75rem; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .info-item { display: flex; flex-direction: column; }
        .info-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        .info-value { font-size: 1rem; font-weight: 600; color: #0f172a; }
        .delivery-agent {
            background: #ecfdf5;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #10b981;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        .delivery-agent strong { color: #047857; }
        .delivery-agent span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 1rem;
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            font-weight: 700;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            font-size: 0.9rem;
            transition: 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .btn-save {
            background: linear-gradient(105deg, #e67e22, #d35400);
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 2px 6px rgba(230,126,34,0.2);
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230,126,34,0.25);
        }
        .text-muted { font-size: 0.75rem; color: #64748b; margin-top: 0.35rem; }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-accepted { background: #ddd6fe; color: #5b21b6; }
        .badge-confirmed { background: #a7f3d0; color: #065f46; }
        .badge-preparing { background: #bfdbfe; color: #1e40af; }
        .badge-ready { background: #fed7aa; color: #9a3412; }
        .badge-picked_up { background: #fbcfe8; color: #9d174d; }
        .badge-delivered { background: #d1fae5; color: #047857; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }
        .alert-success { background: #e6f7ec; color: #0a5c3e; border-left-color: #10b981; }
        .alert-danger { background: #fee9e6; color: #b91c1c; border-left-color: #ef4444; }
        .alert-warning { background: #fffbeb; color: #b45309; border-left-color: #f59e0b; }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th {
            text-align: left;
            padding: 0.85rem 1rem;
            background: #f8fafc;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.85rem;
            vertical-align: top;
        }
        .history-table tr:hover td { background: #fffaf5; }
        .empty-message { text-align: center; padding: 2rem; color: #94a3b8; }
        @media (max-width: 640px) {
            .info-grid { grid-template-columns: 1fr; gap: 0.75rem; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .delivery-agent { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-exchange-alt"></i> Update Order Status</h1>
        <a href="details.php?id=<?php echo $order_id; ?>" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Order
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-pen-alt"></i> Change Order & Payment Status
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Order ID</span>
                    <span class="info-value"><?php echo $order['order_id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Customer</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value">TSh <?php echo number_format($order['grand_total'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Order Status</span>
                    <span class="badge <?php echo $badge_classes[$order['status']] ?? 'badge-pending'; ?>">
                        <i class="fas fa-<?php echo $order['status'] === 'delivered' ? 'check-circle' : ($order['status'] === 'cancelled' ? 'times-circle' : 'sync'); ?>"></i>
                        <?php echo $status_labels[$order['status']] ?? ucfirst($order['status']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Payment Status</span>
                    <span class="badge" style="background: <?php echo $order['payment_status'] === 'paid' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $order['payment_status'] === 'paid' ? '#047857' : '#b45309'; ?>">
                        <i class="fas fa-<?php echo $order['payment_status'] === 'paid' ? 'check-circle' : 'clock'; ?>"></i>
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($order['agent_id'])): ?>
            <div class="delivery-agent">
                <span><i class="fas fa-truck"></i> <strong>Delivery Agent:</strong> <?php echo htmlspecialchars($order['agent_first'] . ' ' . $order['agent_last']); ?></span>
                <span><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($order['agent_phone']); ?></span>
                <span><i class="fas fa-info-circle"></i> Order already assigned to agent.</span>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="updateForm">
                <div class="form-group">
                    <label for="status"><i class="fas fa-tag"></i> New Order Status</label>
                    <select name="status" id="status" class="form-control">
                        <?php foreach ($all_statuses as $status): ?>
                            <?php $disabled = (!in_array($status, $allowed_transitions[$order['status']]) && $status !== $order['status']); ?>
                            <option value="<?php echo $status; ?>" 
                                <?php echo $order['status'] === $status ? 'selected' : ''; ?>
                                <?php echo $disabled ? 'disabled' : ''; ?>>
                                <?php echo $status_labels[$status]; ?>
                                <?php if ($status === $order['status']) echo ' (current)'; ?>
                                <?php if ($disabled) echo ' (not allowed)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($allowed_transitions[$order['status']])): ?>
                        <div class="text-muted">
                            <i class="fas fa-arrow-right"></i> Allowed next: 
                            <?php 
                                $next = array_map(function($s) use ($status_labels) { 
                                    return $status_labels[$s] ?? $s; 
                                }, $allowed_transitions[$order['status']]);
                                echo implode(' → ', $next); 
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="text-muted"><i class="fas fa-ban"></i> This is a final status. No further changes allowed.</div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="payment_status"><i class="fas fa-credit-card"></i> Payment Status</label>
                    <select name="payment_status" id="payment_status" class="form-control">
                        <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending (Not yet paid)</option>
                        <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid (Payment received)</option>
                    </select>
                    <div class="text-muted"><i class="fas fa-info-circle"></i> Mark as "Paid" when cash is collected or mobile/bank transfer is confirmed.</div>
                </div>
                
                <div class="form-group">
                    <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" 
                              placeholder="E.g., Reason for cancellation, payment confirmation, delivery remarks, etc."></textarea>
                </div>
                
                <button type="submit" class="btn-save" id="submitBtn">
                    <i class="fas fa-save"></i> Update Changes
                </button>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-history"></i> Order Status History (including payment changes)
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($history_result) > 0): ?>
                <table class="history-table">
                    <thead>
                        <tr><th>Date & Time</th><th>Order Status Change</th><th>Payment Status Change</th><th>Notes</th><th>Changed By</th></tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($history_result)): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <?php if ($row['old_status'] != $row['new_status']): ?>
                                    <span class="badge <?php echo $badge_classes[$row['old_status']] ?? 'badge-pending'; ?>"><?php echo $status_labels[$row['old_status']] ?? ucfirst($row['old_status']); ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="badge <?php echo $badge_classes[$row['new_status']] ?? 'badge-pending'; ?>"><?php echo $status_labels[$row['new_status']] ?? ucfirst($row['new_status']); ?></span>
                                <?php else: ?>
                                    <em>No change</em>
                                <?php endif; ?>
                             </a>
                            <td>
                                <?php if ($row['old_payment'] != $row['new_payment']): ?>
                                    <span class="badge" style="background: <?php echo $row['old_payment'] === 'paid' ? '#d1fae5' : '#fef3c7'; ?>;"><?php echo ucfirst($row['old_payment']); ?></span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="badge" style="background: <?php echo $row['new_payment'] === 'paid' ? '#d1fae5' : '#fef3c7'; ?>;"><?php echo ucfirst($row['new_payment']); ?></span>
                                <?php else: ?>
                                    <em>No change</em>
                                <?php endif; ?>
                             </a>
                            <td><?php echo nl2br(htmlspecialchars($row['notes'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name'] ?? 'Business Owner'); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-message">
                    <i class="fas fa-info-circle"></i> No status changes have been recorded for this order yet.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Confirmation before cancelling order
    document.getElementById('updateForm').addEventListener('submit', function(e) {
        const statusSelect = document.getElementById('status');
        if (statusSelect.value === 'cancelled') {
            if (!confirm('⚠️ WARNING: Cancelling this order will restore all product quantities to stock. This action cannot be undone. Are you sure you want to cancel this order?')) {
                e.preventDefault();
            }
        }
    });
</script>
</body>
</html>