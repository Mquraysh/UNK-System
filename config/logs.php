<?php
// config/logs.php - Fixed version with session check

// Make sure session is started before using logs
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function addLog($conn, $user_id, $user_type, $action, $details = null) {
    // If user_type not provided, try to get from session
    if ((empty($user_type) || $user_type === null) && isset($_SESSION['role'])) {
        $user_type = $_SESSION['role'];
    }
    
    // If user_id not provided, try to get from session
    if ((empty($user_id) || $user_id === null) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Create table if not exists
    $create_sql = "CREATE TABLE IF NOT EXISTS system_logs (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        user_type ENUM('admin','business','customer','delivery') NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_action (action),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    @mysqli_query($conn, $create_sql);
    
    $stmt = mysqli_prepare($conn, "INSERT INTO system_logs (user_id, user_type, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $user_type, $action, $details, $ip, $user_agent);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

// Shortcut functions
function logLogin($conn, $user_id = null, $user_type = null) {
    return addLog($conn, $user_id, $user_type, 'login', 'User logged in');
}

function logLogout($conn, $user_id = null, $user_type = null) {
    return addLog($conn, $user_id, $user_type, 'logout', 'User logged out');
}

function logCreate($conn, $user_id, $user_type, $item, $name) {
    return addLog($conn, $user_id, $user_type, 'create', "Created $item: $name");
}

function logUpdate($conn, $user_id, $user_type, $item, $id) {
    return addLog($conn, $user_id, $user_type, 'update', "Updated $item ID: $id");
}

function logDelete($conn, $user_id, $user_type, $item, $name) {
    return addLog($conn, $user_id, $user_type, 'delete', "Deleted $item: $name");
}

function logView($conn, $user_id, $user_type, $page) {
    return addLog($conn, $user_id, $user_type, 'view', "Viewed $page");
}

function logError($conn, $error_message) {
    $user_id = $_SESSION['user_id'] ?? null;
    $user_type = $_SESSION['role'] ?? 'system';
    return addLog($conn, $user_id, $user_type, 'error', $error_message);
}
?>