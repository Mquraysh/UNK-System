<?php
// admin/reports/export.php - Export Reports to CSV
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============================================================
// GET PARAMETERS
// ============================================================
$type = isset($_GET['type']) ? $_GET['type'] : 'sales';
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

// Set filename
$filename = $type . '_report_' . date('Y-m-d') . '.csv';

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// ============================================================
// EXPORT BASED ON TYPE
// ============================================================
switch ($type) {
    
    // ============================================================
    // SALES REPORT
    // ============================================================
    case 'sales':
        fputcsv($output, [
            'Order ID', 'Customer', 'Business', 'Items', 'Amount', 
            'Delivery Fee', 'Payment Method', 'Status', 'Date'
        ]);
        
        $sql = "SELECT 
                    o.order_id,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    b.business_name,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
                    o.grand_total,
                    o.delivery_fee,
                    o.payment_method,
                    o.status,
                    o.order_date
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN businesses b ON o.business_id = b.business_id
                WHERE o.status = 'delivered'
                AND DATE(o.order_date) BETWEEN '$from' AND '$to'
                ORDER BY o.order_date DESC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['order_id'],
                $row['customer_name'],
                $row['business_name'],
                $row['items_count'],
                $row['grand_total'],
                $row['delivery_fee'] ?? 0,
                $row['payment_method'] ?? 'N/A',
                $row['status'],
                $row['order_date']
            ]);
        }
        break;
    
    // ============================================================
    // ORDERS REPORT
    // ============================================================
    case 'orders':
        fputcsv($output, [
            'Order ID', 'Customer', 'Business', 'Items', 'Amount', 
            'Delivery Fee', 'Payment Method', 'Payment Status', 'Status', 'Date'
        ]);
        
        $sql = "SELECT 
                    o.order_id,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    b.business_name,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count,
                    o.grand_total,
                    o.delivery_fee,
                    o.payment_method,
                    o.payment_status,
                    o.status,
                    o.order_date
                FROM orders o
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN businesses b ON o.business_id = b.business_id
                WHERE DATE(o.order_date) BETWEEN '$from' AND '$to'
                ORDER BY o.order_date DESC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['order_id'],
                $row['customer_name'],
                $row['business_name'],
                $row['items_count'],
                $row['grand_total'],
                $row['delivery_fee'] ?? 0,
                $row['payment_method'] ?? 'N/A',
                $row['payment_status'] ?? 'N/A',
                $row['status'],
                $row['order_date']
            ]);
        }
        break;
    
    // ============================================================
    // CUSTOMERS REPORT
    // ============================================================
    case 'customers':
        fputcsv($output, [
            'Customer ID', 'Name', 'Email', 'Phone', 'City', 
            'Total Orders', 'Total Spent', 'Joined Date', 'Last Order'
        ]);
        
        $sql = "SELECT 
                    c.customer_id,
                    CONCAT(c.first_name, ' ', c.last_name) as name,
                    u.email,
                    u.phone,
                    c.city,
                    COUNT(o.order_id) as total_orders,
                    COALESCE(SUM(CASE WHEN o.status = 'delivered' THEN o.grand_total ELSE 0 END), 0) as total_spent,
                    c.created_at as joined_date,
                    MAX(o.order_date) as last_order
                FROM customers c
                JOIN users u ON c.user_id = u.user_id
                LEFT JOIN orders o ON c.customer_id = o.customer_id
                WHERE DATE(c.created_at) BETWEEN '$from' AND '$to'
                GROUP BY c.customer_id
                ORDER BY total_spent DESC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['customer_id'],
                $row['name'],
                $row['email'],
                $row['phone'] ?? 'N/A',
                $row['city'] ?? 'N/A',
                $row['total_orders'],
                $row['total_spent'],
                $row['joined_date'],
                $row['last_order'] ?? 'Never'
            ]);
        }
        break;
    
    // ============================================================
    // DELIVERY REPORT
    // ============================================================
    case 'delivery':
        fputcsv($output, [
            'Delivery ID', 'Order ID', 'Customer', 'Business', 'Agent', 
            'Status', 'Delivery Time (min)', 'Fee', 'Created', 'Delivered'
        ]);
        
        $sql = "SELECT 
                    d.delivery_id,
                    d.order_id,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name,
                    b.business_name,
                    CONCAT(a.first_name, ' ', a.last_name) as agent_name,
                    d.status,
                    TIMESTAMPDIFF(MINUTE, d.created_at, d.delivered_at) as delivery_time,
                    d.delivery_fee,
                    d.created_at,
                    d.delivered_at
                FROM deliveries d
                JOIN orders o ON d.order_id = o.order_id
                JOIN customers c ON o.customer_id = c.customer_id
                JOIN businesses b ON o.business_id = b.business_id
                LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
                WHERE DATE(d.created_at) BETWEEN '$from' AND '$to'
                ORDER BY d.created_at DESC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['delivery_id'],
                $row['order_id'],
                $row['customer_name'],
                $row['business_name'],
                $row['agent_name'] ?? 'Unassigned',
                $row['status'],
                $row['delivery_time'] ?? 'N/A',
                $row['delivery_fee'] ?? 0,
                $row['created_at'],
                $row['delivered_at'] ?? 'N/A'
            ]);
        }
        break;
    
    // ============================================================
    // INVENTORY REPORT
    // ============================================================
    case 'inventory':
        fputcsv($output, [
            'Product ID', 'Product Name', 'Category', 'Business', 
            'Price', 'Quantity', 'Stock Value', 'Views', 'Created'
        ]);
        
        $sql = "SELECT 
                    p.product_id,
                    p.name,
                    c.name as category_name,
                    b.business_name,
                    p.price,
                    p.quantity_in_stock,
                    p.price * p.quantity_in_stock as stock_value,
                    p.views,
                    p.created_at
                FROM products p
                JOIN categories c ON p.category_id = c.category_id
                JOIN businesses b ON p.business_id = b.business_id
                WHERE p.is_available = 1 AND p.deleted_at IS NULL
                ORDER BY p.name ASC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['product_id'],
                $row['name'],
                $row['category_name'],
                $row['business_name'],
                $row['price'],
                $row['quantity_in_stock'],
                $row['stock_value'],
                $row['views'],
                $row['created_at']
            ]);
        }
        break;
    
    // ============================================================
    // FINANCIAL REPORT
    // ============================================================
    case 'financial':
        fputcsv($output, [
            'Date', 'Revenue', 'Orders', 'Avg Order Value', 'Delivery Fees'
        ]);
        
        $sql = "SELECT 
                    DATE(order_date) as date,
                    SUM(CASE WHEN status = 'delivered' THEN grand_total ELSE 0 END) as revenue,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as orders,
                    AVG(CASE WHEN status = 'delivered' THEN grand_total ELSE NULL END) as avg_order,
                    SUM(CASE WHEN status = 'delivered' THEN delivery_fee ELSE 0 END) as delivery_fees
                FROM orders
                WHERE DATE(order_date) BETWEEN '$from' AND '$to'
                GROUP BY DATE(order_date)
                ORDER BY DATE(order_date) ASC";
        $result = mysqli_query($conn, $sql);
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, [
                $row['date'],
                $row['revenue'] ?? 0,
                $row['orders'] ?? 0,
                $row['avg_order'] ?? 0,
                $row['delivery_fees'] ?? 0
            ]);
        }
        break;
    
    // ============================================================
    // DEFAULT - SALES
    // ============================================================
    default:
        fputcsv($output, ['Error', 'Invalid export type']);
        break;
}

// Close the output stream
fclose($output);
exit();
?>