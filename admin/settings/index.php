<?php
// admin/settings/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$admin_email = $_SESSION['email'] ?? 'admin@unksystem.com';

// Get delivery fee stats
$fee_count = 0;
$active_fees = 0;
$fee_query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active FROM delivery_rates";
$fee_result = mysqli_query($conn, $fee_query);
if ($fee_result) {
    $fee_data = mysqli_fetch_assoc($fee_result);
    $fee_count = $fee_data['total'] ?? 0;
    $active_fees = $fee_data['active'] ?? 0;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Settings | UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 0.9rem; }
        }
        
        /* Welcome section */
        .welcome-section {
            background: transparent;
            padding: 0 0 1rem 0;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .welcome-text h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }
        .welcome-text h1 span { color: #e67e22; }
        .welcome-text p { color: #64748b; font-size: 0.85rem; }
        .date-badge {
            background: #f8fafc;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        
        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-item {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #eef2f8;
            transition: all 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: #e67e22;
        }
        .stat-item .number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-item .label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-item .icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        .settings-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            padding: 1.5rem;
            text-decoration: none;
            color: #1e293b;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e67e22, #f39c12);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .settings-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.1);
            border-color: rgba(230,126,34,0.3);
        }
        .settings-card:hover::before {
            transform: scaleX(1);
        }
        .settings-card .badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #e67e22;
            color: white;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 2rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .settings-card .badge.green {
            background: #10b981;
        }
        .settings-card .badge.blue {
            background: #3b82f6;
        }
        .settings-card .badge.purple {
            background: #8b5cf6;
        }
        .settings-icon {
            width: 56px;
            height: 56px;
            background: rgba(230,126,34,0.08);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        .settings-card:hover .settings-icon {
            background: rgba(230,126,34,0.15);
            transform: scale(1.05);
        }
        .settings-icon i {
            font-size: 26px;
            color: #e67e22;
        }
        .settings-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            color: #0f172a;
        }
        .settings-card p {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 1rem;
            flex: 1;
        }
        .card-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #e67e22;
            margin-top: 0.5rem;
        }
        .card-footer i {
            transition: transform 0.2s;
        }
        .settings-card:hover .card-footer i {
            transform: translateX(4px);
        }
        
        .delivery-fee-highlight {
            border-left: 3px solid #e67e22;
            background: linear-gradient(135deg, #ffffff, #fffaf5);
        }
        .delivery-fee-highlight .settings-icon {
            background: rgba(230,126,34,0.15);
        }
        
        @media (max-width: 768px) {
            .welcome-section { flex-direction: column; align-items: flex-start; }
            .date-badge { white-space: normal; }
            .stats-bar { grid-template-columns: repeat(2, 1fr); }
            .settings-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats-bar { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-text">
            <h1>Settings <span>Dashboard</span></h1>
            <p>Manage your system configuration and account preferences</p>
        </div>
        <div class="date-badge">
            <i class="fas fa-calendar-alt"></i> <?= date('l, F d, Y') ?>
        </div>
    </div>

    <!-- Settings Grid -->
    <div class="settings-grid">
        <!-- General Settings -->
        <a href="general.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-globe"></i></div>
            <h3>General Settings</h3>
            <p>System name, contact info, social links, and basic configuration</p>
            <div class="card-footer">
                <span>Configure</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- My Profile -->
        <a href="profile.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-user-circle"></i></div>
            <h3>My Profile</h3>
            <p>Update your name, email, phone, and profile picture</p>
            <div class="card-footer">
                <span>Update Profile</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- Change Password -->
        <a href="change-password.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-key"></i></div>
            <h3>Change Password</h3>
            <p>Secure your account with a new password</p>
            <div class="card-footer">
                <span>Change Password</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- Delivery Fee Management -->
        <a href="fees.php" class="settings-card delivery-fee-highlight">
            <span class="badge"><?php echo $active_fees; ?> Active</span>
            <div class="settings-icon"><i class="fas fa-money-bill-wave"></i></div>
            <h3>Delivery Fees</h3>
            <p>Manage distance-based delivery fee rates and pricing rules</p>
            <div class="card-footer">
                <span>Manage Fees</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- Database Management -->
        <a href="database.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-database"></i></div>
            <h3>Database Management</h3>
            <p>Backup, optimize, and manage database performance</p>
            <div class="card-footer">
                <span>Manage Database</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- System Settings -->
        <a href="system.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-server"></i></div>
            <h3>System Settings</h3>
            <p>Advanced system configuration and maintenance</p>
            <div class="card-footer">
                <span>Configure System</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- System Logs -->
        <a href="logs.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-file-alt"></i></div>
            <h3>System Logs</h3>
            <p>View system activity logs and audit trail</p>
            <div class="card-footer">
                <span>View Logs</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- Backup -->
        <a href="backup.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-archive"></i></div>
            <h3>Backup</h3>
            <p>Database backup and restore management</p>
            <div class="card-footer">
                <span>Manage Backup</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

        <!-- Email Settings -->
        <a href="email.php" class="settings-card">
            <div class="settings-icon"><i class="fas fa-envelope"></i></div>
            <h3>Email Settings</h3>
            <p>SMTP configuration and email templates</p>
            <div class="card-footer">
                <span>Configure</span> <i class="fas fa-arrow-right"></i>
            </div>
        </a>

    </div>
</div>
</body>
</html>