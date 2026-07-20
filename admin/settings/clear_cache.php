<?php
// admin/settings/clear_cache.php 
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$cleared_items = [];

// Clear opcache if enabled
if(function_exists('opcache_reset')) {
    opcache_reset();
    $cleared_items[] = "OPcache cleared";
}

// Clear session cache
session_regenerate_id(true);
$cleared_items[] = "Session cache cleared";

// Clear temporary files
$temp_dirs = [
    __DIR__ . '/../../assets/temp/',
    __DIR__ . '/../../assets/cache/'
];

foreach($temp_dirs as $dir) {
    if(file_exists($dir)) {
        $files = glob($dir . '*');
        foreach($files as $file) {
            if(is_file($file)) {
                unlink($file);
            }
        }
        $cleared_items[] = "Cleared: " . basename($dir);
    }
}

$_SESSION['flash_message'] = "Cache cleared successfully: " . implode(", ", $cleared_items);
$_SESSION['flash_type'] = "success";
header("Location: database.php");
exit();
?>