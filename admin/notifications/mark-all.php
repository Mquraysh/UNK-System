<?php
// admin/notifications/mark-all.php - MARK ALL NOTIFICATIONS AS READ
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$stmt = mysqli_prepare($conn, "UPDATE admin_notifications SET is_read = 1 WHERE admin_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
header("Location: index.php");
exit();
?>