<?php
// admin/delivery_agent/delete.php - PERMANENTLY DELETE DELIVERY AGENT (FIXED)
require_once '../../config/database.php';
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$agent_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($agent_id <= 0) {
    $_SESSION['flash_message'] = "Invalid agent ID.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: agents.php");
    exit();
}

// Fetch agent details
$stmt = mysqli_prepare($conn, "SELECT user_id, first_name, last_name FROM delivery_agents WHERE agent_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $agent_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$agent = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$agent) {
    $_SESSION['flash_message'] = "Agent not found.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: agents.php");
    exit();
}

// Check for pending deliveries
$check_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM deliveries WHERE agent_id = ? AND status IN ('assigned', 'picked_up', 'in_transit')");
mysqli_stmt_bind_param($check_stmt, 'i', $agent_id);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);
$pending = mysqli_fetch_assoc($result)['cnt'];
mysqli_stmt_close($check_stmt);

if ($pending > 0) {
    $_SESSION['flash_message'] = "Cannot delete agent with {$pending} pending delivery(ies). Please reassign or complete them first.";
    $_SESSION['flash_type'] = 'danger';
    header("Location: agents.php");
    exit();
}

// Start transaction
mysqli_begin_transaction($conn);

// Delete from delivery_agents
$del_agent = mysqli_prepare($conn, "DELETE FROM delivery_agents WHERE agent_id = ?");
mysqli_stmt_bind_param($del_agent, 'i', $agent_id);
$agent_ok = mysqli_stmt_execute($del_agent);
mysqli_stmt_close($del_agent);

if ($agent_ok) {
    // Delete associated user
    $del_user = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($del_user, 'i', $agent['user_id']);
    $user_ok = mysqli_stmt_execute($del_user);
    mysqli_stmt_close($del_user);
    
    if ($user_ok) {
        mysqli_commit($conn);
        $_SESSION['flash_message'] = "Delivery agent " . htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) . " has been permanently deleted.";
        $_SESSION['flash_type'] = 'success';
    } else {
        mysqli_rollback($conn);
        $_SESSION['flash_message'] = "Failed to delete user account. Error: " . mysqli_error($conn);
        $_SESSION['flash_type'] = 'danger';
    }
} else {
    mysqli_rollback($conn);
    $_SESSION['flash_message'] = "Failed to delete agent profile. Error: " . mysqli_error($conn);
    $_SESSION['flash_type'] = 'danger';
}

header("Location: agents.php");
exit();
?>