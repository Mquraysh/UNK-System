<?php
// admin/products/view.php - PROFESSIONAL PRODUCT DETAILS VIEW
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch product details
$stmt = mysqli_prepare($conn, "
    SELECT p.*, b.business_name, b.business_id, c.name as category_name 
    FROM products p 
    JOIN businesses b ON p.business_id = b.business_id 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    WHERE p.product_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$product) {
    $_SESSION['flash_message'] = "Product not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Get review statistics
$stmt = mysqli_prepare($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM reviews WHERE product_id = ? AND status = 'approved'");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$review_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$avg_rating = round($review_stats['avg_rating'] ?? 0, 1);
$review_count = (int)($review_stats['total'] ?? 0);

// Determine image path
$img = !empty($product['image_url']) && file_exists("../../" . $product['image_url']) 
    ? "../../" . $product['image_url'] 
    : "../../assets/images/default-product.jpg";

// Determine stock badge class and text
if ($product['quantity_in_stock'] <= 0) {
    $stock_badge = 'badge-out';
    $stock_text = 'Out of Stock';
} elseif ($product['quantity_in_stock'] < 10) {
    $stock_badge = 'badge-low';
    $stock_text = 'Low Stock';
} else {
    $stock_badge = 'badge-normal';
    $stock_text = 'In Stock';
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Product Details | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-back {
            background: #64748b;
            color: white;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        .btn-edit {
            background: #f59e0b;
            color: white;
        }
        .btn-edit:hover { background: #d97706; transform: translateY(-2px); }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); }
        .info-card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header i { color: #e67e22; }
        .card-body {
            padding: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
        }
        .product-image {
            flex: 0 0 200px;
        }
        .product-image img {
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .details-grid {
            flex: 1;
        }
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            width: 140px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.85rem;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .badge-normal { background: #d1fae5; color: #059669; }
        .badge-low { background: #fef3c7; color: #d97706; }
        .badge-out { background: #fee2e2; color: #dc2626; }
        .rating-stars {
            color: #f39c12;
            letter-spacing: 2px;
        }
        @media (max-width: 640px) {
            .admin-content { padding: 1rem; }
            .card-body { flex-direction: column; align-items: center; }
            .product-image { flex: none; width: 100%; max-width: 250px; margin: 0 auto; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.25rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-box"></i> Product Details</h1>
        <div class="action-buttons">
            <a href="index.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="edit.php?id=<?= $product_id ?>" class="btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
            <a href="delete.php?id=<?= $product_id ?>" class="btn btn-delete" onclick="return confirm('⚠️ Permanently delete this product? This action cannot be undone.')">
                <i class="fas fa-trash-alt"></i> Delete
            </a>
        </div>
    </div>

    <div class="info-card">
        <div class="card-header">
            <i class="fas fa-info-circle"></i> Product Information
        </div>
        <div class="card-body">
            <div class="product-image">
                <img src="<?= $img ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='../../assets/images/default-product.jpg'">
            </div>
            <div class="details-grid">
                <div class="info-row">
                    <div class="info-label">Product ID</div>
                    <div class="info-value"><?= $product['product_id'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Product Name</div>
                    <div class="info-value"><?= htmlspecialchars($product['name']) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Business</div>
                    <div class="info-value">
                        <a href="../businesses/view.php?id=<?= $product['business_id'] ?>" style="color: #e67e22; text-decoration: none;">
                            <?= htmlspecialchars($product['business_name']) ?>
                        </a>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Price</div>
                    <div class="info-value">TSh <?= number_format($product['price']) ?> / <?= $product['unit'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Stock Quantity</div>
                    <div class="info-value">
                        <?= $product['quantity_in_stock'] ?> <?= $product['unit'] ?>s
                        <span class="badge <?= $stock_badge ?>" style="margin-left: 0.5rem;"><?= $stock_text ?></span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Minimum Order</div>
                    <div class="info-value"><?= $product['min_order'] ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Status</div>
                    <div class="info-value">
                        <span class="badge badge-<?= $product['is_available'] ? 'active' : 'inactive' ?>">
                            <?= $product['is_available'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description</div>
                    <div class="info-value"><?= nl2br(htmlspecialchars($product['description'] ?? 'No description')) ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Customer Reviews</div>
                    <div class="info-value">
                        <span class="rating-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i <= round($avg_rating) ? '' : '-o' ?>"></i>
                            <?php endfor; ?>
                        </span>
                        <?= $avg_rating ?> ★ (<?= $review_count ?> <?= $review_count == 1 ? 'review' : 'reviews' ?>)
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Created At</div>
                    <div class="info-value"><?= date('F j, Y g:i A', strtotime($product['created_at'])) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>