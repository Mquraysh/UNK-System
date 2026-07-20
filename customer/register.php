<?php
// customer/register.php - Customer Registration (Kama business registration)
require_once '../config/database.php';

session_start();

// Redirect if already logged in as customer
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'customer') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect and trim data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? 'Dar es Salaam');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Store for repopulation
    $form_data = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
        'address'    => $address,
        'city'       => $city
    ];

    // Validation
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name))  $errors[] = "Last name is required";
    if (empty($email))      $errors[] = "Email is required";
    if (empty($phone))      $errors[] = "Phone number is required";
    if (empty($address))    $errors[] = "Address is required";
    if (empty($password))   $errors[] = "Password is required";

    if (empty($errors)) {
        // Email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address!";
        }
        // Tanzanian phone number (0712345678 or +255712345678)
        elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
            $error = "Please enter a valid Tanzanian phone number (e.g., 0712345678 or +255712345678)!";
        }
        elseif ($password !== $confirm) {
            $error = "Passwords do not match!";
        }
        else {
            // Strong password validation
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
            } else {
                // Check if email exists
                $check_email_sql = "SELECT user_id FROM users WHERE email = ?";
                $stmt_email = mysqli_prepare($conn, $check_email_sql);
                mysqli_stmt_bind_param($stmt_email, "s", $email);
                mysqli_stmt_execute($stmt_email);
                mysqli_stmt_store_result($stmt_email);
                $email_exists = mysqli_stmt_num_rows($stmt_email) > 0;
                mysqli_stmt_close($stmt_email);

                if ($email_exists) {
                    $error = "Email already registered! Please login.";
                } else {
                    // Check if phone exists
                    $check_phone_sql = "SELECT user_id FROM users WHERE phone = ?";
                    $stmt_phone = mysqli_prepare($conn, $check_phone_sql);
                    mysqli_stmt_bind_param($stmt_phone, "s", $phone);
                    mysqli_stmt_execute($stmt_phone);
                    mysqli_stmt_store_result($stmt_phone);
                    $phone_exists = mysqli_stmt_num_rows($stmt_phone) > 0;
                    mysqli_stmt_close($stmt_phone);

                    if ($phone_exists) {
                        $error = "Phone number already registered!";
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $full_name = $first_name . ' ' . $last_name;
                        $role = 'customer';
                        $status = 'active';

                        mysqli_begin_transaction($conn);

                        // Insert into users
                        $user_sql = "INSERT INTO users (full_name, email, password_hash, phone, role, status, created_at) 
                                     VALUES (?, ?, ?, ?, ?, ?, NOW())";
                        $stmt_user = mysqli_prepare($conn, $user_sql);
                        mysqli_stmt_bind_param($stmt_user, "ssssss", $full_name, $email, $hashed_password, $phone, $role, $status);
                        $user_result = mysqli_stmt_execute($stmt_user);

                        if ($user_result) {
                            $user_id = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt_user);

                            // Insert into customers
                            $customer_sql = "INSERT INTO customers (user_id, first_name, last_name, saved_address, city, created_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW())";
                            $stmt_cust = mysqli_prepare($conn, $customer_sql);
                            mysqli_stmt_bind_param($stmt_cust, "issss", $user_id, $first_name, $last_name, $address, $city);
                            $customer_result = mysqli_stmt_execute($stmt_cust);
                            mysqli_stmt_close($stmt_cust);

                            if ($customer_result) {
                                mysqli_commit($conn);
                                $_SESSION['registration_success'] = "Registration successful! Please login.";
                                header("Location: login.php");
                                exit();
                            } else {
                                mysqli_rollback($conn);
                                $error = "Registration failed: Could not create customer profile.";
                            }
                        } else {
                            mysqli_rollback($conn);
                            $error = "Registration failed: Could not create user account.";
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
    <title>Customer Registration - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Same styles as business/register.php */
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
        .text-muted { font-size: 11px; color: #7f8c8d; margin-top: 5px; display: block; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn { display: inline-block; padding: 12px 24px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; text-align: center; width: 100%; }
        .btn-primary { background: #e67e22; color: white; }
        .btn-primary:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(230,126,34,0.3); }
        .login-link { margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid #eee; }
        .login-link a { color: #e67e22; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
        .home-link { text-align: center; margin-top: 15px; }
        .home-link a { color: #7f8c8d; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s; }
        .home-link a:hover { color: #e67e22; }
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
            <h2><i class="fas fa-user-plus"></i> Customer Registration</h2>
            <p>Join UNK System and start shopping from Kariakoo's best businesses</p>
        </div>
        <div class="card-body">
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
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
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                    <small class="text-muted">We'll send order confirmations to this email</small>
                </div>
                
                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" placeholder="0712345678" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required>
                    <small class="text-muted">Format: 0712345678 or +255712345678</small>
                </div>

                <div class="form-group">
                    <label>Delivery Address <span class="required">*</span></label>
                    <textarea name="address" rows="3" class="form-control" placeholder="Enter your full delivery address" required><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                    <small class="text-muted">We'll deliver your orders to this address</small>
                </div>
                
                <div class="form-group">
                    <label>City <span class="required">*</span></label>
                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($form_data['city'] ?? 'Dar es Salaam'); ?>" required>
                    <small class="text-muted">Your city for delivery purposes</small>
                </div>
                
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
                    <i class="fas fa-user-plus"></i> Register as Customer
                </button>
                
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p style="margin-top: 8px; font-size: 12px;">
                        <a href="../business/register.php">Register as Business</a> | 
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
// Same password validation as business/register.php
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