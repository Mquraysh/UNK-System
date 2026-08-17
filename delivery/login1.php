<?php
// delivery/login.php - DELIVERY AGENT LOGIN (with Remember Me & Forgot Password)
require_once '../config/database.php';

session_start();

// If already logged in as delivery, redirect to dashboard
if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'delivery') {
    header("Location: das/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Prepare statement to fetch delivery agent
    $stmt = mysqli_prepare($conn, "SELECT u.*, d.agent_id, d.first_name, d.last_name, d.status as agent_status 
                                   FROM users u 
                                   LEFT JOIN delivery_agents d ON u.user_id = d.user_id 
                                   WHERE u.email = ? AND u.role = 'delivery'");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Validate credentials and status
    if ($user && $user['status'] == 'active' && $user['agent_status'] == 'active' && password_verify($password, $user['password_hash'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = 'delivery';
        $_SESSION['agent_id'] = $user['agent_id'];
        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        // Handle "Remember Me" – set session cookie lifetime
        if ($remember) {
            // Extend session cookie lifetime to 30 days (86400 * 30)
            ini_set('session.cookie_lifetime', 86400 * 30);
            session_regenerate_id(true);
        } else {
            // Default session lifetime (until browser closes)
            ini_set('session.cookie_lifetime', 0);
        }
        
        // Update last login timestamp
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE user_id = ?");
        mysqli_stmt_bind_param($update_stmt, "i", $user['user_id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
        
        header("Location: das/dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password!";
    }
}

include '../includes/header.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Login - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        
        .login-container {
            max-width: 450px;
            margin: 3rem auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .card-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .card-header h2 i {
            color: #e67e22;
        }
        
        .card-header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
            font-size: 13px;
        }
        
        .form-group label i {
            color: #e67e22;
            margin-right: 5px;
        }
        
        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-group i {
            position: absolute;
            left: 12px;
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 12px 12px 38px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        
        .checkbox-group {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            color: #475569;
        }
        
        .forgot-link a {
            color: #e67e22;
            text-decoration: none;
            font-size: 13px;
        }
        
        .forgot-link a:hover {
            text-decoration: underline;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(230,126,34,0.3);
        }
        
        .alert {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .register-link p {
            margin: 5px 0;
            font-size: 13px;
        }
        
        .register-link a {
            color: #e67e22;
            text-decoration: none;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .home-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .home-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .home-link a:hover {
            color: #e67e22;
        }
        
        @media (max-width: 480px) {
            .card-header {
                padding: 25px 20px;
            }
            .card-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-truck"></i> Delivery Login</h2>
            <p>Access your delivery dashboard</p>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>


                <button type="submit" class="btn"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            <div class="register-link">
                <p>Don't have an account? <a href="register.php">Register as Delivery Agent</a></p>
                <p><a href="../customer/login.php">Customer Login</a> | <a href="../business/login.php">Business Login</a></p>
            </div>

            <div class="home-link">
                <a href="../index.php"><i class="fas fa-home"></i> Back to Home</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php include '../includes/footer2.php'; ?>