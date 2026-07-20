<?php
// admin/settings/email-templates.php - Email Templates Management
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Email Templates';
$success_message = '';
$error_message = '';

// ============================================================
// CREATE TABLES IF NOT EXISTS
// ============================================================
$create_templates = "CREATE TABLE IF NOT EXISTS `email_templates` (
    `template_id` INT(11) NOT NULL AUTO_INCREMENT,
    `template_name` VARCHAR(100) NOT NULL,
    `template_key` VARCHAR(50) NOT NULL UNIQUE,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`template_id`),
    INDEX `idx_template_key` (`template_key`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create_templates);

// ============================================================
// HANDLE DELETE TEMPLATE
// ============================================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $template_id = (int)$_GET['delete'];
    $check_sql = "SELECT template_key FROM email_templates WHERE template_id = $template_id";
    $check_result = mysqli_query($conn, $check_sql);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $template = mysqli_fetch_assoc($check_result);
        // Don't allow deletion of default templates
        $default_keys = ['order_confirmation', 'delivery_notification', 'payment_receipt', 'welcome_email', 'password_reset'];
        if (in_array($template['template_key'], $default_keys)) {
            $error_message = "Cannot delete default template. You can deactivate it instead.";
        } else {
            $delete_sql = "DELETE FROM email_templates WHERE template_id = $template_id";
            if (mysqli_query($conn, $delete_sql)) {
                $success_message = "Email template deleted successfully.";
            } else {
                $error_message = "Failed to delete template.";
            }
        }
    }
}

// ============================================================
// HANDLE TOGGLE STATUS
// ============================================================
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $template_id = (int)$_GET['id'];
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT is_active FROM email_templates WHERE template_id = $template_id"))['is_active'] ?? 0;
    $new_status = $current ? 0 : 1;
    
    $update_sql = "UPDATE email_templates SET is_active = $new_status WHERE template_id = $template_id";
    if (mysqli_query($conn, $update_sql)) {
        $success_message = "Template " . ($new_status ? 'activated' : 'deactivated') . " successfully.";
    } else {
        $error_message = "Failed to update template status.";
    }
}

// ============================================================
// HANDLE CREATE/UPDATE TEMPLATE
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_template'])) {
        $template_name = trim($_POST['template_name'] ?? '');
        $template_key = trim($_POST['template_key'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($template_name) || empty($template_key) || empty($subject) || empty($body)) {
            $error_message = "All fields are required.";
        } else {
            $esc_name = mysqli_real_escape_string($conn, $template_name);
            $esc_key = mysqli_real_escape_string($conn, $template_key);
            $esc_subject = mysqli_real_escape_string($conn, $subject);
            $esc_body = mysqli_real_escape_string($conn, $body);
            
            $insert_sql = "INSERT INTO email_templates (template_name, template_key, subject, body, is_active) 
                           VALUES ('$esc_name', '$esc_key', '$esc_subject', '$esc_body', $is_active)";
            if (mysqli_query($conn, $insert_sql)) {
                $success_message = "Template created successfully!";
            } else {
                $error_message = "Failed to create template: " . mysqli_error($conn);
            }
        }
    }
    
    if (isset($_POST['update_template']) && isset($_GET['edit'])) {
        $template_id = (int)$_GET['edit'];
        $template_name = trim($_POST['template_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($template_name) || empty($subject) || empty($body)) {
            $error_message = "Name, Subject, and Body are required.";
        } else {
            $esc_name = mysqli_real_escape_string($conn, $template_name);
            $esc_subject = mysqli_real_escape_string($conn, $subject);
            $esc_body = mysqli_real_escape_string($conn, $body);
            
            $update_sql = "UPDATE email_templates 
                           SET template_name = '$esc_name', 
                               subject = '$esc_subject', 
                               body = '$esc_body', 
                               is_active = $is_active,
                               updated_at = NOW()
                           WHERE template_id = $template_id";
            if (mysqli_query($conn, $update_sql)) {
                $success_message = "Template updated successfully!";
                // Refresh the page to show updated data
                header("Location: email-templates.php");
                exit();
            } else {
                $error_message = "Failed to update template.";
            }
        }
    }
}

// ============================================================
// GET TEMPLATE FOR EDIT
// ============================================================
$edit_template = null;
if (isset($_GET['edit'])) {
    $template_id = (int)$_GET['edit'];
    $edit_sql = "SELECT * FROM email_templates WHERE template_id = $template_id";
    $edit_result = mysqli_query($conn, $edit_sql);
    if ($edit_result && mysqli_num_rows($edit_result) > 0) {
        $edit_template = mysqli_fetch_assoc($edit_result);
    }
}

// ============================================================
// GET ALL TEMPLATES
// ============================================================
$templates = [];
$templates_sql = "SELECT * FROM email_templates ORDER BY template_name";
$templates_result = mysqli_query($conn, $templates_sql);
if ($templates_result) {
    while ($row = mysqli_fetch_assoc($templates_result)) {
        $templates[] = $row;
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Email Templates | Admin</title>
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
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .card {
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
        
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .form-group label .required { color: #ef4444; }
        .form-group .help-text {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 4px;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        textarea.form-control { 
            resize: vertical; 
            min-height: 150px;
            font-family: 'Inter', sans-serif;
        }
        
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .template-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 18px 20px;
            transition: all 0.3s;
        }
        .template-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .template-card .name {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .template-card .key {
            font-size: 11px;
            color: #94a3b8;
            font-family: monospace;
        }
        .template-card .subject {
            font-size: 13px;
            color: #475569;
            margin: 8px 0;
        }
        .template-card .preview {
            font-size: 12px;
            color: #64748b;
            background: #f8fafc;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            max-height: 80px;
            overflow: hidden;
            margin-bottom: 12px;
            white-space: pre-wrap;
        }
        .template-card .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 12px;
            cursor: pointer;
            transition: 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-sm { padding: 5px 12px; font-size: 11px; }
        .btn-block { width: 100%; justify-content: center; }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .template-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="settings-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-alt"></i> Email Templates</h1>
            <p>Manage email templates for notifications and communications</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="email.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Email Settings</a>
            <?php if (!isset($_GET['edit'])): ?>
                <a href="?create=1" class="btn btn-primary"><i class="fas fa-plus"></i> Create Template</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Messages -->
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

    <!-- Create/Edit Form -->
    <?php if (isset($_GET['create']) || isset($_GET['edit'])): 
        $is_edit = isset($_GET['edit']);
        $template = $is_edit ? $edit_template : null;
        $title = $is_edit ? 'Edit Template' : 'Create New Template';
        $button_text = $is_edit ? 'Update Template' : 'Create Template';
        $action = $is_edit ? 'update_template' : 'create_template';
    ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus'; ?>"></i> <?php echo $title; ?></h3>
            <a href="email-templates.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Cancel</a>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Template Name <span class="required">*</span></label>
                        <input type="text" name="template_name" class="form-control" 
                               value="<?php echo $is_edit ? htmlspecialchars($template['template_name']) : ''; ?>" 
                               placeholder="e.g., Order Confirmation" required>
                    </div>
                    <div class="form-group">
                        <label>Template Key <span class="required">*</span></label>
                        <input type="text" name="template_key" class="form-control" 
                               value="<?php echo $is_edit ? htmlspecialchars($template['template_key']) : ''; ?>" 
                               placeholder="e.g., order_confirmation" required <?php echo $is_edit ? 'readonly style="background:#f1f5f9;"' : ''; ?>>
                        <?php if ($is_edit): ?>
                            <small class="help-text">Template key cannot be changed after creation</small>
                        <?php else: ?>
                            <small class="help-text">Unique identifier (lowercase, underscores)</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Subject <span class="required">*</span></label>
                    <input type="text" name="subject" class="form-control" 
                           value="<?php echo $is_edit ? htmlspecialchars($template['subject']) : ''; ?>" 
                           placeholder="e.g., Order #{{order_id}} Confirmed!" required>
                    <small class="help-text">Use {{variable}} for dynamic content</small>
                </div>
                <div class="form-group">
                    <label>Body <span class="required">*</span></label>
                    <textarea name="body" class="form-control" rows="8" required><?php echo $is_edit ? htmlspecialchars($template['body']) : ''; ?></textarea>
                    <small class="help-text">
                        Available variables: {{customer_name}}, {{order_id}}, {{amount}}, {{items}}, {{total}}, {{agent_name}}, {{delivery_time}}, {{payment_method}}, {{transaction_id}}, {{name}}, {{reset_link}}
                    </small>
                </div>
                <div class="form-group" style="display:flex; align-items:center; gap:12px;">
                    <label style="margin-bottom:0; cursor:pointer;">
                        <input type="checkbox" name="is_active" <?php echo ($is_edit && $template['is_active']) || !$is_edit ? 'checked' : ''; ?>>
                        Active
                    </label>
                </div>
                <button type="submit" name="<?php echo $action; ?>" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> <?php echo $button_text; ?>
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Templates List -->
    <?php if (!isset($_GET['create']) && !isset($_GET['edit'])): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Templates</h3>
            <span><?php echo count($templates); ?> template(s)</span>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>No email templates found.</p>
                    <a href="?create=1" class="btn btn-primary"><i class="fas fa-plus"></i> Create Your First Template</a>
                </div>
            <?php else: ?>
                <div class="template-grid">
                    <?php foreach ($templates as $template): ?>
                    <div class="template-card">
                        <div class="name"><?php echo htmlspecialchars($template['template_name']); ?></div>
                        <div class="key">Key: <?php echo htmlspecialchars($template['template_key']); ?></div>
                        <div class="subject">
                            <i class="fas fa-tag" style="color:#64748b; font-size:11px;"></i>
                            <?php echo htmlspecialchars($template['subject']); ?>
                        </div>
                        <div class="preview"><?php echo htmlspecialchars(substr($template['body'], 0, 100)) . (strlen($template['body']) > 100 ? '...' : ''); ?></div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span class="status-badge <?php echo $template['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $template['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span style="font-size:10px; color:#94a3b8;">
                                Updated: <?php echo date('M d, Y', strtotime($template['updated_at'])); ?>
                            </span>
                        </div>
                        <div class="actions">
                            <a href="?edit=<?php echo $template['template_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?toggle=1&id=<?php echo $template['template_id']; ?>" class="btn btn-sm <?php echo $template['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                <i class="fas <?php echo $template['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                <?php echo $template['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <?php 
                            $default_keys = ['order_confirmation', 'delivery_notification', 'payment_receipt', 'welcome_email', 'password_reset'];
                            if (!in_array($template['template_key'], $default_keys)): 
                            ?>
                            <a href="?delete=<?php echo $template['template_id']; ?>" class="btn btn-danger btn-sm" 
                               onclick="return confirm('⚠️ Delete this template permanently? This action cannot be undone.')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>