<?php
// business/forgot-password.php - Business Forgot Password (Matching Login Style)
require_once '../config/database.php';
session_start();

$error = '';
$success = '';
$email = '';

// Function to send OTP email
function sendOTPEmail($to, $otp, $name, $businessName = '') {
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
            
            $mail->setFrom('no-reply@unksystem.com', 'UNK System Business');
            $mail->addAddress($to, $name);
            
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset OTP - UNK System Business';
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
                    <div class="logo">UNK System Business</div>
                    <h2>Password Reset</h2>
                    <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                    ' . (!empty($businessName) ? '<p>Business: <strong>' . htmlspecialchars($businessName) . '</strong></p>' : '') . '
                    <p>You requested to reset your password. Your OTP code is:</p>
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $email_escaped = mysqli_real_escape_string($conn, $email);
        $sql = "SELECT u.user_id, u.full_name, b.business_name 
                FROM users u 
                LEFT JOIN businesses b ON u.user_id = b.user_id 
                WHERE u.email = '$email_escaped' 
                AND u.role = 'business' 
                AND u.status = 'active'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            $otp = sprintf("%06d", mt_rand(1, 999999));
            mysqli_query($conn, "DELETE FROM otp_verifications WHERE email = '$email_escaped' AND purpose = 'business_password_reset'");
            
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $insert_sql = "INSERT INTO otp_verifications (email, otp_code, purpose, expires_at, created_at) 
                           VALUES ('$email_escaped', '$otp', 'business_password_reset', '$expires_at', NOW())";
            mysqli_query($conn, $insert_sql);
            
            $user_name = $user['full_name'] ?? 'Business Owner';
            $business_name = $user['business_name'] ?? '';
            if (sendOTPEmail($email, $otp, $user_name, $business_name)) {
                $_SESSION['reset_business_email'] = $email;
                $_SESSION['reset_business_user_id'] = $user['user_id'];
                $_SESSION['reset_business_name'] = $user_name;
                
                header("Location: reset-password-otp.php");
                exit();
            } else {
                $error = "Failed to send OTP. Please try again.";
            }
        } else {
            $error = "No business account found with this email address.";
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
    <title>Forgot Password - UNK System Business</title>
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
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 13px;
        }
        .form-group label i { color: #e67e22; margin-right: 5px; }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-group i {
            position: absolute;
            left: 12px;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 12px 12px 38px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
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
        
        .info-box {
            background: #e3f2fd;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #1565c0;
        }
        .info-box i { font-size: 18px; }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .back-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .back-link a:hover { color: #e67e22; }
        
        @media (max-width: 480px) {
            .card-header { padding: 25px 20px; }
            .card-body { padding: 20px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-store"></i> Forgot Password</h2>
            <p>Enter your email to reset your business password</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>We'll send a 6-digit OTP to your email to reset your password.</div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter your registered email" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                    </div>
                </div>
                
                <button type="submit" name="send_otp" class="btn">
                    <i class="fas fa-paper-plane"></i> Send OTP
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php include '../includes/footer2.php'; ?>