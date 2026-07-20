<?php
// delivery/settings/account.php - ACCOUNT MANAGEMENT (UPDATED with prepared statements)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current data using prepared statements
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

$user_sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

$error = '';
$success = '';

// Handle Deactivation
if (isset($_POST['deactivate_account'])) {
    $password = $_POST['password'] ?? '';
    
    $check_sql = "SELECT password_hash FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($stmt);
    
    if (!$user_data || !password_verify($password, $user_data['password_hash'])) {
        $error = "Incorrect password. Please try again.";
    } else {
        $update_user = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_user);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $update_agent = "UPDATE delivery_agents SET status = 'inactive', is_available = 0 WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_agent);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $_SESSION['flash_message'] = "Your account has been deactivated. You can reactivate anytime.";
        $_SESSION['flash_type'] = "success";
        header("Location: ../login.php");
        exit();
    }
}

// Handle Reactivation
if (isset($_POST['reactivate_account'])) {
    $password = $_POST['password'] ?? '';
    
    $check_sql = "SELECT password_hash FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($stmt);
    
    if (!$user_data || !password_verify($password, $user_data['password_hash'])) {
        $error = "Incorrect password. Please try again.";
    } else {
        $update_user = "UPDATE users SET status = 'active' WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_user);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $update_agent = "UPDATE delivery_agents SET status = 'active' WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_agent);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        $_SESSION['flash_message'] = "Your account has been reactivated!";
        $_SESSION['flash_type'] = "success";
        header("Location: ../dashboard.php");
        exit();
    }
}

// Handle Permanent Deletion
if (isset($_POST['delete_account'])) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_delete'] ?? '';
    
    $check_sql = "SELECT password_hash FROM users WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $check_result = mysqli_stmt_get_result($stmt);
    $user_data = mysqli_fetch_assoc($check_result);
    mysqli_stmt_close($stmt);
    
    if (!$user_data || !password_verify($password, $user_data['password_hash'])) {
        $error = "Incorrect password. Please try again.";
    } elseif ($confirm !== 'DELETE') {
        $error = 'Type "DELETE" to confirm permanent deletion.';
    } else {
        mysqli_begin_transaction($conn);
        
        // Delete related records
        $del_sqls = [
            "DELETE FROM deliveries WHERE agent_id = ?",
            "DELETE FROM delivery_earnings WHERE agent_id = ?",
            "DELETE FROM delivery_ratings WHERE agent_id = ?",
            "DELETE FROM delivery_agents WHERE user_id = ?",
            "DELETE FROM users WHERE user_id = ?"
        ];
        $agent_id = $agent['agent_id'];
        foreach ($del_sqls as $sql) {
            $stmt = mysqli_prepare($conn, $sql);
            if (strpos($sql, 'agent_id') !== false) {
                mysqli_stmt_bind_param($stmt, "i", $agent_id);
            } else {
                mysqli_stmt_bind_param($stmt, "i", $user_id);
            }
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        mysqli_commit($conn);
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Account Management - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; line-height: 1.5; }
        .delivery-content {
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
            background: #f5f7fa;
            transition: all 0.2s;
        }
        .page-header {
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            background: none;
            color: #e67e22;
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .card {
            background: white;
            border-radius: 28px;
            border: 1px solid #eef2f8;
            overflow: hidden;
            margin-bottom: 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .card-header {
            padding: 20px 28px;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .card-header i {
            font-size: 24px;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }
        .card-body {
            padding: 28px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
        }
        .badge-active {
            background: #d1fae5;
            color: #059669;
        }
        .badge-inactive {
            background: #fef3c7;
            color: #d97706;
        }
        .info-box, .warning-box, .danger-box {
            padding: 16px 20px;
            border-radius: 20px;
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
        }
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
        }
        .warning-box {
            background: #fffbeb;
            border-left: 4px solid #f39c12;
        }
        .danger-box {
            background: #fef2f2;
            border-left: 4px solid #e74c3c;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            width: 100%;
            font-size: 14px;
            transition: all 0.2s;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-warning:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #27ae60;
            color: white;
        }
        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        .alert {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid #ef4444;
            background: #fee2e2;
            color: #991b1b;
        }
        hr {
            margin: 24px 0;
            border: none;
            border-top: 1px solid #e2e8f0;
        }
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 24px;
            }
        }
        @media (max-width: 768px) {
            .delivery-content {
                padding: 20px;
            }
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <h1><i class="fas fa-user-shield"></i> Account Management</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <!-- Current Status Card -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-circle" style="color: <?php echo $user['status'] == 'active' ? '#27ae60' : '#e74c3c'; ?>"></i>
            <h3>Account Status</h3>
        </div>
        <div class="card-body">
            <div style="text-align: center; padding: 10px;">
                <span class="status-badge badge-<?php echo $user['status']; ?>">
                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'check-circle' : 'pause-circle'; ?>"></i>
                    <?php echo strtoupper($user['status']); ?>
                </span>
                <p style="margin-top: 16px; color: #64748b;">
                    <?php if($user['status'] == 'active'): ?>
                        ✅ Your account is active. You are receiving delivery requests.
                    <?php else: ?>
                        ⏸️ Your account is inactive. You are not receiving delivery requests.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Deactivate Section (Only for Active Accounts) -->
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
                • You can reactivate anytime<br>
                • Your delivery history and earnings are preserved
            </div>
            <?php if($error && isset($_POST['deactivate_account'])): ?>
                <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Deactivate your account? You can reactivate later.')">
                <div class="form-group">
                    <label>Enter your password to confirm</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="deactivate_account" class="btn btn-warning">
                    <i class="fas fa-pause-circle"></i> Deactivate Account
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reactivate Section (Only for Inactive Accounts) -->
    <?php if($user['status'] == 'inactive'): ?>
    <div class="card">
        <div class="card-header" style="background: #d1fae5;">
            <i class="fas fa-play-circle" style="color: #059669;"></i>
            <h3 style="color: #059669;">Reactivate Account</h3>
        </div>
        <div class="card-body">
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <strong>Ready to start delivering again?</strong><br>
                Click the button below to reactivate your account.
            </div>
            <?php if($error && isset($_POST['reactivate_account'])): ?>
                <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Enter your password to confirm</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="reactivate_account" class="btn btn-success">
                    <i class="fas fa-play-circle"></i> Reactivate Account
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <hr>

    <!-- Delete Account Section (Permanent) -->
    <div class="card" style="border-color: #fee2e2;">
        <div class="card-header" style="background: #fef2f2;">
            <i class="fas fa-trash-alt" style="color: #e74c3c;"></i>
            <h3 style="color: #e74c3c;">Delete Account (Permanent)</h3>
        </div>
        <div class="card-body">
            <div class="danger-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>WARNING: This action cannot be undone!</strong><br><br>
                Deleting your account will permanently remove:<br>
                • All your personal information<br>
                • Delivery history and earnings records<br>
                • Ratings and reviews from customers<br>
                • Vehicle information and documents<br><br>
                <strong style="color: #e74c3c;">This action is irreversible. All data will be lost forever!</strong>
            </div>
            <?php if($error && isset($_POST['delete_account'])): ?>
                <div class="alert"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
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