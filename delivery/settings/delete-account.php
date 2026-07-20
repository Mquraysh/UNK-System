<?php
// delivery/settings/delete-account.php - DELETE & DEACTIVATE ACCOUNT
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current user data
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

$error = '';
$success = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle Deactivation
if (isset($_POST['deactivate_account'])) {
    $password = $_POST['password'];
    
    // Verify password
    $check_sql = "SELECT password_hash FROM users WHERE user_id = '$user_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $user_data = mysqli_fetch_assoc($check_result);
    
    if(!password_verify($password, $user_data['password_hash'])) {
        $error = "Incorrect password. Please try again.";
    } else {
        // Deactivate user account
        $update_user = "UPDATE users SET status = 'inactive' WHERE user_id = '$user_id'";
        mysqli_query($conn, $update_user);
        
        // Deactivate delivery agent
        $update_agent = "UPDATE delivery_agents SET status = 'inactive', is_available = 0 WHERE user_id = '$user_id'";
        mysqli_query($conn, $update_agent);
        
        // Destroy session
        session_destroy();
        
        header("Location: ../../index.php?account_deactivated=1");
        exit();
    }
}

// Handle Reactivation
if (isset($_GET['reactivate'])) {
    $update_user = "UPDATE users SET status = 'active' WHERE user_id = '$user_id'";
    mysqli_query($conn, $update_user);
    
    $update_agent = "UPDATE delivery_agents SET status = 'active' WHERE user_id = '$user_id'";
    mysqli_query($conn, $update_agent);
    
    $_SESSION['flash_message'] = "Your account has been reactivated! You can now login.";
    $_SESSION['flash_type'] = "success";
    header("Location: ../login.php");
    exit();
}

// Handle Permanent Deletion
if (isset($_POST['delete_account'])) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_delete'];
    
    // Verify password
    $check_sql = "SELECT password_hash FROM users WHERE user_id = '$user_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $user_data = mysqli_fetch_assoc($check_result);
    
    if(!password_verify($password, $user_data['password_hash'])) {
        $error = "Incorrect password. Please try again.";
    } elseif($confirm !== 'DELETE') {
        $error = 'Type "DELETE" to confirm permanent deletion';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // Delete related records
        mysqli_query($conn, "DELETE FROM deliveries WHERE agent_id = '{$agent['agent_id']}'");
        mysqli_query($conn, "DELETE FROM delivery_earnings WHERE agent_id = '{$agent['agent_id']}'");
        mysqli_query($conn, "DELETE FROM delivery_ratings WHERE agent_id = '{$agent['agent_id']}'");
        mysqli_query($conn, "DELETE FROM delivery_agents WHERE user_id = '$user_id'");
        mysqli_query($conn, "DELETE FROM users WHERE user_id = '$user_id'");
        
        mysqli_commit($conn);
        
        // Destroy session
        session_destroy();
        
        header("Location: ../../index.php?account_deleted=1");
        exit();
    }
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
        .delivery-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s ease;
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
            color: #e74c3c;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-back:hover {
            background: #1a252f;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header i {
            font-size: 24px;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        .card-body {
            padding: 24px;
        }
        
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f39c12;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .warning-box i {
            color: #f39c12;
            margin-right: 10px;
        }
        
        .danger-box {
            background: #fee2e2;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .danger-box i {
            color: #e74c3c;
            margin-right: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
            font-size: 13px;
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
        
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background: #e67e22;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d1fae5;
            color: #059669;
        }
        .status-inactive {
            background: #fef3c7;
            color: #d97706;
        }
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
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
        <div>
            <h1><i class="fas fa-user-shield"></i> Account Management</h1>
            <p>Manage your account status and data</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <!-- Current Status -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-circle" style="color: <?php echo $user['status'] == 'active' ? '#27ae60' : '#e74c3c'; ?>"></i>
            <h3>Account Status</h3>
        </div>
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <span class="status-badge status-<?php echo $user['status']; ?>">
                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'check-circle' : 'clock'; ?>"></i>
                    <?php echo ucfirst($user['status']); ?> Account
                </span>
                <span style="color: #64748b; font-size: 13px;">
                    <?php if($user['status'] == 'active'): ?>
                        Your account is active and you can accept deliveries
                    <?php else: ?>
                        Your account is deactivated. You cannot accept deliveries
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Deactivate Account Section (Only for Active Accounts) -->
    <?php if($user['status'] == 'active'): ?>
    <div class="card">
        <div class="card-header" style="background: #fffbeb;">
            <i class="fas fa-pause-circle" style="color: #f39c12;"></i>
            <h3 style="color: #d97706;">Deactivate Account</h3>
        </div>
        <div class="card-body">
            <div class="warning-box">
                <i class="fas fa-info-circle"></i>
                <strong>What happens when you deactivate?</strong><br><br>
                • You will stop receiving delivery requests<br>
                • Your profile will be hidden from businesses<br>
                • You can reactivate anytime by logging in<br>
                • Your delivery history and earnings are preserved
            </div>
            
            <?php if($error && $_POST['deactivate_account']): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account? You can reactivate by logging in again.')">
                <div class="form-group">
                    <label>Enter your password to confirm deactivation</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="deactivate_account" class="btn btn-warning">
                    <i class="fas fa-pause-circle"></i> Deactivate Account
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Reactivate Account Section (Only for Inactive Accounts) -->
    <?php if($user['status'] == 'inactive'): ?>
    <div class="card">
        <div class="card-header" style="background: #d1fae5;">
            <i class="fas fa-play-circle" style="color: #059669;"></i>
            <h3 style="color: #059669;">Reactivate Account</h3>
        </div>
        <div class="card-body">
            <div class="warning-box" style="background: #d1fae5; border-left-color: #059669;">
                <i class="fas fa-info-circle"></i>
                <strong>Reactivate your account to start delivering again!</strong><br><br>
                • Click the button below to reactivate your account<br>
                • You will immediately start receiving delivery requests<br>
                • Your previous delivery history and ratings remain
            </div>
            
            <a href="?reactivate=1" class="btn btn-warning" style="display: block; text-align: center; text-decoration: none; background: #27ae60;" onclick="return confirm('Reactivate your account? You will start receiving delivery requests again.')">
                <i class="fas fa-play-circle"></i> Reactivate Account
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <hr>
    
    <!-- Delete Account Section (Permanent) -->
    <div class="card" style="border-color: #fee2e2;">
        <div class="card-header" style="background: #fef2f2;">
            <i class="fas fa-trash-alt" style="color: #e74c3c;"></i>
            <h3 style="color: #e74c3c;">Permanently Delete Account</h3>
        </div>
        <div class="card-body">
            <div class="danger-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning: This action cannot be undone!</strong><br><br>
                Deleting your account will permanently remove:<br>
                • All your personal information<br>
                • Delivery history and earnings records<br>
                • Ratings and reviews from customers<br>
                • Vehicle information and documents<br><br>
                <strong>This action is irreversible. All data will be lost forever!</strong>
            </div>
            
            <?php if($error && $_POST['delete_account']): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return confirm('WARNING: This will permanently delete ALL your data! This action cannot be undone. Click OK to proceed.')">
                <div class="form-group">
                    <label>Enter your password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Type <strong style="color: #e74c3c;">DELETE</strong> to confirm</label>
                    <input type="text" name="confirm_delete" class="form-control" placeholder="DELETE" required>
                </div>
                <button type="submit" name="delete_account" class="btn btn-danger">
                    <i class="fas fa-trash-alt"></i> Permanently Delete My Account
                </button>
            </form>
        </div>
    </div>
    
</div>
</body>
</html>