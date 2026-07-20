<?php
// admin/businesses/edit.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch business with user data (including owner name)
$stmt = mysqli_prepare($conn, "
    SELECT b.*, u.email, u.phone as user_phone, u.status as user_status, u.full_name as owner_name
    FROM businesses b
    JOIN users u ON b.user_id = u.user_id
    WHERE b.business_id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$business) {
    $_SESSION['flash_message'] = "Business not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Owner details
    $owner_name     = trim($_POST['owner_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $user_status    = $_POST['user_status'] ?? 'active';
    
    // Business details
    $business_name  = trim($_POST['business_name'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $city           = trim($_POST['city'] ?? '');
    $location       = trim($_POST['location'] ?? '');
    $business_hours = trim($_POST['business_hours'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $is_verified    = isset($_POST['is_verified']) ? 1 : 0;

    // Validation
    if (empty($owner_name) || empty($business_name) || empty($address) || empty($email) || empty($phone)) {
        $error = "Owner name, business name, address, email and phone are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
        $error = "Invalid Tanzanian phone number. Use 0712345678 or +255712345678.";
    } else {
        // Check email uniqueness (exclude current user)
        $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        mysqli_stmt_bind_param($stmt, 'si', $email, $business['user_id']);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $error = "Email already registered to another account.";
        } else {
            // Check phone uniqueness
            $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE phone = ? AND user_id != ?");
            mysqli_stmt_bind_param($stmt, 'si', $phone, $business['user_id']);
            mysqli_stmt_execute($stmt);
            $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($exists) {
                $error = "Phone number already registered to another account.";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);
                $all_ok = true;

                // Update users table (owner name, email, phone, status)
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE user_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $owner_name, $email, $phone, $user_status, $business['user_id']);
                if (!mysqli_stmt_execute($stmt)) $all_ok = false;
                mysqli_stmt_close($stmt);

                // Update businesses table
                $stmt = mysqli_prepare($conn, "UPDATE businesses SET 
                    business_name = ?, address = ?, city = ?, location = ?, phone = ?, 
                    business_hours = ?, description = ?, is_verified = ? 
                    WHERE business_id = ?");
                mysqli_stmt_bind_param($stmt, 'sssssssii', 
                    $business_name, $address, $city, $location, $phone, 
                    $business_hours, $description, $is_verified, $business_id);
                if (!mysqli_stmt_execute($stmt)) $all_ok = false;
                mysqli_stmt_close($stmt);

                if ($all_ok) {
                    mysqli_commit($conn);
                    $_SESSION['flash_message'] = "Business and owner details updated successfully.";
                    $_SESSION['flash_type'] = 'success';
                    header("Location: view.php?id=$business_id");
                    exit();
                } else {
                    mysqli_rollback($conn);
                    $error = "Failed to update. Please try again.";
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
    <title>Edit Business | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-1px); }
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
            max-width: 1000px;
            margin: 0 auto;
            margin-bottom: 1.5rem;
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
        textarea.form-control { resize: vertical; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
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
            margin-top: 0.5rem;
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
            .form-row { grid-template-columns: 1fr; }
            .admin-content { padding: 1rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Business & Owner</h1>
        <a href="view.php?id=<?= $business_id ?>" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Business</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Owner Information Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-tie"></i> Owner Information
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Full Name <span class="required-star">*</span></label>
                    <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($business['owner_name']) ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email <span class="required-star">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($business['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number <span class="required-star">*</span></label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($business['phone']) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="user_status" class="form-control">
                        <option value="active" <?= $business['user_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $business['user_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <small>Inactive users cannot log in to their account.</small>
                </div>
            </div>
        </div>

        <!-- Business Information Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-store"></i> Business Information
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Business Name <span class="required-star">*</span></label>
                    <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($business['business_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Address <span class="required-star">*</span></label>
                    <textarea name="address" class="form-control" rows="2" required><?= htmlspecialchars($business['address']) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($business['city']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Location / Area</label>
                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($business['location']) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Business Hours</label>
                    <input type="text" name="business_hours" class="form-control" value="<?= htmlspecialchars($business['business_hours']) ?>" placeholder="e.g., Mon-Fri 9am-6pm, Sat 10am-4pm">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($business['description']) ?></textarea>
                </div>
                <div class="checkbox-group">
                    <input type="checkbox" name="is_verified" id="is_verified" value="1" <?= $business['is_verified'] ? 'checked' : '' ?>>
                    <label for="is_verified">Verified Business (trusted badge)</label>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="card">
            <div class="card-body">
                <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Save All Changes</button>
            </div>
        </div>
    </form>
</div>

<script>
    // Optional: confirm on status change (optional)
    const userStatusSelect = document.querySelector('select[name="user_status"]');
    const originalUserStatus = userStatusSelect.value;
    userStatusSelect.addEventListener('change', function() {
        if (this.value !== originalUserStatus) {
            if (this.value === 'inactive') {
                if (!confirm('Deactivating this account will prevent the business owner from logging in. Continue?')) {
                    this.value = originalUserStatus;
                }
            } else if (this.value === 'active') {
                if (!confirm('Activating this account will allow the business owner to log in. Continue?')) {
                    this.value = originalUserStatus;
                }
            }
        }
    });
</script>
</body>
</html>