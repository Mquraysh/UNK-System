<?php
// delivery/settings/change-password.php - CHANGE PASSWORD
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get current password hash
    $user_sql = "SELECT password_hash FROM users WHERE user_id = '$user_id'";
    $user_result = mysqli_query($conn, $user_sql);
    $user = mysqli_fetch_assoc($user_result);
    
    if(!password_verify($current_password, $user['password_hash'])) {
        $error = "Current password is incorrect";
    } elseif(strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif(!preg_match('/[A-Z]/', $new_password)) {
        $error = "Password must contain at least one uppercase letter";
    } elseif(!preg_match('/[a-z]/', $new_password)) {
        $error = "Password must contain at least one lowercase letter";
    } elseif(!preg_match('/[0-9]/', $new_password)) {
        $error = "Password must contain at least one number";
    } elseif($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password_hash = '$hashed_password' WHERE user_id = '$user_id'";
        
        if(mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = "Password changed successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to change password. Please try again.";
        }
    }
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; line-height: 1.5; }
        .delivery-content {
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
            background: #f5f7fa;
            transition: all 0.2s ease;
        }
        .delivery-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            color: #e67e22;
        }
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        .card-body {
            padding: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .btn-save {
            background: #e67e22;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-save:hover {
            background: #d35400;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .requirements {
            background: #f8fafc;
            padding: 12px;
            border-radius: 10px;
            margin-top: 10px;
        }
        .requirements p {
            font-size: 11px;
            margin: 5px 0;
        }
        .req-check {
            color: #27ae60;
        }
        .req-cross {
            color: #e74c3c;
        }
        
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <h1><i class="fas fa-key"></i> Change Password</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Update Your Password</h3>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="passwordForm">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                </div>
                
                <div class="requirements">
                    <p><strong>Password Requirements:</strong></p>
                    <p id="length-req"><i class="fas fa-times-circle req-cross"></i> At least 8 characters</p>
                    <p id="upper-req"><i class="fas fa-times-circle req-cross"></i> At least 1 uppercase letter</p>
                    <p id="lower-req"><i class="fas fa-times-circle req-cross"></i> At least 1 lowercase letter</p>
                    <p id="number-req"><i class="fas fa-times-circle req-cross"></i> At least 1 number</p>
                    <p id="match-req"><i class="fas fa-times-circle req-cross"></i> Passwords match</p>
                </div>
                
                <button type="submit" class="btn-save" id="submitBtn" disabled><i class="fas fa-save"></i> Change Password</button>
            </form>
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
    let allValid = true;
    
    if (password.length >= 8) {
        lengthReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 8 characters';
        lengthReq.style.color = '#27ae60';
    } else {
        lengthReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 8 characters';
        lengthReq.style.color = '#e74c3c';
        allValid = false;
    }
    
    if (password.match(/[A-Z]/)) {
        upperReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 uppercase letter';
        upperReq.style.color = '#27ae60';
    } else {
        upperReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 uppercase letter';
        upperReq.style.color = '#e74c3c';
        allValid = false;
    }
    
    if (password.match(/[a-z]/)) {
        lowerReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 lowercase letter';
        lowerReq.style.color = '#27ae60';
    } else {
        lowerReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 lowercase letter';
        lowerReq.style.color = '#e74c3c';
        allValid = false;
    }
    
    if (password.match(/[0-9]/)) {
        numberReq.innerHTML = '<i class="fas fa-check-circle req-check"></i> At least 1 number';
        numberReq.style.color = '#27ae60';
    } else {
        numberReq.innerHTML = '<i class="fas fa-times-circle req-cross"></i> At least 1 number';
        numberReq.style.color = '#e74c3c';
        allValid = false;
    }
    
    if (newPassword.value === confirmPassword.value && newPassword.value !== '') {
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
</html