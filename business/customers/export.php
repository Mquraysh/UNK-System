<?php
// business/customers/export.php - EXPORT CUSTOMERS TO CSV
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

// Get customers data
$sql = "SELECT DISTINCT 
        c.customer_id, 
        c.first_name, 
        c.last_name, 
        c.saved_address, 
        c.city,
        u.email, 
        u.phone, 
        u.created_at as registered_date,
        COUNT(o.order_id) as total_orders,
        SUM(o.grand_total) as total_spent,
        MAX(o.order_date) as last_order_date
FROM customers c
JOIN users u ON c.user_id = u.user_id
JOIN orders o ON c.customer_id = o.customer_id
WHERE o.business_id = '$business_id'
GROUP BY c.customer_id
ORDER BY total_spent DESC";
$result = mysqli_query($conn, $sql);

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="customers_' . $business_name . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, [
    'Customer ID',
    'First Name',
    'Last Name',
    'Email',
    'Phone',
    'Address',
    'City',
    'Registered Date',
    'Total Orders',
    'Total Spent (TSh)',
    'Last Order Date'
]);

// Data
while($row = mysqli_fetch_assoc($result)) {
    fputcsv($output, [
        $row['customer_id'],
        $row['first_name'],
        $row['last_name'],
        $row['email'],
        $row['phone'],
        $row['saved_address'],
        $row['city'],
        date('Y-m-d', strtotime($row['registered_date'])),
        $row['total_orders'],
        $row['total_spent'],
        date('Y-m-d', strtotime($row['last_order_date']))
    ]);
}

fclose($output);
exit();
?>