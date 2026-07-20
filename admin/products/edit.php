<?php
// admin/products/edit.php 
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
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ?");
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

// Build category tree dropdown (with indentation)
function buildCategoryTreeOptions($conn, $parent_id = NULL, $level = 0, $selected = 0) {
    $html = '';
    $sql = "SELECT category_id, name FROM categories WHERE parent_id " . ($parent_id === NULL ? "IS NULL" : "= ?") . " ORDER BY sort_order, name";
    $stmt = mysqli_prepare($conn, $sql);
    if ($parent_id !== NULL) {
        mysqli_stmt_bind_param($stmt, 'i', $parent_id);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $selected_attr = ($selected == $row['category_id']) ? 'selected' : '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $prefix = ($level > 0) ? '└─ ' : '';
        $html .= '<option value="' . $row['category_id'] . '" ' . $selected_attr . '>' . $indent . $prefix . htmlspecialchars($row['name']) . '</option>';
        $html .= buildCategoryTreeOptions($conn, $row['category_id'], $level + 1, $selected);
    }
    mysqli_stmt_close($stmt);
    return $html;
}
$category_options = buildCategoryTreeOptions($conn, NULL, 0, $product['category_id']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $qty = (int)$_POST['quantity'];
    $unit = trim($_POST['unit']);
    $min_order = (int)$_POST['min_order'];
    $category_id = (int)$_POST['category_id'];
    $desc = trim($_POST['description']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if (empty($name) || $price <= 0) {
        $error = "Product name and a valid price are required.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE products SET name=?, price=?, quantity_in_stock=?, unit=?, min_order=?, category_id=?, description=?, is_available=? WHERE product_id=?");
        mysqli_stmt_bind_param($stmt, 'sdisisisi', $name, $price, $qty, $unit, $min_order, $category_id, $desc, $is_available, $product_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Product updated successfully.";
            $_SESSION['flash_type'] = 'success';
            header("Location: view.php?id=$product_id");
            exit();
        } else {
            $error = "Failed to update product. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Edit Product | Admin</title>
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
        .btn-back {
            background: #64748b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-2px); }
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
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
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            color: #334155;
        }
        .required-star { color: #e74c3c; margin-left: 0.2rem; }
        .form-control {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 2px rgba(230,126,34,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            margin-bottom: 2.5rem;
        }
        .btn-save {
            background: linear-gradient(105deg, #e67e22, #d35400);
            color: white;
            border: none;
            padding: 0.7rem;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
            box-shadow: 0 2px 4px rgba(230,126,34,0.2);
        }
        .btn-save:hover {
            background: linear-gradient(105deg, #d35400, #b84306);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(230,126,34,0.25);
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            border-left: 4px solid;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .admin-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Product</h1>
        <a href="view.php?id=<?= $product_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Product</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-box"></i> Product Information
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Product Name <span class="required-star">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (TSh) <span class="required-star">*</span></label>
                        <input type="number" step="0.01" name="price" class="form-control" value="<?= $product['price'] ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit" class="form-control">
                            <option value="piece" <?= $product['unit'] == 'piece' ? 'selected' : '' ?>>Piece</option>
                            <option value="kg" <?= $product['unit'] == 'kg' ? 'selected' : '' ?>>Kilogram (kg)</option>
                            <option value="gram" <?= $product['unit'] == 'gram' ? 'selected' : '' ?>>Gram (g)</option>
                            <option value="liter" <?= $product['unit'] == 'liter' ? 'selected' : '' ?>>Liter (L)</option>
                            <option value="meter" <?= $product['unit'] == 'meter' ? 'selected' : '' ?>>Meter (m)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="<?= $product['quantity_in_stock'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Minimum Order</label>
                        <input type="number" name="min_order" class="form-control" value="<?= $product['min_order'] ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control">
                        <option value="0">-- Uncategorized --</option>
                        <?= $category_options ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($product['description']) ?></textarea>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="is_available" value="1" <?= $product['is_available'] ? 'checked' : '' ?>>
                        Product is active (visible to customers)
                    </label>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>