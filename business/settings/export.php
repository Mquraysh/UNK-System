<?php
// business/settings/export.php - EXPORT DATA
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
$business_id = $business['business_id'];

if (isset($_GET['type'])) {
    $type = $_GET['type'];
    $filename = "business_data_{$business_id}_{$type}_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type == 'products') {
        fputcsv($output, ['Product ID', 'Name', 'Description', 'Price', 'Stock', 'Unit', 'Category', 'Status', 'Created']);
        $data = mysqli_query($conn, "SELECT p.*, c.name as category FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.business_id='$business_id'");
        while($row = mysqli_fetch_assoc($data)) {
            fputcsv($output, [$row['product_id'], $row['name'], $row['description'], $row['price'], $row['quantity_in_stock'], $row['unit'], $row['category'], $row['is_available'] ? 'Active' : 'Inactive', $row['created_at']]);
        }
    } elseif ($type == 'orders') {
        fputcsv($output, ['Order ID', 'Date', 'Customer', 'Total', 'Status', 'Payment Method']);
        $data = mysqli_query($conn, "SELECT o.*, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE o.business_id='$business_id'");
        while($row = mysqli_fetch_assoc($data)) {
            fputcsv($output, [$row['order_id'], $row['order_date'], $row['first_name'] . ' ' . $row['last_name'], $row['grand_total'], $row['status'], $row['payment_method']]);
        }
    } elseif ($type == 'all') {
        fputcsv($output, ['Product ID', 'Name', 'Price', 'Stock']);
        $data = mysqli_query($conn, "SELECT product_id, name, price, quantity_in_stock FROM products WHERE business_id='$business_id'");
        while($row = mysqli_fetch_assoc($data)) {
            fputcsv($output, [$row['product_id'], $row['name'], $row['price'], $row['quantity_in_stock']]);
        }
    }
    fclose($output);
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Data - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .export-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-top: 20px; }
        .export-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; transition: all 0.3s; }
        .export-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .card-header { padding: 20px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px; }
        .card-icon { width: 50px; height: 50px; background: rgba(230,126,34,0.1); border-radius: 14px; display: flex; align-items: center; justify-content: center; }
        .card-icon i { font-size: 24px; color: #e67e22; }
        .card-header h3 { font-size: 18px; font-weight: 600; margin: 0; }
        .card-body { padding: 20px 24px; }
        .card-body p { color: #475569; font-size: 13px; margin-bottom: 15px; }
        .btn-export { background: #e67e22; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; transition: all 0.2s; }
        .btn-export:hover { background: #d35400; transform: translateY(-2px); }
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } .export-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .page-header { flex-direction: column; } }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div><h1><i class="fas fa-database"></i> Export Data</h1><p>Download your business data as CSV files</p></div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <div class="export-grid">
        <div class="export-card">
            <div class="card-header"><div class="card-icon"><i class="fas fa-box"></i></div><h3>Products Export</h3></div>
            <div class="card-body"><p>Export all your product information including names, prices, stock levels, and categories.</p><a href="?type=products" class="btn-export"><i class="fas fa-download"></i> Export Products (CSV)</a></div>
        </div>
        <div class="export-card">
            <div class="card-header"><div class="card-icon"><i class="fas fa-shopping-cart"></i></div><h3>Orders Export</h3></div>
            <div class="card-body"><p>Export all customer orders with details including order amounts, status, and dates.</p><a href="?type=orders" class="btn-export"><i class="fas fa-download"></i> Export Orders (CSV)</a></div>
        </div>
        <div class="export-card">
            <div class="card-header"><div class="card-icon"><i class="fas fa-users"></i></div><h3>Customers Export</h3></div>
            <div class="card-body"><p>Export your customer list with names, contact information, and order history.</p><a href="?type=customers" class="btn-export"><i class="fas fa-download"></i> Export Customers (CSV)</a></div>
        </div>
        <div class="export-card">
            <div class="card-header"><div class="card-icon"><i class="fas fa-file-alt"></i></div><h3>Complete Export</h3></div>
            <div class="card-body"><p>Export all your business data in one comprehensive CSV file.</p><a href="?type=all" class="btn-export"><i class="fas fa-download"></i> Export All Data (CSV)</a></div>
        </div>
    </div>
</div>
</body>
</html>