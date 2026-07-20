<?php
// customer/products/recently-viewed.php
require_once '../../config/database.php';
session_start();

// Redirect if not customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

// Handle clear history
if (isset($_GET['clear'])) {
    unset($_SESSION['recently_viewed']);
    $_SESSION['flash_message'] = "Recently viewed history cleared!";
    $_SESSION['flash_type'] = "success";
    header("Location: recently-viewed.php");
    exit();
}

$recent_products = [];
if (isset($_SESSION['recently_viewed']) && !empty($_SESSION['recently_viewed'])) {
    $ids = $_SESSION['recently_viewed'];
    $ids = array_slice($ids, 0, 20);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $sql = "SELECT p.*, b.business_name, b.is_verified, c.name as category_name
            FROM products p 
            JOIN businesses b ON p.business_id = b.business_id 
            JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id IN ($placeholders) AND p.is_available = 1";
    
    $stmt = mysqli_prepare($conn, $sql);
    $types = str_repeat('i', count($ids));
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $product_map = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                       FROM reviews WHERE product_id = ? AND status = 'approved'";
        $rating_stmt = mysqli_prepare($conn, $rating_sql);
        mysqli_stmt_bind_param($rating_stmt, 'i', $row['product_id']);
        mysqli_stmt_execute($rating_stmt);
        $rating_res = mysqli_stmt_get_result($rating_stmt);
        $rating_data = mysqli_fetch_assoc($rating_res);
        mysqli_stmt_close($rating_stmt);
        
        $row['avg_rating'] = round($rating_data['avg_rating'] ?? 0, 1);
        $row['review_count'] = (int)($rating_data['review_count'] ?? 0);
        $product_map[$row['product_id']] = $row;
    }
    mysqli_stmt_close($stmt);
    
    foreach ($ids as $pid) {
        if (isset($product_map[$pid])) {
            $recent_products[] = $product_map[$pid];
        }
    }
}

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

function getProductImage($imagePath) {
    if (empty($imagePath)) return '../../assets/images/default-product.jpg';
    return '../../' . ltrim($imagePath, './');
}

include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recently Viewed | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .customer-content { padding: 30px 35px; min-height: 100vh; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        .page-header h1 { font-size: 28px; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 13px; margin-top: 5px; }
        
        .btn-back, .btn-clear {
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back { background: #2c3e50; color: white; }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        .btn-clear { background: #e74c3c; color: white; margin-left: 10px; }
        .btn-clear:hover { background: #c0392b; transform: translateY(-2px); }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        
        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: 0.3s;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-3px);
            border-color: #e67e22;
            box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1);
        }
        .product-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        .product-info { padding: 12px; }
        .product-category {
            font-size: 10px;
            font-weight: 600;
            color: #e67e22;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .product-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 34px;
        }
        .rating-mini {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .rating-stars-mini { color: #f39c12; }
        .product-price {
            font-size: 15px;
            font-weight: 800;
            color: #e67e22;
            margin: 8px 0;
        }
        .business-name {
            font-size: 10px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .verified-badge {
            background: #dbeafe;
            color: #2563eb;
            padding: 2px 6px;
            border-radius: 20px;
            font-size: 9px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
        }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 15px; }
        .empty-state h3 { font-size: 20px; margin-bottom: 8px; }
        .empty-state p { color: #64748b; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .customer-content { padding: 20px; }
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .product-card img { height: 120px; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-history"></i> Recently Viewed</h1>
            <p>Products you've recently browsed</p>
        </div>
        <div>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Continue Shopping</a>
            <?php if (!empty($recent_products)): ?>
                <a href="?clear=1" class="btn-clear" onclick="return confirm('Clear history?')"><i class="fas fa-trash"></i> Clear</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($flash_message): ?>
        <div class="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($flash_message); ?></div>
    <?php endif; ?>

    <?php if (empty($recent_products)): ?>
        <div class="empty-state">
            <i class="fas fa-eye-slash"></i>
            <h3>No recently viewed products</h3>
            <p>Products you view will appear here</p>
            <a href="index.php" class="btn-back" style="background: #e67e22;">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="products-grid">
            <?php foreach ($recent_products as $product): ?>
                <div class="product-card" onclick="location.href='details.php?id=<?= $product['product_id'] ?>'">
                    <img src="<?= getProductImage($product['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                    <div class="product-info">
                        <div class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Product') ?></div>
                        <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="rating-mini">
                            <span class="rating-stars-mini">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($product['avg_rating']) ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span>(<?= $product['review_count'] ?> reviews)</span>
                        </div>
                        <div class="product-price">TSh <?= number_format($product['price']) ?></div>
                        <div class="business-name">
                            <i class="fas fa-store"></i> <?= htmlspecialchars($product['business_name']) ?>
                            <?php if($product['is_verified']): ?>
                                <span class="verified-badge">Verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="btn-back" style="background: #e67e22;">Browse More Products</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>