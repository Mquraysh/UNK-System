<?php
// admin/businesses/view.php - PROFESSIONAL BUSINESS DETAILS (WITH OWNER NAME & LOGO)
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

// Fetch business with user details (including owner name, last login)
$stmt = mysqli_prepare($conn, "
    SELECT b.*, u.full_name as owner_name, u.email, u.phone as user_phone, u.status as user_status, 
           u.created_at as user_registered, u.last_login
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

// Get statistics
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as products FROM products WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$products_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['products'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as orders FROM orders WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$orders_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['orders'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT AVG(rating) as avg_rating, COUNT(*) as reviews FROM reviews r JOIN products p ON r.product_id = p.product_id WHERE p.business_id = ? AND r.status = 'approved'");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$review_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$avg_rating = round($review_data['avg_rating'] ?? 0, 1);
$reviews_count = (int)($review_data['reviews'] ?? 0);

// Helper for logo image
function getLogoUrl($logo_path) {
    if (empty($logo_path)) return null;
    if (preg_match('/^https?:\/\//i', $logo_path)) return $logo_path;
    if ($logo_path[0] === '/') return $logo_path;
    return '../../' . ltrim($logo_path, './');
}

$logo_url = getLogoUrl($business['logo_url'] ?? '');

// Flash message
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
    <title>Business Details | Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: 0.2s;
        }
        .btn-back:hover { background: #475569; transform: translateY(-1px); }
        .btn-sm {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            font-size: 0.8rem;
            transition: 0.2s;
        }
        .btn-sm:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid;
        }
        .alert-success {
            background: #e6f7ec;
            color: #0a5c3e;
            border-left-color: #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left-color: #ef4444;
        }
        .info-card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #eef2f8;
            overflow: hidden;
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
        .card-body { padding: 1.25rem 1.5rem; }
        .info-row {
            display: flex;
            padding: 0.6rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-label {
            width: 150px;
            font-weight: 600;
            color: #64748b;
            font-size: 0.8rem;
        }
        .info-value {
            flex: 1;
            color: #1e293b;
            font-size: 0.9rem;
            word-break: break-word;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-verified { background: #d1fae5; color: #059669; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-active { background: #d1fae5; color: #059669; }
        .badge-inactive { background: #fee2e2; color: #dc2626; }
        .logo-preview {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            object-fit: cover;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }
        @media (max-width: 640px) {
            .admin-content { padding: 1rem; }
            .info-row { flex-direction: column; }
            .info-label { width: 100%; margin-bottom: 0.25rem; }
        }
    </style>
</head>
<body>
<div class="admin-content">
    <div class="page-header">
        <h1><i class="fas fa-store"></i> Business Details</h1>
        <div style="display: flex; gap: 0.75rem;">
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to List</a>
            <a href="edit.php?id=<?= $business_id ?>" class="btn-sm btn-edit"><i class="fas fa-edit"></i> Edit</a>
            <a href="delete.php?id=<?= $business_id ?>" class="btn-sm btn-delete" onclick="return confirm('⚠️ Permanently delete this business? This will remove all products, orders, and reviews. This action cannot be undone.')">
                <i class="fas fa-trash-alt"></i> Delete
            </a>
        </div>
    </div>

    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Owner Information Card -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-user-tie"></i> Owner Information</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">Owner Name</div><div class="info-value"><?= htmlspecialchars($business['owner_name']) ?></div></div>
            <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($business['email']) ?></div></div>
            <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?= htmlspecialchars($business['user_phone']) ?></div></div>
            <div class="info-row"><div class="info-label">Account Status</div><div class="info-value"><span class="badge badge-<?= $business['user_status'] ?>"><?= ucfirst($business['user_status']) ?></span></div></div>
            <div class="info-row"><div class="info-label">Last Login</div><div class="info-value"><?= $business['last_login'] ? date('F j, Y g:i A', strtotime($business['last_login'])) : 'Never' ?></div></div>
            <div class="info-row"><div class="info-label">Registered</div><div class="info-value"><?= date('F j, Y g:i A', strtotime($business['user_registered'])) ?></div></div>
        </div>
    </div>

    <!-- Business Information Card -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-store"></i> Business Information</div>
        <div class="card-body">
            <?php if ($logo_url): ?>
            <div class="info-row">
                <div class="info-label">Logo</div>
                <div class="info-value"><img src="<?= htmlspecialchars($logo_url) ?>" class="logo-preview" alt="Business Logo" onerror="this.style.display='none'"></div>
            </div>
            <?php endif; ?>
            <div class="info-row"><div class="info-label">Business ID</div><div class="info-value"><?= $business['business_id'] ?></div></div>
            <div class="info-row"><div class="info-label">Business Name</div><div class="info-value"><?= htmlspecialchars($business['business_name']) ?></div></div>
            <div class="info-row"><div class="info-label">Address</div><div class="info-value"><?= nl2br(htmlspecialchars($business['address'])) ?></div></div>
            <div class="info-row"><div class="info-label">City</div><div class="info-value"><?= htmlspecialchars($business['city']) ?></div></div>
            <div class="info-row"><div class="info-label">Location / Area</div><div class="info-value"><?= htmlspecialchars($business['location'] ?: 'Not set') ?></div></div>
            <div class="info-row"><div class="info-label">Phone (Business)</div><div class="info-value"><?= htmlspecialchars($business['phone']) ?></div></div>
            <div class="info-row"><div class="info-label">Business Hours</div><div class="info-value"><?= nl2br(htmlspecialchars($business['business_hours'] ?? 'Not set')) ?></div></div>
            <div class="info-row"><div class="info-label">Description</div><div class="info-value"><?= nl2br(htmlspecialchars($business['description'] ?? 'No description')) ?></div></div>
            <div class="info-row"><div class="info-label">Verification</div><div class="info-value"><span class="badge badge-<?= $business['is_verified'] ? 'verified' : 'pending' ?>"><?= $business['is_verified'] ? 'Verified' : 'Pending' ?></span></div></div>
            <div class="info-row"><div class="info-label">Account Status</div><div class="info-value"><span class="badge badge-<?= $business['user_status'] ?>"><?= ucfirst($business['user_status']) ?></span></div></div>
            <div class="info-row"><div class="info-label">Registered On</div><div class="info-value"><?= date('F j, Y g:i A', strtotime($business['created_at'])) ?></div></div>
        </div>
    </div>

    <!-- Statistics Card -->
    <div class="info-card">
        <div class="card-header"><i class="fas fa-chart-line"></i> Business Statistics</div>
        <div class="card-body">
            <div class="info-row"><div class="info-label">Total Products</div><div class="info-value"><?= number_format($products_count) ?></div></div>
            <div class="info-row"><div class="info-label">Total Orders</div><div class="info-value"><?= number_format($orders_count) ?></div></div>
            <div class="info-row"><div class="info-label">Customer Reviews</div><div class="info-value"><?= number_format($reviews_count) ?> <?= $reviews_count ? "(Avg: {$avg_rating} ★)" : '' ?></div></div>
        </div>
    </div>
</div>
</body>
</html>