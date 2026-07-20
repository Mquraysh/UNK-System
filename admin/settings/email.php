<?php
// admin/settings/email.php - Email Settings (Database Version)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Email Settings';

// ============================================================
// CREATE TABLES IF NOT EXISTS
// ============================================================
function createEmailTables($conn) {
    // Settings table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `settings` (
        `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT DEFAULT NULL,
        `setting_group` VARCHAR(50) DEFAULT 'general',
        `setting_type` ENUM('text','textarea','number','boolean','json') DEFAULT 'text',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_id`),
        INDEX `idx_setting_key` (`setting_key`),
        INDEX `idx_setting_group` (`setting_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Email templates table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `email_templates` (
        `template_id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_key` VARCHAR(100) NOT NULL UNIQUE,
        `template_name` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body` TEXT NOT NULL,
        `description` VARCHAR(500) DEFAULT NULL,
        `variables` TEXT DEFAULT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`template_id`),
        INDEX `idx_template_key` (`template_key`),
        INDEX `idx_is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Email logs table
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `email_logs` (
        `log_id` INT(11) NOT NULL AUTO_INCREMENT,
        `template_key` VARCHAR(100) DEFAULT NULL,
        `recipient` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body` TEXT NOT NULL,
        `status` ENUM('sent','failed','pending') DEFAULT 'sent',
        `error_message` TEXT DEFAULT NULL,
        `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`log_id`),
        INDEX `idx_recipient` (`recipient`),
        INDEX `idx_status` (`status`),
        INDEX `idx_sent_at` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert default settings if not exists
    $default_settings = [
        ['smtp_host', 'smtp.gmail.com', 'email', 'text'],
        ['smtp_port', '587', 'email', 'number'],
        ['smtp_encryption', 'tls', 'email', 'text'],
        ['from_email', 'noreply@unksystem.com', 'email', 'text'],
        ['from_name', 'UNK System', 'email', 'text'],
        ['smtp_username', '', 'email', 'text'],
        ['smtp_password', '', 'email', 'text'],
    ];

    foreach ($default_settings as $setting) {
        $check = mysqli_query($conn, "SELECT setting_id FROM settings WHERE setting_key = '{$setting[0]}'");
        if (mysqli_num_rows($check) == 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value, setting_group, setting_type) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $setting[0], $setting[1], $setting[2], $setting[3]);
            mysqli_stmt_execute($stmt);
        }
    }

    // Insert default email templates if not exists
    $default_templates = [
        ['order_confirmation', 'Order Confirmation', 'Order Confirmation #{order_id}', 
         "Dear {customer_name},\n\nThank you for your order #{order_id}.\n\nOrder Details:\n{order_details}\n\nTotal: {total_amount}\n\nWe will notify you when your order is shipped.\n\nThank you for shopping with us!",
         'customer_name, order_id, order_details, total_amount', 'Sent when a customer places an order'],
        
        ['order_shipped', 'Order Shipped', 'Your order #{order_id} has been shipped',
         "Dear {customer_name},\n\nYour order #{order_id} has been shipped!\n\nDelivery Agent: {agent_name}\nTracking: {tracking_link}\n\nEstimated Delivery: {estimated_delivery}",
         'customer_name, order_id, agent_name, tracking_link, estimated_delivery', 'Sent when an order is shipped'],
        
        ['order_delivered', 'Order Delivered', 'Your order #{order_id} has been delivered',
         "Dear {customer_name},\n\nYour order #{order_id} has been delivered successfully.\n\nWe hope you enjoy your purchase!\n\nPlease leave a review: {review_link}",
         'customer_name, order_id, review_link', 'Sent when an order is delivered'],
        
        ['password_reset', 'Password Reset', 'Reset your password',
         "Dear {user_name},\n\nYou requested to reset your password.\n\nClick the link below to reset your password:\n{reset_link}\n\nThis link will expire in 24 hours.\n\nIf you did not request this, please ignore this email.",
         'user_name, reset_link', 'Sent when a user requests password reset'],
        
        ['welcome_email', 'Welcome Email', 'Welcome to UNK System!',
         "Welcome to UNK System!\n\nDear {user_name},\n\nThank you for registering with us.\n\nYou can now start shopping and exploring our marketplace.\n\nVisit: {login_link}",
         'user_name, login_link', 'Sent when a new user registers'],
        
        ['business_approval', 'Business Approval', 'Your business has been approved!',
         "Dear {business_name},\n\nYour business has been approved!\n\nYou can now start listing products and selling on UNK System.\n\nLogin to your dashboard: {dashboard_link}",
         'business_name, dashboard_link', 'Sent when a business is approved'],
        
        ['delivery_assigned', 'Delivery Assigned', 'New delivery assigned to you',
         "Dear {agent_name},\n\nYou have been assigned a new delivery.\n\nOrder ID: {order_id}\nPickup: {pickup_address}\nDelivery: {delivery_address}\n\nPlease login to accept this delivery.",
         'agent_name, order_id, pickup_address, delivery_address', 'Sent when a delivery is assigned to an agent'],
    ];

    foreach ($default_templates as $template) {
        $check = mysqli_query($conn, "SELECT template_id FROM email_templates WHERE template_key = '{$template[0]}'");
        if (mysqli_num_rows($check) == 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO email_templates (template_key, template_name, subject, body, variables, description) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssss', $template[0], $template[1], $template[2], $template[3], $template[4], $template[5]);
            mysqli_stmt_execute($stmt);
        }
    }
}

// Create tables on page load
createEmailTables($conn);

// ============================================================
// GET SETTINGS FROM DATABASE
// ============================================================
function getSettings($conn, $group = 'email') {
    $settings = [];
    $sql = "SELECT setting_key, setting_value FROM settings WHERE setting_group = '$group'";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function getEmailTemplates($conn) {
    $templates = [];
    $sql = "SELECT template_key, subject, body, variables FROM email_templates WHERE is_active = 1";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $templates[$row['template_key']] = [
            'subject' => $row['subject'],
            'body' => $row['body'],
            'variables' => $row['variables']
        ];
    }
    return $templates;
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_smtp'])) {
        // Save SMTP settings to database
        $settings = [
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => $_POST['smtp_port'] ?? 587,
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'from_email' => $_POST['from_email'] ?? '',
            'from_name' => $_POST['from_name'] ?? 'UNK System',
        ];

        foreach ($settings as $key => $value) {
            $stmt = mysqli_prepare($conn, "UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            mysqli_stmt_bind_param($stmt, 'ss', $value, $key);
            mysqli_stmt_execute($stmt);
        }

        $message = 'SMTP settings saved successfully!';
        $message_type = 'success';
    }
    
    if (isset($_POST['send_test'])) {
        $to = $_POST['test_email'] ?? $_SESSION['email'] ?? '';
        $message = 'Test email would be sent to: ' . htmlspecialchars($to);
        $message_type = 'success';
    }
    
    if (isset($_POST['save_templates'])) {
        // Save email templates
        foreach ($_POST as $key => $value) {
            if (strpos($key, '_subject') !== false) {
                $template_key = str_replace('_subject', '', $key);
                $subject = $value;
                $body = $_POST[$template_key . '_body'] ?? '';
                
                $stmt = mysqli_prepare($conn, "UPDATE email_templates SET subject = ?, body = ? WHERE template_key = ?");
                mysqli_stmt_bind_param($stmt, 'sss', $subject, $body, $template_key);
                mysqli_stmt_execute($stmt);
            }
        }
        $message = 'Email templates saved successfully!';
        $message_type = 'success';
    }
}

// Load settings from database
$smtp_settings = getSettings($conn, 'email');
$email_templates = getEmailTemplates($conn);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Email Settings | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        
        .settings-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .settings-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .settings-content { padding: 15px; }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .btn-back {
            background: #64748b;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        .settings-card {
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
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 24px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #ef4444; }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        .help-text {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            display: block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover { background: #e2e8f0; }
        
        .template-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .template-tab {
            padding: 8px 16px;
            border-radius: 20px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            transition: 0.2s;
        }
        .template-tab.active {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .template-tab:hover:not(.active) {
            background: #e2e8f0;
        }
        
        .template-content {
            display: none;
        }
        .template-content.active {
            display: block;
        }
        
        .variable-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 10px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px dashed #e2e8f0;
        }
        .variable-tag {
            background: #e2e8f0;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            font-family: monospace;
        }
        
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .card-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="settings-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-envelope"></i> Email Settings</h1>
            <p>Configure SMTP settings and email templates</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- SMTP Settings -->
    <div class="settings-card">
        <div class="card-header">
            <h3><i class="fas fa-server"></i> SMTP Configuration</h3>
            <span style="font-size:12px; color:#64748b;">Configure mail server settings</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host <span class="required">*</span></label>
                        <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['smtp_host'] ?? ''); ?>" required>
                        <small class="help-text">e.g., smtp.gmail.com, smtp.mailtrap.io</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Port <span class="required">*</span></label>
                        <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['smtp_port'] ?? 587); ?>" required>
                        <small class="help-text">587 (TLS) or 465 (SSL)</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['smtp_username'] ?? ''); ?>">
                        <small class="help-text">Your email address or username</small>
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['smtp_password'] ?? ''); ?>">
                        <small class="help-text">App password or SMTP password</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="tls" <?php echo ($smtp_settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                            <option value="ssl" <?php echo ($smtp_settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($smtp_settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" name="from_email" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['from_email'] ?? ''); ?>">
                        <small class="help-text">Email address used as sender</small>
                    </div>
                </div>
                <div class="form-group">
                    <label>From Name</label>
                    <input type="text" name="from_name" class="form-control" value="<?php echo htmlspecialchars($smtp_settings['from_name'] ?? 'UNK System'); ?>">
                    <small class="help-text">Display name for emails</small>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <button type="submit" name="save_smtp" class="btn btn-primary"><i class="fas fa-save"></i> Save SMTP Settings</button>
                    <button type="submit" name="send_test" class="btn btn-success"><i class="fas fa-paper-plane"></i> Send Test Email</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Templates -->
    <div class="settings-card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Email Templates</h3>
            <span style="font-size:12px; color:#64748b;">Customize email content</span>
        </div>
        <div class="card-body">
            <div class="template-tabs">
                <button class="template-tab active" data-template="order_confirmation">Order Confirmation</button>
                <button class="template-tab" data-template="order_shipped">Order Shipped</button>
                <button class="template-tab" data-template="order_delivered">Order Delivered</button>
                <button class="template-tab" data-template="password_reset">Password Reset</button>
                <button class="template-tab" data-template="welcome_email">Welcome Email</button>
                <button class="template-tab" data-template="business_approval">Business Approval</button>
                <button class="template-tab" data-template="delivery_assigned">Delivery Assigned</button>
            </div>

            <form method="POST">
                <?php foreach ($email_templates as $key => $template): ?>
                <div class="template-content <?php echo $key === 'order_confirmation' ? 'active' : ''; ?>" id="template-<?php echo $key; ?>">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" name="<?php echo $key; ?>_subject" class="form-control" value="<?php echo htmlspecialchars($template['subject'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Body</label>
                        <textarea name="<?php echo $key; ?>_body" class="form-control" rows="8"><?php echo htmlspecialchars($template['body'] ?? ''); ?></textarea>
                    </div>
                    <div class="variable-list">
                        <span style="font-size:11px; font-weight:600; color:#64748b; margin-right:8px;">Available Variables:</span>
                        <?php 
                        $vars = isset($template['variables']) ? explode(', ', $template['variables']) : [];
                        foreach ($vars as $var): 
                        ?>
                            <span class="variable-tag">{<?php echo trim($var); ?>}</span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div style="margin-top:20px;">
                    <button type="submit" name="save_templates" class="btn btn-primary"><i class="fas fa-save"></i> Save Templates</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Template tabs
document.querySelectorAll('.template-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.template-tab').forEach(function(t) {
            t.classList.remove('active');
        });
        this.classList.add('active');
        
        document.querySelectorAll('.template-content').forEach(function(content) {
            content.classList.remove('active');
        });
        
        var templateId = this.getAttribute('data-template');
        var content = document.getElementById('template-' + templateId);
        if (content) {
            content.classList.add('active');
        }
    });
});
</script>

</body>
</html>