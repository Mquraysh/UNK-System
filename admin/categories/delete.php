<?php
// admin/categories/delete.php - DELETE CATEGORY
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($category_id > 0) {
    // Check if any products use this category
    $check_sql = "SELECT COUNT(*) as count FROM products WHERE category_id = $category_id";
    $check_res = mysqli_query($conn, $check_sql);
    $count = mysqli_fetch_assoc($check_res)['count'];
    
    if ($count > 0) {
        // Optionally set category_id to NULL for those products
        mysqli_query($conn, "UPDATE products SET category_id = NULL WHERE category_id = $category_id");
    }
    
    // Delete the category
    $delete_sql = "DELETE FROM categories WHERE category_id = $category_id";
    if (mysqli_query($conn, $delete_sql)) {
        $_SESSION['flash_message'] = "Category deleted successfully. Products have been uncategorized.";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Error deleting category: " . mysqli_error($conn);
        $_SESSION['flash_type'] = "danger";
    }
} else {
    $_SESSION['flash_message'] = "Invalid category ID.";
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>