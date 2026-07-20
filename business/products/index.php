<?php
// business/products/index.php - WITH HIERARCHICAL CATEGORY TREE
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

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $product_id = (int)$_GET['toggle_status'];
    $product_sql = "SELECT is_available FROM products WHERE product_id = '$product_id' AND business_id = '$business_id'";
    $product_result = mysqli_query($conn, $product_sql);
    $product = mysqli_fetch_assoc($product_result);
    if ($product) {
        $new_status = $product['is_available'] ? 0 : 1;
        $update_sql = "UPDATE products SET is_available = '$new_status' WHERE product_id = '$product_id' AND business_id = '$business_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = "Product " . ($new_status ? "activated" : "deactivated") . " successfully";
            $_SESSION['flash_type'] = "success";
        }
    }
    header("Location: index.php");
    exit();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['product_ids'])) {
    $product_ids = $_POST['product_ids'];
    $bulk_action = $_POST['bulk_action'];
    
    if ($bulk_action == 'delete') {
        $cannot_delete = [];
        $can_delete = [];
        foreach ($product_ids as $pid) {
            $pid = (int)$pid;
            $check_sql = "SELECT COUNT(*) as order_count FROM order_items WHERE product_id = '$pid'";
            $check_result = mysqli_query($conn, $check_sql);
            $check_data = mysqli_fetch_assoc($check_result);
            if ($check_data['order_count'] > 0) {
                $cannot_delete[] = $pid;
            } else {
                $can_delete[] = $pid;
            }
        }
        if (!empty($can_delete)) {
            $ids_string = implode(',', $can_delete);
            $delete_sql = "DELETE FROM products WHERE product_id IN ($ids_string) AND business_id = '$business_id'";
            if (mysqli_query($conn, $delete_sql)) {
                $_SESSION['flash_message'] = count($can_delete) . " product(s) deleted successfully.";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_message'] = "Error deleting products: " . mysqli_error($conn);
                $_SESSION['flash_type'] = "danger";
            }
        }
        if (!empty($cannot_delete)) {
            $msg = count($cannot_delete) . " product(s) could not be deleted because they have existing orders.";
            if (isset($_SESSION['flash_message'])) {
                $_SESSION['flash_message'] .= " " . $msg;
            } else {
                $_SESSION['flash_message'] = $msg;
                $_SESSION['flash_type'] = "warning";
            }
        }
    } elseif ($bulk_action == 'activate') {
        $ids_string = implode(',', array_map('intval', $product_ids));
        $update_sql = "UPDATE products SET is_available = 1 WHERE product_id IN ($ids_string) AND business_id = '$business_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = count($product_ids) . " products activated";
            $_SESSION['flash_type'] = "success";
        }
    } elseif ($bulk_action == 'deactivate') {
        $ids_string = implode(',', array_map('intval', $product_ids));
        $update_sql = "UPDATE products SET is_available = 0 WHERE product_id IN ($ids_string) AND business_id = '$business_id'";
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = count($product_ids) . " products deactivated";
            $_SESSION['flash_type'] = "success";
        }
    }
    header("Location: index.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.business_id = '$business_id'";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (p.name LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%')";
}
if ($category_filter > 0) {
    $sql .= " AND p.category_id = '$category_filter'";
}
if ($stock_filter == 'low') {
    $sql .= " AND p.quantity_in_stock < 10 AND p.quantity_in_stock > 0";
} elseif ($stock_filter == 'out') {
    $sql .= " AND p.quantity_in_stock = 0";
} elseif ($stock_filter == 'normal') {
    $sql .= " AND p.quantity_in_stock >= 10";
}
if ($status_filter == 'active') {
    $sql .= " AND p.is_available = 1";
} elseif ($status_filter == 'inactive') {
    $sql .= " AND p.is_available = 0";
}
$sql .= " ORDER BY p.created_at DESC";
$products_result = mysqli_query($conn, $sql);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}


// HIERARCHICAL CATEGORY TREE FOR DROPDOWN
// Get all categories ordered by parent and name
$categories = [];
$cat_sql = "SELECT * FROM categories ORDER BY parent_id, sort_order, name";
$cat_result = mysqli_query($conn, $cat_sql);
while ($row = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $row;
}

// Recursive function to build hierarchical options
function buildCategoryOptions($categories, $parent_id = NULL, $level = 0, $selected_id = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parent_id) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $prefix = ($level > 0) ? '└─ ' : '';
            $selected = ($selected_id == $cat['category_id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['category_id'] . '" ' . $selected . '>' . $indent . $prefix . htmlspecialchars($cat['name']) . '</option>';
            $html .= buildCategoryOptions($categories, $cat['category_id'], $level + 1, $selected_id);
        }
    }
    return $html;
}

$category_options = buildCategoryOptions($categories, NULL, 0, $category_filter);

// Statistics
$total_products = count($products);
$active_products = 0;
$inactive_products = 0;
$low_stock_count = 0;
$out_stock_count = 0;
$total_stock_value = 0;
foreach ($products as $p) {
    if ($p['is_available']) $active_products++;
    else $inactive_products++;
    if ($p['quantity_in_stock'] < 10 && $p['quantity_in_stock'] > 0) $low_stock_count++;
    if ($p['quantity_in_stock'] <= 0) $out_stock_count++;
    $total_stock_value += $p['price'] * $p['quantity_in_stock'];
}

// Flash message
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (Same CSS as before, unchanged) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content { margin-left: 280px; padding: 25px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #7f8c8d; font-size: 14px; margin-top: 5px; }
        .btn-add { background: linear-gradient(135deg, #27ae60, #219a52); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39,174,96,0.3); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.25rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-card h3 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .stat-card.total h3 { color: #2c3e50; }
        .stat-card.active h3 { color: #27ae60; }
        .stat-card.inactive h3 { color: #e74c3c; }
        .stat-card.low h3 { color: #f39c12; }
        .stat-card.value h3 { color: #e67e22; }
        .stat-card p { font-size: 12px; color: #7f8c8d; }
        .filters-bar { background: white; border-radius: 16px; padding: 20px 25px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .filters-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 160px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 6px; text-transform: uppercase; }
        .filter-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; }
        .filter-input:focus { outline: none; border-color: #e67e22; }
        .btn-filter { background: #e67e22; color: white; padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; font-weight: 500; }
        .btn-filter:hover { background: #d35400; }
        .btn-reset { background: #95a5a6; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .bulk-bar { background: white; border-radius: 16px; padding: 15px 20px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .bulk-actions { display: flex; gap: 10px; align-items: center; }
        .bulk-select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
        .btn-bulk { background: #3498db; color: white; padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-bulk:hover { background: #2980b9; }
        .selected-count { font-size: 13px; color: #7f8c8d; }
        .table-card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .table-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .table-header h3 { font-size: 16px; font-weight: 600; color: #2c3e50; }
        .table-header h3 i { color: #e67e22; margin-right: 8px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #64748b; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover td { background: #fff5eb; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 10px; background: #f0f2f5; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .badge-low { background: #fef3c7; color: #d97706; }
        .badge-out { background: #fee2e2; color: #dc2626; }
        .btn-sm { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; margin: 2px; }
        .btn-edit { background: #e67e22; color: white; }
        .btn-edit:hover { background: #d35400; }
        .btn-toggle { background: #3498db; color: white; }
        .btn-toggle:hover { background: #2980b9; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-delete:hover { background: #c0392b; }
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b; }
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state i { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .filter-group { min-width: 100%; } .bulk-bar { flex-direction: column; } .bulk-actions { width: 100%; justify-content: space-between; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-box"></i> My Products</h1>
            <p>Manage your products inventory</p>
        </div>
        <a href="add.php" class="btn-add">
            <i class="fas fa-plus-circle"></i> Add New Product
        </a>
    </div>
    
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : ($flash_type == 'danger' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card total"><h3><?php echo $total_products; ?></h3><p><i class="fas fa-box"></i> Total Products</p></div>
        <div class="stat-card active"><h3><?php echo $active_products; ?></h3><p><i class="fas fa-check-circle"></i> Active</p></div>
        <div class="stat-card inactive"><h3><?php echo $inactive_products; ?></h3><p><i class="fas fa-eye-slash"></i> Inactive</p></div>
        <div class="stat-card low"><h3><?php echo $low_stock_count; ?></h3><p><i class="fas fa-exclamation-triangle"></i> Low Stock</p></div>
        <div class="stat-card value"><h3>TSh <?php echo number_format($total_stock_value); ?></h3><p><i class="fas fa-chart-line"></i> Stock Value</p></div>
    </div>
    
    <!-- Filters Bar with Hierarchical Category Dropdown -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <div class="filter-group"><label><i class="fas fa-search"></i> Search</label><input type="text" name="search" class="filter-input" placeholder="Product name..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <div class="filter-group"><label><i class="fas fa-tags"></i> Category</label>
                <select name="category" class="filter-input">
                    <option value="0">All Categories</option>
                    <?php echo $category_options; ?>
                </select>
            </div>
            <div class="filter-group"><label><i class="fas fa-warehouse"></i> Stock Status</label>
                <select name="stock" class="filter-input">
                    <option value="">All Stock</option>
                    <option value="normal" <?php echo $stock_filter == 'normal' ? 'selected' : ''; ?>>Normal Stock (≥10)</option>
                    <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock (&lt;10)</option>
                    <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            <div class="filter-group"><label><i class="fas fa-toggle-on"></i> Status</label>
                <select name="status" class="filter-input">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions Bar -->
    <div class="bulk-bar">
        <div class="bulk-actions">
            <select id="bulkAction" class="bulk-select">
                <option value="">Bulk Actions</option>
                <option value="activate">Activate Selected</option>
                <option value="deactivate">Deactivate Selected</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button type="button" class="btn-bulk" onclick="executeBulkAction()"><i class="fas fa-check-double"></i> Apply</button>
        </div>
        <div class="selected-count" id="selectedCount">0 items selected</div>
    </div>
    
    <!-- Products Table -->
    <div class="table-card">
        <div class="table-header"><h3><i class="fas fa-list"></i> Products List</h3><span><?php echo count($products); ?> products found</span></div>
        <div class="table-container">
            <form id="bulkForm" method="POST">
                <table class="data-table">
                    <thead>
                        <tr><th width="30"><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th><th>Image</th><th>Product Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr><td colspan="8" class="empty-state"><i class="fas fa-box-open"></i><p>No products found</p><a href="add.php" style="color: #e67e22;">Add your first product</a></a></tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                    $img_src = "../../assets/images/default-product.jpg";
                                    if (!empty($product['image_url']) && file_exists("../../" . $product['image_url'])) {
                                        $img_src = "../../" . $product['image_url'];
                                    }
                                ?>
                                <tr>
                                    <td><input type="checkbox" name="product_ids[]" value="<?php echo $product['product_id']; ?>" class="product-checkbox" onclick="updateSelectedCount()"></a>
                                    <td><img src="<?php echo $img_src; ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'"></a>
                                    <td><strong><?php echo htmlspecialchars($product['name']); ?></strong><br><small style="color:#95a5a6;"><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)); ?>...</small></a>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></a>
                                    <td><strong>TSh <?php echo number_format($product['price']); ?></strong></a>
                                    <td>
                                        <strong style="font-size:16px; <?php echo $product['quantity_in_stock'] <= 0 ? 'color:#e74c3c;' : ($product['quantity_in_stock'] < 10 ? 'color:#f39c12;' : 'color:#27ae60;'); ?>"><?php echo $product['quantity_in_stock']; ?></strong> <?php echo $product['unit']; ?>s
                                        <?php if ($product['quantity_in_stock'] <= 0): ?>
                                            <span class="badge badge-out">Out</span>
                                        <?php elseif ($product['quantity_in_stock'] < 10): ?>
                                            <span class="badge badge-low">Low</span>
                                        <?php endif; ?>
                                     </a>
                                    <td><span class="badge <?php echo $product['is_available'] ? 'badge-active' : 'badge-inactive'; ?>"><i class="fas fa-<?php echo $product['is_available'] ? 'check-circle' : 'times-circle'; ?>"></i> <?php echo $product['is_available'] ? 'Active' : 'Inactive'; ?></span></a>
                                    <td>
                                        <a href="edit.php?id=<?php echo $product['product_id']; ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="index.php?toggle_status=<?php echo $product['product_id']; ?>" class="btn-sm btn-toggle"><i class="fas fa-<?php echo $product['is_available'] ? 'eye-slash' : 'eye'; ?>"></i> <?php echo $product['is_available'] ? 'Deactivate' : 'Activate'; ?></a>
                                        <a href="delete.php?id=<?php echo $product['product_id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Are you sure? This product will be permanently deleted if it has no orders.');"><i class="fas fa-trash"></i> Delete</a>
                                     </a>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
</div>

<script>
function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSelectedCount();
}
function updateSelectedCount() {
    const count = document.querySelectorAll('.product-checkbox:checked').length;
    document.getElementById('selectedCount').innerHTML = count + ' item(s) selected';
}
function executeBulkAction() {
    const action = document.getElementById('bulkAction').value;
    const checkboxes = document.querySelectorAll('.product-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Please select at least one product');
        return;
    }
    if (action === '') {
        alert('Please select an action');
        return;
    }
    let confirmMsg = '';
    if (action === 'delete') confirmMsg = 'Delete selected products? This will only delete products without existing orders.';
    if (action === 'activate') confirmMsg = 'Activate selected products?';
    if (action === 'deactivate') confirmMsg = 'Deactivate selected products?';
    if (confirm(confirmMsg)) {
        const form = document.getElementById('bulkForm');
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'bulk_action';
        hiddenInput.value = action;
        form.appendChild(hiddenInput);
        form.submit();
    }
}
</script>
</body>
</html>