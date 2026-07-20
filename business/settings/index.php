<?php
// business/settings/index.php 
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

include '../includes/business_sidebar.php';
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
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; margin-top: 5px; }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .settings-card:hover {
            transform: translateY(-5px);
            border-color: #e67e22;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .card-header {
            padding: 20px 24px;
            background: linear-gradient(135deg, #f8fafc, #ffffff);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .card-icon {
            width: 50px;
            height: 50px;
            background: rgba(230,126,34,0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-icon i { font-size: 24px; color: #e67e22; }
        .card-header h3 { font-size: 18px; font-weight: 600; color: #1e293b; margin: 0; }
        .card-header p { font-size: 13px; color: #64748b; margin: 5px 0 0; }
        .card-body { padding: 20px 24px; }
        .card-body p { color: #475569; font-size: 13px; line-height: 1.5; }
        .card-footer { padding: 15px 24px 20px; border-top: 1px solid #e2e8f0; }
        .arrow-link { color: #e67e22; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } .settings-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> Business Settings</h1>
        <p>Manage your business preferences and configurations</p>
    </div>
    
    <div class="settings-grid">
        <!-- General Settings -->
        <a href="general.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-store"></i></div>
                <div><h3>General Settings</h3><p>Business information & logo</p></div>
            </div>
            <div class="card-body"><p>Update your business name, location, description, and company logo.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
        
        <!-- Business Hours -->
        <a href="hours.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <div><h3>Business Hours</h3><p>Operating schedule</p></div>
            </div>
            <div class="card-body"><p>Set your daily operating hours for customer reference.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
        
        <!-- Notifications -->
        <a href="notifications.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-bell"></i></div>
                <div><h3>Notifications</h3><p>Alert preferences</p></div>
            </div>
            <div class="card-body"><p>Manage email, SMS, and order alert preferences.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
        
        <!-- Payment Settings -->
        <a href="payment.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-credit-card"></i></div>
                <div><h3>Payment Settings</h3><p>Payment methods</p></div>
            </div>
            <div class="card-body"><p>Select which payment methods you accept from customers.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
        
        <!-- Security -->
        <a href="security.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
                <div><h3>Security</h3><p>Password & account protection</p></div>
            </div>
            <div class="card-body"><p>Change your password and manage account security.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
        
        <!-- Export Data -->
        <a href="export.php" class="settings-card">
            <div class="card-header">
                <div class="card-icon"><i class="fas fa-database"></i></div>
                <div><h3>Export Data</h3><p>Backup your information</p></div>
            </div>
            <div class="card-body"><p>Download your products, orders, and customer data.</p></div>
            <div class="card-footer"><span class="arrow-link">Configure <i class="fas fa-arrow-right"></i></span></div>
        </a>
    </div>
</div>
</body>
</html>