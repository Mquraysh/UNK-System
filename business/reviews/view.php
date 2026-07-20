<?php
// business/reviews/view.php 
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
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get review details
$sql = "SELECT r.*, p.name as product_name, p.image_url, p.product_id, p.price,
               c.first_name, c.last_name, c.saved_address, u.email, u.phone
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN customers c ON r.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE r.review_id = '$review_id' AND p.business_id = '$business_id'";
$result = mysqli_query($conn, $sql);
$review = mysqli_fetch_assoc($result);

if (!$review) {
    $_SESSION['flash_message'] = "Review not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Review Details - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .review-grid { grid-template-columns: 1fr; gap: 1rem; }
            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; justify-content: center; }
        }
        
        /* Page Header */
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
            letter-spacing: -0.02em;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.5rem;
            background: #2c3e50;
            color: white;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44,62,80,0.3);
        }
        
        /* Review Grid */
        .review-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 24px -8px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #0f172a;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .badge {
            margin-left: auto;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .badge-approved { background: #d1fae5; color: #059669; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        
        .card-body { padding: 1.5rem; }
        
        /* Info Rows */
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.85rem;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            width: 120px;
            font-weight: 600;
            color: #64748b;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
            color: #0f172a;
            font-weight: 500;
        }
        
        /* Product Display */
        .product-display {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .product-display .product-img {
            width: 64px;
            height: 64px;
            border-radius: 0.75rem;
            object-fit: cover;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .product-display .product-info .name {
            font-weight: 700;
            color: #0f172a;
        }
        .product-display .product-info .price {
            font-size: 0.75rem;
            color: #e67e22;
            font-weight: 600;
        }
        
        /* Rating Display */
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .rating-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #f59e0b;
            line-height: 1;
        }
        .rating-stars {
            color: #f59e0b;
            font-size: 1.2rem;
            letter-spacing: 2px;
        }
        .rating-stars .empty { color: #d1d5db; }
        .rating-label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-left: auto;
        }
        
        /* Customer Avatar */
        .customer-avatar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .customer-avatar .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e67e22, #d35400);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .customer-avatar .info .name {
            font-weight: 700;
            font-size: 0.9rem;
            color: #0f172a;
        }
        .customer-avatar .info .detail {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        /* Comment Box */
        .comment-box {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 0.75rem;
            margin-top: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .comment-box .comment-text {
            font-size: 0.9rem;
            line-height: 1.6;
            color: #1e293b;
            white-space: pre-wrap;
        }
        .comment-box .comment-meta {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 0.5rem;
        }
        
        /* Helpful Indicator */
        .helpful-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: #f1f5f9;
            border-radius: 2rem;
            font-size: 0.75rem;
            color: #64748b;
        }
        .helpful-indicator i { color: #10b981; }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1.5rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(230,126,34,0.3);
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
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
        
        /* Status Timeline */
        .status-timeline {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.5rem 0;
        }
        .timeline-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .timeline-step .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d1d5db;
        }
        .timeline-step .dot.active {
            background: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.2);
        }
        .timeline-step .dot.done {
            background: #10b981;
        }
        .timeline-step .line {
            width: 30px;
            height: 2px;
            background: #d1d5db;
        }
        .timeline-step .line.done {
            background: #10b981;
        }
        
        @media (max-width: 480px) {
            .info-row { flex-direction: column; gap: 0.25rem; }
            .info-label { width: 100%; }
            .rating-number { font-size: 2rem; }
            .product-display { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-star"></i> Review Details</h1>
            <p>View customer feedback and respond to reviews</p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Reviews
        </a>
    </div>
    
    <div class="review-grid">
        <!-- Review Information Card -->
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-comment"></i> Review Information
                    <span class="badge badge-<?php echo $review['status']; ?>">
                        <?php echo ucfirst($review['status']); ?>
                    </span>
                </h3>
            </div>
            <div class="card-body">
                <!-- Product Info -->
                <div class="info-row">
                    <div class="info-label">Product</div>
                    <div class="info-value">
                        <div class="product-display">
                            <img src="<?php echo !empty($review['image_url']) && file_exists("../../" . $review['image_url']) ? "../../" . $review['image_url'] : '../../assets/images/default-product.jpg'; ?>" 
                                 class="product-img" 
                                 onerror="this.src='../../assets/images/default-product.jpg'">
                            <div class="product-info">
                                <div class="name"><?php echo htmlspecialchars($review['product_name']); ?></div>
                                <div class="price">TSh <?php echo number_format($review['price']); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Rating -->
                <div class="info-row">
                    <div class="info-label">Rating</div>
                    <div class="info-value">
                        <div class="rating-display">
                            <span class="rating-number"><?php echo $review['rating']; ?></span>
                            <span class="rating-stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : ' empty'; ?>"></i>
                                <?php endfor; ?>
                            </span>
                            <span class="rating-label">out of 5</span>
                        </div>
                    </div>
                </div>
                
                <!-- Helpful Count -->
                <div class="info-row">
                    <div class="info-label">Helpful</div>
                    <div class="info-value">
                        <span class="helpful-indicator">
                            <i class="fas fa-thumbs-up"></i>
                            <?php echo $review['helpful_count']; ?> customers found this helpful
                        </span>
                    </div>
                </div>
                
                <!-- Date -->
                <div class="info-row">
                    <div class="info-label">Review Date</div>
                    <div class="info-value">
                        <?php echo date('F j, Y g:i A', strtotime($review['created_at'])); ?>
                    </div>
                </div>
                
                <!-- Comment -->
                <div class="info-row" style="flex-direction: column; align-items: stretch; gap: 0.5rem;">
                    <div class="info-label">Comment</div>
                    <div class="comment-box">
                        <div class="comment-text"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                        <div class="comment-meta">
                            <i class="far fa-clock"></i> Posted <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Information Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Customer Information</h3>
            </div>
            <div class="card-body">
                <!-- Customer Avatar -->
                <div class="customer-avatar" style="margin-bottom: 1rem;">
                    <div class="avatar">
                        <?php echo strtoupper(substr($review['first_name'], 0, 1) . substr($review['last_name'], 0, 1)); ?>
                    </div>
                    <div class="info">
                        <div class="name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                        <div class="detail"><i class="fas fa-user"></i> Customer</div>
                    </div>
                </div>
                
                <!-- Contact Details -->
                <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value">
                        <a href="mailto:<?php echo htmlspecialchars($review['email']); ?>" style="color: #e67e22; text-decoration: none;">
                            <?php echo htmlspecialchars($review['email']); ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($review['phone']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($review['saved_address'])); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="respond.php?id=<?php echo $review['review_id']; ?>" class="btn btn-primary">
            <i class="fas fa-reply"></i> Respond to Review
        </a>
        <?php if($review['status'] == 'pending'): ?>
            <a href="approve.php?id=<?php echo $review['review_id']; ?>" class="btn btn-secondary" style="background: #10b981; color: white;" onclick="return confirm('Approve this review?')">
                <i class="fas fa-check-circle"></i> Approve
            </a>
            <a href="reject.php?id=<?php echo $review['review_id']; ?>" class="btn btn-secondary" style="background: #f59e0b; color: white;" onclick="return confirm('Reject this review?')">
                <i class="fas fa-times-circle"></i> Reject
            </a>
        <?php endif; ?>
        <a href="delete.php?id=<?php echo $review['review_id']; ?>" class="btn btn-danger" onclick="return confirm('⚠️ Delete this review permanently? This action cannot be undone.')">
            <i class="fas fa-trash-alt"></i> Delete Review
        </a>
    </div>
</div>
</body>
</html>