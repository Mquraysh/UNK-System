<?php
// admin/products/toggle-status.php - TOGGLE PRODUCT ACTIVE/INACTIVE
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

$stmt = mysqli_prepare($conn, "SELECT is_available FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$cur = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$cur) { $_SESSION['flash_message']="Product not found"; header("Location: index.php"); exit(); }

$new = $cur['is_available'] ? 0 : 1;
$stmt = mysqli_prepare($conn, "UPDATE products SET is_available = ? WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new, $product_id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = "Product " . ($new ? "activated" : "deactivated");
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = "Status change failed";
    $_SESSION['flash_type'] = 'danger';
}
mysqli_stmt_close($stmt);
header("Location: index.php");
exit();
?>