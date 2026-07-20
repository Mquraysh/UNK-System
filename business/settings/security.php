<?php
// business/settings/security.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Change Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password_hash FROM users WHERE user_id='$user_id'"));
    $errors = [];
    
    if (!password_verify($current, $user_data['password_hash'])) $errors[] = "Current password is incorrect!";
    if (strlen($new) < 8) $errors[] = "Password must be at least 8 characters";
    if (!preg_match('/[A-Z]/', $new)) $errors[] = "Password must contain at least one uppercase letter";
    if (!preg_match('/[a-z]/', $new)) $errors[] = "Password must contain at least one lowercase letter";
    if (!preg_match('/[0-9]/', $new)) $errors[] = "Password must contain at least one number";
    if ($new !== $confirm) $errors[] = "Passwords do not match";
    
    if (empty($errors)) {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET password_hash='$new_hash' WHERE user_id='$user_id'");
        
        $_SESSION['flash_message'] = "Password changed successfully! Please login again.";
        $_SESSION['flash_type'] = "success";
        session_destroy();
        header("Location: ../login.php");
        exit();
    } else {
        $_SESSION['flash_message'] = implode("<br>", $errors);
        $_SESSION['flash_type'] = "danger";
        header("Location: security.php");
        exit();
    }
}

// Deactivate Account
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deactivate_account'])) {
    $password = $_POST['deactivate_password'];
    $user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password_hash FROM users WHERE user_id='$user_id'"));
    
    if (password_verify($password, $user_data['password_hash'])) {
        // Deactivate user account
        mysqli_query($conn, "UPDATE users SET status='inactive' WHERE user_id='$user_id'");
        // Deactivate all products (using quantity_in_stock to 0 or is_available)
        mysqli_query($conn, "UPDATE products SET is_available=0 WHERE business_id='{$business['business_id']}'");
        
        $_SESSION['flash_message'] = "Your account has been deactivated. You can reactivate by logging in again.";
        $_SESSION['flash_type'] = "success";
        
        // Destroy session and redirect to home
        session_destroy();
        header("Location: ../index.php?deactivated=1");
        exit();
    } else {
        $_SESSION['flash_message'] = "Incorrect password! Cannot deactivate account.";
        $_SESSION['flash_type'] = "danger";
        header("Location: security.php");
        exit();
    }
}

// Reactivate Account
if (isset($_GET['reactivate']) && $user['status'] == 'inactive') {
    mysqli_query($conn, "UPDATE users SET status='active' WHERE user_id='$user_id'");
    mysqli_query($conn, "UPDATE products SET is_available=1 WHERE business_id='{$business['business_id']}'");
    
    $_SESSION['flash_message'] = "Your account has been reactivated! Your products are now visible to customers.";
    $_SESSION['flash_type'] = "success";
    header("Location: security.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; margin-top: 5px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 25px; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; color: #2c3e50; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        
        .btn-primary { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); }
        .btn-primary:disabled { background: #cbd5e1; cursor: not-allowed; transform: none; }
        
        .btn-warning { background: #f39c12; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-warning:hover { background: #e67e22; transform: translateY(-2px); }
        
        .btn-danger { background: #e74c3c; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.3s; }
        .btn-danger:hover { background: #c0392b; transform: translateY(-2px); }
        
        .btn-success { background: #27ae60; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.3s; }
        .btn-success:hover { background: #219a52; transform: translateY(-2px); }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fff3cd; color: #856404; border-left: 4px solid #f39c12; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fef3c7; color: #d97706; }
        .badge-suspended { background: #fee2e2; color: #dc2626; }
        
        small { font-size: 11px; color: #94a3b8; display: block; margin-top: 5px; }
        
        .requirements-list { background: #f8fafc; padding: 12px; border-radius: 12px; margin-top: 10px; }
        .requirements-list p { font-size: 12px; margin: 5px 0; }
        .req-check { color: #27ae60; }
        .req-cross { color: #e74c3c; }
        
        .warning-box { background: #fef3c7; border-left: 4px solid #f39c12; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .warning-box i { color: #f39c12; font-size: 20px; margin-right: 10px; }
        
        .danger-box { background: #fee2e2; border-left: 4px solid #e74c3c; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .danger-box i { color: #e74c3c; font-size: 20px; margin-right: 10px; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { 
            .page-header { flex-direction: column; } 
            .btn-primary, .btn-warning, .btn-danger, .btn-success { width: 100%; justify-content: center; } 
            .card-body { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-shield-alt"></i> Security Settings</h1>
            <p>Manage your account security and password</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Change Password Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> Change Password</h3>
        </div>
        <div class="card-body">
            <form method="POST">
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
                
                <div class="requirements-list">
                    <p><strong>Password Requirements:</strong></p>
                    <p id="length-req"><i class="fas fa-times-circle req-cross"></i> At least 8 characters</p>
                    <p id="upper-req"><i class="fas fa-times-circle req-cross"></i> At least 1 uppercase letter (A-Z)</p>
                    <p id="lower-req"><i class="fas fa-times-circle req-cross"></i> At least 1 lowercase letter (a-z)</p>
                    <p id="number-req"><i class="fas fa-times-circle req-cross"></i> At least 1 number (0-9)</p>
                    <p id="match-req"><i class="fas fa-times-circle req-cross"></i> Passwords match</p>
                </div>
                
                <button type="submit" name="change_password" class="btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-key"></i> Change Password
                </button>
            </form>
        </div>
    </div>
    
    <!-- Account Status Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Account Status</h3>
        </div>
        <div class="card-body">
            <?php if($user['status'] == 'active'): ?>
                <span class="badge badge-active"><i class="fas fa-check-circle"></i> Active</span>
                <small style="display: inline-block; margin-left: 10px;">Your account is active and visible to customers</small>
            <?php elseif($user['status'] == 'inactive'): ?>
                <span class="badge badge-inactive"><i class="fas fa-clock"></i> Inactive</span>
                <small style="display: inline-block; margin-left: 10px;">Your account is deactivated. Customers cannot see your products.</small>
                <div style="margin-top: 15px;">
                    <a href="?reactivate=1" class="btn-success" style="padding: 10px 20px;">
                        <i class="fas fa-play-circle"></i> Reactivate Account
                    </a>
                </div>
            <?php else: ?>
                <span class="badge badge-suspended"><i class="fas fa-ban"></i> Suspended</span>
                <small style="display: inline-block; margin-left: 10px;">Your account has been suspended. Contact support for assistance.</small>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Deactivate Account Card - ONLY SHOWS FOR ACTIVE ACCOUNTS -->
    <?php if($user['status'] == 'active'): ?>
    <div class="card">
        <div class="card-header" style="background:#fef3c7;">
            <h3 style="color:#d97706;"><i class="fas fa-pause-circle"></i> Deactivate Account</h3>
        </div>
        <div class="card-body">
            <div class="warning-box">
                <i class="fas fa-info-circle"></i> 
                <strong>What happens when you deactivate?</strong><br>
                • Your business will be hidden from customers<br>
                • All your products will become unavailable for purchase<br>
                • Customers cannot place new orders from your business<br>
                • Your data is preserved and can be restored by reactivating<br>
                • You can reactivate anytime by logging in again
            </div>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account? Your products will be hidden from customers. You can reactivate by logging in again.')">
                <div class="form-group">
                    <label>Enter your password to confirm deactivation:</label>
                    <input type="password" name="deactivate_password" class="form-control" required>
                </div>
                <button type="submit" name="deactivate_account" class="btn-warning">
                    <i class="fas fa-pause-circle"></i> Deactivate Account
                </button>
                <small style="display: block; margin-top: 10px;">⚠️ Deactivating will hide your products until you reactivate</small>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Delete Account Card (Danger Zone) -->
    <div class="card" style="border:1px solid #fee2e2;">
        <div class="card-header" style="background:#fef2f2;">
            <h3 style="color:#dc2626;"><i class="fas fa-exclamation-triangle"></i> Danger Zone - Delete Account</h3>
        </div>
        <div class="card-body">
            <div class="danger-box">
                <i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i>
                <strong>Warning: Permanent Action!</strong><br>
                Deleting your account is permanent and cannot be undone. All your business data, products, orders, and customer information will be permanently removed from the system.
            </div>
            
            <a href="delete-account.php" class="btn-danger" onclick="return confirm('WARNING: This will permanently delete ALL your business data! This action cannot be undone. Click OK to proceed.')">
                <i class="fas fa-trash-alt"></i> Permanently Delete Account
            </a>
            <small style="display: block; margin-top: 10px;">⚠️ This action is irreversible. All your products, orders, and customer data will be lost forever!</small>
        </div>
    </div>
</div>

<script>
const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const submitBtn = document.getElementById('submitBtn');
const lengthReq = document.getElementById('length-req');
const upperReq = document.getElementById('upper-req');
const lowerReq = document.getElementById('lower-req');
const numberReq = document.getElementById('number-req');
const matchReq = document.getElementById('match-req');

function validateForm() {
    const password = newPassword.value;
    const confirm = confirmPassword.value;
    let allValid = true;
    
    // Length check
    if (password.length >= 8) { 
        lengthReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 8 characters'; 
        lengthReq.style.color = '#27ae60'; 
    } else { 
        lengthReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 8 characters'; 
        lengthReq.style.color = '#e74c3c'; 
        allValid = false; 
    }
    
    // Uppercase check
    if (password.match(/[A-Z]/)) { 
        upperReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 uppercase letter'; 
        upperReq.style.color = '#27ae60'; 
    } else { 
        upperReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 uppercase letter'; 
        upperReq.style.color = '#e74c3c'; 
        allValid = false; 
    }
    
    // Lowercase check
    if (password.match(/[a-z]/)) { 
        lowerReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 lowercase letter'; 
        lowerReq.style.color = '#27ae60'; 
    } else { 
        lowerReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 lowercase letter'; 
        lowerReq.style.color = '#e74c3c'; 
        allValid = false; 
    }
    
    // Number check
    if (password.match(/[0-9]/)) { 
        numberReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 number'; 
        numberReq.style.color = '#27ae60'; 
    } else { 
        numberReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 number'; 
        numberReq.style.color = '#e74c3c'; 
        allValid = false; 
    }
    
    // Match check
    if (password !== '' && password === confirm) { 
        matchReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> Passwords match'; 
        matchReq.style.color = '#27ae60'; 
    } else { 
        matchReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> Passwords match'; 
        matchReq.style.color = '#e74c3c'; 
        allValid = false; 
    }
    
    submitBtn.disabled = !allValid;
}

newPassword.addEventListener('keyup', validateForm);
confirmPassword.addEventListener('keyup', validateForm);
</script>
</body>
</html>