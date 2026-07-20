<?php
// admin/businesses/toggle-status.php - TOGGLE BUSINESS ACCOUNT STATUS
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($business_id <= 0) {
    $_SESSION['flash_message'] = "Invalid business ID.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

// Get current user_id and status
$stmt = mysqli_prepare($conn, "SELECT u.user_id, u.status FROM businesses b JOIN users u ON b.user_id = u.user_id WHERE b.business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $business_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data) {
    $_SESSION['flash_message'] = "Business not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$new_status = ($data['status'] === 'active') ? 'inactive' : 'active';

$stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'si', $new_status, $data['user_id']);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = "Account " . ($new_status === 'active' ? "activated" : "deactivated") . " successfully.";
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = "Failed to update account status.";
    $_SESSION['flash_type'] = 'danger';
}
mysqli_stmt_close($stmt);

header("Location: index.php");
exit();
?>