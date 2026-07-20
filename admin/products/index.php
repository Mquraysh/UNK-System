<?php
// admin/products/index.php - IMPROVED STATS CARDS + CATEGORY TREE WITH SUBCATEGORIE
require_once '../../config/database.php';
require_once '../notifications/functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Helper: build category tree dropdown options (same as before)
function buildCategoryTree($conn, $parent_id = NULL, $level = 0, $selected_id = 0) {
    $html = '';
    $sql = "SELECT category_id, name FROM categories WHERE parent_id " . ($parent_id === NULL ? "IS NULL" : "= ?") . " ORDER BY sort_order, name";
    $stmt = mysqli_prepare($conn, $sql);
    if ($parent_id !== NULL) {
        mysqli_stmt_bind_param($stmt, 'i', $parent_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $prefix = ($level > 0) ? '└─ ' : '';
        $selected = ($selected_id == $row['category_id']) ? 'selected' : '';
        $html .= '<option value="' . $row['category_id'] . '" ' . $selected . '>' . $indent . $prefix . htmlspecialchars($row['name']) . '</option>';
        $html .= buildCategoryTree($conn, $row['category_id'], $level + 1, $selected_id);
    }
    mysqli_stmt_close($stmt);
    return $html;
}

// Helper: get all subcategory IDs (including itself) for a given parent category
function getAllSubcategoryIds($conn, $parent_id) {
    $ids = [$parent_id];
    $sql = "SELECT category_id FROM categories WHERE parent_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $parent_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $child_ids = getAllSubcategoryIds($conn, $row['category_id']);
        $ids = array_merge($ids, $child_ids);
    }
    mysqli_stmt_close($stmt);
    return $ids;
}

// Build main query with prepared statements, including subcategories if needed
$sql = "SELECT p.*, b.business_name, c.name as category_name 
        FROM products p
        JOIN businesses b ON p.business_id = b.business_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR b.business_name LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
    $types = "sss";
}
if ($status_filter === 'active') {
    $sql .= " AND p.is_available = 1";
} elseif ($status_filter === 'inactive') {
    $sql .= " AND p.is_available = 0";
}
if ($stock_filter === 'low') {
    $sql .= " AND p.quantity_in_stock < 10 AND p.quantity_in_stock > 0";
} elseif ($stock_filter === 'out') {
    $sql .= " AND p.quantity_in_stock = 0";
} elseif ($stock_filter === 'normal') {
    $sql .= " AND p.quantity_in_stock >= 10";
}
if ($category_filter > 0) {
    // Get all subcategory IDs including the selected one
    $sub_ids = getAllSubcategoryIds($conn, $category_filter);
    if (!empty($sub_ids)) {
        $placeholders = implode(',', array_fill(0, count($sub_ids), '?'));
        $sql .= " AND p.category_id IN ($placeholders)";
        $params = array_merge($params, $sub_ids);
        $types .= str_repeat('i', count($sub_ids));
    } else {
        // fallback – should not happen
        $sql .= " AND p.category_id = ?";
        $params[] = $category_filter;
        $types .= "i";
    }
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);

// Statistics
$total = count($products);
$active = 0;
$inactive = 0;
$low = 0;
$out = 0;
foreach ($products as $p) {
    if ($p['is_available']) $active++;
    else $inactive++;
    if ($p['quantity_in_stock'] < 10 && $p['quantity_in_stock'] > 0) $low++;
    if ($p['quantity_in_stock'] <= 0) $out++;
}

$category_options = buildCategoryTree($conn, NULL, 0, $category_filter);

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Manage Products | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            background: #f1f5f9;
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
        /* Improved Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem 1rem;
            text-align: center;
            border: 1px solid #eef2f8;
            transition: all 0.25s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e67e22, #f39c12);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.1);
            border-color: rgba(230,126,34,0.3);
        }
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #e67e22;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, #1e293b, #2d3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .stat-card p {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
            /* text-transform: uppercase; */
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .stat-card p i {
            font-size: 0.8rem;
            color: #e67e22;
        }
        /* Filters bar (unchanged) */
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
            border: 1px solid #eef2f8;
        }
        .filter-group { flex:1; min-width:150px; }
        .filter-group label { font-size:0.7rem; font-weight:600; text-transform:uppercase; color:#64748b; margin-bottom:0.25rem; display:block; }
        .filter-input { width:100%; padding:0.5rem 0.75rem; border:1px solid #e2e8f0; border-radius:0.5rem; font-size:0.85rem; background:white; }
        .filter-input:focus { outline:none; border-color:#e67e22; }
        .btn-filter, .btn-reset { padding:0.5rem 1rem; border-radius:0.5rem; font-weight:600; cursor:pointer; border:none; }
        .btn-filter { background:#e67e22; color:white; }
        .btn-filter:hover { background:#d35400; transform:translateY(-1px); }
        .btn-reset { background:#94a3b8; color:white; text-decoration:none; display:inline-block; }
        .btn-reset:hover { background:#64748b; transform:translateY(-1px); }
        .alert { padding:0.75rem 1rem; border-radius:0.75rem; margin-bottom:1.5rem; border-left:4px solid; }
        .alert-success { background:#e6f7ec; color:#0a5c3e; border-left-color:#10b981; }
        .alert-danger { background:#fee2e2; color:#991b1b; border-left-color:#ef4444; }
        /* Table card */
        .table-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
        }
        .table-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .table-header h3 {
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-header h3 i { color: #e67e22; }
        .table-container { overflow-x: auto; }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th { text-align:left; padding:0.8rem 1rem; background:#f8fafc; font-size:0.7rem; font-weight:700; text-transform:uppercase; color:#475569; border-bottom:1px solid #e2e8f0; }
        .data-table td { padding:0.8rem 1rem; border-bottom:1px solid #f1f5f9; font-size:0.85rem; vertical-align:middle; }
        .data-table tr:hover td { background:#fffaf5; cursor:pointer; }
        .badge { display:inline-block; padding:0.2rem 0.7rem; border-radius:2rem; font-size:0.7rem; font-weight:600; }
        .badge-active { background:#d1fae5; color:#059669; }
        .badge-inactive { background:#fef3c7; color:#d97706; }
        .stock-badge { display:inline-block; padding:0.2rem 0.5rem; border-radius:2rem; font-size:0.6rem; font-weight:600; background:#e2e8f0; }
        .product-img { width:50px; height:50px; object-fit:cover; border-radius:0.5rem; background:#f1f5f9; }
        .btn-sm { padding:0.3rem 0.7rem; border-radius:0.5rem; text-decoration:none; font-size:0.7rem; display:inline-flex; align-items:center; gap:0.3rem; transition:0.2s; cursor:pointer; }
        .btn-sm:hover { transform:translateY(-1px); opacity:0.9; }
        .btn-view { background:#3498db; color:white; }
        .btn-toggle { background:#f59e0b; color:white; }
        .btn-delete { background:#ef4444; color:white; }
        .empty-state { text-align:center; padding:2rem; color:#94a3b8; }
        @media (max-width:768px) { .stats-grid { grid-template-columns:repeat(2,1fr); gap:1rem; } }
        @media (max-width:640px) { .stats-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-box"></i> Manage Products</h1>
    </div>
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>
    
    <!-- Statistics Cards (Improved) -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total ?></h3>
            <p><i class="fas fa-box"></i> Total Products</p>
        </div>
        <div class="stat-card">
            <h3><?= $active ?></h3>
            <p><i class="fas fa-check-circle"></i> Active</p>
        </div>
        <div class="stat-card">
            <h3><?= $inactive ?></h3>
            <p><i class="fas fa-eye-slash"></i> Inactive</p>
        </div>
        <div class="stat-card">
            <h3><?= $low ?></h3>
            <p><i class="fas fa-exclamation-triangle"></i> Low Stock</p>
        </div>
        <div class="stat-card">
            <h3><?= $out ?></h3>
            <p><i class="fas fa-times-circle"></i> Out of Stock</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:1rem; width:100%; align-items:flex-end;">
            <div class="filter-group"><label>Search</label><input type="text" name="search" class="filter-input" placeholder="Product, business..." value="<?= htmlspecialchars($search) ?>"></div>
            <div class="filter-group"><label>Category (Tree)</label><select name="category" class="filter-input"><option value="0">All Categories</option><?= $category_options ?></select></div>
            <div class="filter-group"><label>Status</label><select name="status" class="filter-input"><option value="">All</option><option value="active" <?= $status_filter=='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option></select></div>
            <div class="filter-group"><label>Stock</label><select name="stock" class="filter-input"><option value="">All</option><option value="normal" <?= $stock_filter=='normal'?'selected':'' ?>>Normal (≥10)</option><option value="low" <?= $stock_filter=='low'?'selected':'' ?>>Low (<10)</option><option value="out" <?= $stock_filter=='out'?'selected':'' ?>>Out</option></select></div>
            <div><button type="submit" class="btn-filter">Apply</button> <a href="index.php" class="btn-reset">Reset</a></div>
        </form>
    </div>

    <!-- Products Table -->
    <div class="table-card">
        <div class="table-header"><h3><i class="fas fa-list"></i> Product List</h3><span><?= count($products) ?> records</span></div>
        <div class="table-container">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Image</th><th>Product Name</th><th>Business</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($products)): ?><td><td colspan="8" class="empty-state">No products found</a></tr>
                    <?php else: foreach ($products as $p): 
                        $img = !empty($p['image_url']) && file_exists("../../".$p['image_url']) ? "../../".$p['image_url'] : "../../assets/images/default-product.jpg";
                        $stock_class = $p['quantity_in_stock']<=0 ? 'Out' : ($p['quantity_in_stock']<10 ? 'Low' : '');
                    ?>
                    <tr onclick="location.href='view.php?id=<?= $p['product_id'] ?>'">
                        <td><?= $p['product_id'] ?></td>
                        <td><img src="<?= $img ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'"></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></small></td>
                        <td><?= htmlspecialchars($p['business_name']) ?></td>
                        <td>TSh <?= number_format($p['price']) ?> / <?= $p['unit'] ?></td>
                        <td><?= $p['quantity_in_stock'] ?> <?= $p['unit'] ?>s <?php if($stock_class):?><span class="stock-badge"><?= $stock_class ?></span><?php endif; ?></td>
                        <td><span class="badge badge-<?= $p['is_available']?'active':'inactive' ?>"><?= $p['is_available']?'Active':'Inactive' ?></span></td>
                        <td class="action-btns" onclick="event.stopPropagation();">
                            <a href="view.php?id=<?= $p['product_id'] ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                            <a href="edit.php?id=<?= $p['product_id'] ?>" class="btn-sm btn-toggle"><i class="fas fa-edit"></i> Edit</a>
                            <a href="toggle-status.php?id=<?= $p['product_id'] ?>" class="btn-sm btn-toggle" onclick="return confirm('Toggle availability?')"><i class="fas fa-<?= $p['is_available']?'pause':'play' ?>"></i> <?= $p['is_available']?'Deactivate':'Activate' ?></a>
                            <a href="delete.php?id=<?= $p['product_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete permanently?')"><i class="fas fa-trash-alt"></i> Delete</a>
                        </a>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
             </a>
        </div>
    </div>
</div>
</body>
</html>