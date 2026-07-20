<?php
// admin/settings/save-general.php 
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Allowed general keys
    $allowed = ['site_name','site_logo','contact_email','contact_phone','contact_address',
                'delivery_fee_default','commission_rate','low_stock_threshold',
                'about_text','facebook_url','twitter_url','instagram_url'];
    foreach ($allowed as $key) {
        if (isset($_POST[$key])) {
            $value = mysqli_real_escape_string($conn, trim($_POST[$key]));
            $check = mysqli_query($conn, "SELECT setting_id FROM settings WHERE setting_key = '$key'");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE settings SET setting_value = '$value' WHERE setting_key = '$key'");
            } else {
                mysqli_query($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
            }
        }
    }
    // Maintenance mode (checkbox)
    $maintenance = isset($_POST['maintenance_mode']) ? '1' : '0';
    mysqli_query($conn, "UPDATE settings SET setting_value = '$maintenance' WHERE setting_key = 'maintenance_mode'");
    
    // Update existing rates
    if (isset($_POST['rates']) && is_array($_POST['rates'])) {
        foreach ($_POST['rates'] as $rate_id => $data) {
            $min = (float)$data['min_distance'];
            $max = (float)$data['max_distance'];
            $fee = (float)$data['fee'];
            $desc = mysqli_real_escape_string($conn, $data['description']);
            mysqli_query($conn, "UPDATE delivery_rates SET min_distance = $min, max_distance = $max, fee = $fee, description = '$desc' WHERE rate_id = $rate_id");
        }
    }
    // Insert new rates
    if (isset($_POST['new_rates']) && is_array($_POST['new_rates'])) {
        foreach ($_POST['new_rates'] as $new) {
            if (empty($new['min_distance']) || empty($new['max_distance']) || empty($new['fee'])) continue;
            $min = (float)$new['min_distance'];
            $max = (float)$new['max_distance'];
            $fee = (float)$new['fee'];
            $desc = mysqli_real_escape_string($conn, $new['description']);
            mysqli_query($conn, "INSERT INTO delivery_rates (min_distance, max_distance, fee, description) VALUES ($min, $max, $fee, '$desc')");
        }
    }
    $_SESSION['flash_message'] = "General settings saved successfully!";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Invalid request.";
    $_SESSION['flash_type'] = "danger";
}
header("Location: general.php");
exit();
?>