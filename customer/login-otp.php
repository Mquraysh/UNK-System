<?php
// customer/login-otp.php 
require_once '../config/database.php';
session_start();

$error = '';
$success = '';
$email = '';
$step = 'request'; // request or verify

// Check if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer') {
    header("Location: das/dashboard.php");
    exit();
}

// Function to send OTP email
function sendOTPEmail($to, $otp, $name) {
    // Load PHPMailer
    require_once '../vendor/PHPMailer/src/Exception.php';
    require_once '../vendor/PHPMailer/src/PHPMailer.php';
    require_once '../vendor/PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings - UPDATE WITH YOUR SMTP DETAILS
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';        // SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'albinokh425@gmail.com';  
        $mail->Password   = 'hgww grom kage sadr';     
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('no-reply@unksystem.com', 'UNK System');
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Login OTP - UNK System';
        
        $mail->Body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f7fb; padding: 20px; }
                .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 16px; padding: 30px; text-align: center; }
                .logo { color: #e67e22; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                .otp-code { font-size: 36px; font-weight: bold; color: #e67e22; letter-spacing: 5px; padding: 15px; background: #f8fafc; border-radius: 12px; margin: 20px 0; }
                .footer { font-size: 12px; color: #64748b; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="logo">UNK System</div>
                <h2>Login Verification</h2>
                <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Use the OTP below to complete your login:</p>
                <div class="otp-code">' . $otp . '</div>
                <p>This OTP is valid for <strong>10 minutes</strong>.</p>
                <p>If you didn\'t request this, please ignore this email.</p>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' UNK System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Your OTP for UNK System login is: $otp. Valid for 10 minutes.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("OTP Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle OTP Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_otp'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Check if email exists
        $check_sql = "SELECT u.user_id, u.full_name, u.role, c.first_name, c.last_name 
                      FROM users u 
                      LEFT JOIN customers c ON u.user_id = c.user_id 
                      WHERE u.email = ? AND u.role = 'customer' AND u.status = 'active'";
        $stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $user_name = !empty($user['first_name']) ? $user['first_name'] : $user['full_name'];
            
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            
            // Delete old OTPs for this email
            $delete_sql = "DELETE FROM otp_verifications WHERE email = ? AND purpose = 'login'";
            $stmt_del = mysqli_prepare($conn, $delete_sql);
            mysqli_stmt_bind_param($stmt_del, 's', $email);
            mysqli_stmt_execute($stmt_del);
            mysqli_stmt_close($stmt_del);
            
            // Insert new OTP
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $insert_sql = "INSERT INTO otp_verifications (email, otp_code, purpose, expires_at) VALUES (?, ?, 'login', ?)";
            $stmt_ins = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt_ins, 'sss', $email, $otp, $expires_at);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);
            
            // Send OTP via email
            if (sendOTPEmail($email, $otp, $user_name)) {
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_user_id'] = $user['user_id'];
                $_SESSION['otp_user_name'] = $user_name;
                $step = 'verify';
                $success = "OTP sent to $email. Please check your inbox.";
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        } else {
            $error = "No active customer account found with this email address.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle OTP Verification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['otp_email'] ?? '';
    
    if (empty($otp)) {
        $error = "Please enter the OTP code";
    } elseif (empty($email)) {
        $error = "Session expired. Please request OTP again.";
        $step = 'request';
    } else {
        // Verify OTP
        $verify_sql = "SELECT * FROM otp_verifications WHERE email = ? AND otp_code = ? AND purpose = 'login' AND is_used = 0 AND expires_at > NOW()";
        $stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($stmt, 'ss', $email, $otp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $otp_data = mysqli_fetch_assoc($result);
            
            // Mark OTP as used
            $update_sql = "UPDATE otp_verifications SET is_used = 1 WHERE id = " . $otp_data['id'];
            mysqli_query($conn, $update_sql);
            
            // Set session
            $_SESSION['user_id'] = $_SESSION['otp_user_id'];
            $_SESSION['role'] = 'customer';
            $_SESSION['email'] = $email;
            
            // Get customer details
            $customer_sql = "SELECT customer_id, first_name, last_name FROM customers WHERE user_id = " . $_SESSION['otp_user_id'];
            $customer_result = mysqli_query($conn, $customer_sql);
            if ($customer_data = mysqli_fetch_assoc($customer_result)) {
                $_SESSION['customer_id'] = $customer_data['customer_id'];
                $_SESSION['full_name'] = $customer_data['first_name'] . ' ' . $customer_data['last_name'];
            }
            
            // Update last login
            $update_login = "UPDATE users SET last_login = NOW() WHERE user_id = " . $_SESSION['otp_user_id'];
            mysqli_query($conn, $update_login);
            
            // Clear OTP session variables
            unset($_SESSION['otp_email']);
            unset($_SESSION['otp_user_id']);
            unset($_SESSION['otp_user_name']);
            
            // Redirect to dashboard
            header("Location: das/dashboard.php");
            exit();
        } else {
            $error = "Invalid or expired OTP. Please request a new one.";
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>OTP Login - UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .logo {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 30px;
        }
        .logo span { color: #e67e22; }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
        }
        .step {
            text-align: center;
            flex: 1;
        }
        .step-number {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 700;
            color: #64748b;
        }
        .step.active .step-number {
            background: #e67e22;
            color: white;
        }
        .step.completed .step-number {
            background: #27ae60;
            color: white;
        }
        .step-label {
            font-size: 12px;
            color: #64748b;
        }
        .step.active .step-label {
            color: #e67e22;
            font-weight: 600;
        }
        
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 13px;
            color: #334155;
        }
        .input-group {
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: 0.2s;
            font-family: inherit;
        }
        input:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .otp-input {
            width: 55px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            padding: 0;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .resend-link {
            text-align: center;
            margin-top: 15px;
        }
        .resend-link a {
            color: #e67e22;
            text-decoration: none;
            font-size: 13px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
        }
        
        @media (max-width: 480px) {
            .login-container { padding: 25px; }
            .otp-input { width: 45px; height: 50px; font-size: 20px; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="logo">
        UNK <span>System</span>
    </div>
    
    <div class="step-indicator">
        <div class="step <?php echo $step == 'request' ? 'active' : ($step == 'verify' ? 'completed' : ''); ?>">
            <div class="step-number">1</div>
            <div class="step-label">Email</div>
        </div>
        <div class="step <?php echo $step == 'verify' ? 'active' : ''; ?>">
            <div class="step-number">2</div>
            <div class="step-label">Verify OTP</div>
        </div>
    </div>
    
    <?php if($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>
    
    <?php if($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    
    <?php if($step == 'request'): ?>
    <!-- Step 1: Request OTP -->
    <form method="POST">
        <div class="form-group">
            <label>Email Address</label>
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
            </div>
        </div>
        <button type="submit" name="request_otp" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Send OTP
        </button>
    </form>
    
    <div class="back-link">
        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to regular login</a>
    </div>
    
    <?php else: ?>
    <!-- Step 2: Verify OTP -->
    <form method="POST" id="otpForm">
        <div class="form-group">
            <label>Enter OTP Code</label>
            <div class="otp-input-group" id="otpContainer">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autofocus>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
            </div>
            <input type="hidden" name="otp" id="otpValue">
        </div>
        <button type="submit" name="verify_otp" class="btn btn-primary">
            <i class="fas fa-check-circle"></i> Verify & Login
        </button>
    </form>
    
    <div class="resend-link">
        <a href="javascript:void(0)" onclick="resendOTP()"><i class="fas fa-redo-alt"></i> Resend OTP</a>
    </div>
    
    <div class="back-link">
        <a href="login-otp.php"><i class="fas fa-arrow-left"></i> Back to email</a>
    </div>
    <?php endif; ?>
</div>

<script>
<?php if($step == 'verify'): ?>
// OTP input auto-tab
const inputs = document.querySelectorAll('.otp-input');
const otpValue = document.getElementById('otpValue');

inputs.forEach((input, index) => {
    input.addEventListener('input', (e) => {
        if (e.target.value.length === 1 && index < inputs.length - 1) {
            inputs[index + 1].focus();
        }
        updateOTPValue();
    });
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && index > 0 && !e.target.value) {
            inputs[index - 1].focus();
        }
    });
});

function updateOTPValue() {
    let otp = '';
    inputs.forEach(input => {
        otp += input.value;
    });
    otpValue.value = otp;
}

function resendOTP() {
    // Create form to resend OTP
    let form = document.createElement('form');
    form.method = 'POST';
    let email = '<?php echo htmlspecialchars($_SESSION['otp_email'] ?? ''); ?>';
    form.innerHTML = '<input type="hidden" name="request_otp" value="1"><input type="hidden" name="email" value="' + email + '">';
    document.body.appendChild(form);
    form.submit();
}
<?php endif; ?>
</script>
</body>
</html>