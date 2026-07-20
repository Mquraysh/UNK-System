<?php
// business/inventory/low-stock.php 
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

// Get low stock products (less than 10)
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.business_id = '$business_id' AND p.quantity_in_stock < 10
        ORDER BY p.quantity_in_stock ASC";
$products_result = mysqli_query($conn, $sql);
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
    <title>Low Stock Products - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content { margin-left: 280px; padding: 25px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #e67e22; }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #f39c12;
        }
        
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .table-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #eef2f6; }
        .table-header h3 { font-size: 16px; color: #2c3e50; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #2c3e50; font-size: 12px; border-bottom: 1px solid #eef2f6; background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        
        .badge-low { background: rgba(243,156,18,0.12); color: #f39c12; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .badge-critical { background: rgba(231,76,60,0.12); color: #e74c3c; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        
        .btn-sm { background: #e67e22; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-back { background: #7f8c8d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        
        .product-img { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; background: #f0f2f5; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px 15px; } }
    </style>
</head>
<body>
    <div class="business-content">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
        
        <div class="page-header">
            <h1><i class="fas fa-exclamation-triangle"></i> Low Stock Products</h1>
        </div>
        
        <div class="alert-warning">
            <i class="fas fa-info-circle"></i> 
            These products have stock levels below 10 units. We recommend restocking soon to avoid running out of inventory.
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Products Needing Restock (<?php echo count($products); ?> products)</h3>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Current Stock</th>
                            <th>Unit</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 50px;">
                                <i class="fas fa-check-circle" style="font-size: 48px; color: #27ae60;"></i>
                                <p style="margin-top: 10px;">Great! No low stock products found.</p>
                             </a>
                        </tr>
                        <?php else: ?>
                        <?php foreach($products as $product): ?>
                        <tr>
                            <td>
                                <?php 
                                if(!empty($product['image_url']) && file_exists("../../" . $product['image_url'])) {
                                    $img_src = "../../" . $product['image_url'];
                                } else {
                                    $img_src = "../../assets/images/default-product.jpg";
                                }
                                ?>
                                <img src="<?php echo $img_src; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['name']); ?>">
                             </a>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></a>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></a>
                            <td>
                                <strong style="font-size: 18px; <?php echo $product['quantity_in_stock'] <= 0 ? 'color: #e74c3c;' : 'color: #f39c12;'; ?>">
                                    <?php echo $product['quantity_in_stock']; ?>
                                </strong>
                             </a>
                            <td><?php echo $product['unit']; ?>s</a>
                            <td>
                                <?php if($product['quantity_in_stock'] <= 0): ?>
                                    <span class="badge-critical"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                <?php elseif($product['quantity_in_stock'] < 5): ?>
                                    <span class="badge-critical"><i class="fas fa-exclamation-circle"></i> Critical</span>
                                <?php else: ?>
                                    <span class="badge-low"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                                <?php endif; ?>
                             </a>
                            <td>
                                <a href="update-stock.php?id=<?php echo $product['product_id']; ?>" class="btn-sm">
                                    <i class="fas fa-plus-circle"></i> Restock
                                </a>
                             </a>
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