<?php
// business/reviews/respond.php 
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
$sql = "SELECT r.*, p.name as product_name, c.first_name, c.last_name
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE r.review_id = '$review_id' AND p.business_id = '$business_id'";
$result = mysqli_query($conn, $sql);
$review = mysqli_fetch_assoc($result);

if (!$review) {
    $_SESSION['flash_message'] = "Review not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = mysqli_real_escape_string($conn, trim($_POST['response']));
    
    // Check if review_response table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'review_responses'");
    if (mysqli_num_rows($check_table) == 0) {
        $create_table = "CREATE TABLE review_responses (
            response_id INT PRIMARY KEY AUTO_INCREMENT,
            review_id INT NOT NULL,
            business_id INT NOT NULL,
            response TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE,
            FOREIGN KEY (business_id) REFERENCES businesses(business_id) ON DELETE CASCADE
        )";
        mysqli_query($conn, $create_table);
    }
    
    // Check if response already exists
    $check_sql = "SELECT response_id FROM review_responses WHERE review_id = '$review_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $update_sql = "UPDATE review_responses SET response = '$response' WHERE review_id = '$review_id'";
        mysqli_query($conn, $update_sql);
    } else {
        $insert_sql = "INSERT INTO review_responses (review_id, business_id, response) VALUES ('$review_id', '$business_id', '$response')";
        mysqli_query($conn, $insert_sql);
    }
    
    $_SESSION['flash_message'] = "Response posted successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: index.php");
    exit();
}

// Get existing response
$response_sql = "SELECT * FROM review_responses WHERE review_id = '$review_id'";
$response_result = mysqli_query($conn, $response_sql);
$existing_response = mysqli_fetch_assoc($response_result);

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respond to Review - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        .business-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        
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
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; color: #2c3e50; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        
        .review-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        .review-box h4 { font-size: 14px; color: #64748b; margin-bottom: 10px; }
        .review-box p { color: #0f172a; line-height: 1.5; }
        .rating { color: #f39c12; margin-bottom: 10px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
        }
        .form-control:focus { outline: none; border-color: #e67e22; }
        textarea.form-control { resize: vertical; min-height: 150px; }
        
        .btn-submit { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
        .btn-submit:hover { background: #d35400; }
        
        .existing-response {
            background: #d1fae5;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
        }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <h1><i class="fas fa-reply"></i> Respond to Review</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Reviews</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-comment"></i> Original Review</h3>
        </div>
        <div class="card-body">
            <div class="review-box">
                <div class="rating">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                    <?php endfor; ?>
                </div>
                <h4><?php echo htmlspecialchars($review['product_name']); ?></h4>
                <p><strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong> - <?php echo date('M d, Y', strtotime($review['created_at'])); ?></p>
                <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
            </div>
            
            <?php if($existing_response): ?>
            <div class="existing-response">
                <strong><i class="fas fa-reply"></i> Your Previous Response:</strong>
                <p style="margin-top: 8px;"><?php echo nl2br(htmlspecialchars($existing_response['response'])); ?></p>
                <small>Posted on <?php echo date('M d, Y', strtotime($existing_response['created_at'])); ?></small>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Your Response <span style="color: #e74c3c;">*</span></label>
                    <textarea name="response" class="form-control" rows="6" placeholder="Thank the customer for their feedback and address any concerns..."><?php echo htmlspecialchars($existing_response['response'] ?? ''); ?></textarea>
                    <small style="font-size: 11px; color: #94a3b8;">Be professional and courteous. A good response builds customer trust.</small>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Post Response</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>