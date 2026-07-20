<?php
// business/inventory/bulk-update.php 
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

$message = '';
$message_type = '';

// Get all products 
$products_sql = "SELECT product_id, name, quantity_in_stock FROM products WHERE business_id = '$business_id' ORDER BY name";
$products_result = mysqli_query($conn, $products_sql);
$products = [];
while($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}

// Handle bulk update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_update'])) {
    $updates = 0;
    foreach($_POST['stock'] as $product_id => $new_quantity) {
        $product_id = (int)$product_id;
        $new_quantity = (int)$new_quantity;
        
        // Get current stock
        $current_sql = "SELECT name, quantity_in_stock FROM products WHERE product_id = '$product_id' AND business_id = '$business_id'";
        $current_result = mysqli_query($conn, $current_sql);
        $current = mysqli_fetch_assoc($current_result);
        
        if ($current && $current['quantity_in_stock'] != $new_quantity) {
            $old_quantity = $current['quantity_in_stock'];
            $change = $new_quantity - $old_quantity;
            
            // Update stock
            $update_sql = "UPDATE products SET quantity_in_stock = '$new_quantity' WHERE product_id = '$product_id' AND business_id = '$business_id'";
            if (mysqli_query($conn, $update_sql)) {
                // Check if stock_history table exists
                $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'stock_history'");
                if(mysqli_num_rows($check_table) > 0) {
                    $history_sql = "INSERT INTO stock_history (product_id, business_id, old_quantity, new_quantity, change_amount, action_type, notes, created_at) 
                                   VALUES ('$product_id', '$business_id', '$old_quantity', '$new_quantity', '$change', 'bulk_update', 'Bulk stock update', NOW())";
                    mysqli_query($conn, $history_sql);
                }
                $updates++;
            }
        }
    }
    
    if ($updates > 0) {
        $_SESSION['flash_message'] = "$updates products updated successfully";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "No changes were made";
        $_SESSION['flash_type'] = "info";
    }
    header("Location: index.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Stock Update - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content { margin-left: 280px; padding: 25px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #7f8c8d; margin-top: 5px; }
        
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .stock-table { width: 100%; border-collapse: collapse; }
        .stock-table th { padding: 12px; text-align: left; background: #f8fafc; border-bottom: 2px solid #eef2f6; }
        .stock-table td { padding: 12px; border-bottom: 1px solid #eef2f6; }
        .stock-table input { width: 100px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; }
        .stock-table tr:hover td { background: #fff5eb; }
        
        .btn-group { display: flex; gap: 15px; margin-top: 25px; }
        .btn-primary { background: #e67e22; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #d35400; }
        .btn-back { background: #7f8c8d; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #3498db; }
        
        .stock-value {
            font-weight: 600;
            color: #2c3e50;
        }
        
        @media (max-width: 1024px) { 
            .business-content { margin-left: 0; padding: 20px 15px; } 
            .stock-table input { width: 80px; }
        }
        
        @media (max-width: 768px) {
            .stock-table th, .stock-table td { padding: 8px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="business-content">
        <div class="page-header">
            <h1><i class="fas fa-layer-group"></i> Bulk Stock Update</h1>
            <p>Update stock quantities for multiple products at once</p>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            Enter new stock quantities for the products below. Leave unchanged if no update needed.
        </div>
        
        <div class="form-card">
            <form method="POST">
                <div style="overflow-x: auto;">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Current Stock</th>
                                <th>New Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($products)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-box-open" style="font-size: 48px; color: #95a5a6;"></i>
                                    <p style="margin-top: 10px;">No products found</p>
                                    <a href="../products/add.php" style="color: #e67e22;">Add your first product</a>
                                 </a>
                            </tr>
                            <?php else: ?>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                <td><span class="stock-value"><?php echo $product['quantity_in_stock']; ?></span> <span style="color: #7f8c8d;">units</span></td>
                                <td>
                                    <input type="number" name="stock[<?php echo $product['product_id']; ?>]" 
                                           value="<?php echo $product['quantity_in_stock']; ?>" 
                                           min="0" style="width: 100px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px;">
                                 </a>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if(!empty($products)): ?>
                <div class="btn-group">
                    <button type="submit" name="bulk_update" class="btn-primary"><i class="fas fa-save"></i> Save All Changes</button>
                    <a href="index.php" class="btn-back"><i class="fas fa-times"></i> Cancel</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</body>
</html>