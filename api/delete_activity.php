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

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid log ID.']);
    exit;
}

// Fetch the entry before deleting so we can log what was removed
$fetch = $conn->prepare("SELECT event_type, severity, timestamp FROM activity_log WHERE id = ?");
$fetch->bind_param('i', $id);
$fetch->execute();
$entry = $fetch->get_result()->fetch_assoc();
$fetch->close();

if (!$entry) {
    echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM activity_log WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Log entry not found.']);
    exit;
}
$stmt->close();

// Record the deletion in the activity log
$uid     = (int)$_SESSION['user_id'];
$uname   = $_SESSION['username'] ?? 'admin';
$details = "Admin '$uname' deleted activity log #$id (event: {$entry['event_type']}, severity: {$entry['severity']}, recorded: {$entry['timestamp']})";
$log = $conn->prepare("INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'log_deleted', ?, 'warning')");
$log->bind_param('is', $uid, $details);
$log->execute();
$log->close();

echo json_encode(['success' => true, 'message' => 'Log entry deleted.']);
