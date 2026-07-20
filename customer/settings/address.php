<?php
// customer/settings/address.php 
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get customer data
$customer_sql = "SELECT * FROM customers WHERE user_id = '$user_id'";
$customer_result = mysqli_query($conn, $customer_sql);
$customer = mysqli_fetch_assoc($customer_result);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $saved_address = mysqli_real_escape_string($conn, trim($_POST['saved_address']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    
    if(empty($saved_address)) {
        $error = "Address is required";
    } else {
        $update_sql = "UPDATE customers SET saved_address = '$saved_address', city = '$city' WHERE user_id = '$user_id'";
        
        if(mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = "Address updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to update address";
        }
    }
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Address - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
       * { margin: 0; padding: 0; box-sizing: border-box; }
       body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 30px 35px;
            min-height: 100vh;
            background: #f5f7fb;
        }
        
        .page-header {
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            color: #e67e22;
        }
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        .card-body {
            padding: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
        }
        
        .btn-save {
            background: #e67e22;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .btn-save:hover {
            background: #d35400;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        small {
            font-size: 11px;
            color: #64748b;
            display: block;
            margin-top: 5px;
        }
        
        .address-tip {
            background: #fef3c7;
            padding: 12px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 12px;
            color: #92400e;
        }
        
        @media (max-width: 1024px) {
            .customer-content {
                margin-left: 0;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-map-marker-alt"></i> Delivery Address</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Manage Your Delivery Address</h3>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Full Address</label>
                    <textarea name="saved_address" class="form-control" rows="3" required><?php echo htmlspecialchars($customer['saved_address']); ?></textarea>
                    <small>Include street name, building number, and any landmarks</small>
                </div>
                
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($customer['city']); ?>" required>
                </div>
                
                <div class="address-tip">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Address Tips:</strong><br>
                    • Include your street name and house/building number<br>
                    • Mention nearby landmarks for easy identification<br>
                    • Provide accurate contact number for delivery
                </div>
                
                <button type="submit" class="btn-save" style="margin-top: 20px;"><i class="fas fa-save"></i> Save Address</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>