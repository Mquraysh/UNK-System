<?php
// customer/settings/delete-account.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_delete'];
    
    // Get current password hash
    $user_sql = "SELECT password_hash FROM users WHERE user_id = '$user_id'";
    $user_result = mysqli_query($conn, $user_sql);
    $user = mysqli_fetch_assoc($user_result);
    
    if(!password_verify($password, $user['password_hash'])) {
        $error = "Incorrect password";
    } elseif($confirm !== 'DELETE') {
        $error = 'Type "DELETE" to confirm account deletion';
    } else {
        // Delete customer record
        $delete_customer = "DELETE FROM customers WHERE user_id = '$user_id'";
        mysqli_query($conn, $delete_customer);
        
        // Delete user record
        $delete_user = "DELETE FROM users WHERE user_id = '$user_id'";
        mysqli_query($conn, $delete_user);
        
        // Destroy session
        session_destroy();
        
        header("Location: ../../index.php?account_deleted=1");
        exit();
    }
}

include '../includes/customer_sidebar.php';
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
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
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
            color: #e74c3c;
            display: flex;
            align-items: center;
            gap: 12px;
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
            border: 1px solid #fee2e2;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #fef2f2;
            border-bottom: 1px solid #fee2e2;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #e74c3c;
        }
        .card-body {
            padding: 28px;
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
            border-color: #e74c3c;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-delete:hover {
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
        
        @media (max-width: 1024px) {
            .customer-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-trash-alt"></i> Delete Account</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Permanently Delete Account</h3>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="warning-box">
                <i class="fas fa-info-circle"></i>
                <strong>Warning: This action cannot be undone!</strong><br>
                Deleting your account will permanently remove:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>All your personal information</li>
                    <li>Order history</li>
                    <li>Saved addresses</li>
                    <li>Reviews and ratings</li>
                    <li>Wishlist items</li>
                </ul>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>Enter your password to confirm</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Type <strong style="color: #e74c3c;">DELETE</strong> to confirm</label>
                    <input type="text" name="confirm_delete" class="form-control" placeholder="DELETE" required>
                </div>
                
                <button type="submit" class="btn-delete" onclick="return confirm('Are you absolutely sure? This action cannot be undone!')">
                    <i class="fas fa-trash-alt"></i> Permanently Delete My Account
                </button>
            </form>
        </div>
    </div>
</div>
</body>
</html>