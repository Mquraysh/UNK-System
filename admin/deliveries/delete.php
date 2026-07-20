<?php
// admin/deliveries/delete.php - DELETE DELIVERY RECORD
require_once '../../config/database.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($delivery_id > 0) {
    mysqli_query($conn, "DELETE FROM deliveries WHERE delivery_id = $delivery_id");
    $_SESSION['flash_message'] = "Delivery record deleted.";
    $_SESSION['flash_type'] = "success";
} else {
    $_SESSION['flash_message'] = "Invalid ID.";
    $_SESSION['flash_type'] = "danger";
}
header("Location: index.php");
exit();
?>