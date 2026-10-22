<?php
/**
 * BeeGuard IoT - Colony Status Update (from Edge ML or ESP32)
 * Called after on-device ML inference to report colony classification.
 *
 * POST JSON:
 * {
 *   "api_key":        "BEEGUARD_SECRET_2026",
 *   "hive_id":        1,
 *   "colony_status":  "normal",       // normal|queenless|swarming (CNN classes only)
 *   "confidence":     87.5,
 *   "prob_normal":    70.0,
 *   "prob_queenless": 20.0,
 *   "prob_swarming":  10.0,
 *   "inference_ms":   143
 * }
 */

require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

define('API_KEY', 'BEEGUARD_SECRET_2026');
if (($data['api_key'] ?? '') !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$validStatuses = ['normal', 'queenless', 'swarming'];
$hiveId       = (int)($data['hive_id'] ?? 0);
$status       = $data['colony_status'] ?? '';
$confidence   = round((float)($data['confidence'] ?? 0), 2);
$probNormal   = round((float)($data['prob_normal']    ?? 0), 2);
$probQueenless = round((float)($data['prob_queenless'] ?? 0), 2);
$probSwarming = round((float)($data['prob_swarming']  ?? 0), 2);
$inferenceMs  = isset($data['inference_ms']) ? (int)$data['inference_ms'] : null;
$deviceId     = $conn->real_escape_string($data['device_id'] ?? '');

if (!$hiveId || !in_array($status, $validStatuses)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid hive_id or colony_status']);
    exit;
}

// Insert audio analysis (inference_ms may be NULL if not provided)
$stmt = $conn->prepare(
    "INSERT INTO audio_analysis (hive_id, colony_status, confidence, inference_ms)
     VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("isdi", $hiveId, $status, $confidence, $inferenceMs);
$stmt->execute();
$stmt->close();

// Create alert for non-normal statuses
$alertMessages = [
    'queenless' => ['critical', 'Queenless colony detected by CNN audio model.', 'Verify by visual inspection within 24 hours and introduce new queen if confirmed.'],
    'swarming'  => ['warning',  'Swarming preparation detected by CNN audio model.', 'Inspect for queen cells and consider hive split to prevent swarm loss.'],
];

if (isset($alertMessages[$status])) {
    [$severity, $msg, $action] = $alertMessages[$status];
    $alertStmt = $conn->prepare(
        "INSERT INTO alerts (hive_id, alert_type, severity, message, recommended_action)
         VALUES (?, 'colony_status', ?, ?, ?)"
    );
    $alertStmt->bind_param("isss", $hiveId, $severity, $msg, $action);
    $alertStmt->execute();
    $alertStmt->close();
} elseif ($status === 'normal') {
    // Auto-dismiss colony_status alerts when CNN reports normal
    $conn->query(
        "UPDATE alerts SET is_read = 1
         WHERE hive_id = {$hiveId} AND alert_type = 'colony_status' AND is_read = 0"
    );
}

// Update device sync time
$conn->query("UPDATE edge_devices SET last_sync = NOW() WHERE device_id = '$deviceId'");

echo json_encode([
    'success'   => true,
    'message'   => 'Colony status recorded',
    'hive_id'   => $hiveId,
    'status'    => $status,
    'timestamp' => date('Y-m-d H:i:s'),
]);
