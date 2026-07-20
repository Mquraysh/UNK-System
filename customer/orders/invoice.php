<?php
// customer/orders/invoice.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get customer_id
$customer_sql = "SELECT customer_id FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);
$customer_id = $customer['customer_id'];

// Get order details
$order_sql = "SELECT o.*, b.business_name, b.address, b.phone as business_phone,
                     c.first_name, c.last_name, c.saved_address
              FROM orders o
              JOIN businesses b ON o.business_id = b.business_id
              JOIN customers c ON o.customer_id = c.customer_id
              WHERE o.order_id = '$order_id' AND o.customer_id = '$customer_id'";
$order_result = mysqli_query($conn, $order_sql);
$order = mysqli_fetch_assoc($order_result);

if (!$order) {
    header("Location: index.php");
    exit();
}

// Get order items
$items_sql = "SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = '$order_id'";
$items_result = mysqli_query($conn, $items_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_id']; ?> - UNK System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            padding: 40px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e67e22;
        }
        .invoice-title h1 { color: #e67e22; font-size: 28px; }
        .invoice-title p { color: #64748b; }
        .invoice-number { text-align: right; }
        .invoice-number .number { font-size: 20px; font-weight: 700; color: #1e293b; }
        .business-info, .customer-info {
            margin-bottom: 25px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .info-title { font-weight: 700; margin-bottom: 10px; color: #e67e22; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eef2f6; }
        th { background: #f8fafc; }
        .total-row { background: #f8fafc; font-weight: 700; }
        .grand-total { font-size: 18px; color: #e67e22; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eef2f6; color: #64748b; font-size: 12px; }
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none; }
            .invoice-container { box-shadow: none; padding: 0; }
        }
        .btn-print {
            background: #e67e22;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <button class="btn-print no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Invoice</button>
    
    <div class="invoice-header">
        <div class="invoice-title">
            <h1>UNK System</h1>
            <p>Ulipo ni Kariakoo</p>
        </div>
        <div class="invoice-number">
            <p>INVOICE</p>
            <div class="number"><?php echo $order['order_id']; ?></div>
            <p><?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
        </div>
    </div>
    
    <div class="business-info">
        <div class="info-title">Seller Information</div>
        <p><strong><?php echo htmlspecialchars($order['business_name']); ?></strong></p>
        <p><?php echo htmlspecialchars($order['address']); ?></p>
        <p>Phone: <?php echo htmlspecialchars($order['business_phone']); ?></p>
    </div>
    
    <div class="customer-info">
        <div class="info-title">Customer Information</div>
        <p><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></p>
        <p><?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?></p>
    </div>
    
    <table>
        <thead>
            <tr><th>Item</th><th>Quantity</th><th>Unit Price</th><th>Total</th></tr>
        </thead>
        <tbody>
            <?php while($item = mysqli_fetch_assoc($items_result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>TSh <?php echo number_format($item['unit_price']); ?></td>
                <td>TSh <?php echo number_format($item['subtotal']); ?></td>
            </tr>
            <?php endwhile; ?>
            <tr class="total-row"><td colspan="3" style="text-align: right;">Subtotal:</td><td>TSh <?php echo number_format($order['total_amount']); ?></td></tr>
            <tr class="total-row"><td colspan="3" style="text-align: right;">Delivery Fee:</td><td>TSh <?php echo number_format($order['delivery_fee']); ?></td></tr>
            <tr class="total-row"><td colspan="3" style="text-align: right;"><strong>Grand Total:</strong></td><td class="grand-total"><strong>TSh <?php echo number_format($order['grand_total']); ?></strong></td></tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for shopping with UNK System!</p>
        <p>For any inquiries, please contact us at support@unksystem.com</p>
    </div>
</div>
</body>
</html>