<?php
require_once 'config.php';

// Log activity before destroying session
if (isLoggedIn()) {
    $user_id = getUserId();
    $action = 'logout';
    $log_sql = "INSERT INTO activity_logs (user_id, action, module, description) VALUES (?, ?, 'auth', 'User logged out')";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("is", $user_id, $action);
    $log_stmt->execute();
}

// Destroy session
session_destroy();

// Redirect to login
redirect('login.php');
?>