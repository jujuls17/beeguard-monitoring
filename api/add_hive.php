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

$name     = trim($_POST['name']     ?? '');
$hiveCode = strtoupper(trim($_POST['hive_id']   ?? ''));
$location = trim($_POST['location'] ?? '');
$deviceId = trim($_POST['device_id'] ?? '');

if (!$name || !$hiveCode) {
    echo json_encode(['success' => false, 'message' => 'Hive name and ID code are required.']);
    exit;
}
if (strlen($name) > 100 || strlen($hiveCode) > 50) {
    echo json_encode(['success' => false, 'message' => 'Name or ID code too long.']);
    exit;
}

// Check hive_id uniqueness
$check = $conn->prepare("SELECT id FROM hives WHERE hive_id = ?");
$check->bind_param("s", $hiveCode);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => "Hive ID '{$hiveCode}' already exists. Choose a different code."]);
    exit;
}
$check->close();

// Auto-assign next camera index — starts at 1 (index 0 = laptop built-in, skip it)
$camRes = $conn->query("SELECT COALESCE(MAX(camera_index) + 1, 1) AS next_idx FROM hives");
$camIdx = (int)($camRes ? $camRes->fetch_assoc()['next_idx'] : 1);
if ($camIdx < 1) $camIdx = 1;

// Insert hive
$stmt = $conn->prepare(
    "INSERT INTO hives (name, hive_id, location, status, camera_index) VALUES (?, ?, ?, 'active', ?)"
);
$stmt->bind_param("sssi", $name, $hiveCode, $location, $camIdx);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to create hive: ' . $conn->error]);
    exit;
}
$newHiveId = $conn->insert_id;
$stmt->close();

// If a device_id was provided, link it to this hive in edge_devices
if ($deviceId !== '') {
    $devStmt = $conn->prepare(
        "INSERT INTO edge_devices (device_id, hive_id, status)
         VALUES (?, ?, 'offline')
         ON DUPLICATE KEY UPDATE hive_id = VALUES(hive_id)"
    );
    $devStmt->bind_param("si", $deviceId, $newHiveId);
    $devStmt->execute();
    $devStmt->close();

    // Remove from detected_devices if it was there
    $delStmt = $conn->prepare("DELETE FROM detected_devices WHERE device_id = ?");
    $delStmt->bind_param("s", $deviceId);
    $delStmt->execute();
    $delStmt->close();
}

// Log the action
$userId  = (int)$_SESSION['user_id'];
$details = "Admin added new hive '{$name}' ({$hiveCode})" . ($deviceId ? " with device {$deviceId}" : '');
$logStmt = $conn->prepare(
    "INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'hive_added', ?, 'info')"
);
$logStmt->bind_param("is", $userId, $details);
$logStmt->execute();
$logStmt->close();

echo json_encode([
    'success'      => true,
    'message'      => "Hive '{$name}' added successfully! Camera index: {$camIdx}" . ($deviceId ? " Device {$deviceId} assigned." : ''),
    'hive_id'      => $newHiveId,
    'camera_index' => $camIdx,
]);
