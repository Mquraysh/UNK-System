<?php
// admin/settings/api.php - API Settings (With Auto Table Creation)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'API Settings';
$success_message = '';
$error_message = '';

// ============================================================
// AUTO CREATE API KEYS TABLE IF NOT EXISTS
// ============================================================
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'api_keys'");
if (mysqli_num_rows($table_check) == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS `api_keys` (
        `key_id` INT(11) NOT NULL AUTO_INCREMENT,
        `api_key` VARCHAR(100) NOT NULL UNIQUE,
        `api_secret` VARCHAR(100) NOT NULL,
        `key_name` VARCHAR(255) NOT NULL,
        `permissions` VARCHAR(500) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT(11) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`key_id`),
        INDEX `idx_api_key` (`api_key`),
        INDEX `idx_is_active` (`is_active`),
        INDEX `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    mysqli_query($conn, $create_table);
}

// ============================================================
// HANDLE API KEY GENERATION
// ============================================================
if (isset($_POST['generate_key'])) {
    $key_name = trim($_POST['key_name'] ?? '');
    $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : '';
    
    if (empty($key_name)) {
        $error_message = "API Key name is required.";
    } else {
        // Generate secure API key
        $api_key = 'UNK_' . bin2hex(random_bytes(32));
        $api_secret = bin2hex(random_bytes(24));
        
        $esc_name = mysqli_real_escape_string($conn, $key_name);
        $esc_perms = mysqli_real_escape_string($conn, $permissions);
        $user_id = (int)$_SESSION['user_id'];
        
        $sql = "INSERT INTO api_keys (api_key, api_secret, key_name, permissions, created_by) 
                VALUES ('$api_key', '$api_secret', '$esc_name', '$esc_perms', $user_id)";
        
        if (mysqli_query($conn, $sql)) {
            $success_message = "API Key generated successfully!";
            $new_key = $api_key;
            $new_secret = $api_secret;
        } else {
            $error_message = "Failed to generate API key: " . mysqli_error($conn);
        }
    }
}

// ============================================================
// HANDLE API KEY DELETE
// ============================================================
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $key_id = (int)$_GET['delete'];
    $sql = "DELETE FROM api_keys WHERE key_id = $key_id";
    if (mysqli_query($conn, $sql)) {
        $success_message = "API Key deleted successfully.";
    } else {
        $error_message = "Failed to delete API key.";
    }
    header("Location: api.php");
    exit();
}

// ============================================================
// HANDLE API KEY TOGGLE STATUS
// ============================================================
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $key_id = (int)$_GET['id'];
    $check = mysqli_query($conn, "SELECT is_active FROM api_keys WHERE key_id = $key_id");
    if ($check) {
        $current_status = mysqli_fetch_assoc($check)['is_active'] ?? 0;
        $new_status = $current_status ? 0 : 1;
        
        $sql = "UPDATE api_keys SET is_active = $new_status WHERE key_id = $key_id";
        if (mysqli_query($conn, $sql)) {
            $success_message = "API Key " . ($new_status ? 'activated' : 'deactivated') . " successfully.";
        } else {
            $error_message = "Failed to update API key status.";
        }
    }
    header("Location: api.php");
    exit();
}

// ============================================================
// GET API KEYS
// ============================================================
$keys_sql = "SELECT k.*, u.full_name as creator_name 
             FROM api_keys k
             LEFT JOIN users u ON k.created_by = u.user_id
             ORDER BY k.created_at DESC";
$keys_result = mysqli_query($conn, $keys_sql);
$api_keys = [];
if ($keys_result) {
    while ($row = mysqli_fetch_assoc($keys_result)) {
        $api_keys[] = $row;
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>API Settings | Admin</title>
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
            margin-bottom: 20px;
        }
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
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 5px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: 0.2s;
        }
        .checkbox-item:hover { background: #f1f5f9; }
        .checkbox-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #e67e22;
        }
        .checkbox-item label {
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            cursor: pointer;
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
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #f8fafc; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-active { background: #d1fae5; color: #059669; }
        .status-inactive { background: #fef3c7; color: #d97706; }
        
        .key-display {
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .new-key-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .new-key-box .key-label {
            font-weight: 600;
            color: #92400e;
            font-size: 12px;
        }
        .new-key-box .key-value {
            font-family: monospace;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-all;
        }
        .new-key-box .btn-copy {
            background: #e67e22;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 11px;
            cursor: pointer;
            margin-left: 10px;
        }
        .new-key-box .btn-copy:hover { background: #d35400; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 768px) {
            .checkbox-group { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .checkbox-group { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="settings-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-code"></i> API Settings</h1>
            <p>Manage API keys for third-party integrations</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
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

    <!-- Show Newly Generated Key -->
    <?php if (isset($new_key) && isset($new_secret)): ?>
        <div class="new-key-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:10px;">
                <div>
                    <div class="key-label"><i class="fas fa-key"></i> API Key</div>
                    <div class="key-value"><?php echo $new_key; ?></div>
                    <div style="margin-top:8px;">
                        <div class="key-label"><i class="fas fa-lock"></i> API Secret</div>
                        <div class="key-value"><?php echo $new_secret; ?></div>
                    </div>
                </div>
                <div>
                    <button class="btn-copy" onclick="copyToClipboard('<?php echo $new_key; ?>')">
                        <i class="fas fa-copy"></i> Copy Key
                    </button>
                    <button class="btn-copy" onclick="copyToClipboard('<?php echo $new_secret; ?>')" style="background:#8b5cf6;">
                        <i class="fas fa-copy"></i> Copy Secret
                    </button>
                </div>
            </div>
            <div style="margin-top:10px; font-size:12px; color:#92400e;">
                <i class="fas fa-exclamation-triangle"></i> 
                Copy and store your API secret securely. You won't be able to see it again!
            </div>
        </div>
    <?php endif; ?>

    <!-- Generate New API Key -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Generate New API Key</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Key Name <span class="required">*</span></label>
                    <input type="text" name="key_name" class="form-control" placeholder="e.g., Mobile App, Web Integration, Third-party Service" required>
                </div>
                <div class="form-group">
                    <label>Permissions</label>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="read_products" id="perm_products">
                            <label for="perm_products">Read Products</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="read_orders" id="perm_orders">
                            <label for="perm_orders">Read Orders</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="create_orders" id="perm_create">
                            <label for="perm_create">Create Orders</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="update_orders" id="perm_update">
                            <label for="perm_update">Update Orders</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="read_customers" id="perm_customers">
                            <label for="perm_customers">Read Customers</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="read_businesses" id="perm_businesses">
                            <label for="perm_businesses">Read Businesses</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="manage_webhooks" id="perm_webhooks">
                            <label for="perm_webhooks">Manage Webhooks</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="permissions[]" value="full_access" id="perm_full" 
                                   onclick="document.querySelectorAll('.checkbox-item input[type=\'checkbox\']').forEach(cb => cb.checked = this.checked)">
                            <label for="perm_full"><strong>Full Access</strong></label>
                        </div>
                    </div>
                </div>
                <button type="submit" name="generate_key" class="btn btn-primary">
                    <i class="fas fa-key"></i> Generate API Key
                </button>
            </form>
        </div>
    </div>

    <!-- API Keys List -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> API Keys</h3>
            <span><?php echo count($api_keys); ?> key(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>API Key</th>
                        <th>Permissions</th>
                        <th>Created</th>
                        <th>By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($api_keys)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">
                                <i class="fas fa-key" style="font-size:24px; display:block; margin-bottom:10px;"></i>
                                No API keys found. Generate your first key above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($key['key_name']); ?></strong></td>
                            <td><span class="key-display"><?php echo substr($key['api_key'], 0, 20); ?>...</span></td>
                            <td>
                                <?php 
                                $perms = explode(',', $key['permissions'] ?? '');
                                if (in_array('full_access', $perms)) {
                                    echo '<span class="status-badge status-active">Full Access</span>';
                                } else {
                                    echo count($perms) . ' permissions';
                                }
                                ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($key['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($key['creator_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $key['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <a href="?toggle=1&id=<?php echo $key['key_id']; ?>" class="btn btn-sm <?php echo $key['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                        <i class="fas <?php echo $key['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                        <?php echo $key['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </a>
                                    <a href="?delete=<?php echo $key['key_id']; ?>" class="btn btn-sm btn-danger" 
                                       onclick="return confirm('⚠️ Permanently delete this API key? This action cannot be undone.')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- API Documentation -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> API Documentation</h3>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;">
                <div style="background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-box" style="color:#e67e22;"></i> Products
                    </h4>
                    <div style="font-size:12px; color:#64748b; line-height:1.8;">
                        <div><span class="key-display">GET</span> /api/v1/products</div>
                        <div><span class="key-display">GET</span> /api/v1/products/{id}</div>
                        <div><span class="key-display">GET</span> /api/v1/categories</div>
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-shopping-cart" style="color:#3b82f6;"></i> Orders
                    </h4>
                    <div style="font-size:12px; color:#64748b; line-height:1.8;">
                        <div><span class="key-display">GET</span> /api/v1/orders</div>
                        <div><span class="key-display">POST</span> /api/v1/orders</div>
                        <div><span class="key-display">GET</span> /api/v1/orders/{id}</div>
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-users" style="color:#8b5cf6;"></i> Customers
                    </h4>
                    <div style="font-size:12px; color:#64748b; line-height:1.8;">
                        <div><span class="key-display">GET</span> /api/v1/customers</div>
                        <div><span class="key-display">GET</span> /api/v1/customers/{id}</div>
                        <div><span class="key-display">POST</span> /api/v1/customers</div>
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e2e8f0;">
                    <h4 style="font-size:14px; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-truck" style="color:#10b981;"></i> Delivery
                    </h4>
                    <div style="font-size:12px; color:#64748b; line-height:1.8;">
                        <div><span class="key-display">GET</span> /api/v1/deliveries</div>
                        <div><span class="key-display">GET</span> /api/v1/deliveries/{id}</div>
                        <div><span class="key-display">PUT</span> /api/v1/deliveries/{id}</div>
                    </div>
                </div>
            </div>
            <div style="margin-top:20px; padding:16px; background:#f1f5f9; border-radius:12px; border:1px solid #e2e8f0;">
                <div style="font-size:13px; color:#64748b;">
                    <strong>Authentication:</strong> 
                    <span class="key-display">X-API-Key</span> 
                    <span style="margin:0 8px;">•</span>
                    <span class="key-display">X-API-Secret</span>
                    <span style="margin:0 8px;">•</span>
                    Base URL: <span class="key-display">https://unksystem.com/api/v1/</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard!');
        }).catch(function() {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    var input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    alert('Copied to clipboard!');
}
</script>
</body>
</html>