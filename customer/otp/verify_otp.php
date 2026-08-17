<?php
// customer/otp/verify_otp.php 
require_once '../../config/database.php';
require_once '../../config/otp_helper.php';
session_start();

// Check if customer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($delivery_id <= 0) {
    header("Location: ../orders/my-orders.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_id = $_SESSION['customer_id'] ?? 0;

// Verify this delivery belongs to the customer
$sql = "SELECT d.*, o.order_id, o.grand_total, o.delivery_address,
               b.business_name, b.phone as business_phone,
               a.first_name as agent_first, a.last_name as agent_last,
               a.phone as agent_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN businesses b ON o.business_id = b.business_id
        JOIN delivery_agents a ON d.agent_id = a.agent_id
        WHERE d.delivery_id = '$delivery_id' 
        AND o.customer_id = '$customer_id'
        AND d.status != 'delivered'
        AND d.status != 'failed'";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

// If delivery not found or already delivered
if (!$delivery) {
    $_SESSION['flash_message'] = 'Delivery not found or already completed.';
    $_SESSION['flash_type'] = 'warning';
    header("Location: ../orders/my-orders.php");
    exit();
}

// Check if OTP exists, if not generate one
$otp_check = "SELECT otp_code, otp_status, otp_generated_at FROM deliveries WHERE delivery_id = '$delivery_id'";
$otp_result = mysqli_query($conn, $otp_check);
$otp_data = mysqli_fetch_assoc($otp_result);

if (!$otp_data['otp_code'] || $otp_data['otp_status'] == 'expired') {
    // Generate OTP for this delivery
    $agent_id = $delivery['agent_id'];
    DeliveryOTP::generateDeliveryOTP($conn, $delivery_id, $customer_id, $agent_id);
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['otp_code'])) {
    $otp_code = trim($_POST['otp_code']);
    
    // Verify OTP
    $result = DeliveryOTP::verifyOTP($conn, $delivery_id, $otp_code);
    
    if ($result['success']) {
        $_SESSION['flash_message'] = '✅ Delivery confirmed! Thank you for using our service.';
        $_SESSION['flash_type'] = 'success';
        header("Location: ../orders/order-details.php?order_id=" . $delivery['order_id']);
        exit();
    } else {
        $error = $result['message'];
    }
}

include '../../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Delivery | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; }
        
        .customer-content {
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 28px;
            border: 1px solid #eef2f8;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .card-header .icon {
            font-size: 72px;
            color: #e67e22;
            display: block;
            margin-bottom: 1rem;
        }
        
        .card-header h2 {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }
        
        .card-header p {
            color: #64748b;
            margin-top: 8px;
        }
        
        .delivery-info {
            background: #f8fafc;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .delivery-info .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eef2f8;
        }
        
        .delivery-info .row:last-child {
            border-bottom: none;
        }
        
        .delivery-info .label {
            color: #64748b;
            font-size: 13px;
        }
        
        .delivery-info .value {
            font-weight: 600;
            font-size: 14px;
            color: #0f172a;
        }
        
        .delivery-info .value .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-nearby {
            background: #fef3c7;
            color: #d97706;
        }
        
        .badge-in_transit {
            background: #dbeafe;
            color: #2563eb;
        }
        
        /* OTP Input */
        .otp-container {
            text-align: center;
            margin: 2rem 0;
        }
        
        .otp-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 12px;
            display: block;
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .otp-input-group input {
            width: 52px;
            height: 64px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            background: white;
            transition: all 0.3s;
        }
        
        .otp-input-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 4px rgba(230,126,34,0.1);
            transform: scale(1.02);
        }
        
        .otp-input-group input.error {
            border-color: #e74c3c;
            box-shadow: 0 0 0 4px rgba(231,76,60,0.1);
        }
        
        .otp-input-group input.success {
            border-color: #27ae60;
            box-shadow: 0 0 0 4px rgba(39,174,96,0.1);
        }
        
        .otp-input-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .success-message {
            background: #d1fae5;
            color: #059669;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(230,126,34,0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        
        .resend-section {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eef2f8;
        }
        
        .resend-section p {
            color: #64748b;
            font-size: 14px;
        }
        
        .resend-section a {
            color: #e67e22;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .resend-section a:hover {
            text-decoration: underline;
        }
        
        .timer {
            margin-top: 8px;
            font-size: 14px;
            color: #64748b;
        }
        
        .timer span {
            font-weight: 700;
            color: #e67e22;
        }
        
        .timer .expired {
            color: #e74c3c;
        }
        
        .otp-tip {
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 12px 16px;
            margin-top: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #166534;
        }
        
        .otp-tip i {
            color: #22c55e;
            font-size: 18px;
            margin-top: 2px;
        }
        
        /* Spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .customer-content { padding: 0 12px; margin: 20px auto; }
            .card { padding: 1.5rem; }
            .otp-input-group { gap: 6px; }
            .otp-input-group input { width: 44px; height: 56px; font-size: 24px; }
            .delivery-info .row { flex-direction: column; gap: 4px; }
        }
        
        @media (max-width: 400px) {
            .otp-input-group input { width: 38px; height: 48px; font-size: 20px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="card">
        <!-- Header -->
        <div class="card-header">
            <span class="icon">🔐</span>
            <h2>Confirm Your Delivery</h2>
            <p>Enter the OTP sent to your email to confirm delivery</p>
        </div>
        
        <!-- Delivery Information -->
        <div class="delivery-info">
            <div class="row">
                <span class="label">Delivery ID</span>
                <span class="value">#<?php echo $delivery_id; ?></span>
            </div>
            <div class="row">
                <span class="label">Order ID</span>
                <span class="value">#<?php echo $delivery['order_id']; ?></span>
            </div>
            <div class="row">
                <span class="label">Business</span>
                <span class="value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
            </div>
            <div class="row">
                <span class="label">Delivery Agent</span>
                <span class="value"><?php echo htmlspecialchars($delivery['agent_first'] . ' ' . $delivery['agent_last']); ?></span>
            </div>
            <div class="row">
                <span class="label">Agent Phone</span>
                <span class="value"><?php echo htmlspecialchars($delivery['agent_phone']); ?></span>
            </div>
            <div class="row">
                <span class="label">Status</span>
                <span class="value">
                    <span class="badge badge-<?php echo $delivery['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                    </span>
                </span>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- OTP Input Form -->
        <form method="POST" id="otpForm">
            <div class="otp-container">
                <span class="otp-label">Enter 6-digit OTP</span>
                <div class="otp-input-group" id="otpGroup">
                    <input type="text" maxlength="1" class="otp-input" data-index="0" autofocus required>
                    <input type="text" maxlength="1" class="otp-input" data-index="1" required>
                    <input type="text" maxlength="1" class="otp-input" data-index="2" required>
                    <input type="text" maxlength="1" class="otp-input" data-index="3" required>
                    <input type="text" maxlength="1" class="otp-input" data-index="4" required>
                    <input type="text" maxlength="1" class="otp-input" data-index="5" required>
                </div>
                <input type="hidden" name="otp_code" id="otpCode">
            </div>
            
            <button type="submit" class="btn btn-primary" id="verifyBtn" disabled>
                <i class="fas fa-check-circle"></i> Confirm Delivery
            </button>
        </form>
        
        <!-- OTP Tip -->
        <div class="otp-tip">
            <i class="fas fa-lightbulb"></i>
            <div>
                <strong>Tip:</strong> Check your email inbox (and spam folder) for the OTP code.
                The code expires in <strong>15 minutes</strong>.
            </div>
        </div>
        
        <!-- Resend Section -->
        <div class="resend-section">
            <p>Didn't receive the code?</p>
            <a href="resend_otp.php?id=<?php echo $delivery_id; ?>" onclick="return confirmResend()">
                <i class="fas fa-redo"></i> Resend OTP
            </a>
            <div class="timer">
                <i class="fas fa-clock"></i> OTP expires in: <span id="countdown">15:00</span>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// OTP Input Auto-Focus & Validation
// ============================================
const inputs = document.querySelectorAll('.otp-input');
const otpCode = document.getElementById('otpCode');
const verifyBtn = document.getElementById('verifyBtn');
const otpForm = document.getElementById('otpForm');

// Auto-focus next input
inputs.forEach((input, index) => {
    input.addEventListener('input', function() {
        // Only allow numbers
        this.value = this.value.replace(/[^0-9]/g, '');
        
        if (this.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
        updateOTP();
    });
    
    // Handle backspace
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value === '' && index > 0) {
            inputs[index - 1].focus();
            inputs[index - 1].select();
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!verifyBtn.disabled) {
                verifyBtn.click();
            }
        }
        // Allow arrow keys
        if (e.key === 'ArrowLeft' && index > 0) {
            inputs[index - 1].focus();
        }
        if (e.key === 'ArrowRight' && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
    });
    
    // Select all text on focus for easy re-entry
    input.addEventListener('focus', function() {
        this.select();
    });
    
    // Handle paste
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        if (paste && /^\d{6}$/.test(paste)) {
            const digits = paste.split('');
            inputs.forEach((inp, i) => {
                if (i < digits.length) {
                    inp.value = digits[i];
                }
            });
            updateOTP();
            // Focus last input or verify button
            if (inputs.length > 0) {
                inputs[inputs.length - 1].focus();
            }
            // Auto-submit if all digits filled
            setTimeout(() => {
                if (!verifyBtn.disabled) {
                    verifyBtn.click();
                }
            }, 300);
        }
    });
});

// Update hidden field and enable/disable button
function updateOTP() {
    let code = '';
    let allFilled = true;
    
    inputs.forEach(input => {
        code += input.value;
        if (input.value === '') {
            allFilled = false;
        }
    });
    
    otpCode.value = code;
    verifyBtn.disabled = !allFilled || code.length < 6;
    
    // Clear error states
    inputs.forEach(input => {
        input.classList.remove('error', 'success');
    });
}

// Confirm resend
function confirmResend() {
    return confirm('Resend OTP to your email?');
}

// ============================================
// Countdown Timer (15 minutes)
// ============================================
let totalSeconds = 900;
const timerDisplay = document.getElementById('countdown');
let timerInterval;

function startCountdown() {
    timerInterval = setInterval(() => {
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        timerDisplay.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        
        if (totalSeconds <= 0) {
            clearInterval(timerInterval);
            timerDisplay.textContent = 'Expired!';
            timerDisplay.className = 'expired';
            inputs.forEach(input => {
                input.disabled = true;
                input.classList.add('error');
            });
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-clock"></i> OTP Expired';
            
            // Show message
            const tipDiv = document.querySelector('.otp-tip');
            if (tipDiv) {
                tipDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    <div>
                        <strong>OTP Expired!</strong> Please click "Resend OTP" to get a new code.
                    </div>
                `;
                tipDiv.style.borderColor = '#fecaca';
                tipDiv.style.background = '#fef2f2';
                tipDiv.style.color = '#991b1b';
            }
        }
        totalSeconds--;
    }, 1000);
}

// Start countdown
startCountdown();

// ============================================
// Form Submit with Loading State
// ============================================
otpForm.addEventListener('submit', function(e) {
    const code = otpCode.value;
    if (code.length < 6) {
        e.preventDefault();
        showToast('Please enter all 6 digits of the OTP', 'error');
        return;
    }
    
    // Show loading state
    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<span class="spinner"></span> Verifying...';
});

// ============================================
// Toast Notification
// ============================================
function showToast(message, type = 'info') {
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'error' ? '#dc2626' : '#e67e22'};
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 14px;
        z-index: 9999;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        animation: slideUp 0.3s ease;
        max-width: 90%;
        text-align: center;
    `;
    
    toast.innerHTML = `<i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i> ${message}`;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// ============================================
// CSS Animation for slideUp
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideUp {
        from { transform: translateX(-50%) translateY(20px); opacity: 0; }
        to { transform: translateX(-50%) translateY(0); opacity: 1; }
    }
`;
document.head.appendChild(style);

// ============================================
// Keyboard shortcut - Press ESC to clear all inputs
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        inputs.forEach(input => {
            input.value = '';
        });
        updateOTP();
        inputs[0].focus();
    }
});

console.log('✅ OTP Verification page loaded successfully');
console.log('ℹ️ Delivery ID: <?php echo $delivery_id; ?>');
console.log('ℹ️ OTP Status: <?php echo $otp_data['otp_status'] ?? 'pending'; ?>');
</script>

</body>
</html>
<?php include '../../includes/footer.php'; ?>