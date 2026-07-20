<?php
// customer/products/compare.php 
require_once '../../config/database.php';
session_start();

// Get products to compare (max 4)
$compare_ids = isset($_SESSION['compare_products']) ? $_SESSION['compare_products'] : [];

// Helper for product image
function getProductImage($imagePath) {
    if (empty($imagePath)) return '../../assets/images/default-product.jpg';
    if (preg_match('/^https?:\/\//i', $imagePath)) return $imagePath;
    if ($imagePath[0] === '/') return $imagePath;
    return '../../' . ltrim($imagePath, './');
}

// Handle add to compare
if (isset($_GET['add'])) {
    $id = (int)$_GET['add'];
    if (!in_array($id, $compare_ids) && count($compare_ids) < 4) {
        $compare_ids[] = $id;
        $_SESSION['compare_products'] = $compare_ids;
        $_SESSION['flash_message'] = "Product added to comparison!";
        $_SESSION['flash_type'] = "success";
    } elseif (count($compare_ids) >= 4) {
        $_SESSION['flash_message'] = "You can compare up to 4 products only!";
        $_SESSION['flash_type'] = "danger";
    } else {
        $_SESSION['flash_message'] = "Product already in comparison!";
        $_SESSION['flash_type'] = "warning";
    }
    header("Location: compare.php");
    exit();
}

// Handle remove from compare
if (isset($_GET['remove'])) {
    $id = (int)$_GET['remove'];
    $compare_ids = array_diff($compare_ids, [$id]);
    $_SESSION['compare_products'] = $compare_ids;
    $_SESSION['flash_message'] = "Product removed from comparison!";
    $_SESSION['flash_type'] = "success";
    header("Location: compare.php");
    exit();
}

// Handle clear all
if (isset($_GET['clear'])) {
    $_SESSION['compare_products'] = [];
    $_SESSION['flash_message'] = "Comparison cleared!";
    $_SESSION['flash_type'] = "success";
    header("Location: compare.php");
    exit();
}

// Get filters for sidebar
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$style = isset($_GET['style']) ? $_GET['style'] : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Get popular products for "Add More" section
$popular_sql = "SELECT p.product_id, p.name, p.price, p.image_url, b.business_name 
                FROM products p 
                JOIN businesses b ON p.business_id = b.business_id 
                WHERE p.is_available = 1 
                ORDER BY p.views DESC 
                LIMIT 8";
$popular_result = mysqli_query($conn, $popular_sql);
$popular_products = [];
while ($row = mysqli_fetch_assoc($popular_result)) {
    $popular_products[] = $row;
}

// Get products data for comparison
$products = [];
if (!empty($compare_ids)) {
    $ids_string = implode(',', array_map('intval', $compare_ids));
    $sql = "SELECT p.*, b.business_name, b.location, b.is_verified, b.rating as business_rating, c.name as category_name 
            FROM products p
            JOIN businesses b ON p.business_id = b.business_id
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id IN ($ids_string) AND p.is_available = 1
            ORDER BY FIELD(p.product_id, $ids_string)";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}

// Get average ratings for each product
$ratings = [];
foreach ($products as $p) {
    $pid = $p['product_id'];
    $rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = $pid AND status = 'approved'";
    $rating_res = mysqli_query($conn, $rating_sql);
    if ($rating_res && $rating_row = mysqli_fetch_assoc($rating_res)) {
        $ratings[$pid] = [
            'avg' => round($rating_row['avg_rating'], 1),
            'count' => (int)$rating_row['total']
        ];
    } else {
        $ratings[$pid] = ['avg' => 0, 'count' => 0];
    }
}

// Get price range for budget slider
$price_sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_available = 1";
$price_result = mysqli_query($conn, $price_sql);
$price_range = mysqli_fetch_assoc($price_result);
$global_min_price = $price_range['min_price'] ?? 0;
$global_max_price = $price_range['max_price'] ?? 5000000;

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Compare Products | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .customer-content {
            margin-left: 0;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { 
            font-size: 28px; 
            color: #1e293b; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        .page-header h1 i { color: #e67e22; font-size: 28px; }
        .page-header p { color: #64748b; font-size: 13px; margin-top: 5px; }
        
        /* Buttons */
        .btn-clear { background: #e74c3c; color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-clear:hover { background: #c0392b; transform: translateY(-2px); }
        .btn-add { background: #e67e22; color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; margin-left: 10px; }
        .btn-add:hover { background: #d35400; transform: translateY(-2px); }
        
        /* Alert */
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
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        
        /* Layout */
        .compare-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }
        
        /* Sidebar Filters */
        .filters-sidebar { position: sticky; top: 20px; height: fit-content; }
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
        }
        .filter-title {
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e67e22;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-title i { color: #e67e22; }
        
        /* Price Inputs */
        .price-inputs { display: flex; gap: 10px; margin-bottom: 12px; }
        .price-field { flex: 1; }
        .price-field label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .price-field input { width: 100%; padding: 8px 10px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 12px; }
        .price-field input:focus { outline: none; border-color: #e67e22; }
        
        /* Style Buttons */
        .style-buttons-group { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .style-btn {
            padding: 8px 14px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .style-btn:hover { background: #e67e22; color: white; border-color: #e67e22; transform: translateY(-2px); }
        .style-btn.active { background: #e67e22; color: white; border-color: #e67e22; }
        
        /* Popular Products */
        .popular-products-list { display: flex; flex-direction: column; gap: 10px; }
        .popular-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: #f8fafc;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .popular-item:hover { background: #e67e22; transform: translateX(5px); }
        .popular-item:hover .popular-name { color: white; }
        .popular-item:hover .popular-price { color: #fef3c7; }
        .popular-img { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; }
        .popular-info { flex: 1; }
        .popular-name { font-size: 12px; font-weight: 600; color: #1e293b; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .popular-price { font-size: 11px; color: #e67e22; font-weight: 700; }
        .popular-business { font-size: 9px; color: #64748b; }
        
        /* Compare Table */
        .compare-table {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .compare-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        .compare-table th, .compare-table td {
            padding: 15px 18px;
            text-align: center;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
        }
        .compare-table th {
            background: #f8fafc;
            font-weight: 700;
            color: #1e293b;
            width: 160px;
            font-size: 13px;
            text-align: left;
        }
        .product-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 10px;
        }
        .product-name { font-size: 15px; font-weight: 700; color: #1e293b; margin: 8px 0; }
        .remove-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fee2e2;
            color: #dc2626;
            padding: 5px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            transition: 0.2s;
        }
        .remove-btn:hover { background: #dc2626; color: white; }
        .verified-badge {
            display: inline-block;
            background: #dbeafe;
            color: #2563eb;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        .stock-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .stock-in { background: #d1fae5; color: #059669; }
        .stock-low { background: #fef3c7; color: #d97706; }
        .stock-out { background: #fee2e2; color: #dc2626; }
        .btn-cart, .btn-view {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            margin: 3px;
            transition: 0.2s;
        }
        .btn-cart { background: #e67e22; color: white; }
        .btn-cart:hover { background: #d35400; transform: translateY(-2px); }
        .btn-view { background: #2c3e50; color: white; }
        .btn-view:hover { background: #1a252f; transform: translateY(-2px); }
        .rating-stars { color: #f39c12; font-size: 12px; }
        
        /* Winner Card */
        .winner-card {
            margin-top: 25px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
        }
        .winner-card h3 { margin-bottom: 10px; }
        .winner-card .winner-name { font-size: 20px; font-weight: 800; color: #92400e; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 15px; }
        .empty-state h3 { font-size: 20px; margin-bottom: 10px; }
        
        .info-note {
            margin-top: 20px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        
        .btn-sm { padding: 6px 12px; font-size: 11px; }
        .btn-block { width: 100%; }
        .btn-primary { background: #e67e22; color: white; border: none; border-radius: 10px; cursor: pointer; }
        .btn-primary:hover { background: #d35400; }
        .btn-outline { background: transparent; border: 2px solid #e2e8f0; color: #475569; border-radius: 10px; text-decoration: none; display: inline-block; text-align: center; }
        .btn-outline:hover { border-color: #e67e22; color: #e67e22; }
        
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .small { font-size: 11px; }
        .text-muted { color: #64748b; }
        .text-center { text-align: center; }
        
        @media (max-width: 1024px) {
            .customer-content { padding: 20px; }
            .compare-layout { grid-template-columns: 1fr; }
            .filters-sidebar { position: static; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .compare-table th, .compare-table td { padding: 10px; font-size: 12px; }
            .product-img { width: 70px; height: 70px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-chart-line"></i> Compare Products</h1>
            <p>Compare products side by side to make the best choice</p>
        </div>
        <div>
            <a href="?clear=1" class="btn-clear" onclick="return confirm('Clear all products from comparison?')">
                <i class="fas fa-trash-alt"></i> Clear All
            </a>
            <a href="index.php" class="btn-add">
                <i class="fas fa-plus"></i> Add More
            </a>
        </div>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : ($flash_type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle') ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="compare-layout">
        <!-- Sidebar Filters -->
        <aside class="filters-sidebar">
            
            <!-- Budget Filter -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-chart-line"></i> Filter Products</div>
                <form method="GET" action="index.php">
                    <div class="price-inputs">
                        <div class="price-field">
                            <label>Min Budget (TSh)</label>
                            <input type="number" name="min_price" placeholder="0" value="<?= $min_price ?: '' ?>">
                        </div>
                        <div class="price-field">
                            <label>Max Budget (TSh)</label>
                            <input type="number" name="max_price" placeholder="Any" value="<?= $max_price ?: '' ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary btn-sm btn-block" style="padding: 8px;">Apply Budget</button>
                </form>
                <div class="small text-muted mt-2">
                    <i class="fas fa-info-circle"></i> Price range: TSh <?= number_format($global_min_price) ?> - TSh <?= number_format($global_max_price) ?>
                </div>
            </div>
            
            <!-- Style Quick Filters -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-bolt"></i> Quick Filters</div>
                <div class="style-buttons-group">
                    <a href="index.php?style=budget" class="style-btn <?= $style == 'budget' ? 'active' : '' ?>">
                        <i class="fas fa-coins"></i> Budget (≤ 100k)
                    </a>
                    <a href="index.php?style=midrange" class="style-btn <?= $style == 'midrange' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> Mid Range
                    </a>
                    <a href="index.php?style=premium" class="style-btn <?= $style == 'premium' ? 'active' : '' ?>">
                        <i class="fas fa-gem"></i> Premium
                    </a>
                    <a href="index.php?style=popular" class="style-btn <?= $style == 'popular' ? 'active' : '' ?>">
                        <i class="fas fa-fire"></i> Most Popular
                    </a>
                </div>
                <div class="mt-2">
                    <a href="index.php" class="btn-outline btn-sm btn-block" style="display: block; text-align: center;">Reset Filters</a>
                </div>
            </div>
            
            <!-- Popular Products -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-fire"></i> Popular Products</div>
                <div class="popular-products-list">
                    <?php foreach ($popular_products as $p): ?>
                        <?php if (!in_array($p['product_id'], $compare_ids)): ?>
                            <a href="?add=<?= $p['product_id'] ?>" class="popular-item">
                                <img src="<?= getProductImage($p['image_url'] ?? '') ?>" class="popular-img" onerror="this.src='../../assets/images/default-product.jpg'">
                                <div class="popular-info">
                                    <div class="popular-name"><?= htmlspecialchars(substr($p['name'], 0, 30)) ?></div>
                                    <div class="popular-price">TSh <?= number_format($p['price']) ?></div>
                                    <div class="popular-business"><?= htmlspecialchars($p['business_name']) ?></div>
                                </div>
                                <i class="fas fa-plus-circle" style="color: #e67e22;"></i>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Recently Viewed Link -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-history"></i> Recently Viewed</div>
                <a href="recently-viewed.php" class="btn-outline btn-sm btn-block" style="display: block; text-align: center;">View History →</a>
            </div>
            
            <!-- Price Alerts Link -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-bell"></i> Price Alerts</div>
                <a href="price-alert.php" class="btn-outline btn-sm btn-block" style="display: block; text-align: center;">Manage Alerts →</a>
            </div>
        </aside>

        <!-- Main Content -->
        <div>
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3>No products to compare</h3>
                    <p>Add products from the shop page to compare them side by side.</p>
                    <a href="index.php" class="btn-add" style="display: inline-block;">Browse Products</a>
                </div>
            <?php else: ?>
                <div class="compare-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Details</th>
                                <?php foreach ($products as $p): ?>
                                    <td style="text-align: center;">
                                        <img src="<?= getProductImage($p['image_url'] ?? '') ?>" class="product-img" alt="<?= htmlspecialchars($p['name']) ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                                        <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                                        <a href="?remove=<?= $p['product_id'] ?>" class="remove-btn" onclick="return confirm('Remove this product?')">
                                            <i class="fas fa-times"></i> Remove
                                        </a>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th><i class="fas fa-tag"></i> Price</th>
                                <?php foreach ($products as $p): ?>
                                    <td class="product-price"><strong style="color:#e67e22; font-size:18px;">TSh <?= number_format($p['price']) ?></strong></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-store"></i> Seller</th>
                                <?php foreach ($products as $p): ?>
                                    <td>
                                        <?= htmlspecialchars($p['business_name']) ?>
                                        <?php if ($p['is_verified']): ?>
                                            <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-map-marker-alt"></i> Location</th>
                                <?php foreach ($products as $p): ?>
                                    <td><?= htmlspecialchars($p['location'] ?: 'Dar es Salaam') ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-tags"></i> Category</th>
                                <?php foreach ($products as $p): ?>
                                    <td><?= htmlspecialchars($p['category_name']) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-star"></i> Rating</th>
                                <?php foreach ($products as $p): ?>
                                    <td>
                                        <?php $r = $ratings[$p['product_id']]; ?>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?= $i <= round($r['avg']) ? '' : '-o' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span style="font-size: 11px;">(<?= $r['count'] ?> reviews)</span>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-cubes"></i> Stock Status</th>
                                <?php foreach ($products as $p): ?>
                                    <td>
                                        <?php if ($p['quantity_in_stock'] > 10): ?>
                                            <span class="stock-badge stock-in"><i class="fas fa-check-circle"></i> In Stock (<?= $p['quantity_in_stock'] ?> left)</span>
                                        <?php elseif ($p['quantity_in_stock'] > 0): ?>
                                            <span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> Low Stock (<?= $p['quantity_in_stock'] ?> left)</span>
                                        <?php else: ?>
                                            <span class="stock-badge stock-out"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-weight-hanging"></i> Unit</th>
                                <?php foreach ($products as $p): ?>
                                    <td><?= ucfirst($p['unit'] ?? 'Piece') ?>(s)</td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-align-left"></i> Description</th>
                                <?php foreach ($products as $p): ?>
                                    <td style="text-align: left;">
                                        <?= htmlspecialchars(substr($p['description'] ?? '', 0, 120)) . (strlen($p['description'] ?? '') > 120 ? '…' : '') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr><th><i class="fas fa-shopping-cart"></i> Actions</th>
                                <?php foreach ($products as $p): ?>
                                    <td>
                                        <?php if ($p['quantity_in_stock'] > 0): ?>
                                            <a href="../cart/add.php?product_id=<?= $p['product_id'] ?>&quantity=1" class="btn-cart"><i class="fas fa-cart-plus"></i> Cart</a>
                                            <a href="details.php?id=<?= $p['product_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                                        <?php else: ?>
                                            <span class="stock-badge stock-out">Out of Stock</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Winner / Best Value Card -->
                <?php if (count($products) > 1): 
                    $best_value = null;
                    $best_score = 0;
                    foreach ($products as $p) {
                        $rating = $ratings[$p['product_id']]['avg'] ?? 2.5;
                        $price_score = (1000000 / ($p['price'] + 1)) * 10;
                        $rating_score = $rating * 20;
                        $total_score = $price_score + $rating_score;
                        if ($total_score > $best_score) {
                            $best_score = $total_score;
                            $best_value = $p;
                        }
                    }
                ?>
                <div class="winner-card">
                    <h3><i class="fas fa-trophy" style="color: #f59e0b;"></i> Best Value Recommendation</h3>
                    <div class="winner-name">🏆 <?= htmlspecialchars($best_value['name']) ?></div>
                    <p style="margin-top: 8px;">
                        Best balance of <strong>Price</strong> and <strong>Quality</strong> based on <?= $ratings[$best_value['product_id']]['count'] ?> reviews
                    </p>
                    <a href="details.php?id=<?= $best_value['product_id'] ?>" class="btn-cart" style="background: #92400e;">View This Product</a>
                </div>
                <?php endif; ?>
                
                <div class="info-note">
                    <i class="fas fa-info-circle" style="color: #e67e22;"></i>
                    You can compare up to 4 products. 
                    <a href="index.php" style="color: #e67e22;">Add more products</a> to continue shopping.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>