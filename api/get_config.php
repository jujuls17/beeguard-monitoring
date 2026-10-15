<?php
/**
 * BeeGuard IoT - ESP32 Config Endpoint
 * ESP32 calls this on boot to get its hive assignment and sync interval.
 *
 * GET /api/get_config.php?api_key=BEEGUARD_SECRET_2026&device_id=ESP32-001
 */

require_once '../config.php';

header('Content-Type: application/json');

define('API_KEY', 'BEEGUARD_SECRET_2026');

if (($_GET['api_key'] ?? '') !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$deviceId = $conn->real_escape_string($_GET['device_id'] ?? '');
if (!$deviceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'device_id required']);
    exit;
}

$result = $conn->query(
    "SELECT d.*, h.name as hive_name, h.hive_id as hive_code
     FROM edge_devices d
     LEFT JOIN hives h ON d.hive_id = h.id
     WHERE d.device_id = '$deviceId'
     LIMIT 1"
);

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Device not registered']);
    exit;
}

$device = $result->fetch_assoc();

// Pull system sync interval from settings (always stored in SECONDS)
$settingsResult = $conn->query("SELECT sync_interval FROM user_settings WHERE user_id = 1 LIMIT 1");
$syncSeconds    = 30; // default 30 seconds
if ($settingsResult && $settingsResult->num_rows > 0) {
    $raw = (int)$settingsResult->fetch_assoc()['sync_interval'];
    if ($raw >= 10) $syncSeconds = $raw; // must be at least 10 seconds
}
$syncMinutes = max(1, (int)round($syncSeconds / 60)); // for legacy firmware

echo json_encode([
    'success'                => true,
    'device_id'              => $device['device_id'],
    'device_name'            => $device['device_name'],
    'hive_id'                => (int)$device['hive_id'],
    'hive_name'              => $device['hive_name'],
    'hive_code'              => $device['hive_code'],
    'sync_interval_seconds'  => $syncSeconds,   // new field — use this
    'sync_interval_minutes'  => $syncMinutes,   // legacy field — kept for old firmware
    'api_endpoint'           => 'http://YOUR_SERVER_IP/beemonitoring/api/receive_data.php',
    'firmware'               => $device['firmware_version'],
    'server_time'            => date('Y-m-d H:i:s'),
]);
