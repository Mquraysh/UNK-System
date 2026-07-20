<?php
// admin/deliveries/assign.php - ASSIGN DELIVERY TO AGENT
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get delivery info
$del_sql = "SELECT * FROM deliveries WHERE delivery_id = $delivery_id AND status = 'pending'";
$del_res = mysqli_query($conn, $del_sql);
$delivery = mysqli_fetch_assoc($del_res);

if (!$delivery) {
    $_SESSION['flash_message'] = "Delivery not found or already assigned.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Get available agents
$agents_sql = "SELECT agent_id, first_name, last_name, phone FROM delivery_agents WHERE status = 'active' AND is_available = 1 ORDER BY first_name";
$agents_res = mysqli_query($conn, $agents_sql);
$agents = [];
while($row = mysqli_fetch_assoc($agents_res)) {
    $agents[] = $row;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agent_id'])) {
    $agent_id = (int)$_POST['agent_id'];
    $update_sql = "UPDATE deliveries SET agent_id = $agent_id, status = 'assigned', assigned_at = NOW() WHERE delivery_id = $delivery_id";
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['flash_message'] = "Delivery assigned successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: index.php");
        exit();
    } else {
        $message = "Assignment failed.";
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Delivery - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-content { margin-left: 280px; padding: 30px 35px; background: #f1f5f9; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; }
        .card { background: white; border-radius: 24px; border: 1px solid #e2e8f0; max-width: 600px; margin: 0 auto; }
        .card-header { padding: 20px 25px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        select, input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .btn { background: #e67e22; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; width: 100%; }
        .alert { padding: 12px; background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Assign Delivery</h1>
        <a href="index.php" class="btn-back">Back</a>
    </div>
    <div class="card">
        <div class="card-header">Delivery #<?php echo $delivery_id; ?> (Order #<?php echo $delivery['order_id']; ?>)</div>
        <div class="card-body">
            <?php if($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Select Delivery Agent</label>
                    <select name="agent_id" required>
                        <option value="">-- Choose Agent --</option>
                        <?php foreach($agents as $a): ?>
                        <option value="<?php echo $a['agent_id']; ?>"><?php echo $a['first_name'].' '.$a['last_name'].' ('.$a['phone'].')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn">Assign Delivery</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>