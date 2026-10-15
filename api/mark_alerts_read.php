<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
header('Content-Type: application/json');
$conn->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
