<?php
/**
 * BeeGuard IoT - Colony Status Update (from Edge ML or ESP32)
 * Called after on-device ML inference to report colony classification.
 *
 * POST JSON:
 * {
 *   "api_key":        "BEEGUARD_SECRET_2026",
 *   "device_id":      "ESP32-001",
 *   "hive_id":        1,
 *   "colony_status":  "normal",       // normal|queenless|swarming|environmental_stress|varroa_mite|robbing
 *   "confidence":     87.5,
 *   "dominant_freq":  245.0,
 *   "sound_level":    65.0
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

$validStatuses = ['normal', 'queenless', 'swarming', 'varroa_mite'];
$hiveId       = (int)($data['hive_id'] ?? 0);
$status       = $data['colony_status'] ?? '';
$confidence   = round((float)($data['confidence'] ?? 0), 2);
$dominantFreq = round((float)($data['dominant_freq'] ?? 0), 2);
$soundLevel   = round((float)($data['sound_level'] ?? 0), 2);
$deviceId     = $conn->real_escape_string($data['device_id'] ?? '');

if (!$hiveId || !in_array($status, $validStatuses)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid hive_id or colony_status']);
    exit;
}

// Insert audio analysis
$stmt = $conn->prepare(
    "INSERT INTO audio_analysis (hive_id, colony_status, confidence, dominant_freq, sound_level)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("isddd", $hiveId, $status, $confidence, $dominantFreq, $soundLevel);
$stmt->execute();
$stmt->close();

// Create alert for non-normal statuses
$criticalStatuses = ['queenless', 'varroa_mite'];
$warningStatuses  = ['swarming'];

$alertMessages = [
    'queenless'   => ['Queenless colony detected. Immediate inspection required.', 'Verify by visual inspection within 24 hours and introduce new queen if confirmed.'],
    'varroa_mite' => ['Varroa mite infestation suspected.', 'Perform alcohol wash test and apply treatment if over 3 mites per 100 bees.'],
    'swarming'    => ['Swarming preparation detected.', 'Inspect for queen cells and consider hive split to prevent swarm loss.'],
];

if (isset($alertMessages[$status])) {
    [$msg, $action] = $alertMessages[$status];
    $severity = in_array($status, $criticalStatuses) ? 'critical' : 'warning';
    $alertStmt = $conn->prepare(
        "INSERT INTO alerts (hive_id, alert_type, severity, message, recommended_action)
         VALUES (?, 'colony_status', ?, ?, ?)"
    );
    $alertStmt->bind_param("isss", $hiveId, $severity, $msg, $action);
    $alertStmt->execute();
    $alertStmt->close();
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
