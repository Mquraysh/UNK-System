<?php
// admin/reports/inventory.php - Inventory Report with Clean Category Breakdown
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$page_title = 'Inventory Report';

// ============================================================
// GET FILTERS
// ============================================================
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// ============================================================
// GET ALL CATEGORIES FOR HIERARCHICAL TREE
// ============================================================
$all_categories = [];
$cat_result = mysqli_query($conn, "SELECT category_id, name, parent_id FROM categories ORDER BY parent_id, name");
while ($row = mysqli_fetch_assoc($cat_result)) {
    $all_categories[] = $row;
}

// Build category tree
function buildCategoryTree($categories, $parent_id = 0, $level = 0) {
    $tree = [];
    $rendered_ids = [];
    foreach ($categories as $cat) {
        if (in_array($cat['category_id'], $rendered_ids)) {
            continue;
        }
        if ($cat['parent_id'] == $parent_id) {
            $rendered_ids[] = $cat['category_id'];
            $cat['level'] = $level;
            $cat['children'] = buildCategoryTree($categories, $cat['category_id'], $level + 1);
            $tree[] = $cat;
        }
    }
    return $tree;
}

$category_tree = buildCategoryTree($all_categories);

// Generate category options for filter
function generateCategoryOptions($tree, $selected_id = 0, $prefix = '') {
    $html = '';
    $rendered_ids = [];
    foreach ($tree as $cat) {
        if (in_array($cat['category_id'], $rendered_ids)) {
            continue;
        }
        $rendered_ids[] = $cat['category_id'];
        
        $selected = ($cat['category_id'] == $selected_id) ? 'selected' : '';
        $html .= '<option value="' . $cat['category_id'] . '" ' . $selected . '>';
        $html .= $prefix . htmlspecialchars($cat['name']);
        $html .= '</option>';
        if (!empty($cat['children'])) {
            $html .= generateCategoryOptions($cat['children'], $selected_id, $prefix . '&nbsp;&nbsp;&nbsp;');
        }
    }
    return $html;
}

// ============================================================
// GET CATEGORY BREAKDOWN - TOP 4 CATEGORIES
// ============================================================
$category_breakdown_sql = "SELECT 
                                c.category_id,
                                c.name,
                                COUNT(DISTINCT p.product_id) as product_count,
                                SUM(p.quantity_in_stock) as total_stock,
                                SUM(p.price * p.quantity_in_stock) as stock_value
                            FROM categories c
                            LEFT JOIN products p ON c.category_id = p.category_id 
                                AND p.is_available = 1 
                                AND p.deleted_at IS NULL
                            WHERE c.parent_id = 0 OR c.parent_id IS NULL
                            GROUP BY c.category_id
                            ORDER BY product_count DESC
                            LIMIT 4";
$category_breakdown_result = mysqli_query($conn, $category_breakdown_sql);
$category_breakdown = [];
while ($row = mysqli_fetch_assoc($category_breakdown_result)) {
    $category_breakdown[] = $row;
}

// ============================================================
// BUILD PRODUCT QUERY
// ============================================================
$where = "WHERE p.is_available = 1 AND p.deleted_at IS NULL";

if ($category_filter > 0) {
    $where .= " AND p.category_id = $category_filter";
}

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (p.name LIKE '%$search_esc%' 
                     OR p.description LIKE '%$search_esc%')";
}

if ($stock_filter === 'low') {
    $where .= " AND p.quantity_in_stock > 0 AND p.quantity_in_stock < 10";
} elseif ($stock_filter === 'out') {
    $where .= " AND p.quantity_in_stock <= 0";
} elseif ($stock_filter === 'high') {
    $where .= " AND p.quantity_in_stock >= 50";
}

$order_by = "p.name ASC";
if ($sort_by === 'stock_asc') {
    $order_by = "p.quantity_in_stock ASC";
} elseif ($sort_by === 'stock_desc') {
    $order_by = "p.quantity_in_stock DESC";
} elseif ($sort_by === 'price_asc') {
    $order_by = "p.price ASC";
} elseif ($sort_by === 'price_desc') {
    $order_by = "p.price DESC";
} elseif ($sort_by === 'views') {
    $order_by = "p.views DESC";
}

// Get products with business and category info
$sql = "SELECT 
            p.product_id,
            p.name,
            p.description,
            p.price,
            p.quantity_in_stock,
            p.image_url,
            p.views,
            p.created_at,
            p.category_id,
            c.name as category_name,
            b.business_name,
            b.business_id,
            (SELECT COUNT(*) FROM order_items WHERE product_id = p.product_id) as total_sold,
            (SELECT SUM(quantity) FROM order_items WHERE product_id = p.product_id) as total_quantity_sold,
            (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id AND status = 'approved') as avg_rating
        FROM products p
        JOIN categories c ON p.category_id = c.category_id
        JOIN businesses b ON p.business_id = b.business_id
        $where
        ORDER BY $order_by";

$result = mysqli_query($conn, $sql);
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Determine stock status
    if ($row['quantity_in_stock'] <= 0) {
        $row['stock_status'] = 'Out of Stock';
        $row['stock_color'] = '#ef4444';
        $row['stock_badge'] = 'out';
    } elseif ($row['quantity_in_stock'] < 10) {
        $row['stock_status'] = 'Low Stock';
        $row['stock_color'] = '#f59e0b';
        $row['stock_badge'] = 'low';
    } elseif ($row['quantity_in_stock'] < 50) {
        $row['stock_status'] = 'In Stock';
        $row['stock_color'] = '#10b981';
        $row['stock_badge'] = 'in';
    } else {
        $row['stock_status'] = 'High Stock';
        $row['stock_color'] = '#3b82f6';
        $row['stock_badge'] = 'high';
    }
    
    // Format rating
    $row['avg_rating'] = $row['avg_rating'] ? round($row['avg_rating'], 1) : 0;
    
    $products[] = $row;
}

// ============================================================
// CALCULATE TOTALS
// ============================================================
$total_products = count($products);
$total_value = 0;
$total_quantity = 0;
$low_stock_count = 0;
$out_stock_count = 0;
$high_stock_count = 0;
$total_views = 0;

foreach ($products as $p) {
    $total_value += $p['price'] * $p['quantity_in_stock'];
    $total_quantity += $p['quantity_in_stock'];
    $total_views += $p['views'];
    
    if ($p['stock_badge'] === 'low') $low_stock_count++;
    elseif ($p['stock_badge'] === 'out') $out_stock_count++;
    elseif ($p['stock_badge'] === 'high') $high_stock_count++;
}

$avg_price = $total_products > 0 ? $total_value / $total_products : 0;

// ============================================================
// GET TOP PRODUCTS BY SALES
// ============================================================
$top_selling_sql = "SELECT 
                        p.product_id,
                        p.name,
                        p.price,
                        SUM(oi.quantity) as total_sold,
                        SUM(oi.quantity * p.price) as revenue
                    FROM products p
                    JOIN order_items oi ON p.product_id = oi.product_id
                    JOIN orders o ON oi.order_id = o.order_id
                    WHERE o.status = 'delivered'
                    GROUP BY p.product_id
                    ORDER BY total_sold DESC
                    LIMIT 5";
$top_selling_result = mysqli_query($conn, $top_selling_sql);
$top_selling = [];
while ($row = mysqli_fetch_assoc($top_selling_result)) {
    $top_selling[] = $row;
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Inventory Report | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1f2937; }
        
        .report-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .report-content { margin-left: 0; padding: 20px; }
        }
        @media (max-width: 768px) {
            .report-content { padding: 15px; }
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-back {
            background: #64748b;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        .btn-export {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-export:hover { background: #059669; transform: translateY(-2px); }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
            border-color: #e67e22;
        }
        .summary-card .label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-card .value {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .summary-card .value.orange { color: #e67e22; }
        .summary-card .value.green { color: #10b981; }
        .summary-card .value.blue { color: #3b82f6; }
        .summary-card .value.red { color: #ef4444; }
        .summary-card .value.purple { color: #8b5cf6; }
        .summary-card .sub-text {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .stock-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stock-card {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s;
        }
        .stock-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .stock-card .count {
            font-size: 28px;
            font-weight: 800;
        }
        .stock-card .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        /* Category Cards */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .category-card {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -12px rgba(0,0,0,0.1);
        }
        .category-card .cat-name {
            font-weight: 700;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .category-card .cat-stats {
            font-size: 12px;
            color: #64748b;
        }
        .category-card .cat-value {
            font-size: 14px;
            font-weight: 700;
            color: #e67e22;
        }
        
        .top-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .top-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .top-card-header {
            padding: 14px 20px;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .top-card-header i { color: #e67e22; }
        .top-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .top-item:last-child { border-bottom: none; }
        .top-rank {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            color: #64748b;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .top-rank.rank-1 { background: #fef3c7; color: #e67e22; }
        .top-rank.rank-2 { background: #e2e8f0; color: #64748b; }
        .top-rank.rank-3 { background: #fef3c7; color: #d97706; }
        .top-info { flex: 1; }
        .top-info .name { font-weight: 600; font-size: 13px; }
        .top-info .detail { font-size: 11px; color: #64748b; }
        .top-amount { font-weight: 700; font-size: 14px; color: #e67e22; }
        
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            border: 1px solid #e2e8f0;
        }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 6px;
        }
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.2s;
        }
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .btn-filter {
            background: #e67e22;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-filter:hover { background: #d35400; }
        .btn-reset {
            background: #94a3b8;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-reset:hover { background: #64748b; }
        
        .table-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .table-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-header h3 {
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            background: #fafbfc;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #f8fafc; }
        
        .stock-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            color: white;
        }
        .stock-badge.out { background: #ef4444; }
        .stock-badge.low { background: #f59e0b; }
        .stock-badge.in { background: #10b981; }
        .stock-badge.high { background: #3b82f6; }
        
        .empty-row td { text-align: center; padding: 40px; color: #94a3b8; }
        .empty-row i { font-size: 2rem; display: block; margin-bottom: 10px; opacity: 0.5; }
        
        .text-muted { color: #94a3b8; }
        
        @media (max-width: 1200px) {
            .summary-grid { grid-template-columns: repeat(3, 1fr); }
            .stock-summary { grid-template-columns: repeat(2, 1fr); }
            .category-grid { grid-template-columns: repeat(2, 1fr); }
            .top-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .stock-summary { grid-template-columns: repeat(2, 1fr); }
            .category-grid { grid-template-columns: 1fr; }
            .filters-bar { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; min-width: unset; }
            .filter-buttons { display: flex; gap: 10px; }
            .filter-buttons .btn-filter, .filter-buttons .btn-reset { flex: 1; text-align: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .btn-back, .btn-export { flex: 1; justify-content: center; }
        }
        @media (max-width: 480px) {
            .summary-grid { grid-template-columns: 1fr; }
            .stock-summary { grid-template-columns: 1fr; }
            .data-table td { font-size: 11px; padding: 8px 6px; }
            .data-table th { font-size: 9px; padding: 8px 6px; }
        }
    </style>
</head>
<body>
<div class="report-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-warehouse"></i> Inventory Report</h1>
            <p>Stock levels, product performance, and inventory value</p>
        </div>
        <div class="header-actions">
            <a href="export.php?type=inventory" class="btn-export">
                <i class="fas fa-file-download"></i> Export CSV
            </a>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="label"><i class="fas fa-box"></i> Total Products</div>
            <div class="value"><?php echo number_format($total_products); ?></div>
            <div class="sub-text">Active products</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-money-bill-wave"></i> Inventory Value</div>
            <div class="value orange">TSh <?php echo number_format($total_value); ?></div>
            <div class="sub-text">Total stock value</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-cubes"></i> Total Quantity</div>
            <div class="value blue"><?php echo number_format($total_quantity); ?></div>
            <div class="sub-text">Items in stock</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-eye"></i> Total Views</div>
            <div class="value purple"><?php echo number_format($total_views); ?></div>
            <div class="sub-text">Product views</div>
        </div>
        <div class="summary-card">
            <div class="label"><i class="fas fa-calculator"></i> Avg Price</div>
            <div class="value green">TSh <?php echo number_format($avg_price); ?></div>
            <div class="sub-text">Average product price</div>
        </div>
    </div>

    <!-- Stock Status Summary -->
    <div class="stock-summary">
        <div class="stock-card">
            <div class="count" style="color:#10b981;"><?php echo number_format($total_products - $low_stock_count - $out_stock_count); ?></div>
            <div class="label"><i class="fas fa-check-circle" style="color:#10b981;"></i> In Stock</div>
        </div>
        <div class="stock-card">
            <div class="count" style="color:#f59e0b;"><?php echo number_format($low_stock_count); ?></div>
            <div class="label"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i> Low Stock</div>
        </div>
        <div class="stock-card">
            <div class="count" style="color:#ef4444;"><?php echo number_format($out_stock_count); ?></div>
            <div class="label"><i class="fas fa-times-circle" style="color:#ef4444;"></i> Out of Stock</div>
        </div>
        <div class="stock-card">
            <div class="count" style="color:#3b82f6;"><?php echo number_format($high_stock_count); ?></div>
            <div class="label"><i class="fas fa-arrow-up" style="color:#3b82f6;"></i> High Stock</div>
        </div>
    </div>

    <!-- Top Products -->
    <?php if (!empty($top_selling)): ?>
    <div class="top-grid">
        <div class="top-card">
            <div class="top-card-header"><i class="fas fa-fire"></i> Top Selling Products</div>
            <?php foreach ($top_selling as $index => $p): ?>
            <div class="top-item">
                <div class="top-rank rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></div>
                <div class="top-info">
                    <div class="name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="detail"><?php echo $p['total_sold']; ?> units sold</div>
                </div>
                <div class="top-amount">TSh <?php echo number_format($p['revenue']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; width:100%; align-items:flex-end;">
            <div class="filter-group">
                <label>Category</label>
                <select name="category" class="filter-input">
                    <option value="0">All Categories</option>
                    <?php echo generateCategoryOptions($category_tree, $category_filter); ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Stock Status</label>
                <select name="stock" class="filter-input">
                    <option value="all" <?php echo $stock_filter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                    <option value="high" <?php echo $stock_filter === 'high' ? 'selected' : ''; ?>>High Stock</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select name="sort" class="filter-input">
                    <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name</option>
                    <option value="stock_asc" <?php echo $sort_by === 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                    <option value="stock_desc" <?php echo $sort_by === 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                    <option value="price_asc" <?php echo $sort_by === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                    <option value="price_desc" <?php echo $sort_by === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                    <option value="views" <?php echo $sort_by === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                </select>
            </div>
            <div class="filter-group" style="flex:2;">
                <label>Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Product name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-buttons" style="display:flex; gap:10px;">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="inventory.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Inventory Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Products</h3>
            <span class="text-muted"><?php echo $total_products; ?> record(s)</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Business</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr class="empty-row">
                            <td colspan="7">
                                <i class="fas fa-box"></i>
                                No products found matching your filters
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['business_name']); ?></td>
                            <td><strong>TSh <?php echo number_format($p['price']); ?></strong></td>
                            <td><?php echo number_format($p['quantity_in_stock']); ?></td>
                            <td>
                                <span class="stock-badge <?php echo $p['stock_badge']; ?>">
                                    <?php echo $p['stock_status']; ?>
                                </span>
                            </td>
                            <td><?php echo number_format($p['views']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>