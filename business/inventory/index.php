<?php
// business/inventory/index.php 
require_once '../../config/database.php';

session_start();

// Check if business is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

// Get business data
$user_id = $_SESSION['user_id'];
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_stock'])) {
    $product_id = (int)$_POST['product_id'];
    $new_quantity = (int)$_POST['new_quantity'];
    $reason = trim($_POST['reason'] ?? '');
    
    // Get current stock and product details
    $current_sql = "SELECT name, quantity_in_stock FROM products WHERE product_id = ? AND business_id = ?";
    $stmt = mysqli_prepare($conn, $current_sql);
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $business_id);
    mysqli_stmt_execute($stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    if ($current) {
        $old_quantity = $current['quantity_in_stock'];
        $change = $new_quantity - $old_quantity;
        
        // Update product stock using prepared statement
        $update_sql = "UPDATE products SET quantity_in_stock = ? WHERE product_id = ? AND business_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, 'iii', $new_quantity, $product_id, $business_id);
        $update_ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($update_ok) {
            // Record stock history
            $history_sql = "INSERT INTO stock_history (product_id, business_id, old_quantity, new_quantity, change_amount, action_type, notes, created_at) 
                           VALUES (?, ?, ?, ?, ?, 'manual_update', ?, NOW())";
            $stmt = mysqli_prepare($conn, $history_sql);
            mysqli_stmt_bind_param($stmt, 'iiiiis', $product_id, $business_id, $old_quantity, $new_quantity, $change, $reason);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Insert inventory notification for business
            $title = "Stock Updated";
            $message = "Product '{$current['name']}' stock changed from {$old_quantity} to {$new_quantity}. Reason: " . ($reason ?: "Manual adjustment");
            $type = "inventory";
            $notif_sql = "INSERT INTO business_notifications (business_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())";
            $notif_stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($notif_stmt, 'isss', $business_id, $title, $message, $type);
            mysqli_stmt_execute($notif_stmt);
            mysqli_stmt_close($notif_stmt);
            
            $_SESSION['flash_message'] = "Stock updated successfully for " . htmlspecialchars($current['name']);
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Failed to update stock";
            $_SESSION['flash_type'] = "danger";
        }
    } else {
        $_SESSION['flash_message'] = "Product not found";
        $_SESSION['flash_type'] = "danger";
    }
    header("Location: index.php");
    exit();
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';

// Build query with prepared statements
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.business_id = ?";
$params = [$business_id];
$types = "i";

if (!empty($search)) {
    $sql .= " AND p.name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}
if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
    $types .= "i";
}
if ($stock_filter == 'low') {
    $sql .= " AND p.quantity_in_stock < 10 AND p.quantity_in_stock > 0";
} elseif ($stock_filter == 'out') {
    $sql .= " AND p.quantity_in_stock = 0";
} elseif ($stock_filter == 'normal') {
    $sql .= " AND p.quantity_in_stock >= 10";
}
$sql .= " ORDER BY p.quantity_in_stock ASC, p.name ASC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$products_result = mysqli_stmt_get_result($stmt);
$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}
mysqli_stmt_close($stmt);


// HIERARCHICAL CATEGORY TREE FOR DROPDOWN
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

// Get statistics
$total_products = count($products);
$low_stock_count = 0;
$out_stock_count = 0;
$total_value = 0;

foreach ($products as $p) {
    if ($p['quantity_in_stock'] < 10 && $p['quantity_in_stock'] > 0) $low_stock_count++;
    if ($p['quantity_in_stock'] <= 0) $out_stock_count++;
    $total_value += $p['price'] * $p['quantity_in_stock'];
}

// Check for flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content {
            margin-left: 280px;
            padding: 25px 35px;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #7f8c8d; font-size: 14px; margin-top: 5px; }
        
        .btn-add { background: linear-gradient(135deg, #27ae60, #219a52); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(39,174,96,0.3); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
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
        .stat-card h3 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }
        .stat-card p { color: #7f8c8d; font-size: 13px; }
        .stat-card.total h3 { color: #2c3e50; }
        .stat-card.low h3 { color: #f39c12; }
        .stat-card.out h3 { color: #e74c3c; }
        .stat-card.value h3 { color: #27ae60; }
        
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .filters-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filter-group label { display: block; font-size: 12px; font-weight: 600; color: #7f8c8d; margin-bottom: 6px; text-transform: uppercase; }
        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-input:focus { outline: none; border-color: #e67e22; }
        .btn-filter { background: #e67e22; color: white; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; }
        .btn-filter:hover { background: #d35400; }
        .btn-reset { background: #95a5a6; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .table-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .table-header h3 { font-size: 16px; color: #2c3e50; }
        .table-header h3 i { color: #e67e22; margin-right: 8px; }
        .table-header .actions a { margin-left: 15px; text-decoration: none; font-size: 13px; }
        .btn-history { background: #3498db; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-history:hover { background: #2980b9; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #2c3e50; font-size: 12px; text-transform: uppercase; border-bottom: 1px solid #eef2f6; background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; vertical-align: middle; }
        .data-table tr:hover td { background: #fff5eb; }
        .product-img { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; background: #f0f2f5; }
        .stock-input { width: 100px; padding: 6px 10px; border: 1px solid #e0e0e0; border-radius: 6px; }
        .btn-sm { padding: 5px 10px; border-radius: 6px; text-decoration: none; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-update { background: #e67e22; color: white; border: none; cursor: pointer; }
        .btn-update:hover { background: #d35400; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-low { background: rgba(243,156,18,0.12); color: #f39c12; }
        .badge-out { background: rgba(231,76,60,0.12); color: #e74c3c; }
        .badge-normal { background: rgba(39,174,96,0.12); color: #27ae60; }
        
        .alert { padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 25px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { color: #2c3e50; }
        .modal-close { cursor: pointer; font-size: 24px; color: #7f8c8d; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body label { display: block; margin-bottom: 5px; font-weight: 600; }
        .modal-body input, .modal-body textarea { width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; }
        .btn-modal { background: #e67e22; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; width: 100%; }
        
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state i { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px 15px; } }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .filter-group { min-width: 100%; } }
    </style>
</head>
<body>
    <div class="business-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
                <p>Manage your product stock levels</p>
            </div>
            <a href="../products/add.php" class="btn-add">
                <i class="fas fa-plus-circle"></i> Add Product
            </a>
        </div>
        
        <?php if(!empty($flash_message)): ?>
            <div class="alert alert-<?php echo $flash_type; ?>">
                <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($flash_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <h3><?php echo $total_products; ?></h3>
                <p><i class="fas fa-box"></i> Total Products</p>
            </div>
            <div class="stat-card low">
                <h3><?php echo $low_stock_count; ?></h3>
                <p><i class="fas fa-exclamation-triangle"></i> Low Stock (&lt;10)</p>
            </div>
            <div class="stat-card out">
                <h3><?php echo $out_stock_count; ?></h3>
                <p><i class="fas fa-times-circle"></i> Out of Stock</p>
            </div>
            <div class="stat-card value">
                <h3>TSh <?php echo number_format($total_value); ?></h3>
                <p><i class="fas fa-chart-line"></i> Total Inventory Value</p>
            </div>
        </div>
        
        <!-- Filters with Hierarchical Category Dropdown -->
        <div class="filters-bar">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Search Product</label>
                    <input type="text" name="search" class="filter-input" placeholder="Product name..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-tags"></i> Category</label>
                    <select name="category" class="filter-input">
                        <option value="0">All Categories</option>
                        <?php echo $category_options; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Stock Status</label>
                    <select name="stock" class="filter-input">
                        <option value="">All Stock</option>
                        <option value="normal" <?php echo $stock_filter == 'normal' ? 'selected' : ''; ?>>Normal Stock (&ge;10)</option>
                        <option value="low" <?php echo $stock_filter == 'low' ? 'selected' : ''; ?>>Low Stock (&lt;10)</option>
                        <option value="out" <?php echo $stock_filter == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                    <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Products Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Stock List</h3>
                <div class="actions">
                    <a href="stock-history.php" class="btn-history"><i class="fas fa-history"></i> Stock History</a>
                    <a href="export.php" class="btn-history"><i class="fas fa-download"></i> Export Report</a>
                </div>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price (TSh)</th>
                            <th>Current Stock</th>
                            <th>Status</th>
                            <th>Update Stock</th>
                            <th>History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($products)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-box-open" style="font-size: 48px; color: #95a5a6;"></i>
                                <p>No products found</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($products as $product): ?>
                        <tr>
                            <td>
                                <?php 
                                if(!empty($product['image_url']) && file_exists("../../" . $product['image_url'])) {
                                    $img_src = "../../" . $product['image_url'];
                                } else {
                                    $img_src = "../../assets/images/default-product.jpg";
                                }
                                ?>
                                <img src="<?php echo $img_src; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                            </td>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><strong>TSh <?php echo number_format($product['price']); ?></strong></td>
                            <td>
                                <strong style="font-size: 16px; <?php echo $product['quantity_in_stock'] <= 0 ? 'color:#e74c3c;' : ($product['quantity_in_stock'] < 10 ? 'color:#f39c12;' : 'color:#27ae60;'); ?>"><?php echo $product['quantity_in_stock']; ?></strong> <?php echo $product['unit']; ?>s
                            </td>
                            <td>
                                <?php if($product['quantity_in_stock'] <= 0): ?>
                                    <span class="badge badge-out"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                <?php elseif($product['quantity_in_stock'] < 10): ?>
                                    <span class="badge badge-low"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                                <?php else: ?>
                                    <span class="badge badge-normal"><i class="fas fa-check-circle"></i> In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: flex; gap: 5px; align-items: center;">
                                    <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                    <input type="number" name="new_quantity" class="stock-input" value="<?php echo $product['quantity_in_stock']; ?>" min="0" required>
                                    <button type="button" class="btn-sm btn-update" onclick="openUpdateModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['quantity_in_stock']; ?>)">
                                        <i class="fas fa-save"></i> Update
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="stock-history.php?product_id=<?php echo $product['product_id']; ?>" class="btn-sm btn-history" title="View History" style="background: #3498db; color: white; padding: 5px 10px; border-radius: 6px; text-decoration: none;">
                                    <i class="fas fa-history"></i> History
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Update Stock Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Stock</h3>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="index.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" id="modalProductName" class="form-control" readonly style="background: #f8fafc; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>Current Stock</label>
                        <input type="text" id="modalCurrentStock" class="form-control" readonly style="background: #f8fafc; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>New Stock Quantity <span style="color: #e74c3c;">*</span></label>
                        <input type="number" name="new_quantity" id="modalNewQuantity" class="form-control" min="0" required style="padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>Reason for Update</label>
                        <textarea name="reason" class="form-control" placeholder="e.g., New delivery, Stock adjustment, Returned items..." rows="3" style="padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px; width: 100%; resize: vertical;"></textarea>
                    </div>
                    <input type="hidden" name="product_id" id="modalProductId">
                    <button type="submit" name="update_stock" class="btn-modal"><i class="fas fa-save"></i> Update Stock</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openUpdateModal(productId, productName, currentStock) {
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalProductName').value = productName;
            document.getElementById('modalCurrentStock').value = currentStock;
            document.getElementById('modalNewQuantity').value = currentStock;
            document.getElementById('updateModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('updateModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('updateModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>