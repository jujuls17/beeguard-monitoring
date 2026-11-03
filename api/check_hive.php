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

// Only admins and beekeepers can mark a hive as checked
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'beekeeper'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$hiveId = (int)($_POST['hive_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if (!$hiveId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid hive_id']);
    exit;
}

$stmt = $conn->prepare("UPDATE hives SET last_checked_at = NOW(), last_checked_by = ? WHERE id = ?");
$stmt->bind_param("ii", $userId, $hiveId);
$stmt->execute();
$stmt->close();

// Fetch the saved timestamp back
$r = $conn->query("SELECT last_checked_at FROM hives WHERE id = $hiveId");
$row = $r ? $r->fetch_assoc() : null;
$checkedAt = $row['last_checked_at'] ?? null;

logActivity($conn, $hiveId, 'Hive Checked',
    "User '{$_SESSION['username']}' marked hive as physically checked", 'info');

echo json_encode([
    'success'    => true,
    'checked_at' => $checkedAt,
    'elapsed'    => $checkedAt ? time_elapsed_string($checkedAt) : 'just now',
]);
