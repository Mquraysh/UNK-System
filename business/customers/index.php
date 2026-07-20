<?php
// business/customers/index.php - SHOW ONLY CUSTOMERS WHO PURCHASED (FIXED)
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$business_name = $business['business_name'];

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'total_spent';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$min_orders = isset($_GET['min_orders']) ? (int)$_GET['min_orders'] : 1;
$min_spent = isset($_GET['min_spent']) ? (int)$_GET['min_spent'] : 0;
$segment = isset($_GET['segment']) ? $_GET['segment'] : 'all';

// ============================================================
// BUILD QUERY - FIXED: Proper GROUP BY and HAVING
// ============================================================
$sql = "SELECT 
        c.customer_id, 
        c.first_name, 
        c.last_name, 
        c.saved_address, 
        c.city,
        u.email, 
        u.phone as customer_phone, 
        u.created_at as registered_date,
        COUNT(o.order_id) as total_orders,
        SUM(o.grand_total) as total_spent,
        MAX(o.order_date) as last_order_date,
        MIN(o.order_date) as first_order_date,
        AVG(o.grand_total) as avg_order_value
FROM customers c
INNER JOIN users u ON c.user_id = u.user_id
INNER JOIN orders o ON c.customer_id = o.customer_id AND o.business_id = '$business_id'
WHERE o.status != 'cancelled'";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (c.first_name LIKE '%$search_escaped%' 
               OR c.last_name LIKE '%$search_escaped%' 
               OR u.email LIKE '%$search_escaped%' 
               OR u.phone LIKE '%$search_escaped%'
               OR c.city LIKE '%$search_escaped%')";
}

$sql .= " GROUP BY c.customer_id";

// Segment filter - using HAVING after GROUP BY
if ($segment == 'new') {
    $sql .= " HAVING total_orders = 1";
} elseif ($segment == 'regular') {
    $sql .= " HAVING total_orders BETWEEN 2 AND 5";
} elseif ($segment == 'vip') {
    $sql .= " HAVING total_orders >= 6";
}

// Min orders filter
if ($min_orders > 0) {
    // If segment already has HAVING, add AND, else add HAVING
    if (strpos($sql, 'HAVING') !== false) {
        $sql .= " AND total_orders >= $min_orders";
    } else {
        $sql .= " HAVING total_orders >= $min_orders";
    }
}

// Min spent filter - must come after HAVING
if ($min_spent > 0) {
    if (strpos($sql, 'HAVING') !== false) {
        $sql .= " AND total_spent >= $min_spent";
    } else {
        $sql .= " HAVING total_spent >= $min_spent";
    }
}

// Apply sorting
switch($sort) {
    case 'name':
        $sql .= " ORDER BY c.first_name $order, c.last_name $order";
        break;
    case 'total_orders':
        $sql .= " ORDER BY total_orders $order";
        break;
    case 'total_spent':
        $sql .= " ORDER BY total_spent $order";
        break;
    case 'last_order':
        $sql .= " ORDER BY last_order_date $order";
        break;
    case 'avg_order':
        $sql .= " ORDER BY avg_order_value $order";
        break;
    case 'registered':
        $sql .= " ORDER BY registered_date $order";
        break;
    default:
        $sql .= " ORDER BY total_spent DESC";
}

// Debug: Uncomment to see the actual SQL
// echo "<pre>$sql</pre>";

$result = mysqli_query($conn, $sql);
$customers = [];
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        $customers[] = $row;
    }
} else {
    // If query fails, show empty array
    $customers = [];
}

// ============================================================
// CALCULATE STATISTICS
// ============================================================
$total_customers = count($customers);
$total_revenue = array_sum(array_column($customers, 'total_spent'));
$total_orders = array_sum(array_column($customers, 'total_orders'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Customer segments
$new_customers = 0;
$regular_customers = 0;
$vip_customers = 0;
foreach($customers as $c) {
    if($c['total_orders'] == 1) $new_customers++;
    elseif($c['total_orders'] <= 5) $regular_customers++;
    else $vip_customers++;
}

// Get top 5 customers
$top_customers = array_slice($customers, 0, 5);

// Flash message
$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Customers - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .business-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
        }
        
        .page-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        .page-header .customer-count {
            background: #e67e22;
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 0.85rem 1.1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #ecfdf5; color: #065f46; border-left-color: #10b981; }
        .alert-danger { background: #fef2f2; color: #991b1b; border-left-color: #ef4444; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: #e67e22;
        }
        .stat-label {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }
        .stat-icon {
            font-size: 1.2rem;
            color: #e67e22;
            margin-bottom: 0.3rem;
        }
        
        .segments-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .segment-card {
            background: white;
            border-radius: 1rem;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        .segment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: #e67e22;
        }
        .segment-card.active {
            border-color: #e67e22;
            background: #fdf2e9;
        }
        .segment-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .segment-new .segment-icon { background: #dbeafe; color: #3b82f6; }
        .segment-regular .segment-icon { background: #d1fae5; color: #10b981; }
        .segment-vip .segment-icon { background: #fef3c7; color: #f59e0b; }
        .segment-info h4 { font-size: 0.75rem; font-weight: 600; color: #64748b; }
        .segment-info h3 { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
        .segment-info p { font-size: 0.6rem; color: #94a3b8; }
        .segment-count { 
            margin-left: auto; 
            font-size: 0.6rem; 
            background: #f1f5f9; 
            padding: 0.15rem 0.5rem; 
            border-radius: 1rem;
            color: #64748b;
        }
        
        .filters-bar {
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .filters-form {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { flex: 1; min-width: 140px; }
        .filter-group label {
            display: block;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .filter-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .filter-input:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn-filter {
            background: #e67e22;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-filter:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-reset {
            background: #f1f5f9;
            color: #64748b;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s;
        }
        .btn-reset:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        
        .table-card {
            background: white;
            border-radius: 1.25rem;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .table-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .table-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-header h3 i { color: #e67e22; }
        .table-header .badge-count {
            background: #e2e8f0;
            padding: 0.15rem 0.6rem;
            border-radius: 2rem;
            font-size: 0.65rem;
            font-weight: 600;
            color: #64748b;
        }
        
        .table-container { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .data-table th {
            padding: 0.6rem 0.8rem;
            text-align: left;
            font-weight: 600;
            color: #64748b;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e2e8f0;
            background: #f8fafc;
            cursor: pointer;
            transition: color 0.2s;
            white-space: nowrap;
        }
        .data-table th:hover { color: #e67e22; }
        .data-table td {
            padding: 0.6rem 0.8rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .data-table tr:hover td { background: #fffbeb; }
        
        .customer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
            background: linear-gradient(135deg, #e67e22, #d35400);
        }
        
        .badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 2rem;
            font-size: 0.6rem;
            font-weight: 600;
        }
        .badge-new { background: #dbeafe; color: #2563eb; }
        .badge-regular { background: #d1fae5; color: #059669; }
        .badge-vip { background: #fef3c7; color: #d97706; }
        
        .btn-sm {
            padding: 0.2rem 0.5rem;
            border-radius: 0.4rem;
            text-decoration: none;
            font-size: 0.6rem;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-sm:hover { transform: translateY(-2px); }
        .btn-view { background: #3498db; color: white; }
        .btn-view:hover { background: #2980b9; }
        .btn-orders { background: #e67e22; color: white; }
        .btn-orders:hover { background: #d35400; }
        
        .sort-icon { font-size: 0.55rem; margin-left: 0.2rem; opacity: 0.5; }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 0.75rem;
            opacity: 0.3;
        }
        .empty-state h3 {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 0.3rem;
        }
        .empty-state p { font-size: 0.85rem; }
        
        .top-customers {
            margin-top: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 1024px) {
            .business-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .business-content { padding: 0.9rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .segments-grid { grid-template-columns: 1fr; }
            .filters-form { flex-direction: column; }
            .filter-group { min-width: 100%; }
            .filter-buttons { width: 100%; }
            .filter-buttons .btn-filter, .filter-buttons .btn-reset { flex: 1; justify-content: center; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .data-table { font-size: 0.7rem; }
            .data-table th, .data-table td { padding: 0.4rem 0.5rem; }
        }
        @media (max-width: 480px) {
            .business-content { padding: 0.5rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .table-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-users"></i> My Customers
                <span class="customer-count"><?php echo $total_customers; ?></span>
            </h1>
            <p>Customers who have purchased from your business</p>
        </div>
        <a href="export.php" class="btn btn-success">
            <i class="fas fa-download"></i> Export Customers
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash_message); ?>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-number"><?php echo $total_customers; ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <div class="stat-number">TSh <?php echo number_format($total_revenue); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="stat-number"><?php echo $total_orders; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-bar"></i></div>
            <div class="stat-number">TSh <?php echo number_format($avg_order_value); ?></div>
            <div class="stat-label">Avg Order Value</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-number">TSh <?php echo $total_customers > 0 ? number_format($total_revenue / $total_customers) : 0; ?></div>
            <div class="stat-label">Avg per Customer</div>
        </div>
    </div>

    <!-- Customer Segments -->
    <div class="segments-grid">
        <a href="?segment=new<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="segment-card segment-new <?php echo $segment == 'new' ? 'active' : ''; ?>">
            <div class="segment-icon"><i class="fas fa-seedling"></i></div>
            <div class="segment-info">
                <h4>New Customers</h4>
                <h3><?php echo $new_customers; ?></h3>
                <p>1 order only</p>
            </div>
            <span class="segment-count"><?php echo $new_customers > 0 ? 'View →' : ''; ?></span>
        </a>
        <a href="?segment=regular<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="segment-card segment-regular <?php echo $segment == 'regular' ? 'active' : ''; ?>">
            <div class="segment-icon"><i class="fas fa-user-friends"></i></div>
            <div class="segment-info">
                <h4>Regular Customers</h4>
                <h3><?php echo $regular_customers; ?></h3>
                <p>2-5 orders</p>
            </div>
            <span class="segment-count"><?php echo $regular_customers > 0 ? 'View →' : ''; ?></span>
        </a>
        <a href="?segment=vip<?php echo $search ? '&search='.urlencode($search) : ''; ?>" class="segment-card segment-vip <?php echo $segment == 'vip' ? 'active' : ''; ?>">
            <div class="segment-icon"><i class="fas fa-crown"></i></div>
            <div class="segment-info">
                <h4>VIP Customers</h4>
                <h3><?php echo $vip_customers; ?></h3>
                <p>6+ orders</p>
            </div>
            <span class="segment-count"><?php echo $vip_customers > 0 ? 'View →' : ''; ?></span>
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar">
        <form method="GET" class="filters-form">
            <input type="hidden" name="segment" value="<?php echo $segment; ?>">
            <div class="filter-group">
                <label><i class="fas fa-search"></i> Search</label>
                <input type="text" name="search" class="filter-input" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-shopping-cart"></i> Min Orders</label>
                <input type="number" name="min_orders" class="filter-input" placeholder="Min orders" value="<?php echo $min_orders ?: ''; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-money-bill"></i> Min Spent (TSh)</label>
                <input type="number" name="min_spent" class="filter-input" placeholder="Min spent" value="<?php echo $min_spent ?: ''; ?>">
            </div>
            <div class="filter-buttons">
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
                <a href="index.php" class="btn-reset"><i class="fas fa-undo-alt"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-list"></i> Customer List</h3>
            <span class="badge-count"><?php echo $total_customers; ?> customers found</span>
        </div>
        <div class="table-container">
            <?php if (empty($customers)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No Customers Yet</h3>
                    <p>You haven't had any customers purchase from your business yet.</p>
                    <a href="../products/index.php" class="btn" style="background:#e67e22; color:white; margin-top:0.5rem; display:inline-flex;">
                        <i class="fas fa-plus"></i> Start Selling
                    </a>
                </div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name', 'order' => ($sort == 'name' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>'">
                            Customer <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th>Contact</th>
                        <th>Location</th>
                        <th onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total_orders', 'order' => ($sort == 'total_orders' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>'">
                            Orders <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => 'total_spent', 'order' => ($sort == 'total_spent' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>'">
                            Total Spent <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['sort' => 'last_order', 'order' => ($sort == 'last_order' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>'">
                            Last Order <i class="fas fa-sort sort-icon"></i>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <div class="customer-avatar">
                                    <?php echo strtoupper(substr($customer['first_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:0.85rem;">
                                        <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                    </div>
                                    <div style="font-size:0.6rem; color:#94a3b8;">
                                        <i class="far fa-calendar-alt"></i> Joined <?php echo date('M Y', strtotime($customer['registered_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size:0.75rem;">
                                <div><i class="fas fa-envelope" style="color:#94a3b8; width:14px;"></i> <?php echo htmlspecialchars($customer['email']); ?></div>
                                <div><i class="fas fa-phone" style="color:#94a3b8; width:14px;"></i> <?php echo htmlspecialchars($customer['customer_phone'] ?? 'N/A'); ?></div>
                            </div>
                        </td>
                        <td>
                            <?php if(!empty($customer['city'])): ?>
                                <span style="font-size:0.75rem;">
                                    <i class="fas fa-map-marker-alt" style="color:#94a3b8;"></i> <?php echo htmlspecialchars($customer['city']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:0.7rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php 
                                echo $customer['total_orders'] == 1 ? 'badge-new' : ($customer['total_orders'] <= 5 ? 'badge-regular' : 'badge-vip'); 
                            ?>">
                                <?php echo $customer['total_orders']; ?> orders
                            </span>
                        </td>
                        <td><strong style="color:#e67e22;">TSh <?php echo number_format($customer['total_spent']); ?></strong></td>
                        <td style="font-size:0.75rem; color:#64748b;">
                            <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.3rem; flex-wrap:wrap;">
                                <a href="view.php?id=<?php echo $customer['customer_id']; ?>" class="btn-sm btn-view" title="View Details">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="orders.php?id=<?php echo $customer['customer_id']; ?>" class="btn-sm btn-orders" title="View Orders">
                                    <i class="fas fa-shopping-cart"></i> Orders
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Customers -->
    <?php if (!empty($top_customers)): ?>
    <div class="table-card top-customers">
        <div class="table-header">
            <h3><i class="fas fa-trophy" style="color:#f59e0b;"></i> Top 5 Customers by Spending</h3>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Avg Order</th>
                        <th>Last Order</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($top_customers as $customer): 
                    ?>
                    <tr>
                        <td>
                            <?php if($rank == 1): ?>
                                <i class="fas fa-crown" style="color: #f59e0b; font-size:1.1rem;"></i>
                            <?php elseif($rank == 2): ?>
                                <i class="fas fa-medal" style="color: #94a3b8;"></i>
                            <?php elseif($rank == 3): ?>
                                <i class="fas fa-medal" style="color: #cd7f32;"></i>
                            <?php else: ?>
                                <?php echo $rank; ?>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong></td>
                        <td><?php echo $customer['total_orders']; ?></td>
                        <td><strong style="color:#e67e22;">TSh <?php echo number_format($customer['total_spent']); ?></strong></td>
                        <td>TSh <?php echo number_format($customer['avg_order_value']); ?></td>
                        <td style="font-size:0.7rem; color:#64748b;"><?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?></td>
                    </tr>
                    <?php 
                    $rank++;
                    endforeach; 
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var links = document.querySelectorAll('.sidebar-menu a');
    for (var i = 0; i < links.length; i++) {
        if (links[i].getAttribute('href') === '../customers/index.php' || 
            links[i].getAttribute('href') === 'index.php') {
            links[i].classList.add('active');
        }
    }
});
</script>
</body>
</html>