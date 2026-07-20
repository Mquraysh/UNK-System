<?php
// admin/delivery_agent/edit.php - EDIT DELIVERY AGENT (WITH LOCATION & REQUIRED MARKERS)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($agent_id <= 0) {
    header("Location: agents.php");
    exit();
}

// Fetch current agent data including user email
$stmt = mysqli_prepare($conn, "
    SELECT a.*, u.email 
    FROM delivery_agents a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.agent_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $agent_id);
mysqli_stmt_execute($stmt);
$agent = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$agent) {
    $_SESSION['flash_message'] = "Agent not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: agents.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Personal details
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $location       = trim($_POST['location'] ?? '');   // NEW: location field
    
    // Vehicle & license
    $vehicle_type   = trim($_POST['vehicle_type'] ?? '');
    $vehicle_reg    = trim($_POST['vehicle_registration'] ?? '');
    $vehicle_model  = trim($_POST['vehicle_model'] ?? '');
    $vehicle_color  = trim($_POST['vehicle_color'] ?? '');
    $id_number      = trim($_POST['id_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $insurance_expiry = trim($_POST['insurance_expiry'] ?? '');
    $status         = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($email)) {
        $error = "Name, phone and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
        $error = "Invalid Tanzanian phone number. Use 0623456789, 0712345678 or +255...";
    } elseif (empty($vehicle_type) || empty($vehicle_reg)) {
        $error = "Vehicle type and registration are required.";
    } elseif (!empty($insurance_expiry) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $insurance_expiry)) {
        $error = "Invalid insurance expiry date format (YYYY-MM-DD).";
    } else {
        // Check if email is already used by another user (exclude current user)
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        mysqli_stmt_bind_param($stmt, 'si', $email, $agent['user_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $error = "Email already registered to another account.";
        } else {
            // Check if phone is already used by another user
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE phone = ? AND user_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $phone, $agent['user_id']);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($exists) {
                $error = "Phone number already registered to another account.";
            } else {
                // Update users table
                $full_name = $first_name . ' ' . $last_name;
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'sssi', $full_name, $email, $phone, $agent['user_id']);
                $user_update = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                if ($user_update) {
                    // Update delivery_agents table (added location)
                    $stmt = mysqli_prepare($conn, "UPDATE delivery_agents SET 
                        first_name = ?, last_name = ?, phone = ?, location = ?,
                        vehicle_type = ?, vehicle_registration = ?, vehicle_model = ?, vehicle_color = ?,
                        id_number = ?, license_number = ?, insurance_expiry = ?, status = ?
                        WHERE agent_id = ?");
                    mysqli_stmt_bind_param($stmt, 'ssssssssssssi', 
                        $first_name, $last_name, $phone, $location,
                        $vehicle_type, $vehicle_reg, $vehicle_model, $vehicle_color,
                        $id_number, $license_number, $insurance_expiry, $status, $agent_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['flash_message'] = "Agent details updated successfully.";
                        $_SESSION['flash_type'] = 'success';
                        header("Location: details.php?id=$agent_id");
                        exit();
                    } else {
                        $error = "Failed to update agent details.";
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $error = "Failed to update user account.";
                }
            }
        }
    }
}

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Edit Agent | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS unchanged – same as before */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; }
        .admin-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
            transition: all 0.3s;
        }
        @media (max-width: 1024px) {
            .admin-content { margin-left: 0; padding: 1.25rem; }
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.75rem;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #1e293b, #2c3e50);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .btn-back {
            background: #64748b;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
        }
        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            color: #334155;
        }
        .required-star {
            color: #e74c3c;
            margin-left: 0.2rem;
        }
        .form-control {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.9rem;
        }
        .form-control:focus { outline: none; border-color: #e67e22; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .btn-submit {
            background: #e67e22;
            color: white;
            padding: 0.7rem;
            border: none;
            border-radius: 2rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
        }
        .btn-submit:hover { background: #d35400; transform: translateY(-2px); }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left-color: #ef4444; }
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .admin-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Delivery Agent</h1>
        <a href="details.php?id=<?= $agent_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Details</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-truck"></i> Agent Information</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <!-- Personal Details (required fields with red star) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required-star">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($agent['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required-star">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($agent['last_name']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email <span class="required-star">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($agent['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required-star">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($agent['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Location (Base Area)</label>
                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($agent['location'] ?? '') ?>" placeholder="e.g., Kariakoo, Posta, Kinondoni">
                </div>

                <!-- Vehicle Details (vehicle type and registration are required) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type <span class="required-star">*</span></label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="Motorcycle" <?= $agent['vehicle_type'] === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle (Boda)</option>
                            <option value="Bicycle" <?= $agent['vehicle_type'] === 'Bicycle' ? 'selected' : '' ?>>Bicycle</option>
                            <option value="Car" <?= $agent['vehicle_type'] === 'Car' ? 'selected' : '' ?>>Car</option>
                            <option value="Van" <?= $agent['vehicle_type'] === 'Van' ? 'selected' : '' ?>>Van</option>
                            <option value="Truck" <?= $agent['vehicle_type'] === 'Truck' ? 'selected' : '' ?>>Truck</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Registration <span class="required-star">*</span></label>
                        <input type="text" name="vehicle_registration" class="form-control" value="<?= htmlspecialchars($agent['vehicle_registration']) ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Model (Optional)</label>
                        <input type="text" name="vehicle_model" class="form-control" value="<?= htmlspecialchars($agent['vehicle_model'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Vehicle Color (Optional)</label>
                        <input type="text" name="vehicle_color" class="form-control" value="<?= htmlspecialchars($agent['vehicle_color'] ?? '') ?>">
                    </div>
                </div>

                <!-- License & ID -->
                <div class="form-row">
                    <div class="form-group">
                        <label>ID Number (Optional)</label>
                        <input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($agent['id_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>License Number (Optional)</label>
                        <input type="text" name="license_number" class="form-control" value="<?= htmlspecialchars($agent['license_number'] ?? '') ?>">
                    </div>
                </div>

                <!-- Insurance & Status -->
                <div class="form-group">
                    <label>Insurance Expiry Date (Optional)</label>
                    <input type="date" name="insurance_expiry" class="form-control" value="<?= htmlspecialchars($agent['insurance_expiry'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $agent['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $agent['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="suspended" <?= $agent['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>