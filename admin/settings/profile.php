<?php
// admin/settings/profile.php - EDIT ADMIN PROFILE
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$sql = "SELECT full_name, email, phone FROM users WHERE user_id = $user_id";
$res = mysqli_query($conn, $sql);
$admin = mysqli_fetch_assoc($res);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {
        $error = "Full name is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Valid email is required";
    } else {
        $check_sql = "SELECT user_id FROM users WHERE email = '$email' AND user_id != $user_id";
        $check_res = mysqli_query($conn, $check_sql);
        if (mysqli_num_rows($check_res) > 0) {
            $error = "Email already used by another account";
        } else {
            $update = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone' WHERE user_id = $user_id";
            if (mysqli_query($conn, $update)) {
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $success = "Profile updated successfully!";
                $admin['full_name'] = $full_name;
                $admin['email'] = $email;
                $admin['phone'] = $phone;
            } else {
                $error = "Failed to update profile";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - UNK Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        .admin-content { margin-left: 280px; padding: 30px 35px; background: #f1f5f9; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; color: #1e293b; display: flex; align-items: center; gap: 12px; }
        .btn-back { background: #2c3e50; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; }
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; max-width: 1200px; margin: 0 auto; }
        .card-header { padding: 18px 24px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 600; }
        .card-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e293b; }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .btn-save { background: #e67e22; color: white; padding: 10px 20px; border: none; border-radius: 10px; cursor: pointer; width: 100%; font-weight: 600; }
        .alert { padding: 12px; border-radius: 12px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        @media (max-width: 1024px) { .admin-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-user-shield"></i> Admin Profile</h1>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    <div class="card">
        <div class="card-header">Edit Your Profile</div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                </div>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Update Profile</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>