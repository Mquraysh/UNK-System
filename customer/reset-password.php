<?php
// customer/reset-password.php 
require_once '../config/database.php';
session_start();

// Redirect if not verified
if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    header("Location: forgot-password.php");
    exit();
}

// Redirect if no reset session data
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot-password.php");
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];
$user_id = $_SESSION['reset_user_id'];

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $user_id_escaped = (int)$user_id;
        
        $sql = "UPDATE users SET password_hash = '$hashed_password' WHERE user_id = $user_id_escaped";
        $result = mysqli_query($conn, $sql);
        
        if ($result) {
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_full_name']);
            
            $_SESSION['password_reset_success'] = "Your password has been reset successfully. Please login.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Failed to reset password. Please try again.";
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
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: #1565c0;
        }
        .info-box i { font-size: 18px; }
        .info-box strong { color: #0d47a1; }
        
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
        
        /* Password Strength Meter */
        .strength-meter { margin-top: 8px; }
        .strength-bar { display: flex; gap: 6px; margin-top: 5px; }
        .strength-segment { height: 4px; flex: 1; background: #e2e8f0; border-radius: 4px; transition: all 0.3s; }
        .strength-text { font-size: 11px; margin-top: 5px; }
        .strength-weak .strength-segment:nth-child(1) { background: #dc2626; }
        .strength-medium .strength-segment:nth-child(1),
        .strength-medium .strength-segment:nth-child(2) { background: #f59e0b; }
        .strength-strong .strength-segment { background: #10b981; }
        
        .password-hint { font-size: 11px; color: #64748b; margin-top: 5px; }
        .password-hint i { color: #e67e22; margin-right: 4px; }
        
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
            <h2><i class="fas fa-lock"></i> Reset Password</h2>
            <p>Create a new password for your account</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <i class="fas fa-envelope"></i>
                <div>Resetting password for: <strong><?php echo htmlspecialchars($email); ?></strong></div>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> New Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required autofocus>
                    </div>
                    <div class="strength-meter" id="strengthMeter">
                        <div class="strength-bar">
                            <div class="strength-segment"></div>
                            <div class="strength-segment"></div>
                            <div class="strength-segment"></div>
                        </div>
                        <div class="strength-text" id="strengthText"></div>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i> Use 8+ characters with letters and numbers
                    </div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <div class="input-group">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                    </div>
                    <div id="matchMessage" class="password-hint"></div>
                </div>
                
                <button type="submit" name="reset_password" class="btn" id="submitBtn">
                    <i class="fas fa-save"></i> Reset Password
                </button>
            </form>
            
            <div class="back-link">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>
</div>

<script>
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const strengthMeter = document.getElementById('strengthMeter');
const strengthText = document.getElementById('strengthText');
const matchMessage = document.getElementById('matchMessage');
const submitBtn = document.getElementById('submitBtn');

function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    if (password.length === 0) return { level: 0, text: '' };
    if (strength <= 2) return { level: 1, text: '⚠️ Weak - Use longer password with letters and numbers' };
    if (strength <= 4) return { level: 2, text: '🟡 Medium - Good password, could be stronger' };
    return { level: 3, text: '✅ Strong - Excellent password!' };
}

function updateStrength() {
    const password = passwordInput.value;
    const result = checkPasswordStrength(password);
    
    strengthMeter.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
    
    if (result.level === 1) {
        strengthMeter.classList.add('strength-weak');
        strengthText.textContent = result.text;
        strengthText.style.color = '#dc2626';
    } else if (result.level === 2) {
        strengthMeter.classList.add('strength-medium');
        strengthText.textContent = result.text;
        strengthText.style.color = '#f59e0b';
    } else if (result.level === 3) {
        strengthMeter.classList.add('strength-strong');
        strengthText.textContent = result.text;
        strengthText.style.color = '#10b981';
    } else {
        strengthText.textContent = '';
    }
    
    checkMatch();
}

function checkMatch() {
    const password = passwordInput.value;
    const confirm = confirmInput.value;
    
    if (confirm.length === 0) {
        matchMessage.textContent = '';
        submitBtn.disabled = false;
        return;
    }
    
    if (password === confirm) {
        matchMessage.textContent = '✓ Passwords match';
        matchMessage.style.color = '#10b981';
        submitBtn.disabled = false;
    } else {
        matchMessage.textContent = '✗ Passwords do not match';
        matchMessage.style.color = '#dc2626';
        submitBtn.disabled = true;
    }
}

passwordInput.addEventListener('input', updateStrength);
confirmInput.addEventListener('input', checkMatch);
</script>

</body>
</html>
<?php include '../includes/footer2.php'; ?>