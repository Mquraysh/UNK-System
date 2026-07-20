<?php
// customer/settings/profile.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current data
$stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);


// REGIONS OF TANZANIA WITH COORDINATES
$regions = [
    'arusha' => ['name' => 'Arusha', 'lat' => -3.3869, 'lng' => 36.6820],
    'dar_es_salaam' => ['name' => 'Dar es Salaam', 'lat' => -6.7924, 'lng' => 39.2083],
    'dodoma' => ['name' => 'Dodoma', 'lat' => -6.1629, 'lng' => 35.7516],
    'geita' => ['name' => 'Geita', 'lat' => -2.8725, 'lng' => 32.2325],
    'iringa' => ['name' => 'Iringa', 'lat' => -7.7667, 'lng' => 35.7000],
    'kagera' => ['name' => 'Kagera', 'lat' => -1.3000, 'lng' => 31.7000],
    'katavi' => ['name' => 'Katavi', 'lat' => -6.3333, 'lng' => 31.1667],
    'kigoma' => ['name' => 'Kigoma', 'lat' => -4.8833, 'lng' => 29.6333],
    'kilimanjaro' => ['name' => 'Kilimanjaro', 'lat' => -3.7500, 'lng' => 37.3500],
    'lindi' => ['name' => 'Lindi', 'lat' => -9.5000, 'lng' => 39.7167],
    'manyara' => ['name' => 'Manyara', 'lat' => -4.7500, 'lng' => 36.0000],
    'mara' => ['name' => 'Mara', 'lat' => -1.5000, 'lng' => 34.0000],
    'mbeya' => ['name' => 'Mbeya', 'lat' => -8.9000, 'lng' => 33.4500],
    'morogoro' => ['name' => 'Morogoro', 'lat' => -6.8333, 'lng' => 37.6667],
    'mtwara' => ['name' => 'Mtwara', 'lat' => -10.2725, 'lng' => 40.1720],
    'mwanza' => ['name' => 'Mwanza', 'lat' => -2.5167, 'lng' => 32.9000],
    'njombe' => ['name' => 'Njombe', 'lat' => -9.3333, 'lng' => 34.6667],
    'pemba_north' => ['name' => 'Pemba North', 'lat' => -5.0000, 'lng' => 39.7000],
    'pemba_south' => ['name' => 'Pemba South', 'lat' => -5.3000, 'lng' => 39.7500],
    'pwani' => ['name' => 'Pwani', 'lat' => -6.8000, 'lng' => 38.9000],
    'rukwa' => ['name' => 'Rukwa', 'lat' => -7.5000, 'lng' => 31.5000],
    'ruvuma' => ['name' => 'Ruvuma', 'lat' => -10.5000, 'lng' => 36.0000],
    'shinyanga' => ['name' => 'Shinyanga', 'lat' => -3.6615, 'lng' => 33.4251],
    'simiyu' => ['name' => 'Simiyu', 'lat' => -3.0000, 'lng' => 34.0000],
    'singida' => ['name' => 'Singida', 'lat' => -4.8167, 'lng' => 34.7500],
    'songwe' => ['name' => 'Songwe', 'lat' => -8.5000, 'lng' => 33.0000],
    'tabora' => ['name' => 'Tabora', 'lat' => -5.0167, 'lng' => 32.8000],
    'tanga' => ['name' => 'Tanga', 'lat' => -5.0667, 'lng' => 39.1000],
    'zungu' => ['name' => 'Zanzibar North', 'lat' => -5.9333, 'lng' => 39.2833],
    'zanzibar_south' => ['name' => 'Zanzibar South', 'lat' => -6.2000, 'lng' => 39.3833],
    'zanzibar_west' => ['name' => 'Zanzibar West', 'lat' => -6.1500, 'lng' => 39.2000]
];

// Check if customer has a logo
$logo_url = $customer['profile_image'] ?? null;

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $city = trim($_POST['city']);
    $region_key = trim($_POST['region'] ?? '');
    $logo_path = $customer['profile_image'] ?? null;
    
    // Get coordinates from region
    $delivery_lat = null;
    $delivery_lng = null;
    if (!empty($region_key) && isset($regions[$region_key])) {
        $delivery_lat = $regions[$region_key]['lat'];
        $delivery_lng = $regions[$region_key]['lng'];
    }
    
    if (empty($first_name) || empty($last_name)) {
        $error = "First name and last name are required.";
    } else {
        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
            $target_dir = "../../assets/uploads/customers/";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if ($_FILES['logo']['size'] <= 5*1024*1024 && in_array($ext, $allowed)) {
                $filename = 'customer_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $filename)) {
                    if (!empty($customer['profile_image']) && file_exists("../../" . $customer['profile_image'])) {
                        unlink("../../" . $customer['profile_image']);
                    }
                    $logo_path = "assets/uploads/customers/" . $filename;
                }
            } else {
                $error = "Invalid file or too large (max 5MB). Allowed: JPG, PNG, GIF, WEBP";
            }
        }
        
        if (empty($error)) {
            // Update customers table with delivery coordinates
            $stmt = mysqli_prepare($conn, "UPDATE customers SET 
                first_name = ?, 
                last_name = ?, 
                city = ?, 
                profile_image = ?,
                delivery_latitude = ?,
                delivery_longitude = ?
                WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssddi', 
                $first_name, 
                $last_name, 
                $city, 
                $logo_path,
                $delivery_lat,
                $delivery_lng,
                $user_id
            );
            $update_customer = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Update users table (phone)
            $stmt = mysqli_prepare($conn, "UPDATE users SET phone = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $phone, $user_id);
            $update_user = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            if ($update_customer && $update_user) {
                $_SESSION['full_name'] = $first_name . ' ' . $last_name;
                $_SESSION['flash_message'] = "Profile updated successfully!";
                $_SESSION['flash_type'] = "success";
                header("Location: profile.php");
                exit();
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
    
    if ($error) {
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = "danger";
        header("Location: profile.php");
        exit();
    }
}

// Handle logo removal
if (isset($_GET['remove_logo'])) {
    if (!empty($customer['profile_image']) && file_exists("../../" . $customer['profile_image'])) {
        unlink("../../" . $customer['profile_image']);
        $stmt = mysqli_prepare($conn, "UPDATE customers SET profile_image = NULL WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['flash_message'] = "Logo removed successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: profile.php");
        exit();
    }
}

// Flash message handling
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
    <title>Edit Profile - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .customer-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f5f7fb;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .customer-content { margin-left: 0; padding: 1.25rem; }
        }
        
        @media (max-width: 768px) {
            .customer-content { padding: 0.9rem; }
            .profile-container { max-width: 100%; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .logo-preview img { width: 80px; height: 80px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .btn-back { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .customer-content { padding: 0.75rem; }
            .card-body { padding: 1rem; }
            .form-control { padding: 10px 12px; font-size: 13px; }
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
            letter-spacing: -0.02em;
        }
        .page-header h1 i { color: #e67e22; }
        .page-header p { color: #64748b; font-size: 0.85rem; margin-top: 0.25rem; }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.5rem;
            background: #2c3e50;
            color: white;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 1.5rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 1.25rem 1.75rem;
            background: #fafcff;
            border-bottom: 2px solid #f0f2f6;
        }
        .card-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: #0f172a;
        }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 1.75rem; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #0f172a;
            font-size: 0.85rem;
        }
        .form-group label i {
            color: #e67e22;
            width: 20px;
            margin-right: 4px;
        }
        .form-group small {
            display: block;
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 0.3rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
        .form-control[readonly],
        .form-control:disabled {
            background: #f8fafc;
            cursor: not-allowed;
            color: #94a3b8;
        }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        
        .help-text {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .help-text i { color: #e67e22; font-size: 0.7rem; }
        
        .logo-preview {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
        }
        .logo-preview img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .logo-preview .logo-info {
            flex: 1;
        }
        .logo-preview .logo-info .name {
            font-weight: 600;
            color: #0f172a;
        }
        .logo-preview .logo-info .size {
            font-size: 0.7rem;
            color: #94a3b8;
        }
        .btn-remove-logo {
            padding: 0.4rem 0.8rem;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-remove-logo:hover {
            background: #dc2626;
            color: white;
        }
        
        .divider {
            height: 1px;
            background: #e2e8f0;
            margin: 1.25rem 0;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #e67e22, #d35400);
            color: white;
            padding: 0.9rem 1.75rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(230,126,34,0.3);
        }
        
        .alert {
            padding: 0.9rem 1.2rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .alert i { font-size: 1.1rem; }
        
        .info-box {
            background: #fef9f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            margin-top: 1.25rem;
            border: 1px solid #ffe4cc;
        }
        .info-box p {
            font-size: 0.8rem;
            color: #e67e22;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .info-box i { font-size: 1rem; }
        
        .coords-info {
            background: #ecfdf5;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            color: #065f46;
            border: 1px solid #6ee7b7;
            margin-top: 0.5rem;
            display: <?php echo !empty($customer['delivery_latitude']) ? 'block' : 'none'; ?>;
        }
        .coords-info i { margin-right: 0.3rem; }
    </style>
</head>
<body>
<div class="customer-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your personal information and delivery location</p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Settings
        </a>
    </div>
    
    <?php if ($flash_message): ?>
        <div class="alert alert-<?= $flash_type ?>">
            <i class="fas fa-<?= $flash_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- PROFILE IMAGE / LOGO UPLOAD -->
                    <div class="form-group">
                        <label><i class="fas fa-image"></i> Profile Photo</label>
                        
                        <?php if(!empty($logo_url)): ?>
                            <div class="logo-preview">
                                <img src="../../<?php echo $logo_url; ?>" alt="Profile Photo">
                                <div class="logo-info">
                                    <div class="name">Current Profile Photo</div>
                                    <div class="size">Click "Choose File" to change</div>
                                </div>
                                <a href="?remove_logo=1" class="btn-remove-logo" onclick="return confirm('Remove your profile photo?')">
                                    <i class="fas fa-trash"></i> Remove
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="logo-preview" style="background: #f1f5f9; justify-content: center; padding: 1.5rem;">
                                <div style="text-align: center;">
                                    <i class="fas fa-user-circle" style="font-size: 48px; color: #cbd5e1;"></i>
                                    <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">No profile photo uploaded</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <input type="file" name="logo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" style="padding: 8px;">
                        <small style="display: block; font-size: 0.7rem; color: #94a3b8; margin-top: 0.3rem;">
                            <i class="fas fa-info-circle"></i> 
                            Leave empty to keep current photo. Allowed: JPG, PNG, GIF, WEBP. Max: 5MB
                        </small>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <!-- PERSONAL INFORMATION -->
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($customer['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($customer['last_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> Email cannot be changed. Contact support if needed.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        <div class="help-text">
                            <i class="fas fa-mobile-alt"></i> Format: 0712345678 or +25512345678
                        </div>
                    </div>
                    
                    <!-- <div class="form-group">
                        <label><i class="fas fa-city"></i> City / Location</label>
                        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city']) ?>" required>
                        <div class="help-text">
                            <i class="fas fa-map-marker-alt"></i> e.g., Dar es Salaam, Arusha, Mwanza, etc.
                        </div>
                    </div> -->
   
                    <!-- REGION DROPDOWN FOR DELIVERY LOCATION -->
                    <div class="form-group">
                        <label><i class="fas fa-map-pin"></i> Delivery Region <span class="required" style="color: #e74c3c;">*</span></label>
                        <select name="region" class="form-control" id="regionSelect" required>
                            <option value="">-- Select Your Delivery Region --</option>
                            <?php foreach ($regions as $key => $region): 
                                $selected = '';
                                if (!empty($customer['delivery_latitude']) && !empty($customer['delivery_longitude'])) {
                                    if ($customer['delivery_latitude'] == $region['lat'] && $customer['delivery_longitude'] == $region['lng']) {
                                        $selected = 'selected';
                                    }
                                }
                            ?>
                                <option value="<?php echo $key; ?>" data-lat="<?php echo $region['lat']; ?>" data-lng="<?php echo $region['lng']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Select your delivery region for accurate delivery coordination</small>
                    </div>

                    <!-- Display Current Delivery Coordinates -->
                    <div class="coords-info" id="coordsInfo">
                        <i class="fas fa-map-pin"></i> 
                        Delivery Coordinates: 
                        <span id="coordDisplay">
                            <?php 
                            if (!empty($customer['delivery_latitude']) && !empty($customer['delivery_longitude'])) {
                                echo $customer['delivery_latitude'] . ', ' . $customer['delivery_longitude'];
                            } else {
                                echo 'Not set - Please select a region above';
                            }
                            ?>
                        </span>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <button type="submit" name="update_profile" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
                
                <div class="info-box">
                    <p>
                        <i class="fas fa-shield-alt"></i> 
                        Your information is secure and will only be used for order processing and delivery.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview logo before upload
document.querySelector('input[name="logo"]')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            const preview = document.querySelector('.logo-preview img');
            if (preview) {
                preview.src = event.target.result;
            } else {
                const container = document.querySelector('.logo-preview');
                if (container) {
                    container.innerHTML = `<img src="${event.target.result}" alt="Preview" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:2px solid #e2e8f0;">`;
                }
            }
        };
        reader.readAsDataURL(file);
    }
});

// Update coordinates display when region is selected
document.getElementById('regionSelect')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const lat = selectedOption.getAttribute('data-lat');
    const lng = selectedOption.getAttribute('data-lng');
    const coordDisplay = document.getElementById('coordDisplay');
    const coordsInfo = document.getElementById('coordsInfo');
    
    if (lat && lng) {
        coordDisplay.textContent = lat + ', ' + lng;
        coordsInfo.style.display = 'block';
        coordsInfo.style.background = '#ecfdf5';
        coordsInfo.style.borderColor = '#6ee7b7';
        coordsInfo.style.color = '#065f46';
    } else {
        coordDisplay.textContent = 'Not set - Please select a region above';
    }
});
</script>
</body>
</html>