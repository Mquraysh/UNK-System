<?php
// business/reports/stock_history.php - PROFESSIONAL STOCK HISTORY REPORT
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business data
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);
$business_id = $business['business_id'];
$business_name = $business['business_name'];

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// ============================================================
// GET STOCK HISTORY
// ============================================================
$stock_history_result = mysqli_query($conn, "SELECT h.*, p.name as product_name, p.image_url, p.price
    FROM stock_history h 
    JOIN products p ON h.product_id = p.product_id
    WHERE h.business_id = $business_id 
    AND DATE(h.created_at) BETWEEN '$start_date' AND '$end_date'
    ORDER BY h.created_at DESC LIMIT 500");
$stock_history = mysqli_fetch_all($stock_history_result, MYSQLI_ASSOC);

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_records = count($stock_history);
$total_restocks = 0;
$total_sales = 0;
$total_manual = 0;
$total_quantity_changed = 0;

foreach ($stock_history as $record) {
    if ($record['action_type'] == 'restock') $total_restocks++;
    elseif ($record['action_type'] == 'sale') $total_sales++;
    else $total_manual++;
    $total_quantity_changed += abs($record['change_amount']);
}

// Get current low stock products
$low_stock_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products 
    WHERE business_id = $business_id AND quantity_in_stock < 10 AND quantity_in_stock > 0");
$low_stock = mysqli_fetch_assoc($low_stock_result)['count'];

// Get out of stock products
$out_stock_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products 
    WHERE business_id = $business_id AND quantity_in_stock <= 0 AND is_available = 1");
$out_stock = mysqli_fetch_assoc($out_stock_result)['count'];

// ============================================================
// HELPER FUNCTION FOR IMAGE PATH
// ============================================================
function fixImageUrl($imagePath) {
    if (empty($imagePath)) {
        return '../../assets/images/default-product.jpg';
    }
    if (preg_match('/^https?:\/\//i', $imagePath)) {
        return $imagePath;
    }
    if ($imagePath[0] === '/') {
        return $imagePath;
    }
    return '../../' . ltrim($imagePath, './');
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Stock History Report - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
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
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        /* Sub Tabs */
        .sub-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .sub-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            color: #64748b;
            transition: all 0.2s;
        }
        .sub-tab i { font-size: 0.9rem; }
        .sub-tab:hover { background: #fef3c7; color: #e67e22; }
        .sub-tab.active { background: #e67e22; color: white; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 1.75rem;
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
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-group input {
            padding: 0.5rem 0.8rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            transition: all 0.2s;
            background: white;
        }
        .filter-group input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-filter {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-filter:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-reset:hover {
            background: #64748b;
            transform: translateY(-2px);
        }
        .btn-export {
            background: #10b981;
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .btn-export:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
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
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 0; }
        
        /* Table */
        .table-wrapper { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .data-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.8rem;
        }
        .data-table tr:hover td {
            background: #fffbeb;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 0.5rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: block;
        }
        .product-info {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .product-name {
            font-weight: 600;
            color: #0f172a;
        }
        
        .badge {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #059669; }
        .badge-warning { background: #fef3c7; color: #d97706; }
        .badge-info { background: #dbeafe; color: #2563eb; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        
        .change-positive {
            color: #059669;
            font-weight: 700;
        }
        .change-negative {
            color: #dc2626;
            font-weight: 700;
        }
        .change-neutral {
            color: #94a3b8;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
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
        .empty-state p { font-size: 0.85rem; }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-buttons { width: 100%; }
            .filter-buttons .btn { flex: 1; justify-content: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .sub-tabs { justify-content: center; }
            .sub-tab { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 0.4rem 0.5rem; }
            .product-img { width: 30px; height: 30px; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .filter-buttons { flex-direction: column; }
            .filter-buttons .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-history"></i> Stock History Report</h1>
            <p>Track all stock movements and changes for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
            <div class="stat-number"><?php echo $total_records; ?></div>
            <div class="stat-label">Total Movements</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-plus-circle" style="color:#10b981;"></i></div>
            <div class="stat-number"><?php echo $total_restocks; ?></div>
            <div class="stat-label">Restocks</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart" style="color:#f59e0b;"></i></div>
            <div class="stat-number"><?php echo $total_sales; ?></div>
            <div class="stat-label">Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-boxes" style="color:#3b82f6;"></i></div>
            <div class="stat-number"><?php echo number_format($total_quantity_changed); ?></div>
            <div class="stat-label">Units Changed</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> End Date</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="stock_history.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
                <a href="export_stock.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn-export">
                    <i class="fas fa-file-export"></i> Export
                </a>
            </div>
        </form>
    </div>

    <!-- Stock History Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Stock Movement History</h3>
            <span class="badge-count">
                <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <?php if (!empty($stock_history)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Old Stock</th>
                            <th>New Stock</th>
                            <th>Change</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($stock_history as $record): 
                            $img_url = fixImageUrl($record['image_url'] ?? '');
                            $change = $record['change_amount'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M d, Y', strtotime($record['created_at'])); ?></strong>
                                <br>
                                <span style="font-size:0.65rem; color:#94a3b8;"><?php echo date('H:i', strtotime($record['created_at'])); ?></span>
                            </td>
                            <td>
                                <div class="product-info">
                                    <img src="<?php echo $img_url; ?>" 
                                         class="product-img" 
                                         onerror="this.onerror=null; this.src='../../assets/images/default-product.jpg';"
                                         alt="<?php echo htmlspecialchars($record['product_name']); ?>">
                                    <span class="product-name"><?php echo htmlspecialchars($record['product_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo number_format($record['old_quantity']); ?></td>
                            <td><?php echo number_format($record['new_quantity']); ?></td>
                            <td>
                                <?php if ($change > 0): ?>
                                    <span class="change-positive">
                                        <i class="fas fa-arrow-up"></i> +<?php echo $change; ?>
                                    </span>
                                <?php elseif ($change < 0): ?>
                                    <span class="change-negative">
                                        <i class="fas fa-arrow-down"></i> <?php echo $change; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="change-neutral">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['action_type'] == 'restock'): ?>
                                    <span class="badge badge-success"><i class="fas fa-plus-circle"></i> Restock</span>
                                <?php elseif ($record['action_type'] == 'sale'): ?>
                                    <span class="badge badge-warning"><i class="fas fa-shopping-cart"></i> Sale</span>
                                <?php else: ?>
                                    <span class="badge badge-info"><i class="fas fa-edit"></i> Manual</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8fafc; font-weight:700;">
                            <td colspan="4" style="text-align:right;">Total:</td>
                            <td colspan="2">
                                <?php 
                                $total_change = array_sum(array_column($stock_history, 'change_amount'));
                                if ($total_change > 0): ?>
                                    <span class="change-positive">+<?php echo number_format($total_change); ?> units</span>
                                <?php elseif ($total_change < 0): ?>
                                    <span class="change-negative"><?php echo number_format($total_change); ?> units</span>
                                <?php else: ?>
                                    <span class="change-neutral"><?php echo number_format($total_change); ?> units</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h3>No Stock History Found</h3>
                    <p>No stock movements recorded for the selected period.</p>
                    <p style="font-size:0.75rem; margin-top:0.3rem;">
                        <i class="fas fa-info-circle"></i> Stock history is recorded when products are restocked or sold.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stock Alerts -->
    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header">
            <h3><i class="fas fa-bell"></i> Stock Alerts</h3>
            <span class="badge-count">Action required</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0; border-collapse: collapse;">
                <div style="padding:1rem 1.25rem; border-right:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:0.7rem; color:#64748b;">Low Stock Products</div>
                    <div style="font-size:1.5rem; font-weight:800; color:#f59e0b;"><?php echo $low_stock; ?></div>
                    <div style="font-size:0.65rem; color:#94a3b8;">Products with less than 10 units</div>
                </div>
                <div style="padding:1rem 1.25rem; border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:0.7rem; color:#64748b;">Out of Stock Products</div>
                    <div style="font-size:1.5rem; font-weight:800; color:#dc2626;"><?php echo $out_stock; ?></div>
                    <div style="font-size:0.65rem; color:#94a3b8;">Products that need restocking</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/stock_history.php' || 
            links[i].getAttribute('href') === 'stock_history.php') {
            links[i].classList.add('active');
        }
    }
});
</script>

</body>
</html>