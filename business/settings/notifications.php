<?php
// business/settings/notifications.php - NOTIFICATION SETTINGS
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
$business_id = $business['business_id'];

if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

$settings_result = mysqli_query($conn, "SELECT notification_settings FROM business_settings WHERE business_id='$business_id'");
$settings = mysqli_fetch_assoc($settings_result);
$notification_settings = json_decode($settings['notification_settings'] ?? '{}', true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $notifications_json = json_encode([
        'email' => isset($_POST['email_notifications']) ? 1 : 0,
        'sms' => isset($_POST['sms_notifications']) ? 1 : 0,
        'orders' => isset($_POST['order_alerts']) ? 1 : 0,
        'low_stock' => isset($_POST['low_stock_alerts']) ? 1 : 0
    ]);
    mysqli_query($conn, "INSERT INTO business_settings (business_id, notification_settings) VALUES ('$business_id', '$notifications_json') ON DUPLICATE KEY UPDATE notification_settings='$notifications_json'");
    $_SESSION['flash_message'] = "Notification preferences updated!";
    $_SESSION['flash_type'] = "success";
    header("Location: notifications.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; max-width: 1200px; margin: 0 auto; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        .checkbox-group { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 12px; background: #f8fafc; border-radius: 12px; }
        .checkbox-group input { width: 20px; height: 20px; cursor: pointer; accent-color: #e67e22; }
        .checkbox-group label { font-size: 14px; font-weight: 500; color: #1e293b; cursor: pointer; }
        .checkbox-group small { font-size: 11px; color: #64748b; display: block; margin-top: 2px; }
        .btn-save { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; margin-top: 20px; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { .page-header { flex-direction: column; } .btn-save { width: 100%; justify-content: center; } .card-body { padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div><h1><i class="fas fa-bell"></i> Notification Settings</h1><p>Manage your alert preferences</p></div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <?php if (isset($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>"><i class="fas fa-check-circle"></i><?php echo $flash_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-envelope"></i> Notification Preferences</h3></div>
        <div class="card-body">
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" name="email_notifications" id="email" <?php echo ($notification_settings['email'] ?? true) ? 'checked' : ''; ?>>
                    <div><label for="email">Email Notifications</label><small>Receive updates via email</small></div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="sms_notifications" id="sms" <?php echo ($notification_settings['sms'] ?? false) ? 'checked' : ''; ?>>
                    <div><label for="sms">SMS Notifications</label><small>Get alerts on your phone</small></div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="order_alerts" id="orders" <?php echo ($notification_settings['orders'] ?? true) ? 'checked' : ''; ?>>
                    <div><label for="orders">New Order Alerts</label><small>Get notified when customers place orders</small></div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="low_stock_alerts" id="lowstock" <?php echo ($notification_settings['low_stock'] ?? true) ? 'checked' : ''; ?>>
                    <div><label for="lowstock">Low Stock Alerts</label><small>Get notified when products are running low</small></div>
                </div>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Preferences</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>