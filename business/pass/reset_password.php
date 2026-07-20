<?php
// business/reset_password.php - Wider layout (upana wakawaids)
require_once '../config/database.php';
session_start();

$error = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

$debug_info = '';
$user = null;

// Verify token exists and belongs to a business account
$stmt = mysqli_prepare($conn, "SELECT user_id, full_name, reset_token, reset_expires, role FROM users WHERE reset_token = ? AND role = 'business'");
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    $error = "Invalid reset link. The token does not exist in our records or your account is not a business account.";
    
    $stmt2 = mysqli_prepare($conn, "SELECT role FROM users WHERE reset_token = ?");
    mysqli_stmt_bind_param($stmt2, "s", $token);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);
    $wrong_role = mysqli_fetch_assoc($res2);
    mysqli_stmt_close($stmt2);
    if ($wrong_role) {
        $error .= " (Your account role is '{$wrong_role['role']}', but this reset link is for business accounts only.)";
    }
} else {
    $expires_time = strtotime($user['reset_expires']);
    $now = time();
    if ($now > $expires_time) {
        $error = "Reset link has expired. (Expired on " . $user['reset_expires'] . ") Please request a new one.";
        $user = null;
    } else {
        $debug_info = "Token valid for user: " . htmlspecialchars($user['full_name']) . " (expires at " . $user['reset_expires'] . ")";
    }
}

if ($user && empty($error) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    $pwd_errors = [];
    if (strlen($password) < 8) $pwd_errors[] = "at least 8 characters";
    if (!preg_match('/[A-Z]/', $password)) $pwd_errors[] = "one uppercase letter";
    if (!preg_match('/[a-z]/', $password)) $pwd_errors[] = "one lowercase letter";
    if (!preg_match('/[0-9]/', $password)) $pwd_errors[] = "one number";
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $pwd_errors[] = "one special character";
    
    if (!empty($pwd_errors)) {
        $error = "Password must contain: " . implode(", ", $pwd_errors);
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        mysqli_stmt_bind_param($update_stmt, "si", $hashed, $user['user_id']);
        $updated = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        if ($updated) {
            $_SESSION['reset_success'] = "Password reset successful! Please login with your new password.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
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
    <title>Reset Password - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        /* Wider container */
        .container { max-width: 800px; margin: 3rem auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%); color: white; padding: 30px; text-align: center; }
        .card-header h2 { font-size: 28px; margin-bottom: 8px; }
        .card-header h2 i { color: #e67e22; margin-right: 10px; }
        .card-header p { opacity: 0.9; font-size: 14px; }
        .card-body { padding: 40px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 10px; font-weight: 600; color: #2c3e50; font-size: 14px; }
        .form-control { width: 100%; padding: 14px 18px; border: 1px solid #ddd; border-radius: 10px; font-size: 15px; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .btn { width: 100%; padding: 14px; background: #e67e22; color: white; border: none; border-radius: 10px; font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(230,126,34,0.3); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-size: 14px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .text-muted { font-size: 12px; color: #7f8c8d; margin-top: 8px; display: block; }
        .back-link { text-align: center; margin-top: 30px; }
        .back-link a { color: #e67e22; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        @media (max-width: 768px) {
            .container { max-width: 95%; margin: 2rem auto; }
            .card-body { padding: 25px; }
            .card-header h2 { font-size: 24px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-lock-open"></i> Reset Password</h2>
            <p>Create a new password for your business account</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($debug_info)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $debug_info; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($user && empty($error)): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <small class="text-muted">Min 8 characters, at least one uppercase, one lowercase, one number, and one special character (!@#$%^&*)</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php elseif(empty($error) && !$user): ?>
                <div class="alert alert-danger">Invalid or expired reset link. Please request a new one.</div>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script>
    const pwd = document.getElementById('password');
    const conf = document.getElementById('confirm_password');
    function checkMatch() {
        if (pwd.value !== conf.value) {
            conf.setCustomValidity("Passwords do not match");
        } else {
            conf.setCustomValidity('');
        }
    }
    pwd.onchange = checkMatch;
    conf.onkeyup = checkMatch;
</script>

</body>
</html>

<?php include '../includes/footer.php'; ?>