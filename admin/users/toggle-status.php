<?php
// admin/users/toggle-status.php - TOGGLE USER STATUS
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_status = isset($_GET['status']) ? $_GET['status'] : '';

if ($user_id > 0 && !empty($current_status)) {
    $new_status = ($current_status == 'active') ? 'inactive' : 'active';
    
    // Ensure we don't change admin status
    $check_sql = "SELECT role FROM users WHERE user_id = '$user_id'";
    $check_result = mysqli_query($conn, $check_sql);
    $user = mysqli_fetch_assoc($check_result);
    
    if ($user && $user['role'] != 'admin') {
        $update_sql = "UPDATE users SET status = '$new_status' WHERE user_id = '$user_id'";
        mysqli_query($conn, $update_sql);
        
        $_SESSION['flash_message'] = "User status updated successfully!";
        $_SESSION['flash_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Cannot change admin status!";
        $_SESSION['flash_type'] = "danger";
    }
} else {
    $_SESSION['flash_message'] = "Invalid request!";
    $_SESSION['flash_type'] = "danger";
}

header("Location: index.php");
exit();
?>