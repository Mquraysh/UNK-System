<?php
// admin/settings/system.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ====================== ENSURE SETTINGS TABLE HAS ALL NEEDED KEYS ======================
$system_keys = [
    'environment' => ['value' => 'production', 'type' => 'text', 'description' => 'Application environment (development/production)'],
    'debug_mode' => ['value' => '0', 'type' => 'boolean', 'description' => 'Show detailed error messages (only for development)'],
    'session_timeout_minutes' => ['value' => '60', 'type' => 'number', 'description' => 'User session timeout in minutes'],
    'max_upload_size_mb' => ['value' => '5', 'type' => 'number', 'description' => 'Maximum file upload size in megabytes'],
    'allowed_image_types' => ['value' => 'jpg,jpeg,png,gif,webp', 'type' => 'text', 'description' => 'Comma-separated list of allowed image extensions'],
    'cache_enabled' => ['value' => '1', 'type' => 'boolean', 'description' => 'Enable caching for better performance'],
    'backup_schedule' => ['value' => 'daily', 'type' => 'text', 'description' => 'Database backup frequency (daily, weekly, monthly, none)'],
    'backup_retention_days' => ['value' => '30', 'type' => 'number', 'description' => 'How many days to keep backups'],
    'smtp_host' => ['value' => '', 'type' => 'text', 'description' => 'SMTP server hostname'],
    'smtp_port' => ['value' => '587', 'type' => 'number', 'description' => 'SMTP port (587 for TLS, 465 for SSL)'],
    'smtp_encryption' => ['value' => 'tls', 'type' => 'text', 'description' => 'Encryption type (tls, ssl, none)'],
    'smtp_username' => ['value' => '', 'type' => 'text', 'description' => 'SMTP authentication username'],
    'smtp_password' => ['value' => '', 'type' => 'text', 'description' => 'SMTP authentication password (leave blank to keep current)'],
    'smtp_from_email' => ['value' => '', 'type' => 'text', 'description' => 'Sender email address'],
    'smtp_from_name' => ['value' => 'UNK System', 'type' => 'text', 'description' => 'Sender display name'],
    'mpesa_enabled' => ['value' => '0', 'type' => 'boolean', 'description' => 'Enable M‑Pesa payment gateway'],
    'mpesa_consumer_key' => ['value' => '', 'type' => 'text', 'description' => 'M‑Pesa API consumer key'],
    'mpesa_consumer_secret' => ['value' => '', 'type' => 'text', 'description' => 'M‑Pesa API consumer secret'],
    'mpesa_shortcode' => ['value' => '', 'type' => 'text', 'description' => 'M‑Pesa shortcode / till number'],
    'mpesa_passkey' => ['value' => '', 'type' => 'text', 'description' => 'M‑Pesa API passkey'],
    'sms_enabled' => ['value' => '0', 'type' => 'boolean', 'description' => 'Enable SMS notifications'],
    'sms_api_key' => ['value' => '', 'type' => 'text', 'description' => 'SMS gateway API key'],
    'sms_sender_id' => ['value' => '', 'type' => 'text', 'description' => 'SMS sender ID'],
    'google_maps_api_key' => ['value' => '', 'type' => 'text', 'description' => 'Google Maps JavaScript API key'],
    'recaptcha_site_key' => ['value' => '', 'type' => 'text', 'description' => 'reCAPTCHA v2 site key'],
    'recaptcha_secret_key' => ['value' => '', 'type' => 'text', 'description' => 'reCAPTCHA v2 secret key']
];

// Ensure all settings exist in the `settings` table
foreach ($system_keys as $key => $data) {
    $stmt = mysqli_prepare($conn, "SELECT setting_id FROM settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $exists = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
    mysqli_stmt_close($stmt);
    
    if (!$exists) {
        $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $key, $data['value'], $data['type'], $data['description']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Fetch current settings
$settings = [];
$stmt = mysqli_prepare($conn, "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('" . implode("','", array_keys($system_keys)) . "')");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
mysqli_stmt_close($stmt);

// Fill missing with defaults
foreach ($system_keys as $key => $data) {
    if (!isset($settings[$key])) $settings[$key] = $data['value'];
}

// ====================== HANDLE FORM SUBMISSION ======================
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_system'])) {
    // Prepare updates array
    $updates = [
        'environment' => $_POST['environment'] ?? 'production',
        'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
        'session_timeout_minutes' => (int)$_POST['session_timeout_minutes'],
        'max_upload_size_mb' => (float)$_POST['max_upload_size_mb'],
        'allowed_image_types' => trim($_POST['allowed_image_types']),
        'cache_enabled' => isset($_POST['cache_enabled']) ? 1 : 0,
        'backup_schedule' => $_POST['backup_schedule'],
        'backup_retention_days' => (int)$_POST['backup_retention_days'],
        'smtp_host' => trim($_POST['smtp_host']),
        'smtp_port' => (int)$_POST['smtp_port'],
        'smtp_encryption' => $_POST['smtp_encryption'],
        'smtp_username' => trim($_POST['smtp_username']),
        'smtp_from_email' => trim($_POST['smtp_from_email']),
        'smtp_from_name' => trim($_POST['smtp_from_name']),
        'mpesa_enabled' => isset($_POST['mpesa_enabled']) ? 1 : 0,
        'mpesa_consumer_key' => trim($_POST['mpesa_consumer_key']),
        'mpesa_consumer_secret' => trim($_POST['mpesa_consumer_secret']),
        'mpesa_shortcode' => trim($_POST['mpesa_shortcode']),
        'mpesa_passkey' => trim($_POST['mpesa_passkey']),
        'sms_enabled' => isset($_POST['sms_enabled']) ? 1 : 0,
        'sms_api_key' => trim($_POST['sms_api_key']),
        'sms_sender_id' => trim($_POST['sms_sender_id']),
        'google_maps_api_key' => trim($_POST['google_maps_api_key']),
        'recaptcha_site_key' => trim($_POST['recaptcha_site_key']),
        'recaptcha_secret_key' => trim($_POST['recaptcha_secret_key'])
    ];
    
    // Handle SMTP password separately: only update if a new value is provided
    if (!empty($_POST['smtp_password'])) {
        $updates['smtp_password'] = trim($_POST['smtp_password']);
    } else {
        $updates['smtp_password'] = $settings['smtp_password']; // keep old
    }
    
    $success = true;
    foreach ($updates as $key => $value) {
        $stmt = mysqli_prepare($conn, "UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $value, $key);
        if (!mysqli_stmt_execute($stmt)) $success = false;
        mysqli_stmt_close($stmt);
    }
    
    if ($success) {
        $message = "System settings updated successfully.";
        $message_type = "success";
        // Refresh local settings
        foreach ($updates as $key => $value) {
            $settings[$key] = $value;
        }
    } else {
        $message = "Failed to update some settings.";
        $message_type = "danger";
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>System Settings | UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }
        .alert-success { background: #e6f7ec; color: #0a5c3e; border-left-color: #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left-color: #ef4444; }
        .settings-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header i { color: #e67e22; }
        .card-header h3 { margin: 0; font-size: 1rem; }
        .card-body {
            padding: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .checkbox-group input {
            width: 18px;
            height: 18px;
            accent-color: #e67e22;
        }
        .btn-save {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 0.5rem;
        }
        .btn-save:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .help-text {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        hr {
            margin: 1rem 0;
            border: none;
            border-top: 1px solid #eef2f8;
        }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-server"></i> System Settings</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- General System Configuration -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-microchip"></i><h3>General System</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Environment</label>
                        <select name="environment" class="form-control">
                            <option value="development" <?= $settings['environment'] === 'development' ? 'selected' : '' ?>>Development</option>
                            <option value="production" <?= $settings['environment'] === 'production' ? 'selected' : '' ?>>Production</option>
                        </select>
                        <div class="help-text"><?= htmlspecialchars($system_keys['environment']['description']) ?></div>
                    </div>
                    <div class="checkbox-group" style="align-items: flex-end; margin-top: 0;">
                        <input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?= $settings['debug_mode'] ? 'checked' : '' ?>>
                        <label for="debug_mode">Enable Debug Mode (show errors)</label>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Session Timeout (minutes)</label>
                        <input type="number" name="session_timeout_minutes" class="form-control" value="<?= $settings['session_timeout_minutes'] ?>" min="5">
                        <div class="help-text"><?= htmlspecialchars($system_keys['session_timeout_minutes']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>Max Upload Size (MB)</label>
                        <input type="number" step="0.5" name="max_upload_size_mb" class="form-control" value="<?= $settings['max_upload_size_mb'] ?>" min="1">
                        <div class="help-text"><?= htmlspecialchars($system_keys['max_upload_size_mb']['description']) ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Allowed Image Types</label>
                    <input type="text" name="allowed_image_types" class="form-control" value="<?= htmlspecialchars($settings['allowed_image_types']) ?>">
                    <div class="help-text"><?= htmlspecialchars($system_keys['allowed_image_types']['description']) ?></div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="cache_enabled" id="cache_enabled" value="1" <?= $settings['cache_enabled'] ? 'checked' : '' ?>>
                    <label for="cache_enabled">Enable Cache (performance boost)</label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Backup Schedule</label>
                        <select name="backup_schedule" class="form-control">
                            <option value="none" <?= $settings['backup_schedule'] === 'none' ? 'selected' : '' ?>>Disabled</option>
                            <option value="daily" <?= $settings['backup_schedule'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                            <option value="weekly" <?= $settings['backup_schedule'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                            <option value="monthly" <?= $settings['backup_schedule'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        </select>
                        <div class="help-text"><?= htmlspecialchars($system_keys['backup_schedule']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>Backup Retention (days)</label>
                        <input type="number" name="backup_retention_days" class="form-control" value="<?= $settings['backup_retention_days'] ?>" min="1">
                        <div class="help-text"><?= htmlspecialchars($system_keys['backup_retention_days']['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email SMTP Settings -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-envelope"></i><h3>Email (SMTP) Configuration</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host']) ?>" placeholder="smtp.gmail.com">
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_host']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= $settings['smtp_port'] ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_port']['description']) ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="smtp_encryption" class="form-control">
                            <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
                            <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_encryption']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_name']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_from_name']['description']) ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= htmlspecialchars($settings['smtp_username']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_username']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" class="form-control" placeholder="Leave blank to keep current">
                        <div class="help-text"><?= htmlspecialchars($system_keys['smtp_password']['description']) ?></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>From Email Address</label>
                    <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($settings['smtp_from_email']) ?>">
                    <div class="help-text"><?= htmlspecialchars($system_keys['smtp_from_email']['description']) ?></div>
                </div>
            </div>
        </div>

        <!-- M‑Pesa Integration -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-mobile-alt"></i><h3>M‑Pesa Integration</h3></div>
            <div class="card-body">
                <div class="checkbox-group">
                    <input type="checkbox" name="mpesa_enabled" id="mpesa_enabled" value="1" <?= $settings['mpesa_enabled'] ? 'checked' : '' ?>>
                    <label for="mpesa_enabled">Enable M‑Pesa Payments</label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Consumer Key</label>
                        <input type="text" name="mpesa_consumer_key" class="form-control" value="<?= htmlspecialchars($settings['mpesa_consumer_key']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['mpesa_consumer_key']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>Consumer Secret</label>
                        <input type="text" name="mpesa_consumer_secret" class="form-control" value="<?= htmlspecialchars($settings['mpesa_consumer_secret']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['mpesa_consumer_secret']['description']) ?></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Shortcode / Till Number</label>
                        <input type="text" name="mpesa_shortcode" class="form-control" value="<?= htmlspecialchars($settings['mpesa_shortcode']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['mpesa_shortcode']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>Passkey</label>
                        <input type="text" name="mpesa_passkey" class="form-control" value="<?= htmlspecialchars($settings['mpesa_passkey']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['mpesa_passkey']['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Gateway -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-comment"></i><h3>SMS Gateway</h3></div>
            <div class="card-body">
                <div class="checkbox-group">
                    <input type="checkbox" name="sms_enabled" id="sms_enabled" value="1" <?= $settings['sms_enabled'] ? 'checked' : '' ?>>
                    <label for="sms_enabled">Enable SMS Notifications</label>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="text" name="sms_api_key" class="form-control" value="<?= htmlspecialchars($settings['sms_api_key']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['sms_api_key']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>Sender ID</label>
                        <input type="text" name="sms_sender_id" class="form-control" value="<?= htmlspecialchars($settings['sms_sender_id']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['sms_sender_id']['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- External APIs -->
        <div class="settings-card">
            <div class="card-header"><i class="fas fa-plug"></i><h3>External APIs</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Google Maps API Key</label>
                    <input type="text" name="google_maps_api_key" class="form-control" value="<?= htmlspecialchars($settings['google_maps_api_key']) ?>">
                    <div class="help-text"><?= htmlspecialchars($system_keys['google_maps_api_key']['description']) ?></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>reCAPTCHA Site Key</label>
                        <input type="text" name="recaptcha_site_key" class="form-control" value="<?= htmlspecialchars($settings['recaptcha_site_key']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['recaptcha_site_key']['description']) ?></div>
                    </div>
                    <div class="form-group">
                        <label>reCAPTCHA Secret Key</label>
                        <input type="text" name="recaptcha_secret_key" class="form-control" value="<?= htmlspecialchars($settings['recaptcha_secret_key']) ?>">
                        <div class="help-text"><?= htmlspecialchars($system_keys['recaptcha_secret_key']['description']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" name="update_system" class="btn-save"><i class="fas fa-save"></i> Save System Settings</button>
    </form>
</div>
</body>
</html>