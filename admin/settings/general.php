<?php
// admin/settings/general.php - General Settings
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'General Settings';

// CHECK IF TABLE EXISTS AND CREATE IF NOT
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'system_settings'");
if (mysqli_num_rows($table_check) == 0) {
    // Create the table
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
    
    // Insert default values - FIXED: Using array() instead of []
    $defaults = array(
        array('site_name', 'UNK System', 'general', 'text'),
        array('site_tagline', 'Ulipo ni Kariakoo', 'general', 'text'),
        array('site_email', 'info@unksystem.com', 'general', 'text'),
        array('site_phone', '+255 615 215 404', 'general', 'text'),
        array('site_address', 'Kariakoo Market, Dar es Salaam, Tanzania', 'general', 'textarea'),
        array('site_currency', 'TSh', 'general', 'text'),
        array('timezone', 'Africa/Dar_es_Salaam', 'general', 'text'),
        array('date_format', 'Y-m-d', 'general', 'text'),
        array('time_format', 'H:i:s', 'general', 'text')
    );
    
    foreach ($defaults as $d) {
        $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value, setting_group, setting_type) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $d[0], $d[1], $d[2], $d[3]);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// ============================================================
// FUNCTIONS
// ============================================================
function getSetting($conn, $key, $default = null) {
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM system_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['setting_value'] : $default;
}

function updateSetting($conn, $key, $value, $group = 'general', $type = 'text') {
    $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value, setting_group, setting_type) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
    mysqli_stmt_bind_param($stmt, 'sssss', $key, $value, $group, $type, $value);
    return mysqli_stmt_execute($stmt);
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $site_name = trim($_POST['site_name'] ?? 'UNK System');
    $site_tagline = trim($_POST['site_tagline'] ?? 'Ulipo ni Kariakoo');
    $site_email = trim($_POST['site_email'] ?? '');
    $site_phone = trim($_POST['site_phone'] ?? '');
    $site_address = trim($_POST['site_address'] ?? '');
    $site_currency = trim($_POST['site_currency'] ?? 'TSh');
    $timezone = trim($_POST['timezone'] ?? 'Africa/Dar_es_Salaam');
    $date_format = trim($_POST['date_format'] ?? 'Y-m-d');
    $time_format = trim($_POST['time_format'] ?? 'H:i:s');
    
    // Validate email
    if (!empty($site_email) && !filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $all_saved = true;
        
        // Save each setting
        $settings_data = array(
            'site_name' => $site_name,
            'site_tagline' => $site_tagline,
            'site_email' => $site_email,
            'site_phone' => $site_phone,
            'site_address' => $site_address,
            'site_currency' => $site_currency,
            'timezone' => $timezone,
            'date_format' => $date_format,
            'time_format' => $time_format
        );
        
        foreach ($settings_data as $key => $value) {
            // Determine type
            $type = 'text';
            if ($key == 'site_address') {
                $type = 'textarea';
            }
            if (!updateSetting($conn, $key, $value, 'general', $type)) {
                $all_saved = false;
                break;
            }
        }
        
        if ($all_saved) {
            $success_message = "Settings saved successfully!";
            // Update session
            $_SESSION['settings'] = $settings_data;
        } else {
            $error_message = "Failed to save settings. Please try again.";
        }
    }
}

// Load current settings from database
$settings = array();
$setting_keys = array('site_name', 'site_tagline', 'site_email', 'site_phone', 'site_address', 
                 'site_currency', 'timezone', 'date_format', 'time_format');

foreach ($setting_keys as $key) {
    $default = '';
    if ($key == 'site_name') $default = 'UNK System';
    elseif ($key == 'site_tagline') $default = 'Ulipo ni Kariakoo';
    elseif ($key == 'site_email') $default = 'info@unksystem.com';
    elseif ($key == 'site_phone') $default = '+255 615 215 404';
    elseif ($key == 'site_address') $default = 'Kariakoo Market, Dar es Salaam, Tanzania';
    elseif ($key == 'site_currency') $default = 'TSh';
    elseif ($key == 'timezone') $default = 'Africa/Dar_es_Salaam';
    elseif ($key == 'date_format') $default = 'Y-m-d';
    elseif ($key == 'time_format') $default = 'H:i:s';
    
    $settings[$key] = getSetting($conn, $key, $default);
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>General Settings | Admin</title>
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
        
        .preview-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            margin-top: 10px;
        }
        .preview-box .preview-label {
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .preview-box .preview-value {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }
        .preview-box .preview-value .highlight {
            color: #e67e22;
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
            <h1><i class="fas fa-cog"></i> General Settings</h1>
            <p>Manage your site configuration and basic information</p>
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

    <!-- Settings Form -->
    <form method="POST" action="">
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Site Information
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Site Name <span class="required">*</span></label>
                    <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                    <div class="help-text">The name of your website/app</div>
                </div>
                
                <div class="form-group">
                    <label>Site Tagline</label>
                    <input type="text" name="site_tagline" class="form-control" value="<?php echo htmlspecialchars($settings['site_tagline']); ?>">
                    <div class="help-text">A short description of your site</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="site_email" class="form-control" value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                        <div class="help-text">Primary contact email</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="site_phone" class="form-control" value="<?php echo htmlspecialchars($settings['site_phone']); ?>">
                        <div class="help-text">Primary contact phone</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="site_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['site_address']); ?></textarea>
                    <div class="help-text">Physical address of your business</div>
                </div>
            </div>
        </div>

        <!-- Localization -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-globe"></i> Localization
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Currency <span class="required">*</span></label>
                        <select name="site_currency" class="form-control">
                            <option value="TSh" <?php echo $settings['site_currency'] == 'TSh' ? 'selected' : ''; ?>>Tanzanian Shilling (TSh)</option>
                            <option value="$" <?php echo $settings['site_currency'] == '$' ? 'selected' : ''; ?>>US Dollar ($)</option>
                            <option value="€" <?php echo $settings['site_currency'] == '€' ? 'selected' : ''; ?>>Euro (€)</option>
                            <option value="£" <?php echo $settings['site_currency'] == '£' ? 'selected' : ''; ?>>British Pound (£)</option>
                            <option value="KES" <?php echo $settings['site_currency'] == 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                            <option value="UGX" <?php echo $settings['site_currency'] == 'UGX' ? 'selected' : ''; ?>>Ugandan Shilling (UGX)</option>
                        </select>
                        <div class="help-text">Default currency for all transactions</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Time Zone <span class="required">*</span></label>
                        <select name="timezone" class="form-control">
                            <option value="Africa/Dar_es_Salaam" <?php echo $settings['timezone'] == 'Africa/Dar_es_Salaam' ? 'selected' : ''; ?>>Dar es Salaam (EAT)</option>
                            <option value="Africa/Nairobi" <?php echo $settings['timezone'] == 'Africa/Nairobi' ? 'selected' : ''; ?>>Nairobi (EAT)</option>
                            <option value="Africa/Kampala" <?php echo $settings['timezone'] == 'Africa/Kampala' ? 'selected' : ''; ?>>Kampala (EAT)</option>
                            <option value="Africa/Lagos" <?php echo $settings['timezone'] == 'Africa/Lagos' ? 'selected' : ''; ?>>Lagos (WAT)</option>
                            <option value="Africa/Johannesburg" <?php echo $settings['timezone'] == 'Africa/Johannesburg' ? 'selected' : ''; ?>>Johannesburg (SAST)</option>
                            <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                        </select>
                        <div class="help-text">Server time zone</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Date Format <span class="required">*</span></label>
                        <select name="date_format" class="form-control">
                            <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-01-15)</option>
                            <option value="d-m-Y" <?php echo $settings['date_format'] == 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY (15-01-2024)</option>
                            <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (01/15/2024)</option>
                            <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (15/01/2024)</option>
                            <option value="F j, Y" <?php echo $settings['date_format'] == 'F j, Y' ? 'selected' : ''; ?>>January 15, 2024</option>
                            <option value="M j, Y" <?php echo $settings['date_format'] == 'M j, Y' ? 'selected' : ''; ?>>Jan 15, 2024</option>
                        </select>
                        <div class="help-text">How dates are displayed</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Time Format <span class="required">*</span></label>
                        <select name="time_format" class="form-control">
                            <option value="H:i:s" <?php echo $settings['time_format'] == 'H:i:s' ? 'selected' : ''; ?>>24-hour (14:30:00)</option>
                            <option value="h:i A" <?php echo $settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12-hour (02:30 PM)</option>
                            <option value="H:i" <?php echo $settings['time_format'] == 'H:i' ? 'selected' : ''; ?>>24-hour no seconds (14:30)</option>
                            <option value="h:i a" <?php echo $settings['time_format'] == 'h:i a' ? 'selected' : ''; ?>>12-hour lowercase (02:30 pm)</option>
                        </select>
                        <div class="help-text">How times are displayed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="settings-card">
            <div class="card-header">
                <i class="fas fa-eye"></i> Preview
            </div>
            <div class="card-body">
                <div class="preview-box">
                    <div class="preview-label">Site Name & Tagline</div>
                    <div class="preview-value">
                        <span class="highlight" id="previewSiteName"><?php echo htmlspecialchars($settings['site_name']); ?></span>
                        <span style="font-weight:400; color:#64748b; font-size:14px;">—</span>
                        <span id="previewTagline" style="font-weight:400; color:#64748b; font-size:14px;"><?php echo htmlspecialchars($settings['site_tagline']); ?></span>
                    </div>
                </div>
                
                <div class="preview-box" style="margin-top:15px;">
                    <div class="preview-label">Currency & Date Format</div>
                    <div class="preview-value">
                        <span id="previewCurrency" class="highlight"><?php echo htmlspecialchars($settings['site_currency']); ?></span>
                        <span style="font-weight:400; color:#64748b; font-size:14px;">—</span>
                        <span id="previewDate" style="font-weight:400; color:#64748b; font-size:14px;"><?php echo date($settings['date_format']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div style="display: flex; gap: 15px; flex-wrap: wrap; padding-top: 10px;">
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Save Settings
            </button>
            <a href="index.php" class="btn-back" style="padding: 12px 24px; text-decoration: none;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live preview on input change
    var siteNameInput = document.querySelector('input[name="site_name"]');
    var previewSiteName = document.getElementById('previewSiteName');
    if (siteNameInput && previewSiteName) {
        siteNameInput.addEventListener('input', function() {
            previewSiteName.textContent = this.value || 'Site Name';
        });
    }
    
    var taglineInput = document.querySelector('input[name="site_tagline"]');
    var previewTagline = document.getElementById('previewTagline');
    if (taglineInput && previewTagline) {
        taglineInput.addEventListener('input', function() {
            previewTagline.textContent = this.value || '';
        });
    }
    
    var currencySelect = document.querySelector('select[name="site_currency"]');
    var previewCurrency = document.getElementById('previewCurrency');
    if (currencySelect && previewCurrency) {
        currencySelect.addEventListener('change', function() {
            previewCurrency.textContent = this.value;
        });
    }
    
    var dateFormatSelect = document.querySelector('select[name="date_format"]');
    var previewDate = document.getElementById('previewDate');
    if (dateFormatSelect && previewDate) {
        dateFormatSelect.addEventListener('change', function() {
            var now = new Date();
            var format = this.value;
            var formatted = format
                .replace('Y', now.getFullYear())
                .replace('m', String(now.getMonth() + 1).padStart(2, '0'))
                .replace('d', String(now.getDate()).padStart(2, '0'))
                .replace('F', now.toLocaleString('default', { month: 'long' }))
                .replace('M', now.toLocaleString('default', { month: 'short' }))
                .replace('j', now.getDate());
            previewDate.textContent = formatted;
        });
    }
});
</script>

</body>
</html>