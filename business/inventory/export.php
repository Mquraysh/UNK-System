<?php
// business/inventory/export.php - EXPORT INVENTORY TO CSV
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
$business_name = $business['business_name'];

// Get all products
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.category_id 
        WHERE p.business_id = '$business_id'
        ORDER BY p.name ASC";
$result = mysqli_query($conn, $sql);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Product ID',
    'Product Name',
    'Category',
    'Description',
    'Price (TSh)',
    'Quantity in Stock',
    'Unit',
    'Status',
    'Created Date'
]);

// Add data rows
while($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['product_id'],
        $row['name'],
        $row['category_name'] ?? 'Uncategorized',
        strip_tags($row['description']),
        $row['price'],
        $row['quantity_in_stock'],
        $row['unit'],
        $row['is_available'] ? 'Active' : 'Inactive',
        date('Y-m-d', strtotime($row['created_at']))
    ]);
}

fclose($output);
exit();
?>