<?php
// business/login.php - Business Login with Remember Me & Forgot Password
require_once '../config/database.php';

session_start();

// Auto-login via Remember Me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    // Hash token before comparing (store hashed in DB)
    $hashed_token = hash('sha256', $token);
    $stmt = mysqli_prepare($conn, "SELECT u.*, b.business_id, b.business_name, b.is_verified 
                                   FROM users u 
                                   LEFT JOIN businesses b ON u.user_id = b.user_id 
                                   WHERE u.remember_token = ? AND u.token_expires > NOW() AND u.role = 'business'");
    mysqli_stmt_bind_param($stmt, "s", $hashed_token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user && $user['status'] == 'active') {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['business_id'] = $user['business_id'];
        $_SESSION['business_name'] = $user['business_name'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['is_verified'] = $user['is_verified'];
        header("Location: dashboard.php");
        exit();
    } else {
        // Invalid or expired cookie, clear it
        setcookie('remember_me', '', time() - 3600, '/');
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'business') {
    header("Location: dashboard.php");
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

// Password reset success message (from reset_password.php)
if (isset($_SESSION['reset_success'])) {
    $success = $_SESSION['reset_success'];
    unset($_SESSION['reset_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;
    
    // Prepare statement
    $stmt = mysqli_prepare($conn, "SELECT u.*, b.business_id, b.business_name, b.is_verified 
                                   FROM users u 
                                   LEFT JOIN businesses b ON u.user_id = b.user_id 
                                   WHERE u.email = ? AND u.role = 'business'");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($user) {
        // Check if account is active
        if ($user['status'] != 'active') {
            $error = "Your account is inactive. Please contact support!";
        } elseif (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['business_id'] = $user['business_id'];
            $_SESSION['business_name'] = $user['business_name'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['is_verified'] = $user['is_verified'];
            
            // Handle Remember Me
            if ($remember_me) {
                $remember_token = bin2hex(random_bytes(32)); // 64 chars
                $hashed_token = hash('sha256', $remember_token);
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ?, token_expires = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($update_stmt, "ssi", $hashed_token, $expires, $user['user_id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                
                // Set cookie (30 days)
                setcookie('remember_me', $remember_token, time() + (86400 * 30), '/', '', false, true);
            } else {
                // Clear any existing remember token
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = NULL, token_expires = NULL WHERE user_id = ?");
                mysqli_stmt_bind_param($update_stmt, "i", $user['user_id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                setcookie('remember_me', '', time() - 3600, '/');
            }
            
            // Update last login time
            $update_login = "UPDATE users SET last_login = NOW() WHERE user_id = " . $user['user_id'];
            mysqli_query($conn, $update_login);
            
            header("Location: dashboard.php");
            exit();
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
    <title>Business Login - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .login-container { max-width: 450px; margin: 3rem auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%); color: white; padding: 30px; text-align: center; }
        .card-header h2 { font-size: 24px; margin-bottom: 5px; }
        .card-header h2 i { color: #e67e22; }
        .card-header p { opacity: 0.9; font-size: 13px; }
        .card-body { padding: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; font-size: 13px; }
        .form-group label i { color: #e67e22; margin-right: 5px; }
        .input-group { position: relative; display: flex; align-items: center; }
        .input-group i { position: absolute; left: 12px; color: #7f8c8d; font-size: 14px; }
        .form-control { width: 100%; padding: 12px 12px 12px 38px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .btn { width: 100%; padding: 12px; background: #e67e22; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(230,126,34,0.3); }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .register-link { text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
        .register-link p { margin: 5px 0; font-size: 13px; }
        .register-link a { color: #e67e22; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
        .home-link { text-align: center; margin-top: 15px; }
        .home-link a { color: #7f8c8d; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s; }
        .home-link a:hover { color: #e67e22; }
        .checkbox-group { display: flex; align-items: center; gap: 8px; margin-top: 5px; }
        .checkbox-group label { margin: 0; font-weight: normal; font-size: 13px; cursor: pointer; }
        .forgot-link { text-align: right; margin-top: -10px; margin-bottom: 15px; }
        .forgot-link a { font-size: 12px; color: #e67e22; text-decoration: none; }
        .forgot-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-store"></i> Business Login</h2>
            <p>Access your business dashboard</p>
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
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" placeholder="Enter your email" required>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="checkbox-group" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <label>
                        <input type="checkbox" name="remember_me"> Remember Me
                    </label>
                    <div class="forgot-link" style="margin: 0;">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="register-link">
                <p>Don't have a business account? <a href="register.php">Register as Business</a></p>
                <p><a href="../customer/login.php">Customer Login</a> | <a href="../delivery/login.php">Delivery Login</a></p>
            </div>
            
            <div class="home-link">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php include '../includes/footer.php'; ?>