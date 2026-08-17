<?php
// delivery/otp/verify_otp.php - OTP Verification Page
require_once '../../config/database.php';
require_once '../../config/otp_helper.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id <= 0) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get agent info
$agent_sql = "SELECT agent_id, first_name, last_name FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$agent_id = $agent['agent_id'];

// Verify delivery belongs to agent
$sql = "SELECT d.*, o.customer_id, c.first_name, c.last_name, c.phone, 
               b.business_name, u.email
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        JOIN businesses b ON o.business_id = b.business_id
        WHERE d.delivery_id = '$delivery_id' AND d.agent_id = '$agent_id'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header("Location: ../my-deliveries/my-deliveries.php");
    exit();
}

// Check if OTP exists, if not generate one
$otp_check = "SELECT otp_code, otp_status FROM deliveries WHERE delivery_id = '$delivery_id'";
$otp_result = mysqli_query($conn, $otp_check);
$otp_data = mysqli_fetch_assoc($otp_result);

if (!$otp_data['otp_code'] || $otp_data['otp_status'] == 'expired') {
    DeliveryOTP::generateDeliveryOTP($conn, $delivery_id, $delivery['customer_id'], $agent_id);
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_code'])) {
    $otp_code = trim($_POST['otp_code']);
    $result = DeliveryOTP::verifyOTP($conn, $delivery_id, $otp_code);
    
    if ($result['success']) {
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = 'success';
        header("Location: ../track/track-delivery.php?id=" . $delivery_id);
        exit();
    } else {
        $error = $result['message'];
    }
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | Delivery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .delivery-content { margin-left: 280px; padding: 2rem; min-height: 100vh; }
        .page-header { margin-bottom: 2rem; }
        .page-header h1 { font-size: 28px; font-weight: 800; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; }
        .card { background: white; border-radius: 28px; border: 1px solid #eef2f8; max-width: 600px; margin: 0 auto; padding: 2rem; }
        .card-header { text-align: center; margin-bottom: 2rem; }
        .card-header h3 { font-size: 24px; font-weight: 700; }
        .card-header p { color: #64748b; margin-top: 8px; }
        .otp-icon { font-size: 80px; color: #e67e22; display: block; margin-bottom: 1rem; }
        .delivery-info { background: #f8fafc; padding: 1rem; border-radius: 16px; margin-bottom: 2rem; }
        .delivery-info .row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #eef2f8; }
        .delivery-info .row:last-child { border-bottom: none; }
        .delivery-info .label { color: #64748b; font-size: 13px; }
        .delivery-info .value { font-weight: 600; font-size: 14px; }
        .otp-input-group { display: flex; gap: 12px; justify-content: center; margin: 2rem 0; }
        .otp-input-group input { width: 60px; height: 70px; text-align: center; font-size: 28px; font-weight: 700; border: 2px solid #e2e8f0; border-radius: 16px; background: white; transition: all 0.3s; }
        .otp-input-group input:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 4px rgba(230,126,34,0.1); }
        .otp-input-group input.error { border-color: #e74c3c; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 32px; border-radius: 40px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; }
        .btn-primary { background: #e67e22; color: white; width: 100%; justify-content: center; font-size: 16px; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(230,126,34,0.3); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .error-message { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 12px; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .resend-section { text-align: center; margin-top: 1.5rem; font-size: 14px; color: #64748b; }
        .resend-section a { color: #e67e22; text-decoration: none; font-weight: 600; }
        .resend-section a:hover { text-decoration: underline; }
        .timer { font-size: 14px; color: #64748b; margin-top: 8px; }
        .timer span { font-weight: 700; color: #e67e22; }
        @media (max-width: 768px) {
            .delivery-content { margin-left: 0; padding: 1rem; }
            .card { padding: 1.5rem; }
            .otp-input-group input { width: 50px; height: 60px; font-size: 24px; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <h1><i class="fas fa-shield-alt"></i> OTP Verification</h1>
    </div>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-key otp-icon"></i>
            <h3>Verify Delivery</h3>
            <p>Ask the customer for the OTP code to confirm delivery</p>
        </div>
        
        <div class="delivery-info">
            <div class="row">
                <span class="label">Delivery ID</span>
                <span class="value">#<?php echo $delivery_id; ?></span>
            </div>
            <div class="row">
                <span class="label">Customer</span>
                <span class="value"><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></span>
            </div>
            <div class="row">
                <span class="label">Business</span>
                <span class="value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
            </div>
            <div class="row">
                <span class="label">Customer Phone</span>
                <span class="value"><?php echo htmlspecialchars($delivery['phone']); ?></span>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="otp-input-group" id="otpGroup">
                <input type="text" maxlength="1" class="otp-input" data-index="0" autofocus>
                <input type="text" maxlength="1" class="otp-input" data-index="1">
                <input type="text" maxlength="1" class="otp-input" data-index="2">
                <input type="text" maxlength="1" class="otp-input" data-index="3">
                <input type="text" maxlength="1" class="otp-input" data-index="4">
                <input type="text" maxlength="1" class="otp-input" data-index="5">
            </div>
            <input type="hidden" name="otp_code" id="otpCode">
            
            <button type="submit" class="btn btn-primary" id="verifyBtn" disabled>
                <i class="fas fa-check-circle"></i> Verify OTP
            </button>
        </form>
        
        <div class="resend-section">
            <p>Didn't receive the code?</p>
            <a href="resend_otp.php?id=<?php echo $delivery_id; ?>" onclick="return confirm('Resend OTP to customer?')">
                <i class="fas fa-redo"></i> Resend OTP
            </a>
            <div class="timer" id="timer">
                <i class="fas fa-clock"></i> OTP expires in: <span id="countdown">15:00</span>
            </div>
        </div>
    </div>
</div>

<script>
const inputs = document.querySelectorAll('.otp-input');
const otpCode = document.getElementById('otpCode');
const verifyBtn = document.getElementById('verifyBtn');

inputs.forEach((input, index) => {
    input.addEventListener('input', function() {
        if (this.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
        updateOTP();
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value === '' && index > 0) {
            inputs[index - 1].focus();
        }
        if (e.key === 'Enter') {
            verifyBtn.click();
        }
    });
    
    input.addEventListener('keypress', function(e) {
        if (!/^[0-9]$/.test(e.key)) {
            e.preventDefault();
        }
    });
});

function updateOTP() {
    let code = '';
    inputs.forEach(input => {
        code += input.value;
    });
    otpCode.value = code;
    verifyBtn.disabled = code.length < 6;
}

// Countdown Timer (15 minutes)
let totalSeconds = 900;
const timerDisplay = document.getElementById('countdown');
const timerInterval = setInterval(() => {
    const mins = Math.floor(totalSeconds / 60);
    const secs = totalSeconds % 60;
    timerDisplay.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    
    if (totalSeconds <= 0) {
        clearInterval(timerInterval);
        timerDisplay.textContent = 'Expired!';
        timerDisplay.style.color = '#e74c3c';
        inputs.forEach(input => input.disabled = true);
        verifyBtn.disabled = true;
    }
    totalSeconds--;
}, 1000);
</script>
</body>
</html>