<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Admin only.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Remove a hive (and unlink its edge device) ──────────────
if ($action === 'hive') {
    $hiveId = (int)($_POST['hive_id'] ?? 0);
    if (!$hiveId) { echo json_encode(['success' => false, 'message' => 'Invalid hive.']); exit; }

    // Unlink edge devices assigned to this hive
    $conn->prepare("UPDATE edge_devices SET hive_id = NULL WHERE hive_id = ?")->execute() || true;
    $u = $conn->prepare("UPDATE edge_devices SET hive_id = NULL WHERE hive_id = ?");
    $u->bind_param('i', $hiveId); $u->execute(); $u->close();

    // Delete the hive
    $stmt = $conn->prepare("DELETE FROM hives WHERE id = ?");
    $stmt->bind_param('i', $hiveId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) { echo json_encode(['success' => false, 'message' => 'Hive not found.']); exit; }
    $stmt->close();

    // Log
    $uid = (int)$_SESSION['user_id'];
    $log = $conn->prepare("INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'hive_removed', ?, 'warning')");
    $msg = "Admin removed hive ID $hiveId";
    $log->bind_param('is', $uid, $msg); $log->execute(); $log->close();

    echo json_encode(['success' => true, 'message' => 'Hive removed.']);
    exit;
}

// ── Remove an ESP32 from edge_devices ───────────────────────
if ($action === 'edge_device') {
    $deviceId = trim($_POST['device_id'] ?? '');
    if (!$deviceId) { echo json_encode(['success' => false, 'message' => 'Invalid device ID.']); exit; }

    $stmt = $conn->prepare("DELETE FROM edge_devices WHERE device_id = ?");
    $stmt->bind_param('s', $deviceId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) { echo json_encode(['success' => false, 'message' => 'Device not found.']); exit; }
    $stmt->close();

    $uid = (int)$_SESSION['user_id'];
    $log = $conn->prepare("INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'device_removed', ?, 'warning')");
    $msg = "Admin removed edge device: $deviceId";
    $log->bind_param('is', $uid, $msg); $log->execute(); $log->close();

    echo json_encode(['success' => true, 'message' => "Device $deviceId removed."]);
    exit;
}

// ── Dismiss a detected (unregistered) device ────────────────
if ($action === 'detected') {
    $deviceId = trim($_POST['device_id'] ?? '');
    if (!$deviceId) { echo json_encode(['success' => false, 'message' => 'Invalid device ID.']); exit; }

    $stmt = $conn->prepare("DELETE FROM detected_devices WHERE device_id = ?");
    $stmt->bind_param('s', $deviceId);
    $stmt->execute();
    $stmt->close();

    $uid = (int)$_SESSION['user_id'];
    $log = $conn->prepare("INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'Device Dismissed', ?, 'info')");
    $msg = "Admin dismissed unregistered device: $deviceId";
    $log->bind_param('is', $uid, $msg); $log->execute(); $log->close();

    echo json_encode(['success' => true, 'message' => "Device $deviceId dismissed."]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
