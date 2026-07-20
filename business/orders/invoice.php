<?php
// business/orders/invoice.php - PROFESSIONAL ORDER INVOICE (UPDATED)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Fetch business details
$stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

// Fetch business owner's email from users table
$user_sql = "SELECT email FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$business_email = $user_data['email'] ?? 'business@unksystem.com';

$business_id = (int)$business['business_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch order details with customer info (verify business owns the order)
$order_sql = "SELECT o.*, c.first_name, c.last_name, c.saved_address, u.email, u.phone,
                     da.first_name AS agent_first, da.last_name AS agent_last, da.phone AS agent_phone
              FROM orders o
              JOIN customers c ON o.customer_id = c.customer_id
              JOIN users u ON c.user_id = u.user_id
              LEFT JOIN delivery_agents da ON o.agent_id = da.agent_id
              WHERE o.order_id = ? AND o.business_id = ?";
$stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $business_id);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['flash_message'] = "Order not found or you don't have permission.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Fetch order items
$items = [];
$items_sql = "SELECT oi.*, p.name, p.image_url, p.unit
              FROM order_items oi
              JOIN products p ON oi.product_id = p.product_id
              WHERE oi.order_id = ?";
$stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($items_result)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// Generate invoice number
$invoice_no = 'INV-' . date('Ymd') . '-' . str_pad($order_id, 5, '0', STR_PAD_LEFT);
$invoice_date = date('F d, Y');

// Status label mapping
$status_labels = [
    'pending'    => 'Pending',
    'accepted'   => 'Accepted',
    'confirmed'  => 'Confirmed',
    'preparing'  => 'Preparing',
    'ready'      => 'Ready',
    'picked_up'  => 'Picked Up',
    'delivered'  => 'Delivered',
    'cancelled'  => 'Cancelled'
];

$badge_classes = [
    'pending'    => 'badge-pending',
    'accepted'   => 'badge-accepted',
    'confirmed'  => 'badge-confirmed',
    'preparing'  => 'badge-preparing',
    'ready'      => 'badge-ready',
    'picked_up'  => 'badge-picked_up',
    'delivered'  => 'badge-delivered',
    'cancelled'  => 'badge-cancelled'
];

function getProductImage($imagePath) {
    if (empty($imagePath)) return '../../assets/images/default-product.jpg';
    if (preg_match('/^https?:\/\//i', $imagePath)) return $imagePath;
    if ($imagePath[0] === '/') return '../..' . $imagePath;
    return '../../' . ltrim($imagePath, './');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Invoice #<?php echo htmlspecialchars($invoice_no); ?> | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #e67e22;
            --primary-dark: #d35400;
            --secondary: #2c3e50;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --gray-800: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            padding: 2rem;
        }
        .invoice-container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 20px 35px -12px rgba(0,0,0,0.15);
            transition: transform 0.2s;
        }
        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            padding: 2rem 2.5rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .logo-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.15);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #e67e22;
        }
        .logo-text h2 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 0.2rem;
        }
        .logo-text p {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        .invoice-title h3 {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 1px;
            color: #f39c12;
        }
        .invoice-title p {
            text-align: right;
            font-size: 0.85rem;
            opacity: 0.9;
        }
        /* Body */
        .invoice-body {
            padding: 2rem 2.5rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px dashed #eef2f8;
        }
        .info-box h4 {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-box h4 i {
            color: var(--primary);
        }
        .info-box p {
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .info-box strong {
            color: #1e293b;
        }
        /* Table */
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 1.5rem;
            border-radius: 1rem;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        .items-table th {
            text-align: left;
            padding: 0.85rem 1rem;
            background: var(--gray-50);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }
        .items-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f0f2f5;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .items-table tr:hover td {
            background: #fefaf5;
        }
        .total-row {
            background: var(--gray-50);
            font-weight: 700;
        }
        .total-row td {
            padding: 0.85rem 1rem;
            border-top: 1px solid var(--gray-200);
            border-bottom: none;
        }
        .product-img {
            width: 45px;
            height: 45px;
            object-fit: cover;
            border-radius: 0.5rem;
            background: var(--gray-100);
            transition: transform 0.2s;
        }
        .product-img:hover {
            transform: scale(1.05);
        }
        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #b45309; }
        .badge-accepted { background: #ddd6fe; color: #5b21b6; }
        .badge-confirmed { background: #a7f3d0; color: #065f46; }
        .badge-preparing { background: #bfdbfe; color: #1e40af; }
        .badge-ready { background: #fed7aa; color: #9a3412; }
        .badge-picked_up { background: #fbcfe8; color: #9d174d; }
        .badge-delivered { background: #d1fae5; color: #047857; }
        .badge-cancelled { background: #fee2e2; color: #b91c1c; }
        /* Footer */
        .footer {
            background: var(--gray-50);
            padding: 1.5rem 2.5rem;
            text-align: center;
            font-size: 0.7rem;
            color: var(--gray-600);
            border-top: 1px solid var(--gray-200);
        }
        .action-buttons {
            text-align: right;
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 2rem;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.8rem;
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
        .thank-you {
            margin-top: 1.5rem;
            padding: 1rem;
            background: linear-gradient(105deg, #fef9e7, #fff7ed);
            border-radius: 1rem;
            text-align: center;
            font-size: 0.85rem;
            border-left: 4px solid var(--primary);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .action-buttons, .business-sidebar, .sidebar-toggle, .menu-toggle, .btn-back, .btn-print {
                display: none !important;
            }
            .invoice-container {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
            }
            .invoice-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            .invoice-body {
                padding: 1.25rem;
            }
            .info-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .invoice-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
            .invoice-title p {
                text-align: center;
            }
            .logo {
                justify-content: center;
            }
            .items-table th, .items-table td {
                padding: 0.5rem;
                font-size: 0.7rem;
            }
            .product-img {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <div class="invoice-header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="logo-text">
                <h2>UNK System</h2>
                <p>Ulipo ni Kariakoo</p>
            </div>
        </div>
        <div class="invoice-title">
            <h3>TAX INVOICE</h3>
            <p><?php echo htmlspecialchars($invoice_no); ?></p>
        </div>
    </div>

    <div class="invoice-body">
        <!-- Business & Customer Info -->
        <div class="info-grid">
            <div class="info-box">
                <h4><i class="fas fa-store"></i> From (Business)</h4>
                <p><strong><?php echo htmlspecialchars($business['business_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($business['address'])); ?></p>
                <p><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($business['phone']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($business_email); ?></p>
            </div>
            <div class="info-box">
                <h4><i class="fas fa-user"></i> To (Customer)</h4>
                <p><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></p>
                <p><?php echo nl2br(htmlspecialchars($order['delivery_address'] ?: $order['saved_address'])); ?></p>
                <p><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($order['phone']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['email']); ?></p>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="info-grid">
            <div class="info-box">
                <h4><i class="fas fa-receipt"></i> Invoice Details</h4>
                <p><strong>Invoice No:</strong> <?php echo htmlspecialchars($invoice_no); ?></p>
                <p><strong>Issue Date:</strong> <?php echo $invoice_date; ?></p>
                <p><strong>Order ID:</strong> #<?php echo $order['order_id']; ?></p>
                <p><strong>Order Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></p>
            </div>
            <div class="info-box">
                <h4><i class="fas fa-truck"></i> Delivery Details</h4>
                <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                <p><strong>Payment Status:</strong> 
                    <span class="badge" style="background: <?php echo $order['payment_status'] === 'paid' ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $order['payment_status'] === 'paid' ? '#047857' : '#b45309'; ?>">
                        <i class="fas fa-<?php echo $order['payment_status'] === 'paid' ? 'check-circle' : 'clock'; ?>"></i>
                        <?php echo ucfirst($order['payment_status']); ?>
                    </span>
                </p>
                <p><strong>Order Status:</strong> 
                    <span class="badge <?php echo $badge_classes[$order['status']] ?? 'badge-pending'; ?>">
                        <i class="fas fa-<?php echo $order['status'] === 'delivered' ? 'check-circle' : ($order['status'] === 'cancelled' ? 'times-circle' : 'sync'); ?>"></i>
                        <?php echo $status_labels[$order['status']] ?? ucfirst($order['status']); ?>
                    </span>
                </p>
                <?php if (!empty($order['agent_first'])): ?>
                <p><strong>Delivery Agent:</strong> <?php echo htmlspecialchars($order['agent_first'] . ' ' . $order['agent_last']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Order Items Table -->
        <table class="items-table">
            <thead>
                <tr><th>Item</th><th>Description</th><th>Qty</th><th>Unit Price (TSh)</th><th>Subtotal (TSh)</th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td style="width: 70px;">
                        <img src="<?php echo getProductImage($item['image_url'] ?? ''); ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'" alt="<?php echo htmlspecialchars($item['name']); ?>">
                     </a>
                    <td><strong><?php echo htmlspecialchars($item['name']); ?></strong><br><small class="text-muted"><?php echo $item['unit']; ?></small></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price']); ?></td>
                    <td><?php echo number_format($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;"><strong>Subtotal</strong></td>
                    <td><strong>TSh <?php echo number_format($order['total_amount']); ?></strong></td>
                </tr>
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;"><strong>Delivery Fee</strong></td>
                    <td><strong>TSh <?php echo number_format($order['delivery_fee']); ?></strong></td>
                </tr>
                <tr class="total-row">
                    <td colspan="4" style="text-align: right;"><strong>Grand Total</strong></td>
                    <td><strong style="color: var(--primary); font-size: 1rem;">TSh <?php echo number_format($order['grand_total']); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <!-- Thank You Note -->
        <div class="thank-you">
            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i> 
            Thank you for your business! Payment has been processed according to the selected method.
        </div>
    </div>

    <div class="footer">
        <p>UNK System – Ulipo ni Kariakoo | Kariakoo Market, Dar es Salaam | support@unksystem.com | +255 615 215 404</p>
        <p style="margin-top: 0.3rem;">This is a computer-generated invoice. No signature required. Keep for your records.</p>
    </div>
</div>

<div class="action-buttons">
    <button class="btn btn-print" onclick="window.print();"><i class="fas fa-print"></i> Print Invoice</button>
    <a href="details.php?id=<?php echo $order_id; ?>" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back to Order</a>
</div>

<script>
    if (window.location.search.includes('print=1')) {
        window.print();
    }
</script>
</body>
</html>