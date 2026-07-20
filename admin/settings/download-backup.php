<?php
// admin/settings/download-backup.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$backup_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($backup_id == 0) {
    header("Location: backup.php");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT file_path, backup_name FROM backup_logs WHERE backup_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $backup_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$backup = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$backup || !file_exists($backup['file_path'])) {
    header("Location: backup.php");
    exit();
}

// Download file
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backup['backup_name']) . '"');
header('Content-Length: ' . filesize($backup['file_path']));
readfile($backup['file_path']);
exit();
?>