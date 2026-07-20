<?php
// admin/products/delete.php - DELETE PRODUCT (CHECK ORDERS)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    $_SESSION['flash_message'] = "Invalid product ID";
    header("Location: index.php");
    exit();
}

// Check if product has any order items
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM order_items WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$cnt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
mysqli_stmt_close($stmt);

if ($cnt > 0) {
    $_SESSION['flash_message'] = "Cannot delete product because it has existing orders. You may deactivate it instead.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = "Product permanently deleted";
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = "Delete failed";
    $_SESSION['flash_type'] = 'danger';
}
mysqli_stmt_close($stmt);
header("Location: index.php");
exit();
?>