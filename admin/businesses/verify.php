<?php
// admin/businesses/verify.php - TOGGLE BUSINESS VERIFICATION
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$business_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($business_id <= 0 || !in_array($action, ['verify', 'unverify'])) {
    $_SESSION['flash_message'] = "Invalid request.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: index.php");
    exit();
}

$new_verified = ($action === 'verify') ? 1 : 0;

$stmt = mysqli_prepare($conn, "UPDATE businesses SET is_verified = ? WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'ii', $new_verified, $business_id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = "Business " . ($new_verified ? "verified" : "unverified") . " successfully.";
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = "Failed to update verification status.";
    $_SESSION['flash_type'] = 'danger';
}
mysqli_stmt_close($stmt);

header("Location: index.php");
exit();
?>