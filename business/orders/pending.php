<?php
// business/order/pending.php 
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

$sql = "SELECT o.*, c.first_name, c.last_name, u.phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON c.user_id = u.user_id
        WHERE o.business_id = '$business_id' AND o.status = 'pending'
        ORDER BY o.order_date ASC";
$result = mysqli_query($conn, $sql);
$orders = [];
while($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Orders - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
       * { margin: 0; padding: 0; box-sizing: border-box; }
       body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 25px 35px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #f39c12; }
        
        .btn-back { background: #7f8c8d; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        
        .table-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .table-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { padding: 14px 16px; text-align: left; font-weight: 600; color: #2c3e50; font-size: 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .data-table td { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
        
        .badge-pending { background: #fef3c7; color: #d97706; padding: 4px 12px; border-radius: 20px; display: inline-block; }
        .btn-sm { padding: 5px 10px; border-radius: 8px; text-decoration: none; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; margin: 2px; }
        .btn-view { background: #3498db; color: white; }
        .btn-update { background: #e67e22; color: white; }
        
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-state i { font-size: 64px; margin-bottom: 15px; opacity: 0.5; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to All Orders</a>
    
    <div class="page-header">
        <h1><i class="fas fa-clock"></i> Pending Orders</h1>
    </div>
    
    <div class="table-card">
        <div class="table-header">
            <strong><?php echo count($orders); ?> pending orders</strong>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($orders)): ?>
                    <tr class="empty-state">
                        <td colspan="6">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending orders</p>
                         </a>
                    </tr>
                    <?php else: ?>
                    <?php foreach($orders as $order): ?>
                    <tr>
                        <td><span style="font-weight: 600;"><?php echo $order['order_id']; ?></span></td>
                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></a>
                        <td><?php echo $order['phone']; ?></a>
                        <td>TSh <?php echo number_format($order['grand_total']); ?></a>
                        <td><?php echo date('M d, H:i', strtotime($order['order_date'])); ?></a>
                        <td>
                            <a href="details.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-view"><i class="fas fa-eye"></i> View</a>
                            <a href="update-status.php?id=<?php echo $order['order_id']; ?>" class="btn-sm btn-update"><i class="fas fa-edit"></i> Update</a>
                         </a>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>