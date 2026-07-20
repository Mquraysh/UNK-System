<?php
// admin/settings/database.php - COMPLETE DATABASE MANAGEMENT (FINAL)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get optimization results from session
$optimize_results = isset($_SESSION['optimize_results']) ? $_SESSION['optimize_results'] : [];
$optimized_count = isset($_SESSION['optimized_count']) ? $_SESSION['optimized_count'] : 0;
$failed_count = isset($_SESSION['failed_count']) ? $_SESSION['failed_count'] : 0;
unset($_SESSION['optimize_results']);
unset($_SESSION['optimized_count']);
unset($_SESSION['failed_count']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Management - UNK System Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content { margin-left: 280px; padding: 30px 35px; background: #f0f2f5; min-height: 100vh; }
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        /* .page-header i {
            color: #e67e22;
        } */
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
        }
        
        .settings-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; background: white; padding: 12px 20px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .tab-btn { padding: 10px 24px; border-radius: 30px; text-decoration: none; font-size: 14px; font-weight: 500; background: #f8fafc; color: #64748b; transition: all 0.2s; }
        .tab-btn:hover, .tab-btn.active { background: #e67e22; color: white; }
        
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; max-width: 1200px; margin: 0 auto; margin-bottom: 25px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-header h3 { font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 24px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; background: #f8fafc; }
        
        .btn-save { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-save:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(230,126,34,0.3); }
        
        .btn-danger { background: #dc2626; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        
        .danger-zone { border: 1px solid #fee2e2; background: #fef2f2; }
        .danger-zone .card-header { background: #fee2e2; border-bottom-color: #fecaca; }
        .danger-zone .card-header i { color: #dc2626; }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fef3c7; color: #d97706; border-left: 4px solid #f59e0b; }
        
        .result-list { max-height: 300px; overflow-y: auto; margin-top: 15px; border: 1px solid #e2e8f0; border-radius: 12px; }
        .result-item { padding: 10px 15px; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-family: monospace; }
        .result-item:last-child { border-bottom: none; }
        .result-success { color: #059669; background: #ecfdf5; }
        .result-failed { color: #dc2626; background: #fef2f2; }
        
        .help-text { font-size: 11px; color: #94a3b8; margin-top: 5px; }
        
        .btn-print {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 15px;
        }
        .btn-print:hover {
            background: #1a252f;
        }
        
        @media print {
            .admin-sidebar, .settings-tabs, .top-bar .user-info a, .btn-print,
            .danger-zone, .btn-save, .btn-danger, .tab-btn, .settings-tabs {
                display: none !important;
            }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .card { border: 1px solid #ddd; box-shadow: none; }
        }
        
        @media (max-width: 1024px) { .main-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { .top-bar { flex-direction: column; text-align: center; gap: 15px; } .settings-tabs { justify-content: center; } }
    </style>
</head>
<body>
<div class="main-content">
    <div class="page-header">
        <h1 class="page-header"><i class="fas fa-database"></i> Database Management</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    <?php if(isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash_type']; ?>">
        <i class="fas fa-<?php echo $_SESSION['flash_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php 
            echo $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        ?>
    </div>
    <?php endif; ?>

    <!-- Optimization Results -->
    <?php if(!empty($optimize_results)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Optimization Results</h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 15px; display: flex; gap: 20px;">
                <span><i class="fas fa-check-circle" style="color: #059669;"></i> <strong>Optimized:</strong> <?php echo $optimized_count; ?></span>
                <span><i class="fas fa-times-circle" style="color: #dc2626;"></i> <strong>Failed:</strong> <?php echo $failed_count; ?></span>
            </div>
            <div class="result-list">
                <?php foreach($optimize_results as $result): ?>
                <div class="result-item <?php echo strpos($result, '✅') !== false ? 'result-success' : (strpos($result, '❌') !== false ? 'result-failed' : ''); ?>">
                    <?php echo $result; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Database Information -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-database"></i> Database Information</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label><i class="fas fa-chart-line"></i> Database Size</label>
                <input type="text" class="form-control" readonly id="dbSize" value="Calculating...">
            </div>
            <div class="form-group">
                <label><i class="fas fa-table"></i> Number of Tables</label>
                <input type="text" class="form-control" readonly value="<?php 
                    $tables = $conn->query("SHOW TABLES");
                    echo $tables ? $tables->num_rows : '0';
                ?> tables">
            </div>
            <div class="form-group">
                <label><i class="fas fa-box"></i> Total Records</label>
                <input type="text" class="form-control" readonly value="<?php
                    $total = 0;
                    $tables = $conn->query("SHOW TABLES");
                    if($tables) {
                        while($row = $tables->fetch_row()) {
                            $count = $conn->query("SELECT COUNT(*) as count FROM {$row[0]}")->fetch_assoc();
                            $total += $count['count'];
                        }
                    }
                    echo number_format($total);
                ?> records">
            </div>
            <div style="display: flex; gap: 15px; margin-top: 20px; flex-wrap: wrap;">
                <button onclick="exportDatabase()" class="btn-save">
                    <i class="fas fa-download"></i> Backup Database
                </button>
                <button onclick="optimizeDatabase()" class="btn-save">
                    <i class="fas fa-rocket"></i> Optimize Tables
                </button>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card danger-zone">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Danger Zone</h3>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 15px; color: #991b1b;">These actions are irreversible. Please be careful!</p>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <button onclick="clearCache()" class="btn-danger">
                    <i class="fas fa-trash-alt"></i> Clear System Cache
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function exportDatabase() {
    if(confirm('📦 Export database backup?\n\nThis will download a SQL file containing all your data.\nThe file size may be large.')) {
        window.location.href = 'export_database.php';
    }
}

function optimizeDatabase() {
    if(confirm('🔧 Optimize database tables?\n\nThis will improve database performance.\nMay take a few moments.')) {
        window.location.href = 'optimize_database.php';
    }
}

function clearCache() {
    if(confirm('🗑️ Clear system cache?\n\nThis will not delete any user data or settings.\nIt will only clear temporary files.')) {
        window.location.href = 'clear_cache.php';
    }
}

// Get database size
fetch('get_db_size.php')
    .then(response => response.json())
    .then(data => {
        if(data.size) document.getElementById('dbSize').value = data.size;
    })
    .catch(() => {
        document.getElementById('dbSize').value = 'Unable to calculate';
    });
</script>
</body>
</html> 