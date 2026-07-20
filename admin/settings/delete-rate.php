<?php
// admin/settings/delete-rate.php - DELETE DELIVERY RATE
require_once '../../config/database.php';
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
if (isset($_POST['rate_id'])) {
    $rate_id = (int)$_POST['rate_id'];
    mysqli_query($conn, "DELETE FROM delivery_rates WHERE rate_id = $rate_id");
    $_SESSION['flash_message'] = "Rate deleted.";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "No rate specified.";
    $_SESSION['flash_type'] = "danger";
}
header("Location: index.php");
exit();
?>