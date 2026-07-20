<?php
// business/deliveries/assign.php - ASSIGN DELIVERY AGENT
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
$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get delivery details
$delivery_sql = "SELECT d.*, o.delivery_address, o.contact_phone
                 FROM deliveries d
                 JOIN orders o ON d.order_id = o.order_id
                 WHERE d.delivery_id = '$delivery_id' AND o.business_id = '$business_id'";
$delivery_result = mysqli_query($conn, $delivery_sql);
$delivery = mysqli_fetch_assoc($delivery_result);

if (!$delivery) {
    $_SESSION['flash_message'] = "Delivery not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Get available delivery agents
$agents_sql = "SELECT agent_id, first_name, last_name, vehicle_type, rating 
               FROM delivery_agents 
               WHERE is_available = 1 
               ORDER BY rating DESC";
$agents_result = mysqli_query($conn, $agents_sql);
$agents = [];
while($row = mysqli_fetch_assoc($agents_result)) {
    $agents[] = $row;
}

$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $agent_id = (int)$_POST['agent_id'];
    
    $update_sql = "UPDATE deliveries SET agent_id = '$agent_id', status = 'assigned', assigned_at = NOW() WHERE delivery_id = '$delivery_id'";
    
    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['flash_message'] = "Delivery agent assigned successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: view.php?id=$delivery_id");
        exit();
    } else {
        $flash_message = "Failed to assign agent";
        $flash_type = "danger";
    }
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Delivery Agent - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        
        .business-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f0f2f5;
        }
        
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; }
        
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-body { padding: 28px; }
        
        .delivery-info {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .agent-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .agent-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .agent-item:hover { border-color: #e67e22; background: #fff5eb; }
        .agent-item.selected { border-color: #e67e22; background: #fff5eb; }
        .agent-info h4 { margin-bottom: 5px; }
        .agent-info p { font-size: 12px; color: #64748b; }
        .agent-rating { color: #f39c12; }
        
        .btn-submit { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .btn-submit:hover { background: #d35400; }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="business-content">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Deliveries</a>
    
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Assign Delivery Agent</h1>
    </div>
    
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-exclamation-circle"></i> <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Delivery Information</h3>
        </div>
        <div class="card-body">
            <div class="delivery-info">
                <p><strong>Delivery ID:</strong> #<?php echo $delivery['delivery_id']; ?></p>
                <p><strong>Order ID:</strong> #<?php echo $delivery['order_id']; ?></p>
                <p><strong>Delivery Address:</strong> <?php echo htmlspecialchars($delivery['delivery_address']); ?></p>
                <p><strong>Contact Phone:</strong> <?php echo htmlspecialchars($delivery['contact_phone']); ?></p>
            </div>
            
            <form method="POST" id="assignForm">
                <h4 style="margin-bottom: 15px;"><i class="fas fa-motorcycle"></i> Available Delivery Agents</h4>
                
                <?php if(empty($agents)): ?>
                    <div class="alert alert-danger" style="background: #fef3c7; color: #d97706;">
                        <i class="fas fa-info-circle"></i> No available delivery agents at the moment.
                    </div>
                <?php else: ?>
                <div class="agent-list">
                    <?php foreach($agents as $agent): ?>
                    <div class="agent-item" onclick="selectAgent(<?php echo $agent['agent_id']; ?>)">
                        <div class="agent-info">
                            <h4><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></h4>
                            <p><i class="fas fa-motorcycle"></i> <?php echo ucfirst($agent['vehicle_type']); ?></p>
                        </div>
                        <div class="agent-rating">
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $agent['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                            <span style="color: #64748b;">(<?php echo $agent['rating']; ?>)</span>
                        </div>
                        <input type="radio" name="agent_id" value="<?php echo $agent['agent_id']; ?>" style="display: none;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-submit" <?php echo empty($agents) ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-circle"></i> Assign Agent
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function selectAgent(agentId) {
    // Remove selected class from all items
    document.querySelectorAll('.agent-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('input[type="radio"]').checked = false;
    });
    
    // Add selected class to clicked item
    const selectedItem = event.currentTarget;
    selectedItem.classList.add('selected');
    const radio = selectedItem.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
}
</script>
</body>
</html>