<?php
// admin/settings/system.php - System Settings
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'System Settings';

// CHECK IF TABLE EXISTS
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
if (mysqli_num_rows($table_check) == 0) {
    // Create the table with all columns
    $create_table = "CREATE TABLE IF NOT EXISTS `system_settings` (
        `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(100) NOT NULL UNIQUE,
        `setting_value` TEXT,
        `setting_group` VARCHAR(50) DEFAULT 'general',
        `setting_type` ENUM('text','number','boolean','json','textarea','select') DEFAULT 'text',
        `is_public` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_id`),
        INDEX `idx_setting_key` (`setting_key`),
        INDEX `idx_setting_group` (`setting_group`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    mysqli_query($conn, $create_table);
}


// FUNCTIONS
function getSetting($conn, $key, $default = null) {
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM system_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['setting_value'] : $default;
}

function updateSetting($conn, $key, $value, $group = 'system', $type = 'text') {
    // Check if setting exists
    $check = mysqli_prepare($conn, "SELECT setting_id FROM system_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($check, 's', $key);
    mysqli_stmt_execute($check);
    $check_result = mysqli_stmt_get_result($check);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing
        $stmt = mysqli_prepare($conn, "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $value, $key);
    } else {
        // Insert new
        $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value, setting_group, setting_type) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $key, $value, $group, $type);
    }
    mysqli_stmt_close($check);
    return mysqli_stmt_execute($stmt);
}

// HANDLE FORM SUBMISSION
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get all system settings
    $system_settings = array(
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
        'require_email_verification' => isset($_POST['require_email_verification']) ? '1' : '0',
        'require_phone_verification' => isset($_POST['require_phone_verification']) ? '1' : '0',
        'enable_guest_checkout' => isset($_POST['enable_guest_checkout']) ? '1' : '0',
        'enable_reviews' => isset($_POST['enable_reviews']) ? '1' : '0',
        'enable_wishlist' => isset($_POST['enable_wishlist']) ? '1' : '0',
        'enable_comparison' => isset($_POST['enable_comparison']) ? '1' : '0',
        'max_upload_size' => trim($_POST['max_upload_size'] ?? '5'),
        'allowed_file_types' => trim($_POST['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx'),
        'image_quality' => trim($_POST['image_quality'] ?? '80'),
        'thumbnail_width' => trim($_POST['thumbnail_width'] ?? '300'),
        'thumbnail_height' => trim($_POST['thumbnail_height'] ?? '300'),
        'items_per_page' => trim($_POST['items_per_page'] ?? '15'),
        'max_cart_items' => trim($_POST['max_cart_items'] ?? '50'),
        'session_timeout' => trim($_POST['session_timeout'] ?? '3600'),
        'max_login_attempts' => trim($_POST['max_login_attempts'] ?? '5'),
        'lockout_time' => trim($_POST['lockout_time'] ?? '15'),
        'enable_ssl' => isset($_POST['enable_ssl']) ? '1' : '0',
        'enable_cache' => isset($_POST['enable_cache']) ? '1' : '0',
        'cache_duration' => trim($_POST['cache_duration'] ?? '3600'),
        'enable_debug_mode' => isset($_POST['enable_debug_mode']) ? '1' : '0',
        'log_errors' => isset($_POST['log_errors']) ? '1' : '0'
    );
    
    $all_saved = true;
    foreach ($system_settings as $key => $value) {
        // Determine type
        $type = 'text';
        if (in_array($key, array('maintenance_mode', 'allow_registration', 'require_email_verification', 
            'require_phone_verification', 'enable_guest_checkout', 'enable_reviews', 'enable_wishlist', 
            'enable_comparison', 'enable_ssl', 'enable_cache', 'enable_debug_mode', 'log_errors'))) {
            $type = 'boolean';
        } elseif (in_array($key, array('max_upload_size', 'image_quality', 'thumbnail_width', 
            'thumbnail_height', 'items_per_page', 'max_cart_items', 'session_timeout', 
            'max_login_attempts', 'lockout_time', 'cache_duration'))) {
            $type = 'number';
        } elseif (in_array($key, array('allowed_file_types'))) {
            $type = 'text';
        }
        
        if (!updateSetting($conn, $key, $value, 'system', $type)) {
            $all_saved = false;
            break;
        }
    }
    
    if ($all_saved) {
        $success_message = "System settings saved successfully!";
        // Update session
        $_SESSION['system_settings'] = $system_settings;
    } else {
        $error_message = "Failed to save settings. Please try again.";
    }
}

// Load current settings from database
$setting_keys = array(
    'maintenance_mode', 'allow_registration', 'require_email_verification', 
    'require_phone_verification', 'enable_guest_checkout', 'enable_reviews', 
    'enable_wishlist', 'enable_comparison', 'max_upload_size', 'allowed_file_types',
    'image_quality', 'thumbnail_width', 'thumbnail_height', 'items_per_page', 
    'max_cart_items', 'session_timeout', 'max_login_attempts', 'lockout_time',
    'enable_ssl', 'enable_cache', 'cache_duration', 'enable_debug_mode', 'log_errors'
);

$settings = array();
foreach ($setting_keys as $key) {
    $default = '';
    switch ($key) {
        case 'maintenance_mode': $default = '0'; break;
        case 'allow_registration': $default = '1'; break;
        case 'require_email_verification': $default = '1'; break;
        case 'require_phone_verification': $default = '0'; break;
        case 'enable_guest_checkout': $default = '1'; break;
        case 'enable_reviews': $default = '1'; break;
        case 'enable_wishlist': $default = '1'; break;
        case 'enable_comparison': $default = '1'; break;
        case 'max_upload_size': $default = '5'; break;
        case 'allowed_file_types': $default = 'jpg,jpeg,png,gif,pdf,doc,docx'; break;
        case 'image_quality': $default = '80'; break;
        case 'thumbnail_width': $default = '300'; break;
        case 'thumbnail_height': $default = '300'; break;
        case 'items_per_page': $default = '15'; break;
        case 'max_cart_items': $default = '50'; break;
        case 'session_timeout': $default = '3600'; break;
        case 'max_login_attempts': $default = '5'; break;
        case 'lockout_time': $default = '15'; break;
        case 'enable_ssl': $default = '1'; break;
        case 'enable_cache': $default = '1'; break;
        case 'cache_duration': $default = '3600'; break;
        case 'enable_debug_mode': $default = '0'; break;
        case 'log_errors': $default = '1'; break;
    }
    $settings[$key] = getSetting($conn, $key, $default);
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>System Settings | Admin</title>
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
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .settings-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .card-header {
            padding: 18px 24px;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header i { color: #e67e22; }
        .card-body { padding: 24px; }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #ef4444; }
        .form-group .help-text {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .checkbox-group:last-child { border-bottom: none; }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #e67e22;
        }
        .checkbox-group label {
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            margin-bottom: 0;
        }
        .checkbox-group .desc {
            font-size: 12px;
            color: #94a3b8;
            margin-left: 4px;
        }
        
        .btn-save {
            background: #e67e22;
            color: white;
            padding: 12px 32px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-save:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230,126,34,0.3);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="settings-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-server"></i> System Settings</h1>
            <p>Manage system configuration, security, and performance settings</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <!-- Alerts -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- General Settings -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-sliders-h"></i> General Settings
            </div>
            <div class="card-body">
                <div class="checkbox-group">
                    <input type="checkbox" name="maintenance_mode" id="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                    <label for="maintenance_mode">Maintenance Mode <span class="desc">(Disable site for users)</span></label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="allow_registration" id="allow_registration" value="1" <?php echo $settings['allow_registration'] == '1' ? 'checked' : ''; ?>>
                    <label for="allow_registration">Allow User Registration</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="require_email_verification" id="require_email_verification" value="1" <?php echo $settings['require_email_verification'] == '1' ? 'checked' : ''; ?>>
                    <label for="require_email_verification">Require Email Verification <span class="desc">(Users must verify email before login)</span></label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="require_phone_verification" id="require_phone_verification" value="1" <?php echo $settings['require_phone_verification'] == '1' ? 'checked' : ''; ?>>
                    <label for="require_phone_verification">Require Phone Verification</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_guest_checkout" id="enable_guest_checkout" value="1" <?php echo $settings['enable_guest_checkout'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_guest_checkout">Enable Guest Checkout</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_reviews" id="enable_reviews" value="1" <?php echo $settings['enable_reviews'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_reviews">Enable Product Reviews</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_wishlist" id="enable_wishlist" value="1" <?php echo $settings['enable_wishlist'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_wishlist">Enable Wishlist</label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_comparison" id="enable_comparison" value="1" <?php echo $settings['enable_comparison'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_comparison">Enable Product Comparison</label>
                </div>
            </div>
        </div>

        <!-- Media Settings -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-image"></i> Media Settings
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Upload Size (MB)</label>
                        <input type="number" name="max_upload_size" class="form-control" value="<?php echo htmlspecialchars($settings['max_upload_size']); ?>" min="1" max="50">
                        <div class="help-text">Maximum file size for uploads in MB</div>
                    </div>
                    <div class="form-group">
                        <label>Image Quality (%)</label>
                        <input type="number" name="image_quality" class="form-control" value="<?php echo htmlspecialchars($settings['image_quality']); ?>" min="1" max="100">
                        <div class="help-text">JPEG image quality (1-100)</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Thumbnail Width (px)</label>
                        <input type="number" name="thumbnail_width" class="form-control" value="<?php echo htmlspecialchars($settings['thumbnail_width']); ?>" min="50">
                        <div class="help-text">Default thumbnail width in pixels</div>
                    </div>
                    <div class="form-group">
                        <label>Thumbnail Height (px)</label>
                        <input type="number" name="thumbnail_height" class="form-control" value="<?php echo htmlspecialchars($settings['thumbnail_height']); ?>" min="50">
                        <div class="help-text">Default thumbnail height in pixels</div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Allowed File Types</label>
                    <input type="text" name="allowed_file_types" class="form-control" value="<?php echo htmlspecialchars($settings['allowed_file_types']); ?>">
                    <div class="help-text">Comma separated list of allowed file extensions</div>
                </div>
            </div>
        </div>

        <!-- Security Settings -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-shield-alt"></i> Security Settings
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Session Timeout (seconds)</label>
                        <input type="number" name="session_timeout" class="form-control" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>" min="60">
                        <div class="help-text">User session timeout in seconds (3600 = 1 hour)</div>
                    </div>
                    <div class="form-group">
                        <label>Max Login Attempts</label>
                        <input type="number" name="max_login_attempts" class="form-control" value="<?php echo htmlspecialchars($settings['max_login_attempts']); ?>" min="1">
                        <div class="help-text">Maximum failed login attempts before lockout</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Lockout Time (minutes)</label>
                        <input type="number" name="lockout_time" class="form-control" value="<?php echo htmlspecialchars($settings['lockout_time']); ?>" min="1">
                        <div class="help-text">Time to lock user out after max attempts</div>
                    </div>
                    <div class="form-group">
                        <label>Items Per Page</label>
                        <input type="number" name="items_per_page" class="form-control" value="<?php echo htmlspecialchars($settings['items_per_page']); ?>" min="1">
                        <div class="help-text">Default items displayed per page</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Max Cart Items</label>
                        <input type="number" name="max_cart_items" class="form-control" value="<?php echo htmlspecialchars($settings['max_cart_items']); ?>" min="1">
                        <div class="help-text">Maximum items allowed in cart</div>
                    </div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_ssl" id="enable_ssl" value="1" <?php echo $settings['enable_ssl'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_ssl">Force SSL/HTTPS <span class="desc">(Redirect all HTTP to HTTPS)</span></label>
                </div>
            </div>
        </div>

        <!-- Performance Settings -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-rocket"></i> Performance Settings
            </div>
            <div class="card-body">
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_cache" id="enable_cache" value="1" <?php echo $settings['enable_cache'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_cache">Enable Cache <span class="desc">(Improve page load speed)</span></label>
                </div>
                <div class="form-group">
                    <label>Cache Duration (seconds)</label>
                    <input type="number" name="cache_duration" class="form-control" value="<?php echo htmlspecialchars($settings['cache_duration']); ?>" min="60">
                    <div class="help-text">How long to cache pages (3600 = 1 hour)</div>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_debug_mode" id="enable_debug_mode" value="1" <?php echo $settings['enable_debug_mode'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_debug_mode">Enable Debug Mode <span class="desc">(Show error messages for developers)</span></label>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="log_errors" id="log_errors" value="1" <?php echo $settings['log_errors'] == '1' ? 'checked' : ''; ?>>
                    <label for="log_errors">Log Errors <span class="desc">(Save errors to log file)</span></label>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; padding-top: 10px;">
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Save System Settings
            </button>
            <a href="index.php" class="btn-back" style="padding: 12px 24px; text-decoration: none;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>
</body>
</html>