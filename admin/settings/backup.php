<?php
// admin/settings/backup.php 
require_once '../../config/database.php';
require_once '../../config/notifications.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Backup Settings';

// CHECK ZIP EXTENSION
$zip_available = class_exists('ZipArchive');

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

function updateSetting($conn, $key, $value, $group = 'backup', $type = 'text') {
    $check = mysqli_prepare($conn, "SELECT setting_id FROM system_settings WHERE setting_key = ?");
    mysqli_stmt_bind_param($check, 's', $key);
    mysqli_stmt_execute($check);
    $check_result = mysqli_stmt_get_result($check);
    
    if (mysqli_num_rows($check_result) > 0) {
        $stmt = mysqli_prepare($conn, "UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $value, $key);
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value, setting_group, setting_type) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $key, $value, $group, $type);
    }
    mysqli_stmt_close($check);
    return mysqli_stmt_execute($stmt);
}

function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getBackupDirectory() {
    // Use absolute path
    return __DIR__ . '/../../backups/';
}

function getBackupUrl() {
    // Get base URL for downloads
    $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    // Go up 2 levels to get to root (admin/settings -> admin -> root)
    $basePath = dirname(dirname($basePath));
    return $protocol . $host . $basePath . '/backups/';
}

// ============================================================
// CREATE BACKUP
// ============================================================
function createBackup($conn, $backup_type = 'database', $compress = true) {
    global $zip_available;
    
    $backup_dir = getBackupDirectory();
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backup_name = 'backup_' . $timestamp;
    $sql_file = $backup_dir . $backup_name . '.sql';
    
    // Get all tables
    $tables = [];
    $tables_result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($tables_result)) {
        $tables[] = $row[0];
    }
    
    // Build SQL content
    $sql_content = "-- ============================================\n";
    $sql_content .= "-- DATABASE BACKUP\n";
    $sql_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
    $sql_content .= "-- Tables: " . count($tables) . "\n";
    $sql_content .= "-- ============================================\n\n";
    $sql_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $create_result = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
        $create_row = mysqli_fetch_assoc($create_result);
        $sql_content .= $create_row['Create Table'] . ";\n\n";
        
        $data_result = mysqli_query($conn, "SELECT * FROM `$table`");
        if (mysqli_num_rows($data_result) > 0) {
            $sql_content .= "INSERT INTO `$table` VALUES\n";
            $rows = [];
            while ($data_row = mysqli_fetch_assoc($data_result)) {
                $values = [];
                foreach ($data_row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
                    }
                }
                $rows[] = "(" . implode(", ", $values) . ")";
            }
            $sql_content .= implode(",\n", $rows) . ";\n\n";
        }
    }
    
    $sql_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Write SQL file
    if (!file_put_contents($sql_file, $sql_content)) {
        return ['success' => false, 'message' => 'Failed to write SQL file'];
    }
    
    // If compression is enabled and ZIP is available
    if ($compress && $zip_available) {
        $zip_file = $backup_dir . $backup_name . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile($sql_file, $backup_name . '.sql');
            $zip->close();
            unlink($sql_file);
            $file_path = $zip_file;
            $extension = '.zip';
        } else {
            $file_path = $sql_file;
            $extension = '.sql';
        }
    } else {
        $file_path = $sql_file;
        $extension = '.sql';
    }
    
    // Store relative path in database (relative to root)
    $relative_path = 'backups/' . $backup_name . $extension;
    
    return [
        'success' => true,
        'file_path' => $file_path,
        'relative_path' => $relative_path,
        'file_size' => formatSizeUnits(filesize($file_path)),
        'backup_name' => $backup_name . $extension,
        'extension' => $extension
    ];
}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save Settings
    if (isset($_POST['save_settings'])) {
        $settings = [
            'auto_backup_enabled' => isset($_POST['auto_backup_enabled']) ? '1' : '0',
            'backup_frequency' => trim($_POST['backup_frequency'] ?? 'daily'),
            'backup_time' => trim($_POST['backup_time'] ?? '02:00'),
            'backup_type' => trim($_POST['backup_type'] ?? 'full'),
            'max_backups' => trim($_POST['max_backups'] ?? '10'),
            'backup_retention_days' => trim($_POST['backup_retention_days'] ?? '30'),
            'backup_location' => trim($_POST['backup_location'] ?? '../backups/'),
            'compress_backups' => isset($_POST['compress_backups']) ? '1' : '0',
            'email_backup_notifications' => isset($_POST['email_backup_notifications']) ? '1' : '0',
            'backup_email' => trim($_POST['backup_email'] ?? '')
        ];
        
        $all_saved = true;
        foreach ($settings as $key => $value) {
            if (!updateSetting($conn, $key, $value, 'backup', 'text')) {
                $all_saved = false;
                break;
            }
        }
        
        if ($all_saved) {
            $success_message = "Backup settings saved successfully!";
        } else {
            $error_message = "Failed to save settings. Please try again.";
        }
    }
    
    // CREATE MANUAL BACKUP
    if (isset($_POST['create_backup'])) {
        $backup_type = $_POST['backup_type_manual'] ?? 'database';
        $compress = isset($_POST['compress_backup']) ? true : false;
        
        if ($compress && !$zip_available) {
            $error_message = "⚠️ ZipArchive extension is not installed. Please install PHP zip extension or uncheck 'Compress as ZIP'.";
        } else {
            $result = createBackup($conn, $backup_type, $compress);
            
            if ($result['success']) {
                $user_id = $_SESSION['user_id'] ?? 1;
                $status = 'completed';
                $file_path = $result['relative_path']; // Store relative path
                $full_path = $result['file_path'];
                $file_size = $result['file_size'];
                $backup_name = $result['backup_name'];
                
                // Insert with relative path
                $stmt = mysqli_prepare($conn, "INSERT INTO backup_logs (backup_name, backup_type, file_path, file_size, status, created_by, completed_at) 
                                               VALUES (?, ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, 'sssssi', $backup_name, $backup_type, $file_path, $file_size, $status, $user_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                $success_message = "✅ Backup created successfully! File: $backup_name ($file_size)";
            } else {
                $error_message = "❌ Failed to create backup: " . ($result['message'] ?? 'Unknown error');
            }
        }
    }
    
    // RUN AUTO BACKUP MANUALLY
    if (isset($_POST['run_auto_backup'])) {
        $result = createBackup($conn, 'database', true);
        if ($result['success']) {
            $user_id = $_SESSION['user_id'] ?? 1;
            $file_path = $result['relative_path'];
            $full_path = $result['file_path'];
            $file_size = $result['file_size'];
            $backup_name = $result['backup_name'];
            
            $stmt = mysqli_prepare($conn, "INSERT INTO backup_logs (backup_name, backup_type, file_path, file_size, status, created_by, completed_at) 
                                           VALUES (?, 'auto', ?, ?, 'completed', ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'sssi', $backup_name, $file_path, $file_size, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $success_message = "✅ Auto backup completed successfully!";
        } else {
            $error_message = "❌ Auto backup failed: " . ($result['message'] ?? 'Unknown error');
        }
    }
}

// ============================================================
// HANDLE BACKUP DELETION
// ============================================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $backup_id = (int)$_GET['delete'];
    
    // Get file path
    $get_stmt = mysqli_prepare($conn, "SELECT file_path FROM backup_logs WHERE backup_id = ?");
    mysqli_stmt_bind_param($get_stmt, 'i', $backup_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $backup = mysqli_fetch_assoc($result);
    mysqli_stmt_close($get_stmt);
    
    if ($backup) {
        // Build full path from relative path
        $full_path = __DIR__ . '/../../' . $backup['file_path'];
        
        // Delete physical file if exists
        if (!empty($backup['file_path']) && file_exists($full_path)) {
            unlink($full_path);
        }
        
        // Delete database record
        $del_stmt = mysqli_prepare($conn, "DELETE FROM backup_logs WHERE backup_id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $backup_id);
        if (mysqli_stmt_execute($del_stmt)) {
            $success_message = "✅ Backup deleted successfully!";
        } else {
            $error_message = "❌ Failed to delete backup record.";
        }
        mysqli_stmt_close($del_stmt);
    } else {
        $error_message = "❌ Backup not found.";
    }
    
    // Redirect to refresh page
    header("Location: backup.php");
    exit();
}

// ============================================================
// HANDLE BACKUP DOWNLOAD
// ============================================================
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $backup_id = (int)$_GET['download'];
    
    $get_stmt = mysqli_prepare($conn, "SELECT file_path, backup_name FROM backup_logs WHERE backup_id = ?");
    mysqli_stmt_bind_param($get_stmt, 'i', $backup_id);
    mysqli_stmt_execute($get_stmt);
    $result = mysqli_stmt_get_result($get_stmt);
    $backup = mysqli_fetch_assoc($result);
    mysqli_stmt_close($get_stmt);
    
    if ($backup && !empty($backup['file_path'])) {
        // Build full path from relative path
        $full_path = __DIR__ . '/../../' . $backup['file_path'];
        
        if (file_exists($full_path)) {
            $file_name = basename($backup['backup_name']);
            
            // Set headers for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Content-Length: ' . filesize($full_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            
            // Output file
            readfile($full_path);
            exit();
        } else {
            $error_message = "❌ Backup file not found on server. Path: " . htmlspecialchars($full_path);
        }
    } else {
        $error_message = "❌ Backup record not found.";
    }
}

// ============================================================
// LOAD SETTINGS
// ============================================================
$setting_keys = [
    'auto_backup_enabled', 'backup_frequency', 'backup_time', 'backup_type',
    'max_backups', 'backup_retention_days', 'backup_location', 'compress_backups',
    'email_backup_notifications', 'backup_email'
];

$settings = [];
$defaults = [
    'auto_backup_enabled' => '0',
    'backup_frequency' => 'daily',
    'backup_time' => '02:00',
    'backup_type' => 'full',
    'max_backups' => '10',
    'backup_retention_days' => '30',
    'backup_location' => '../backups/',
    'compress_backups' => '1',
    'email_backup_notifications' => '0',
    'backup_email' => 'admin@unksystem.com'
];

foreach ($setting_keys as $key) {
    $settings[$key] = getSetting($conn, $key, $defaults[$key]);
}

// GET BACKUP LOGS
$logs_sql = "SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20";
$logs_result = mysqli_query($conn, $logs_sql);
$backup_logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) {
    $backup_logs[] = $row;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Backup Settings | Admin</title>
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
        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid #f59e0b;
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .checkbox-group:last-child { border-bottom: none; }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #e67e22;
            flex-shrink: 0;
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
        .btn-backup {
            background: #10b981;
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
        .btn-backup:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16,185,129,0.3);
        }
        .btn-danger-sm {
            background: #ef4444;
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-danger-sm:hover {
            background: #dc2626;
        }
        .btn-download {
            background: #3b82f6;
            color: white;
            padding: 5px 12px;
            border-radius: 6px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-download:hover {
            background: #2563eb;
        }
        .btn-auto-backup {
            background: #8b5cf6;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-auto-backup:hover {
            background: #7c3aed;
            transform: translateY(-2px);
        }
        
        .backup-list {
            margin-top: 15px;
        }
        .backup-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
            gap: 10px;
        }
        .backup-item:last-child { border-bottom: none; }
        .backup-item .backup-info {
            display: flex;
            flex-direction: column;
        }
        .backup-item .backup-name {
            font-weight: 600;
            font-size: 14px;
        }
        .backup-item .backup-details {
            font-size: 12px;
            color: #64748b;
        }
        .backup-item .backup-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-running { background: #dbeafe; color: #2563eb; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-failed { background: #fee2e2; color: #dc2626; }
        
        .btn-group { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
        
        .zip-status {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .zip-status.available { background: #d1fae5; color: #059669; }
        .zip-status.unavailable { background: #fee2e2; color: #dc2626; }
        
        .auto-backup-section {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 15px;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .backup-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn-group {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="settings-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-database"></i> Backup Settings</h1>
            <p>Manage database backups and restore points</p>
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

    <!-- ZIP Extension Status -->
    <div class="zip-status <?php echo $zip_available ? 'available' : 'unavailable'; ?>">
        <i class="fas fa-<?php echo $zip_available ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
        ZipArchive extension: <strong><?php echo $zip_available ? 'Installed ✅' : 'Not Installed ❌'; ?></strong>
        <?php if (!$zip_available): ?>
            <span style="font-weight:normal; font-size:11px; color:#64748b;">(Uncheck "Compress as ZIP" to create backups without compression)</span>
        <?php endif; ?>
    </div>

    <!-- Backup Settings Form -->
    <div class="settings-card">
        <div class="card-header">
            <i class="fas fa-cog"></i> Backup Configuration
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="checkbox-group">
                    <input type="checkbox" name="auto_backup_enabled" id="auto_backup_enabled" value="1" <?php echo $settings['auto_backup_enabled'] == '1' ? 'checked' : ''; ?>>
                    <label for="auto_backup_enabled">🔄 Enable Auto Backup <span class="desc">(Schedule automatic backups)</span></label>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>⏱️ Backup Frequency</label>
                        <select name="backup_frequency" class="form-control">
                            <option value="daily" <?php echo $settings['backup_frequency'] == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="weekly" <?php echo $settings['backup_frequency'] == 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                            <option value="monthly" <?php echo $settings['backup_frequency'] == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="hourly" <?php echo $settings['backup_frequency'] == 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                        </select>
                        <div class="help-text">How often to create automatic backups</div>
                    </div>
                    <div class="form-group">
                        <label>🕐 Backup Time</label>
                        <input type="time" name="backup_time" class="form-control" value="<?php echo htmlspecialchars($settings['backup_time']); ?>">
                        <div class="help-text">Time of day to run backup (24-hour format)</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📦 Backup Type</label>
                        <select name="backup_type" class="form-control">
                            <option value="full" <?php echo $settings['backup_type'] == 'full' ? 'selected' : ''; ?>>Full (Database + Files)</option>
                            <option value="database" <?php echo $settings['backup_type'] == 'database' ? 'selected' : ''; ?>>Database Only</option>
                            <option value="files" <?php echo $settings['backup_type'] == 'files' ? 'selected' : ''; ?>>Files Only</option>
                        </select>
                        <div class="help-text">What to include in the backup</div>
                    </div>
                    <div class="form-group">
                        <label>📊 Max Backups</label>
                        <input type="number" name="max_backups" class="form-control" value="<?php echo htmlspecialchars($settings['max_backups']); ?>" min="1" max="100">
                        <div class="help-text">Maximum number of backups to keep</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 Retention Period (days)</label>
                        <input type="number" name="backup_retention_days" class="form-control" value="<?php echo htmlspecialchars($settings['backup_retention_days']); ?>" min="1" max="365">
                        <div class="help-text">How long to keep backups (in days)</div>
                    </div>
                    <div class="form-group">
                        <label>📁 Backup Location</label>
                        <input type="text" name="backup_location" class="form-control" value="<?php echo htmlspecialchars($settings['backup_location']); ?>">
                        <div class="help-text">Directory where backups are stored</div>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="compress_backups" id="compress_backups" value="1" <?php echo $settings['compress_backups'] == '1' ? 'checked' : ''; ?>>
                    <label for="compress_backups">🗜️ Compress Backups (ZIP) <span class="desc">(Smaller file size - requires ZipArchive)</span></label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="email_backup_notifications" id="email_backup_notifications" value="1" <?php echo $settings['email_backup_notifications'] == '1' ? 'checked' : ''; ?>>
                    <label for="email_backup_notifications">📧 Email Notifications <span class="desc">(Send email when backup completes)</span></label>
                </div>
                
                <div class="form-group">
                    <label>📧 Notification Email</label>
                    <input type="email" name="backup_email" class="form-control" value="<?php echo htmlspecialchars($settings['backup_email']); ?>">
                    <div class="help-text">Email address to receive backup notifications</div>
                </div>
                
                <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="submit" name="save_settings" class="btn-save">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <button type="submit" name="run_auto_backup" class="btn-auto-backup">
                        <i class="fas fa-play"></i> Run Auto Backup Now
                    </button>
                </div>
            </form>
            
            <div class="auto-backup-section">
                <small>
                    <i class="fas fa-info-circle" style="color: #10b981;"></i>
                    <strong>Auto Backup Status:</strong> 
                    <?php if ($settings['auto_backup_enabled'] == '1'): ?>
                        <span style="color: #10b981;">Enabled ✅</span> - 
                        Will run <?php echo $settings['backup_frequency']; ?> at <?php echo $settings['backup_time']; ?>
                    <?php else: ?>
                        <span style="color: #dc2626;">Disabled ❌</span>
                    <?php endif; ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Manual Backup -->
    <div class="settings-card">
        <div class="card-header">
            <i class="fas fa-play"></i> Create Manual Backup
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>Backup Type</label>
                        <select name="backup_type_manual" class="form-control">
                            <option value="database">Database Only</option>
                            <option value="full">Full (Database + Files)</option>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button type="submit" name="create_backup" class="btn-backup">
                            <i class="fas fa-play"></i> Create Backup Now
                        </button>
                    </div>
                </div>
                <div class="checkbox-group" style="border: none; padding: 10px 0;">
                    <input type="checkbox" name="compress_backup" id="compress_backup" value="1" <?php echo $zip_available ? 'checked' : ''; ?> <?php echo !$zip_available ? 'disabled' : ''; ?>>
                    <label for="compress_backup">🗜️ Compress as ZIP <span class="desc"><?php echo $zip_available ? '(Recommended - smaller file)' : '(ZipArchive not available)'; ?></span></label>
                </div>
                <div class="help-text" style="margin-top: 10px;">
                    <i class="fas fa-info-circle"></i> This will create a backup of your database immediately.
                    <strong>Note:</strong> Large databases may take a few minutes.
                </div>
            </form>
        </div>
    </div>

    <!-- Backup Logs -->
    <div class="settings-card">
        <div class="card-header">
            <i class="fas fa-history"></i> Backup History
            <span class="badge-count" style="margin-left: auto; background: #e2e8f0; padding: 2px 12px; border-radius: 20px; font-size: 12px;">
                <?php echo count($backup_logs); ?> backups
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($backup_logs)): ?>
                <div style="text-align:center; padding:30px; color:#94a3b8;">
                    <i class="fas fa-database" style="font-size:40px; display:block; margin-bottom:10px;"></i>
                    No backups found. Create your first backup above.
                </div>
            <?php else: ?>
                <div class="backup-list">
                    <?php foreach ($backup_logs as $log): 
                        // Build full path to check if file exists
                        $full_path = __DIR__ . '/../../' . $log['file_path'];
                        $file_exists = !empty($log['file_path']) && file_exists($full_path);
                    ?>
                    <div class="backup-item">
                        <div class="backup-info">
                            <span class="backup-name"><?php echo htmlspecialchars($log['backup_name']); ?></span>
                            <span class="backup-details">
                                <?php echo ucfirst($log['backup_type']); ?> Backup • 
                                <?php echo $log['file_size'] ?? 'N/A'; ?> • 
                                <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                            </span>
                        </div>
                        <div class="btn-group">
                            <span class="backup-status status-<?php echo $log['status']; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                            <?php if ($log['status'] == 'completed' && $file_exists): ?>
                                <a href="?download=<?php echo $log['backup_id']; ?>" class="btn-download">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php endif; ?>
                            <a href="?delete=<?php echo $log['backup_id']; ?>" class="btn-danger-sm" onclick="return confirm('⚠️ Delete this backup permanently? This action cannot be undone.')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>