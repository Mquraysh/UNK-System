<?php
// customer/rate/rate-delivery.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer data
$cust_res = mysqli_query($conn, "SELECT c.*, u.email FROM customers c JOIN users u ON c.user_id = u.user_id WHERE c.user_id = '$user_id'");
if (mysqli_num_rows($cust_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer = mysqli_fetch_assoc($cust_res);
$customer_id = $customer['customer_id'];

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($delivery_id == 0) {
    header("Location: ../orders/index.php");
    exit();
}

// CHECK IF DELIVERY EXISTS AND IS DELIVERED
$delivery_sql = "SELECT 
                    d.delivery_id,
                    d.order_id,
                    d.status,
                    d.delivery_fee,
                    d.pickup_address,
                    d.delivery_address,
                    d.picked_up_at,
                    d.delivered_at,
                    d.rating as delivery_rating,
                    d.rating_comment as delivery_rating_comment,
                    d.rated_at,
                    o.customer_id,
                    o.grand_total,
                    b.business_name,
                    b.business_id,
                    da.agent_id,
                    da.first_name,
                    da.last_name,
                    da.vehicle_type,
                    u.phone as agent_phone
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.order_id
                 JOIN businesses b ON o.business_id = b.business_id
                 JOIN delivery_agents da ON d.agent_id = da.agent_id
                 JOIN users u ON da.user_id = u.user_id
                 WHERE d.delivery_id = $delivery_id 
                 AND o.customer_id = $customer_id
                 AND d.status = 'delivered'";
$delivery_result = mysqli_query($conn, $delivery_sql);

if (mysqli_num_rows($delivery_result) == 0) {
    $_SESSION['flash_message'] = "Delivery not found or not completed.";
    $_SESSION['flash_type'] = "danger";
    header("Location: ../orders/index.php");
    exit();
}
$delivery = mysqli_fetch_assoc($delivery_result);

// CHECK IF ALREADY RATED IN delivery_ratings
$check_rating = mysqli_query($conn, "SELECT rating_id FROM delivery_ratings WHERE delivery_id = $delivery_id AND customer_id = $customer_id");
if (mysqli_num_rows($check_rating) > 0) {
    $_SESSION['flash_message'] = "You have already rated this delivery.";
    $_SESSION['flash_type'] = "info";
    header("Location: ../orders/index.php");
    exit();
}

// GET AGENT AVERAGE RATING FROM delivery_ratings
$agent_id = $delivery['agent_id'];
$avg_sql = "SELECT 
                AVG(rating) as avg_rating, 
                COUNT(*) as total_ratings 
            FROM delivery_ratings 
            WHERE agent_id = $agent_id";
$avg_result = mysqli_query($conn, $avg_sql);
$avg_data = mysqli_fetch_assoc($avg_result);
$agent_avg_rating = $avg_data['avg_rating'] ?? 0;
$agent_total_ratings = $avg_data['total_ratings'] ?? 0;

// HANDLE RATING SUBMISSION
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_rating'])) {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment'] ?? '');
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } else {
        $esc_comment = mysqli_real_escape_string($conn, $comment);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Insert into delivery_ratings
            $insert_sql = "INSERT INTO delivery_ratings (delivery_id, customer_id, agent_id, order_id, rating, comment) 
                           VALUES ($delivery_id, $customer_id, {$delivery['agent_id']}, {$delivery['order_id']}, $rating, '$esc_comment')";
            
            if (!mysqli_query($conn, $insert_sql)) {
                throw new Exception("Failed to insert rating: " . mysqli_error($conn));
            }
            
            // 2. Update deliveries table with rating
            $update_delivery = "UPDATE deliveries 
                                SET rating = $rating, 
                                    rating_comment = '$esc_comment', 
                                    rated_at = NOW() 
                                WHERE delivery_id = $delivery_id";
            if (!mysqli_query($conn, $update_delivery)) {
                throw new Exception("Failed to update delivery: " . mysqli_error($conn));
            }
            
            // 3. Update delivery_agents table with average rating
            $avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total 
                        FROM delivery_ratings 
                        WHERE agent_id = {$delivery['agent_id']}";
            $avg_result = mysqli_query($conn, $avg_sql);
            $avg_data = mysqli_fetch_assoc($avg_result);
            
            if ($avg_data) {
                $new_avg = round($avg_data['avg_rating'], 2);
                $new_total = $avg_data['total'];
                $update_agent = "UPDATE delivery_agents 
                                 SET avg_rating = $new_avg, 
                                     total_ratings = $new_total 
                                 WHERE agent_id = {$delivery['agent_id']}";
                if (!mysqli_query($conn, $update_agent)) {
                    throw new Exception("Failed to update agent rating: " . mysqli_error($conn));
                }
            }
            
            mysqli_commit($conn);
            
            $_SESSION['flash_message'] = "Thank you for rating your delivery!";
            $_SESSION['flash_type'] = "success";
            header("Location: ../orders/index.php");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to submit rating: " . $e->getMessage();
        }
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Rate Your Delivery | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .customer-content { padding: 0.9rem; }
            .rating-card { margin: 0; }
            .star-rating .stars { font-size: 2.8rem; gap: 0.5rem; }
            .delivery-info .row { flex-direction: column; gap: 0.15rem; }
            .delivery-info .row .value { text-align: left; }
            .agent-avatar { flex-wrap: wrap; }
            .rating-stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .star-rating .stars { font-size: 2.2rem; gap: 0.3rem; }
            .rating-stats-grid { grid-template-columns: 1fr; }
        }
        
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.3rem; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #e67e22;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }
        .back-link:hover { transform: translateX(-4px); }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.85rem;
            font-weight: 500;
            animation: slideIn 0.3s ease;
            border-left: 4px solid;
        }
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left-color: #3b82f6; }
        
        .rating-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        
        .rating-card-header {
            padding: 1.25rem 1.75rem;
            background: linear-gradient(135deg, #fafcff, #ffffff);
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }
        .rating-card-header i { color: #e67e22; }
        .rating-card-header .badge {
            margin-left: auto;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            background: #d1fae5;
            color: #047857;
            font-weight: 600;
        }
        
        .rating-card-body { padding: 1.75rem; }
        
        .delivery-info {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .delivery-info .row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            font-size: 0.85rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .delivery-info .row:last-child { border-bottom: none; }
        .delivery-info .row .label { color: #64748b; font-weight: 500; }
        .delivery-info .row .value { font-weight: 600; color: #1e293b; text-align: right; }
        
        .agent-avatar {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.25rem 0;
        }
        .agent-avatar .avatar {
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
        .agent-avatar .info .name { font-weight: 700; font-size: 0.9rem; }
        .agent-avatar .info .vehicle { font-size: 0.75rem; color: #64748b; }
        .agent-avatar .info .rating { font-size: 0.75rem; color: #f59e0b; }
        
        .rating-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .rating-stat {
            text-align: center;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
        }
        .rating-stat .number { font-size: 1.2rem; font-weight: 800; color: #0f172a; }
        .rating-stat .label { font-size: 0.6rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .rating-stat .stars-small { font-size: 0.7rem; color: #f59e0b; }
        
        .star-rating {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin: 1.5rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .star-rating .stars {
            display: flex;
            gap: 0.75rem;
            font-size: 3.5rem;
            cursor: pointer;
            user-select: none;
        }
        
        .star-rating .stars i {
            transition: all 0.2s ease;
            color: #d1d5db;
        }
        
        .star-rating .stars i.active {
            color: #f59e0b;
        }
        
        .star-rating .stars i:hover {
            transform: scale(1.15);
            color: #f59e0b;
        }
        
        .star-rating .rating-text {
            font-size: 0.95rem;
            font-weight: 600;
            color: #64748b;
        }
        
        .star-rating .rating-text .highlight {
            color: #f59e0b;
        }
        
        .comment-box { margin: 1.5rem 0; }
        .comment-box label {
            font-weight: 600;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 0.4rem;
            color: #475569;
        }
        .comment-box label i { color: #e67e22; }
        .comment-box textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.6rem;
            font-family: 'Inter', sans-serif;
            font-size: 0.85rem;
            resize: vertical;
            min-height: 80px;
            transition: all 0.2s;
            background: white;
        }
        .comment-box textarea:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.6rem;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
            width: 100%;
            justify-content: center;
        }
        .btn-primary {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            transform: none !important;
        }
        
        .footer-note {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .footer-note i { margin-right: 0.3rem; }
        
        .rating-label-selected {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .rating-label-selected.poor { background: #fee2e2; color: #dc2626; }
        .rating-label-selected.fair { background: #fef3c7; color: #d97706; }
        .rating-label-selected.good { background: #dbeafe; color: #2563eb; }
        .rating-label-selected.very-good { background: #d1fae5; color: #059669; }
        .rating-label-selected.excellent { background: #fef3c7; color: #d97706; }
    </style>
</head>
<body>
<div class="customer-content">
    <a href="../orders/index.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Orders
    </a>
    
    <div class="page-header">
        <h1><i class="fas fa-star"></i> Rate Your Delivery</h1>
        <p>Help us improve by rating your delivery experience</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="rating-card">
        <div class="rating-card-header">
            <i class="fas fa-truck"></i> Delivery <?php echo $delivery_id; ?>
            <span class="badge"><i class="fas fa-check-circle"></i> Completed</span>
        </div>
        <div class="rating-card-body">
            <!-- Delivery Info -->
            <div class="delivery-info">
                <div class="row">
                    <span class="label">Order ID</span>
                    <span class="value"> <?php echo $delivery['order_id']; ?></span>
                </div>
                <div class="row">
                    <span class="label">Business</span>
                    <span class="value"><?php echo htmlspecialchars($delivery['business_name']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Delivery Address</span>
                    <span class="value" style="font-size:0.75rem;"><?php echo htmlspecialchars($delivery['delivery_address']); ?></span>
                </div>
                <div class="row">
                    <span class="label">Amount</span>
                    <span class="value" style="color: #e67e22;">TSh <?php echo number_format($delivery['grand_total']); ?></span>
                </div>
                <div class="row" style="border-bottom: none; padding-top: 0.5rem;">
                    <span class="label">Delivery Agent</span>
                    <span class="value" style="text-align: right;">
                        <div class="agent-avatar" style="justify-content: flex-end;">
                            <div class="info" style="text-align: right;">
                                <div class="name"><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></div>
                                <div class="vehicle"><i class="fas fa-<?php echo $delivery['vehicle_type'] == 'motorcycle' ? 'motorcycle' : ($delivery['vehicle_type'] == 'bicycle' ? 'bicycle' : 'truck'); ?>"></i> <?php echo ucfirst($delivery['vehicle_type'] ?? 'Vehicle'); ?></div>
                                <?php if ($agent_avg_rating > 0): ?>
                                <div class="rating">
                                    <i class="fas fa-star" style="color: #f59e0b;"></i> 
                                    <?php echo number_format($agent_avg_rating, 1); ?> 
                                    (<?php echo $agent_total_ratings; ?> reviews)
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="avatar">
                                <?php echo strtoupper(substr($delivery['first_name'], 0, 1) . substr($delivery['last_name'], 0, 1)); ?>
                            </div>
                        </div>
                    </span>
                </div>
            </div>
            
            <!-- Rating Stats Preview -->
            <div class="rating-stats-grid" id="ratingStats" style="display: none;">
                <div class="rating-stat">
                    <div class="stars-small" id="previewStars">☆☆☆☆☆</div>
                    <div class="number" id="previewNumber">0</div>
                    <div class="label">Rating</div>
                </div>
                <div class="rating-stat">
                    <div class="number" id="previewLabel">Select</div>
                    <div class="label">Status</div>
                </div>
                <div class="rating-stat">
                    <div class="number" id="previewEmoji">⭐</div>
                    <div class="label">Feel</div>
                </div>
                <div class="rating-stat">
                    <div class="number" id="previewMessage">—</div>
                    <div class="label">Message</div>
                </div>
            </div>
            
            <form method="POST" action="" id="ratingForm">
                <!-- Star Rating -->
                <div class="star-rating">
                    <div class="stars" id="starsContainer">
                        <i class="fas fa-star" data-value="1"></i>
                        <i class="fas fa-star" data-value="2"></i>
                        <i class="fas fa-star" data-value="3"></i>
                        <i class="fas fa-star" data-value="4"></i>
                        <i class="fas fa-star" data-value="5"></i>
                    </div>
                    <div class="rating-text">
                        <span id="ratingLabel">👆 Tap a star to rate</span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0" required>
                </div>
                
                <!-- Comment -->
                <div class="comment-box">
                    <label for="comment"><i class="fas fa-pen"></i> Leave a comment (optional)</label>
                    <textarea name="comment" id="comment" placeholder="Share your delivery experience... How was the service?" rows="3"></textarea>
                    <div style="font-size:0.65rem; color:#94a3b8; margin-top:0.25rem; text-align:right;">
                        <span id="charCount">0</span> characters
                    </div>
                </div>
                
                <button type="submit" name="submit_rating" class="btn btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane"></i> Submit Rating
                </button>
            </form>
            
            <div class="footer-note">
                <i class="fas fa-lock"></i> Your feedback is anonymous and helps improve our service
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.stars i');
    const ratingInput = document.getElementById('ratingInput');
    const ratingLabel = document.getElementById('ratingLabel');
    const submitBtn = document.getElementById('submitBtn');
    const comment = document.getElementById('comment');
    const charCount = document.getElementById('charCount');
    const ratingStats = document.getElementById('ratingStats');
    const previewStars = document.getElementById('previewStars');
    const previewNumber = document.getElementById('previewNumber');
    const previewLabel = document.getElementById('previewLabel');
    const previewEmoji = document.getElementById('previewEmoji');
    const previewMessage = document.getElementById('previewMessage');
    
    const ratingData = {
        0: { label: 'Select', emoji: '⭐', message: '—', stars: '☆☆☆☆☆' },
        1: { label: 'Poor', emoji: '😞', message: 'Very dissatisfied', stars: '★☆☆☆☆', class: 'poor' },
        2: { label: 'Fair', emoji: '😕', message: 'Needs improvement', stars: '★★☆☆☆', class: 'fair' },
        3: { label: 'Good', emoji: '😊', message: 'Satisfactory', stars: '★★★☆☆', class: 'good' },
        4: { label: 'Very Good', emoji: '😄', message: 'Impressed', stars: '★★★★☆', class: 'very-good' },
        5: { label: 'Excellent', emoji: '🌟', message: 'Outstanding!', stars: '★★★★★', class: 'excellent' }
    };
    
    // Character counter
    comment.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });
    
    // Click to select rating
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const value = parseInt(this.dataset.value);
            ratingInput.value = value;
            
            // Update star colors
            stars.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= value) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
            
            // Update preview
            const data = ratingData[value];
            previewStars.textContent = data.stars;
            previewNumber.textContent = value;
            previewLabel.textContent = data.label;
            previewEmoji.textContent = data.emoji;
            previewMessage.textContent = data.message;
            ratingStats.style.display = 'grid';
            
            // Update label
            ratingLabel.innerHTML = `<span class="highlight">${value} stars</span> - ${data.emoji} ${data.label}`;
            
            // Enable submit button
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        });
        
        // Hover effect
        star.addEventListener('mouseenter', function() {
            const value = parseInt(this.dataset.value);
            stars.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= value) {
                    s.style.color = '#f59e0b';
                } else {
                    s.style.color = '#d1d5db';
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            const selected = parseInt(ratingInput.value);
            stars.forEach(s => {
                const val = parseInt(s.dataset.value);
                if (val <= selected && selected > 0) {
                    s.style.color = '#f59e0b';
                } else {
                    s.style.color = '#d1d5db';
                }
            });
        });
    });
    
    // Prevent form submission without rating
    document.getElementById('ratingForm').addEventListener('submit', function(e) {
        if (parseInt(ratingInput.value) < 1) {
            e.preventDefault();
            ratingLabel.innerHTML = '⚠️ Please select a rating first!';
            ratingLabel.style.color = '#ef4444';
            setTimeout(() => {
                ratingLabel.style.color = '#64748b';
                ratingLabel.innerHTML = '👆 Tap a star to rate';
            }, 3000);
        }
    });
});
</script>
</body>
</html>