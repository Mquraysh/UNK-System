<?php
// admin/delivery_agent/add.php - ADD NEW DELIVERY AGENT (UPDATED PHONE VALIDATION)
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';
    $vehicle_type   = trim($_POST['vehicle_type'] ?? '');
    $vehicle_reg    = trim($_POST['vehicle_registration'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $status         = $_POST['status'] ?? 'active';

    // Validation
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($email) || empty($password)) {
        $error = "All required fields must be filled.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {  // Allows 06 and 07 prefixes
        $error = "Invalid Tanzanian phone number. Use format: 0623456789, 0712345678 or +255623456789, +255712345678";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif (empty($vehicle_type) || empty($vehicle_reg)) {
        $error = "Vehicle type and registration are required.";
    } else {
        // Check if email already exists
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $error = "Email already registered.";
        } else {
            // Check if phone already exists
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE phone = ?");
            mysqli_stmt_bind_param($stmt, 's', $phone);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($exists) {
                $error = "Phone number already registered.";
            } else {
                // Insert into users table
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $full_name = $first_name . ' ' . $last_name;
                $role = 'delivery';
                $user_status = 'active';
                $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, phone, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                mysqli_stmt_bind_param($stmt, 'ssssss', $full_name, $email, $hashed, $phone, $role, $user_status);
                if (mysqli_stmt_execute($stmt)) {
                    $user_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt);
                    
                    // Insert into delivery_agents
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO delivery_agents (user_id, first_name, last_name, phone, vehicle_type, vehicle_registration, license_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt2, 'isssssss', $user_id, $first_name, $last_name, $phone, $vehicle_type, $vehicle_reg, $license_number, $status);
                    if (mysqli_stmt_execute($stmt2)) {
                        $_SESSION['flash_message'] = "Delivery agent added successfully.";
                        $_SESSION['flash_type'] = 'success';
                        header("Location: agents.php");
                        exit();
                    } else {
                        $error = "Failed to create agent profile.";
                    }
                    mysqli_stmt_close($stmt2);
                } else {
                    $error = "Failed to create user account.";
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
    <title>Add Delivery Agent | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* CSS unchanged – same as provided */
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
        <h1><i class="fas fa-user-plus"></i> Add New Delivery Agent</h1>
        <a href="agents.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Agents</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-truck"></i> Agent Information</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email <span style="color:#e74c3c;">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <span style="color:#e74c3c;">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="0623456789 or 0712345678" required>
                    <small>Accepted formats: 0623456789, 0712345678 or +255623456789, +255712345678</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span style="color:#e74c3c;">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span style="color:#e74c3c;">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type <span style="color:#e74c3c;">*</span></label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="Motorcycle">Motorcycle (Boda)</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Car">Car</option>
                            <option value="Van">Van</option>
                            <option value="Truck">Truck</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Registration <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="vehicle_registration" class="form-control" placeholder="e.g., T123ABC" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>License Number (Optional)</label>
                    <input type="text" name="license_number" class="form-control" placeholder="e.g., DL123456">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Add Agent</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>