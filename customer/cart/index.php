<?php
// customer/cart/index.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$customer_res = mysqli_query($conn, "SELECT customer_id, saved_address, city FROM customers WHERE user_id = '$user_id'");
if (mysqli_num_rows($customer_res) == 0) {
    header("Location: ../register.php");
    exit();
}
$customer_data = mysqli_fetch_assoc($customer_res);
$customer_id = $customer_data['customer_id'];
$customer_address = $customer_data['saved_address'] ?? 'Kariakoo, Dar es Salaam';
$customer_city = $customer_data['city'] ?? 'Dar es Salaam';
$customer_lat = -6.8225;
$customer_lon = 39.2697;

// DistanceMatrix API Configuration
define('DISTANCE_MATRIX_API_KEY', 'W0el2McecCDTUcgd3RTPmkKB8sqDSBRqQJMqLPlzc3bGBWU137QPxiZ3TNLp0kTy');
define('DISTANCE_MATRIX_URL', 'https://api.distancematrix.ai/maps/api/distancematrix/json?origins=51.4822656,-0.1933769&destinations=51.4994794,-0.1269979&key=W0el2McecCDTUcgd3RTPmkKB8sqDSBRqQJMqLPlzc3bGBWU137QPxiZ3TNLp0kTy');

// ============================================
// Get delivery rates from database ONLY
// ============================================
function getDeliveryRates($conn) {
    $rates = [];
    $sql = "SELECT rate_id, min_distance, max_distance, fee, description 
            FROM delivery_rates 
            WHERE is_active = 1 
            ORDER BY min_distance ASC";
    $result = mysqli_query($conn, $sql);
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rates[] = $row;
        }
    }
    
    return $rates;
}

// Function to calculate delivery fee based on distance using database rates
function calculateDeliveryFee($distance_km, $delivery_rates) {
    foreach ($delivery_rates as $rate) {
        if ($distance_km >= $rate['min_distance'] && $distance_km < $rate['max_distance']) {
            return $rate['fee'];
        }
    }
    // If no rate found, return 0
    return 0;
}

// Get delivery rates from database
$delivery_rates = getDeliveryRates($conn);

// Function to get accurate distance from DistanceMatrix API
function getAccurateDistance($origin, $destination, $api_key) {
    $origin_encoded = urlencode($origin);
    $destination_encoded = urlencode($destination);
    
    $url = DISTANCE_MATRIX_URL . "?origins={$origin_encoded}&destinations={$destination_encoded}&key={$api_key}&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && $response) {
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] == 'OK' && 
            isset($data['rows'][0]['elements'][0]['status']) && 
            $data['rows'][0]['elements'][0]['status'] == 'OK') {
            
            $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];
            $distance_km = round($distance_meters / 1000, 1);
            $duration_seconds = $data['rows'][0]['elements'][0]['duration']['value'];
            $duration_minutes = round($duration_seconds / 60);
            $duration_text = $data['rows'][0]['elements'][0]['duration']['text'];
            
            return [
                'success' => true,
                'distance_km' => $distance_km,
                'distance_meters' => $distance_meters,
                'duration_minutes' => $duration_minutes,
                'duration_text' => $duration_text,
                'distance_text' => $data['rows'][0]['elements'][0]['distance']['text']
            ];
        }
    }
    
    return ['success' => false, 'error' => 'Could not calculate distance'];
}

// Get cart items
$cart_items = [];
$cart_query = "
    SELECT c.*, p.name, p.price, p.image_url, p.quantity_in_stock, p.unit,
           b.business_id, b.business_name, b.latitude, b.longitude, b.address, b.city
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    JOIN businesses b ON p.business_id = b.business_id
    WHERE c.customer_id = '$customer_id'
    ORDER BY b.business_name
";
$cart_result = mysqli_query($conn, $cart_query);

$businesses = [];
$total_subtotal = 0;
$total_items = 0;
$total_delivery_fee = 0;
$distance_details = [];

while ($row = mysqli_fetch_assoc($cart_result)) {
    $cart_items[] = $row;
    $bid = $row['business_id'];
    
    if (!isset($businesses[$bid])) {
        // Build origin address for API
        $origin = $row['address'];
        if (!empty($row['city'])) $origin .= ', ' . $row['city'];
        if (!empty($row['latitude']) && !empty($row['longitude']) && $row['latitude'] != 0) {
            $origin = $row['latitude'] . ',' . $row['longitude'];
        }
        
        // Get accurate distance from DistanceMatrix API
        $distance_result = getAccurateDistance($origin, $customer_address, DISTANCE_MATRIX_API_KEY);
        
        if ($distance_result['success']) {
            $distance_km = $distance_result['distance_km'];
            $duration_text = $distance_result['duration_text'];
            $distance_text = $distance_result['distance_text'];
        } else {
            // Fallback calculation if API fails
            if (!empty($row['latitude']) && !empty($row['longitude']) && $row['latitude'] != 0 && $row['longitude'] != 0) {
                $lat1 = deg2rad($row['latitude']);
                $lon1 = deg2rad($row['longitude']);
                $lat2 = deg2rad($customer_lat);
                $lon2 = deg2rad($customer_lon);
                $dlat = $lat2 - $lat1;
                $dlon = $lon2 - $lon1;
                $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $distance_km = round(6371 * $c, 1);
                $duration_text = round($distance_km / 30 * 60) . ' minutes';
                $distance_text = $distance_km . ' km';
            } else {
                $distance_km = 0;
                $duration_text = 'N/A';
                $distance_text = '0 km';
            }
        }
        
        // Calculate delivery fee using database rates
        $delivery_fee = calculateDeliveryFee($distance_km, $delivery_rates);
        
        // Find which rate range applies
        $rate_description = '';
        foreach ($delivery_rates as $rate) {
            if ($distance_km >= $rate['min_distance'] && $distance_km < $rate['max_distance']) {
                $rate_description = $rate['description'] ?? '';
                break;
            }
        }
        
        $businesses[$bid] = [
            'business_id' => $bid,
            'business_name' => $row['business_name'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'address' => $row['address'],
            'city' => $row['city'],
            'distance_km' => $distance_km,
            'distance_text' => $distance_text,
            'duration_text' => $duration_text,
            'delivery_fee' => $delivery_fee,
            'rate_description' => $rate_description,
            'items' => [],
            'subtotal' => 0
        ];
        
        $distance_details[] = [
            'business_name' => $row['business_name'],
            'distance_km' => $distance_km,
            'distance_text' => $distance_text,
            'duration_text' => $duration_text,
            'fee' => $delivery_fee,
            'rate_description' => $rate_description
        ];
        
        $total_delivery_fee += $delivery_fee;
    }
    
    $businesses[$bid]['items'][] = $row;
    $businesses[$bid]['subtotal'] += $row['price'] * $row['quantity'];
    $total_subtotal += $row['price'] * $row['quantity'];
    $total_items += $row['quantity'];
}

// Estimate total delivery time
$max_duration_minutes = 0;
foreach ($distance_details as $dd) {
    preg_match('/(\d+)/', $dd['duration_text'], $matches);
    $minutes = isset($matches[1]) ? (int)$matches[1] : 45;
    if ($minutes > $max_duration_minutes) $max_duration_minutes = $minutes;
}

if ($max_duration_minutes <= 30) $delivery_time = "30-45 minutes";
elseif ($max_duration_minutes <= 60) $delivery_time = "1 hour";
elseif ($max_duration_minutes <= 120) $delivery_time = "1-2 hours";
else $delivery_time = round($max_duration_minutes / 60) . "-" . round($max_duration_minutes / 60 + 1) . " hours";

$grand_total = $total_subtotal + $total_delivery_fee;

$flash_message = $_SESSION['flash_message'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Cart | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 28px 32px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .page-header { margin-bottom: 28px; }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { 
            color: #64748b; 
            font-size: 16px; 
            margin-top: 8px;
            font-weight: 500;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        .cart-layout {
            display: grid;
            grid-template-columns: 400 380px;
            gap: 24px;
            align-items: start;
        }
        
        .cart-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        .cart-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .cart-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cart-header h3 i { color: #e67e22; font-size: 18px; }
        
        .btn-clear {
            background: #fee2e2;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-clear:hover { background: #dc2626; color: white; }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        .cart-table th {
            text-align: left;
            padding: 14px 18px;
            font-size: 14px;
            font-weight: 700;
            color: #64748b;
            background: #fafcff;
            border-bottom: 1px solid #eef2f6;
        }
        .cart-table td {
            padding: 18px;
            border-bottom: 1px solid #eef2f6;
            vertical-align: middle;
            font-size: 14px;
        }
        
        .product-img {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 12px;
            background: #f8fafc;
        }
        .product-name {
            font-weight: 700;
            font-size: 16px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .product-business {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .product-price {
            font-weight: 700;
            color: #e67e22;
            font-size: 16px;
            white-space: nowrap;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .qty-btn {
            width: 32px;
            height: 32px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 16px;
            transition: 0.2s;
        }
        .qty-btn:hover:not(:disabled) {
            background: #e67e22;
            color: white;
            border-color: #e67e22;
        }
        .qty-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .qty-input {
            width: 50px;
            text-align: center;
            padding: 6px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .item-total {
            font-weight: 700;
            color: #e67e22;
            font-size: 16px;
        }
        .remove-btn {
            background: none;
            border: none;
            color: #e74c3c;
            font-size: 16px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .remove-btn:hover {
            background: #fee2e2;
        }
        
        .summary-card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 20px;
        }
        .summary-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .summary-header h3 i { color: #e67e22; font-size: 18px; }
        .summary-body { padding: 24px; }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
            font-size: 15px;
        }
        .delivery-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0 10px 16px;
            font-size: 13px;
            color: #64748b;
            border-bottom: 1px dashed #eef2f6;
        }
        .delivery-item small {
            font-size: 11px;
            color: #94a3b8;
        }
        .rate-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 4px;
        }
        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 18px 0 12px;
            margin-top: 10px;
            border-top: 2px solid #e2e8f0;
            font-size: 20px;
            font-weight: 800;
            color: #e67e22;
        }
        
        .delivery-note {
            background: #fffbeb;
            padding: 14px;
            border-radius: 12px;
            font-size: 13px;
            color: #92400e;
            text-align: center;
            margin: 18px 0;
            line-height: 1.5;
        }
        .distance-badge {
            font-size: 12px;
            color: #e67e22;
            margin-left: 5px;
            background: #fef3c7;
            padding: 3px 8px;
            border-radius: 20px;
        }
        
        .btn-checkout {
            width: 100%;
            padding: 14px;
            background: #e67e22;
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.2s;
        }
        .btn-checkout:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-continue {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-top: 14px;
            transition: 0.2s;
        }
        .btn-continue:hover {
            border-color: #e67e22;
            color: #e67e22;
        }
        
        .empty-cart {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .empty-cart i {
            font-size: 72px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        .empty-cart h3 {
            font-size: 24px;
            margin-bottom: 12px;
        }
        .empty-cart p {
            color: #64748b;
            font-size: 15px;
            margin-bottom: 24px;
        }
        .btn-shop {
            background: #e67e22;
            color: white;
            padding: 12px 28px;
            border-radius: 30px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            font-size: 15px;
        }
        .btn-shop:hover { background: #d35400; }
        
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            z-index: 2000;
            opacity: 0;
            transition: 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 20px; }
            .cart-layout { grid-template-columns: 1fr; }
            .summary-card { position: static; }
        }
        @media (max-width: 768px) {
            .cart-table th, .cart-table td { padding: 12px; }
            .product-img { width: 50px; height: 50px; }
            .qty-btn { width: 28px; height: 28px; }
            .qty-input { width: 45px; }
            .product-name { font-size: 14px; }
            .product-business { font-size: 11px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
        <p>
            <i class="fas fa-map-marker-alt"></i> Delivery from: <strong><?php echo htmlspecialchars($customer_address); ?></strong>
        </p>
    </div>

    <?php if($flash_message): ?>
    <div class="alert alert-<?php echo $flash_type; ?>">
        <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <span><?php echo htmlspecialchars($flash_message); ?></span>
    </div>
    <?php endif; ?>

    <div id="cartContainer">
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Your cart is empty</h3>
                <p>Add products to your cart and they will appear here</p>
                <a href="../products/index.php" class="btn-shop"><i class="fas fa-store"></i> Browse Products</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-card">
                    <div class="cart-header">
                        <h3><i class="fas fa-boxes"></i> Cart Items (<span id="totalItemsCount"><?php echo $total_items; ?></span>)</h3>
                        <a href="clear_cart.php" class="btn-clear" onclick="return confirm('Clear entire cart?')"><i class="fas fa-trash"></i> Clear Cart</a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="cart-table">
                            <thead>
                                <tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item):
                                    $item_total = $item['price'] * $item['quantity'];
                                    $img_src = '../../assets/images/default-product.jpg';
                                    if (!empty($item['image_url'])) {
                                        if (file_exists('../../' . $item['image_url'])) $img_src = '../../' . $item['image_url'];
                                        elseif (file_exists($item['image_url'])) $img_src = $item['image_url'];
                                    }
                                    $business_dist = $businesses[$item['business_id']] ?? null;
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <img src="<?php echo $img_src; ?>" class="product-img" onerror="this.src='../../assets/images/default-product.jpg'">
                                            <div>
                                                <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <div class="product-business">
                                                    <i class="fas fa-store"></i> <?php echo htmlspecialchars($item['business_name']); ?>
                                                    <?php if($business_dist): ?>
                                                        <span class="distance-badge">
                                                            <i class="fas fa-road"></i> <?php echo $business_dist['distance_text']; ?> · 
                                                            <i class="fas fa-clock"></i> <?php echo $business_dist['duration_text']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="product-price">TSh <?php echo number_format($item['price']); ?> / <?php echo $item['unit']; ?></td>
                                    <td>
                                        <form method="POST" action="update_cart.php" style="display: flex; align-items: center; gap: 8px;">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['quantity_in_stock']; ?>" style="width: 55px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center;">
                                            <button type="submit" class="qty-btn" style="width: 32px; height: 32px; background: #e67e22; color: white; border: none; border-radius: 8px; cursor: pointer;">
                                                <i class="fas fa-check" style="font-size: 12px;"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="item-total">TSh <?php echo number_format($item_total); ?></td>
                                    <td>
                                        <a href="remove_item.php?id=<?php echo $item['cart_id']; ?>" class="remove-btn" onclick="return confirm('Remove this item?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="summary-card">
                    <div class="summary-header">
                        <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                    </div>
                    <div class="summary-body">
                        <div class="summary-row">
                            <span>Subtotal (<span><?php echo $total_items; ?></span> items)</span>
                            <span><strong>TSh <?php echo number_format($total_subtotal); ?></strong></span>
                        </div>
                        <div style="font-weight: 600; margin: 12px 0 8px; font-size: 14px;">
                            <i class="fas fa-truck"></i> Delivery Fees
                            <small style="font-weight: 400; color: #94a3b8; font-size: 11px;">(from database rates)</small>
                        </div>
                        <?php foreach ($distance_details as $dd): ?>
                        <div class="delivery-item">
                            <span>
                                <i class="fas fa-store"></i> <?php echo htmlspecialchars($dd['business_name']); ?>
                                <small>(<?php echo $dd['distance_text']; ?> · <?php echo $dd['duration_text']; ?>)</small>
                                <?php if(!empty($dd['rate_description'])): ?>
                                    <span class="rate-badge"><?php echo htmlspecialchars($dd['rate_description']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span>TSh <?php echo number_format($dd['fee']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <div class="summary-row">
                            <span>Total Delivery</span>
                            <span><strong>TSh <?php echo number_format($total_delivery_fee); ?></strong></span>
                        </div>
                        <div class="summary-total">
                            <span>Grand Total</span>
                            <span><strong>TSh <?php echo number_format($grand_total); ?></strong></span>
                        </div>
                        
                        <div class="delivery-note">
                            <i class="fas fa-clock"></i> Estimated delivery: <strong><?php echo $delivery_time; ?></strong><br>
                            <small><i class="fas fa-database"></i> Delivery rates loaded from database · Powered by DistanceMatrix API</small>
                        </div>
                        
                        <form method="POST" action="checkout.php">
                            <input type="hidden" name="delivery_fee" value="<?php echo $total_delivery_fee; ?>">
                            <input type="hidden" name="distance_details" value='<?php echo htmlspecialchars(json_encode($distance_details)); ?>'>
                            <button type="submit" class="btn-checkout">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </button>
                        </form>
                        <a href="../products/index.php" class="btn-continue">
                            <i class="fas fa-arrow-left"></i> Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Simple toast notification
function showToast(message, isError = false) {
    let toast = document.getElementById('toastMessage');
    toast.textContent = message;
    toast.style.background = isError ? '#dc2626' : '#27ae60';
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 3000);
}
</script>
</body>
</html>