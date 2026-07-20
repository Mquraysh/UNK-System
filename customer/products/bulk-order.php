<?php
// customer/products/bulk-order.php - SIMPLE VERSION (No Business Discount)
require_once '../../config/database.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT p.*, b.business_name, b.is_verified 
                                   FROM products p
                                   JOIN businesses b ON p.business_id = b.business_id
                                   WHERE p.product_id = ? AND p.is_available = 1");
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Bulk discount tiers (quantity-based only)
$bulk_tiers = [
    5 => 5,      // 5+ units: 5% off
    10 => 10,    // 10+ units: 10% off
    25 => 15,    // 25+ units: 15% off
    50 => 20,    // 50+ units: 20% off
    100 => 25    // 100+ units: 25% off
];

// PROCESS ADD TO CART
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = (int)$_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0 && $product_id > 0) {
        $user_id = $_SESSION['user_id'];
        $customer_sql = "SELECT customer_id FROM customers WHERE user_id = $user_id";
        $customer_result = mysqli_query($conn, $customer_sql);
        $customer = mysqli_fetch_assoc($customer_result);
        $customer_id = $customer['customer_id'];
        
        $stock_sql = "SELECT quantity_in_stock FROM products WHERE product_id = $product_id";
        $stock_result = mysqli_query($conn, $stock_sql);
        $stock = mysqli_fetch_assoc($stock_result);
        
        if ($quantity > $stock['quantity_in_stock']) {
            $_SESSION['flash_message'] = "Only " . $stock['quantity_in_stock'] . " units available";
            $_SESSION['flash_type'] = "danger";
        } else {
            $check_sql = "SELECT cart_id FROM cart WHERE customer_id = $customer_id AND product_id = $product_id";
            $check_result = mysqli_query($conn, $check_sql);
            
            if (mysqli_num_rows($check_result) > 0) {
                $update_sql = "UPDATE cart SET quantity = quantity + $quantity WHERE customer_id = $customer_id AND product_id = $product_id";
                mysqli_query($conn, $update_sql);
                $_SESSION['flash_message'] = "Cart updated!";
            } else {
                $insert_sql = "INSERT INTO cart (customer_id, product_id, quantity) VALUES ($customer_id, $product_id, $quantity)";
                mysqli_query($conn, $insert_sql);
                $_SESSION['flash_message'] = "Product added to cart!";
            }
            $_SESSION['flash_type'] = "success";
        }
        
        header("Location: ../cart/index.php");
        exit();
    }
}

function getProductImage($imagePath) {
    if (empty($imagePath)) return '../../assets/images/default-product.jpg';
    return '../../' . ltrim($imagePath, './');
}

function getBulkDiscountPercent($quantity, $tiers) {
    $discount = 0;
    foreach ($tiers as $min_qty => $discount_percent) {
        if ($quantity >= $min_qty) {
            $discount = $discount_percent;
        }
    }
    return $discount;
}

include '../../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Order | UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; }
        .customer-content { padding: 30px 35px; min-height: 100vh; background: #f5f7fb; }
        .page-header { margin-bottom: 25px; }
        .page-header h1 { font-size: 28px; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 13px; margin-top: 5px; }
        
        .card {
            background: white;
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            max-width: 12000px;
            margin: 0 auto;
        }
        .card-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .card-header h3 { font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        
        .product-info {
            display: flex;
            gap: 20px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap;
        }
        .product-info img { width: 80px; height: 80px; object-fit: cover; border-radius: 12px; }
        .product-details h4 { font-size: 16px; font-weight: 700; margin-bottom: 5px; }
        .product-price { font-size: 18px; font-weight: 700; color: #e67e22; margin: 5px 0; }
        .stock-status { font-size: 12px; margin-top: 5px; }
        .stock-available { color: #059669; }
        
        .discount-info {
            background: #fef3c7;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #92400e;
        }
        .discount-info i { margin-right: 8px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #334155; }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
        }
        .form-control:focus { outline: none; border-color: #e67e22; }
        
        .total-price {
            background: #fff7ed;
            padding: 18px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .total-price .label { font-size: 13px; color: #64748b; margin-bottom: 5px; }
        .total-price .original { font-size: 14px; color: #94a3b8; text-decoration: line-through; }
        .total-price .amount { font-size: 28px; font-weight: 800; color: #e67e22; }
        .total-price .saved { font-size: 12px; color: #27ae60; margin-top: 5px; }
        
        .btn {
            width: 100%;
            background: #e67e22;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
        }
        .btn:hover { background: #d35400; transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 60px; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 15px; }
        .btn-secondary { background: #2c3e50; display: inline-block; width: auto; padding: 10px 24px; }
        
        @media (max-width: 768px) {
            .customer-content { padding: 20px; }
            .product-info { flex-direction: column; text-align: center; }
            .total-price .amount { font-size: 22px; }
        }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <h1><i class="fas fa-boxes"></i> Bulk Order</h1>
        <p>Save more when you buy in bulk quantities</p>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calculator"></i> Order Quantity</h3>
        </div>
        <div class="card-body">
            <?php if ($product): ?>
                <div class="product-info">
                    <img src="<?= getProductImage($product['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" onerror="this.src='../../assets/images/default-product.jpg'">
                    <div class="product-details">
                        <h4>
                            <?= htmlspecialchars($product['name']) ?>
                            <?php if($product['is_verified']): ?>
                                <span style="background:#dbeafe; color:#2563eb; padding:2px 8px; border-radius:20px; font-size:10px; margin-left:5px;">Verified</span>
                            <?php endif; ?>
                        </h4>
                        <div class="product-business" style="font-size: 12px; color: #64748b; margin-bottom: 5px;">
                            <i class="fas fa-store"></i> <?= htmlspecialchars($product['business_name']) ?>
                        </div>
                        <div class="product-price">
                            TSh <?= number_format($product['price']) ?> / <?= $product['unit'] ?? 'piece' ?>
                        </div>
                        <div class="stock-status">
                            <i class="fas fa-check-circle stock-available"></i> 
                            <span class="stock-available">In Stock: <?= $product['quantity_in_stock'] ?> units</span>
                        </div>
                    </div>
                </div>

                <div class="discount-info">
                    <i class="fas fa-tag"></i> <strong>Bulk Discounts:</strong>
                    <?php foreach ($bulk_tiers as $qty => $discount): ?>
                        <span style="margin-right: 12px;">
                            📦 <?= $qty ?>+ units = <?= $discount ?>% OFF
                        </span>
                    <?php endforeach; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= $product['product_id'] ?>">
                    <div class="form-group">
                        <label>Quantity (<?= ucfirst($product['unit'] ?? 'pieces') ?>)</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" 
                               min="1" max="<?= $product['quantity_in_stock'] ?>" value="1" required>
                    </div>
                    <div class="total-price" id="totalPriceBox">
                        <div class="label">Total Price</div>
                        <div class="original" id="originalPrice" style="display: none;"></div>
                        <div class="amount" id="totalAmount">TSh <?= number_format($product['price']) ?></div>
                        <div class="saved" id="savedAmount" style="display: none;"></div>
                    </div>
                    <button type="submit" class="btn">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </form>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Product Not Found</h3>
                    <p>The product you're looking for doesn't exist or is no longer available.</p>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Browse Products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($product): ?>
<script>
    const price = <?= $product['price'] ?>;
    const maxStock = <?= $product['quantity_in_stock'] ?>;
    const quantityInput = document.getElementById('quantity');
    const totalAmountSpan = document.getElementById('totalAmount');
    const savedAmountSpan = document.getElementById('savedAmount');
    const originalPriceSpan = document.getElementById('originalPrice');
    
    const bulkTiers = <?php echo json_encode($bulk_tiers); ?>;
    
    function getDiscountPercent(quantity) {
        let discount = 0;
        for (let [minQty, discountPercent] of Object.entries(bulkTiers)) {
            if (quantity >= parseInt(minQty)) {
                discount = discountPercent;
            }
        }
        return discount;
    }
    
    function updateTotal() {
        let qty = parseInt(quantityInput.value) || 1;
        if (qty < 1) qty = 1;
        if (qty > maxStock) qty = maxStock;
        quantityInput.value = qty;
        
        const originalTotal = price * qty;
        const discountPercent = getDiscountPercent(qty);
        const discountAmount = (originalTotal * discountPercent) / 100;
        const finalTotal = originalTotal - discountAmount;
        
        if (discountPercent > 0) {
            originalPriceSpan.style.display = 'block';
            originalPriceSpan.innerHTML = 'Original: TSh ' + originalTotal.toLocaleString();
            totalAmountSpan.innerText = 'TSh ' + finalTotal.toLocaleString();
            savedAmountSpan.style.display = 'block';
            savedAmountSpan.innerHTML = '<i class="fas fa-tag"></i> You save TSh ' + discountAmount.toLocaleString() + ' (' + discountPercent + '% off)';
        } else {
            originalPriceSpan.style.display = 'none';
            totalAmountSpan.innerText = 'TSh ' + originalTotal.toLocaleString();
            savedAmountSpan.style.display = 'none';
        }
    }
    
    quantityInput.addEventListener('input', updateTotal);
    updateTotal();
</script>
<?php endif; ?>
</body>
</html>