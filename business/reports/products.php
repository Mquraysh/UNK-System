<?php
// business/reports/products.php - PROFESSIONAL PRODUCTS REPORT
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business data
$business_sql = "SELECT * FROM businesses WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $business_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$business_result = mysqli_stmt_get_result($stmt);
$business = mysqli_fetch_assoc($business_result);
$business_id = $business['business_id'];
$business_name = $business['business_name'];
mysqli_stmt_close($stmt);

// ============================================================
// GET PRODUCTS WITH CATEGORY
// ============================================================
$products_sql = "SELECT p.*, c.name as category_name 
                 FROM products p 
                 LEFT JOIN categories c ON p.category_id = c.category_id 
                 WHERE p.business_id = ? 
                 ORDER BY p.created_at DESC";
$stmt = mysqli_prepare($conn, $products_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);
$all_products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// ============================================================
// GET SALES COUNTS FOR EACH PRODUCT
// ============================================================
$sales_counts = [];
$sales_sql = "SELECT oi.product_id, COUNT(oi.order_id) as times_sold, SUM(oi.quantity) as total_quantity,
                     SUM(oi.subtotal) as total_revenue
              FROM order_items oi 
              JOIN orders o ON oi.order_id = o.order_id
              WHERE o.business_id = ? AND o.status = 'delivered'
              GROUP BY oi.product_id";
$stmt = mysqli_prepare($conn, $sales_sql);
mysqli_stmt_bind_param($stmt, "i", $business_id);
mysqli_stmt_execute($stmt);
$sales_result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($sales_result)) {
    $sales_counts[$row['product_id']] = $row;
}
mysqli_stmt_close($stmt);

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_products = count($all_products);
$active_products = 0;
$low_stock_count = 0;
$out_of_stock = 0;
$total_value = 0;

foreach ($all_products as $p) {
    if ($p['is_available']) $active_products++;
    if ($p['quantity_in_stock'] <= 0) $out_of_stock++;
    elseif ($p['quantity_in_stock'] < 10) $low_stock_count++;
    $total_value += $p['price'] * $p['quantity_in_stock'];
}

// ============================================================
// HELPER FUNCTION FOR IMAGE PATH
// ============================================================
function getProductImageUrl($image_value) {
    $base_path = '../../';
    $default_image = $base_path . 'assets/images/default-product.jpg';
    
    if (empty($image_value)) {
        return $default_image;
    }
    
    if (preg_match('/^https?:\/\//i', $image_value)) {
        return $image_value;
    }
    
    if (strpos($image_value, 'assets/') === 0 || strpos($image_value, 'uploads/') === 0) {
        return $base_path . $image_value;
    }
    
    return $base_path . 'assets/uploads/products/' . ltrim($image_value, '/');
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Products Report - UNK System</title>
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
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
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
        
        /* Table Card */
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
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 0.5rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: block;
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
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #dbeafe; color: #2563eb; }
        .badge-secondary { background: #e2e8f0; color: #64748b; }
        
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
        
        .btn-sm {
            padding: 0.2rem 0.5rem;
            border-radius: 0.4rem;
            font-size: 0.6rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-edit {
            background: #fef3c7;
            color: #d97706;
        }
        .btn-edit:hover {
            background: #f59e0b;
            color: white;
        }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .sub-tabs { justify-content: center; }
            .sub-tab { padding: 0.3rem 0.8rem; font-size: 0.7rem; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 0.4rem 0.5rem; }
            .product-img { width: 35px; height: 35px; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-box"></i> Products Report</h1>
            <p>Complete product inventory and sales performance for <?php echo htmlspecialchars($business_name); ?></p>
        </div>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-number"><?php echo $total_products; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle" style="color:#10b981;"></i></div>
            <div class="stat-number"><?php echo $active_products; ?></div>
            <div class="stat-label">Active Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i></div>
            <div class="stat-number" style="color:#f59e0b;"><?php echo $low_stock_count + $out_of_stock; ?></div>
            <div class="stat-label">Low / Out of Stock</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_value); ?></div>
            <div class="stat-label">Total Inventory Value</div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Products</h3>
            <span class="badge-count"><?php echo $total_products; ?> products</span>
        </div>
        <div class="card-body">
            <div class="table-wrapper">
                <?php if (!empty($all_products)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Sales</th>
                            <th>Revenue</th>
                            <th>Status</th>
                            <th style="width:80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_products as $product): 
                            $sold_data = $sales_counts[$product['product_id']] ?? ['times_sold' => 0, 'total_quantity' => 0, 'total_revenue' => 0];
                            $image_url = getProductImageUrl($product['image_url'] ?? '');
                            
                            // Stock status
                            if ($product['quantity_in_stock'] <= 0) {
                                $stock_badge = 'badge-danger';
                                $stock_text = 'Out of Stock';
                            } elseif ($product['quantity_in_stock'] < 10) {
                                $stock_badge = 'badge-warning';
                                $stock_text = $product['quantity_in_stock'] . ' left';
                            } else {
                                $stock_badge = 'badge-success';
                                $stock_text = $product['quantity_in_stock'] . ' units';
                            }
                        ?>
                        <tr>
                            <td>
                                <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                     class="product-img" 
                                     onerror="this.onerror=null; this.src='../../assets/images/default-product.jpg';"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </td>
                            <td>
                                <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                <br>
                                <span style="font-size:0.6rem; color:#94a3b8;">ID: <?php echo $product['product_id']; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><strong>TSh <?php echo number_format($product['price'], 0, '.', ','); ?></strong></td>
                            <td><span class="badge <?php echo $stock_badge; ?>"><?php echo $stock_text; ?></span></td>
                            <td>
                                <?php echo $sold_data['times_sold']; ?> orders
                                <br>
                                <span style="font-size:0.6rem; color:#94a3b8;"><?php echo $sold_data['total_quantity']; ?> units sold</span>
                            </td>
                            <td>
                                <?php if ($sold_data['total_revenue'] > 0): ?>
                                    <strong style="color:#e67e22;">TSh <?php echo number_format($sold_data['total_revenue']); ?></strong>
                                <?php else: ?>
                                    <span style="color:#94a3b8; font-size:0.7rem;">No sales</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['is_available']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../products/edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-edit btn-sm">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8fafc; font-weight:700;">
                            <td colspan="3" style="text-align:right;">Total:</td>
                            <td>TSh <?php echo number_format(array_sum(array_column($all_products, 'price')), 0, '.', ','); ?></td>
                            <td><?php echo array_sum(array_column($all_products, 'quantity_in_stock')); ?> units</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Products Found</h3>
                    <p>You haven't added any products to your inventory yet.</p>
                    <a href="../products/add.php" class="btn btn-primary" style="margin-top:0.5rem; display:inline-flex;">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/products.php' || 
            links[i].getAttribute('href') === 'products.php') {
            links[i].classList.add('active');
        }
    }
});
</script>

</body>
</html>