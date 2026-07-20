<?php
// business/settings/delete-account.php 
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $confirm_text = $_POST['confirm_text'];
    $password = $_POST['password'];
    
    $user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password_hash FROM users WHERE user_id='$user_id'"));
    
    if ($confirm_text !== 'DELETE') {
        $error = 'Please type "DELETE" to confirm permanent deletion';
    } elseif (!password_verify($password, $user_data['password_hash'])) {
        $error = 'Incorrect password!';
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Delete order items
            mysqli_query($conn, "DELETE oi FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE o.business_id = '{$business['business_id']}'");
            
            // Delete orders
            mysqli_query($conn, "DELETE FROM orders WHERE business_id = '{$business['business_id']}'");
            
            // Delete products
            mysqli_query($conn, "DELETE FROM products WHERE business_id = '{$business['business_id']}'");
            
            // Delete business
            mysqli_query($conn, "DELETE FROM businesses WHERE business_id = '{$business['business_id']}'");
            
            // Delete user
            mysqli_query($conn, "DELETE FROM users WHERE user_id = '$user_id'");
            
            mysqli_commit($conn);
            
            // Destroy session
            session_destroy();
            
            $_SESSION['delete_success'] = "Your account has been permanently deleted.";
            header("Location: ../index.php?deleted=1");
            exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to delete account. Please try again.";
        }
    }
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #dc2626; display: flex; align-items: center; gap: 12px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        
        .card { background: white; border-radius: 20px; border: 1px solid #fee2e2; overflow: hidden; max-width: 1200px; margin: 0 auto; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #fee2e2; background: #fef2f2; }
        .card-header h3 { font-size: 18px; font-weight: 600; color: #dc2626; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 28px; }
        
        .warning-box { background: #fee2e2; padding: 15px; border-radius: 12px; margin-bottom: 20px; }
        .warning-box ul { margin-left: 20px; margin-top: 10px; }
        .warning-box li { margin: 5px 0; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; }
        .form-control:focus { outline: none; border-color: #dc2626; }
        
        .btn-danger { background: #dc2626; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-danger:hover { background: #b91c1c; }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-trash-alt"></i> Permanently Delete Account</h1>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Warning: Irreversible Action!</h3>
        </div>
        <div class="card-body">
            <div class="warning-box">
                <strong>This will permanently delete:</strong>
                <ul>
                    <li>Your business profile and information</li>
                    <li>All your products and categories</li>
                    <li>All customer orders and order history</li>
                    <li>All product reviews and ratings</li>
                    <li>Your account and login credentials</li>
                </ul>
                <p style="margin-top: 10px;"><strong>This action CANNOT be undone!</strong></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Type <strong style="color:#dc2626;">DELETE</strong> to confirm:</label>
                    <input type="text" name="confirm_text" class="form-control" placeholder="DELETE" required>
                </div>
                <div class="form-group">
                    <label>Enter your password to confirm:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn-danger" onclick="return confirm('WARNING: This will permanently delete ALL your data! This action cannot be undone. Click OK to proceed.')">
                    <i class="fas fa-trash-alt"></i> Permanently Delete My Account
                </button>
            </form>
        </div>
    </div>
    
    <div style="margin-top: 20px; text-align: center;">
        <a href="security.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Security Settings</a>
    </div>
</div>
</body>
</html>