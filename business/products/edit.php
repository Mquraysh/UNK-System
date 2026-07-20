<?php
// business/products/edit.php 
require_once '../../config/database.php';

session_start();

// Check if business is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'business') {
    header("Location: ../login.php");
    exit();
}

// Get business data
$user_id = $_SESSION['user_id'];
$business_sql = "SELECT * FROM businesses WHERE user_id = '$user_id'";
$business_result = mysqli_query($conn, $business_sql);
$business = mysqli_fetch_assoc($business_result);

if (!$business) {
    header("Location: ../register.php");
    exit();
}

$business_id = $business['business_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get product data
$product_sql = "SELECT * FROM products WHERE product_id = '$product_id' AND business_id = '$business_id'";
$product_result = mysqli_query($conn, $product_sql);
$product = mysqli_fetch_assoc($product_result);

if (!$product) {
    $_SESSION['flash_message'] = "Product not found";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit();
}


// BUILD HIERARCHICAL CATEGORY TREE 


// Get all categories
$all_cats_sql = "SELECT * FROM categories ORDER BY parent_id, sort_order, name";
$all_cats_result = mysqli_query($conn, $all_cats_sql);
$all_categories = [];
while($row = mysqli_fetch_assoc($all_cats_result)) {
    $all_categories[] = $row;
}

// Build category tree recursively with selected value
function buildCategoryOptionsWithSelected($categories, $selected_id, $parent_id = NULL, $level = 0) {
    $html = '';
    foreach($categories as $cat) {
        if($cat['parent_id'] == $parent_id) {
            // Create indentation
            $indent = '';
            for($i = 0; $i < $level; $i++) {
                $indent .= '&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            $prefix = ($level > 0) ? '└─ ' : '';
            
            $selected = ($selected_id == $cat['category_id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['category_id'] . '" ' . $selected . '>' . $indent . $prefix . htmlspecialchars($cat['name']) . '</option>';
            
            // Get sub-categories
            $html .= buildCategoryOptionsWithSelected($categories, $selected_id, $cat['category_id'], $level + 1);
        }
    }
    return $html;
}

$category_options = buildCategoryOptionsWithSelected($all_categories, $product['category_id']);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $category_id = intval($_POST['category_id']);
    $unit = mysqli_real_escape_string($conn, trim($_POST['unit']));
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Product name is required";
    if (empty($category_id)) $errors[] = "Category is required";
    if ($price <= 0) $errors[] = "Valid price is required";
    if ($quantity < 0) $errors[] = "Valid quantity is required";
    
    // Handle image upload
    $image_url = $product['image_url'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "../../assets/uploads/products/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        // Check file size (max 5MB)
        if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = "Image file is too large. Maximum size is 5MB.";
        }
        // Check file extension
        elseif (in_array($file_extension, $allowed_extensions)) {
            $filename = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if exists
                if (!empty($product['image_url'])) {
                    $old_image_path = str_replace("assets/uploads/products/", "../../assets/uploads/products/", $product['image_url']);
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $image_url = "assets/uploads/products/" . $filename;
            } else {
                $errors[] = "Failed to upload image. Please try again.";
            }
        } else {
            $errors[] = "Invalid image format. Allowed: JPG, JPEG, PNG, GIF, WEBP";
        }
    }
    
    if (empty($errors)) {
        $update_sql = "UPDATE products SET 
                        name = '$name', 
                        description = '$description', 
                        price = '$price',
                        quantity_in_stock = '$quantity', 
                        category_id = '$category_id', 
                        unit = '$unit', 
                        image_url = '$image_url'
                    WHERE product_id = '$product_id' AND business_id = '$business_id'";
        
        if (mysqli_query($conn, $update_sql)) {
            $_SESSION['flash_message'] = "Product updated successfully!";
            $_SESSION['flash_type'] = "success";
            header("Location: index.php");
            exit();
        } else {
            $message = "Error updating product: " . mysqli_error($conn);
            $message_type = "danger";
        }
    } else {
        $message = implode(", ", $errors);
        $message_type = "danger";
    }
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
      
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        /* Business Content Area */
        .business-content {
            margin-left: 280px;
            padding: 25px 35px;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s ease;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #e67e22;
            font-size: 32px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.03);
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .form-group label .required {
            color: #e74c3c;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        select.form-control {
            cursor: pointer;
            background: white;
        }

        input[type="file"].form-control {
            padding: 8px;
        }

        /* Row Grid */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Text Muted */
        .text-muted {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 6px;
            display: block;
        }

        /* Current Image */
        .current-image {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .current-image-label {
            font-size: 12px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            display: block;
        }

        .current-image img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e67e22;
            padding: 3px;
        }

        /* Image Preview */
        .image-preview-container {
            margin-top: 15px;
            display: none;
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            padding: 10px;
            background: #f8f9fa;
            text-align: center;
        }

        .image-preview-container.active {
            display: block;
        }

        .image-preview {
            position: relative;
            display: inline-block;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 10px;
            border: 2px solid #e67e22;
            padding: 5px;
            background: white;
        }

        .remove-image {
            position: absolute;
            top: -10px;
            right: -10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .remove-image:hover {
            background: #c0392b;
            transform: scale(1.1);
        }

        /* Price Input */
        .price-input-wrapper {
            position: relative;
        }

        .price-currency {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
            font-weight: 500;
            z-index: 1;
        }

        .price-input {
            padding-left: 55px !important;
        }

        /* Category Select Styling */
        select.form-control option {
            padding: 8px;
            font-size: 14px;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #e67e22;
            color: white;
        }

        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e0e0e0;
            color: #7f8c8d;
        }

        .btn-outline:hover {
            border-color: #e67e22;
            color: #e67e22;
            transform: translateY(-2px);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        /* Alert Messages */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .alert i {
            font-size: 18px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #27ae60;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #e74c3c;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .business-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }

        @media (max-width: 768px) {
            .business-content {
                padding: 70px 15px 20px 15px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .form-card {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .page-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="business-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-edit"></i> 
                Edit Product
            </h1>
            <p>Update the product information below</p>
        </div>

        <!-- Display Message -->
        <?php if(!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category_id" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <?php echo $category_options; ?>
                        </select>
                        <small class="text-muted">Select the most specific category for your product</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Describe your product in detail..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                    <small class="text-muted">Provide detailed information about the product, features, and specifications</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (TSh) <span class="required">*</span></label>
                        <div class="price-input-wrapper">
                            <span class="price-currency">TSh</span>
                            <input type="number" name="price" class="form-control price-input" placeholder="0" step="0.01" value="<?php echo $product['price']; ?>" required>
                        </div>
                        <small class="text-muted">Enter the selling price per unit</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity in Stock <span class="required">*</span></label>
                        <input type="number" name="quantity" class="form-control" placeholder="0" value="<?php echo $product['quantity_in_stock']; ?>" required>
                        <small class="text-muted">How many items do you have available?</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Unit</label>
                        <select name="unit" class="form-control">
                            <option value="piece" <?php echo $product['unit'] == 'piece' ? 'selected' : ''; ?>>Piece</option>
                            <option value="kg" <?php echo $product['unit'] == 'kg' ? 'selected' : ''; ?>>Kilogram (kg)</option>
                            <option value="gram" <?php echo $product['unit'] == 'gram' ? 'selected' : ''; ?>>Gram (g)</option>
                            <option value="liter" <?php echo $product['unit'] == 'liter' ? 'selected' : ''; ?>>Liter (L)</option>
                            <option value="dozen" <?php echo $product['unit'] == 'dozen' ? 'selected' : ''; ?>>Dozen</option>
                            <option value="pack" <?php echo $product['unit'] == 'pack' ? 'selected' : ''; ?>>Pack</option>
                            <option value="box" <?php echo $product['unit'] == 'box' ? 'selected' : ''; ?>>Box</option>
                            <option value="bottle" <?php echo $product['unit'] == 'bottle' ? 'selected' : ''; ?>>Bottle</option>
                        </select>
                        <small class="text-muted">Select the unit of measurement for this product</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Product Image</label>
                        <?php if(!empty($product['image_url'])): ?>
                            <div class="current-image">
                                <span class="current-image-label">Current Image:</span><br>
                                <img src="../../<?php echo $product['image_url']; ?>" alt="Current product image">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" id="productImage">
                        <small class="text-muted">Leave empty to keep current image. Allowed formats: JPG, JPEG, PNG, GIF, WEBP. Max size: 5MB</small>
                        
                        <!-- Image Preview Container -->
                        <div class="image-preview-container" id="imagePreviewContainer">
                            <div class="image-preview">
                                <img id="previewImg" src="#" alt="Preview">
                                <div class="remove-image" id="removeImageBtn">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get elements
        const productImageInput = document.getElementById('productImage');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const previewImg = document.getElementById('previewImg');
        const removeImageBtn = document.getElementById('removeImageBtn');

        // Image preview functionality
        if (productImageInput) {
            productImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    // Check file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Invalid file type! Please select a JPG, JPEG, PNG, GIF, or WEBP image.');
                        productImageInput.value = '';
                        imagePreviewContainer.classList.remove('active');
                        return false;
                    }
                    
                    // Check file size (5MB = 5 * 1024 * 1024 bytes)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File is too large! Maximum size is 5MB.');
                        productImageInput.value = '';
                        imagePreviewContainer.classList.remove('active');
                        return false;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(event) {
                        previewImg.src = event.target.result;
                        imagePreviewContainer.classList.add('active');
                    }
                    
                    reader.readAsDataURL(file);
                } else {
                    imagePreviewContainer.classList.remove('active');
                    previewImg.src = '#';
                }
            });
        }

        // Remove image function
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', function() {
                productImageInput.value = '';
                imagePreviewContainer.classList.remove('active');
                previewImg.src = '#';
            });
        }

        // Form validation
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const price = document.querySelector('input[name="price"]').value;
            const quantity = document.querySelector('input[name="quantity"]').value;
            const productName = document.querySelector('input[name="name"]').value;
            const category = document.querySelector('select[name="category_id"]').value;
            
            if (productName.trim() === '') {
                alert('Please enter a product name');
                e.preventDefault();
                return false;
            }
            
            if (category === '') {
                alert('Please select a category');
                e.preventDefault();
                return false;
            }
            
            if (parseFloat(price) <= 0) {
                alert('Please enter a valid price greater than 0');
                e.preventDefault();
                return false;
            }
            
            if (parseInt(quantity) < 0) {
                alert('Quantity cannot be negative');
                e.preventDefault();
                return false;
            }
            
            if (isNaN(parseFloat(price))) {
                alert('Please enter a valid price');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html> 