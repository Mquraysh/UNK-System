<?php
// business/products/delete.php
require_once '../../config/database.php';

session_start();

// Check if business is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

// Get business data
$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];

// Get product ID
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "No product specified";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Verify product belongs to this business
$check_sql = "SELECT * FROM products WHERE product_id = '$product_id' AND business_id = '$business_id'";
$check_result = mysqli_query($conn, $check_sql);
$product = mysqli_fetch_assoc($check_result);

if (!$product) {
    $_SESSION['flash_message'] = "Product not found or you don't have permission to delete it";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Check if product has any order items (foreign key constraint)
$order_check_sql = "SELECT COUNT(*) as order_count FROM order_items WHERE product_id = '$product_id'";
$order_check_result = mysqli_query($conn, $order_check_sql);
$order_data = mysqli_fetch_assoc($order_check_result);

if ($order_data['order_count'] > 0) {
    $_SESSION['flash_message'] = "Cannot delete this product because it has " . $order_data['order_count'] . " order(s). You can deactivate it instead.";
    $_SESSION['flash_type'] = "warning";
    header("Location: index.php");
    exit();
}

// Delete image file if exists
if (!empty($product['image_url'])) {
    $image_path = "../../" . $product['image_url'];
    if (file_exists($image_path)) {
        unlink($image_path);
    }
}

// Delete the product
$delete_sql = "DELETE FROM products WHERE product_id = '$product_id' AND business_id = '$business_id'";
if (mysqli_query($conn, $delete_sql)) {
    $_SESSION['flash_message'] = "Product deleted successfully";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Failed to delete product: " . mysqli_error($conn);
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>