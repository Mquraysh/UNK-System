<?php
// customer/products/index.php 
require_once '../../config/database.php';
session_start();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Helper: get all subcategory IDs for a given parent category (iterative)
function getAllSubcategoryIds($conn, $parent_id) {
    $ids = [$parent_id];
    $stack = [$parent_id];
    while (!empty($stack)) {
        $current = array_shift($stack);
        $stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE parent_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $current);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = $row['category_id'];
            $stack[] = $row['category_id'];
        }
        mysqli_stmt_close($stmt);
    }
    return array_unique($ids);
}

// Helper: get category IDs that match search term (including subcategories)
function getSearchCategoryIds($conn, $search_term) {
    $category_ids = [];
    $stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE name LIKE ?");
    $search_like = "%$search_term%";
    mysqli_stmt_bind_param($stmt, 's', $search_like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $category_ids[] = $row['category_id'];
    }
    mysqli_stmt_close($stmt);
    
    $expanded = [];
    foreach ($category_ids as $cid) {
        $expanded = array_merge($expanded, getAllSubcategoryIds($conn, $cid));
    }
    return array_unique($expanded);
}

// Build category filter IDs (selected category + its subcategories)
$category_ids = [];
if ($category_id > 0) {
    $category_ids = getAllSubcategoryIds($conn, $category_id);
}

// Build WHERE clause
$where = "WHERE p.is_available = 1";
$params = [];
$types = "";

// Category filter
if (!empty($category_ids)) {
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $where .= " AND p.category_id IN ($placeholders)";
    $params = array_merge($params, $category_ids);
    $types .= str_repeat('i', count($category_ids));
}

// Search filter (product name, description, and category names)
if (!empty($search)) {
    $search_param = "%$search%";
    $search_cat_ids = getSearchCategoryIds($conn, $search);
    
    if (!empty($search_cat_ids)) {
        $cat_placeholders = implode(',', array_fill(0, count($search_cat_ids), '?'));
        $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.category_id IN ($cat_placeholders))";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $params = array_merge($params, $search_cat_ids);
        $types .= "sss" . str_repeat('i', count($search_cat_ids));
    } else {
        $where .= " AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
        $types .= "sss";
    }
}

// Price range
if ($min_price > 0) {
    $where .= " AND p.price >= ?";
    $params[] = $min_price;
    $types .= "i";
}
if ($max_price > 0) {
    $where .= " AND p.price <= ?";
    $params[] = $max_price;
    $types .= "i";
}

// Sorting
$order_by = "ORDER BY p.created_at DESC";
if ($sort == 'price_low') $order_by = "ORDER BY p.price ASC";
if ($sort == 'price_high') $order_by = "ORDER BY p.price DESC";
if ($sort == 'popular') $order_by = "ORDER BY p.views DESC";
if ($sort == 'name_asc') $order_by = "ORDER BY p.name ASC";
if ($sort == 'name_desc') $order_by = "ORDER BY p.name DESC";

// Total count
$count_sql = "SELECT COUNT(*) as total 
              FROM products p 
              JOIN categories c ON p.category_id = c.category_id 
              $where";
$stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$count_result = mysqli_stmt_get_result($stmt);
$count_data = mysqli_fetch_assoc($count_result);
$total_products = $count_data['total'] ?? 0;
$total_pages = ceil($total_products / $limit);
mysqli_stmt_close($stmt);

// Get products
$sql = "SELECT p.*, b.business_name, b.location, c.name as category_name 
        FROM products p 
        JOIN businesses b ON p.business_id = b.business_id 
        JOIN categories c ON p.category_id = c.category_id 
        $where 
        $order_by 
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);

// Fetch ratings
$product_ids = array_column($products, 'product_id');
$ratings = [];
if (!empty($product_ids)) {
    $ids_string = implode(',', array_map('intval', $product_ids));
    $rating_sql = "SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count 
                   FROM reviews 
                   WHERE product_id IN ($ids_string) AND status = 'approved' 
                   GROUP BY product_id";
    $rating_res = mysqli_query($conn, $rating_sql);
    while ($row = mysqli_fetch_assoc($rating_res)) {
        $ratings[$row['product_id']] = [
            'avg' => round($row['avg_rating'], 1),
            'count' => (int)$row['review_count']
        ];
    }
}

// Build category tree for sidebar
$all_categories = [];
$cat_sql = "SELECT * FROM categories ORDER BY parent_id, sort_order, name";
$cat_result = mysqli_query($conn, $cat_sql);
while ($row = mysqli_fetch_assoc($cat_result)) {
    $all_categories[] = $row;
}

function buildCategoryTree($categories, $parent_id = NULL) {
    $branch = [];
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) {
            $children = buildCategoryTree($categories, $cat['category_id']);
            $cat['children'] = $children;
            $branch[] = $cat;
        }
    }
    return $branch;
}

function displayCategoryTree($tree, $current_category_id, $level = 0) {
    $html = '';
    foreach ($tree as $cat) {
        $has_children = !empty($cat['children']);
        $is_active = ($current_category_id == $cat['category_id']);
        $html .= '<li class="category-item">';
        if ($has_children) {
            $html .= '<div class="category-link-wrapper">';
            $html .= '<a href="javascript:void(0)" class="category-link main-category" onclick="toggleSubcategories(this)">';
            $html .= '<span class="category-name"><i class="fas fa-folder"></i> ' . htmlspecialchars($cat['name']) . '</span>';
            $html .= '<span class="category-toggle-icon"><i class="fas fa-chevron-right"></i></span>';
            $html .= '</a></div>';
            $html .= '<ul class="subcategory-list" style="display: none;">';
            $html .= displayCategoryTree($cat['children'], $current_category_id, $level + 1);
            $html .= '</ul>';
        } else {
            $html .= '<div class="category-link-wrapper">';
            $html .= '<a href="?category=' . $cat['category_id'] . '&page=1" class="category-link ' . ($is_active ? 'active' : '') . '">';
            $html .= '<span class="category-name"><i class="fas fa-folder"></i> ' . htmlspecialchars($cat['name']) . '</span>';
            $html .= '</a></div>';
        }
        $html .= '</li>';
    }
    return $html;
}

$category_tree = buildCategoryTree($all_categories);
$category_list_html = displayCategoryTree($category_tree, $category_id);
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer';

if (!isset($_SESSION['compare_products'])) {
    $_SESSION['compare_products'] = [];
}
include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Shop | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (CSS unchanged – same as your working version) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f7f9fc; color: #1e293b; }
        .customer-content { margin-left: 0; padding: 30px 35px; min-height: 100vh; background: #f5f7fb; transition: all 0.3s; }
        .hero-section { text-align: center; margin-bottom: 35px; }
        .hero-section h1 { font-size: 28px; font-weight: 700; }
        .hero-section h1 i { color: #e67e22; margin-right: 12px; }
        .top-search-bar { background: white; border-radius: 20px; padding: 20px 25px; margin-bottom: 25px; display: flex; gap: 15px; flex-wrap: wrap; border: 1px solid #e2e8f0; }
        .top-search-input { flex: 1; padding: 12px 18px; border: 2px solid #e2e8f0; border-radius: 40px; }
        .top-search-input:focus { outline: none; border-color: #e67e22; }
        .top-search-btn { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 40px; font-weight: 600; cursor: pointer; }
        .compare-bar { background: white; border-radius: 16px; padding: 10px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border: 1px solid #e2e8f0; }
        .btn-compare-page { background: #3498db; color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-size: 12px; }
        .filter-toggle-btn { display: none; background: #e67e22; color: white; border: none; padding: 12px 20px; border-radius: 40px; font-weight: 600; width: 100%; margin-bottom: 20px; }
        .shop-layout { display: grid; grid-template-columns: 280px 1fr; gap: 30px; }
        .filters-sidebar { position: sticky; top: 20px; height: fit-content; }
        .filter-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .filter-title { font-weight: 700; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e67e22; }
        .category-list { list-style: none; }
        .category-link { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #475569; font-size: 12px; cursor: pointer; }
        .category-link:hover, .category-link.active { background: #e67e22; color: white; }
        .subcategory-list { list-style: none; margin-left: 28px; padding-left: 10px; border-left: 1px dashed #e2e8f0; display: none; }
        .price-inputs { display: flex; gap: 10px; }
        .price-field { flex: 1; }
        .price-field input { width: 100%; padding: 8px 10px; border: 2px solid #e2e8f0; border-radius: 10px; }
        .btn { display: inline-block; padding: 8px 16px; border-radius: 10px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; border: none; cursor: pointer; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-outline { background: transparent; border: 2px solid #e2e8f0; color: #475569; }
        .btn-block { width: 100%; }
        .products-header { background: white; border-radius: 16px; padding: 14px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; border: 1px solid #e2e8f0; }
        .sort-select { padding: 8px 16px; border: 2px solid #e2e8f0; border-radius: 30px; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .product-card { background: white; border-radius: 16px; overflow: hidden; transition: 0.3s; border: 1px solid #e2e8f0; position: relative; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1); border-color: #e67e22; }
        .product-badges { position: absolute; top: 10px; left: 10px; display: flex; gap: 5px; z-index: 10; }
        .badge { padding: 3px 8px; border-radius: 20px; font-size: 9px; font-weight: 700; }
        .badge-new { background: #27ae60; color: white; }
        .badge-low-stock { background: #f39c12; color: white; }
        .product-image { position: relative; height: 160px; overflow: hidden; background: #f8fafc; cursor: pointer; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .product-card:hover .product-image img { transform: scale(1.05); }
        .product-actions-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 10px; display: flex; gap: 8px; justify-content: center; transform: translateY(100%); transition: transform 0.3s; }
        .product-card:hover .product-actions-overlay { transform: translateY(0); }
        .action-icon { background: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; }
        .action-icon:hover { background: #e67e22; color: white; }
        .product-info { padding: 12px; }
        .product-category { font-size: 9px; font-weight: 600; color: #e67e22; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 13px; font-weight: 700; margin-bottom: 5px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 34px; }
        .product-business { font-size: 10px; color: #64748b; margin-bottom: 6px; }
        .rating-mini { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
        .rating-stars-mini { color: #f39c12; }
        .product-price { font-size: 16px; font-weight: 800; color: #e67e22; margin-bottom: 8px; }
        .product-actions { display: flex; gap: 6px; margin-top: 8px; }
        .btn-cart { flex: 2; padding: 6px 8px; background: #2c3e50; color: white; border: none; border-radius: 8px; font-size: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; }
        .btn-cart:hover { background: #e67e22; }
        .btn-view { flex: 1; padding: 6px 8px; background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; }
        .btn-view:hover { background: #e67e22; color: white; border-color: #e67e22; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 40px; }
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 10px; text-decoration: none; background: white; color: #475569; border: 1px solid #e2e8f0; }
        .pagination .active { background: #e67e22; color: white; border-color: #e67e22; }
        .empty-state { text-align: center; padding: 60px 40px; background: white; border-radius: 20px; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { max-width: 900px; width: 90%; max-height: 90%; overflow: auto; background: white; border-radius: 20px; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: #27ae60; color: white; padding: 12px 20px; border-radius: 10px; z-index: 1001; display: none; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @media (max-width: 1024px) {
            .customer-content { padding: 20px; }
            .filter-toggle-btn { display: block; }
            .shop-layout { grid-template-columns: 1fr; }
            .filters-sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100vh; overflow-y: auto; background: white; z-index: 1002; padding: 20px; transition: left 0.3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
            .filters-sidebar.show { left: 0; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1001; }
            .sidebar-overlay.show { display: block; }
        }
        @media (max-width: 768px) {
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .product-image { height: 120px; }
        }
    </style>
</head>
<body>

<div class="customer-content">
    <div class="hero-section">
        <h1><i class="fas fa-store"></i> Welcome to Our Shop</h1>
        <p>Discover amazing products from trusted sellers across Tanzania</p>
    </div>

    <div class="top-search-bar">
        <form method="GET" style="display: flex; gap: 15px; flex:1; flex-wrap: wrap;">
            <input type="text" name="search" class="top-search-input" placeholder="Search products or categories (e.g., Electronics)..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="top-search-btn"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search)): ?>
                <a href="index.php" class="btn-outline" style="padding: 12px 20px;">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="compare-bar">
        <div><i class="fas fa-chart-line"></i> <strong><?= count($_SESSION['compare_products']) ?></strong> / 4 products selected</div>
        <?php if (!empty($_SESSION['compare_products'])): ?>
            <a href="compare.php" class="btn-compare-page">Compare Now</a>
        <?php endif; ?>
    </div>

    <button class="filter-toggle-btn" id="filterToggleBtn"><i class="fas fa-filter"></i> Show Filters</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="shop-layout">
        <aside class="filters-sidebar" id="filtersSidebar">
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-tags"></i> Categories</div>
                <ul class="category-list">
                    <li><a href="?category=0&page=1" class="category-link <?= $category_id == 0 ? 'active' : '' ?>"><span><i class="fas fa-th-large"></i> All Products</span></a></li>
                    <?= $category_list_html ?>
                </ul>
            </div>
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-dollar-sign"></i> Price Range</div>
                <form method="GET">
                    <div class="price-inputs">
                        <div class="price-field"><label>Min (TSh)</label><input type="number" name="min_price" value="<?= $min_price ?: '' ?>" placeholder="0"></div>
                        <div class="price-field"><label>Max (TSh)</label><input type="number" name="max_price" value="<?= $max_price ?: '' ?>" placeholder="Any"></div>
                    </div><br>
                    <button type="submit" class="btn btn-primary btn-block">Apply</button>
                </form>
            </div>
            <div class="filter-card"><a href="index.php" class="btn btn-outline btn-block">Clear All Filters</a></div>
            <div class="filter-card"><div class="filter-title"><i class="fas fa-history"></i> Recently Viewed</div><a href="recently-viewed.php" class="btn btn-outline btn-block">View History →</a></div>
            <div class="filter-card"><div class="filter-title"><i class="fas fa-bell"></i> Price Alerts</div><a href="price-alert.php" class="btn btn-outline btn-block">Manage Alerts →</a></div>
        </aside>

        <main>
            <div class="products-header">
                <div><i class="fas fa-box"></i> Showing <strong><?= count($products) ?></strong> of <strong><?= $total_products ?></strong> products</div>
                <form method="GET">
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name A to Z</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name Z to A</option>
                    </select>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state"><i class="fas fa-box-open" style="font-size:64px; color:#cbd5e1;"></i><h3>No products found</h3><p>Try adjusting your search or filter.</p><a href="index.php" class="btn btn-primary" style="margin-top:15px;">Clear Filters</a></div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product):
                        $days_old = (time() - strtotime($product['created_at'])) / (60 * 60 * 24);
                        $img_src = '../../assets/images/default-product.jpg';
                        if (!empty($product['image_url']) && file_exists('../../' . $product['image_url'])) $img_src = '../../' . $product['image_url'];
                        elseif (!empty($product['image_url']) && file_exists($product['image_url'])) $img_src = $product['image_url'];
                        $rating = $ratings[$product['product_id']] ?? ['avg' => 0, 'count' => 0];
                    ?>
                    <div class="product-card" data-product-id="<?= $product['product_id'] ?>">
                        <div class="product-badges">
                            <?php if ($days_old < 7): ?><span class="badge badge-new">New</span><?php endif; ?>
                            <?php if ($product['quantity_in_stock'] < 10 && $product['quantity_in_stock'] > 0): ?><span class="badge badge-low-stock">Low Stock</span><?php endif; ?>
                        </div>
                        <div class="product-image" onclick="window.location.href='details.php?id=<?= $product['product_id'] ?>'">
                            <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                            <div class="product-actions-overlay">
                                <button class="action-icon quick-view-btn" data-id="<?= $product['product_id'] ?>" title="Quick View"><i class="fas fa-eye"></i></button>
                                <button class="action-icon compare-btn" data-id="<?= $product['product_id'] ?>" title="Compare"><i class="fas fa-chart-line"></i></button>
                                <button class="action-icon price-alert-btn" data-id="<?= $product['product_id'] ?>" data-price="<?= $product['price'] ?>" title="Price Alert"><i class="fas fa-bell"></i></button>
                                <button class="action-icon bulk-order-btn" data-id="<?= $product['product_id'] ?>" title="Bulk Order"><i class="fas fa-boxes"></i></button>
                            </div>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?= htmlspecialchars($product['category_name'] ?? 'Product') ?></div>
                            <div class="product-title"><?= htmlspecialchars($product['name']) ?></div>
                            <div class="product-business"><i class="fas fa-store"></i> <?= htmlspecialchars($product['business_name']) ?></div>
                            <div class="rating-mini">
                                <span class="rating-stars-mini"><?php for ($i=1; $i<=5; $i++): ?><i class="fas fa-star<?= $i <= round($rating['avg']) ? '' : '-o' ?>"></i><?php endfor; ?></span>
                                <span>(<?= $rating['count'] ?> reviews)</span>
                            </div>
                            <div class="product-price">TSh <?= number_format($product['price'], 0, '.', ',') ?></div>
                            <div class="product-actions">
                                <?php if ($is_logged_in && $product['quantity_in_stock'] > 0): ?>
                                    <button class="btn-cart add-to-cart-btn" data-id="<?= $product['product_id'] ?>"><i class="fas fa-cart-plus"></i> Cart</button>
                                <?php elseif (!$is_logged_in): ?>
                                    <a href="../login.php" class="btn-cart"><i class="fas fa-sign-in-alt"></i> Login to Buy</a>
                                <?php else: ?>
                                    <button class="btn-cart" disabled style="background:#94a3b8; cursor:not-allowed;">Out of Stock</button>
                                <?php endif; ?>
                                <a href="details.php?id=<?= $product['product_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>"><i class="fas fa-chevron-left"></i> Previous</a><?php else: ?><span><i class="fas fa-chevron-left"></i> Previous</span><?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <?= $i == $page ? '<span class="active">'.$i.'</span>' : '<a href="?page='.$i.'">'.$i.'</a>' ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>">Next <i class="fas fa-chevron-right"></i></a><?php else: ?><span>Next <i class="fas fa-chevron-right"></i></span><?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modals -->
<div id="quickViewModal" class="modal"><div class="modal-content" id="quickViewContent"></div></div>
<div id="toast" class="toast"></div>

<script>
function showToast(msg, isError = false) {
    let t = document.getElementById('toast');
    t.innerHTML = '<i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i> ' + msg;
    t.style.background = isError ? '#dc2626' : '#27ae60';
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 3000);
}

// Add to cart
function addToCart(productId) {
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '../cart/add.php';
    form.innerHTML = `<input type="hidden" name="product_id" value="${productId}"><input type="hidden" name="quantity" value="1">`;
    document.body.appendChild(form);
    form.submit();
}

// Quick View
function quickView(productId) {
    let modal = document.getElementById('quickViewModal');
    let content = document.getElementById('quickViewContent');
    if (!modal || !content) return;
    modal.classList.add('active');
    fetch('quick-view.php?id=' + encodeURIComponent(productId))
        .then(res => res.text())
        .then(html => { content.innerHTML = html; })
        .catch(err => { content.innerHTML = '<div style="padding:20px;text-align:center;">Error loading product</div>'; });
}

// Compare
function addToCompare(productId) {
    fetch('compare.php?add=' + productId)
        .then(() => { showToast('Added to compare'); setTimeout(() => location.reload(), 800); });
}

// Price Alert
function setPriceAlert(productId, currentPrice) {
    let desired = prompt('Enter desired price (TSh):\nCurrent: TSh ' + currentPrice.toLocaleString(), Math.round(currentPrice * 0.8));
    if (desired && desired > 0) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = 'price-alert.php';
        form.innerHTML = `<input type="hidden" name="product_id" value="${productId}"><input type="hidden" name="desired_price" value="${desired}">`;
        document.body.appendChild(form);
        form.submit();
    }
}

// Bulk Order
function bulkOrder(productId) { window.location.href = 'bulk-order.php?id=' + productId; }

document.addEventListener('DOMContentLoaded', function() {
    // Quick View buttons
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) quickView(id);
        });
    });
    // Compare buttons
    document.querySelectorAll('.compare-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) addToCompare(id);
        });
    });
    // Price Alert buttons
    document.querySelectorAll('.price-alert-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            let price = btn.getAttribute('data-price');
            if (id && price) setPriceAlert(id, parseFloat(price));
        });
    });
    // Bulk Order buttons
    document.querySelectorAll('.bulk-order-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) bulkOrder(id);
        });
    });
    // Add to Cart buttons (AJAX)
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) addToCart(id);
        });
    });
});

// Modal close
document.getElementById('quickViewModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('quickViewModal')?.classList.remove('active');
});

// Category toggle
function toggleSubcategories(el) {
    let parentLi = el.closest('.category-item');
    let subUl = parentLi?.querySelector('.subcategory-list');
    if (subUl) subUl.style.display = subUl.style.display === 'none' ? 'block' : 'none';
}

// Mobile sidebar
const filterToggle = document.getElementById('filterToggleBtn');
const sidebar = document.getElementById('filtersSidebar');
const overlay = document.getElementById('sidebarOverlay');
function openSidebar() { sidebar.classList.add('show'); overlay.classList.add('show'); filterToggle.innerHTML = '<i class="fas fa-times"></i> Hide Filters'; }
function closeSidebar() { sidebar.classList.remove('show'); overlay.classList.remove('show'); filterToggle.innerHTML = '<i class="fas fa-filter"></i> Show Filters'; }
if (filterToggle) filterToggle.addEventListener('click', () => { sidebar.classList.contains('show') ? closeSidebar() : openSidebar(); });
if (overlay) overlay.addEventListener('click', closeSidebar);
</script>

</body>
</html>
<?php include '../../includes/footer.php'; ?>