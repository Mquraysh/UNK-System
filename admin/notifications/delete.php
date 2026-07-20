<?php
// admin/notifications/delete.php - DELETE NOTIFICATION
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM admin_notifications WHERE id = ? AND admin_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
header("Location: index.php");
exit();
?>