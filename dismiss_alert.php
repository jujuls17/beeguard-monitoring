<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

header('Content-Type: application/json');
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$action = $_POST['action'] ?? 'dismiss';

try {
    if ($action === 'dismiss_all') {
        $conn->query("UPDATE alerts SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['success' => true, 'message' => 'All alerts dismissed.']);
        exit;
    }

    if ($action === 'restore') {
        $id   = (int)($_POST['alert_id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing alert ID']); exit; }
        $stmt = $conn->prepare("UPDATE alerts SET is_read = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Alert restored.']);
        exit;
    }

    if ($action === 'create') {
        if (!isAdmin()) { echo json_encode(['success' => false, 'message' => 'Admin only.']); exit; }
        $hive_id  = (int)($_POST['hive_id'] ?? 0);
        $atype    = preg_replace('/[^a-z_]/', '', $_POST['alert_type'] ?? 'system');
        $severity = in_array($_POST['severity'] ?? '', ['critical','warning','info']) ? $_POST['severity'] : 'warning';
        $message  = trim($_POST['message'] ?? '');
        $recAction= trim($_POST['recommended_action'] ?? '');
        if (!$hive_id || !$message) { echo json_encode(['success' => false, 'message' => 'Hive and message required.']); exit; }
        $stmt = $conn->prepare("INSERT INTO alerts (hive_id, alert_type, severity, message, recommended_action, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->bind_param('issss', $hive_id, $atype, $severity, $message, $recAction);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Alert created.']);
        exit;
    }

    // Default: dismiss single alert
    $id = (int)($_POST['alert_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Missing alert ID']); exit; }
    $stmt = $conn->prepare("UPDATE alerts SET is_read = 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Alert dismissed.']);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
