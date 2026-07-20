<?php
// admin/settings/get_fee.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit();
}

$sql = "SELECT * FROM delivery_rates WHERE rate_id = $id";
$result = mysqli_query($conn, $sql);

if ($row = mysqli_fetch_assoc($result)) {
    header('Content-Type: application/json');
    echo json_encode($row);
} else {
    echo json_encode(['error' => 'Fee rule not found']);
}
?>