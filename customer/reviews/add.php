<?php
// customer/reviews/add.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_name = $_SESSION['full_name'] ?? 'A customer';

// Get customer_id using prepared statement
$stmt = mysqli_prepare($conn, "SELECT customer_id FROM customers WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$customer_id = $customer_data['customer_id'];

// Get product_id from URL
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Get product details (including business_id) using prepared statement
$product_sql = "SELECT p.*, b.business_id, b.business_name 
                FROM products p
                JOIN businesses b ON p.business_id = b.business_id
                WHERE p.product_id = ? AND p.is_available = 1";
$stmt = mysqli_prepare($conn, $product_sql);
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// If product not found, redirect to orders page
if (!$product && $product_id == 0) {
    header("Location: ../orders/index.php");
    exit();
}

// Check if user has already reviewed this product
$check_sql = "SELECT review_id FROM reviews WHERE product_id = ? AND customer_id = ?";
$stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($stmt, 'ii', $product_id, $customer_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);
$already_reviewed = mysqli_num_rows($check_result) > 0;
mysqli_stmt_close($stmt);

if ($already_reviewed) {
    $_SESSION['flash_message'] = "You have already reviewed this product!";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    $product_id = (int)$_POST['product_id'];
    
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating";
    } elseif (empty($comment)) {
        $error = "Please write your review";
    } else {
        // Insert review using prepared statement
        $insert_sql = "INSERT INTO reviews (product_id, customer_id, rating, comment, status, created_at) 
                       VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($stmt, 'iiis', $product_id, $customer_id, $rating, $comment);
        if (mysqli_stmt_execute($stmt)) {
            // --- Insert business notification (review) ---
            $title = "New Product Review";
            $message = "Customer {$customer_name} rated your product '{$product['name']}' with {$rating} stars.";
            $type = "review";
            $notif_sql = "INSERT INTO business_notifications (business_id, title, message, type, created_at) 
                          VALUES (?, ?, ?, ?, NOW())";
            $stmt2 = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($stmt2, 'isss', $product['business_id'], $title, $message, $type);
            if (mysqli_stmt_execute($stmt2)) {
                // success
            } else {
                // Log error (optional)
                error_log("Failed to insert business notification: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt2);
            
            $_SESSION['flash_message'] = "Thank you for your review! It will appear after approval.";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to submit review. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review - UNK System</title>
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
            max-width: 700px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
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
        .product-details h4 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .product-details p {
            font-size: 13px;
            color: #64748b;
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
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
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
        <h1><i class="fas fa-star"></i> Write a Review</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Reviews</a>
    </div>
    
    <?php if ($product): ?>
    <div class="card">
        <div class="card-header">
            <h3>Share Your Experience</h3>
        </div>
        <div class="card-body">
            <div class="product-info">
                <?php 
                $img_src = '../../assets/images/default-product.jpg';
                if (!empty($product['image_url']) && file_exists('../../' . $product['image_url'])) {
                    $img_src = '../../' . $product['image_url'];
                }
                ?>
                <img src="<?php echo $img_src; ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                <div class="product-details">
                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                    <p><?php echo htmlspecialchars($product['business_name']); ?></p>
                    <p>TSh <?php echo number_format($product['price']); ?></p>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                <div class="form-group">
                    <label>Your Rating</label>
                    <div class="star-rating" id="starRating">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue" required>
                </div>
                <div class="form-group">
                    <label>Your Review</label>
                    <textarea name="comment" class="form-control" rows="5" placeholder="Tell others about your experience with this product..." required></textarea>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Submit Review</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 40px;">
            <i class="fas fa-box-open" style="font-size: 48px; color: #cbd5e1;"></i>
            <h3 style="margin-top: 15px;">Select a Product to Review</h3>
            <p>You can only review products you have purchased.</p>
            <a href="../orders/index.php" class="btn-back" style="margin-top: 15px; display: inline-block;">View My Orders</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Star rating functionality
const stars = document.querySelectorAll('#starRating i');
const ratingInput = document.getElementById('ratingValue');

if (stars.length > 0) {
    stars.forEach(star => {
        star.addEventListener('click', function() {
            let rating = this.getAttribute('data-rating');
            ratingInput.value = rating;
            stars.forEach(s => {
                s.classList.remove('fas');
                s.classList.add('far');
                s.classList.remove('active');
            });
            for (let i = 1; i <= rating; i++) {
                let targetStar = document.querySelector('#starRating i[data-rating="' + i + '"]');
                if (targetStar) {
                    targetStar.classList.remove('far');
                    targetStar.classList.add('fas');
                    targetStar.classList.add('active');
                }
            }
        });
    });
}
</script>

</body>
</html>