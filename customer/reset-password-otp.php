<?php
// customer/reset-password-otp.php 
require_once '../config/database.php';
session_start();

// Redirect if no reset session data
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot-password.php");
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];
$full_name = $_SESSION['reset_full_name'] ?? 'Customer';

// FUNCTION: Mask Email
function maskEmail($email) {
    if (empty($email)) return '';
    
    $parts = explode('@', $email);
    $username = $parts[0];
    $domain = $parts[1] ?? '';
    
    if (strlen($username) <= 3) {
        $firstChars = substr($username, 0, 2);
        $masked = $firstChars . '***@' . $domain;
    } else {
        $firstChars = substr($username, 0, 3);
        $masked = $firstChars . '***@' . $domain;
    }
    
    return $masked;
}

// Function to send OTP email (resend)
function sendOTPEmail($to, $otp, $name) {
    $phpmailer_path = __DIR__ . '/vendor/PHPMailer/src/Exception.php';
    
    if (file_exists($phpmailer_path)) {
        require_once __DIR__ . '/vendor/PHPMailer/src/Exception.php';
        require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
        require_once __DIR__ . '/vendor/PHPMailer/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'albinokh425@gmail.com';
            $mail->Password   = 'hgww grom kage sadr';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->setFrom('no-reply@unksystem.com', 'UNK System');
            $mail->addAddress($to, $name);
            
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - UNK System';
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; background: #f5f7fb; padding: 20px; }
                    .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; text-align: center; }
                    .logo { color: #e67e22; font-size: 24px; font-weight: bold; }
                    .otp-code { font-size: 36px; font-weight: bold; color: #e67e22; letter-spacing: 5px; padding: 15px; background: #f8fafc; border-radius: 12px; margin: 20px 0; }
                    .footer { font-size: 12px; color: #64748b; margin-top: 20px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="logo">UNK System</div>
                    <h2>Password Reset</h2>
                    <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                    <p>Your password reset OTP code is:</p>
                    <div class="otp-code">' . $otp . '</div>
                    <p>This code is valid for <strong>10 minutes</strong>.</p>
                    <div class="footer"><p>&copy; ' . date('Y') . ' UNK System</p></div>
                </div>
            </body>
            </html>
            ';
            $mail->AltBody = "Your password reset OTP is: $otp. Valid for 10 minutes.";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'verify_otp') {
        $otp_code = trim($_POST['otp_code'] ?? '');
        
        if (empty($otp_code)) {
            $error = "Please enter the verification code.";
        } else {
            $email_escaped = mysqli_real_escape_string($conn, $email);
            $otp_escaped = mysqli_real_escape_string($conn, $otp_code);
            
            $sql = "SELECT otp_id, expires_at, is_used FROM otp_verifications 
                    WHERE email = '$email_escaped' 
                    AND otp_code = '$otp_escaped' 
                    AND purpose = 'customer_password_reset' 
                    AND is_used = 0 
                    ORDER BY created_at DESC 
                    LIMIT 1";
            $result = mysqli_query($conn, $sql);
            
            if ($result && mysqli_num_rows($result) > 0) {
                $otp_record = mysqli_fetch_assoc($result);
                
                $current_time = time();
                $expires_time = strtotime($otp_record['expires_at']);
                $time_diff = $expires_time - $current_time;
                
                if ($time_diff > 0) {
                    $otp_id = (int)$otp_record['otp_id'];
                    $update_sql = "UPDATE otp_verifications SET is_used = 1 WHERE otp_id = $otp_id";
                    mysqli_query($conn, $update_sql);
                    
                    $_SESSION['reset_verified'] = true;
                    
                    header("Location: reset-password.php");
                    exit();
                } else {
                    $otp_id = (int)$otp_record['otp_id'];
                    $update_sql = "UPDATE otp_verifications SET is_used = 1 WHERE otp_id = $otp_id";
                    mysqli_query($conn, $update_sql);
                    $error = "This verification code has expired. Please request a new one.";
                }
            } else {
                $error = "Invalid verification code. Please check and try again.";
            }
        }
    }
    
    if ($action === 'resend_otp') {
        $email_escaped = mysqli_real_escape_string($conn, $email);
        
        $check_sql = "SELECT otp_id, expires_at FROM otp_verifications 
                      WHERE email = '$email_escaped' AND purpose = 'customer_password_reset' AND is_used = 0 
                      ORDER BY created_at DESC LIMIT 1";
        $check_result = mysqli_query($conn, $check_sql);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $existing = mysqli_fetch_assoc($check_result);
            $current_time = time();
            $expires_time = strtotime($existing['expires_at']);
            $time_diff = $expires_time - $current_time;
            
            if ($time_diff > 30) {
                $remaining = ceil($time_diff / 60);
                $error = "You already have a valid OTP. Please check your email. ($remaining minutes remaining)";
            } else {
                $new_otp = sprintf("%06d", mt_rand(1, 999999));
                mysqli_query($conn, "DELETE FROM otp_verifications WHERE email = '$email_escaped' AND purpose = 'customer_password_reset' AND is_used = 0");
                
                $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $insert_sql = "INSERT INTO otp_verifications (email, otp_code, purpose, expires_at, created_at) 
                               VALUES ('$email_escaped', '$new_otp', 'customer_password_reset', '$expires_at', NOW())";
                mysqli_query($conn, $insert_sql);
                
                if (sendOTPEmail($email, $new_otp, $full_name)) {
                    $success = "A new OTP has been sent to your email.";
                } else {
                    $error = "Failed to send OTP. Please try again.";
                }
            }
        } else {
            $new_otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $insert_sql = "INSERT INTO otp_verifications (email, otp_code, purpose, expires_at, created_at) 
                           VALUES ('$email_escaped', '$new_otp', 'customer_password_reset', '$expires_at', NOW())";
            mysqli_query($conn, $insert_sql);
            
            if (sendOTPEmail($email, $new_otp, $full_name)) {
                $success = "A new OTP has been sent to your email.";
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
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
    <title>Verify OTP - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .login-container {
            max-width: 450px;
            margin: 3rem auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .card-header h2 i { color: #e67e22; }
        .card-header p { opacity: 0.9; font-size: 13px; }
        
        .card-body { padding: 30px; }
        
        .info-box {
            background: #e3f2fd;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #1565c0;
        }
        .info-box i { font-size: 18px; }
        .info-box strong { color: #0d47a1; }
        .email-display {
            background: #f8f9fa;
            padding: 2px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 13px;
        }
        .form-group label i { color: #e67e22; margin-right: 5px; }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border: 2px solid #ddd;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .otp-input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230,126,34,0.3);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
        }
        .alert i { margin-top: 2px; }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .resend-section {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .resend-section p {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .resend-link {
            color: #e67e22;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 14px;
        }
        .resend-link:hover { text-decoration: underline; }
        .resend-timer { color: #7f8c8d; font-size: 13px; }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        .back-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 13px;
        }
        .back-link a:hover { color: #e67e22; }
        
        @media (max-width: 480px) {
            .card-header { padding: 25px 20px; }
            .card-body { padding: 20px; }
            .otp-input { width: 42px; height: 52px; font-size: 20px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-key"></i> Reset Password</h2>
            <p>Enter the 6-digit OTP sent to your email</p>
        </div>
        <div class="card-body">
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php echo htmlspecialchars($error); ?>
                        <?php if(strpos($error, 'expired') !== false): ?>
                            <br><small>Click <a href="#" onclick="document.getElementById('resendForm').submit();" style="color: #e67e22; font-weight: 600; text-decoration: underline; cursor: pointer;">Resend OTP</a></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- EMAIL DISPLAY - MASKED -->
            <div class="info-box">
                <i class="fas fa-envelope"></i>
                <div>
                    <strong>Email:</strong> 
                    <span class="email-display">
                        <?php echo maskEmail($email); ?>
                    </span>
                </div>
            </div>
            
            <div class="info-box" style="background: #fef9e7; color: #8d6e63;">
                <i class="fas fa-clock"></i>
                <div>
                    <strong>OTP expires in:</strong> 
                    <span id="timer">10:00</span>
                </div>
            </div>
            
            <form method="POST" id="otpForm">
                <input type="hidden" name="action" value="verify_otp">
                
                <div class="form-group">
                    <label><i class="fas fa-key"></i> OTP Code</label>
                    <div class="otp-input-group" id="otpContainer">
                        <input type="text" class="otp-input" id="otp1" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                        <input type="text" class="otp-input" id="otp2" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="otp-input" id="otp3" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="otp-input" id="otp4" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="otp-input" id="otp5" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                        <input type="text" class="otp-input" id="otp6" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    </div>
                    <input type="hidden" name="otp_code" id="otpHidden">
                </div>
                
                <button type="submit" class="btn" id="verifyBtn">
                    <i class="fas fa-check-circle"></i> Verify OTP
                </button>
            </form>
            
            <div class="resend-section">
                <p id="resendMessage">Didn't receive the OTP?</p>
                <form method="POST" id="resendForm" style="display: inline;">
                    <input type="hidden" name="action" value="resend_otp">
                    <button type="submit" class="resend-link" id="resendBtn">
                        <i class="fas fa-redo"></i> Resend OTP
                    </button>
                    <span class="resend-timer" id="resendTimer" style="display: none;">(Wait <span id="countdown">30</span>s)</span>
                </form>
            </div>
            
            <div class="back-link">
                <a href="forgot-password.php"><i class="fas fa-arrow-left"></i> Back to Forgot Password</a>
            </div>
        </div>
    </div>
</div>

<script>
// OTP Input Auto-focus
const otpInputs = document.querySelectorAll('.otp-input');
const otpHidden = document.getElementById('otpHidden');

otpInputs.forEach((input, index) => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '');
        if (this.value.length === 1 && index < otpInputs.length - 1) {
            otpInputs[index + 1].focus();
        }
        updateHiddenOTP();
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && this.value.length === 0 && index > 0) {
            otpInputs[index - 1].focus();
        }
    });
    
    input.addEventListener('paste', function(e) {
        e.preventDefault();
        const paste = (e.clipboardData || window.clipboardData).getData('text');
        const digits = paste.replace(/\D/g, '').slice(0, 6);
        digits.split('').forEach((digit, i) => {
            if (otpInputs[i]) otpInputs[i].value = digit;
        });
        updateHiddenOTP();
        if (digits.length === 6) {
            setTimeout(() => document.getElementById('otpForm').submit(), 300);
        }
    });
});

function updateHiddenOTP() {
    let otp = '';
    otpInputs.forEach(input => { otp += input.value; });
    otpHidden.value = otp;
    
    let filled = true;
    otpInputs.forEach(input => { if (input.value.length === 0) filled = false; });
    if (filled) {
        setTimeout(() => document.getElementById('otpForm').submit(), 300);
    }
}

// Timer
let timeLeft = 600;
const timerElement = document.getElementById('timer');

function updateTimer() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    timerElement.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    if (timeLeft <= 0) {
        timerElement.textContent = 'Expired!';
        timerElement.style.color = '#e74c3c';
        document.getElementById('verifyBtn').disabled = true;
        document.getElementById('verifyBtn').style.opacity = '0.6';
    }
    timeLeft--;
}

const errorMsg = document.querySelector('.alert-danger');
if (!errorMsg || !errorMsg.textContent.includes('expired')) {
    setInterval(updateTimer, 1000);
}

// Resend cooldown
let resendCooldown = 30;
let cooldownActive = false;
const resendBtn = document.getElementById('resendBtn');
const resendTimer = document.getElementById('resendTimer');
const countdownEl = document.getElementById('countdown');

document.getElementById('resendForm').addEventListener('submit', function(e) {
    if (cooldownActive) {
        e.preventDefault();
        return;
    }
    cooldownActive = true;
    resendBtn.style.display = 'none';
    resendTimer.style.display = 'inline';
    resendCooldown = 30;
    countdownEl.textContent = resendCooldown;
    
    const interval = setInterval(() => {
        resendCooldown--;
        countdownEl.textContent = resendCooldown;
        if (resendCooldown <= 0) {
            clearInterval(interval);
            resendTimer.style.display = 'none';
            resendBtn.style.display = 'inline';
            cooldownActive = false;
        }
    }, 1000);
});

window.addEventListener('load', function() {
    document.getElementById('otp1').focus();
});
</script>

</body>
</html>
<?php include '../includes/footer2.php'; ?>