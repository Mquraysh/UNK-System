<?php
// business/settings/general.php - WITH LATITUDE AND LONGITUDE
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

$user_sql = "SELECT * FROM users WHERE user_id = '$user_id'";
$user_result = mysqli_query($conn, $user_sql);
$user = mysqli_fetch_assoc($user_result);

$flash_message = '';
$flash_type = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}


// ============================================
// CHECK AND ADD LATITUDE & LONGITUDE COLUMNS
// ============================================
$check_lat = mysqli_query($conn, "SHOW COLUMNS FROM businesses LIKE 'latitude'");
if (mysqli_num_rows($check_lat) == 0) {
    mysqli_query($conn, "ALTER TABLE businesses ADD COLUMN latitude DECIMAL(10,8) NULL");
}

$check_lng = mysqli_query($conn, "SHOW COLUMNS FROM businesses LIKE 'longitude'");
if (mysqli_num_rows($check_lng) == 0) {
    mysqli_query($conn, "ALTER TABLE businesses ADD COLUMN longitude DECIMAL(11,8) NULL");
}

// ============================================
// REGIONS OF TANZANIA WITH COORDINATES
// ============================================
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $business_name = mysqli_real_escape_string($conn, trim($_POST['business_name']));
    $location = mysqli_real_escape_string($conn, trim($_POST['location']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $registration_number = mysqli_real_escape_string($conn, trim($_POST['registration_number']));
    $license_number = mysqli_real_escape_string($conn, trim($_POST['license_number']));
    $nida_number = mysqli_real_escape_string($conn, trim($_POST['nida_number']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $business_hours = mysqli_real_escape_string($conn, trim($_POST['business_hours']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    
    // Get latitude and longitude from region selection
    $region_key = trim($_POST['region'] ?? '');
    $latitude = null;
    $longitude = null;
    if (!empty($region_key) && isset($regions[$region_key])) {
        $latitude = $regions[$region_key]['lat'];
        $longitude = $regions[$region_key]['lng'];
    }
    
    // Validate NIDA format (if provided, must be 20 digits)
    if (!empty($nida_number) && !preg_match('/^\d{20}$/', $nida_number)) {
        $error = "NIDA number must be exactly 20 digits (0-9).";
        $_SESSION['flash_message'] = $error;
        $_SESSION['flash_type'] = "danger";
        header("Location: general.php");
        exit();
    }
    
    // Update business table - WITH LATITUDE AND LONGITUDE
    $update_business = "UPDATE businesses SET 
                        business_name = '$business_name',
                        location = '$location',
                        address = '$address',
                        city = '$city',
                        registration_number = '$registration_number',
                        license_number = '$license_number',
                        nida_number = '$nida_number',
                        description = '$description',
                        business_hours = '$business_hours',
                        latitude = " . ($latitude !== null ? "'$latitude'" : "NULL") . ",
                        longitude = " . ($longitude !== null ? "'$longitude'" : "NULL") . "
                        WHERE business_id = '{$business['business_id']}'";
    mysqli_query($conn, $update_business);
    
    // Update users table for phone
    $update_user = "UPDATE users SET phone = '$phone' WHERE user_id = '$user_id'";
    mysqli_query($conn, $update_user);
    
    // Handle logo upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $target_dir = "../../assets/uploads/businesses/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed) && $_FILES['logo']['size'] <= 5 * 1024 * 1024) {
            $filename = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                $logo_url = "assets/uploads/businesses/" . $filename;
                
                if (!empty($business['logo_url']) && file_exists("../../" . $business['logo_url'])) {
                    unlink("../../" . $business['logo_url']);
                }
                
                $update_logo = "UPDATE businesses SET logo_url = '$logo_url' WHERE business_id = '{$business['business_id']}'";
                mysqli_query($conn, $update_logo);
            }
        }
    }
    
    $_SESSION['flash_message'] = "General settings updated successfully!";
    $_SESSION['flash_type'] = "success";
    header("Location: general.php");
    exit();
}

include '../includes/business_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Settings - UNK System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
         * { margin: 0; padding: 0; box-sizing: border-box; }
         body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .business-content { margin-left: 280px; padding: 30px 35px; min-height: 100vh; background: #f0f2f5; }
        .page-header { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #e67e22; font-size: 32px; }
        .page-header p { color: #64748b; margin-top: 5px; }
        .btn-back { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
        .card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; max-width: 1200px; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        .card-header h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; color: #2c3e50; }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 28px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 13px; }
        .form-group label .required { color: #e74c3c; }
        .form-group label i { color: #e67e22; margin-right: 5px; width: 18px; }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 14px; transition: all 0.2s; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #e67e22; box-shadow: 0 0 0 3px rgba(230,126,34,0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        input:disabled { background: #f1f5f9; cursor: not-allowed; }
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }
        
        .logo-preview { margin-bottom: 15px; }
        .logo-preview img { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; border: 2px solid #e2e8f0; }
        
        .btn-save { background: #e67e22; color: white; border: none; padding: 12px 28px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-save:hover { background: #d35400; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(230,126,34,0.3); }
        
        .alert { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        small { font-size: 11px; color: #94a3b8; display: block; margin-top: 5px; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-verified { background: #d1fae5; color: #059669; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-row-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .form-row-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        
        .info-text { font-size: 12px; color: #64748b; margin-top: 8px; padding: 8px; background: #f8fafc; border-radius: 8px; }
        
        .coords-info {
            background: #ecfdf5;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #065f46;
            border: 1px solid #6ee7b7;
            margin-top: 5px;
        }
        .coords-info i { margin-right: 5px; }
        
        @media (max-width: 1024px) { .business-content { margin-left: 0; padding: 20px; } }
        @media (max-width: 768px) { 
            .page-header { flex-direction: column; text-align: center; } 
            .btn-save { width: 100%; justify-content: center; } 
            .card-body { padding: 20px; }
            .form-row, .form-row-3, .form-row-4 { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>
<div class="business-content">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-store"></i> General Settings</h1>
            <p>Update your business information and location</p>
        </div>
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    </div>
    
    <?php if (!empty($flash_message)): ?>
        <div class="alert alert-<?php echo $flash_type; ?>">
            <i class="fas fa-<?php echo $flash_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Business Information</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <!-- Business Name -->
                <div class="form-group">
                    <label><i class="fas fa-store"></i> Business Name <span class="required">*</span></label>
                    <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($business['business_name']); ?>" required>
                </div>
                
                <!-- Location & Address -->
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Location / Area <span class="required">*</span></label>
                        <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($business['location']); ?>" placeholder="e.g., Kariakoo, Posta, Mchikichini" required>
                        <small>Area or neighborhood where your business is located</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-road"></i> Street Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($business['address']); ?>" placeholder="e.g., Soko Kuu, Jengo la Posta, Ghorofa ya 2">
                        <small>Detailed street address (optional)</small>
                    </div>
                </div>
                
                <!-- City & Phone -->
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-city"></i> City <span class="required">*</span></label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($business['city']); ?>" placeholder="e.g., Dar es Salaam, Arusha, Mwanza" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone-alt"></i> Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                </div>
                
                <!-- Registration & License & NIDA -->
                <div class="form-row-3">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Registration Number</label>
                        <input type="text" name="registration_number" class="form-control" value="<?php echo htmlspecialchars($business['registration_number']); ?>" placeholder="e.g., BLA-2024-00123">
                        <small>Business Registration Number (BRELA)</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-certificate"></i> License Number</label>
                        <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($business['license_number']); ?>" placeholder="e.g., TIN-123456789">
                        <small>Business License / TIN Number</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-fingerprint"></i> NIDA Number</label>
                        <input type="text" name="nida_number" class="form-control" value="<?php echo htmlspecialchars($business['nida_number']); ?>" placeholder="20 digits NIDA number" maxlength="20">
                        <small>Your 20‑digit National ID (NIDA) – Required for verification</small>
                    </div>
                </div>
                
                <!-- ============================================
                REGION DROPDOWN - LATITUDE & LONGITUDE
                ============================================ -->
                <div class="form-group">
                    <label><i class="fas fa-globe-africa"></i> Business Region <span class="required">*</span></label>
                    <select name="region" class="form-control" id="regionSelect" required>
                        <option value="">-- Select Your Business Region --</option>
                        <?php foreach ($regions as $key => $region): 
                            $selected = '';
                            if (!empty($business['latitude']) && !empty($business['longitude'])) {
                                if ($business['latitude'] == $region['lat'] && $business['longitude'] == $region['lng']) {
                                    $selected = 'selected';
                                }
                            }
                        ?>
                            <option value="<?php echo $key; ?>" data-lat="<?php echo $region['lat']; ?>" data-lng="<?php echo $region['lng']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Select your business region for location services and delivery coordination</small>
                </div>

                <!-- Display Current Coordinates -->
                <div class="coords-info" id="coordsInfo">
                    <i class="fas fa-map-pin"></i> 
                    Business Coordinates: 
                    <span id="coordDisplay">
                        <?php 
                        if (!empty($business['latitude']) && !empty($business['longitude'])) {
                            echo $business['latitude'] . ', ' . $business['longitude'];
                        } else {
                            echo 'Not set - Please select a region above';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- Email (disabled) -->
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>Email cannot be changed</small>
                </div>
                
                <!-- Business Hours -->
                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Business Hours</label>
                    <input type="text" name="business_hours" class="form-control" value="<?php echo htmlspecialchars($business['business_hours']); ?>" placeholder="e.g., Mon-Fri: 9AM-6PM, Sat: 10AM-4PM">
                    <small>Optional: Set your operating hours</small>
                </div>
                
                <!-- Business Description -->
                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Business Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Describe your business..."><?php echo htmlspecialchars($business['description']); ?></textarea>
                    <small>Tell customers about your business and what you offer</small>
                </div>
                
                <!-- Business Logo -->
                <div class="form-group">
                    <label><i class="fas fa-image"></i> Business Logo</label>
                    <?php if(!empty($business['logo_url'])): ?>
                        <div class="logo-preview">
                            <img src="../../<?php echo $business['logo_url']; ?>" alt="Business Logo">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                    <small>Leave empty to keep current logo. Allowed: JPG, PNG, GIF, WEBP. Max: 5MB</small>
                </div>
                
                <!-- Verification Status -->
                <div class="form-group">
                    <label><i class="fas fa-shield-alt"></i> Verification Status</label>
                    <?php if($business['is_verified']): ?>
                        <span class="badge badge-verified"><i class="fas fa-check-circle"></i> Verified Business</span>
                    <?php else: ?>
                        <span class="badge badge-pending"><i class="fas fa-clock"></i> Pending Verification</span>
                        <div class="info-text">
                            <i class="fas fa-info-circle"></i> Complete your registration number, license number, and NIDA to speed up verification.
                        </div>
                    <?php endif; ?>
                    <small>Verification helps build trust with customers</small>
                </div>
                
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
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