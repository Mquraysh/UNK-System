<?php
// customer/reviews/index.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Get reviews
$reviews_sql = "SELECT r.*, p.name as product_name, p.image_url, p.price,
                       b.business_name
                FROM reviews r
                JOIN products p ON r.product_id = p.product_id
                JOIN businesses b ON p.business_id = b.business_id
                WHERE r.customer_id = '$customer_id'
                ORDER BY r.created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_sql);
$reviews = [];
while($row = mysqli_fetch_assoc($reviews_result)) {
    $reviews[] = $row;
}

// Statistics
$total_reviews = count($reviews);
$avg_rating = 0;
$rating_counts = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
$pending_count = 0;
$approved_count = 0;

foreach($reviews as $review) {
    $avg_rating += $review['rating'];
    $rating_counts[$review['rating']]++;
    if($review['status'] == 'pending') $pending_count++;
    if($review['status'] == 'approved') $approved_count++;
}
$avg_rating = $total_reviews > 0 ? round($avg_rating / $total_reviews, 1) : 0;

// Flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reviews - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s ease;
        }
        
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        .page-header h1 i {
            color: #f39c12;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .btn-write {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-write:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        
        /* Rating Summary */
        .rating-summary {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            align-items: center;
            border: 1px solid #e2e8f0;
        }
        .avg-rating {
            text-align: center;
            padding: 15px 25px;
            background: #f8fafc;
            border-radius: 16px;
        }
        .avg-rating h2 {
            font-size: 48px;
            font-weight: 800;
            color: #f39c12;
        }
        .avg-rating .stars {
            color: #f39c12;
            font-size: 16px;
            margin: 8px 0;
        }
        .rating-bars {
            flex: 1;
        }
        .rating-bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .rating-bar-item span {
            width: 35px;
            font-size: 13px;
        }
        .rating-bar {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .rating-bar-fill {
            height: 100%;
            background: #f39c12;
            border-radius: 4px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e2e8f0;
        }
        .stat-card h3 {
            font-size: 28px;
            font-weight: 700;
            color: #e67e22;
        }
        .stat-card p {
            color: #64748b;
            font-size: 13px;
            margin-top: 5px;
        }
        
        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        /* Reviews Grid */
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }
        
        .review-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1);
        }
        
        .review-header {
            display: flex;
            gap: 15px;
            padding: 18px;
            border-bottom: 1px solid #eef2f6;
        }
        .product-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
        }
        .product-info h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .product-info .business {
            font-size: 11px;
            color: #64748b;
        }
        .product-info .price {
            font-size: 13px;
            font-weight: 600;
            color: #e67e22;
            margin-top: 5px;
        }
        
        .review-body {
            padding: 18px;
        }
        .rating {
            color: #f39c12;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .review-date {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        .review-comment {
            color: #475569;
            line-height: 1.5;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        .status-approved {
            background: #d1fae5;
            color: #059669;
        }
        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eef2f6;
        }
        .btn-sm {
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-edit {
            background: #e67e22;
            color: white;
        }
        .btn-edit:hover {
            background: #d35400;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 15px;
        }
        .empty-state h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #64748b;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1024px) {
            .customer-content {
                margin-left: 0;
                padding: 20px;
            }
            .reviews-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .rating-summary {
                flex-direction: column;
                text-align: center;
            }
            .rating-bars {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-star"></i> My Reviews</h1>
            <p>Manage your product reviews</p>
        </div>
        <a href="add.php" class="btn-write"><i class="fas fa-plus"></i> Write a Review</a>
    </div>
    
    <?php if(!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Rating Summary -->
    <div class="rating-summary">
        <div class="avg-rating">
            <h2><?php echo $avg_rating; ?></h2>
            <div class="stars">
                <?php for($i=1; $i<=5; $i++): ?>
                    <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                <?php endfor; ?>
            </div>
            <p><?php echo $total_reviews; ?> total reviews</p>
        </div>
        <div class="rating-bars">
            <?php for($i=5; $i>=1; $i--): ?>
            <div class="rating-bar-item">
                <span><?php echo $i; ?> <i class="fas fa-star"></i></span>
                <div class="rating-bar">
                    <div class="rating-bar-fill" style="width: <?php echo $total_reviews > 0 ? ($rating_counts[$i]/$total_reviews)*100 : 0; ?>%"></div>
                </div>
                <span><?php echo $rating_counts[$i]; ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo $total_reviews; ?></h3>
            <p><i class="fas fa-star"></i> Total Reviews</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $approved_count; ?></h3>
            <p><i class="fas fa-check-circle"></i> Approved</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $pending_count; ?></h3>
            <p><i class="fas fa-clock"></i> Pending</p>
        </div>
    </div>
    
    <?php if(empty($reviews)): ?>
        <div class="empty-state">
            <i class="fas fa-star"></i>
            <h3>No reviews yet</h3>
            <p>You haven't written any reviews. Share your experience with products you've purchased.</p>
            <a href="../orders/index.php" class="btn-write"><i class="fas fa-shopping-cart"></i> View Your Orders</a>
        </div>
    <?php else: ?>
        <div class="reviews-grid">
            <?php foreach($reviews as $review): 
                $img_src = '../../assets/images/default-product.jpg';
                if(!empty($review['image_url'])) {
                    if(file_exists('../../' . $review['image_url'])) {
                        $img_src = '../../' . $review['image_url'];
                    }
                }
            ?>
            <div class="review-card">
                <div class="review-header">
                    <img src="<?php echo $img_src; ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'">
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($review['product_name']); ?></h4>
                        <div class="business"><?php echo htmlspecialchars($review['business_name']); ?></div>
                        <div class="price">TSh <?php echo number_format($review['price']); ?></div>
                    </div>
                </div>
                <div class="review-body">
                    <div class="rating">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="review-date">
                        <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                    </div>
                    <div class="review-comment">
                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                    </div>
                    <div>
                        <span class="status-badge status-<?php echo $review['status']; ?>">
                            <i class="fas fa-<?php echo $review['status'] == 'approved' ? 'check-circle' : ($review['status'] == 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                            <?php echo ucfirst($review['status']); ?>
                        </span>
                    </div>
                    <div class="review-actions">
                        <?php if($review['status'] != 'approved'): ?>
                            <a href="edit.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        <?php endif; ?>
                        <a href="delete.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this review? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>