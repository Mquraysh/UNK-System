<?php
// admin/users/edit.php - EDIT USER (COMMON FIELDS) – PROFESSIONAL
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch user details
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    $_SESSION['flash_message'] = "User not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';

    // Validation
    if (empty($full_name) || empty($email) || empty($phone)) {
        $error = "Name, email and phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
        $error = "Invalid Tanzanian phone number. Use 0712345678 or +255712345678.";
    } else {
        // Check if email already used by another user
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        mysqli_stmt_bind_param($stmt, 'si', $email, $user_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $error = "Email already registered to another account.";
        } else {
            // Check if phone already used by another user
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE phone = ? AND user_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $phone, $user_id);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($exists) {
                $error = "Phone number already registered to another account.";
            } else {
                // Update user
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $full_name, $email, $phone, $status, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_message'] = "User updated successfully.";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: view.php?id=$user_id");
                    exit();
                } else {
                    $error = "Failed to update user. Please try again.";
                }
                mysqli_stmt_close($stmt);
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
    <title>Edit User | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
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
        .btn-back:hover { background: #475569; transform: translateY(-1px); }
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #fafcff;
            border-bottom: 1px solid #f0f2f5;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header i { color: #e67e22; }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.3rem;
            color: #334155;
        }
        .required-star { color: #e74c3c; margin-left: 0.2rem; }
        .form-control {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            transition: 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 2px rgba(230,126,34,0.1);
        }
        .btn-submit {
            background: #e67e22;
            color: white;
            border: none;
            padding: 0.7rem;
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
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        @media (max-width: 640px) {
            .admin-content { padding: 1rem; }
            .card-body { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit User</h1>
        <a href="view.php?id=<?= $user_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to User</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <i class="fas fa-user"></i> Basic Information
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Full Name <span class="required-star">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email <span class="required-star">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number <span class="required-star">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="0712345678" required>
                    <small style="display: block; margin-top: 0.2rem; color: #64748b;">Format: 0712345678 or +255712345678</small>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status" class="form-control">
                        <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <small style="display: block; margin-top: 0.2rem; color: #64748b;">Inactive users cannot log in.</small>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>