<?php
// customer/products/quick-view.php - QUICK VIEW MODAL
require_once '../../config/database.php';
session_start();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT p.*, b.business_name, b.location, b.is_verified, c.name as category_name 
        FROM products p
        JOIN businesses b ON p.business_id = b.business_id
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.product_id = $product_id AND p.is_available = 1";
$result = mysqli_query($conn, $sql);
$product = mysqli_fetch_assoc($result);

if(!$product) {
    echo '<div style="padding: 40px; text-align: center; background: white; border-radius: 20px;">
            <i class="fas fa-box-open" style="font-size: 48px; color: #cbd5e1;"></i>
            <p style="margin-top: 15px;">Product not found</p>
          </div>';
    exit();
}

// Get product rating
$rating_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = $product_id AND status = 'approved'";
$rating_result = mysqli_query($conn, $rating_sql);
$rating_data = mysqli_fetch_assoc($rating_result);
$avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
$total_reviews = $rating_data['total'] ?? 0;

// Get related products
$related_sql = "SELECT p.product_id, p.name, p.price, p.image_url 
                FROM products p
                WHERE p.category_id = {$product['category_id']} 
                AND p.product_id != $product_id
                AND p.is_available = 1
                LIMIT 3";
$related_result = mysqli_query($conn, $related_sql);

// Product image
$img_src = '../../assets/images/default-product.jpg';
if(!empty($product['image_url'])) {
    if(file_exists('../../' . $product['image_url'])) {
        $img_src = '../../' . $product['image_url'];
    } elseif(file_exists($product['image_url'])) {
        $img_src = $product['image_url'];
    }
}

// Check wishlist status
$in_wishlist = false;
if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer') {
    $user_id = $_SESSION['user_id'];
    $customer_sql = "SELECT customer_id FROM customers WHERE user_id = $user_id";
    $customer_result = mysqli_query($conn, $customer_sql);
    if(mysqli_num_rows($customer_result) > 0) {
        $customer_data = mysqli_fetch_assoc($customer_result);
        $customer_id = $customer_data['customer_id'];
        $wishlist_sql = "SELECT * FROM wishlist WHERE product_id = $product_id AND customer_id = $customer_id";
        $wishlist_result = mysqli_query($conn, $wishlist_sql);
        $in_wishlist = mysqli_num_rows($wishlist_result) > 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick View - <?php echo htmlspecialchars($product['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: rgba(0,0,0,0.5); }
        
        .quick-view-container {
            background: white;
            border-radius: 20px;
            max-width: 850px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .quick-content {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .quick-image {
            flex: 1;
            min-width: 180px;
        }
        .quick-image img {
            width: 100%;
            border-radius: 14px;
            object-fit: cover;
            aspect-ratio: 1/1;
        }
        
        .quick-info {
            flex: 1.5;
            min-width: 240px;
        }
        .quick-info h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        
        .quick-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .quick-rating {
            color: #f39c12;
            font-size: 12px;
        }
        .quick-rating span {
            color: #64748b;
            margin-left: 5px;
        }
        .quick-category {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            color: #64748b;
        }
        .verified-badge {
            background: #dbeafe;
            color: #2563eb;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
        }
        
        .quick-business {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quick-business i {
            color: #e67e22;
            width: 14px;
        }
        
        .quick-price {
            font-size: 24px;
            font-weight: 800;
            color: #e67e22;
            margin: 12px 0;
        }
        
        .quick-stock {
            margin: 8px 0;
            padding: 4px 10px;
            display: inline-block;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .stock-in { background: #d1fae5; color: #059669; }
        .stock-low { background: #fef3c7; color: #d97706; }
        .stock-out { background: #fee2e2; color: #dc2626; }
        
        .quick-description {
            color: #475569;
            line-height: 1.4;
            margin: 12px 0;
            font-size: 12px;
        }
        
        .quantity-section {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0;
        }
        .quantity-label {
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
        }
        .quantity-select {
            width: 70px;
            padding: 8px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
        }
        .quantity-select:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .quick-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .btn-cart {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 13px;
        }
        .btn-cart:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-view {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 13px;
        }
        .btn-view:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .btn-wishlist {
            background: white;
            border: 2px solid #e2e8f0;
            color: #1e293b;
            padding: 10px 18px;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 13px;
        }
        .btn-wishlist:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }
        .btn-wishlist.active {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }
        .btn-cart-disabled {
            background: #94a3b8;
            cursor: not-allowed;
            padding: 10px 20px;
            border-radius: 12px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .related-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .related-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .related-grid {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        .related-item {
            min-width: 110px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        .related-item:hover {
            transform: translateY(-2px);
            border-color: #e67e22;
        }
        .related-item img {
            width: 100%;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
        }
        .related-item h6 {
            font-size: 10px;
            margin-top: 6px;
            margin-bottom: 3px;
        }
        .related-price {
            font-size: 10px;
            font-weight: 700;
            color: #e67e22;
        }
        
        @media (max-width: 600px) {
            .quick-view-container { padding: 15px; }
            .quick-content { flex-direction: column; }
            .quick-actions { flex-direction: column; }
            .btn-cart, .btn-view, .btn-wishlist { justify-content: center; }
        }
    </style>
</head>
<body>
<div class="quick-view-container">
    <div class="quick-content">
        <div class="quick-image">
            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='../../assets/images/default-product.jpg'">
        </div>
        <div class="quick-info">
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            
            <div class="quick-meta">
                <div class="quick-rating">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                    <span>(<?php echo $total_reviews; ?> reviews)</span>
                </div>
                <div class="quick-category">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                </div>
                <?php if($product['is_verified']): ?>
                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
                <?php endif; ?>
            </div>
            
            <div class="quick-business">
                <i class="fas fa-store"></i> <?php echo htmlspecialchars($product['business_name']); ?>
                <i class="fas fa-map-marker-alt" style="margin-left: 6px;"></i> <?php echo htmlspecialchars($product['location'] ?: 'Dar es Salaam'); ?>
            </div>
            
            <div class="quick-price">TSh <?php echo number_format($product['price'], 0, '.', ','); ?></div>
            
            <div class="quick-stock <?php 
                echo $product['quantity_in_stock'] > 10 ? 'stock-in' : ($product['quantity_in_stock'] > 0 ? 'stock-low' : 'stock-out'); 
            ?>">
                <?php 
                if($product['quantity_in_stock'] > 10) {
                    echo '<i class="fas fa-check-circle"></i> In Stock (' . $product['quantity_in_stock'] . ' units)';
                } elseif($product['quantity_in_stock'] > 0) {
                    echo '<i class="fas fa-exclamation-triangle"></i> Low Stock - ' . $product['quantity_in_stock'] . ' left';
                } else {
                    echo '<i class="fas fa-times-circle"></i> Out of Stock';
                }
                ?>
            </div>
            
            <div class="quick-description">
                <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 100)) . (strlen($product['description'] ?? '') > 100 ? '...' : ''); ?>
            </div>
            
            <?php if($product['quantity_in_stock'] > 0): ?>
            <div class="quantity-section">
                <span class="quantity-label">Qty:</span>
                <input type="number" id="quickQty" class="quantity-select" value="1" min="1" max="<?php echo $product['quantity_in_stock']; ?>">
            </div>
            
            <div class="quick-actions">
                <form method="POST" action="../cart/add.php" style="display: inline;">
                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                    <input type="hidden" name="quantity" id="cartQty" value="1">
                    <button type="submit" class="btn-cart" onclick="document.getElementById('cartQty').value = document.getElementById('quickQty').value">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </form>
                
                <a href="details.php?id=<?php echo $product['product_id']; ?>" class="btn-view">
                    <i class="fas fa-eye"></i> Details
                </a>
                
                <a href="../wishlist/add.php?id=<?php echo $product['product_id']; ?>" class="btn-wishlist <?php echo $in_wishlist ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i> 
                    <?php echo $in_wishlist ? 'Saved' : 'Wishlist'; ?>
                </a>
            </div>
            <?php else: ?>
            <div class="quick-actions">
                <span class="btn-cart-disabled">
                    <i class="fas fa-times-circle"></i> Out of Stock
                </span>
                <a href="details.php?id=<?php echo $product['product_id']; ?>" class="btn-view">
                    <i class="fas fa-eye"></i> Details
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Related Products -->
            <?php if(mysqli_num_rows($related_result) > 0): ?>
            <div class="related-section">
                <div class="related-title">
                    <i class="fas fa-fire" style="color: #e67e22;"></i> You May Also Like
                </div>
                <div class="related-grid">
                    <?php while($related = mysqli_fetch_assoc($related_result)): 
                        $rel_img = '../../assets/images/default-product.jpg';
                        if(!empty($related['image_url']) && file_exists('../../' . $related['image_url'])) {
                            $rel_img = '../../' . $related['image_url'];
                        }
                    ?>
                    <div class="related-item" onclick="location.href='details.php?id=<?php echo $related['product_id']; ?>'">
                        <img src="<?php echo $rel_img; ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                        <h6><?php echo htmlspecialchars(substr($related['name'], 0, 20)); ?></h6>
                        <div class="related-price">TSh <?php echo number_format($related['price']); ?></div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Update cart quantity before submit
document.getElementById('quickQty')?.addEventListener('change', function() {
    let qtyInput = document.getElementById('cartQty');
    if(qtyInput) {
        qtyInput.value = this.value;
    }
});
</script>
</body>
</html>