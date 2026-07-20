<?php
// delivery/invoice.php - PROFESSIONAL DELIVERY INVOICE
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = '$user_id'";
$agent_result = mysqli_query($conn, $agent_sql);
$agent = mysqli_fetch_assoc($agent_result);

if (!$agent) {
    header("Location: register.php");
    exit();
}

$agent_id = $agent['agent_id'];
$agent_name = $agent['first_name'] . ' ' . $agent['last_name'];
$agent_phone = $agent['phone'] ?? 'Not provided';

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($delivery_id <= 0) {
    die("Invalid delivery ID.");
}

// Fetch delivery, order, business, customer details
$sql = "SELECT d.*, 
               o.order_id, o.grand_total as order_total, o.delivery_address, o.contact_phone, o.order_date,
               c.first_name, c.last_name, c.saved_address as customer_address,
               b.business_name, b.location as business_address, b.phone as business_phone,
               u.email as customer_email, u.phone as customer_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN businesses b ON o.business_id = b.business_id
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE d.delivery_id = $delivery_id AND d.agent_id = $agent_id";

$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) == 0) {
    die("Delivery not found or you don't have permission.");
}

$delivery = mysqli_fetch_assoc($result);

if ($delivery['status'] != 'delivered') {
    die("Invoice is only available for completed deliveries.");
}

// Generate invoice number (unique and professional)
$invoice_no = 'INV-' . date('Ymd') . '-' . str_pad($delivery['delivery_id'], 5, '0', STR_PAD_LEFT);
$invoice_date = date('F d, Y', strtotime($delivery['delivered_at'] ?? $delivery['updated_at']));
$delivered_at_formatted = date('F d, Y h:i A', strtotime($delivery['delivered_at']));

$delivery_fee = (float)$delivery['delivery_fee'];
$order_total = (float)$delivery['order_total'];
$subtotal = $order_total;
$total = $order_total + $delivery_fee;

// Customer phone: prioritize from users table, fallback to order contact_phone
$customer_phone = !empty($delivery['customer_phone']) ? $delivery['customer_phone'] : ($delivery['contact_phone'] ?? 'Not provided');

// Payment method (from orders table)
$payment_method = !empty($delivery['payment_method']) ? ucfirst($delivery['payment_method']) : 'Cash on Delivery';

// Generate a simple "barcode" (dummy) for visual effect
$barcode_text = $invoice_no;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Invoice #<?php echo htmlspecialchars($invoice_no); ?> | UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            padding: 40px;
            color: #1e293b;
        }
        .invoice-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .invoice-header {
            background: linear-gradient(135deg, #e67e22, #d35400);
            padding: 30px 40px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .logo h2 {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .logo p {
            font-size: 12px;
            opacity: 0.85;
            margin-top: 4px;
        }
        .invoice-title h3 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .invoice-title p {
            font-size: 14px;
            text-align: right;
            margin-top: 4px;
        }
        .invoice-body {
            padding: 40px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-box h4 {
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box p {
            margin-bottom: 6px;
            font-size: 14px;
            line-height: 1.5;
        }
        .info-box strong {
            color: #1e293b;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .details-table th {
            text-align: left;
            padding: 14px 0;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
        }
        .details-table td {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .total-row {
            background: #f8fafc;
            border-radius: 20px;
            padding: 24px;
            margin-top: 20px;
        }
        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .total-grand {
            display: flex;
            justify-content: space-between;
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid #e2e8f0;
            font-weight: 800;
            font-size: 20px;
            color: #e67e22;
        }
        .barcode {
            text-align: center;
            margin: 20px 0 10px;
            padding: 12px;
            background: #fef9e7;
            border-radius: 12px;
            border: 1px dashed #e67e22;
        }
        .barcode .code {
            font-family: monospace;
            font-size: 18px;
            letter-spacing: 2px;
            font-weight: 600;
            color: #e67e22;
        }
        .footer {
            background: #f8fafc;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
        .action-buttons {
            text-align: center;
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .btn-print {
            background: #2c3e50;
            color: white;
        }
        .btn-print:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .btn-back {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-back:hover {
            background: #cbd5e1;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .action-buttons, .footer .no-print, .btn-back, .btn-print {
                display: none;
            }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
            }
            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        @media (max-width: 640px) {
            body {
                padding: 20px;
            }
            .invoice-body {
                padding: 20px;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .invoice-header {
                flex-direction: column;
                text-align: center;
            }
            .invoice-title p {
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="invoice-header">
        <div class="logo">
            <h2>UNK System</h2>
            <p>Ulipo ni Kariakoo – Premium Delivery</p>
        </div>
        <div class="invoice-title">
            <h3>DELIVERY INVOICE</h3>
            <p><?php echo htmlspecialchars($invoice_no); ?></p>
        </div>
    </div>

    <div class="invoice-body">
        <!-- From & To -->
        <div class="info-grid">
            <div class="info-box">
                <h4><i class="fas fa-store"></i> FROM (Business)</h4>
                <p><strong><?php echo htmlspecialchars($delivery['business_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($delivery['business_address'])); ?></p>
                <p><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($delivery['business_phone']); ?></p>
            </div>
            <div class="info-box">
                <h4><i class="fas fa-user"></i> TO (Customer)</h4>
                <p><strong><?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($delivery['delivery_address'])); ?></p>
                <p><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($customer_phone); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($delivery['customer_email']); ?></p>
            </div>
        </div>

        <!-- Invoice & Delivery Info -->
        <div class="info-grid">
            <div class="info-box">
                <h4><i class="fas fa-receipt"></i> Invoice Details</h4>
                <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($invoice_no); ?></p>
                <p><strong>Issue Date:</strong> <?php echo $invoice_date; ?></p>
                <p><strong>Delivery ID:</strong> <?php echo $delivery['delivery_id']; ?></p>
                <p><strong>Order ID:</strong> <?php echo $delivery['order_id']; ?></p>
                <p><strong>Payment Method:</strong> <?php echo $payment_method; ?></p>
            </div>
            <div class="info-box">
                <h4><i class="fas fa-truck"></i> Delivery Information</h4>
                <p><strong>Delivery Agent:</strong> <?php echo htmlspecialchars($agent_name); ?></p>
                <p><strong>Agent Contact:</strong> <?php echo htmlspecialchars($agent_phone); ?></p>
                <p><strong>Status:</strong> <span style="color: #10b981; font-weight: 600;">✓ Delivered</span></p>
                <p><strong>Delivered On:</strong> <?php echo $delivered_at_formatted; ?></p>
            </div>
        </div>

        <!-- Financial Summary -->
        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount (TSh)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Order Total (merchandise)</td>
                    <td>TSh <?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <tr>
                    <td>Delivery Fee</td>
                    <td>TSh <?php echo number_format($delivery_fee, 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="total-row">
            <div class="total-line">
                <span>Subtotal</span>
                <span>TSh <?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="total-line">
                <span>Delivery Fee</span>
                <span>TSh <?php echo number_format($delivery_fee, 2); ?></span>
            </div>
            <div class="total-grand">
                <span>GRAND TOTAL</span>
                <span>TSh <?php echo number_format($total, 2); ?></span>
            </div>
        </div>

        <!-- Barcode visual (simulated) -->
        <div class="barcode">
            <i class="fas fa-barcode fa-2x" style="color: #e67e22;"></i>
            <div class="code"><?php echo htmlspecialchars($barcode_text); ?></div>
            <div style="font-size: 10px; color: #64748b;">Verification Code</div>
        </div>

        <div style="margin-top: 20px; font-size: 12px; color: #64748b; background: #fef9e7; padding: 12px; border-radius: 12px;">
            <i class="fas fa-check-circle" style="color: #10b981;"></i> 
            Payment has been successfully collected via the platform. Thank you for using UNK System.
        </div>
    </div>

    <div class="footer">
        <p>UNK System – Ulipo ni Kariakoo | Kariakoo Market, Dar es Salaam | support@unksystem.com | +255 615 215 404</p>
        <p class="no-print" style="margin-top: 8px;">This is a computer-generated invoice. No signature required.</p>
        <p class="no-print" style="margin-top: 4px; font-size: 10px;"><i class="fas fa-print"></i> Print or save as PDF</p>
    </div>
</div>

<div class="action-buttons no-print">
    <button class="btn btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print Invoice</button>
    <a href="my-deliveries/my-deliveries.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Deliveries</a>
</div>

<script>
    // Auto-print if URL contains 'print=1'
    if (window.location.search.includes('print=1')) {
        window.print();
    }
</script>
</body>
</html>