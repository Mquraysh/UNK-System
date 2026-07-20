<?php
// business/reports/export.php - PROFESSIONAL EXPORT REPORTS
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get business data
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$business_name = $business['business_name'];

// Handle export actions
$export_message = '';
$export_type = '';

// Export CSV
if (isset($_POST['export_csv'])) {
    $export_type = 'CSV';
    $report_type = $_POST['report_type'];
    
    // Set filename
    $filename = $business_name . '_' . $report_type . '_' . date('Y-m-d') . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add headers based on report type
    if ($report_type == 'orders') {
        fputcsv($output, ['Order ID', 'Customer', 'Total', 'Status', 'Payment', 'Date', 'Items']);
        
        $sql = "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer, 
                       o.grand_total, o.status, o.payment_status, o.order_date,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.business_id = '$business_id'
                ORDER BY o.order_date DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['order_id'],
                $row['customer'],
                $row['grand_total'],
                $row['status'],
                $row['payment_status'],
                date('Y-m-d H:i', strtotime($row['order_date'])),
                $row['items']
            ]);
        }
    } elseif ($report_type == 'customers') {
        fputcsv($output, ['Customer ID', 'Name', 'Email', 'Phone', 'City', 'Orders', 'Total Spent', 'Joined']);
        
        $sql = "SELECT c.customer_id, CONCAT(c.first_name, ' ', c.last_name) as name,
                       u.email, u.phone, c.city,
                       COUNT(o.order_id) as orders,
                       COALESCE(SUM(o.grand_total), 0) as total_spent,
                       u.created_at as joined
                FROM customers c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN orders o ON c.customer_id = o.customer_id AND o.business_id = '$business_id'
                GROUP BY c.customer_id
                ORDER BY total_spent DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['customer_id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['city'],
                $row['orders'],
                $row['total_spent'],
                date('Y-m-d', strtotime($row['joined']))
            ]);
        }
    } elseif ($report_type == 'products') {
        fputcsv($output, ['Product ID', 'Name', 'Category', 'Price', 'Stock', 'Views', 'Sold']);
        
        $sql = "SELECT p.product_id, p.name, c.name as category, p.price, 
                       p.quantity_in_stock as stock, p.views,
                       (SELECT COUNT(*) FROM order_items WHERE product_id = p.product_id) as sold
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.business_id = '$business_id'
                ORDER BY sold DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category'],
                $row['price'],
                $row['stock'],
                $row['views'],
                $row['sold']
            ]);
        }
    } elseif ($report_type == 'stock') {
        fputcsv($output, ['Product ID', 'Name', 'Category', 'Current Stock', 'Price', 'Status']);
        
        $sql = "SELECT p.product_id, p.name, c.name as category, 
                       p.quantity_in_stock as stock, p.price,
                       CASE 
                           WHEN p.quantity_in_stock <= 0 THEN 'Out of Stock'
                           WHEN p.quantity_in_stock < 10 THEN 'Low Stock'
                           ELSE 'In Stock'
                       END as status
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.business_id = '$business_id'
                ORDER BY p.quantity_in_stock ASC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category'],
                $row['stock'],
                $row['price'],
                $row['status']
            ]);
        }
    }
    
    fclose($output);
    exit();
}

// Export Excel (CSV with Excel headers)
if (isset($_POST['export_excel'])) {
    $export_type = 'Excel';
    $report_type = $_POST['report_type'];
    
    $filename = $business_name . '_' . $report_type . '_' . date('Y-m-d') . '.csv';
    
    // Excel CSV uses different delimiter
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Add BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Use semicolon as delimiter for Excel
    $delimiter = ';';
    
    if ($report_type == 'orders') {
        fputcsv($output, ['Order ID', 'Customer', 'Total', 'Status', 'Payment', 'Date', 'Items'], $delimiter);
        
        $sql = "SELECT o.order_id, CONCAT(c.first_name, ' ', c.last_name) as customer, 
                       o.grand_total, o.status, o.payment_status, o.order_date,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                WHERE o.business_id = '$business_id'
                ORDER BY o.order_date DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['order_id'],
                $row['customer'],
                $row['grand_total'],
                $row['status'],
                $row['payment_status'],
                date('Y-m-d H:i', strtotime($row['order_date'])),
                $row['items']
            ], $delimiter);
        }
    } elseif ($report_type == 'customers') {
        fputcsv($output, ['Customer ID', 'Name', 'Email', 'Phone', 'City', 'Orders', 'Total Spent', 'Joined'], $delimiter);
        
        $sql = "SELECT c.customer_id, CONCAT(c.first_name, ' ', c.last_name) as name,
                       u.email, u.phone, c.city,
                       COUNT(o.order_id) as orders,
                       COALESCE(SUM(o.grand_total), 0) as total_spent,
                       u.created_at as joined
                FROM customers c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN orders o ON c.customer_id = o.customer_id AND o.business_id = '$business_id'
                GROUP BY c.customer_id
                ORDER BY total_spent DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['customer_id'],
                $row['name'],
                $row['email'],
                $row['phone'],
                $row['city'],
                $row['orders'],
                $row['total_spent'],
                date('Y-m-d', strtotime($row['joined']))
            ], $delimiter);
        }
    } elseif ($report_type == 'products') {
        fputcsv($output, ['Product ID', 'Name', 'Category', 'Price', 'Stock', 'Views', 'Sold'], $delimiter);
        
        $sql = "SELECT p.product_id, p.name, c.name as category, p.price, 
                       p.quantity_in_stock as stock, p.views,
                       (SELECT COUNT(*) FROM order_items WHERE product_id = p.product_id) as sold
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.business_id = '$business_id'
                ORDER BY sold DESC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category'],
                $row['price'],
                $row['stock'],
                $row['views'],
                $row['sold']
            ], $delimiter);
        }
    } elseif ($report_type == 'stock') {
        fputcsv($output, ['Product ID', 'Name', 'Category', 'Current Stock', 'Price', 'Status'], $delimiter);
        
        $sql = "SELECT p.product_id, p.name, c.name as category, 
                       p.quantity_in_stock as stock, p.price,
                       CASE 
                           WHEN p.quantity_in_stock <= 0 THEN 'Out of Stock'
                           WHEN p.quantity_in_stock < 10 THEN 'Low Stock'
                           ELSE 'In Stock'
                       END as status
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                WHERE p.business_id = '$business_id'
                ORDER BY p.quantity_in_stock ASC";
        $result = mysqli_query($conn, $sql);
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category'],
                $row['stock'],
                $row['price'],
                $row['status']
            ], $delimiter);
        }
    }
    
    fclose($output);
    exit();
}

// Get statistics for dashboard
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE business_id = '$business_id'"))['total'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT customer_id) as total FROM orders WHERE business_id = '$business_id'"))['total'];
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE business_id = '$business_id'"))['total'];
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(grand_total) as total FROM orders WHERE business_id = '$business_id'"))['total'] ?? 0;

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Export Reports | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        .btn-info {
            background: #3b82f6;
            color: white;
        }
        .btn-info:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        /* Stats Grid */
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
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        .card-body { padding: 1.25rem; }
        
        /* Export Options */
        .export-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        .export-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s;
        }
        .export-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }
        .export-card .card-body { padding: 1.25rem; }
        .export-card .icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.75rem;
            font-size: 1.8rem;
        }
        .export-card .icon-wrapper.csv { background: #dbeafe; color: #2563eb; }
        .export-card .icon-wrapper.excel { background: #d1fae5; color: #059669; }
        .export-card .icon-wrapper.pdf { background: #fee2e2; color: #dc2626; }
        .export-card .icon-wrapper.json { background: #fef3c7; color: #d97706; }
        
        .export-card h4 {
            font-size: 0.95rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.3rem;
        }
        .export-card p {
            font-size: 0.75rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 0.75rem;
        }
        
        .report-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            background: white;
            margin-bottom: 0.75rem;
        }
        .report-select:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .export-btn {
            width: 100%;
            padding: 0.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .export-btn.csv-btn { background: #dbeafe; color: #2563eb; }
        .export-btn.csv-btn:hover { background: #2563eb; color: white; }
        .export-btn.excel-btn { background: #d1fae5; color: #059669; }
        .export-btn.excel-btn:hover { background: #059669; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
            opacity: 0.3;
        }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info { background: #eff6ff; color: #1e40af; border-left: 4px solid #3b82f6; }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .export-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
            .card-body { padding: 0.9rem; }
            .export-card .icon-wrapper { width: 50px; height: 50px; font-size: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-export"></i> Export Reports</h1>
            <p>Export your business data in various formats</p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-number"><?php echo number_format($total_orders); ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?php echo number_format($total_customers); ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-number"><?php echo number_format($total_products); ?></div>
            <div class="stat-label">Total Products</div>
        </div>
    </div>

    <!-- Export Options -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-download"></i> Export Data</h3>
            <span class="badge-count">Choose format</span>
        </div>
        <div class="card-body">
            <div class="export-grid">
                <!-- CSV Export -->
                <div class="export-card">
                    <div class="card-body">
                        <div class="icon-wrapper csv">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <h4>CSV Export</h4>
                        <p>Comma-separated values, compatible with Excel, Google Sheets</p>
                        <form method="POST">
                            <select name="report_type" class="report-select" required>
                                <option value="">-- Select Report --</option>
                                <option value="orders">📦 Orders Report</option>
                                <option value="customers">👤 Customers Report</option>
                                <option value="products">📦 Products Report</option>
                                <option value="stock">📊 Stock Report</option>
                            </select>
                            <button type="submit" name="export_csv" class="export-btn csv-btn">
                                <i class="fas fa-download"></i> Export CSV
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Excel Export -->
                <div class="export-card">
                    <div class="card-body">
                        <div class="icon-wrapper excel">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <h4>Excel Export</h4>
                        <p>Excel-compatible CSV with UTF-8 encoding, semicolon separated</p>
                        <form method="POST">
                            <select name="report_type" class="report-select" required>
                                <option value="">-- Select Report --</option>
                                <option value="orders">📦 Orders Report</option>
                                <option value="customers">👤 Customers Report</option>
                                <option value="products">📦 Products Report</option>
                                <option value="stock">📊 Stock Report</option>
                            </select>
                            <button type="submit" name="export_excel" class="export-btn excel-btn">
                                <i class="fas fa-download"></i> Export Excel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Guide -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Export Guide</h3>
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1rem;">
                <div style="background:#f8fafc; border-radius:0.75rem; padding:0.75rem; border:1px solid #e2e8f0;">
                    <h4 style="font-size:0.8rem; font-weight:700; color:#0f172a;">
                        <i class="fas fa-file-csv" style="color:#2563eb;"></i> CSV Format
                    </h4>
                    <ul style="font-size:0.7rem; color:#64748b; list-style:disc; padding-left:1.2rem; margin-top:0.3rem;">
                        <li>Comma-separated values</li>
                        <li>Compatible with Excel, Google Sheets</li>
                        <li>Best for data analysis</li>
                    </ul>
                </div>
                <div style="background:#f8fafc; border-radius:0.75rem; padding:0.75rem; border:1px solid #e2e8f0;">
                    <h4 style="font-size:0.8rem; font-weight:700; color:#0f172a;">
                        <i class="fas fa-file-excel" style="color:#059669;"></i> Excel Format
                    </h4>
                    <ul style="font-size:0.7rem; color:#64748b; list-style:disc; padding-left:1.2rem; margin-top:0.3rem;">
                        <li>UTF-8 encoding with BOM</li>
                        <li>Semicolon-separated for Excel</li>
                        <li>Better for reporting and printing</li>
                    </ul>
                </div>
                <div style="background:#f8fafc; border-radius:0.75rem; padding:0.75rem; border:1px solid #e2e8f0;">
                    <h4 style="font-size:0.8rem; font-weight:700; color:#0f172a;">
                        <i class="fas fa-clock" style="color:#f59e0b;"></i> Export Tips
                    </h4>
                    <ul style="font-size:0.7rem; color:#64748b; list-style:disc; padding-left:1.2rem; margin-top:0.3rem;">
                        <li>Reports include all data</li>
                        <li>Files are saved with date</li>
                        <li>Open in Excel for best view</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../reports/export.php' || 
            links[i].getAttribute('href') === 'export.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>