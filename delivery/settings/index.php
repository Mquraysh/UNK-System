<?php
// delivery/settings/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch agent data using prepared statement
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

// Fetch user data (email, phone, etc.)
$user_sql = "SELECT email, phone, full_name FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Flash message handling
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Settings | Delivery Partner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .delivery-content {
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
            background: #f5f7fa;
            transition: all 0.2s ease;
        }
        .page-header {
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 30px;
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
            font-size: 32px;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }
        .alert {
            padding: 14px 20px;
            border-radius: 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .alert-success {
            background: #e0f2e9;
            color: #0a5c3e;
            border-left-color: #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
        }
        .settings-card {
            background: white;
            border-radius: 28px;
            border: 1px solid #eef2f8;
            overflow: hidden;
            transition: all 0.25s;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
            border-color: rgba(230,126,34,0.3);
        }
        .card-header {
            padding: 22px 28px;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .card-header i {
            font-size: 32px;
            color: #e67e22;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        .card-body {
            padding: 22px 28px;
        }
        .card-body p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }
        .card-footer {
            padding: 16px 28px;
            background: #fafcff;
            border-top: 1px solid #f0f2f5;
            text-align: right;
        }
        .card-footer span {
            color: #e67e22;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .danger-card {
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
        @media (max-width: 1100px) {
            .delivery-content {
                margin-left: 0;
                padding: 24px;
            }
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .delivery-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Account Settings</h1>
        <p>Manage your profile, security, and preferences</p>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Settings Cards Grid (No Profile Card) -->
    <div class="settings-grid">
        <a href="profile.php" class="settings-card">
            <div class="card-header"><i class="fas fa-user-edit"></i><h3>Edit Profile</h3></div>
            <div class="card-body"><p>Update your personal information, vehicle details, and contact info.</p></div>
            <div class="card-footer"><span>Update Profile <i class="fas fa-arrow-right"></i></span></div>
        </a>

        <a href="change-password.php" class="settings-card">
            <div class="card-header"><i class="fas fa-lock"></i><h3>Change Password</h3></div>
            <div class="card-body"><p>Keep your account secure by changing your password regularly.</p></div>
            <div class="card-footer"><span>Update Password <i class="fas fa-arrow-right"></i></span></div>
        </a>

        <a href="vehicle.php" class="settings-card">
            <div class="card-header"><i class="fas fa-motorcycle"></i><h3>Vehicle Information</h3></div>
            <div class="card-body"><p>Update your vehicle type, registration number, and license details.</p></div>
            <div class="card-footer"><span>Update Vehicle <i class="fas fa-arrow-right"></i></span></div>
        </a>

        <a href="../ratings/my-rating.php" class="settings-card">
            <div class="card-header"><i class="fas fa-star"></i><h3>My Ratings</h3></div>
            <div class="card-body"><p>View your customer ratings, feedback, and performance score.</p></div>
            <div class="card-footer"><span>View Ratings <i class="fas fa-arrow-right"></i></span></div>
        </a>

        <a href="account.php" class="settings-card danger-card">
            <div class="card-header"><i class="fas fa-user-shield"></i><h3>Account Management</h3></div>
            <div class="card-body"><p>Deactivate, reactivate, or permanently delete your account.</p></div>
            <div class="card-footer"><span>Manage Account <i class="fas fa-arrow-right"></i></span></div>
        </a>
    </div>
</div>
</body>
</html>