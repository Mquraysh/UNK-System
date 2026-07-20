<?php
// admin/categories/edit.php - PROFESSIONAL EDIT CATEGORY (PREVENTS CYCLES)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($category_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch the category
$stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE category_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $category_id);
mysqli_stmt_execute($stmt);
$category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$category) {
    $_SESSION['flash_message'] = "Category not found.";
    header("Location: index.php");
    exit();
}

// Function to get all descendant IDs (to exclude from parent dropdown)
function getDescendantIds($conn, $cat_id, &$ids = []) {
    $ids[] = $cat_id;
    $stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE parent_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $cat_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        getDescendantIds($conn, $row['category_id'], $ids);
    }
    mysqli_stmt_close($stmt);
    return $ids;
}

$exclude_ids = getDescendantIds($conn, $category_id);
$exclude_ids[] = $category_id;

// Build parent dropdown (excluding current and its descendants)
function buildParentOptionsEdit($conn, $parent_id = NULL, $level = 0, $selected = 0, $exclude = []) {
    $html = '';
    $sql = "SELECT category_id, name FROM categories WHERE parent_id " . ($parent_id === NULL ? "IS NULL" : "= ?") . " ORDER BY sort_order, name";
    $stmt = mysqli_prepare($conn, $sql);
    if ($parent_id !== NULL) {
        mysqli_stmt_bind_param($stmt, 'i', $parent_id);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        if (in_array($row['category_id'], $exclude)) continue;
        $selected_attr = ($selected == $row['category_id']) ? 'selected' : '';
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
        $prefix = ($level > 0) ? '└─ ' : '';
        $html .= '<option value="' . $row['category_id'] . '" ' . $selected_attr . '>' . $indent . $prefix . htmlspecialchars($row['name']) . '</option>';
        $html .= buildParentOptionsEdit($conn, $row['category_id'], $level + 1, $selected, $exclude);
    }
    mysqli_stmt_close($stmt);
    return $html;
}

$parent_options = buildParentOptionsEdit($conn, NULL, 0, $category['parent_id'], $exclude_ids);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] ? (int)$_POST['parent_id'] : NULL;
    $sort_order = (int)$_POST['sort_order'];

    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE categories SET name = ?, parent_id = ?, sort_order = ? WHERE category_id = ?");
        mysqli_stmt_bind_param($stmt, 'siii', $name, $parent_id, $sort_order, $category_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Category updated successfully.";
            $_SESSION['flash_type'] = 'success';
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to update category. Please try again.";
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
    <title>Edit Category | Admin Panel</title>
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
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
        .required-star {
            color: #e74c3c;
            margin-left: 0.2rem;
        }
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
        select.form-control {
            background-color: white;
            cursor: pointer;
        }
        .btn-save {
            background: linear-gradient(105deg, #e67e22, #d35400);
            color: white;
            border: none;
            padding: 0.7rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
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
            .admin-content { padding: 1rem; }
            .card-body { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Category</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Categories</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-tag"></i> Category Information
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Category Name <span class="required-star">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Parent Category</label>
                    <select name="parent_id" class="form-control">
                        <option value="0">-- None (Root Category) --</option>
                        <?= $parent_options ?>
                    </select>
                    <small style="display: block; margin-top: 0.3rem; color: #64748b;">Select parent category (if any). Categories with products cannot become parents.</small>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $category['sort_order'] ?>" placeholder="Numeric order (lower = higher priority)">
                    <small style="display: block; margin-top: 0.3rem; color: #64748b;">Categories are displayed in ascending order of this value.</small>
                </div>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>