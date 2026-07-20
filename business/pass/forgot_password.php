<?php
// business/forgot_password.php - Request password reset (local link display)
require_once '../config/database.php';
session_start();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists and belongs to a business
    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name FROM users WHERE email = ? AND role = 'business'");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user) {
        // Generate secure token (64 hex chars)
        $reset_token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($update_stmt, "ssi", $reset_token, $expires, $user['user_id']);
        $updated = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        if ($updated) {
            // Build reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/reset_password.php?token=" . $reset_token;
            $success = "A password reset link has been generated.<br>
                        Click the link below (valid for 1 hour):<br><br>
                        <a href='$reset_link' target='_blank'>$reset_link</a><br><br>
                        <small>In production, this link would be sent to your email.</small>";
        } else {
            $error = "Failed to generate reset link. Please try again.";
        }
    } else {
        // Security: don't reveal if email exists
        $success = "If that email is registered as a business account, you will receive a reset link (in this local version, the link is shown above).";
    }
}

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; min-height: 100vh; }
        .container { max-width: 450px; margin: 3rem auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%); color: white; padding: 30px; text-align: center; }
        .card-header h2 { font-size: 24px; }
        .card-header h2 i { color: #e67e22; }
        .card-header p { opacity: 0.9; font-size: 13px; }
        .card-body { padding: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; font-size: 13px; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 10px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        .btn { width: 100%; padding: 12px; background: #e67e22; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn:hover { background: #d35400; transform: translateY(-2px); }
        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; word-break: break-word; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a { color: #e67e22; text-decoration: none; font-size: 13px; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-key"></i> Forgot Password</h2>
            <p>Enter your business email to reset your password</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if(!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your registered business email" required>
                </div>
                <button type="submit" class="btn">Send Reset Link</button>
            </form>
            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php include '../includes/footer.php'; ?>