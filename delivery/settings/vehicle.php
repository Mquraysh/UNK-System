<?php
// delivery/settings/vehicle.php - VEHICLE INFORMATION (UPDATED with prepared statements)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current vehicle data using prepared statement
$agent_sql = "SELECT * FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

if (!$agent) {
    header("Location: ../register.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_registration = trim($_POST['vehicle_registration'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $vehicle_model = trim($_POST['vehicle_model'] ?? '');
    $vehicle_color = trim($_POST['vehicle_color'] ?? '');
    $insurance_expiry = trim($_POST['insurance_expiry'] ?? '');
    
    // Validation
    if (empty($vehicle_type)) {
        $error = "Vehicle type is required.";
    } elseif (empty($vehicle_registration)) {
        $error = "License plate number is required.";
    } elseif (strlen($vehicle_registration) < 3) {
        $error = "License plate number must be at least 3 characters.";
    } else {
        // Update using prepared statement
        $update_sql = "UPDATE delivery_agents SET 
                        vehicle_type = ?,
                        vehicle_registration = ?,
                        license_number = ?,
                        vehicle_model = ?,
                        vehicle_color = ?,
                        insurance_expiry = ?
                        WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($stmt, "ssssssi", 
            $vehicle_type, $vehicle_registration, $license_number, 
            $vehicle_model, $vehicle_color, $insurance_expiry, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = "Vehicle information updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $error = "Failed to update vehicle information. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}

include '../includes/delivery_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Vehicle Information - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; color: #1e293b; line-height: 1.5; }
        .delivery-content {
            margin-left: 280px;
            padding: 32px 40px;
            min-height: 100vh;
            background: #f5f7fa;
            transition: all 0.2s ease;
        }
        .page-header {
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            background: none;
            color: #e67e22;
        }
        .page-header p {
            color: #64748b;
            font-size: 14px;
            margin-top: 6px;
        }
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        .card {
            background: white;
            border-radius: 28px;
            border: 1px solid #eef2f8;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            transition: box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 12px 24px -12px rgba(0,0,0,0.08);
        }
        .card-header {
            padding: 24px 28px;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-header h3 i {
            color: #e67e22;
        }
        .card-body {
            padding: 28px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }
        .form-group label .required {
            color: #e74c3c;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 14px;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        select.form-control {
            cursor: pointer;
            background: white;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        .btn-save {
            background: linear-gradient(105deg, #e67e22, #d35400);
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(230,126,34,0.3);
        }
        .alert {
            padding: 14px 18px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        .info-note {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 18px 20px;
            border-radius: 20px;
            margin-top: 24px;
            display: flex;
            gap: 14px;
        }
        .info-note i {
            color: #3b82f6;
            font-size: 20px;
        }
        .info-note p {
            font-size: 13px;
            color: #1e40af;
            line-height: 1.5;
        }
        small {
            font-size: 11px;
            color: #64748b;
            display: block;
            margin-top: 6px;
        }
        @media (max-width: 1024px) {
            .delivery-content {
                margin-left: 0;
                padding: 24px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
        @media (max-width: 768px) {
            .delivery-content {
                padding: 16px;
            }
            .card-body {
                padding: 20px;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-motorcycle"></i> Vehicle Information</h1>
            <p>Manage your vehicle details for deliveries</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-truck"></i> Vehicle Details</h3>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type <span class="required">*</span></label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="">Select Vehicle Type</option>
                            <option value="Motorcycle" <?php echo ($agent['vehicle_type'] == 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle (Boda)</option>
                            <option value="Bicycle" <?php echo ($agent['vehicle_type'] == 'Bicycle') ? 'selected' : ''; ?>>Bicycle</option>
                            <option value="Car" <?php echo ($agent['vehicle_type'] == 'Car') ? 'selected' : ''; ?>>Car</option>
                            <option value="Van" <?php echo ($agent['vehicle_type'] == 'Van') ? 'selected' : ''; ?>>Van</option>
                            <option value="Truck" <?php echo ($agent['vehicle_type'] == 'Truck') ? 'selected' : ''; ?>>Truck</option>
                        </select>
                        <small>Select your primary delivery vehicle</small>
                    </div>
                    <div class="form-group">
                        <label>License Plate Number <span class="required">*</span></label>
                        <input type="text" name="vehicle_registration" class="form-control" 
                               value="<?php echo htmlspecialchars($agent['vehicle_registration']); ?>" 
                               placeholder="e.g., T123ABC" required>
                        <small>Vehicle registration / license plate number</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Driver License Number</label>
                        <input type="text" name="license_number" class="form-control" 
                               value="<?php echo htmlspecialchars($agent['license_number']); ?>" 
                               placeholder="e.g., DL123456">
                        <small>Your driving license number (optional)</small>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Model</label>
                        <input type="text" name="vehicle_model" class="form-control" 
                               value="<?php echo htmlspecialchars($agent['vehicle_model'] ?? ''); ?>" 
                               placeholder="e.g., Bajaj Boxer, Toyota Hilux">
                        <small>Vehicle make and model</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Color</label>
                        <input type="text" name="vehicle_color" class="form-control" 
                               value="<?php echo htmlspecialchars($agent['vehicle_color'] ?? ''); ?>" 
                               placeholder="e.g., Red, Blue, Black">
                        <small>Color of your vehicle</small>
                    </div>
                    <div class="form-group">
                        <label>Insurance Expiry Date</label>
                        <input type="date" name="insurance_expiry" class="form-control" 
                               value="<?php echo htmlspecialchars($agent['insurance_expiry'] ?? ''); ?>">
                        <small>Vehicle insurance expiry date (optional)</small>
                    </div>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Vehicle Information
                </button>
            </form>

            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Important Note:</strong> Please ensure your vehicle information is accurate. This information helps businesses verify your delivery capability and may be shared with customers for identification purposes. License plate number is required for all delivery agents.
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>