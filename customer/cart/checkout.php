<?php
// customer/cart/checkout.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'] ?? '';

// Get customer data
$stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$customer) {
    header("Location: ../register.php");
    exit();
}
$customer_id = $customer['customer_id'];
$customer_name = $customer['first_name'] . ' ' . $customer['last_name'];

// Get user phone and email
$stmt = mysqli_prepare($conn, "SELECT phone, email FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$user_phone = $user_info['phone'] ?? '';
$user_email = $user_info['email'] ?? '';

// DistanceMatrix.ai API Configuration
define('DISTANCE_MATRIX_API_KEY', 'W0el2McecCDTUcgd3RTPmkKB8sqDSBRqQJMqLPlzc3bGBWU137QPxiZ3TNLp0kTy');
define('DISTANCE_MATRIX_URL', 'https://api.distancematrix.ai/maps/api/distancematrix/json?origins=51.4822656,-0.1933769&destinations=51.4994794,-0.1269979&key=W0el2McecCDTUcgd3RTPmkKB8sqDSBRqQJMqLPlzc3bGBWU137QPxiZ3TNLp0kTy');


// ============================================
// Get delivery rates from database
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
    return 0;
}

// Get delivery rates from database
$delivery_rates = getDeliveryRates($conn);

// Get cart items grouped by business
$cart_items = [];
$businesses = [];
$cart_query = "SELECT c.*, p.name, p.price, p.image_url, p.quantity_in_stock, p.unit,
                      b.business_id, b.business_name, b.location, b.city, b.phone, b.latitude, b.longitude, b.address
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               JOIN businesses b ON p.business_id = b.business_id
               WHERE c.customer_id = ?";
$stmt = mysqli_prepare($conn, $cart_query);
mysqli_stmt_bind_param($stmt, 'i', $customer_id);
mysqli_stmt_execute($stmt);
$cart_res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($cart_res)) {
    $cart_items[] = $row;
    if (!isset($businesses[$row['business_id']])) {
        $businesses[$row['business_id']] = [
            'business_id' => $row['business_id'],
            'business_name' => $row['business_name'],
            'location' => $row['location'],
            'city' => $row['city'],
            'phone' => $row['phone'],
            'latitude' => $row['latitude'],
            'longitude' => $row['longitude'],
            'address' => $row['address'],
            'items' => [],
            'subtotal' => 0
        ];
    }
    $businesses[$row['business_id']]['items'][] = $row;
    $businesses[$row['business_id']]['subtotal'] += $row['price'] * $row['quantity'];
}
mysqli_stmt_close($stmt);

if (empty($cart_items)) {
    header("Location: index.php");
    exit();
}

// Function to get accurate distance from DistanceMatrix.ai API
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

// Customer location (Kariakoo)
$customer_lat = -6.8225;
$customer_lon = 39.2697;
$customer_address = $customer['saved_address'] ?? 'Kariakoo, Dar es Salaam';

$delivery_details = [];
$total_delivery_fee = 0;
$total_subtotal = 0;
$total_items = 0;

foreach ($cart_items as $item) {
    $total_subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
}

foreach ($businesses as $bid => $bus) {
    // Build origin address from business
    $origin = $bus['address'];
    if (!empty($bus['city'])) $origin .= ', ' . $bus['city'];
    if (!empty($bus['latitude']) && !empty($bus['longitude']) && $bus['latitude'] != 0) {
        $origin = $bus['latitude'] . ',' . $bus['longitude'];
    }
    
    // Get accurate distance from DistanceMatrix.ai API
    $distance_result = getAccurateDistance($origin, $customer_address, DISTANCE_MATRIX_API_KEY);
    
    if ($distance_result['success']) {
        $distance_km = $distance_result['distance_km'];
        $duration_text = $distance_result['duration_text'];
        $distance_text = $distance_result['distance_text'];
    } else {
        // Fallback to haversine if API fails
        if (!empty($bus['latitude']) && !empty($bus['longitude']) && $bus['latitude'] != 0 && $bus['longitude'] != 0) {
            $lat1 = deg2rad($bus['latitude']);
            $lon1 = deg2rad($bus['longitude']);
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
            $distance_km = 3;
            $duration_text = '15-20 minutes';
            $distance_text = '3 km';
        }
    }
    
    // Calculate delivery fee using database rates
    $fee = calculateDeliveryFee($distance_km, $delivery_rates);
    
    // Find which rate range applies
    $rate_description = '';
    foreach ($delivery_rates as $rate) {
        if ($distance_km >= $rate['min_distance'] && $distance_km < $rate['max_distance']) {
            $rate_description = $rate['description'] ?? '';
            break;
        }
    }
    
    $delivery_details[$bid] = [
        'business_id' => $bid,
        'business_name' => $bus['business_name'],
        'distance_km' => $distance_km,
        'distance_text' => $distance_text,
        'duration_text' => $duration_text,
        'fee' => $fee,
        'rate_description' => $rate_description,
        'subtotal' => $bus['subtotal']
    ];
    $total_delivery_fee += $fee;
}

$grand_total = $total_subtotal + $total_delivery_fee;


// HANDLE AJAX REQUEST - Place Order Directly 
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($is_ajax && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;
    
    $payment_method = trim($input['payment_method'] ?? '');
    $delivery_address = trim($input['delivery_address'] ?? '');
    $special_instructions = trim($input['special_instructions'] ?? '');
    
    $error = '';
    if (empty($payment_method)) $error = "Select payment method";
    elseif (empty($delivery_address)) $error = "Enter delivery address";
    
    if (empty($error)) {
        $payment_details = "Payment: " . ucfirst(str_replace('_', ' ', $payment_method));
        if (!empty($special_instructions)) {
            $full_instructions = $special_instructions . "\n[{$payment_details}]";
        } else {
            $full_instructions = "[{$payment_details}]";
        }
        
        mysqli_begin_transaction($conn);
        $order_success = true;
        $order_ids = [];
        $first_order_id = 0;
        
        foreach ($businesses as $bid => $bus) {
            $order_total = $bus['subtotal'];
            $delivery_fee = $delivery_details[$bid]['fee'];
            $order_grand = $order_total + $delivery_fee;
            
            $order_sql = "INSERT INTO orders (customer_id, business_id, total_amount, delivery_fee, grand_total,
                          payment_method, payment_status, delivery_address, special_instructions, status, order_date)
                          VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, 'pending', NOW())";
            $stmt = mysqli_prepare($conn, $order_sql);
            mysqli_stmt_bind_param($stmt, 'iiddssss', 
                $customer_id, $bid, $order_total, $delivery_fee, $order_grand,
                $payment_method, $delivery_address, $full_instructions);
            if (!mysqli_stmt_execute($stmt)) $order_success = false;
            $order_id = mysqli_insert_id($conn);
            if ($first_order_id == 0) $first_order_id = $order_id;
            mysqli_stmt_close($stmt);
            $order_ids[] = $order_id;
            
            foreach ($bus['items'] as $item) {
                $sub = $item['price'] * $item['quantity'];
                $item_sql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $item_sql);
                mysqli_stmt_bind_param($stmt, 'iiidd', $order_id, $item['product_id'], $item['quantity'], $item['price'], $sub);
                if (!mysqli_stmt_execute($stmt)) $order_success = false;
                mysqli_stmt_close($stmt);
                
                $new_stock = $item['quantity_in_stock'] - $item['quantity'];
                $update_stock = "UPDATE products SET quantity_in_stock = ? WHERE product_id = ?";
                $stmt = mysqli_prepare($conn, $update_stock);
                mysqli_stmt_bind_param($stmt, 'ii', $new_stock, $item['product_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            
            // Add notification for business
            $notif_sql = "INSERT INTO business_notifications (business_id, title, message, type, created_at) 
                         VALUES (?, 'New Order', CONCAT('Order ', ?, ' from ', ?, ' - TSh ', FORMAT(?, 0)), 'order', NOW())";
            $stmt = mysqli_prepare($conn, $notif_sql);
            mysqli_stmt_bind_param($stmt, 'iiss', $bid, $order_id, $customer_name, $order_grand);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        
        if ($order_success && $first_order_id > 0) {
            // Delete cart
            $delete_cart = "DELETE FROM cart WHERE customer_id = ?";
            $stmt = mysqli_prepare($conn, $delete_cart);
            mysqli_stmt_bind_param($stmt, 'i', $customer_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            mysqli_commit($conn);
            
            echo json_encode([
                'success' => true, 
                'redirect' => '../orders/index.php',
                'message' => 'Order placed successfully! Order ' . $first_order_id
            ]);
            exit();
        } else {
            mysqli_rollback($conn);
            $error = "Failed to place order. Please try again.";
        }
    }
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

include '../includes/customer_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Checkout | UNK System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            align-items: start;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }
        .card-header {
            padding: 18px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i { color: #e67e22; font-size: 18px; }
        .card-body { padding: 24px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #334155;
        }
        .form-group label .required { color: #e74c3c; }
        .form-control, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: 0.2s;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        textarea.form-control { resize: vertical; }
        small { 
            font-size: 12px; 
            color: #94a3b8; 
            margin-top: 6px; 
            display: block;
        }
        
        .optional-field {
            color: #94a3b8;
            font-size: 12px;
            margin-left: 5px;
            font-weight: normal;
        }
        
        .payment-options { display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px; }
        .payment-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            cursor: pointer;
            transition: 0.2s;
        }
        .payment-option:hover, .payment-option.selected {
            border-color: #e67e22;
            background: #fffaf5;
        }
        .payment-option input { width: 18px; height: 18px; cursor: pointer; accent-color: #e67e22; }
        .payment-option label {
            flex: 1;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .payment-option label i { font-size: 20px; color: #e67e22; width: 28px; }
        .payment-option span { font-size: 12px; color: #64748b; }
        
        .payment-details-fields {
            padding: 18px;
            background: #fef9e8;
            border-radius: 16px;
            border: 1px solid #fde3b6;
            display: none;
        }
        .payment-details-fields.show { display: block; }
        
        .order-summary {
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
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eef2f6;
            font-size: 15px;
        }
        .business-delivery {
            padding: 8px 0 8px 20px;
            font-size: 13px;
            color: #64748b;
            border-bottom: 1px dashed #eef2f6;
        }
        .business-delivery i { color: #e67e22; width: 20px; margin-right: 6px; }
        .business-delivery small {
            font-size: 11px;
            color: #94a3b8;
        }
        .rate-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            margin-left: 4px;
            display: inline-block;
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
        
        .btn-place-order {
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
            margin-top: 20px;
        }
        .btn-place-order:hover {
            background: #d35400;
            transform: translateY(-2px);
        }
        .btn-place-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-back {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-top: 14px;
            transition: 0.2s;
        }
        .btn-back:hover {
            border-color: #e67e22;
            color: #e67e22;
        }
        
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
            .checkout-layout { grid-template-columns: 1fr; }
            .order-summary { position: static; }
        }
        @media (max-width: 640px) {
            .customer-content { padding: 16px; }
            .card-header, .card-body, .summary-header, .summary-body { padding: 16px; }
            .payment-option { padding: 12px 14px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-credit-card"></i> Checkout</h1>
        <p><i class="fas fa-lock"></i> Secure checkout · Confirm your order details below</p>
    </div>

    <div class="checkout-layout">
        <!-- Left: Forms -->
        <div>
            <form id="checkoutForm">
                <!-- Delivery Info -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-map-marker-alt"></i> Delivery Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer_name); ?>" readonly style="background:#f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_phone); ?>" readonly style="background:#f8fafc;">
                        </div>
                        <div class="form-group">
                            <label>Delivery Address <span class="required">*</span></label>
                            <textarea name="delivery_address" class="form-control" rows="3" required placeholder="House number, street, area, landmark..."><?php echo htmlspecialchars($customer['saved_address'] ?? ''); ?></textarea>
                            <small><i class="fas fa-info-circle"></i> Delivery fee calculated from your location to each store</small>
                        </div>
                        <div class="form-group">
                            <label>Special Instructions <span class="optional-field">(Optional)</span></label>
                            <textarea name="special_instructions" class="form-control" rows="3" placeholder="Gate code, landmark, delivery time preference, contact person..."></textarea>
                            <small><i class="fas fa-info-circle"></i> Add any special instructions for the delivery person (optional)</small>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-wallet"></i> Payment Method</h3>
                    </div>
                    <div class="card-body">
                        <div class="payment-options" id="paymentOptions">
                            <!-- Cash on Delivery -->
                            <div class="payment-option" data-value="cash" style="border-color:#e67e22; background:#fffaf5;">
                                <input type="radio" name="payment_method" value="cash" id="cash" checked>
                                <label for="cash"><i class="fas fa-money-bill-wave"></i> Cash on Delivery</label>
                                <span>Pay when you receive</span>
                            </div>
                            
                            <!-- Mobile Money -->
                            <div class="payment-option" data-value="mobile_money">
                                <input type="radio" name="payment_method" value="mobile_money" id="mobile_money">
                                <label for="mobile_money"><i class="fas fa-mobile-alt"></i> Mobile Money</label>
                                <span>M-Pesa, Mix by Yas, Airtel</span>
                            </div>
                        </div>
                        
                        <!-- Mobile Money Details -->
                        <div id="mobileMoneyFields" class="payment-details-fields">
                            <div class="form-group">
                                <label>Mobile Network <span class="required">*</span></label>
                                <select name="mobile_network" class="form-select">
                                    <option value="">Select Network</option>
                                    <option value="M-Pesa">M-Pesa (Vodacom)</option>
                                    <option value="Mix by Yas">Mix by Yas (Tigo)</option>
                                    <option value="Airtel Money">Airtel Money</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Phone Number <span class="required">*</span></label>
                                <input type="tel" name="mobile_phone" class="form-control" placeholder="0712345678" value="<?php echo htmlspecialchars($user_phone); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right: Order Summary -->
        <div>
            <div class="order-summary">
                <div class="summary-header">
                    <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                </div>
                <div class="summary-body">
                    <div class="summary-item">
                        <span>Items (<?php echo $total_items; ?> products)</span>
                        <span><strong>TSh <?php echo number_format($total_subtotal); ?></strong></span>
                    </div>
                    <div style="margin-top: 10px;">
                        <div style="font-weight: 600; margin-bottom: 8px; font-size: 14px;">Delivery Fees <small>(from database rates)</small></div>
                        <?php foreach ($delivery_details as $d): ?>
                        <div class="business-delivery">
                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($d['business_name']); ?>
                            <?php if(!empty($d['rate_description'])): ?>
                                <span class="rate-badge"><?php echo htmlspecialchars($d['rate_description']); ?></span>
                            <?php endif; ?>
                            <span style="float: right;">TSh <?php echo number_format($d['fee']); ?></span>
                            <div style="clear: both;"></div>
                            <small><i class="fas fa-road"></i> <?php echo $d['distance_text']; ?> · <i class="fas fa-clock"></i> <?php echo $d['duration_text']; ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="summary-item" style="margin-top: 10px;">
                        <span>Total Delivery</span>
                        <span><strong>TSh <?php echo number_format($total_delivery_fee); ?></strong></span>
                    </div>
                    <div class="summary-total">
                        <span>Grand Total</span>
                        <span><strong>TSh <?php echo number_format($grand_total); ?></strong></span>
                    </div>
                    <button type="button" id="placeOrderBtn" class="btn-place-order">
                        <i class="fas fa-check-circle"></i> Place Order
                    </button>
                    <a href="index.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toastMessage" class="toast-message"></div>

<script>
function showToast(message, isError = false) {
    let toast = document.getElementById('toastMessage');
    toast.textContent = message;
    toast.style.background = isError ? '#dc2626' : '#27ae60';
    toast.style.opacity = '1';
    setTimeout(() => { toast.style.opacity = '0'; }, 4000);
}

function getFormData() {
    let form = document.getElementById('checkoutForm');
    let formData = new FormData(form);
    let data = {};
    formData.forEach((value, key) => { data[key] = value; });
    let paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    if (paymentMethod) data.payment_method = paymentMethod.value;
    return data;
}

document.getElementById('placeOrderBtn').addEventListener('click', async function() {
    let btn = this;
    let originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    let data = getFormData();
    
    try {
        let response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });
        let result = await response.json();
        
        if (result.success) {
            showToast(result.message);
            setTimeout(() => { 
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    window.location.href = '../orders/index.php';
                }
            }, 1500);
        } else {
            showToast(result.message, true);
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch(err) {
        showToast('Network error. Please try again.', true);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Payment method UI
let paymentRadios = document.querySelectorAll('input[name="payment_method"]');
let mobileFields = document.getElementById('mobileMoneyFields');
let paymentOptions = document.querySelectorAll('.payment-option');

function showFieldsForMethod(value) {
    mobileFields.classList.remove('show');
    if (value === 'mobile_money') mobileFields.classList.add('show');
}

paymentRadios.forEach(radio => {
    radio.addEventListener('change', function() { showFieldsForMethod(this.value); });
});

paymentOptions.forEach(opt => {
    let radio = opt.querySelector('input[type="radio"]');
    opt.addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            radio.checked = true;
            showFieldsForMethod(radio.value);
        }
        paymentOptions.forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
    });
    if (radio.checked) {
        opt.classList.add('selected');
        showFieldsForMethod(radio.value);
    }
});

showFieldsForMethod('cash');
</script>
</body>
</html>