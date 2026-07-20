<?php
// delivery/verify-delivery-otp.php - FIXED
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$agent_id = $_SESSION['agent_id'] ?? 0;
$error = '';
$success = '';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header("Location: my-deliveries/my-deliveries.php");
    exit();
}

// ============================================
// FIXED: delivery_agent_id -> agent_id
// ============================================
$check_sql = "SELECT o.*, c.first_name, c.last_name, c.phone, u.email 
              FROM orders o 
              JOIN customers c ON o.customer_id = c.customer_id 
              JOIN users u ON c.user_id = u.user_id 
              WHERE o.order_id = $order_id 
              AND o.agent_id = $agent_id 
              AND o.status = 'delivered_pending_verification'";
$check_result = mysqli_query($conn, $check_sql);

if (mysqli_num_rows($check_result) == 0) {
    $_SESSION['flash_message'] = "Invalid order or not authorized.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: my-deliveries/my-deliveries.php");
    exit();
}

$order = mysqli_fetch_assoc($check_result);
$customer_name = $order['first_name'] . ' ' . $order['last_name'];
$customer_phone = $order['phone'] ?? 'Not provided';

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $otp_code = trim($_POST['otp_code'] ?? '');
    
    if (empty($otp_code)) {
        $error = "Please enter the OTP code.";
    } else {
        $verify_sql = "SELECT * FROM delivery_otp_logs 
                       WHERE order_id = $order_id 
                       AND otp_code = '$otp_code' 
                       AND status = 'pending' 
                       AND expires_at > NOW()
                       ORDER BY generated_at DESC LIMIT 1";
        $verify_result = mysqli_query($conn, $verify_sql);
        
        if (mysqli_num_rows($verify_result) > 0) {
            $otp_record = mysqli_fetch_assoc($verify_result);
            
            $update_otp = "UPDATE delivery_otp_logs 
                           SET status = 'verified', verified_at = NOW() 
                           WHERE log_id = " . $otp_record['log_id'];
            mysqli_query($conn, $update_otp);
            
            $update_order = "UPDATE orders 
                             SET status = 'delivered', 
                                 delivery_otp_verified = 1, 
                                 delivery_completed_at = NOW() 
                             WHERE order_id = $order_id";
            mysqli_query($conn, $update_order);
            
            $update_delivery = "UPDATE deliveries 
                                SET status = 'delivered', 
                                    completed_at = NOW() 
                                WHERE order_id = $order_id AND agent_id = $agent_id";
            mysqli_query($conn, $update_delivery);
            
            $success = "✅ Delivery verified successfully! Order #$order_id has been completed.";
            header("Refresh: 3; url=my-deliveries/my-deliveries.php");
        } else {
            $error = "Invalid OTP. Please try again.";
        }
    }
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Delivery OTP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .container { max-width: 500px; margin: 3rem auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
        .card-header { background: linear-gradient(135deg, #2c3e50, #1a252f); color: white; padding: 30px; text-align: center; border-radius: 20px 20px 0 0; }
        .card-header h2 { font-size: 22px; }
        .card-body { padding: 30px; }
        .otp-input-group { display: flex; gap: 10px; justify-content: center; }
        .otp-input { width: 50px; height: 60px; text-align: center; font-size: 24px; font-weight: 700; border: 2px solid #ddd; border-radius: 10px; }
        .otp-input:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .btn { width: 100%; padding: 14px; background: #27ae60; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e8449; }
        .alert { padding: 12px 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        .order-info { background: #fef9e7; padding: 12px 15px; border-radius: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; }
        @media (max-width: 480px) { .otp-input { width: 42px; height: 52px; font-size: 20px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-truck"></i> Verify Delivery</h2>
            <p style="opacity:0.8;">Enter the OTP from the customer</p>
        </div>
        <div class="card-body">
            <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
            
            <div class="order-info">
                <span><i class="fas fa-receipt"></i> Order #<?php echo $order_id; ?></span>
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($customer_name); ?></span>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label style="display:block;margin-bottom:8px;font-weight:600;"><i class="fas fa-key"></i> Delivery OTP Code</label>
                    <div class="otp-input-group">
                        <input type="text" class="otp-input" id="otp1" maxlength="1" pattern="[0-9]" required autofocus>
                        <input type="text" class="otp-input" id="otp2" maxlength="1" pattern="[0-9]" required>
                        <input type="text" class="otp-input" id="otp3" maxlength="1" pattern="[0-9]" required>
                        <input type="text" class="otp-input" id="otp4" maxlength="1" pattern="[0-9]" required>
                        <input type="text" class="otp-input" id="otp5" maxlength="1" pattern="[0-9]" required>
                        <input type="text" class="otp-input" id="otp6" maxlength="1" pattern="[0-9]" required>
                    </div>
                    <input type="hidden" name="otp_code" id="otpHidden">
                </div>
                <button type="submit" name="verify_otp" class="btn"><i class="fas fa-check-circle"></i> Verify & Complete</button>
            </form>
            
            <div style="text-align:center;margin-top:20px;padding-top:20px;border-top:1px solid #eee;">
                <a href="my-deliveries/my-deliveries.php" style="color:#e67e22;text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to My Deliveries</a>
            </div>
        </div>
    </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-input');
const hidden = document.getElementById('otpHidden');
inputs.forEach((input, index) => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length === 1 && index < 5) inputs[index + 1].focus();
        let otp = '';
        inputs.forEach(inp => otp += inp.value);
        hidden.value = otp;
        if (otp.length === 6) setTimeout(() => document.querySelector('form').submit(), 300);
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value.length === 0 && index > 0) inputs[index - 1].focus();
    });
});
window.addEventListener('load', () => document.getElementById('otp1').focus());
</script>
</body>
</html>