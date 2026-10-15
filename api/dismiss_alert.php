<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$alertId = (int)($_POST['alert_id'] ?? 0);
if (!$alertId) {
    echo json_encode(['success' => false, 'message' => 'Missing alert ID.']);
    exit;
}

$stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
$stmt->bind_param("i", $alertId);
$stmt->execute();
$stmt->close();

$uname = $_SESSION['username'] ?? 'user';
logActivity($conn, null, 'Alert Dismissed',
    "User '$uname' dismissed alert #$alertId", 'info');

echo json_encode(['success' => true]);
