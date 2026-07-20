<?php
// admin/settings/get_db_size.php - GET DATABASE SIZE
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die(json_encode(['size' => 'Access denied']));
}

$total_size = 0;
$tables = $conn->query("SHOW TABLE STATUS");
if($tables) {
    while($table = $tables->fetch_assoc()) {
        $total_size += ($table['Data_length'] + $table['Index_length']);
    }
}

$size = $total_size;
$units = ['B', 'KB', 'MB', 'GB', 'TB'];
$i = 0;
while ($size >= 1024 && $i < count($units) - 1) {
    $size /= 1024;
    $i++;
}

$size_formatted = round($size, 2) . ' ' . $units[$i];

echo json_encode(['size' => $size_formatted]);
?>