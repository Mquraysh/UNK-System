<?php
// business/customers/orders.php - PROFESSIONAL CUSTOMER ORDERS (FIXED)
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
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id == 0) {
    header("Location: index.php");
    exit();
}

// ============================================================
// GET CUSTOMER NAME - REMOVED 'phone' from customers table
// ============================================================
$customer_sql = "SELECT c.first_name, c.last_name, c.city, c.saved_address, u.phone as customer_phone
                 FROM customers c
                 JOIN users u ON c.user_id = u.user_id
                 WHERE c.customer_id = '$customer_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

if (!$customer) {
    $_SESSION['flash_message'] = "Customer not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// ============================================================
// GET CUSTOMER ORDERS
// ============================================================
$orders_sql = "SELECT o.*, 
                      (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as items_count
               FROM orders o
               WHERE o.customer_id = '$customer_id' AND o.business_id = '$business_id'
               ORDER BY o.order_date DESC";
$orders_result = mysqli_query($conn, $orders_sql);
$orders = [];
while($row = mysqli_fetch_assoc($orders_result)) {
    $orders[] = $row;
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_orders = count($orders);
$total_spent = array_sum(array_column($orders, 'grand_total'));
$total_items = array_sum(array_column($orders, 'items_count'));

// Status counts
$status_counts = [
    'pending' => 0,
    'confirmed' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0
];
foreach ($orders as $order) {
    if (isset($status_counts[$order['status']])) {
        $status_counts[$order['status']]++;
    }
}

// Average order value
$avg_order = $total_orders > 0 ? $total_spent / $total_orders : 0;

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Customer Orders - UNK System</title>
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
        .btn-sm {
            padding: 0.25rem 0.6rem;
            border-radius: 0.4rem;
            font-size: 0.65rem;
            background: #3498db;
            color: white;
        }
        .btn-sm:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        /* Customer Header */
        .customer-header {
            background: white;
            border-radius: 1.25rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .customer-header .info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0f172a;
        }
        .customer-header .info p {
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 0.2rem;
        }
        .customer-header .info p i {
            color: #e67e22;
            margin-right: 0.3rem;
        }
        .customer-header .stats {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .customer-header .stats .stat-item {
            text-align: center;
        }
        .customer-header .stats .stat-item .number {
            font-size: 1.2rem;
            font-weight: 800;
            color: #e67e22;
        }
        .customer-header .stats .stat-item .label {
            font-size: 0.6rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
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
        .card-body { padding: 0; }
        
        /* Status Badges */
        .order-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
        }
        .order-status.pending { background: #fef3c7; color: #d97706; }
        .order-status.confirmed { background: #dbeafe; color: #2563eb; }
        .order-status.processing { background: #ede9fe; color: #7c3aed; }
        .order-status.shipped { background: #fce7f3; color: #be185d; }
        .order-status.delivered { background: #d1fae5; color: #059669; }
        .order-status.cancelled { background: #fee2e2; color: #dc2626; }
        
        /* Table */
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            background: #f8fafc;
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        .data-table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover {
            background: #fffbeb;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }
        .empty-state p { font-size: 0.85rem; }
        
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .customer-header { flex-direction: column; align-items: flex-start; }
            .customer-header .stats { width: 100%; justify-content: space-between; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 0.4rem 0.5rem; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .card-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-shopping-cart"></i> Customer Orders</h1>
            <p>Complete order history for this customer</p>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="view.php?id=<?php echo $customer_id; ?>" class="btn-back">
                <i class="fas fa-user"></i> Customer Details
            </a>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Customers
            </a>
        </div>
    </div>

    <!-- Customer Header -->
    <div class="customer-header">
        <div class="info">
            <h3>
                <i class="fas fa-user" style="color:#e67e22;"></i>
                <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
            </h3>
            <p>
                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['customer_phone'] ?? 'N/A'); ?>
                <span style="margin:0 0.5rem;">•</span>
                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($customer['city'] ?? 'N/A'); ?>
                <span style="margin:0 0.5rem;">•</span>
                <i class="fas fa-shopping-bag"></i> <?php echo $total_orders; ?> orders
            </p>
        </div>
        <div class="stats">
            <div class="stat-item">
                <div class="number"><?php echo $total_orders; ?></div>
                <div class="label">Orders</div>
            </div>
            <div class="stat-item">
                <div class="number">TSh <?php echo number_format($total_spent); ?></div>
                <div class="label">Total Spent</div>
            </div>
            <div class="stat-item">
                <div class="number"><?php echo $total_items; ?></div>
                <div class="label">Items</div>
            </div>
            <div class="stat-item">
                <div class="number">TSh <?php echo number_format($avg_order); ?></div>
                <div class="label">Avg Order</div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_spent); ?></div>
            <div class="stat-label">Total Spent</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-box"></i></div>
            <div class="stat-number"><?php echo $total_items; ?></div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calculator"></i></div>
            <div class="stat-number">TSh <?php echo number_format($avg_order); ?></div>
            <div class="stat-label">Average Order</div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Orders</h3>
            <span class="badge-count"><?php echo $total_orders; ?> orders</span>
        </div>
        <div class="card-body">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Orders Found</h3>
                    <p>This customer hasn't placed any orders yet.</p>
                </div>
            <?php else: ?>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Subtotal</th>
                            <th>Delivery</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo $order['order_id']; ?></strong></td>
                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                            <td><?php echo $order['items_count']; ?></td>
                            <td>TSh <?php echo number_format($order['total_amount']); ?></td>
                            <td>TSh <?php echo number_format($order['delivery_fee']); ?></td>
                            <td><strong style="color:#e67e22;">TSh <?php echo number_format($order['grand_total']); ?></strong></td>
                            <td><span class="order-status <?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                            <td>
                                <a href="../orders/details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../customers/orders.php' || 
            links[i].getAttribute('href') === 'orders.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>