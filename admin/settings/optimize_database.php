<?php
// admin/settings/optimize_database.php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$optimized = 0;
$failed = 0;
$results = [];

$tables = $conn->query("SHOW TABLES");
while($table = $tables->fetch_row()) {
    $table_name = $table[0];
    if($conn->query("OPTIMIZE TABLE $table_name")) {
        $optimized++;
        $results[] = "✅ $table_name - Optimized successfully";
    } else {
        $failed++;
        $results[] = "❌ $table_name - Failed to optimize";
    }
}

// Store results in session for display
$_SESSION['optimize_results'] = $results;
$_SESSION['optimized_count'] = $optimized;
$_SESSION['failed_count'] = $failed;
$_SESSION['flash_message'] = "Database optimization completed: $optimized tables optimized, $failed failed";
$_SESSION['flash_type'] = $failed > 0 ? "warning" : "success";

header("Location: database.php");
exit();
?>