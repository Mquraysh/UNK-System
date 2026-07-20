<?php
// customer/settings/index.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer data
$customer_sql = "SELECT * FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

// Flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s ease;
        }
        
        .page-header {
            margin-bottom: 25px;
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
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        .settings-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .settings-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.15);
            border-color: #e67e22;
        }
        
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .card-header i {
            font-size: 32px;
            color: #e67e22;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        .card-body {
            padding: 20px 24px;
        }
        .card-body p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .card-footer {
            padding: 15px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }
        .card-footer span {
            color: #e67e22;
            font-weight: 500;
            font-size: 13px;
        }
        
        /* Delete Account Card Special Style */
        .settings-card.danger-card {
            border-color: #fee2e2;
        }
        .danger-card .card-header {
            background: #fef2f2;
        }
        .danger-card .card-header i {
            color: #e74c3c;
        }
        .danger-card .card-header h3 {
            color: #e74c3c;
        }
        .danger-card .card-footer {
            background: #fef2f2;
        }
        .danger-card .card-footer span {
            color: #e74c3c;
        }
        
        @media (max-width: 1024px) {
            .customer-content {
                margin-left: 0;
                padding: 20px;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .customer-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Account Settings</h1>
        <p>Manage your account preferences and information</p>
    </div>
    
    <?php if(!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Settings Grid -->
    <div class="settings-grid">
        <!-- Profile Settings -->
        <a href="profile.php" class="settings-card">
            <div class="card-header">
                <i class="fas fa-user-circle"></i>
                <h3>Profile Information</h3>
            </div>
            <div class="card-body">
                <p>Update your personal information including name, phone number, and city.</p>
            </div>
            <div class="card-footer">
                <span>Edit Profile <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
        
        <!-- Change Password -->
        <a href="change-password.php" class="settings-card">
            <div class="card-header">
                <i class="fas fa-lock"></i>
                <h3>Change Password</h3>
            </div>
            <div class="card-body">
                <p>Keep your account secure by updating your password regularly.</p>
            </div>
            <div class="card-footer">
                <span>Update Password <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
        
        <!-- Address Management -->
        <a href="address.php" class="settings-card">
            <div class="card-header">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Delivery Address</h3>
            </div>
            <div class="card-body">
                <p>Manage your delivery addresses for faster checkout.</p>
            </div>
            <div class="card-footer">
                <span>Manage Addresses <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
        
        <!-- Delete Account -->
        <a href="delete-account.php" class="settings-card danger-card">
            <div class="card-header">
                <i class="fas fa-trash-alt"></i>
                <h3>Delete Account</h3>
            </div>
            <div class="card-body">
                <p>Permanently delete your account and all associated data.</p>
            </div>
            <div class="card-footer">
                <span>Delete Account <i class="fas fa-arrow-right"></i></span>
            </div>
        </a>
    </div>
</div>
</body>
</html>