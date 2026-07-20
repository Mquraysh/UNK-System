<?php
// customer/products/details.php 
require_once '../../config/database.php';

session_start();

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get product details using prepared statement
$product_sql = "SELECT p.*, b.business_name, b.location, b.address, b.city, b.phone, b.is_verified,
                       c.name as category_name
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.product_id = ? AND p.is_available = 1";
$stmt = mysqli_prepare($conn, $product_sql);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$product_result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($product_result);
mysqli_stmt_close($stmt);

// Record recently viewed
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer' && $product) {
    if (!isset($_SESSION['recently_viewed'])) {
        $_SESSION['recently_viewed'] = [];
    }
    $_SESSION['recently_viewed'] = array_diff($_SESSION['recently_viewed'], [$product_id]);
    array_unshift($_SESSION['recently_viewed'], $product_id);
    $_SESSION['recently_viewed'] = array_slice($_SESSION['recently_viewed'], 0, 10);
}

if (!$product) {
    header("Location: index.php");
    exit();
}

// Get business email
$business_user_sql = "SELECT u.email FROM users u 
                      JOIN businesses b ON u.user_id = b.user_id 
                      WHERE b.business_id = ?";
$stmt = mysqli_prepare($conn, $business_user_sql);
mysqli_stmt_bind_param($stmt, 'i', $product['business_id']);
mysqli_stmt_execute($stmt);
$business_user_result = mysqli_stmt_get_result($stmt);
$business_user = mysqli_fetch_assoc($business_user_result);
$business_email = $business_user['email'] ?? 'Not available';
mysqli_stmt_close($stmt);

// Update views count
$update_views = "UPDATE products SET views = views + 1 WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $update_views);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Get similar products (same category)
$similar_sql = "SELECT p.*, b.business_name 
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                WHERE p.category_id = ? 
                AND p.product_id != ?
                AND p.is_available = 1
                ORDER BY ABS(p.price - ?) ASC
                LIMIT 8";
$stmt = mysqli_prepare($conn, $similar_sql);
mysqli_stmt_bind_param($stmt, 'iid', $product['category_id'], $product_id, $product['price']);
mysqli_stmt_execute($stmt);
$similar_result = mysqli_stmt_get_result($stmt);
$similar_products = [];
while($row = mysqli_fetch_assoc($similar_result)) {
    $similar_products[] = $row;
}
mysqli_stmt_close($stmt);

// Get budget friendly alternatives (30% cheaper or more)
$budget_sql = "SELECT p.*, b.business_name, 
                      ((? - p.price) / ?) * 100 as discount_percent
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                WHERE p.category_id = ? 
                AND p.price < ? * 0.7
                AND p.product_id != ?
                AND p.is_available = 1
                ORDER BY p.price ASC
                LIMIT 4";
$stmt = mysqli_prepare($conn, $budget_sql);
$price_70_percent = $product['price'] * 0.7;
mysqli_stmt_bind_param($stmt, 'ddiid', $product['price'], $product['price'], $product['category_id'], $product['price'], $product_id);
mysqli_stmt_execute($stmt);
$budget_result = mysqli_stmt_get_result($stmt);
$budget_alternatives = [];
while($row = mysqli_fetch_assoc($budget_result)) {
    $budget_alternatives[] = $row;
}
mysqli_stmt_close($stmt);

// Get competitor products (better prices)
$competitor_sql = "SELECT p.*, b.business_name 
                   FROM products p
                   JOIN businesses b ON p.business_id = b.business_id
                   WHERE p.category_id = ? 
                   AND p.price < ?
                   AND p.product_id != ?
                   AND p.is_available = 1
                   ORDER BY p.price ASC
                   LIMIT 4";
$stmt = mysqli_prepare($conn, $competitor_sql);
mysqli_stmt_bind_param($stmt, 'idi', $product['category_id'], $product['price'], $product_id);
mysqli_stmt_execute($stmt);
$competitor_result = mysqli_stmt_get_result($stmt);
$competitors = [];
while($row = mysqli_fetch_assoc($competitor_result)) {
    $competitors[] = $row;
}
mysqli_stmt_close($stmt);

// Get premium alternatives (higher price)
$premium_sql = "SELECT p.*, b.business_name 
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                WHERE p.category_id = ? 
                AND p.price > ?
                AND p.product_id != ?
                AND p.is_available = 1
                ORDER BY p.price ASC
                LIMIT 4";
$stmt = mysqli_prepare($conn, $premium_sql);
mysqli_stmt_bind_param($stmt, 'idi', $product['category_id'], $product['price'], $product_id);
mysqli_stmt_execute($stmt);
$premium_result = mysqli_stmt_get_result($stmt);
$premium_alternatives = [];
while($row = mysqli_fetch_assoc($premium_result)) {
    $premium_alternatives[] = $row;
}
mysqli_stmt_close($stmt);

// Get product reviews
$reviews_sql = "SELECT r.*, c.first_name, c.last_name 
                FROM reviews r
                JOIN customers c ON r.customer_id = c.customer_id
                WHERE r.product_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC";
$stmt = mysqli_prepare($conn, $reviews_sql);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$reviews_result = mysqli_stmt_get_result($stmt);
$reviews = [];
while($row = mysqli_fetch_assoc($reviews_result)) {
    $reviews[] = $row;
}
mysqli_stmt_close($stmt);

// Calculate average rating
$avg_rating = 0;
$total_reviews = count($reviews);
if($total_reviews > 0) {
    $rating_sum = 0;
    foreach($reviews as $rev) {
        $rating_sum += $rev['rating'];
    }
    $avg_rating = round($rating_sum / $total_reviews, 1);
}

// Get rating distribution
$rating_counts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
foreach($reviews as $rev) {
    $rating_counts[$rev['rating']]++;
}

// Get price history
$price_history_sql = "SELECT price, recorded_at FROM price_history WHERE product_id = ? ORDER BY recorded_at DESC LIMIT 10";
$stmt = mysqli_prepare($conn, $price_history_sql);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$price_history_result = mysqli_stmt_get_result($stmt);
$price_history = [];
while($row = mysqli_fetch_assoc($price_history_result)) {
    $price_history[] = $row;
}
mysqli_stmt_close($stmt);

// Check if user already reviewed
$user_reviewed = false;
$user_rating = null;
$user_comment = null;
$customer_id = null;

if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer') {
    $user_id = $_SESSION['user_id'];
    $customer_sql = "SELECT customer_id FROM customers WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $customer_sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $customer_result = mysqli_stmt_get_result($stmt);
    if($customer_data = mysqli_fetch_assoc($customer_result)) {
        $customer_id = $customer_data['customer_id'];
    }
    mysqli_stmt_close($stmt);
    
    if($customer_id) {
        $check_review_sql = "SELECT * FROM reviews WHERE product_id = ? AND customer_id = ?";
        $stmt = mysqli_prepare($conn, $check_review_sql);
        mysqli_stmt_bind_param($stmt, 'ii', $product_id, $customer_id);
        mysqli_stmt_execute($stmt);
        $check_review_result = mysqli_stmt_get_result($stmt);
        if(mysqli_num_rows($check_review_result) > 0) {
            $user_reviewed = true;
            $existing_review = mysqli_fetch_assoc($check_review_result);
            $user_rating = $existing_review['rating'];
            $user_comment = $existing_review['comment'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Check if product is in wishlist
$in_wishlist = false;
if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer' && $customer_id) {
    $wishlist_sql = "SELECT * FROM wishlist WHERE product_id = ? AND customer_id = ?";
    $stmt = mysqli_prepare($conn, $wishlist_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $wishlist_result = mysqli_stmt_get_result($stmt);
    $in_wishlist = mysqli_num_rows($wishlist_result) > 0;
    mysqli_stmt_close($stmt);
}

// Get flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 2px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s ease;
        }
        
        .breadcrumb { margin-bottom: 25px; }
        .breadcrumb a { color: #64748b; text-decoration: none; font-size: 13px; }
        .breadcrumb a:hover { color: #e67e22; }
        .breadcrumb span { color: #1e293b; font-size: 13px; }
        
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
        .alert-info { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        .product-main {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: white;
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid #e2e8f0;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            border-radius: 20px;
            overflow: hidden;
            background: #f8fafc;
        }
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info-detail h1 { font-size: 28px; font-weight: 700; color: #1e293b; margin-bottom: 10px; }
        .product-meta { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .rating { color: #f39c12; font-size: 14px; }
        .rating span { color: #64748b; margin-left: 8px; }
        .category { background: #f1f5f9; padding: 4px 12px; border-radius: 30px; font-size: 12px; }
        .verified-badge { display: inline-block; background: #dbeafe; color: #2563eb; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .price { font-size: 32px; font-weight: 800; color: #e67e22; margin-bottom: 20px; }
        .description { color: #475569; line-height: 1.6; margin-bottom: 25px; }
        
        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 40px;
            margin-bottom: 20px;
        }
        .stock-in { background: #d1fae5; color: #059669; }
        .stock-low { background: #fef3c7; color: #d97706; }
        .stock-out { background: #fee2e2; color: #dc2626; }
        
        .quantity-section { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .quantity-label { font-weight: 600; color: #1e293b; }
        .quantity-input { display: flex; align-items: center; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .quantity-btn { width: 40px; height: 40px; background: #f8fafc; border: none; cursor: pointer; font-size: 18px; transition: all 0.3s; }
        .quantity-btn:hover { background: #e67e22; color: white; }
        .quantity-input input { width: 60px; height: 40px; text-align: center; border: none; font-size: 16px; font-weight: 600; }
        
        .action-buttons { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .btn-cart {
            flex: 2;
            padding: 14px 24px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-cart:hover { background: #d35400; transform: translateY(-2px); }
        .btn-wishlist {
            flex: 1;
            padding: 14px 24px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            color: #1e293b;
        }
        .btn-wishlist:hover { border-color: #e74c3c; color: #e74c3c; transform: translateY(-2px); }
        .btn-wishlist.active { background: #e74c3c; color: white; border-color: #e74c3c; }
        .btn-compare {
            flex: 1;
            padding: 14px 20px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-compare:hover { background: #1a252f; transform: translateY(-2px); }
        
        .business-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
        }
        .business-card h4 { font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .business-card p { font-size: 13px; color: #475569; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .business-card i { width: 20px; color: #e67e22; }
        
        .product-tabs {
            background: white;
            border-radius: 24px;
            margin-bottom: 40px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .tabs-nav {
            display: flex;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            overflow-x: auto;
        }
        .tab-btn {
            padding: 16px 24px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .tab-btn:hover { color: #e67e22; background: #f1f5f9; }
        .tab-btn.active {
            color: #e67e22;
            border-bottom: 2px solid #e67e22;
            background: white;
        }
        .tab-content {
            padding: 30px;
            display: none;
        }
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .rating-summary {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        .avg-rating { text-align: center; }
        .avg-rating .big-rating { font-size: 48px; font-weight: 800; color: #f39c12; }
        .rating-bars { flex: 1; min-width: 200px; }
        .rating-bar-item { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .rating-bar-item span { width: 45px; font-size: 12px; }
        .rating-bar { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; }
        .rating-bar-fill { height: 100%; background: #f39c12; border-radius: 3px; }
        
        .review-card {
            border-bottom: 1px solid #eef2f6;
            padding: 20px 0;
        }
        .review-card:last-child { border-bottom: none; }
        .reviewer { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .reviewer-avatar {
            width: 40px;
            height: 40px;
            background: #e67e22;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .reviewer-name { font-weight: 600; color: #1e293b; }
        .review-date { font-size: 11px; color: #94a3b8; }
        .review-rating { color: #f39c12; font-size: 12px; margin-bottom: 8px; }
        .review-comment { color: #475569; line-height: 1.5; }
        
        .star-rating { display: flex; gap: 5px; margin-bottom: 15px; }
        .star-rating i { font-size: 28px; cursor: pointer; color: #cbd5e1; transition: all 0.2s; }
        .star-rating i:hover, .star-rating i.active { color: #f39c12; }
        .review-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-family: inherit;
            resize: vertical;
            margin-bottom: 15px;
        }
        .review-textarea:focus { outline: none; border-color: #e67e22; }
        
        .deals-grid, .alternatives-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .deal-card, .alt-card {
            background: #f8fafc;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }
        .deal-card:hover, .alt-card:hover { transform: translateY(-3px); border-color: #e67e22; }
        .deal-card .save-amount { color: #059669; font-weight: 700; font-size: 13px; margin-top: 8px; }
        .alt-card .discount-badge { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 20px; font-size: 10px; display: inline-block; margin-top: 5px; }
        
        .similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        .similar-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }
        .similar-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1); border-color: #e67e22; }
        .similar-card img { width: 100%; height: 150px; object-fit: cover; }
        .similar-info { padding: 12px; }
        .similar-info h4 { font-size: 13px; margin-bottom: 5px; }
        .similar-price { font-size: 14px; font-weight: 700; color: #e67e22; }
        
        .price-chart-container { margin-bottom: 20px; }
        .price-history-table { width: 100%; border-collapse: collapse; }
        .price-history-table th, .price-history-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eef2f6; }
        
        .write-review { margin-top: 30px; padding-top: 25px; border-top: 1px solid #e2e8f0; }
        
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #e67e22;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            z-index: 100;
        }
        .back-to-top.active { opacity: 1; visibility: visible; }
        .back-to-top:hover { background: #d35400; transform: translateY(-3px); }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e67e22;
            display: inline-block;
        }
        
        @media (max-width: 1024px) {
            .customer-content { padding: 20px; }
            .product-main { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .main-image { height: 300px; }
            .action-buttons { flex-direction: column; }
            .tab-btn { padding: 12px 16px; font-size: 12px; }
            .tab-content { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="index.php">Home</a> / 
        <a href="index.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> / 
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </div>
    
    <!-- Flash Message -->
    <?php if(!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Product Main Section -->
    <div class="product-main">
        <div class="product-gallery">
            <div class="main-image">
                <?php 
                $img_src = '../../assets/images/default-product.jpg';
                if(!empty($product['image_url'])) {
                    if(file_exists('../../' . $product['image_url'])) {
                        $img_src = '../../' . $product['image_url'];
                    } elseif(file_exists($product['image_url'])) {
                        $img_src = $product['image_url'];
                    }
                }
                ?>
                <img src="<?php echo $img_src; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     onerror="this.src='../../assets/images/default-product.jpg'">
            </div>
        </div>
        
        <div class="product-info-detail">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-meta">
                <div class="rating">
                    <?php for($i=1; $i<=5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                    <span>(<?php echo $total_reviews; ?> reviews)</span>
                </div>
                <div class="category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></div>
                <?php if($product['is_verified']): ?>
                    <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified Seller</span>
                <?php endif; ?>
            </div>
            
            <div class="price">TSh <?php echo number_format($product['price'], 0, '.', ','); ?></div>
            
            <div class="description">
                <?php echo nl2br(htmlspecialchars($product['description'] ?: 'No description available for this product.')); ?>
            </div>
            
            <div class="stock-status <?php 
                echo $product['quantity_in_stock'] > 10 ? 'stock-in' : ($product['quantity_in_stock'] > 0 ? 'stock-low' : 'stock-out'); 
            ?>">
                <i class="fas <?php echo $product['quantity_in_stock'] > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                <?php 
                if($product['quantity_in_stock'] > 10) {
                    echo 'In Stock (' . $product['quantity_in_stock'] . ' units available)';
                } elseif($product['quantity_in_stock'] > 0) {
                    echo 'Low Stock - Only ' . $product['quantity_in_stock'] . ' left!';
                } else {
                    echo 'Out of Stock';
                }
                ?>
            </div>
            
            <?php if($product['quantity_in_stock'] > 0): ?>
            <div class="quantity-section">
                <span class="quantity-label">Quantity:</span>
                <div class="quantity-input">
                    <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                    <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['quantity_in_stock']; ?>">
                    <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                </div>
                <span style="font-size: 12px; color: #64748b;"><?php echo $product['quantity_in_stock']; ?> units available</span>
            </div>
            
            <div class="action-buttons">
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'customer'): ?>
                    <button class="btn-cart" onclick="addToCart()">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                <?php else: ?>
                    <a href="../login.php" class="btn-cart" style="text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Login to Buy
                    </a>
                <?php endif; ?>
                
                <a href="../wishlist/add.php?id=<?php echo $product_id; ?>" class="btn-wishlist <?php echo $in_wishlist ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <?php echo $in_wishlist ? 'Saved' : 'Wishlist'; ?>
                </a>
                
                <button class="btn-compare" onclick="addToCompare()">
                    <i class="fas fa-chart-line"></i> Compare
                </button>
            </div>
            <?php endif; ?>
            
            <div class="business-card">
                <h4><i class="fas fa-store"></i> <?php echo htmlspecialchars($product['business_name']); ?></h4>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($product['location'] ?: $product['address']); ?>, <?php echo htmlspecialchars($product['city']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($product['phone']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($business_email); ?></p>
            </div>
        </div>
    </div>
    
    <!-- TABS SECTION -->
    <div class="product-tabs">
        <div class="tabs-nav">
            <button class="tab-btn active" data-tab="specs">
                <i class="fas fa-microchip"></i> Specifications
            </button>
            <button class="tab-btn" data-tab="reviews">
                <i class="fas fa-star"></i> Reviews (<?php echo $total_reviews; ?>)
            </button>
            <button class="tab-btn" data-tab="deals">
                <i class="fas fa-tag"></i> Best Deals
                <?php if(count($competitors) > 0): ?>
                    <span style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 20px; font-size: 10px;"><?php echo count($competitors); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" data-tab="similar">
                <i class="fas fa-th-large"></i> Similar Products
            </button>
            <button class="tab-btn" data-tab="price-history">
                <i class="fas fa-chart-line"></i> Price History
            </button>
        </div>
        
        <!-- Tab 1: Specifications -->
        <div class="tab-content active" id="tab-specs">
            <table style="width: 100%; border-collapse: collapse;">
                <tr><td style="padding: 12px; font-weight: 600; width: 200px;">Product Name<td style="padding: 12px;"><?php echo htmlspecialchars($product['name']); ?> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">Category<td style="padding: 12px;"><?php echo htmlspecialchars($product['category_name']); ?> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">Price<td style="padding: 12px;"><span style="color: #e67e22; font-weight: 700;">TSh <?php echo number_format($product['price'], 0, '.', ','); ?></span> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">Stock<td style="padding: 12px;"><?php if($product['quantity_in_stock'] > 0): ?><span style="color: #059669;"><?php echo $product['quantity_in_stock']; ?> units available</span><?php else: ?><span style="color: #dc2626;">Out of Stock</span><?php endif; ?> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">Seller<td style="padding: 12px;"><?php echo htmlspecialchars($product['business_name']); ?> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">Location<td style="padding: 12px;"><?php echo htmlspecialchars($product['location'] ?: $product['address']); ?>, <?php echo htmlspecialchars($product['city']); ?> </tr>
                <tr><td style="padding: 12px; font-weight: 600;">SKU<td style="padding: 12px;">UNK-<?php echo str_pad($product['product_id'], 6, '0', STR_PAD_LEFT); ?> </tr>
                <?php if($product['description']): ?>
                <tr><td style="padding: 12px; font-weight: 600;">Description<td style="padding: 12px;"><?php echo nl2br(htmlspecialchars($product['description'])); ?> </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- Tab 2: Reviews -->
        <div class="tab-content" id="tab-reviews">
            <div class="rating-summary">
                <div class="avg-rating">
                    <div class="big-rating"><?php echo $avg_rating; ?></div>
                    <div class="rating"><?php for($i=1; $i<=5; $i++): ?><i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i><?php endfor; ?></div>
                    <div><?php echo $total_reviews; ?> reviews</div>
                </div>
                <div class="rating-bars">
                    <?php for($i=5; $i>=1; $i--): ?>
                    <div class="rating-bar-item">
                        <span><?php echo $i; ?> <i class="fas fa-star"></i></span>
                        <div class="rating-bar"><div class="rating-bar-fill" style="width: <?php echo $total_reviews > 0 ? ($rating_counts[$i]/$total_reviews)*100 : 0; ?>%"></div></div>
                        <span><?php echo $rating_counts[$i]; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <?php if(empty($reviews)): ?>
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-comment" style="font-size: 48px; color: #cbd5e1;"></i>
                    <p style="margin-top: 10px; color: #64748b;">No reviews yet. Be the first to review this product!</p>
                </div>
            <?php else: ?>
                <?php foreach($reviews as $review): ?>
                <div class="review-card">
                    <div class="reviewer">
                        <div class="reviewer-avatar"><?php echo strtoupper(substr($review['first_name'], 0, 1)); ?></div>
                        <div>
                            <div class="reviewer-name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                            <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                        </div>
                    </div>
                    <div class="review-rating"><?php for($i=1; $i<=5; $i++): ?><i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i><?php endfor; ?></div>
                    <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer'): ?>
                <?php if($user_reviewed): ?>
                    <div class="write-review"><div class="alert alert-info"><i class="fas fa-info-circle"></i> You have already reviewed this product. Thank you for your feedback!</div></div>
                <?php else: ?>
                    <div class="write-review">
                        <h3 style="margin-bottom: 15px;">Write a Review</h3>
                        <form method="POST" action="review-submit.php">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <div class="star-rating" id="starRating">
                                <i class="far fa-star" data-rating="1"></i><i class="far fa-star" data-rating="2"></i>
                                <i class="far fa-star" data-rating="3"></i><i class="far fa-star" data-rating="4"></i>
                                <i class="far fa-star" data-rating="5"></i>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" required>
                            <textarea name="comment" class="review-textarea" rows="4" placeholder="Share your experience with this product..." required></textarea>
                            <button type="submit" name="submit_review" class="btn-cart" style="width: auto; padding: 10px 24px;">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="write-review"><div class="alert alert-info"><i class="fas fa-info-circle"></i><a href="../login.php" style="color: #e67e22;">Login</a> to write a review</div></div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 3: Best Deals -->
        <div class="tab-content" id="tab-deals">
            <h3 class="section-title"><i class="fas fa-tag"></i> Better Prices Available</h3>
            <?php if(count($competitors) > 0): ?>
                <div class="deals-grid">
                    <?php foreach($competitors as $comp):
                        $saved = $product['price'] - $comp['price'];
                        $comp_img = '../../assets/images/default-product.jpg';
                        if(!empty($comp['image_url']) && file_exists('../../' . $comp['image_url'])) {
                            $comp_img = '../../' . $comp['image_url'];
                        }
                    ?>
                    <div class="deal-card" onclick="location.href='details.php?id=<?php echo $comp['product_id']; ?>'">
                        <img src="<?php echo $comp_img; ?>" style="width: 100%; height: 120px; object-fit: cover; border-radius: 12px;">
                        <h4 style="margin-top: 10px; font-size: 14px;"><?php echo htmlspecialchars(substr($comp['name'], 0, 30)); ?></h4>
                        <div style="font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($comp['business_name']); ?></div>
                        <div style="font-size: 18px; font-weight: 700; color: #e67e22; margin: 5px 0;">TSh <?php echo number_format($comp['price']); ?></div>
                        <div class="save-amount">Save TSh <?php echo number_format($saved); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No better deals found. Current price is competitive!</div>
            <?php endif; ?>
            
            <!-- Budget Friendly Alternatives -->
            <?php if(count($budget_alternatives) > 0): ?>
            <h3 class="section-title" style="margin-top: 30px;"><i class="fas fa-coins"></i> Budget Friendly Alternatives</h3>
            <div class="alternatives-grid">
                <?php foreach($budget_alternatives as $alt): 
                    $discount = round((($product['price'] - $alt['price']) / $product['price']) * 100);
                    $alt_img = '../../assets/images/default-product.jpg';
                    if(!empty($alt['image_url']) && file_exists('../../' . $alt['image_url'])) {
                        $alt_img = '../../' . $alt['image_url'];
                    }
                ?>
                <div class="alt-card" onclick="location.href='details.php?id=<?php echo $alt['product_id']; ?>'">
                    <img src="<?php echo $alt_img; ?>" style="width: 100%; height: 120px; object-fit: cover; border-radius: 12px;">
                    <h4 style="margin-top: 10px; font-size: 14px;"><?php echo htmlspecialchars(substr($alt['name'], 0, 30)); ?></h4>
                    <div style="font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($alt['business_name']); ?></div>
                    <div style="font-size: 18px; font-weight: 700; color: #27ae60; margin: 5px 0;">TSh <?php echo number_format($alt['price']); ?></div>
                    <div class="discount-badge">Save <?php echo $discount; ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Premium Alternatives -->
            <?php if(count($premium_alternatives) > 0): ?>
            <h3 class="section-title" style="margin-top: 30px;"><i class="fas fa-gem"></i> Premium Alternatives</h3>
            <div class="alternatives-grid">
                <?php foreach($premium_alternatives as $alt): 
                    $alt_img = '../../assets/images/default-product.jpg';
                    if(!empty($alt['image_url']) && file_exists('../../' . $alt['image_url'])) {
                        $alt_img = '../../' . $alt['image_url'];
                    }
                ?>
                <div class="alt-card" onclick="location.href='details.php?id=<?php echo $alt['product_id']; ?>'">
                    <img src="<?php echo $alt_img; ?>" style="width: 100%; height: 120px; object-fit: cover; border-radius: 12px;">
                    <h4 style="margin-top: 10px; font-size: 14px;"><?php echo htmlspecialchars(substr($alt['name'], 0, 30)); ?></h4>
                    <div style="font-size: 13px; color: #64748b;"><?php echo htmlspecialchars($alt['business_name']); ?></div>
                    <div style="font-size: 18px; font-weight: 700; color: #8e44ad; margin: 5px 0;">TSh <?php echo number_format($alt['price']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 30px; background: #fef3c7; border-radius: 16px; padding: 20px; text-align: center;">
                <i class="fas fa-bell" style="font-size: 24px; color: #d97706;"></i>
                <h4 style="margin: 10px 0;">Want a better price?</h4>
                <p>Set a price alert and we'll notify you when this product drops below TSh <?php echo number_format($product['price'] * 0.9); ?></p>
                <button class="btn-cart" style="width: auto; padding: 10px 24px;" onclick="setPriceAlert()">
                    <i class="fas fa-bell"></i> Set Price Alert
                </button>
            </div>
        </div>
        
        <!-- Tab 4: Similar Products -->
        <div class="tab-content" id="tab-similar">
            <h3 class="section-title"><i class="fas fa-th-large"></i> You May Also Like</h3>
            <?php if(count($similar_products) > 0): ?>
                <div class="similar-grid">
                    <?php foreach($similar_products as $similar):
                        $sim_img = '../../assets/images/default-product.jpg';
                        if(!empty($similar['image_url']) && file_exists('../../' . $similar['image_url'])) {
                            $sim_img = '../../' . $similar['image_url'];
                        }
                    ?>
                    <div class="similar-card" onclick="location.href='details.php?id=<?php echo $similar['product_id']; ?>'">
                        <img src="<?php echo $sim_img; ?>" alt="<?php echo htmlspecialchars($similar['name']); ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                        <div class="similar-info">
                            <h4><?php echo htmlspecialchars($similar['name']); ?></h4>
                            <div class="similar-price">TSh <?php echo number_format($similar['price'], 0, '.', ','); ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($similar['business_name']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No similar products found.</div>
            <?php endif; ?>
        </div>
        
        <!-- Tab 5: Price History -->
        <div class="tab-content" id="tab-price-history">
            <h3 class="section-title"><i class="fas fa-chart-line"></i> Price History</h3>
            <?php if(count($price_history) > 0): ?>
                <div class="price-chart-container"><canvas id="priceChart" style="max-height: 300px; width: 100%;"></canvas></div>
                <table class="price-history-table">
                    <thead><tr><th>Date</th><th>Price (TSh)</th><th>Change</th>弯</thead>
                    <tbody>
                        <?php $prev_price = $product['price']; $chart_labels = []; $chart_prices = [];
                        foreach($price_history as $hist):
                            $change = $hist['price'] - $prev_price;
                            $change_class = $change > 0 ? 'text-danger' : ($change < 0 ? 'text-success' : 'text-muted');
                            $chart_labels[] = date('d M', strtotime($hist['recorded_at']));
                            $chart_prices[] = $hist['price'];
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($hist['recorded_at'])); ?></td>
                            <td><strong>TSh <?php echo number_format($hist['price']); ?></strong></td>
                            <td class="<?php echo $change_class; ?>"><?php if($change != 0): ?><?php echo $change > 0 ? '+' : ''; ?>TSh <?php echo number_format(abs($change)); ?><?php else: ?>No change<?php endif; ?></td>
                        </tr>
                        <?php $prev_price = $hist['price']; endforeach; ?>
                        <tr style="background: #f8fafc; font-weight: 700;">
                            <td>Current Price</a><td style="color: #e67e22;">TSh <?php echo number_format($product['price']); ?> </a><td></a>
                        </tr>
                    </tbody>
                </table>
                <div class="alert alert-info mt-3"><i class="fas fa-chart-line"></i> <?php if(count($price_history) >= 2) { $oldest = $price_history[count($price_history)-1]['price']; $newest = $product['price']; if($newest < $oldest) echo "Price has decreased by TSh " . number_format($oldest - $newest) . " 📉"; elseif($newest > $oldest) echo "Price has increased by TSh " . number_format($newest - $oldest) . " 📈"; else echo "Price has remained stable 📊"; } else { echo "Not enough price history data available."; } ?></div>
            <?php else: ?>
                <div class="alert alert-info">No price history available for this product yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="back-to-top" id="backToTop"><i class="fas fa-arrow-up"></i></div>

<script>
function changeQuantity(change) {
    let input = document.getElementById('quantity');
    let current = parseInt(input.value);
    let min = 1;
    let max = <?php echo $product['quantity_in_stock']; ?>;
    let newValue = current + change;
    if(newValue >= min && newValue <= max) input.value = newValue;
}

function addToCart() {
    let quantity = document.getElementById('quantity').value;
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '../cart/add.php';
    form.innerHTML = `<input type="hidden" name="product_id" value="<?php echo $product_id; ?>"><input type="hidden" name="quantity" value="${quantity}">`;
    document.body.appendChild(form);
    form.submit();
}

function addToCompare() { 
    window.location.href = 'compare.php?add=<?php echo $product_id; ?>'; 
}

function setPriceAlert() {
    let desired = prompt('Enter desired price (TSh):\nCurrent: TSh <?php echo number_format($product['price']); ?>', Math.round(<?php echo $product['price']; ?> * 0.8));
    if (desired && desired > 0) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = 'price-alert.php';
        form.innerHTML = `<input type="hidden" name="product_id" value="<?php echo $product_id; ?>"><input type="hidden" name="desired_price" value="${desired}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

// Tabs functionality
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        let tabId = this.getAttribute('data-tab');
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// Star rating for review
const stars = document.querySelectorAll('#starRating i');
if(stars.length > 0) {
    stars.forEach(star => {
        star.addEventListener('click', function() {
            let rating = this.getAttribute('data-rating');
            document.getElementById('ratingValue').value = rating;
            stars.forEach(s => { s.classList.remove('fas'); s.classList.add('far'); });
            for(let i = 1; i <= rating; i++) {
                let targetStar = document.querySelector('#starRating i[data-rating="' + i + '"]');
                if(targetStar) { targetStar.classList.remove('far'); targetStar.classList.add('fas'); }
            }
        });
    });
}

// Price Chart
<?php if(count($price_history) > 0): ?>
const ctx = document.getElementById('priceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: { 
        labels: <?php echo json_encode(array_reverse($chart_labels)); ?>, 
        datasets: [{ 
            label: 'Price (TSh)', 
            data: <?php echo json_encode(array_reverse($chart_prices)); ?>, 
            borderColor: '#e67e22', 
            backgroundColor: 'rgba(230, 126, 34, 0.1)', 
            fill: true, 
            tension: 0.4 
        }] 
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: true, 
        plugins: { 
            tooltip: { 
                callbacks: { 
                    label: function(context) { 
                        return 'TSh ' + context.raw.toLocaleString(); 
                    } 
                } 
            } 
        }, 
        scales: { 
            y: { 
                ticks: { 
                    callback: function(value) { 
                        return 'TSh ' + value.toLocaleString(); 
                    } 
                } 
            } 
        } 
    }
});
<?php endif; ?>

// Back to top
const backToTop = document.getElementById('backToTop');
window.addEventListener('scroll', function() { 
    window.scrollY > 300 ? backToTop.classList.add('active') : backToTop.classList.remove('active'); 
});
backToTop.addEventListener('click', function() { 
    window.scrollTo({ top: 0, behavior: 'smooth' }); 
});
</script>
</body>
</html>
<?php include '../../includes/footer.php'; ?>