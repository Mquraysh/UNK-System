<?php
// admin/settings/export_database.php - Professional Database Backup
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// ============================================================
// CONFIGURATION
// ============================================================
$backup_dir = '../../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Check if we should save to file or download directly
$save_to_file = isset($_GET['save']) && $_GET['save'] == 'file';
$filename = 'unk_system_backup_' . date('Y-m-d_H-i-s') . '.sql';

// ============================================================
// GET ALL TABLES
// ============================================================
$tables = [];
$result = $conn->query("SHOW TABLES");
if (!$result) {
    die("Error getting tables: " . $conn->error);
}

while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// ============================================================
// BUILD BACKUP CONTENT
// ============================================================
$output = "";
$output .= "-- =============================================\n";
$output .= "-- UNK SYSTEM DATABASE BACKUP\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Database: " . $conn->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
$output .= "-- Tables: " . count($tables) . "\n";
$output .= "-- =============================================\n\n";

$output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$output .= "SET AUTOCOMMIT = 0;\n";
$output .= "START TRANSACTION;\n";
$output .= "SET time_zone = '+00:00';\n\n";

// Disable foreign key checks
$output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// ============================================================
// PROCESS EACH TABLE
// ============================================================
$total_tables = count($tables);
$current_table = 0;

foreach ($tables as $table) {
    $current_table++;
    $output .= "-- --------------------------------------------------------\n";
    $output .= "-- Table: `$table` ($current_table of $total_tables)\n";
    $output .= "-- --------------------------------------------------------\n\n";
    
    // Get create table statement
    $create_result = $conn->query("SHOW CREATE TABLE `$table`");
    if (!$create_result) {
        $output .= "-- ERROR: Could not get structure for table `$table`\n\n";
        continue;
    }
    
    $create_row = $create_result->fetch_assoc();
    $create_sql = $create_row['Create Table'] ?? '';
    
    // Drop table if exists
    $output .= "DROP TABLE IF EXISTS `$table`;\n";
    $output .= $create_sql . ";\n\n";
    
    // Get table data (limit to prevent memory issues)
    $data_result = $conn->query("SELECT * FROM `$table`");
    if ($data_result && $data_result->num_rows > 0) {
        $output .= "--\n-- Dumping data for table `$table` (" . $data_result->num_rows . " rows)\n--\n\n";
        
        $row_count = 0;
        $batch_size = 100;
        $values_batch = [];
        
        while ($row = $data_result->fetch_assoc()) {
            $row_count++;
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = "NULL";
                } else {
                    $values[] = "'" . mysqli_real_escape_string($conn, $value) . "'";
                }
            }
            $values_batch[] = "(" . implode(", ", $values) . ")";
            
            // Insert in batches to avoid memory issues
            if (count($values_batch) >= $batch_size) {
                $output .= "INSERT INTO `$table` VALUES \n";
                $output .= implode(",\n", $values_batch) . ";\n";
                $values_batch = [];
            }
        }
        
        // Insert remaining rows
        if (!empty($values_batch)) {
            $output .= "INSERT INTO `$table` VALUES \n";
            $output .= implode(",\n", $values_batch) . ";\n";
        }
        
        $output .= "\n";
    } else {
        $output .= "-- No data found for table `$table`\n\n";
    }
    
    // Free result
    if ($data_result) {
        $data_result->free();
    }
}

// Re-enable foreign key checks
$output .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
$output .= "COMMIT;\n";

// ============================================================
// OUTPUT THE BACKUP
// ============================================================
if ($save_to_file) {
    // Save to file on server
    $filepath = $backup_dir . $filename;
    if (file_put_contents($filepath, $output)) {
        $_SESSION['flash_message'] = "✅ Backup saved successfully! File: " . $filename;
        $_SESSION['flash_type'] = "success";
        header("Location: database.php");
        exit();
    } else {
        $_SESSION['flash_message'] = "❌ Failed to save backup file.";
        $_SESSION['flash_type'] = "danger";
        header("Location: database.php");
        exit();
    }
} else {
    // Download directly
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($output));
    header('Pragma: no-cache');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    
    echo $output;
    exit();
}
?>