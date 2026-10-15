<?php
session_start();
require_once 'config.php';

// Log logout before session is destroyed
if (isset($_SESSION['user_id'])) {
    $uid   = (int)$_SESSION['user_id'];
    $uname = $conn->real_escape_string($_SESSION['username'] ?? '');
    $conn->query("INSERT INTO activity_log (user_id, event_type, details, severity)
                  VALUES ($uid, 'user_logout', 'User logged out: $uname', 'info')");
}

$_SESSION = [];
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}
session_destroy();
header('Location: login.php');
exit();
