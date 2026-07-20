<?php
// admin/users/view.php - VIEW USER DETAILS 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_admin_id = (int)$_SESSION['user_id'];
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header("Location: index.php");
    exit();
}

// Fetch user from users table
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

// Fetch role-specific data
$role_data = null;
if ($user['role'] === 'customer') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $role_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
} elseif ($user['role'] === 'business') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM businesses WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $role_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    // Fetch business rating from reviews table
    if ($role_data) {
        // First, check if business_id column exists in reviews table
        $check_col = mysqli_query($conn, "SHOW COLUMNS FROM reviews LIKE 'business_id'");
        if (mysqli_num_rows($check_col) > 0) {
            $rating_stmt = mysqli_prepare($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE business_id = ? AND status = 'approved'");
            mysqli_stmt_bind_param($rating_stmt, 'i', $role_data['business_id']);
            mysqli_stmt_execute($rating_stmt);
            $rating_result = mysqli_stmt_get_result($rating_stmt);
            $rating_data = mysqli_fetch_assoc($rating_result);
            mysqli_stmt_close($rating_stmt);
            
            if ($rating_data && $rating_data['avg_rating'] !== null) {
                $role_data['avg_rating'] = round($rating_data['avg_rating'], 1);
                $role_data['review_count'] = $rating_data['review_count'];
            } else {
                $role_data['avg_rating'] = 0;
                $role_data['review_count'] = 0;
            }
        } else {
            // If business_id doesn't exist, try alternative columns
            $check_col2 = mysqli_query($conn, "SHOW COLUMNS FROM reviews LIKE 'product_id'");
            if (mysqli_num_rows($check_col2) > 0) {
                // Check if product table has business_id
                $product_check = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'business_id'");
                if (mysqli_num_rows($product_check) > 0) {
                    $rating_stmt = mysqli_prepare($conn, "SELECT AVG(r.rating) as avg_rating, COUNT(*) as review_count 
                                                         FROM reviews r 
                                                         JOIN products p ON r.product_id = p.product_id 
                                                         WHERE p.business_id = ? AND r.status = 'approved'");
                    mysqli_stmt_bind_param($rating_stmt, 'i', $role_data['business_id']);
                    mysqli_stmt_execute($rating_stmt);
                    $rating_result = mysqli_stmt_get_result($rating_stmt);
                    $rating_data = mysqli_fetch_assoc($rating_result);
                    mysqli_stmt_close($rating_stmt);
                    
                    if ($rating_data && $rating_data['avg_rating'] !== null) {
                        $role_data['avg_rating'] = round($rating_data['avg_rating'], 1);
                        $role_data['review_count'] = $rating_data['review_count'];
                    } else {
                        $role_data['avg_rating'] = 0;
                        $role_data['review_count'] = 0;
                    }
                }
            }
        }
    }
} elseif ($user['role'] === 'delivery') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM delivery_agents WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $role_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// Flash message (from session, e.g., after edit/delete)
$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/admin_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>User Details | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f1f5f9; }
        .admin-content { margin-left:280px; padding:2rem; min-height:100vh; transition:0.3s; }
        @media (max-width:1024px) { .admin-content { margin-left:0; padding:1.25rem; } }
        .page-header {
            display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
            gap:1rem; margin-bottom:1.5rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;
        }
        .page-header h1 {
            font-size:1.8rem; font-weight:700; background:linear-gradient(135deg,#1e293b,#2c3e50);
            -webkit-background-clip:text; background-clip:text; color:transparent;
            display:flex; align-items:center; gap:0.75rem;
        }
        .page-header h1 i { color:#e67e22; }
        .btn-back { background:#64748b; color:white; padding:0.5rem 1rem; border-radius:2rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.5rem; }
        .btn-back:hover { background:#475569; transform:translateY(-1px); }
        .btn-sm { display:inline-flex; align-items:center; gap:0.5rem; padding:0.5rem 1rem; border-radius:2rem; text-decoration:none; font-size:0.8rem; transition:0.2s; }
        .btn-edit { background:#f59e0b; color:white; }
        .btn-edit:hover { background:#d97706; transform:translateY(-1px); }
        .btn-delete { background:#ef4444; color:white; }
        .btn-delete:hover { background:#dc2626; transform:translateY(-1px); }
        .btn-delete.disabled { background:#cbd5e1; cursor:not-allowed; pointer-events:none; }
        .alert {
            padding:0.75rem 1rem;
            border-radius:0.75rem;
            margin-bottom:1.5rem;
            border-left:4px solid;
        }
        .alert-success { background:#e6f7ec; color:#0a5c3e; border-left-color:#10b981; }
        .alert-danger { background:#fee2e2; color:#991b1b; border-left-color:#ef4444; }
        .info-card { background:white; border-radius:1.25rem; border:1px solid #eef2f8; overflow:hidden; margin-bottom:1.5rem; }
        .card-header { padding:1rem 1.5rem; background:#fafcff; border-bottom:1px solid #f0f2f5; font-weight:700; display:flex; align-items:center; gap:0.5rem; }
        .card-body { padding:1.25rem 1.5rem; }
        .info-row { display:flex; padding:0.6rem 0; border-bottom:1px solid #f1f5f9; }
        .info-label { width:140px; font-weight:600; color:#64748b; font-size:0.8rem; }
        .info-value { flex:1; color:#1e293b; font-size:0.9rem; }
        .role-badge, .status-badge { display:inline-block; padding:0.2rem 0.7rem; border-radius:2rem; font-size:0.7rem; font-weight:600; }
        .role-admin { background:#e0e7ff; color:#3730a3; }
        .role-business { background:#fed7aa; color:#c2410c; }
        .role-customer { background:#d1fae5; color:#059669; }
        .role-delivery { background:#dbeafe; color:#2563eb; }
        .status-active { background:#d1fae5; color:#059669; }
        .status-inactive { background:#fef3c7; color:#d97706; }
        .rating-stars { color:#f59e0b; font-size:0.9rem; letter-spacing:1px; }
        .rating-stars .empty { color:#d1d5db; }
        @media (max-width:640px) {
            .admin-content { padding:1rem; }
            .info-row { flex-direction:column; }
            .info-label { width:100%; margin-bottom:0.25rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <!-- Flash Message -->
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> User Details</h1>
        <div style="display:flex; gap:0.75rem;">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="edit.php?id=<?= $user_id ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
            <?php if ($user_id != $current_admin_id): ?>
                <a href="delete.php?id=<?= $user_id ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this user? This action cannot be undone.')">
                    <i class="fas fa-trash-alt"></i> Delete
                </a>
            <?php else: ?>
                <span class="btn-sm btn-delete disabled" title="You cannot delete your own account">
                    <i class="fas fa-trash-alt"></i> Delete
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Basic User Info -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-user"></i> Account Information</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">User ID</div><div class="info-value"><?= $user['user_id'] ?></div></div>
            <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($user['full_name']) ?></div></div>
            <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($user['email']) ?></div></div>
            <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($user['phone']) ?></div></div>
            <div class="info-row"><div class="info-label">Role</div><div class="info-value"><span class="role-badge role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span></div></div>
            <div class="info-row"><div class="info-label">Status</div><div class="info-value"><span class="status-badge status-<?= $user['status'] ?>"><?= ucfirst($user['status']) ?></span></div></div>
            <div class="info-row"><div class="info-label">Last Login</div><div class="info-value"><?= $user['last_login'] ? date('F j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></div></div>
            <div class="info-row"><div class="info-label">Registered</div><div class="info-value"><?= date('F j, Y g:i A', strtotime($user['created_at'])) ?></div></div>
        </div>
    </div>

    <!-- Role-Specific Information -->
    <?php if ($role_data): ?>
    <div class="info-card">
        <div class="card-header"><i class="fas fa-<?= $user['role'] === 'customer' ? 'user' : ($user['role'] === 'business' ? 'store' : 'truck') ?>"></i> <?= ucfirst($user['role']) ?> Details</div>
        <div class="card-body">
            <?php if ($user['role'] === 'customer'): ?>
                <div class="info-row"><div class="info-label">Customer ID</div><div class="info-value"><?= $role_data['customer_id'] ?></div></div>
                <div class="info-row"><div class="info-label">First Name</div><div class="info-value"><?= htmlspecialchars($role_data['first_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Last Name</div><div class="info-value"><?= htmlspecialchars($role_data['last_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Saved Address</div><div class="info-value"><?= nl2br(htmlspecialchars($role_data['saved_address'] ?? 'Not set')) ?></div></div>
                <div class="info-row"><div class="info-label">City</div><div class="info-value"><?= htmlspecialchars($role_data['city'] ?? 'Not set') ?></div></div>
            <?php elseif ($user['role'] === 'business'): ?>
                <div class="info-row"><div class="info-label">Business ID</div><div class="info-value"><?= $role_data['business_id'] ?></div></div>
                <div class="info-row"><div class="info-label">Business Name</div><div class="info-value"><?= htmlspecialchars($role_data['business_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Address</div><div class="info-value"><?= nl2br(htmlspecialchars($role_data['address'])) ?></div></div>
                <div class="info-row"><div class="info-label">City</div><div class="info-value"><?= htmlspecialchars($role_data['city']) ?></div></div>
                <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($role_data['phone']) ?></div></div>
                <div class="info-row"><div class="info-label">Verified</div><div class="info-value"><?= $role_data['is_verified'] ? '<span class="status-badge status-active">Yes</span>' : '<span class="status-badge status-inactive">No</span>' ?></div></div>
                <div class="info-row"><div class="info-label">Rating</div>
                    <div class="info-value">
                        <?php if (isset($role_data['avg_rating']) && $role_data['avg_rating'] > 0): ?>
                            <span class="rating-stars">
                                <?php 
                                $fullStars = floor($role_data['avg_rating']);
                                $halfStar = ($role_data['avg_rating'] - $fullStars) >= 0.5;
                                for ($i = 1; $i <= 5; $i++):
                                    if ($i <= $fullStars):
                                        echo '<i class="fas fa-star"></i>';
                                    elseif ($halfStar && $i == $fullStars + 1):
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                        $halfStar = false;
                                    else:
                                        echo '<i class="fas fa-star empty"></i>';
                                    endif;
                                endfor;
                                ?>
                            </span>
                            <span style="font-weight:600; color:#1e293b; margin-left:0.5rem;"><?= number_format($role_data['avg_rating'], 1) ?></span>
                            <span style="color:#94a3b8; font-size:0.75rem;">(<?= $role_data['review_count'] ?? 0 ?> reviews)</span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">No ratings yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($user['role'] === 'delivery'): ?>
                <div class="info-row"><div class="info-label">Agent ID</div><div class="info-value"><?= $role_data['agent_id'] ?></div></div>
                <div class="info-row"><div class="info-label">First Name</div><div class="info-value"><?= htmlspecialchars($role_data['first_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Last Name</div><div class="info-value"><?= htmlspecialchars($role_data['last_name']) ?></div></div>
                <div class="info-row"><div class="info-label">Vehicle Type</div><div class="info-value"><?= htmlspecialchars($role_data['vehicle_type']) ?></div></div>
                <div class="info-row"><div class="info-label">Registration</div><div class="info-value"><?= htmlspecialchars($role_data['vehicle_registration']) ?></div></div>
                <div class="info-row"><div class="info-label">Total Deliveries</div><div class="info-value"><?= $role_data['total_deliveries'] ?? 0 ?></div></div>
                <div class="info-row"><div class="info-label">Rating</div>
                    <div class="info-value">
                        <?php if (isset($role_data['rating']) && $role_data['rating'] > 0): ?>
                            <span class="rating-stars">
                                <?php 
                                $fullStars = floor($role_data['rating']);
                                $halfStar = ($role_data['rating'] - $fullStars) >= 0.5;
                                for ($i = 1; $i <= 5; $i++):
                                    if ($i <= $fullStars):
                                        echo '<i class="fas fa-star"></i>';
                                    elseif ($halfStar && $i == $fullStars + 1):
                                        echo '<i class="fas fa-star-half-alt"></i>';
                                        $halfStar = false;
                                    else:
                                        echo '<i class="fas fa-star empty"></i>';
                                    endif;
                                endfor;
                                ?>
                            </span>
                            <span style="font-weight:600; color:#1e293b; margin-left:0.5rem;"><?= number_format($role_data['rating'], 1) ?></span>
                        <?php else: ?>
                            <span style="color:#94a3b8;">No ratings yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>