<?php
// customer/products/index.php 
require_once '../../config/database.php';
session_start();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$style = isset($_GET['style']) ? $_GET['style'] : '';

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
$where = "WHERE p.is_available = 1 AND p.deleted_at IS NULL";
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

// Budget / Price Range (Manual Input - Min and Max)
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

// STYLE FILTERS - Now based on PRICE
if (!empty($style)) {
    switch($style) {
        case 'budget':
            $where .= " AND p.price <= 100000";
            break;
        case 'midrange':
            $where .= " AND p.price BETWEEN 100000 AND 500000";
            break;
        case 'premium':
            $where .= " AND p.price BETWEEN 500000 AND 1000000";
            break;
        case 'luxury':
            $where .= " AND p.price >= 1000000";
            break;
        case 'popular':
            $where .= " AND p.views > 30";
            break;
        case 'bestvalue':
            $where .= " AND p.price <= 500000";
            break;
    }
}

// Sorting
$order_by = "ORDER BY p.created_at DESC";
if ($sort == 'price_low') $order_by = "ORDER BY p.price ASC";
if ($sort == 'price_high') $order_by = "ORDER BY p.price DESC";
if ($sort == 'popular') $order_by = "ORDER BY p.views DESC";
if ($sort == 'name_asc') $order_by = "ORDER BY p.name ASC";
if ($sort == 'name_desc') $order_by = "ORDER BY p.name DESC";

// Get min and max price for reference
$price_range_sql = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_available = 1 AND deleted_at IS NULL";
$price_range_result = mysqli_query($conn, $price_range_sql);
$price_range = mysqli_fetch_assoc($price_range_result);
$global_min_price = $price_range['min_price'] ?? 0;
$global_max_price = $price_range['max_price'] ?? 5000000;

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
$compare_count = count($_SESSION['compare_products']);

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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f7f9fc; color: #1e293b; }
        .customer-content { margin-left: 0; padding: 30px 35px; min-height: 100vh; background: #f5f7fb; transition: all 0.3s; }
        .hero-section { text-align: center; margin-bottom: 35px; }
        .hero-section h1 { font-size: 28px; font-weight: 700; }
        .hero-section h1 i { color: #e67e22; margin-right: 12px; }
        .hero-section p { color: #64748b; }
        
        /* Search Bar with Camera Icon - Professional Design */
        .top-search-bar {
            background: white;
            border-radius: 60px;
            padding: 8px 8px 8px 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s;
        }
        .top-search-bar:focus-within {
            box-shadow: 0 4px 20px rgba(230,126,34,0.15);
            border-color: #e67e22;
        }
        .search-wrapper {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
        }
        .top-search-input {
            width: 100%;
            padding: 14px 50px 14px 18px;
            border: none;
            border-radius: 40px;
            font-size: 14px;
            background: #f8fafc;
        }
        .top-search-input:focus {
            outline: none;
            background: white;
        }
        .camera-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 20px;
            color: #64748b;
            transition: all 0.2s;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .camera-icon:hover {
            background: #f1f5f9;
            color: #e67e22;
        }
        .top-search-btn {
            background: #e67e22;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .top-search-btn:hover {
            background: #d35400;
            transform: translateY(-1px);
        }
        
        /* Image Upload Preview - Professional Design */
        .image-upload-preview {
            display: none;
            background: white;
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .image-upload-preview.show {
            display: block;
            animation: fadeInUp 0.4s ease;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e67e22;
        }
        .preview-header h6 {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        .preview-header h6 i {
            color: #e67e22;
            margin-right: 8px;
        }
        .close-preview {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: all 0.2s;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-preview:hover {
            background: #fee2e2;
            color: #e74c3c;
        }
        .preview-img-container {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 15px;
        }
        .preview-img {
            max-height: 180px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #e67e22;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .similar-results-header {
            margin: 20px 0 15px 0;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .similar-results-header h6 {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
        }
        .similar-products-row {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            scrollbar-width: thin;
        }
        .similar-products-row::-webkit-scrollbar {
            height: 6px;
        }
        .similar-products-row::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 10px;
        }
        .similar-products-row::-webkit-scrollbar-thumb {
            background: #e67e22;
            border-radius: 10px;
        }
        .similar-product-card {
            min-width: 160px;
            background: white;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            cursor: pointer;
        }
        .similar-product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .similar-product-card img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .similar-product-name {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
            display: -webkit-box;
           
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .similar-product-price {
            font-size: 13px;
            font-weight: bold;
            color: #e67e22;
            margin-bottom: 8px;
        }
        .similar-product-btn {
            background: #e67e22;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .similar-product-btn:hover {
            background: #d35400;
        }
        
        .compare-bar { background: white; border-radius: 16px; padding: 10px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border: 1px solid #e2e8f0; }
        .btn-compare-page { background: #3498db; color: white; padding: 8px 16px; border-radius: 10px; text-decoration: none; font-size: 12px; }
        
        .filter-toggle-btn { display: none; background: #e67e22; color: white; border: none; padding: 12px 20px; border-radius: 40px; font-weight: 600; width: 100%; margin-bottom: 20px; cursor: pointer; }
        
        .shop-layout { display: grid; grid-template-columns: 280px 1fr; gap: 30px; }
        .filters-sidebar { position: sticky; top: 20px; height: fit-content; }
        .filter-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .filter-title { font-weight: 700; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #e67e22; font-size: 14px; }
        .filter-title i { margin-right: 8px; color: #e67e22; }
        
        .category-list { list-style: none; margin: 0; padding: 0; }
        .category-item { margin-bottom: 5px; }
        .category-link { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 10px; text-decoration: none; color: #475569; font-size: 12px; cursor: pointer; transition: all 0.2s; }
        .category-link:hover, .category-link.active { background: #e67e22; color: white; }
        .subcategory-list { list-style: none; margin-left: 28px; padding-left: 10px; border-left: 1px dashed #e2e8f0; display: none; }
        
        .price-inputs { display: flex; gap: 10px; }
        .price-field { flex: 1; }
        .price-field label { display: block; font-size: 11px; color: #64748b; margin-bottom: 4px; }
        .price-field input { width: 100%; padding: 8px 10px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 12px; }
        .price-field input:focus { outline: none; border-color: #e67e22; }
        
        .style-buttons-group { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .style-btn { padding: 8px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 30px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .style-btn:hover { background: #e67e22; color: white; border-color: #e67e22; transform: translateY(-2px); }
        .style-btn.active { background: #e67e22; color: white; border-color: #e67e22; box-shadow: 0 2px 5px rgba(230,126,34,0.3); }
        .style-btn i { margin-right: 5px; font-size: 11px; }
        
        .btn { display: inline-block; padding: 8px 16px; border-radius: 10px; font-size: 12px; font-weight: 600; text-decoration: none; text-align: center; border: none; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; }
        .btn-outline { background: transparent; border: 2px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: #e67e22; color: #e67e22; }
        .btn-block { width: 100%; }
        
        .products-header { background: white; border-radius: 16px; padding: 14px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; border: 1px solid #e2e8f0; }
        .sort-select { padding: 8px 16px; border: 2px solid #e2e8f0; border-radius: 30px; background: white; cursor: pointer; font-size: 12px; }
        
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .product-card { background: white; border-radius: 16px; overflow: hidden; transition: 0.3s; border: 1px solid #e2e8f0; position: relative; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -8px rgba(0,0,0,0.1); border-color: #e67e22; }
        .product-card.in-compare { border: 2px solid #3498db; }
        
        .product-badges { position: absolute; top: 10px; left: 10px; display: flex; gap: 5px; z-index: 10; }
        .badge { padding: 3px 8px; border-radius: 20px; font-size: 9px; font-weight: 700; }
        .badge-new { background: #27ae60; color: white; }
        .badge-low-stock { background: #f39c12; color: white; }
        .badge-budget { background: #e67e22; color: white; }
        
        .product-image { position: relative; height: 160px; overflow: hidden; background: #f8fafc; cursor: pointer; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .product-card:hover .product-image img { transform: scale(1.05); }
        
        .product-actions-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); padding: 10px; display: flex; gap: 8px; justify-content: center; transform: translateY(100%); transition: transform 0.3s; }
        .product-card:hover .product-actions-overlay { transform: translateY(0); }
        .action-icon { background: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: none; transition: all 0.2s; }
        .action-icon:hover { background: #e67e22; color: white; transform: scale(1.1); }
        
        .product-info { padding: 12px; }
        .product-category { font-size: 9px; font-weight: 600; color: #e67e22; text-transform: uppercase; margin-bottom: 5px; }
        .product-title { font-size: 13px; font-weight: 700; margin-bottom: 5px; display: -webkit-box;  -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; min-height: 36px; }
        .product-business { font-size: 10px; color: #64748b; margin-bottom: 6px; }
        
        .rating-mini { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
        .rating-stars-mini { color: #f39c12; }
        .product-price { font-size: 16px; font-weight: 800; color: #e67e22; margin-bottom: 8px; }
        
        .product-actions { display: flex; gap: 6px; margin-top: 8px; }
        .btn-cart { flex: 2; padding: 6px 8px; background: #2c3e50; color: white; border: none; border-radius: 8px; font-size: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.2s; }
        .btn-cart:hover { background: #e67e22; transform: translateY(-2px); }
        .btn-view { flex: 1; padding: 6px 8px; background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; transition: all 0.2s; }
        .btn-view:hover { background: #e67e22; color: white; border-color: #e67e22; transform: translateY(-2px); }
        
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 40px;}
        .pagination a, .pagination span { padding: 8px 14px; border-radius: 10px; text-decoration: none; background: white; color: #475569; border: 1px solid #e2e8f0; transition: all 0.2s; }
        .pagination a:hover { background: #e67e22; color: white; border-color: #e67e22; }
        .pagination .active { background: #e67e22; color: white; border-color: #e67e22; }
        
        .empty-state { text-align: center; padding: 60px 40px; background: white; border-radius: 20px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { max-width: 900px; width: 90%; max-height: 90%; overflow: auto; background: white; border-radius: 20px; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: #27ae60; color: white; padding: 12px 20px; border-radius: 10px; z-index: 1001; display: none; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        
        .active-filter-badge { display: inline-block; padding: 3px 8px; background: #e67e22; color: white; border-radius: 12px; font-size: 10px; margin: 2px; }
        
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
            .top-search-bar { border-radius: 20px; padding: 8px 12px; flex-direction: column; }
            .search-wrapper { width: 100%; }
            .top-search-btn { width: 100%; }
        }
        
        .small { font-size: 11px; }
        .text-muted { color: #64748b; }
        .fw-bold { font-weight: 700; }
        .mt-2 { margin-top: 8px; }
        .mb-2 { margin-bottom: 8px; }
        .text-center { text-align: center; }
    </style>
</head>
<body>

<div class="customer-content">
    <div class="hero-section">
        <h1><i class="fas fa-store"></i> Welcome to Our Shop</h1>
        <p>Discover amazing products from trusted sellers across Tanzania</p>
    </div>

    <!-- Search Bar with Camera Icon - Professional -->
    <div class="top-search-bar">
        <form method="GET" style="display: flex; gap: 10px; flex:1; flex-wrap: wrap;" id="searchForm">
            <div class="search-wrapper">
                <input type="text" name="search" class="top-search-input" placeholder="Search products or categories (e.g., Electronics, Phones)..." value="<?= htmlspecialchars($search) ?>" id="searchInput">
                <button type="button" class="camera-icon" id="cameraBtn" title="Search by photo">
                    <i class="fas fa-camera"></i>
                </button>
                <input type="file" id="imageSearchInput" accept="image/*" style="display: none;">
            </div>
            <button type="submit" class="top-search-btn"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search) || $category_id > 0 || $min_price > 0 || $max_price > 0 || !empty($style)): ?>
                <a href="index.php" class="btn-outline" style="padding: 12px 20px; border-radius: 40px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-times"></i> Clear All
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Image Upload Preview - Professional Design -->
    <div id="imageUploadPreview" class="image-upload-preview">
        <div class="preview-header">
            <h6><i class="fas fa-camera"></i> Search by Photo</h6>
            <button type="button" class="close-preview" onclick="closeImagePreview()">&times;</button>
        </div>
        <div class="preview-img-container" id="previewContainer" style="display: none;">
            <img id="previewImg" class="preview-img">
            <div id="loadingSpinner" style="display: none; margin-top: 15px;">
                <div class="loading-spinner"></div>
                <p style="margin-top: 10px; font-size: 12px; color: #64748b;">Analyzing image...</p>
            </div>
        </div>
        <div id="similarResultsContainer" style="display: none;">
            <div class="similar-results-header">
                <h6><i class="fas fa-search"></i> Similar Products Found</h6>
            </div>
            <div id="similarProductsList" class="similar-products-row"></div>
        </div>
    </div>

    <!-- Compare Bar -->
    <div class="compare-bar">
        <div><i class="fas fa-chart-line" style="color:#e67e22;"></i> <strong><?= $compare_count ?></strong> / 4 products selected for comparison</div>
        <?php if ($compare_count > 0): ?>
            <div>
                <a href="compare.php" class="btn-compare-page"><i class="fas fa-chart-line"></i> Compare Now</a>
                <button onclick="clearCompare()" style="background:#e74c3c; color:white; border:none; padding:6px 12px; border-radius:8px; margin-left:10px; cursor:pointer; font-size:11px;">
                    <i class="fas fa-trash"></i> Clear
                </button>
            </div>
        <?php endif; ?>
    </div>

    <button class="filter-toggle-btn" id="filterToggleBtn"><i class="fas fa-filter"></i> Show Filters</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="shop-layout">
        <aside class="filters-sidebar" id="filtersSidebar">
            <!-- CARD 1: CATEGORIES -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-tags"></i> Categories</div>
                <ul class="category-list">
                    <li><a href="?category=0&page=1" class="category-link <?= $category_id == 0 ? 'active' : '' ?>"><span><i class="fas fa-th-large"></i> All Products</span></a></li>
                    <?= $category_list_html ?>
                </ul>
            </div>
            
            <!-- CARD 2: BUDGET / PRICE RANGE -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-chart-line"></i> Budget (TSh)</div>
                <form method="GET" id="budgetForm">
                    <div class="price-inputs">
                        <div class="price-field">
                            <label>Min Budget</label>
                            <input type="number" name="min_price" placeholder="0" value="<?= $min_price ?: '' ?>">
                        </div>
                        <div class="price-field">
                            <label>Max Budget</label>
                            <input type="number" name="max_price" placeholder="Any" value="<?= $max_price ?: '' ?>">
                        </div>
                    </div>
                    <?php if($category_id): ?>
                        <input type="hidden" name="category" value="<?= $category_id ?>">
                    <?php endif; ?>
                    <?php if($search): ?>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <?php endif; ?>
                    <?php if($style): ?>
                        <input type="hidden" name="style" value="<?= htmlspecialchars($style) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-block mt-2"><i class="fas fa-check"></i> Apply Budget</button>
                </form>
                <div class="small text-muted mt-2">
                    <i class="fas fa-info-circle"></i> Price range: TSh <?= number_format($global_min_price) ?> - TSh <?= number_format($global_max_price) ?>
                </div>
            </div>
            
            <!-- CARD 3: STYLE BUTTONS -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-palette"></i> Quick Filters</div>
                <div class="style-buttons-group">
                    <button type="button" class="style-btn <?= $style == 'budget' ? 'active' : '' ?>" data-style="budget">
                        <i class="fas fa-coins"></i> Budget (≤ 100k)
                    </button>
                    <button type="button" class="style-btn <?= $style == 'midrange' ? 'active' : '' ?>" data-style="midrange">
                        <i class="fas fa-chart-line"></i> Mid Range (100k - 500k)
                    </button>
                    <button type="button" class="style-btn <?= $style == 'premium' ? 'active' : '' ?>" data-style="premium">
                        <i class="fas fa-gem"></i> Premium (500k - 1M)
                    </button>
                    <button type="button" class="style-btn <?= $style == 'luxury' ? 'active' : '' ?>" data-style="luxury">
                        <i class="fas fa-crown"></i> Luxury (≥ 1M)
                    </button>
                    <button type="button" class="style-btn <?= $style == 'popular' ? 'active' : '' ?>" data-style="popular">
                        <i class="fas fa-fire"></i> Most Popular
                    </button>
                    <button type="button" class="style-btn <?= $style == 'bestvalue' ? 'active' : '' ?>" data-style="bestvalue">
                        <i class="fas fa-tag"></i> Best Value
                    </button>
                </div>
                <?php if(!empty($style)): ?>
                <div class="mt-2">
                    <a href="?<?= http_build_query(array_merge($_GET, ['style' => null])) ?>" class="small" style="color:#e74c3c; text-decoration:none;">
                        <i class="fas fa-times-circle"></i> Clear Filter
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- CARD 4: Clear All Filters -->
            <div class="filter-card">
                <a href="index.php" class="btn btn-outline btn-block"><i class="fas fa-eraser"></i> Clear All Filters</a>
            </div>
            
            <!-- CARD 5: Recently Viewed -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-history"></i> Recently Viewed</div>
                <a href="recently-viewed.php" class="btn btn-outline btn-block"><i class="fas fa-clock"></i> View History →</a>
            </div>
            
            <!-- CARD 6: Price Alerts -->
            <div class="filter-card">
                <div class="filter-title"><i class="fas fa-bell"></i> Price Alerts</div>
                <a href="price-alert.php" class="btn btn-outline btn-block"><i class="fas fa-bell"></i> Manage Alerts →</a>
            </div>
        </aside>

        <main>
            <div class="products-header">
                <div><i class="fas fa-box"></i> Showing <strong><?= count($products) ?></strong> of <strong><?= $total_products ?></strong> products</div>
                <form method="GET" id="sortForm">
                    <select name="sort" class="sort-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name A to Z</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name Z to A</option>
                    </select>
                    <?php if($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                    <?php if($category_id): ?><input type="hidden" name="category" value="<?= $category_id ?>"><?php endif; ?>
                    <?php if($min_price): ?><input type="hidden" name="min_price" value="<?= $min_price ?>"><?php endif; ?>
                    <?php if($max_price): ?><input type="hidden" name="max_price" value="<?= $max_price ?>"><?php endif; ?>
                    <?php if($style): ?><input type="hidden" name="style" value="<?= $style ?>"><?php endif; ?>
                </form>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open" style="font-size:64px; color:#cbd5e1;"></i>
                    <h3>No products found</h3>
                    <p class="text-muted">Try adjusting your search or filter criteria.</p>
                    <a href="index.php" class="btn btn-primary" style="margin-top:15px;"><i class="fas fa-eraser"></i> Clear All Filters</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product):
                        $days_old = (time() - strtotime($product['created_at'])) / (60 * 60 * 24);
                        $img_src = '../../assets/images/default-product.jpg';
                        if (!empty($product['image_url']) && file_exists('../../' . $product['image_url'])) {
                            $img_src = '../../' . $product['image_url'];
                        } elseif (!empty($product['image_url']) && file_exists($product['image_url'])) {
                            $img_src = $product['image_url'];
                        }
                        $rating = $ratings[$product['product_id']] ?? ['avg' => 0, 'count' => 0];
                        $in_compare = in_array($product['product_id'], $_SESSION['compare_products']);
                    ?>
                    <div class="product-card <?= $in_compare ? 'in-compare' : '' ?>" data-product-id="<?= $product['product_id'] ?>">
                        <div class="product-badges">
                            <?php if ($days_old < 7): ?>
                                <span class="badge badge-new"><i class="fas fa-fire"></i> New</span>
                            <?php endif; ?>
                            <?php if ($product['quantity_in_stock'] < 10 && $product['quantity_in_stock'] > 0): ?>
                                <span class="badge badge-low-stock"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                            <?php endif; ?>
                            <?php if ($product['price'] <= 100000): ?>
                                <span class="badge badge-budget"><i class="fas fa-coins"></i> Budget</span>
                            <?php endif; ?>
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
                                <span class="rating-stars-mini">
                                    <?php for ($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= round($rating['avg']) ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <span>(<?= $rating['count'] ?> reviews)</span>
                            </div>
                            <div class="product-price">TSh <?= number_format($product['price'], 0, '.', ',') ?></div>
                            <div class="product-actions">
                                <?php if ($is_logged_in && $product['quantity_in_stock'] > 0): ?>
                                    <button class="btn-cart add-to-cart-btn" data-id="<?= $product['product_id'] ?>"><i class="fas fa-cart-plus"></i> Cart</button>
                                <?php elseif (!$is_logged_in): ?>
                                    <a href="../login.php" class="btn-cart"><i class="fas fa-sign-in-alt"></i> Login to Buy</a>
                                <?php else: ?>
                                    <button class="btn-cart" disabled style="background:#94a3b8; cursor:not-allowed;"><i class="fas fa-ban"></i> Out of Stock</button>
                                <?php endif; ?>
                                <a href="details.php?id=<?= $product['product_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="pagination ">
                    <?php 
                    $query_params = array_filter([
                        'search' => $search,
                        'category' => $category_id,
                        'min_price' => $min_price,
                        'max_price' => $max_price,
                        'style' => $style,
                        'sort' => $sort
                    ]);
                    $query_string = http_build_query($query_params);
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&<?= $query_string ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                    <?php else: ?>
                        <span><i class="fas fa-chevron-left"></i> Previous</span>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <?= $i == $page ? '<span class="active">'.$i.'</span>' : '<a href="?page='.$i.'&'.$query_string.'">'.$i.'</a>' ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page+1 ?>&<?= $query_string ?>">Next <i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span>Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
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

function addToCart(productId) {
    let form = document.createElement('form');
    form.method = 'POST';
    form.action = '../cart/add.php';
    form.innerHTML = `<input type="hidden" name="product_id" value="${productId}"><input type="hidden" name="quantity" value="1">`;
    document.body.appendChild(form);
    form.submit();
}

function quickView(productId) {
    let modal = document.getElementById('quickViewModal');
    let content = document.getElementById('quickViewContent');
    if (!modal || !content) return;
    modal.classList.add('active');
    fetch('quick-view.php?id=' + encodeURIComponent(productId))
        .then(res => res.text())
        .then(html => { content.innerHTML = html; })
        .catch(err => { content.innerHTML = '<div style="padding:20px;text-align:center;"><i class="fas fa-exclamation-circle"></i> Error loading product</div>'; });
}

function addToCompare(productId) {
    fetch('compare.php?add=' + productId)
        .then(() => { showToast('Added to compare'); setTimeout(() => location.reload(), 800); });
}

function clearCompare() {
    if (confirm('Clear all products from comparison?')) {
        window.location.href = 'compare.php?clear=1';
    }
}

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

function bulkOrder(productId) { window.location.href = 'bulk-order.php?id=' + productId; }

// Style Buttons
document.querySelectorAll('.style-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        let style = this.dataset.style;
        let url = new URL(window.location.href);
        if (url.searchParams.get('style') === style) {
            url.searchParams.delete('style');
        } else {
            url.searchParams.set('style', style);
        }
        url.searchParams.set('page', 1);
        window.location.href = url.toString();
    });
});

// ============================================
// PROFESSIONAL IMAGE SEARCH
// ============================================
const cameraBtn = document.getElementById('cameraBtn');
const imageInput = document.getElementById('imageSearchInput');
const previewContainer = document.getElementById('previewContainer');
const previewImg = document.getElementById('previewImg');
const loadingSpinner = document.getElementById('loadingSpinner');
const similarResultsContainer = document.getElementById('similarResultsContainer');
const similarProductsList = document.getElementById('similarProductsList');
const imageUploadPreview = document.getElementById('imageUploadPreview');

cameraBtn.addEventListener('click', () => {
    imageInput.click();
});

function closeImagePreview() {
    imageUploadPreview.classList.remove('show');
    previewContainer.style.display = 'none';
    similarResultsContainer.style.display = 'none';
    imageInput.value = '';
}

imageInput.addEventListener('change', function(e) {
    let file = e.target.files[0];
    if (!file) return;
    
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showToast('Image too large (max 5MB)', true);
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        showToast('Please select an image file', true);
        return;
    }
    
    imageUploadPreview.classList.add('show');
    previewContainer.style.display = 'block';
    similarResultsContainer.style.display = 'none';
    
    let reader = new FileReader();
    reader.onload = function(e) {
        previewImg.src = e.target.result;
    };
    reader.readAsDataURL(file);
    
    // Show loading spinner
    loadingSpinner.style.display = 'block';
    let formData = new FormData();
    formData.append('product_image', file);
    
    // Search for similar products
    fetch('search_by_serapi.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        loadingSpinner.style.display = 'none';
        if (data.success && data.products && data.products.length > 0) {
            similarProductsList.innerHTML = '';
            data.products.forEach(p => {
                let imgUrl = '../../assets/images/default-product.jpg';
                if (p.image_url) {
                    if (p.image_url.startsWith('http://') || p.image_url.startsWith('https://')) {
                        imgUrl = p.image_url;
                    } else if (p.image_url.startsWith('../../')) {
                        imgUrl = p.image_url;
                    } else if (p.image_url.startsWith('../')) {
                        imgUrl = p.image_url;
                    } else {
                        imgUrl = '../../' + p.image_url;
                    }
                }
                similarProductsList.innerHTML += `
                    <div class="similar-product-card" onclick="window.location.href='details.php?id=${p.product_id}'">
                        <img src="${imgUrl}" onerror="this.src='../../assets/images/default-product.jpg'">
                        <div class="similar-product-name">${escapeHtml(p.name.substring(0, 35))}</div>
                        <div class="similar-product-price">TSh ${parseInt(p.price).toLocaleString()}</div>
                        <button class="similar-product-btn" onclick="event.stopPropagation(); window.location.href='details.php?id=${p.product_id}'">View Product</button>
                    </div>
                `;
            });
            similarResultsContainer.style.display = 'block';
            showToast(`Found ${data.products.length} similar products`, false);
        } else {
            showToast(data.message || 'No similar products found. Try a different photo.', true);
        }
    })
    .catch(err => {
        loadingSpinner.style.display = 'none';
        console.error('Error:', err);
        showToast('Error processing image. Please try again.', true);
    });
});

function escapeHtml(text) {
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.quick-view-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) quickView(id);
        });
    });
    document.querySelectorAll('.compare-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) addToCompare(id);
        });
    });
    document.querySelectorAll('.price-alert-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            let price = btn.getAttribute('data-price');
            if (id && price) setPriceAlert(id, parseFloat(price));
        });
    });
    document.querySelectorAll('.bulk-order-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) bulkOrder(id);
        });
    });
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            let id = btn.getAttribute('data-id');
            if (id) addToCart(id);
        });
    });
});

document.getElementById('quickViewModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.getElementById('quickViewModal')?.classList.remove('active');
});

function toggleSubcategories(el) {
    let parentLi = el.closest('.category-item');
    let subUl = parentLi?.querySelector('.subcategory-list');
    if (subUl) subUl.style.display = subUl.style.display === 'none' ? 'block' : 'none';
}

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