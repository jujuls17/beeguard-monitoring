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

$conn->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
