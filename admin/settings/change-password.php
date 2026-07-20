<?php
// admin/settings/change-password.php - CHANGE ADMIN PASSWORD
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    $user_sql = "SELECT password_hash FROM users WHERE user_id = $user_id";
    $user_res = mysqli_query($conn, $user_sql);
    $user = mysqli_fetch_assoc($user_res);

    if (!password_verify($current, $user['password_hash'])) {
        $error = "Current password is incorrect";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif (!preg_match('/[a-z]/', $new)) {
        $error = "Password must contain at least one lowercase letter";
    } elseif (!preg_match('/[0-9]/', $new)) {
        $error = "Password must contain at least one number";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match";
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $update = "UPDATE users SET password_hash = '$new_hash' WHERE user_id = $user_id";
        if (mysqli_query($conn, $update)) {
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password";
        }
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        .admin-content { margin-left: 280px; padding: 30px 35px; background: #f1f5f9; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; color: #1e293b; display: flex; align-items: center; gap: 12px; }
        .btn-back { background: #2c3e50; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; }
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; max-width: 1200px; margin: 0 auto; }
        .card-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
        .card-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e293b; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .btn-save { background: #e67e22; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; width: 100%; font-weight: 600; }
        .alert { padding: 12px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .requirements { background: #f8fafc; padding: 12px; border-radius: 12px; margin-top: 10px; font-size: 12px; }
        .req-check { color: #27ae60; }
        .req-cross { color: #e74c3c; }
        @media (max-width: 1024px) { .admin-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-key"></i> Change Password</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    <div class="card">
        <div class="card-header">Update Your Password</div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                <div class="requirements" id="requirements">
                    <p><strong>Password must:</strong></p>
                    <p id="length-req"><i class="fas fa-times-circle req-cross"></i> At least 8 characters</p>
                    <p id="upper-req"><i class="fas fa-times-circle req-cross"></i> At least one uppercase letter</p>
                    <p id="lower-req"><i class="fas fa-times-circle req-cross"></i> At least one lowercase letter</p>
                    <p id="number-req"><i class="fas fa-times-circle req-cross"></i> At least one number</p>
                    <p id="match-req"><i class="fas fa-times-circle req-cross"></i> Passwords match</p>
                </div>
                <button type="submit" class="btn-save" id="submitBtn" disabled><i class="fas fa-save"></i> Change Password</button>
            </form>
        </div>
    </div>
</div>
<script>
    const newPwd = document.getElementById('new_password');
    const confirmPwd = document.getElementById('confirm_password');
    const lengthReq = document.getElementById('length-req');
    const upperReq = document.getElementById('upper-req');
    const lowerReq = document.getElementById('lower-req');
    const numberReq = document.getElementById('number-req');
    const matchReq = document.getElementById('match-req');
    const submitBtn = document.getElementById('submitBtn');

    function validate() {
        let pwd = newPwd.value;
        let allValid = true;

        if(pwd.length >= 8) { lengthReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 8 characters'; lengthReq.style.color = '#27ae60'; }
        else { lengthReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 8 characters'; lengthReq.style.color = '#e74c3c'; allValid = false; }

        if(/[A-Z]/.test(pwd)) { upperReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least one uppercase letter'; upperReq.style.color = '#27ae60'; }
        else { upperReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least one uppercase letter'; upperReq.style.color = '#e74c3c'; allValid = false; }

        if(/[a-z]/.test(pwd)) { lowerReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least one lowercase letter'; lowerReq.style.color = '#27ae60'; }
        else { lowerReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least one lowercase letter'; lowerReq.style.color = '#e74c3c'; allValid = false; }

        if(/[0-9]/.test(pwd)) { numberReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least one number'; numberReq.style.color = '#27ae60'; }
        else { numberReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least one number'; numberReq.style.color = '#e74c3c'; allValid = false; }

        if(pwd === confirmPwd.value && pwd !== '') { matchReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> Passwords match'; matchReq.style.color = '#27ae60'; }
        else { matchReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> Passwords match'; matchReq.style.color = '#e74c3c'; allValid = false; }

        submitBtn.disabled = !allValid;
    }

    newPwd.addEventListener('keyup', validate);
    confirmPwd.addEventListener('keyup', validate);
</script>
</body>
</html>