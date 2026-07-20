<?php
// business/reviews/index.php - WITH APPROVE/REJECT ACTIONS
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

// Get filter parameters
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT r.*, p.name as product_name, p.image_url, p.product_id,
               c.first_name, c.last_name
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE p.business_id = '$business_id'";

if ($rating_filter > 0) {
    $sql .= " AND r.rating = '$rating_filter'";
}
if (!empty($status_filter)) {
    $sql .= " AND r.status = '$status_filter'";
}
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (p.name LIKE '%$search_escaped%' 
               OR c.first_name LIKE '%$search_escaped%' 
               OR c.last_name LIKE '%$search_escaped%'
               OR r.comment LIKE '%$search_escaped%')";
}
$sql .= " ORDER BY r.created_at DESC";
$result = mysqli_query($conn, $sql);
$reviews = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reviews[] = $row;
}

// Statistics
$total_reviews = count($reviews);
$avg_rating = 0;
$rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$pending_count = 0;
$approved_count = 0;

foreach ($reviews as $r) {
    $avg_rating += $r['rating'];
    $rating_counts[$r['rating']]++;
    if ($r['status'] == 'pending') $pending_count++;
    if ($r['status'] == 'approved') $approved_count++;
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

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Reviews - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (Same CSS as before, unchanged) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; margin-top: 5px; }
        .rating-summary { background: white; border-radius: 20px; padding: 25px; margin-bottom: 25px; display: flex; gap: 40px; flex-wrap: wrap; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .avg-rating { text-align: center; padding: 15px 25px; background: #f8fafc; border-radius: 16px; }
        .avg-rating h2 { font-size: 52px; font-weight: 800; color: #f39c12; }
        .avg-rating .stars { color: #f39c12; font-size: 18px; margin: 8px 0; }
        .rating-bars { flex: 1; }
        .rating-bar-item { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .rating-bar-item span { width: 40px; font-size: 13px; }
        .rating-bar { flex: 1; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .rating-bar-fill { height: 100%; background: #f39c12; border-radius: 4px; }
        .rating-count { width: 45px; font-size: 13px; color: #64748b; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            border-color: #e67e22;
            box-shadow: 0 12px 28px rgba(0,0,0,0.1);
        }
        .stat-card h3 { font-size: 28px; font-weight: 700; color: #e67e22; }
        .stat-card p { color: #64748b; font-size: 13px; margin-top: 5px; }
        .filters-bar { background: white; border-radius: 16px; padding: 20px 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .filters-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 6px; }
        .filter-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .filter-input:focus { outline: none; border-color: #e67e22; }
        .btn-filter { background: #e67e22; color: white; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; }
        .btn-reset { background: #94a3b8; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .quick-filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .quick-filter { padding: 6px 14px; background: #f1f5f9; border-radius: 20px; text-decoration: none; color: #475569; font-size: 13px; transition: all 0.3s; }
        .quick-filter:hover, .quick-filter.active { background: #e67e22; color: white; }
        .reviews-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 25px; }
        .review-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: all 0.3s; }
        .review-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -12px rgba(0,0,0,0.15); }
        .review-header { padding: 18px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; }
        .product-info h4 { font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .product-info .rating { color: #f39c12; font-size: 12px; }
        .review-body { padding: 18px 20px; }
        .customer-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .customer-avatar { width: 32px; height: 32px; background: #e67e22; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .customer-name { font-weight: 600; color: #0f172a; font-size: 14px; }
        .review-date { font-size: 10px; color: #94a3b8; margin-left: auto; }
        .review-comment { color: #475569; font-size: 13px; line-height: 1.5; margin-bottom: 15px; }
        .review-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid #e2e8f0; }
        .helpful-count { font-size: 12px; color: #94a3b8; }
        .helpful-count i { color: #e67e22; }
        .status-badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-approved { background: #d1fae5; color: #059669; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .review-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
        .btn-sm { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-view { background: #3498db; color: white; }
        .btn-approve { background: #27ae60; color: white; }
        .btn-reject { background: #e74c3c; color: white; }
        .btn-delete { background: #7f8c8d; color: white; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 20px; color: #94a3b8; }
        .empty-state i { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .reviews-grid { grid-template-columns: 1fr; } .quick-filters { justify-content: center; } .filter-group { min-width: 100%; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-star"></i> Customer Reviews</h1>
            <p>Manage and respond to customer feedback</p>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Rating Summary (same as before) -->
    <div class="rating-summary">
        <div class="avg-rating">
            <h2><?php echo $avg_rating; ?></h2>
            <div class="stars">
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star<?php echo $i <= $avg_rating ? '' : '-o'; ?>"></i>
                <?php endfor; ?>
            </div>
            <p><?php echo $total_reviews; ?> reviews</p>
        </div>
        <div class="rating-bars">
            <?php for($i = 5; $i >= 1; $i--): ?>
            <div class="rating-bar-item">
                <span><?php echo $i; ?> <i class="fas fa-star" style="color: #f39c12;"></i></span>
                <div class="rating-bar">
                    <div class="rating-bar-fill" style="width: <?php echo $total_reviews > 0 ? ($rating_counts[$i]/$total_reviews)*100 : 0; ?>%"></div>
                </div>
                <span class="rating-count"><?php echo $rating_counts[$i]; ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card"><h3><?php echo $total_reviews; ?></h3><p><i class="fas fa-star"></i> Total Reviews</p></div>
        <div class="stat-card"><h3><?php echo $approved_count; ?></h3><p><i class="fas fa-check-circle"></i> Approved</p></div>
        <div class="stat-card"><h3><?php echo $pending_count; ?></h3><p><i class="fas fa-clock"></i> Pending</p></div>
        <div class="stat-card"><h3><?php echo $avg_rating; ?> / 5</h3><p><i class="fas fa-chart-line"></i> Average Rating</p></div>
    </div>

    <!-- Quick Filters -->
    <div class="quick-filters">
        <a href="index.php" class="quick-filter <?php echo empty($rating_filter) && empty($status_filter) ? 'active' : ''; ?>">All Reviews</a>
        <a href="?rating=5" class="quick-filter <?php echo $rating_filter == 5 ? 'active' : ''; ?>">5 Star</a>
        <a href="?rating=4" class="quick-filter <?php echo $rating_filter == 4 ? 'active' : ''; ?>">4 Star</a>
        <a href="?rating=3" class="quick-filter <?php echo $rating_filter == 3 ? 'active' : ''; ?>">3 Star</a>
        <a href="?rating=2" class="quick-filter <?php echo $rating_filter == 2 ? 'active' : ''; ?>">2 Star</a>
        <a href="?rating=1" class="quick-filter <?php echo $rating_filter == 1 ? 'active' : ''; ?>">1 Star</a>
        <a href="?status=pending" class="quick-filter <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
        <a href="?status=approved" class="quick-filter <?php echo $status_filter == 'approved' ? 'active' : ''; ?>">Approved</a>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="filter-group"><label><i class="fas fa-search"></i> Search</label><input type="text" name="search" class="filter-input" placeholder="Product, customer, or comment..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <div class="filter-group"><label><i class="fas fa-star"></i> Rating</label><select name="rating" class="filter-input"><option value="0">All Ratings</option><option value="5" <?php echo $rating_filter == 5 ? 'selected' : ''; ?>>5 Stars</option><option value="4" <?php echo $rating_filter == 4 ? 'selected' : ''; ?>>4 Stars</option><option value="3" <?php echo $rating_filter == 3 ? 'selected' : ''; ?>>3 Stars</option><option value="2" <?php echo $rating_filter == 2 ? 'selected' : ''; ?>>2 Stars</option><option value="1" <?php echo $rating_filter == 1 ? 'selected' : ''; ?>>1 Star</option></select></div>
            <div class="filter-group"><label><i class="fas fa-flag"></i> Status</label><select name="status" class="filter-input"><option value="">All Status</option><option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option><option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option><option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option></select></div>
            <div class="filter-buttons"><button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button><a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a></div>
        </form>
    </div>

    <!-- Reviews Grid -->
    <?php if(empty($reviews)): ?>
        <div class="empty-state"><i class="fas fa-star"></i><p>No reviews found</p><p style="font-size: 12px;">When customers leave reviews, they will appear here</p></div>
    <?php else: ?>
        <div class="reviews-grid">
            <?php foreach($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <?php 
                    $img_src = "../../assets/images/default-product.jpg";
                    if(!empty($review['image_url']) && file_exists("../../" . $review['image_url'])) {
                        $img_src = "../../" . $review['image_url'];
                    }
                    ?>
                    <img src="<?php echo $img_src; ?>" class="product-img" alt="<?php echo htmlspecialchars($review['product_name']); ?>">
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($review['product_name']); ?></h4>
                        <div class="rating">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
                <div class="review-body">
                    <div class="customer-info">
                        <div class="customer-avatar"><?php echo strtoupper(substr($review['first_name'], 0, 1)); ?></div>
                        <div class="customer-name"><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></div>
                        <div class="review-date"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                    </div>
                    <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                    <div class="review-footer">
                        <div class="helpful-count"><i class="fas fa-thumbs-up"></i> <?php echo $review['helpful_count']; ?> found helpful</div>
                        <div><span class="status-badge status-<?php echo $review['status']; ?>"><?php echo ucfirst($review['status']); ?></span></div>
                    </div>
                    <div class="review-actions">
                        <a href="view.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> Details</a>
                        <!-- Approve button only for pending reviews -->
                        <?php if($review['status'] == 'pending'): ?>
                            <a href="approve.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-approve" onclick="return confirm('Approve this review? It will become visible to customers.')">
                                <i class="fas fa-check-circle"></i> Approve
                            </a>
                            <a href="reject.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-reject" onclick="return confirm('Reject this review? It will be hidden from customers.')">
                                <i class="fas fa-times-circle"></i> Reject
                            </a>
                        <?php endif; ?>
                        <a href="delete.php?id=<?php echo $review['review_id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this review permanently?')">
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