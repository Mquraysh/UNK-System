<?php
// admin/deliveries/delivery-details.php - VIEW DELIVERY DETAILS
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$sql = "SELECT d.*, o.order_id, o.grand_total, o.delivery_address, o.contact_phone, o.special_instructions,
               b.business_name, b.address as business_address, b.phone as business_phone,
               a.first_name as agent_first_name, a.last_name as agent_last_name, a.phone as agent_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.order_id
        JOIN businesses b ON o.business_id = b.business_id
        LEFT JOIN delivery_agents a ON d.agent_id = a.agent_id
        WHERE d.delivery_id = $delivery_id";
$result = mysqli_query($conn, $sql);
$delivery = mysqli_fetch_assoc($result);

if (!$delivery) {
    header("Location: index.php");
    exit();
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Details - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-content { margin-left: 280px; padding: 30px 35px; background: #f1f5f9; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; }
        .card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 25px; }
        .card-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
        .card-body { padding: 25px; }
        .info-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 15px; }
        .info-row { display: flex; padding: 8px 0; border-bottom: 1px solid #eef2f6; }
        .info-label { width: 150px; font-weight: 600; color: #64748b; }
        .info-value { flex: 1; color: #1e293b; }
        .status-badge { display: inline-block; padding: 5px 15px; border-radius: 40px; font-size: 12px; font-weight: 600; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-assigned { background: #dbeafe; color: #2563eb; }
        .status-picked_up { background: #c7d2fe; color: #3730a3; }
        .status-in_transit { background: #c7d2fe; color: #3730a3; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-failed { background: #fee2e2; color: #dc2626; }
        .action-buttons { display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px; }
        .btn { padding: 10px 24px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        @media (max-width:1024px){ .admin-content { margin-left:0; padding:20px; } .info-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-truck"></i> Delivery Details</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Deliveries</a>
    </div>
    <div class="card">
        <div class="card-header">Delivery #<?php echo $delivery['delivery_id']; ?> (Order #<?php echo $delivery['order_id']; ?>)</div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-row"><div class="info-label">Status</div><div class="info-value"><span class="status-badge status-<?php echo strtolower($delivery['status']); ?>"><?php echo ucfirst(str_replace('_',' ',$delivery['status'])); ?></span></div></div>
                <div class="info-row"><div class="info-label">Delivery Fee</div><div class="info-value">TSh <?php echo number_format($delivery['delivery_fee']); ?></div></div>
                <div class="info-row"><div class="info-label">Order Amount</div><div class="info-value">TSh <?php echo number_format($delivery['grand_total']); ?></div></div>
                <div class="info-row"><div class="info-label">Business</div><div class="info-value"><?php echo htmlspecialchars($delivery['business_name']); ?></div></div>
                <div class="info-row"><div class="info-label">Business Phone</div><div class="info-value"><?php echo htmlspecialchars($delivery['business_phone']); ?></div></div>
                <div class="info-row"><div class="info-label">Business Address</div><div class="info-value"><?php echo nl2br(htmlspecialchars($delivery['business_address'])); ?></div></div>
                <div class="info-row"><div class="info-label">Delivery Address</div><div class="info-value"><?php echo nl2br(htmlspecialchars($delivery['delivery_address'])); ?></div></div>
                <div class="info-row"><div class="info-label">Customer Phone</div><div class="info-value"><?php echo htmlspecialchars($delivery['contact_phone']); ?></div></div>
                <div class="info-row"><div class="info-label">Special Instructions</div><div class="info-value"><?php echo nl2br(htmlspecialchars($delivery['special_instructions'] ?: 'None')); ?></div></div>
                <div class="info-row"><div class="info-label">Delivery Agent</div><div class="info-value"><?php echo $delivery['agent_first_name'] ? $delivery['agent_first_name'].' '.$delivery['agent_last_name'].' ('.$delivery['agent_phone'].')' : 'Not assigned'; ?></div></div>
                <div class="info-row"><div class="info-label">Assigned At</div><div class="info-value"><?php echo $delivery['assigned_at'] ? date('M d, Y H:i', strtotime($delivery['assigned_at'])) : 'N/A'; ?></div></div>
                <div class="info-row"><div class="info-label">Delivered At</div><div class="info-value"><?php echo $delivery['delivered_at'] ? date('M d, Y H:i', strtotime($delivery['delivered_at'])) : 'N/A'; ?></div></div>
            </div>
        </div>
    </div>
    <div class="action-buttons">
        <a href="update-status.php?id=<?php echo $delivery_id; ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Update Status</a>
        <a href="delete.php?id=<?php echo $delivery_id; ?>" class="btn btn-danger" onclick="return confirm('Delete this delivery record?')"><i class="fas fa-trash-alt"></i> Delete</a>
    </div>
</div>
</body>
</html>