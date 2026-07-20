<?php
// customer/wishlist/index.php 
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
if (mysqli_num_rows($customer_result) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer_data = mysqli_fetch_assoc($customer_result);
$customer_id = $customer_data['customer_id'];

// HANDLE BULK ACTIONS (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['bulk_action']) && isset($_POST['selected_items'])) {
        $bulk_action = $_POST['bulk_action'];
        $selected = $_POST['selected_items'];
        if ($bulk_action == 'move_to_cart') {
            foreach ($selected as $wishlist_id) {
                $wish = mysqli_fetch_assoc(mysqli_query($conn, "SELECT product_id FROM wishlist WHERE wishlist_id = $wishlist_id AND customer_id = $customer_id"));
                if ($wish) {
                    $product_id = $wish['product_id'];
                    $cart_check = mysqli_query($conn, "SELECT cart_id FROM cart WHERE customer_id = $customer_id AND product_id = $product_id");
                    if (mysqli_num_rows($cart_check) == 0) {
                        mysqli_query($conn, "INSERT INTO cart (customer_id, product_id, quantity) VALUES ($customer_id, $product_id, 1)");
                    } else {
                        mysqli_query($conn, "UPDATE cart SET quantity = quantity + 1 WHERE customer_id = $customer_id AND product_id = $product_id");
                    }
                }
            }
            $_SESSION['flash_message'] = count($selected) . " item(s) moved to cart.";
            $_SESSION['flash_type'] = "success";
        } elseif ($bulk_action == 'remove') {
            $ids = implode(',', array_map('intval', $selected));
            mysqli_query($conn, "DELETE FROM wishlist WHERE wishlist_id IN ($ids) AND customer_id = $customer_id");
            $_SESSION['flash_message'] = count($selected) . " item(s) removed from wishlist.";
            $_SESSION['flash_type'] = "success";
        }
        header("Location: index.php");
        exit();
    }
}

// GET WISHLIST ITEMS WITH FILTERS & SORTING
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

$sql = "SELECT w.*, p.name, p.price, p.image_url, p.quantity_in_stock, p.unit, 
               b.business_name, b.business_id
        FROM wishlist w
        JOIN products p ON w.product_id = p.product_id
        JOIN businesses b ON p.business_id = b.business_id
        WHERE w.customer_id = '$customer_id'";

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (p.name LIKE '%$search_esc%' OR b.business_name LIKE '%$search_esc%')";
}

if ($stock_filter == 'in_stock') {
    $sql .= " AND p.quantity_in_stock > 0";
} elseif ($stock_filter == 'low_stock') {
    $sql .= " AND p.quantity_in_stock BETWEEN 1 AND 9";
} elseif ($stock_filter == 'out_of_stock') {
    $sql .= " AND p.quantity_in_stock = 0";
}

if ($sort == 'price_asc') {
    $sql .= " ORDER BY p.price ASC";
} elseif ($sort == 'price_desc') {
    $sql .= " ORDER BY p.price DESC";
} elseif ($sort == 'name_asc') {
    $sql .= " ORDER BY p.name ASC";
} elseif ($sort == 'name_desc') {
    $sql .= " ORDER BY p.name DESC";
} else {
    $sql .= " ORDER BY w.added_at DESC";
}

$result = mysqli_query($conn, $sql);
$wishlist_items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $wishlist_items[] = $row;
}

$total_items = count($wishlist_items);
$total_value = array_sum(array_column($wishlist_items, 'price'));
$unique_sellers = count(array_unique(array_column($wishlist_items, 'business_id')));

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Wishlist - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 28px 32px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            color: #e74c3c;
            font-size: 28px;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .btn-shop-sm {
            background: #e67e22;
            color: white;
            padding: 10px 22px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-shop-sm:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        
        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
            font-size: 14px;
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        
        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #eef2f8;
            transition: all 0.2s;
        }
        .stat-card:hover {
            border-color: #e67e22;
        }
        .stat-card h3 {
            font-size: 24px;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-card p {
            color: #64748b;
            font-size: 12px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .stat-card p i { color: #e67e22; }
        
        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 14px 20px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            border: 1px solid #eef2f8;
        }
        .filters-left {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            padding: 6px 14px 6px 18px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
        }
        .search-box input {
            border: none;
            background: none;
            padding: 8px 0;
            width: 160px;
            font-size: 13px;
            outline: none;
        }
        .search-box button {
            background: none;
            border: none;
            color: #e67e22;
            cursor: pointer;
        }
        .sort-select, .stock-select {
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
            background: white;
            font-size: 13px;
            cursor: pointer;
            outline: none;
        }
        .sort-select:focus, .stock-select:focus {
            border-color: #e67e22;
        }
        .btn-reset {
            background: #f1f5f9;
            color: #475569;
            padding: 8px 16px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-reset:hover {
            background: #e67e22;
            color: white;
        }
        
        /* Bulk Bar */
        .bulk-bar {
            background: white;
            border-radius: 16px;
            padding: 12px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid #eef2f8;
        }
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .bulk-select {
            padding: 8px 16px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
            font-size: 13px;
            outline: none;
        }
        .bulk-select:focus {
            border-color: #e67e22;
        }
        .btn-bulk {
            background: #e67e22;
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: 0.2s;
        }
        .btn-bulk:hover {
            background: #d35400;
        }
        .btn-move-all {
            background: #27ae60;
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-move-all:hover {
            background: #219a52;
        }
        .selected-count {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Wishlist Grid - Smaller Cards */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 18px;
        }
        .wishlist-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s;
            border: 1px solid #eef2f8;
            position: relative;
        }
        .wishlist-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        
        /* Product Image - Smaller */
        .product-image {
            position: relative;
            height: 140px;
            overflow: hidden;
            background: #f8fafc;
        }
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .wishlist-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        /* Checkbox */
        .checkbox-item {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 5;
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #e67e22;
            background: white;
            border-radius: 4px;
        }
        
        /* Remove Button */
        .remove-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: white;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e74c3c;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: 0.2s;
            font-size: 12px;
            z-index: 5;
        }
        .remove-btn:hover {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
        }
        
        /* Stock Badge */
        .stock-badge {
            position: absolute;
            bottom: 8px;
            left: 8px;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stock-in { background: #27ae60; color: white; }
        .stock-low { background: #f39c12; color: white; }
        .stock-out { background: #e74c3c; color: white; }
        
        /* Product Info */
        .product-info {
            padding: 12px 14px 14px;
        }
        .product-business {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .product-business i {
            font-size: 10px;
            color: #e67e22;
        }
        .product-name {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
            display: -webkit-box;
            /* -webkit-line-clamp: 2; */
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 36px;
            color: #1e293b;
            line-height: 1.3;
        }
        .product-price {
            font-size: 15px;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 10px;
        }
        .product-price .unit {
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
        }
        
        /* Product Actions */
        .product-actions {
            display: flex;
            gap: 6px;
        }
        .btn-cart {
            flex: 2;
            padding: 7px 0;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: 0.2s;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .btn-cart:hover:not(:disabled) {
            background: #e67e22;
            transform: translateY(-1px);
        }
        .btn-cart:disabled {
            background: #94a3b8;
            cursor: not-allowed;
        }
        .btn-view {
            flex: 1;
            padding: 7px 0;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: 0.2s;
        }
        .btn-view:hover {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 20px;
            border: 1px solid #eef2f8;
        }
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        .empty-state p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        /* Clear All Button */
        .clear-all-wrap {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #eef2f8;
        }
        .btn-clear-all {
            background: #fef2f2;
            color: #dc2626;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #fecaca;
        }
        .btn-clear-all:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        
        /* Responsive */
        @media (max-width: 1100px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .wishlist-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .product-image { height: 110px; }
            .product-name { font-size: 12px; height: 30px; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filters-left { flex-wrap: wrap; }
            .search-box { flex: 1; }
            .search-box input { width: 100px; }
            .bulk-bar { flex-direction: column; align-items: stretch; }
            .bulk-actions { flex-wrap: wrap; }
            .page-header h1 { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .wishlist-grid { grid-template-columns: 1fr; }
            .product-image { height: 160px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-heart"></i> My Wishlist</h1>
            <p><i class="fas fa-clock"></i> Products you've saved for later</p>
        </div>
        <a href="../products/index.php" class="btn-shop-sm"><i class="fas fa-store"></i> Continue Shopping</a>
    </div>

    <!-- Flash Message -->
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($wishlist_items)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-heart-broken"></i>
            <h3>Your wishlist is empty</h3>
            <p>Save your favorite products here to buy them later</p>
            <a href="../products/index.php" class="btn-shop-sm" style="background: #e67e22;"><i class="fas fa-store"></i> Start Shopping</a>
        </div>
    <?php else: ?>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_items; ?></h3>
                <p><i class="fas fa-heart"></i> Items in Wishlist</p>
            </div>
            <div class="stat-card">
                <h3>TSh <?php echo number_format($total_value); ?></h3>
                <p><i class="fas fa-chart-line"></i> Total Value</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $unique_sellers; ?></h3>
                <p><i class="fas fa-store"></i> Different Sellers</p>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filters-left">
                <form method="GET" class="search-box">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                <select name="sort" class="sort-select" onchange="window.location.href='?sort='+this.value+'&search=<?php echo urlencode($search); ?>&stock=<?php echo $stock_filter; ?>'">
                    <option value="date_desc" <?php echo $sort == 'date_desc' ? 'selected' : ''; ?>>Newest first</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                    <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                </select>
                <select name="stock" class="stock-select" onchange="window.location.href='?stock='+this.value+'&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>'">
                    <option value="">All stock</option>
                    <option value="in_stock" <?php echo $stock_filter == 'in_stock' ? 'selected' : ''; ?>>In stock</option>
                    <option value="low_stock" <?php echo $stock_filter == 'low_stock' ? 'selected' : ''; ?>>Low stock (≤9)</option>
                    <option value="out_of_stock" <?php echo $stock_filter == 'out_of_stock' ? 'selected' : ''; ?>>Out of stock</option>
                </select>
            </div>
            <div>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <form method="POST" id="bulkForm">
            <div class="bulk-bar">
                <div class="bulk-actions">
                    <select name="bulk_action" class="bulk-select" id="bulkAction">
                        <option value="">Bulk actions</option>
                        <option value="move_to_cart"><i class="fas fa-cart-plus"></i> Move to Cart</option>
                        <option value="remove"><i class="fas fa-trash"></i> Remove</option>
                    </select>
                    <button type="button" class="btn-bulk" onclick="executeBulkAction()">Apply</button>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="selected-count" id="selectedCount">0 selected</span>
                    <a href="move-all-to-cart.php" class="btn-move-all" onclick="return confirm('Move ALL items from wishlist to cart?')">
                        <i class="fas fa-cart-plus"></i> Move All
                    </a>
                </div>
            </div>

            <!-- Wishlist Grid -->
            <div class="wishlist-grid">
                <?php foreach ($wishlist_items as $item):
                    $img_src = '../../assets/images/default-product.jpg';
                    if (!empty($item['image_url'])) {
                        if (file_exists('../../' . $item['image_url'])) $img_src = '../../' . $item['image_url'];
                        elseif (file_exists($item['image_url'])) $img_src = $item['image_url'];
                    }
                    $stock_class = 'stock-in';
                    $stock_text = 'In Stock';
                    if ($item['quantity_in_stock'] <= 0) {
                        $stock_class = 'stock-out';
                        $stock_text = 'Out of Stock';
                    } elseif ($item['quantity_in_stock'] < 10) {
                        $stock_class = 'stock-low';
                        $stock_text = 'Only ' . $item['quantity_in_stock'] . ' left';
                    }
                ?>
                <div class="wishlist-card">
                    <input type="checkbox" name="selected_items[]" value="<?php echo $item['wishlist_id']; ?>" class="checkbox-item" onclick="updateSelectedCount()">
                    
                    <div class="product-image">
                        <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" loading="lazy" onerror="this.src='../../assets/images/default-product.jpg'">
                        <a href="remove.php?id=<?php echo $item['wishlist_id']; ?>" class="remove-btn" onclick="return confirm('Remove this item?')"><i class="fas fa-times"></i></a>
                        <span class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></span>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-business"><i class="fas fa-store"></i> <?php echo htmlspecialchars($item['business_name']); ?></div>
                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="product-price">
                            TSh <?php echo number_format($item['price']); ?>
                            <span class="unit">/ <?php echo $item['unit']; ?></span>
                        </div>
                        <div class="product-actions">
                            <?php if ($item['quantity_in_stock'] > 0): ?>
                                <a href="move-to-cart.php?id=<?php echo $item['wishlist_id']; ?>" class="btn-cart"><i class="fas fa-cart-plus"></i> Move</a>
                            <?php else: ?>
                                <button class="btn-cart" disabled><i class="fas fa-times"></i> Out of Stock</button>
                            <?php endif; ?>
                            <a href="../products/details.php?id=<?php echo $item['product_id']; ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>

        <!-- Clear All -->
        <div class="clear-all-wrap">
            <a href="clear.php" class="btn-clear-all" onclick="return confirm('Are you sure you want to clear your entire wishlist?')">
                <i class="fas fa-trash-alt"></i> Clear All Wishlist
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function updateSelectedCount() {
    let checkboxes = document.querySelectorAll('input[name="selected_items[]"]:checked');
    document.getElementById('selectedCount').textContent = checkboxes.length + ' selected';
}

function executeBulkAction() {
    let action = document.getElementById('bulkAction').value;
    let checkboxes = document.querySelectorAll('input[name="selected_items[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('Please select at least one item');
        return;
    }
    if (action === '') {
        alert('Please select a bulk action');
        return;
    }
    
    let confirmMsg = action === 'move_to_cart' ? 'Move selected items to cart?' : 'Remove selected items?';
    if (confirm(confirmMsg)) {
        document.getElementById('bulkForm').submit();
    }
}

// Auto update selected count on load
document.addEventListener('DOMContentLoaded', updateSelectedCount);
</script>
</body>
</html>