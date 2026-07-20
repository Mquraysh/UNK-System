<?php
// customer/reviews/edit.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// Get review details
$review_sql = "SELECT r.*, p.name as product_name, p.image_url, p.price, b.business_name
               FROM reviews r
               JOIN products p ON r.product_id = p.product_id
               JOIN businesses b ON p.business_id = b.business_id
               WHERE r.review_id = '$review_id' AND r.customer_id = '$customer_id'";
$review_result = mysqli_query($conn, $review_sql);
$review = mysqli_fetch_assoc($review_result);

if(!$review) {
    $_SESSION['flash_message'] = "Review not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = mysqli_real_escape_string($conn, trim($_POST['comment']));
    
    if($rating < 1 || $rating > 5) {
        $error = "Please select a rating";
    } elseif(empty($comment)) {
        $error = "Please write your review";
    } else {
        $update_sql = "UPDATE reviews SET rating = '$rating', comment = '$comment', status = 'pending', created_at = NOW() 
                       WHERE review_id = '$review_id' AND customer_id = '$customer_id'";
        
        if(mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = "Review updated successfully! It will be reviewed again.";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to update review. Please try again.";
        }
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Review - UNK System</title>
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
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-body {
            padding: 28px;
        }
        
        .product-info {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        .product-info img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
        }
        
        .star-rating {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .star-rating i {
            font-size: 32px;
            cursor: pointer;
            color: #cbd5e1;
            transition: all 0.2s;
        }
        .star-rating i:hover,
        .star-rating i.active {
            color: #f39c12;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .btn-submit {
            background: #e67e22;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-submit:hover {
            background: #d35400;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        @media (max-width: 1024px) {
            .customer-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Review</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Reviews</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Update Your Review</h3>
        </div>
        <div class="card-body">
            <div class="product-info">
                <?php 
                $img_src = '../../assets/images/default-product.jpg';
                if(!empty($review['image_url']) && file_exists('../../' . $review['image_url'])) {
                    $img_src = '../../' . $review['image_url'];
                }
                ?>
                <img src="<?php echo $img_src; ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                <div>
                    <h4><?php echo htmlspecialchars($review['product_name']); ?></h4>
                    <p><?php echo htmlspecialchars($review['business_name']); ?></p>
                    <p>TSh <?php echo number_format($review['price']); ?></p>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Your Rating</label>
                    <div class="star-rating" id="starRating">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star" data-rating="<?php echo $i; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" value="<?php echo $review['rating']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Your Review</label>
                    <textarea name="comment" class="form-control" rows="5" required><?php echo htmlspecialchars($review['comment']); ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Update Review</button>
            </form>
        </div>
    </div>
</div>

<script>
const stars = document.querySelectorAll('#starRating i');
const ratingInput = document.getElementById('ratingValue');
const currentRating = <?php echo $review['rating']; ?>;

// Initialize active stars
for(let i = 1; i <= currentRating; i++) {
    let star = document.querySelector('#starRating i[data-rating="' + i + '"]');
    if(star) {
        star.classList.remove('far');
        star.classList.add('fas');
        star.classList.add('active');
    }
}

stars.forEach(star => {
    star.addEventListener('click', function() {
        let rating = this.getAttribute('data-rating');
        ratingInput.value = rating;
        
        stars.forEach(s => {
            s.classList.remove('fas');
            s.classList.add('far');
            s.classList.remove('active');
        });
        
        for(let i = 1; i <= rating; i++) {
            let targetStar = document.querySelector('#starRating i[data-rating="' + i + '"]');
            if(targetStar) {
                targetStar.classList.remove('far');
                targetStar.classList.add('fas');
                targetStar.classList.add('active');
            }
        }
    });
});
</script>

</body>
</html>