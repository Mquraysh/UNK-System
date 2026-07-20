<?php
// delivery/register.php - DELIVERY AGENT REGISTRATION (SECURE)
require_once '../config/database.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'delivery') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and sanitize inputs
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_registration = trim($_POST['vehicle_registration'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Store for repopulation
    $form_data = compact('first_name', 'last_name', 'email', 'phone', 'vehicle_type',
                         'vehicle_registration', 'license_number', 'id_number', 'location');

    // Validation
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name))  $errors[] = "Last name is required";
    if (empty($email))      $errors[] = "Email is required";
    if (empty($phone))      $errors[] = "Phone number is required";
    if (empty($vehicle_type)) $errors[] = "Vehicle type is required";
    if (empty($vehicle_registration)) $errors[] = "Vehicle registration is required";
    if (empty($license_number)) $errors[] = "Driver license number is required";
    if (empty($id_number))  $errors[] = "National ID number is required";
    if (empty($password))   $errors[] = "Password is required";

    if (empty($errors)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        }
        // Validate Tanzanian phone number
        elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
            $error = "Please enter a valid Tanzanian phone number (e.g., 0712345678 or +255712345678)!";
        }
        // Password match
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        }
        else {
            // Strong password validation
            $pwd_errors = [];
            if (strlen($password) < 8) $pwd_errors[] = "at least 8 characters";
            if (!preg_match('/[A-Z]/', $password)) $pwd_errors[] = "one uppercase letter";
            if (!preg_match('/[a-z]/', $password)) $pwd_errors[] = "one lowercase letter";
            if (!preg_match('/[0-9]/', $password)) $pwd_errors[] = "one number";
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) $pwd_errors[] = "one special character (!@#$%^&*)";
            
            if (!empty($pwd_errors)) {
                $error = "Password must contain: " . implode(", ", $pwd_errors);
            } else {
                // Check if email already exists
                $check_sql = "SELECT user_id FROM users WHERE email = ?";
                $stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($stmt, 's', $email);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $email_exists = mysqli_num_rows($result) > 0;
                mysqli_stmt_close($stmt);
                
                if ($email_exists) {
                    $error = "Email already registered! Please use a different email.";
                } else {
                    // Check if phone already exists
                    $check_phone = "SELECT user_id FROM users WHERE phone = ?";
                    $stmt = mysqli_prepare($conn, $check_phone);
                    mysqli_stmt_bind_param($stmt, 's', $phone);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    $phone_exists = mysqli_num_rows($result) > 0;
                    mysqli_stmt_close($stmt);
                    
                    if ($phone_exists) {
                        $error = "Phone number already registered!";
                    } else {
                        // Start transaction
                        mysqli_begin_transaction($conn);
                        
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $full_name = $first_name . ' ' . $last_name;
                        $role = 'delivery';
                        $status = 'active';
                        
                        // Insert into users table
                        $user_sql = "INSERT INTO users (full_name, email, password_hash, phone, role, status, created_at) 
                                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = mysqli_prepare($conn, $user_sql);
                        mysqli_stmt_bind_param($stmt, 'ssssss', $full_name, $email, $hashed_password, $phone, $role, $status);
                        $user_result = mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                        
                        if ($user_result) {
                            $user_id = mysqli_insert_id($conn);
                            
                            // Insert into delivery_agents table
                            $agent_sql = "INSERT INTO delivery_agents 
                                         (user_id, first_name, last_name, phone, vehicle_type, vehicle_registration, 
                                          license_number, id_number, location, status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
                            $stmt = mysqli_prepare($conn, $agent_sql);
                            mysqli_stmt_bind_param($stmt, 'issssssss', 
                                $user_id, $first_name, $last_name, $phone, $vehicle_type, 
                                $vehicle_registration, $license_number, $id_number, $location);
                            $agent_result = mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                            
                            if ($agent_result) {
                                mysqli_commit($conn);
                                $_SESSION['registration_success'] = "Registration successful! Please login.";
                                header("Location: login.php");
                                exit();
                            } else {
                                mysqli_rollback($conn);
                                $error = "Registration failed: Could not create agent profile. Please try again.";
                            }
                        } else {
                            mysqli_rollback($conn);
                            $error = "Registration failed: Could not create user account. Please try again.";
                        }
                    }
                }
            }
        }
    } else {
        $error = implode(", ", $errors);
    }
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Delivery Agent Registration | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; }
        .register-container { max-width: 850px; margin: 2rem auto; padding: 0 20px; }
        .card { background: white; border-radius: 20px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%); color: white; padding: 40px 30px; text-align: center; }
        .card-header h2 { font-size: 28px; margin-bottom: 8px; }
        .card-header h2 i { color: #e67e22; margin-right: 10px; }
        .card-header p { opacity: 0.9; font-size: 14px; }
        .card-body { padding: 30px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50; font-size: 13px; }
        .form-group label .required { color: #e74c3c; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        textarea.form-control { resize: vertical; font-family: inherit; }
        select.form-control { cursor: pointer; background: white; }
        .text-muted { font-size: 11px; color: #7f8c8d; margin-top: 5px; display: block; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-align: center; width: 100%; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(230,126,34,0.3); }
        .login-link { margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid #eee; }
        .login-link a { color: #e67e22; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .home-link { text-align: center; margin-top: 15px; }
        .home-link a { color: #7f8c8d; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s; }
        .home-link a:hover { color: #e67e22; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 8px; }
        .checkbox-group label { display: flex; align-items: center; gap: 6px; font-weight: normal; font-size: 13px; cursor: pointer; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .card-body { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="register-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-truck"></i> Delivery Agent Registration</h2>
            <p>Join our delivery team and start earning</p>
        </div>
        <div class="card-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        <small class="text-muted">We'll send important notifications to this email</small>
                    </div>
                </div>

                <div class="form-group">
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="0712345678" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                        <small class="text-muted">Format: 0712345678 or +255712345678</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type <span class="required">*</span></label>
                        <select name="vehicle_type" class="form-control" required>
                            <option value="">Select Vehicle Type</option>
                            <option value="Motorcycle" <?php echo (isset($form_data['vehicle_type']) && $form_data['vehicle_type'] == 'Motorcycle') ? 'selected' : ''; ?>>Motorcycle (Boda)</option>
                            <option value="Bicycle" <?php echo (isset($form_data['vehicle_type']) && $form_data['vehicle_type'] == 'Bicycle') ? 'selected' : ''; ?>>Bicycle</option>
                            <option value="Car" <?php echo (isset($form_data['vehicle_type']) && $form_data['vehicle_type'] == 'Car') ? 'selected' : ''; ?>>Car</option>
                            <option value="Van" <?php echo (isset($form_data['vehicle_type']) && $form_data['vehicle_type'] == 'Van') ? 'selected' : ''; ?>>Van</option>
                            <option value="Truck" <?php echo (isset($form_data['vehicle_type']) && $form_data['vehicle_type'] == 'Truck') ? 'selected' : ''; ?>>Truck</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Registration <span class="required">*</span></label>
                        <input type="text" name="vehicle_registration" class="form-control" placeholder="e.g., T123ABC" value="<?php echo htmlspecialchars($form_data['vehicle_registration'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Driver License Number <span class="required">*</span></label>
                        <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($form_data['license_number'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>National ID Number <span class="required">*</span></label>
                        <input type="text" name="id_number" class="form-control" value="<?php echo htmlspecialchars($form_data['id_number'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Your Location / Area</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Kariakoo, Posta, Mchikichini" value="<?php echo htmlspecialchars($form_data['location'] ?? ''); ?>">
                    <small class="text-muted">Where you are currently based for deliveries</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <small class="text-muted">Min 8 chars, uppercase, lowercase, number & special character</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <small class="text-muted" id="confirmMessage"></small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-truck"></i> Register as Delivery Agent
                </button>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p style="margin-top: 8px; font-size: 12px;">
                        <a href="../customer/register.php">Register as Customer</a> | 
                        <a href="../business/register.php">Register as Business</a>
                    </p>
                </div>
            </form>

            <div class="home-link">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Password match and strength check
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    const confirmMsg = document.getElementById('confirmMessage');
    
    function validatePasswordMatch() {
        if (confirm.value.length > 0) {
            if (password.value === confirm.value) {
                confirmMsg.innerHTML = '<i class="fas fa-check-circle" style="color:#27ae60;"></i> Passwords match!';
                confirmMsg.style.color = '#27ae60';
                confirm.style.borderColor = '#27ae60';
                password.style.borderColor = '#27ae60';
            } else {
                confirmMsg.innerHTML = '<i class="fas fa-times-circle" style="color:#e74c3c;"></i> Passwords do not match!';
                confirmMsg.style.color = '#e74c3c';
                confirm.style.borderColor = '#e74c3c';
                password.style.borderColor = '#e74c3c';
            }
        } else {
            confirmMsg.innerHTML = '';
            confirm.style.borderColor = '#e0e0e0';
        }
    }
    
    password.addEventListener('keyup', validatePasswordMatch);
    confirm.addEventListener('keyup', validatePasswordMatch);
</script>
</body>
</html>
<?php include '../includes/footer2.php'; ?>