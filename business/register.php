<?php
// business/register.php - UPDATED: ALL FIELDS REQUIRED + NIDA
require_once '../config/database.php';

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role == 'business') {
        header("Location: dashboard.php");
        exit();
    } elseif ($role == 'customer') {
        header("Location: ../customer/dashboard.php");
        exit();
    } elseif ($role == 'delivery') {
        header("Location: ../delivery/dashboard.php");
        exit();
    } elseif ($role == 'admin') {
        header("Location: ../admin/dashboard.php");
        exit();
    }
}

$error = '';
$success = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and trim all inputs
    $first_name        = trim($_POST['first_name'] ?? '');
    $last_name         = trim($_POST['last_name'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $business_name     = trim($_POST['business_name'] ?? '');
    $business_address  = trim($_POST['business_address'] ?? '');
    $city              = trim($_POST['city'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $business_hours    = trim($_POST['business_hours'] ?? '');
    $registration_no   = trim($_POST['registration_number'] ?? '');
    $license_no        = trim($_POST['license_number'] ?? '');
    $location          = trim($_POST['location'] ?? '');
    $nida_number       = trim($_POST['nida_number'] ?? '');
    $payment_methods   = isset($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : '';
    $password          = $_POST['password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';

    // Convert empty strings to NULL for unique fields (registration_number)
    $registration_no_db = ($registration_no === '') ? null : $registration_no;
    $license_no_db      = ($license_no === '') ? null : $license_no;

    // Store for repopulation
    $form_data = [
        'first_name'        => $first_name,
        'last_name'         => $last_name,
        'email'             => $email,
        'phone'             => $phone,
        'business_name'     => $business_name,
        'business_address'  => $business_address,
        'city'              => $city,
        'description'       => $description,
        'business_hours'    => $business_hours,
        'registration_no'   => $registration_no,
        'license_no'        => $license_no,
        'location'          => $location,
        'nida_number'       => $nida_number,
        'payment_methods'   => $payment_methods
    ];

    // ---- Server-side validation (all fields required) ----
    $errors = [];

    if (empty($first_name))        $errors[] = "First name is required";
    if (empty($last_name))         $errors[] = "Last name is required";
    if (empty($email))             $errors[] = "Email is required";
    if (empty($phone))             $errors[] = "Phone number is required";
    if (empty($business_name))     $errors[] = "Business name is required";
    if (empty($business_address))  $errors[] = "Business address is required";
    if (empty($city))              $errors[] = "City is required";
    if (empty($description))       $errors[] = "Business description is required";
    if (empty($business_hours))    $errors[] = "Business hours are required";
    if (empty($registration_no))   $errors[] = "Registration number is required";
    if (empty($license_no))        $errors[] = "License number is required";
    if (empty($location))          $errors[] = "Location / area is required";
    if (empty($nida_number))       $errors[] = "NIDA (National ID) number is required";
    if (empty($payment_methods))   $errors[] = "At least one payment method is required";
    if (empty($password))          $errors[] = "Password is required";

    if (empty($errors)) {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        }
        // Validate Tanzanian phone number
        elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
            $error = "Please enter a valid Tanzanian phone number (e.g., 0712345678 or +255712345678)!";
        }
        // Validate NIDA format (simple: numbers only, length 20? common for Tanzania NIDA is 20 digits)
        elseif (!preg_match('/^\d{20}$/', $nida_number)) {
            $error = "Please enter a valid NIDA number (20 digits).";
        }
        // Password match
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        }
        else {
            // Strong password checks
            $pwd_errors = [];
            if (strlen($password) < 8) {
                $pwd_errors[] = "at least 8 characters";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $pwd_errors[] = "one uppercase letter";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $pwd_errors[] = "one lowercase letter";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $pwd_errors[] = "one number";
            }
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                $pwd_errors[] = "one special character (!@#$%^&*)";
            }

            if (!empty($pwd_errors)) {
                $error = "Password must contain: " . implode(", ", $pwd_errors);
            }
            else {
                // Check if email already exists
                $check_email_sql = "SELECT user_id FROM users WHERE email = ?";
                $stmt_check = mysqli_prepare($conn, $check_email_sql);
                mysqli_stmt_bind_param($stmt_check, "s", $email);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);
                $email_exists = mysqli_stmt_num_rows($stmt_check) > 0;
                mysqli_stmt_close($stmt_check);

                if ($email_exists) {
                    $error = "Email already registered! Please use a different email or login.";
                }
                else {
                    // Check if phone already exists
                    $check_phone_sql = "SELECT user_id FROM users WHERE phone = ?";
                    $stmt_phone = mysqli_prepare($conn, $check_phone_sql);
                    mysqli_stmt_bind_param($stmt_phone, "s", $phone);
                    mysqli_stmt_execute($stmt_phone);
                    mysqli_stmt_store_result($stmt_phone);
                    $phone_exists = mysqli_stmt_num_rows($stmt_phone) > 0;
                    mysqli_stmt_close($stmt_phone);

                    if ($phone_exists) {
                        $error = "Phone number already registered!";
                    }
                    else {
                        // Check if registration_number already exists (if provided)
                        if ($registration_no_db !== null) {
                            $check_reg_sql = "SELECT business_id FROM businesses WHERE registration_number = ?";
                            $stmt_reg = mysqli_prepare($conn, $check_reg_sql);
                            mysqli_stmt_bind_param($stmt_reg, "s", $registration_no_db);
                            mysqli_stmt_execute($stmt_reg);
                            mysqli_stmt_store_result($stmt_reg);
                            $reg_exists = mysqli_stmt_num_rows($stmt_reg) > 0;
                            mysqli_stmt_close($stmt_reg);
                            
                            if ($reg_exists) {
                                $error = "Registration number already exists! Please use a different number.";
                            }
                        }
                        
                        // Check if NIDA already used (optional, but good)
                        if (empty($error)) {
                            $check_nida_sql = "SELECT business_id FROM businesses WHERE nida_number = ?";
                            $stmt_nida = mysqli_prepare($conn, $check_nida_sql);
                            mysqli_stmt_bind_param($stmt_nida, "s", $nida_number);
                            mysqli_stmt_execute($stmt_nida);
                            mysqli_stmt_store_result($stmt_nida);
                            $nida_exists = mysqli_stmt_num_rows($stmt_nida) > 0;
                            mysqli_stmt_close($stmt_nida);
                            
                            if ($nida_exists) {
                                $error = "NIDA number already registered!";
                            }
                        }
                        
                        if (empty($error)) {
                            // Hash password
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $full_name = $first_name . ' ' . $last_name;
                            $role = 'business';
                            $status = 'active';

                            // Start transaction
                            mysqli_begin_transaction($conn);

                            // Insert into users table
                            $user_sql = "INSERT INTO users (full_name, email, password_hash, phone, role, status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            $stmt_user = mysqli_prepare($conn, $user_sql);
                            mysqli_stmt_bind_param($stmt_user, "ssssss", $full_name, $email, $hashed_password, $phone, $role, $status);
                            $user_result = mysqli_stmt_execute($stmt_user);

                            if ($user_result) {
                                $user_id = mysqli_insert_id($conn);
                                mysqli_stmt_close($stmt_user);

                                // Insert into businesses table (added nida_number)
                                $business_sql = "INSERT INTO businesses 
                                    (user_id, business_name, registration_number, description, address, location, city, 
                                    license_number, business_hours, phone, payment_methods, nida_number, is_verified, is_active, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())";
                                $stmt_biz = mysqli_prepare($conn, $business_sql);
                                mysqli_stmt_bind_param($stmt_biz, "isssssssssss", 
                                    $user_id, 
                                    $business_name, 
                                    $registration_no_db,
                                    $description, 
                                    $business_address, 
                                    $location, 
                                    $city, 
                                    $license_no_db,
                                    $business_hours, 
                                    $phone,
                                    $payment_methods,
                                    $nida_number
                                );
                                $business_result = mysqli_stmt_execute($stmt_biz);
                                mysqli_stmt_close($stmt_biz);

                                if ($business_result) {
                                    mysqli_commit($conn);
                                    $_SESSION['registration_success'] = "Business registration successful! Please login.";
                                    header("Location: login.php");
                                    exit();
                                } else {
                                    mysqli_rollback($conn);
                                    $error = "Registration failed: Could not create business profile. Please try again.";
                                }
                            } else {
                                mysqli_rollback($conn);
                                $error = "Registration failed: Could not create user account. Please try again.";
                            }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Registration - UNK System</title>
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
            <h2><i class="fas fa-store"></i> Business Registration</h2>
            <p>Register your business and start selling on UNK System</p>
        </div>
        <div class="card-body">
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <h3 style="color: #2c3e50; margin-bottom: 20px; font-size: 18px;">
                    <i class="fas fa-user"></i> Personal Information
                </h3>
                
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
                
                <!-- <div class="form-row">                           -->
                    <div class="form-group">
                        <label>Email Address <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                        <small class="text-muted">We'll send business notifications to this email</small>
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-control" placeholder="0712345678" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                        <small class="text-muted">Format: 0712345678 or +255712345678</small>
                    </div>
                <!-- </div> -->
                
                <h3 style="color: #2c3e50; margin: 20px 0 20px 0; font-size: 18px;">
                    <i class="fas fa-store"></i> Business Information
                </h3>
                
                <div class="form-group">
                    <label>Business Name <span class="required">*</span></label>
                    <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($form_data['business_name'] ?? ''); ?>" required>
                    <small class="text-muted">Your store/business name as it will appear to customers</small>
                </div>
                
                <div class="form-group">
                    <label>Business Address <span class="required">*</span></label>
                    <textarea name="business_address" rows="2" class="form-control" required><?php echo htmlspecialchars($form_data['business_address'] ?? ''); ?></textarea>
                    <small class="text-muted">Full address of your business in Kariakoo</small>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>City <span class="required">*</span></label>
                        <select name="city" class="form-control" required>
                            <option value="Dar es Salaam" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Dar es Salaam') ? 'selected' : ''; ?>>Dar es Salaam</option>
                            <option value="Arusha" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Arusha') ? 'selected' : ''; ?>>Arusha</option>
                            <option value="Mwanza" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Mwanza') ? 'selected' : ''; ?>>Mwanza</option>
                            <option value="Dodoma" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Dodoma') ? 'selected' : ''; ?>>Dodoma</option>
                            <option value="Zanzibar" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Zanzibar') ? 'selected' : ''; ?>>Zanzibar</option>
                            <option value="Other" <?php echo (isset($form_data['city']) && $form_data['city'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location / Area <span class="required">*</span></label>
                        <input type="text" name="location" class="form-control" placeholder="e.g., Kariakoo-Msimbazi" value="<?php echo htmlspecialchars($form_data['location'] ?? ''); ?>" required>
                        <small class="text-muted">Specific area inside Kariakoo</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Registration Number <span class="required">*</span></label>
                        <input type="text" name="registration_number" class="form-control" placeholder="e.g., BLA-2024-00123" value="<?php echo htmlspecialchars($form_data['registration_no'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>License Number <span class="required">*</span></label>
                        <input type="text" name="license_number" class="form-control" placeholder="e.g., TIN-12345678" value="<?php echo htmlspecialchars($form_data['license_no'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>NIDA (National ID) Number <span class="required">*</span></label>
                    <input type="text" name="nida_number" class="form-control" placeholder="20 digits NIDA number" value="<?php echo htmlspecialchars($form_data['nida_number'] ?? ''); ?>" required>
                    <small class="text-muted">Your 20-digit National ID number</small>
                </div>
                
                <div class="form-group">
                    <label>Business Hours <span class="required">*</span></label>
                    <input type="text" name="business_hours" class="form-control" placeholder="Mon-Fri: 9AM-6PM, Sat: 10AM-4PM" value="<?php echo htmlspecialchars($form_data['business_hours'] ?? ''); ?>" required>
                    <small class="text-muted">Your operating hours</small>
                </div>
                
                <div class="form-group">
                    <label>Business Description <span class="required">*</span></label>
                    <textarea name="description" rows="3" class="form-control" placeholder="Describe what your business sells and what makes it unique..." required><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    <small class="text-muted">Tell customers about your products and services</small>
                </div>
                
                <div class="form-group">
                    <label>Accepted Payment Methods <span class="required">*</span></label>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="payment_methods[]" value="cash" <?php echo (strpos($form_data['payment_methods'] ?? '', 'cash') !== false) ? 'checked' : ''; ?>> Cash</label>
                        <label><input type="checkbox" name="payment_methods[]" value="mobile_money" <?php echo (strpos($form_data['payment_methods'] ?? '', 'mobile_money') !== false) ? 'checked' : ''; ?>> Mobile Money</label>
                        <label><input type="checkbox" name="payment_methods[]" value="bank_transfer" <?php echo (strpos($form_data['payment_methods'] ?? '', 'bank_transfer') !== false) ? 'checked' : ''; ?>> Bank Transfer</label>
                        <label><input type="checkbox" name="payment_methods[]" value="card" <?php echo (strpos($form_data['payment_methods'] ?? '', 'card') !== false) ? 'checked' : ''; ?>> Card</label>
                    </div>
                    <small class="text-muted">Select which payment methods you accept (at least one)</small>
                </div>
                
                <h3 style="color: #2c3e50; margin: 20px 0 20px 0; font-size: 18px;">
                    <i class="fas fa-lock"></i> Account Security
                </h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <small class="text-muted">Min 8 chars, uppercase, lowercase, number & special char</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span class="required">*</span></label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <small class="text-muted" id="confirmMessage"></small>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-store"></i> Register Business
                </button>
                
                <div class="login-link">
                    <p>Already have a business account? <a href="login.php">Login here</a></p>
                    <p style="margin-top: 8px; font-size: 12px;">
                        <a href="../customer/register.php">Register as Customer</a> | 
                        <a href="../delivery/register.php">Register as Delivery Agent</a>
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
// Same JavaScript as before (password match, validation)
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('confirm_password');
const confirmMessage = document.getElementById('confirmMessage');

passwordInput.onkeyup = function() {
    let pwd = this.value;
    let errors = [];
    
    if (pwd.length > 0 && pwd.length < 8) {
        errors.push("at least 8 characters");
        this.style.borderColor = '#e74c3c';
    }
    if (pwd.length > 0 && !/[A-Z]/.test(pwd)) {
        errors.push("one uppercase letter");
        this.style.borderColor = '#e74c3c';
    }
    if (pwd.length > 0 && !/[a-z]/.test(pwd)) {
        errors.push("one lowercase letter");
        this.style.borderColor = '#e74c3c';
    }
    if (pwd.length > 0 && !/[0-9]/.test(pwd)) {
        errors.push("one number");
        this.style.borderColor = '#e74c3c';
    }
    if (pwd.length > 0 && !/[!@#$%^&*(),.?":{}|<>]/.test(pwd)) {
        errors.push("one special character (!@#$%^&*)");
        this.style.borderColor = '#e74c3c';
    }
    
    if (pwd.length > 0 && errors.length === 0) {
        this.style.borderColor = '#27ae60';
    } else if (pwd.length === 0) {
        this.style.borderColor = '#e0e0e0';
    }
    
    let confirmVal = confirmInput.value;
    if (confirmVal.length > 0) {
        if (pwd === confirmVal) {
            confirmMessage.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Passwords match!';
            confirmMessage.style.color = '#27ae60';
            confirmInput.style.borderColor = '#27ae60';
        } else {
            confirmMessage.innerHTML = '<i class="fas fa-times-circle" style="color: #e74c3c;"></i> Passwords do not match!';
            confirmMessage.style.color = '#e74c3c';
            confirmInput.style.borderColor = '#e74c3c';
        }
    } else {
        confirmMessage.innerHTML = '';
        confirmInput.style.borderColor = '#e0e0e0';
    }
};

confirmInput.onkeyup = function() {
    let pwd = passwordInput.value;
    let confirmVal = this.value;
    
    if (confirmVal.length > 0) {
        if (pwd === confirmVal) {
            confirmMessage.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Passwords match!';
            confirmMessage.style.color = '#27ae60';
            this.style.borderColor = '#27ae60';
            passwordInput.style.borderColor = '#27ae60';
        } else {
            confirmMessage.innerHTML = '<i class="fas fa-times-circle" style="color: #e74c3c;"></i> Passwords do not match!';
            confirmMessage.style.color = '#e74c3c';
            this.style.borderColor = '#e74c3c';
        }
    } else {
        confirmMessage.innerHTML = '';
        this.style.borderColor = '#e0e0e0';
    }
};

document.getElementById('registerForm').onsubmit = function(e) {
    let password = passwordInput.value;
    let confirm = confirmInput.value;
    let hasError = false;
    let errorMsg = '';
    
    if (password.length < 8) {
        errorMsg = "Password must be at least 8 characters long!";
        hasError = true;
    } 
    else if (!/[A-Z]/.test(password)) {
        errorMsg = "Password must contain at least one uppercase letter (A-Z)!";
        hasError = true;
    } 
    else if (!/[a-z]/.test(password)) {
        errorMsg = "Password must contain at least one lowercase letter (a-z)!";
        hasError = true;
    } 
    else if (!/[0-9]/.test(password)) {
        errorMsg = "Password must contain at least one number (0-9)!";
        hasError = true;
    } 
    else if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
        errorMsg = "Password must contain at least one special character (!@#$%^&*)!";
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
        alert(errorMsg);
        passwordInput.focus();
        return false;
    }
    
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        confirmInput.focus();
        return false;
    }
    
    return true;
};
</script>

</body>
</html>

<?php include '../includes/footer2.php'; ?>