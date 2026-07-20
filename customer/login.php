<?php
// customer/login.php 0
require_once '../config/database.php';
// require_once '../config/logs.php'; 
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer') {
    header("Location: das/dashboard.php");
    exit();
}

$error = '';
$email = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Function to send OTP email using PHPMailer
function sendOTPEmail($to, $otp, $name) {
    // Load PHPMailer classes
    require_once 'vendor/PHPMailer/src/Exception.php';
    require_once 'vendor/PHPMailer/src/PHPMailer.php';
    require_once 'vendor/PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
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
        $mail->Subject = 'Your Login Verification Code - UNK System';
        
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
                <h2>Login Verification</h2>
                <p>Hello <strong>' . htmlspecialchars($name) . '</strong>,</p>
                <p>Use the code below to complete your login:</p>
                <div class="otp-code">' . $otp . '</div>
                <p>This code is valid for <strong>10 minutes</strong>.</p>
                <div class="footer"><p>&copy; ' . date('Y') . ' UNK System</p></div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = "Your login verification code is: $otp. Valid for 10 minutes.";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("OTP Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Get user data
    $stmt = mysqli_prepare($conn, "SELECT u.*, c.customer_id, c.first_name, c.last_name 
                                   FROM users u 
                                   LEFT JOIN customers c ON u.user_id = c.user_id 
                                   WHERE u.email = ? AND u.role = 'customer'");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user) {
        if ($user['status'] != 'active') {
            $error = "Your account is inactive. Please contact support!";
        } elseif (password_verify($password, $user['password_hash'])) {
            // Password is correct - Generate OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            
            // Delete old OTPs
            mysqli_query($conn, "DELETE FROM otp_verifications WHERE email = '$email' AND purpose = 'login'");
            
            // Insert new OTP
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $insert_sql = "INSERT INTO otp_verifications (email, otp_code, purpose, expires_at) VALUES (?, ?, 'login', ?)";
            $stmt_ins = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($stmt_ins, 'sss', $email, $otp, $expires_at);
            mysqli_stmt_execute($stmt_ins);
            mysqli_stmt_close($stmt_ins);
            
            // Send OTP email
            $user_name = $user['first_name'] ?: $user['full_name'];
            if (sendOTPEmail($email, $otp, $user_name)) {
                // Store user info in session temporarily
                $_SESSION['temp_user_id'] = $user['user_id'];
                $_SESSION['temp_email'] = $email;
                $_SESSION['temp_customer_id'] = $user['customer_id'];
                $_SESSION['temp_full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Redirect to OTP verification page
                header("Location: verify-otp.php");
                exit();
            } else {
                $error = "Failed to send verification code. Please try again.";
            }
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Invalid email or password!";
    }
}

include '../includes/header.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - UNK System</title>
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
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
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
        
        .info-text {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 10px;
            font-size: 12px;
            color: #1565c0;
            text-align: center;
            margin-top: 15px;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .register-link p { margin: 5px 0; font-size: 13px; }
        .register-link a { color: #e67e22; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
        
        .home-link {
            text-align: center;
            margin-top: 15px;
        }
        .home-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        .home-link a:hover { color: #e67e22; }
        
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
            <h2><i class="fas fa-user"></i> Customer Login</h2>
            <p>Enter your credentials to continue</p>
        </div>
        <div class="card-body">
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>

                <div class="text-center" style="display: flex; justify-content: flex-end; margin-top: 15px; margin-bottom: 15px" >
                    <a href="forgot-password.php" style="color: #e67e22; text-decoration: none; font-size: 13px;">
                        <i class="fas fa-key"></i> Forgot Password?
                    </a>
                </div>
            </form>
            
            <!-- <div class="info-text">
                <i class="fas fa-shield-alt"></i> We'll send a verification code to your email after login
            </div> -->
            
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register as Customer</a></p>
                <p><a href="../business/login.php">Business Login</a> | <a href="../delivery/login.php">Delivery Login</a></p>
            </div>
            
            <div class="home-link">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php include '../includes/footer2.php'; ?>