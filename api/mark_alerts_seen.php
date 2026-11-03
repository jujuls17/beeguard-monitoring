<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();

header('Content-Type: application/json');
ob_clean();

$uid = (int)$_SESSION['user_id'];
$conn->query("INSERT INTO user_settings (user_id, last_alerts_seen)
              VALUES ($uid, NOW())
              ON DUPLICATE KEY UPDATE last_alerts_seen = NOW()");

echo json_encode(['success' => true]);
