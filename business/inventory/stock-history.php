<?php
// business/inventory/stock-history.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$product_filter = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Get history
$sql = "SELECT h.*, p.name as product_name, p.unit 
        FROM stock_history h
        JOIN products p ON h.product_id = p.product_id
        WHERE h.business_id = '$business_id'";

if ($product_filter > 0) {
    $sql .= " AND h.product_id = '$product_filter'";
}

$sql .= " ORDER BY h.created_at DESC LIMIT 100";
$history_result = mysqli_query($conn, $sql);
$history = [];
while($row = mysqli_fetch_assoc($history_result)) {
    $history[] = $row;
}

// Get products for filter
$products_sql = "SELECT product_id, name FROM products WHERE business_id = '$business_id' ORDER BY name";
$products_result = mysqli_query($conn, $products_sql);
$products = [];
while($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock History - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
       * { margin: 0; padding: 0; box-sizing: border-box; }
       body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content { margin-left: 280px; padding: 25px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #e67e22; }
        
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
        }
        .filters-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { min-width: 200px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 6px; }
        .filter-input { width: 100%; padding: 10px 14px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .btn-filter { background: #e67e22; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; }
        .btn-reset { background: #95a5a6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #eef2f6; }
        .table-header h3 { font-size: 16px; color: #2c3e50; }
        .table-header h3 i { color: #e67e22; margin-right: 8px; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #2c3e50; font-size: 12px; border-bottom: 1px solid #eef2f6; background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover td { background: #fff5eb; }
        
        .badge-increase { background: rgba(39,174,96,0.12); color: #27ae60; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .badge-decrease { background: rgba(231,76,60,0.12); color: #e74c3c; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        
        .btn-back { background: #7f8c8d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px 15px; } }
    </style>
</head>
<body>
    <div class="business-content">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
        
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Stock History</h1>
        </div>
        
        <div class="filters-bar">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label><i class="fas fa-box"></i> Filter by Product</label>
                    <select name="product_id" class="filter-input">
                        <option value="0">All Products</option>
                        <?php foreach($products as $p): ?>
                        <option value="<?php echo $p['product_id']; ?>" <?php echo $product_filter == $p['product_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                    <a href="stock-history.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Stock Change Records</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Product</th>
                            <th>Old Stock</th>
                            <th>New Stock</th>
                            <th>Change</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($history)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 50px;">
                                <i class="fas fa-history" style="font-size: 48px; color: #95a5a6;"></i>
                                <p>No stock history records found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($history as $record): ?>
                        <tr>
                            <td><?php echo date('M d, Y H:i:s', strtotime($record['created_at'])); ?></a>
                            <td><strong><?php echo htmlspecialchars($record['product_name']); ?></strong></a>
                            <td><?php echo $record['old_quantity']; ?> <?php echo $record['unit']; ?>s</a>
                            <td><?php echo $record['new_quantity']; ?> <?php echo $record['unit']; ?>s</a>
                            <td>
                                <?php if($record['change_amount'] > 0): ?>
                                    <span class="badge-increase"><i class="fas fa-arrow-up"></i> +<?php echo $record['change_amount']; ?></span>
                                <?php elseif($record['change_amount'] < 0): ?>
                                    <span class="badge-decrease"><i class="fas fa-arrow-down"></i> <?php echo $record['change_amount']; ?></span>
                                <?php else: ?>
                                    <span>0</span>
                                <?php endif; ?>
                             </a>
                            <td><?php echo htmlspecialchars($record['notes'] ?: '-'); ?></a>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>