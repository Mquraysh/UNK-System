<?php
// delivery/settings/profile.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'delivery') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current data - USING current_latitude and current_longitude
$agent_sql = "SELECT agent_id, first_name, last_name, phone, location, vehicle_type, 
                     vehicle_registration, license_number, profile_image, is_available,
                     current_latitude, current_longitude
              FROM delivery_agents WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $agent_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$agent_result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($agent_result);
mysqli_stmt_close($stmt);

$user_sql = "SELECT email, phone, full_name FROM users WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

$error = '';
$success = '';

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

// Check if profile_image column exists
$col_check = mysqli_query($conn, "SHOW COLUMNS FROM delivery_agents LIKE 'profile_image'");
$has_profile_image = mysqli_num_rows($col_check) > 0;

// Check if current_location columns exist
$lat_check = mysqli_query($conn, "SHOW COLUMNS FROM delivery_agents LIKE 'current_latitude'");
$has_current_location = mysqli_num_rows($lat_check) > 0;

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "../../assets/uploads/profiles/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, GIF, and WEBP images are allowed.";
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $error = "Image size must be less than 2MB.";
        } else {
            $filename = 'delivery_' . $user_id . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                if (!empty($agent['profile_image']) && file_exists('../../' . $agent['profile_image'])) {
                    unlink('../../' . $agent['profile_image']);
                }
                
                $image_path = 'assets/uploads/profiles/' . $filename;
                $update_sql = "UPDATE delivery_agents SET profile_image = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "si", $image_path, $user_id);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['flash_message'] = "Profile image updated successfully!";
                    $_SESSION['flash_type'] = "success";
                    header("Location: profile.php");
                    exit();
                } else {
                    $error = "Failed to update profile image in database.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Failed to upload image. Please try again.";
            }
        }
    } else {
        $error = "Please select an image to upload.";
    }
}

// Handle profile update - WITH CURRENT LOCATION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_registration = trim($_POST['vehicle_registration'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $region_key = trim($_POST['region'] ?? '');

    // Validation
    if (empty($first_name) || empty($last_name)) {
        $error = "First name and last name are required.";
    } elseif (!preg_match('/^(?:\+255|0)[67]\d{8}$/', $phone)) {
        $error = "Please enter a valid Tanzanian phone number (e.g., 0712345678 or +255712345678).";
    } else {
        // Get coordinates from region if selected
        $current_lat = null;
        $current_lng = null;
        if (!empty($region_key) && isset($regions[$region_key])) {
            $current_lat = $regions[$region_key]['lat'];
            $current_lng = $regions[$region_key]['lng'];
        }

        // Build update query based on available columns
        $update_fields = "first_name = ?, last_name = ?, location = ?, vehicle_type = ?, vehicle_registration = ?, license_number = ?";
        $params = [$first_name, $last_name, $location, $vehicle_type, $vehicle_registration, $license_number];
        $types = "ssssss";

        // Add current location coordinates if columns exist
        if ($has_current_location) {
            $update_fields .= ", current_latitude = ?, current_longitude = ?";
            $params[] = $current_lat;
            $params[] = $current_lng;
            $types .= "dd";
        }

        $params[] = $user_id;
        $types .= "i";

        // Update delivery_agents table
        $update_agent = "UPDATE delivery_agents SET $update_fields WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_agent);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $agent_updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update users table (phone)
        $update_user = "UPDATE users SET phone = ? WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $update_user);
        mysqli_stmt_bind_param($stmt, "si", $phone, $user_id);
        $user_updated = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($agent_updated && $user_updated) {
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

// Handle remove profile image
if (isset($_GET['remove_image']) && $has_profile_image) {
    if (!empty($agent['profile_image']) && file_exists('../../' . $agent['profile_image'])) {
        unlink('../../' . $agent['profile_image']);
    }
    $update_sql = "UPDATE delivery_agents SET profile_image = NULL WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $_SESSION['flash_message'] = "Profile image removed.";
    $_SESSION['flash_type'] = "success";
    header("Location: profile.php");
    exit();
}

include '../includes/delivery_sidebar.php';
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
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        
        .delivery-content {
            margin-left: 280px;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            background: #f0f2f5;
            transition: all 0.3s;
        }
        
        @media (max-width: 1024px) {
            .delivery-content { margin-left: 0; padding: 1.25rem; }
        }
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
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
        
        .btn-back {
            background: #2c3e50;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-back:hover { background: #1a252f; transform: translateY(-2px); }
        
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
        
        .card {
            background: white;
            border-radius: 1.25rem;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.3s;
        }
        .card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        .card-header {
            padding: 1rem 1.25rem;
            background: #fafcff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .card-header h3 {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-header h3 i { color: #e67e22; }
        .card-body { padding: 1.25rem; }
        
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
        .btn-primary {
            background: #e67e22;
            color: white;
        }
        .btn-primary:hover {
            background: #d35400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(230,126,34,0.3);
        }
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.8rem;
            color: #475569;
            margin-bottom: 0.3rem;
        }
        .form-group label .required { color: #dc2626; }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            transition: all 0.2s;
            background: white;
        }
        .form-control:focus {
            outline: none;
            border-color: #e67e22;
            box-shadow: 0 0 0 3px rgba(230,126,34,0.1);
        }
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
            gap: 1rem;
        }
        .help-text {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 0.25rem;
            display: block;
        }
        
        /* Profile Image */
        .profile-image-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .profile-image-container {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid #e67e22;
            flex-shrink: 0;
            background: #f1f5f9;
        }
        .profile-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-image-container .no-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #94a3b8;
        }
        .profile-image-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .profile-image-actions .btn { font-size: 0.7rem; }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .sql-note {
            background: #fef3c7;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            color: #92400e;
            border: 1px solid #fde68a;
            margin-bottom: 1rem;
        }
        .sql-note code {
            background: #fef3c7;
            padding: 0.1rem 0.3rem;
            border-radius: 0.2rem;
        }
        
        /* Region selected display */
        .region-coords {
            background: #ecfdf5;
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            color: #065f46;
            border: 1px solid #6ee7b7;
            margin-top: 0.25rem;
            display: <?php echo !empty($agent['current_latitude']) ? 'block' : 'none'; ?>;
        }
        
        @media (max-width: 768px) {
            .delivery-content { padding: 0.9rem; }
            .form-row { grid-template-columns: 1fr; }
            .profile-image-section { flex-direction: column; align-items: center; text-align: center; }
            .profile-image-actions { justify-content: center; }
        }
        @media (max-width: 480px) {
            .delivery-content { padding: 0.5rem; }
            .card-body { padding: 0.9rem; }
        }
    </style>
</head>
<body>
<div class="delivery-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your personal information and current location</p>
        </div>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Settings
        </a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'success'; ?>">
            <i class="fas fa-<?php echo $_SESSION['flash_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php 
    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM delivery_agents LIKE 'profile_image'");
    if (mysqli_num_rows($col_check) == 0): 
    ?>
    <div class="sql-note">
        <i class="fas fa-info-circle"></i> 
        <strong>Note:</strong> To enable profile images, run this SQL:
        <br><code>ALTER TABLE delivery_agents ADD COLUMN profile_image VARCHAR(255) NULL;</code>
    </div>
    <?php endif; ?>

    <?php if (!$has_current_location): ?>
    <div class="sql-note">
        <i class="fas fa-info-circle"></i> 
        <strong>Note:</strong> To enable current location, run this SQL:
        <br><code>ALTER TABLE delivery_agents ADD COLUMN current_latitude DECIMAL(10,8) NULL, ADD COLUMN current_longitude DECIMAL(11,8) NULL;</code>
    </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
        </div>
        <div class="card-body">
            <!-- Profile Image Section -->
            <div class="profile-image-section">
                <div class="profile-image-container">
                    <?php 
                    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM delivery_agents LIKE 'profile_image'");
                    if (mysqli_num_rows($col_check) > 0 && !empty($agent['profile_image']) && file_exists('../../' . $agent['profile_image'])): 
                    ?>
                        <img src="../../<?php echo htmlspecialchars($agent['profile_image']); ?>" alt="Profile Image">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-image-actions">
                    <form method="POST" enctype="multipart/form-data" style="display:inline;">
                        <div class="file-input-wrapper btn btn-primary" style="display:inline-flex;">
                            <i class="fas fa-upload"></i> Upload Photo
                            <input type="file" name="profile_image" accept="image/*" onchange="this.form.submit()">
                        </div>
                        <input type="hidden" name="upload_image" value="1">
                    </form>
                    <?php 
                    $col_check = mysqli_query($conn, "SHOW COLUMNS FROM delivery_agents LIKE 'profile_image'");
                    if (mysqli_num_rows($col_check) > 0 && !empty($agent['profile_image'])): 
                    ?>
                        <a href="?remove_image=1" class="btn btn-danger" onclick="return confirm('Remove profile image?')">
                            <i class="fas fa-trash-alt"></i> Remove
                        </a>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.65rem; color:#94a3b8;">
                    <i class="fas fa-info-circle"></i> Max 2MB • JPG, PNG, GIF, WEBP
                </div>
            </div>

            <!-- Profile Form -->
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($agent['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($agent['last_name']); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <span class="help-text"><i class="fas fa-info-circle"></i> Email cannot be changed. Contact support if needed.</span>
                </div>

                <div class="form-group">
                    <label>Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    <span class="help-text">Format: 0712345678 or +255712345678</span>
                </div>

                <div class="form-group">
                    <label>Location / Area</label>
                    <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($agent['location']); ?>" placeholder="e.g., Kariakoo, Posta, Kinondoni">
                    <span class="help-text">Where you are currently based for deliveries (optional)</span>
                </div>

                <!-- REGION DROPDOWN FOR CURRENT LOCATION -->
                <div class="form-group">
                    <label>Current Region <span class="required">*</span></label>
                    <select name="region" class="form-control" id="regionSelect" required>
                        <option value="">-- Select Your Current Region --</option>
                        <?php foreach ($regions as $key => $region): 
                            $selected = '';
                            // Check if current agent region matches
                            if (!empty($agent['current_latitude']) && !empty($agent['current_longitude'])) {
                                if ($agent['current_latitude'] == $region['lat'] && $agent['current_longitude'] == $region['lng']) {
                                    $selected = 'selected';
                                }
                            }
                        ?>
                            <option value="<?php echo $key; ?>" data-lat="<?php echo $region['lat']; ?>" data-lng="<?php echo $region['lng']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($region['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="help-text">Select your current region for delivery coordination</span>
                </div>

                <!-- Display current coordinates -->
                <div class="region-coords" id="regionCoords">
                    <i class="fas fa-map-pin"></i> 
                    Current Coordinates: 
                    <span id="coordDisplay">
                        <?php 
                        if (!empty($agent['current_latitude']) && !empty($agent['current_longitude'])) {
                            echo $agent['current_latitude'] . ', ' . $agent['current_longitude'];
                        } else {
                            echo 'Not set';
                        }
                        ?>
                    </span>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Vehicle Type</label>
                        <select name="vehicle_type" class="form-control">
                            <option value="">Select vehicle type</option>
                            <option value="motorcycle" <?php echo ($agent['vehicle_type'] ?? '') == 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                            <option value="bicycle" <?php echo ($agent['vehicle_type'] ?? '') == 'bicycle' ? 'selected' : ''; ?>>Bicycle</option>
                            <option value="car" <?php echo ($agent['vehicle_type'] ?? '') == 'car' ? 'selected' : ''; ?>>Car</option>
                            <option value="van" <?php echo ($agent['vehicle_type'] ?? '') == 'van' ? 'selected' : ''; ?>>Van</option>
                            <option value="truck" <?php echo ($agent['vehicle_type'] ?? '') == 'truck' ? 'selected' : ''; ?>>Truck</option>
                            <option value="walking" <?php echo ($agent['vehicle_type'] ?? '') == 'walking' ? 'selected' : ''; ?>>Walking</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Registration</label>
                        <input type="text" name="vehicle_registration" class="form-control" value="<?php echo htmlspecialchars($agent['vehicle_registration'] ?? ''); ?>" placeholder="e.g., T123ABC">
                    </div>
                </div>

                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($agent['license_number'] ?? ''); ?>" placeholder="e.g., DL12345678">
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:0.75rem;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-submit image upload form
document.querySelector('.file-input-wrapper input[type="file"]')?.addEventListener('change', function() {
    if (this.files.length > 0) {
        this.closest('form').submit();
    }
});

// Update coordinates display when region is selected
document.getElementById('regionSelect')?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const lat = selectedOption.getAttribute('data-lat');
    const lng = selectedOption.getAttribute('data-lng');
    const coordDisplay = document.getElementById('coordDisplay');
    const regionCoords = document.getElementById('regionCoords');
    
    if (lat && lng) {
        coordDisplay.textContent = lat + ', ' + lng;
        regionCoords.style.display = 'block';
        regionCoords.style.background = '#ecfdf5';
        regionCoords.style.borderColor = '#6ee7b7';
        regionCoords.style.color = '#065f46';
    } else {
        coordDisplay.textContent = 'Not set';
        regionCoords.style.display = 'none';
    }
});
</script>

</body>
</html>