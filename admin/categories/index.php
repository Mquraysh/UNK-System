<?php
// admin/categories/index.php - PROFESSIONAL MAIN & SUB CATEGORIES VIE
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch all categories (for processing)
$all_cats = [];
$stmt = mysqli_prepare($conn, "SELECT category_id, name, parent_id, sort_order FROM categories ORDER BY parent_id, sort_order, name");
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $all_cats[$row['category_id']] = $row;
}
mysqli_stmt_close($stmt);

// Group main categories (parent_id = NULL) and their children
$main_categories = [];
$sub_categories = [];
foreach ($all_cats as $id => $cat) {
    if ($cat['parent_id'] === NULL) {
        $cat['children'] = [];
        $main_categories[$id] = $cat;
    } else {
        $sub_categories[] = $cat;
    }
}
// Attach children to their parents
foreach ($sub_categories as $sub) {
    if (isset($main_categories[$sub['parent_id']])) {
        $main_categories[$sub['parent_id']]['children'][] = $sub;
    } else {
        // orphan? assign to a special "Other" but we'll ignore for now
    }
}
// Sort children by sort_order
foreach ($main_categories as &$main) {
    usort($main['children'], function($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });
}

$total_main = count($main_categories);
$total_sub = count($sub_categories);
$total_all = $total_main + $total_sub;

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
    <title>Manage Categories | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter', sans-serif; background:#f1f5f9; }
        .admin-content {
            margin-left:280px;
            padding:2rem;
            min-height:100vh;
            transition:0.3s;
        }
        @media (max-width:1024px) {
            .admin-content { margin-left:0; padding:1.25rem; }
        }
        .page-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:1rem;
            margin-bottom:1.5rem;
            border-bottom:1px solid #e2e8f0;
            padding-bottom:0.75rem;
        }
        .page-header h1 {
            font-size:1.8rem;
            font-weight:700;
            background:linear-gradient(135deg,#1e293b,#2c3e50);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
            display:flex;
            align-items:center;
            gap:0.75rem;
        }
        .page-header h1 i { color:#e67e22; }
        .btn-add {
            background:#e67e22;
            color:white;
            padding:0.5rem 1rem;
            border-radius:2rem;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            gap:0.5rem;
            transition:0.2s;
        }
        .btn-add:hover { background:#d35400; transform:translateY(-2px); }
        .stats-grid {
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:1rem;
            margin-bottom:1.5rem;
        }
        .stat-card {
            background:white;
            border-radius:1.25rem;
            padding:1rem;
            text-align:center;
            border:1px solid #eef2f8;
            transition:0.2s;
            position:relative;
            overflow:hidden;
        }
        .stat-card::before {
            content:'';
            position:absolute;
            top:0;
            left:0;
            right:0;
            height:3px;
            background:linear-gradient(90deg,#e67e22,#f39c12);
            transform:scaleX(0);
            transition:transform 0.3s;
        }
        .stat-card:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.05); }
        .stat-card:hover::before { transform:scaleX(1); }
        .stat-card h3 {
            font-size:1.8rem;
            font-weight:800;
            color:#e67e22;
            margin-bottom:0.25rem;
            background:linear-gradient(135deg,#1e293b,#2d3e50);
            -webkit-background-clip:text;
            background-clip:text;
            color:transparent;
        }
        .stat-card p { color:#64748b; font-size:0.75rem; font-weight:500;  letter-spacing:0.5px; display:flex; align-items:center; justify-content:center; gap:0.3rem; }
        .main-category-card {
            background:white;
            border-radius:1.25rem;
            border:1px solid #eef2f8;
            overflow:hidden;
            margin-bottom:1.5rem;
            box-shadow:0 1px 3px rgba(0,0,0,0.02);
            transition:0.2s;
        }
        .main-category-card:hover { box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .main-header {
            padding:1rem 1.25rem;
            background:linear-gradient(105deg,#fafcff,#f8fafc);
            border-bottom:1px solid #f0f2f5;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:0.5rem;
        }
        .main-title {
            font-size:1.1rem;
            font-weight:700;
            display:flex;
            align-items:center;
            gap:0.5rem;
        }
        .main-title i { color:#e67e22; }
        .main-badge {
            background:#e67e22;
            color:white;
            padding:0.2rem 0.6rem;
            border-radius:2rem;
            font-size:0.65rem;
            font-weight:600;
        }
        .main-actions { display:flex; gap:0.5rem; }
        .sub-table {
            width:100%;
            border-collapse:collapse;
        }
        .sub-table th {
            text-align:left;
            padding:0.75rem 1rem;
            background:#f8fafc;
            font-size:0.7rem;
            font-weight:700;
            text-transform:uppercase;
            color:#475569;
            border-bottom:1px solid #eef2f8;
        }
        .sub-table td {
            padding:0.75rem 1rem;
            border-bottom:1px solid #f1f5f9;
            font-size:0.85rem;
            vertical-align:middle;
        }
        .sub-table tr:last-child td { border-bottom:none; }
        .sub-table tr:hover td { background:#fffaf5; }
        .badge { display:inline-block; padding:0.2rem 0.6rem; border-radius:2rem; font-size:0.65rem; font-weight:600; background:#e2e8f0; color:#475569; }
        .action-btns { display:flex; gap:0.5rem; }
        .btn-sm {
            padding:0.3rem 0.7rem;
            border-radius:0.5rem;
            text-decoration:none;
            font-size:0.7rem;
            display:inline-flex;
            align-items:center;
            gap:0.3rem;
            transition:0.2s;
            cursor:pointer;
        }
        .btn-sm:hover { transform:translateY(-1px); opacity:0.9; }
        .btn-edit { background:#f59e0b; color:white; }
        .btn-delete { background:#ef4444; color:white; }
        .empty-row td { text-align:center; padding:1rem; color:#94a3b8; }
        .alert { padding:0.75rem 1rem; border-radius:0.75rem; margin-bottom:1.5rem; border-left:4px solid; }
        .alert-success { background:#e6f7ec; color:#0a5c3e; border-left-color:#10b981; }
        .alert-danger { background:#fee2e2; color:#991b1b; border-left-color:#ef4444; }
        @media (max-width:768px) { .main-header { flex-direction:column; align-items:flex-start; } }
        @media (max-width:640px) { .admin-content { padding:1rem; } .stats-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-tags"></i> Manage Categories</h1>
        <a href="add.php" class="btn-add"><i class="fas fa-plus"></i> Add Category</a>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card"><h3><?= $total_main ?></h3><p><i class="fas fa-folder"></i> Main Categories</p></div>
        <div class="stat-card"><h3><?= $total_sub ?></h3><p><i class="fas fa-folder-open"></i> Sub Categories</p></div>
        <div class="stat-card"><h3><?= $total_all ?></h3><p><i class="fas fa-tags"></i> Total Categories</p></div>
    </div>

    <!-- Main Categories and their Sub-categories -->
    <?php if (empty($main_categories)): ?>
        <div class="main-category-card">
            <div class="main-header">
                <span class="main-title"><i class="fas fa-info-circle"></i> No Categories</span>
            </div>
            <div class="sub-table" style="padding:1rem; text-align:center;">No categories found. Click "Add Category" to create one.</div>
        </div>
    <?php else: ?>
        <?php foreach ($main_categories as $main): ?>
            <div class="main-category-card">
                <div class="main-header">
                    <div class="main-title">
                        <i class="fas fa-folder"></i>
                        <?= htmlspecialchars($main['name']) ?>
                        <span class="main-badge"><?= count($main['children']) ?> sub</span>
                    </div>
                    <div class="main-actions">
                        <a href="edit.php?id=<?= $main['category_id'] ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit Main</a>
                        <a href="delete.php?id=<?= $main['category_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Delete this main category and all its sub‑categories? This action cannot be undone.')"><i class="fas fa-trash-alt"></i> Delete</a>
                    </div>
                </div>
                <?php if (empty($main['children'])): ?>
                    <div class="sub-table" style="padding:1rem; text-align:center; color:#94a3b8;">No sub‑categories yet. <a href="add.php?parent=<?= $main['category_id'] ?>" style="color:#e67e22;">Add sub‑category</a></div>
                <?php else: ?>
                    <table class="sub-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Sub‑Category Name</th>
                                <th>Sort Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($main['children'] as $sub): ?>
                                <tr>
                                    <td><?= $sub['category_id'] ?> </a>
                                    <td><?= str_repeat('&nbsp;&nbsp;', 1) . '└─ ' . htmlspecialchars($sub['name']) ?> </a>
                                    <td><?= $sub['sort_order'] ?> </a>
                                    <td class="action-btns">
                                        <a href="edit.php?id=<?= $sub['category_id'] ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="delete.php?id=<?= $sub['category_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this sub‑category?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    </a>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>