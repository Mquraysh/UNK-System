<?php
// admin/settings/fees.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============================================================
// HANDLE CRUD OPERATIONS
// ============================================================

// Create new fee rule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fee'])) {
    $min_distance = (float)$_POST['min_distance'];
    $max_distance = (float)$_POST['max_distance'];
    $fee = (float)$_POST['fee'];
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($min_distance >= 0 && $max_distance > $min_distance && $fee >= 0) {
        // Check for overlapping ranges - FIXED: proper overlap detection
        $check_sql = "SELECT COUNT(*) as count FROM delivery_rates 
                      WHERE (min_distance < '$max_distance' AND max_distance > '$min_distance')";
        $check_result = mysqli_query($conn, $check_sql);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if ($check_data['count'] > 0) {
            $_SESSION['flash_message'] = "Error: Distance range overlaps with existing rule! Please adjust the range.";
            $_SESSION['flash_type'] = "danger";
        } else {
            $esc_min = mysqli_real_escape_string($conn, $min_distance);
            $esc_max = mysqli_real_escape_string($conn, $max_distance);
            $esc_fee = mysqli_real_escape_string($conn, $fee);
            $esc_desc = mysqli_real_escape_string($conn, $description);
            
            $sql = "INSERT INTO delivery_rates (min_distance, max_distance, fee, description, is_active) 
                    VALUES ('$esc_min', '$esc_max', '$esc_fee', '$esc_desc', $is_active)";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['flash_message'] = "Fee rule added successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Error adding fee rule: " . mysqli_error($conn);
                $_SESSION['flash_type'] = "danger";
            }
        }
    } else {
        $_SESSION['flash_message'] = "Invalid input. Please check all fields.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: fees.php");
    exit();
}

// Update fee rule
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_fee'])) {
    $rate_id = (int)$_POST['rate_id'];
    $min_distance = (float)$_POST['min_distance'];
    $max_distance = (float)$_POST['max_distance'];
    $fee = (float)$_POST['fee'];
    $description = trim($_POST['description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if ($rate_id > 0 && $min_distance >= 0 && $max_distance > $min_distance && $fee >= 0) {
        // Check for overlapping ranges - FIXED: proper overlap detection excluding current
        $check_sql = "SELECT COUNT(*) as count FROM delivery_rates 
                      WHERE rate_id != $rate_id 
                      AND (min_distance < '$max_distance' AND max_distance > '$min_distance')";
        $check_result = mysqli_query($conn, $check_sql);
        $check_data = mysqli_fetch_assoc($check_result);
        
        if ($check_data['count'] > 0) {
            $_SESSION['flash_message'] = "Error: Distance range overlaps with existing rule! Please adjust the range.";
            $_SESSION['flash_type'] = "danger";
        } else {
            $esc_min = mysqli_real_escape_string($conn, $min_distance);
            $esc_max = mysqli_real_escape_string($conn, $max_distance);
            $esc_fee = mysqli_real_escape_string($conn, $fee);
            $esc_desc = mysqli_real_escape_string($conn, $description);
            
            $sql = "UPDATE delivery_rates SET 
                        min_distance = '$esc_min',
                        max_distance = '$esc_max',
                        fee = '$esc_fee',
                        description = '$esc_desc',
                        is_active = $is_active
                    WHERE rate_id = $rate_id";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['flash_message'] = "Fee rule updated successfully!";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Error updating fee rule: " . mysqli_error($conn);
                $_SESSION['flash_type'] = "danger";
            }
        }
    } else {
        $_SESSION['flash_message'] = "Invalid input. Please check all fields.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: fees.php");
    exit();
}

// Delete fee rule
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $rate_id = (int)$_GET['id'];
    $sql = "DELETE FROM delivery_rates WHERE rate_id = $rate_id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_message'] = "Fee rule deleted successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error deleting fee rule: " . mysqli_error($conn);
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: fees.php");
    exit();
}

// Toggle active status
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $rate_id = (int)$_GET['id'];
    $sql = "UPDATE delivery_rates SET is_active = NOT is_active WHERE rate_id = $rate_id";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_message'] = "Fee rule status toggled successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error toggling status.";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: fees.php");
    exit();
}

// ============================================================
// GET FEE RATES
// ============================================================
$sql = "SELECT * FROM delivery_rates ORDER BY min_distance ASC";
$result = mysqli_query($conn, $sql);
$fee_rates = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $fee_rates[] = $row;
    }
}

// Get count for statistics
$total_rules = count($fee_rates);
$active_rules = 0;
foreach ($fee_rates as $rate) {
    if ($rate['is_active'] ?? 1) $active_rules++;
}

// Calculate base fee (first active rule)
$base_fee = 0;
$max_fee = 0;
foreach ($fee_rates as $rate) {
    if (($rate['is_active'] ?? 1)) {
        if ($base_fee == 0) $base_fee = $rate['fee'];
        if ($rate['fee'] > $max_fee) $max_fee = $rate['fee'];
    }
}

$flash = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Fees | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .admin-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .admin-content { padding: 0.9rem; }
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .header-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(230,126,34,0.3); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e67e22; color: white; border-color: #e67e22; transform: translateY(-2px); }
        .btn-sm { padding: 0.25rem 0.6rem; font-size: 0.7rem; }
        .btn-block { width: 100%; justify-content: center; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.5rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            background: #fafcff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 1.25rem; }
        
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .fee-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }
        .fee-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .fee-table tr {
            transition: background 0.2s;
        }
        .fee-table tr:hover {
            background: #fffbeb;
        }
        .fee-table tr.inactive {
            opacity: 0.5;
            background: #f8fafc;
        }
        .fee-table tr.inactive td {
            text-decoration: line-through;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .status-badge.active {
            background: #d1fae5;
            color: #059669;
        }
        .status-badge.inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .modal-header h3 { font-size: 1.1rem; }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
        }
        .modal-close:hover { color: #1f2937; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.8rem;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        .form-group label .required { color: #dc2626; }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.75rem;
        }
        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #e67e22;
            cursor: pointer;
        }
        .form-check label {
            font-weight: 500;
            font-size: 0.8rem;
            color: #475569;
            cursor: pointer;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }
        .empty-state p {
            font-size: 0.85rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 992px) {
            .info-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .fee-table { font-size: 0.7rem; }
            .fee-table th, .fee-table td { padding: 0.4rem 0.5rem; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; justify-content: center; }
            .form-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .header-actions .btn { flex: 1; justify-content: center; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .modal-content { padding: 1.25rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-money-bill-wave"></i> Delivery Fees</h1>
            <p>Manage distance-based delivery fee rates for your marketplace</p>
        </div>
        <div class="header-actions">
            <button onclick="openAddModal()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Fee Rule
            </button>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-route"></i></div>
            <div class="stat-number"><?php echo $total_rules; ?></div>
            <div class="stat-label">Total Fee Rules</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color: #059669;"></i></div>
            <div class="stat-number"><?php echo $active_rules; ?></div>
            <div class="stat-label">Active Rules</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($base_fee); ?></div>
            <div class="stat-label">Base Fee</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="stat-number">TSh <?php echo number_format($max_fee); ?></div>
            <div class="stat-label">Maximum Fee</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Fee Rules</h3>
            <span style="font-size:0.7rem; color:#64748b;">
                <i class="fas fa-circle" style="color:#059669; font-size:0.5rem;"></i> <?php echo $active_rules; ?> active
                <?php if (($total_rules - $active_rules) > 0): ?>
                    <i class="fas fa-circle" style="color:#dc2626; font-size:0.5rem; margin-left:0.5rem;"></i> <?php echo $total_rules - $active_rules; ?> inactive
                <?php endif; ?>
            </span>
        </div>
        <div class="card-body">
            <?php if (empty($fee_rates)): ?>
                <div class="empty-state">
                    <i class="fas fa-tachometer-alt"></i>
                    <h3>No Fee Rules Configured</h3>
                    <p>Click "Add Fee Rule" to set up delivery fees based on distance</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="fee-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Min Distance</th>
                                <th>Max Distance</th>
                                <th>Fee (TSh)</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_rates as $index => $rate): 
                                $is_active = isset($rate['is_active']) ? $rate['is_active'] : 1;
                                $row_class = $is_active ? '' : 'inactive';
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo number_format($rate['min_distance'], 1); ?> km</strong></td>
                                <td><strong><?php echo number_format($rate['max_distance'], 1); ?> km</strong></td>
                                <td><strong style="color:#e67e22;">TSh <?php echo number_format($rate['fee']); ?></strong></td>
                                <td><?php echo htmlspecialchars($rate['description'] ?? '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-<?php echo $is_active ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="openEditModal(<?php echo $rate['rate_id']; ?>)" class="btn btn-warning btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?toggle=1&id=<?php echo $rate['rate_id']; ?>" class="btn btn-secondary btn-sm" title="<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>">
                                            <i class="fas fa-<?php echo $is_active ? 'pause' : 'play'; ?>"></i>
                                        </a>
                                        <a href="?delete=1&id=<?php echo $rate['rate_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this fee rule permanently?')" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> How Fee Calculation Works</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div>
                    <h4 style="font-size:0.85rem; font-weight:700; color:#1e293b; margin-bottom:0.5rem;">
                        <i class="fas fa-route" style="color:#e67e22;"></i> Distance-Based Pricing
                    </h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6;">
                        Delivery fees are calculated based on the distance between the business and the customer.
                        Each fee rule defines a distance range and the corresponding fee. The system automatically
                        finds the matching range for each delivery.
                    </p>
                </div>
                <div>
                    <h4 style="font-size:0.85rem; font-weight:700; color:#1e293b; margin-bottom:0.5rem;">
                        <i class="fas fa-calculator" style="color:#e67e22;"></i> Calculation Example
                    </h4>
                    <p style="font-size:0.8rem; color:#64748b; line-height:1.6; background:#f8fafc; padding:0.75rem; border-radius:0.5rem; border-left:3px solid #e67e22;">
                        <strong>Example:</strong> If distance is <strong>5.5 km</strong>, and the rule is 
                        <strong>5-10 km = TSh 3,500</strong>, the delivery fee will be <strong>TSh 3,500</strong>.
                    </p>
                </div>
                <div>
                    <h4 style="font-size:0.85rem; font-weight:700; color:#1e293b; margin-bottom:0.5rem;">
                        <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i> Important Notes
                    </h4>
                    <ul style="font-size:0.8rem; color:#64748b; list-style:disc; padding-left:1.5rem; line-height:1.8;">
                        <li>Distance ranges must not overlap</li>
                        <li>Ranges should be sequential (0-5, 5-10, 10-20, etc.)</li>
                        <li>Fees can be updated at any time</li>
                        <li>Inactive rules are not applied to new deliveries</li>
                    </ul>
                </div>
                <div>
                    <h4 style="font-size:0.85rem; font-weight:700; color:#1e293b; margin-bottom:0.5rem;">
                        <i class="fas fa-tag" style="color:#e67e22;"></i> Current Configuration
                    </h4>
                    <div style="font-size:0.8rem; color:#64748b; background:#f8fafc; padding:0.75rem; border-radius:0.5rem;">
                        <div><strong>Total Rules:</strong> <?php echo $total_rules; ?></div>
                        <div><strong>Active Rules:</strong> <?php echo $active_rules; ?></div>
                        <div><strong>Base Fee:</strong> TSh <?php echo number_format($base_fee); ?></div>
                        <div><strong>Maximum Fee:</strong> TSh <?php echo number_format($max_fee); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus" style="color:#e67e22;"></i> Add Fee Rule</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Min Distance (km) <span class="required">*</span></label>
                    <input type="number" name="min_distance" class="form-control" step="0.1" min="0" required placeholder="0">
                </div>
                <div class="form-group">
                    <label>Max Distance (km) <span class="required">*</span></label>
                    <input type="number" name="max_distance" class="form-control" step="0.1" min="0" required placeholder="5">
                </div>
                <div class="form-group">
                    <label>Fee (TSh) <span class="required">*</span></label>
                    <input type="number" name="fee" class="form-control" step="100" min="0" required placeholder="2000">
                </div>
            </div>
            <div class="form-group">
                <label>Description <span style="font-size:0.7rem; color:#94a3b8;">(optional)</span></label>
                <input type="text" name="description" class="form-control" placeholder="e.g., Short distance delivery (0-5 km)">
            </div>
            <div class="form-check">
                <input type="checkbox" name="is_active" id="add_is_active" checked>
                <label for="add_is_active">Active (apply this rule to deliveries)</label>
            </div>
            <div style="margin-top:1rem;">
                <button type="submit" name="add_fee" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Add Fee Rule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:#f59e0b;"></i> Edit Fee Rule</h3>
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="rate_id" id="edit_rate_id">
            <div class="form-row">
                <div class="form-group">
                    <label>Min Distance (km) <span class="required">*</span></label>
                    <input type="number" name="min_distance" id="edit_min_distance" class="form-control" step="0.1" min="0" required>
                </div>
                <div class="form-group">
                    <label>Max Distance (km) <span class="required">*</span></label>
                    <input type="number" name="max_distance" id="edit_max_distance" class="form-control" step="0.1" min="0" required>
                </div>
                <div class="form-group">
                    <label>Fee (TSh) <span class="required">*</span></label>
                    <input type="number" name="fee" id="edit_fee" class="form-control" step="100" min="0" required>
                </div>
            </div>
            <div class="form-group">
                <label>Description <span style="font-size:0.7rem; color:#94a3b8;">(optional)</span></label>
                <input type="text" name="description" id="edit_description" class="form-control" placeholder="e.g., Short distance delivery">
            </div>
            <div class="form-check">
                <input type="checkbox" name="is_active" id="edit_is_active">
                <label for="edit_is_active">Active (apply this rule to deliveries)</label>
            </div>
            <div style="margin-top:1rem;">
                <button type="submit" name="update_fee" class="btn btn-warning btn-block">
                    <i class="fas fa-save"></i> Update Fee Rule                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function openEditModal(id) {
    // Use fetch to get data
    fetch('get_fee.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            document.getElementById('edit_rate_id').value = data.rate_id;
            document.getElementById('edit_min_distance').value = data.min_distance;
            document.getElementById('edit_max_distance').value = data.max_distance;
            document.getElementById('edit_fee').value = data.fee;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_is_active').checked = data.is_active == 1;
            document.getElementById('editModal').classList.add('active');
        })
        .catch(err => {
            alert('Error loading fee data. Please refresh and try again.');
            console.error('Fetch error:', err);
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// Keyboard shortcut: Escape to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(modal => {
            modal.classList.remove('active');
        });
    }
});

// Sidebar active link
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../settings/fees.php' || 
            links[i].getAttribute('href') === 'fees.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>