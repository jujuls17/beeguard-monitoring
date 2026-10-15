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

// Mark devices offline if no data in 6+ minutes
$conn->query(
    "UPDATE edge_devices
     SET status = 'offline'
     WHERE last_sync < DATE_SUB(NOW(), INTERVAL 6 MINUTE)
       AND status != 'offline'"
);

// Device counts
$result  = $conn->query("SELECT COUNT(*) AS total FROM edge_devices WHERE status = 'online'");
$online  = $result ? (int)$result->fetch_assoc()['total'] : 0;

$result2 = $conn->query("SELECT COUNT(*) AS total FROM edge_devices");
$total   = $result2 ? (int)$result2->fetch_assoc()['total'] : 0;

// Latest sensor reading across all hives
$latest = $conn->query(
    "SELECT sd.temperature, sd.humidity, sd.co2, sd.sound_level,
            sd.timestamp, h.name AS hive_name,
            TIMESTAMPDIFF(SECOND, sd.timestamp, NOW()) AS seconds_ago
     FROM sensor_data sd
     LEFT JOIN hives h ON h.id = sd.hive_id
     ORDER BY sd.timestamp DESC
     LIMIT 1"
);
$lastReading = $latest ? $latest->fetch_assoc() : null;

// Last sync time from edge_devices
$lastDevice = $conn->query(
    "SELECT last_sync, device_name, hive_id
     FROM edge_devices
     ORDER BY last_sync DESC
     LIMIT 1"
);
$lastDev = $lastDevice ? $lastDevice->fetch_assoc() : null;

// Format time ago
function timeAgo($seconds) {
    if ($seconds === null) return 'Never';
    $seconds = (int)$seconds;
    if ($seconds < 60)  return $seconds . 's ago';
    if ($seconds < 3600) return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's ago';
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm ago';
}

// Log the manual sync
$userId      = (int)$_SESSION['user_id'];
$syncDetails = "Manual device sync — {$online}/{$total} devices online";
$syncStmt    = $conn->prepare(
    "INSERT INTO activity_log (user_id, event_type, details, severity) VALUES (?, 'manual_sync', ?, 'info')"
);
$syncStmt->bind_param("is", $userId, $syncDetails);
$syncStmt->execute();

$secsAgo = $lastReading ? (int)$lastReading['seconds_ago'] : null;

echo json_encode([
    'success'      => true,
    'online'       => $online,
    'total'        => $total,
    'last_reading' => $lastReading ? [
        'hive'        => $lastReading['hive_name'] ?? 'Unknown',
        'temperature' => $lastReading['temperature'],
        'humidity'    => $lastReading['humidity'],
        'co2'         => $lastReading['co2'],
        'sound_level' => $lastReading['sound_level'],
        'timestamp'   => $lastReading['timestamp'],
        'time_ago'    => timeAgo($secsAgo),
        'seconds_ago' => $secsAgo,
    ] : null,
    'message' => $online > 0
        ? "{$online}/{$total} device(s) online. Last data: " . timeAgo($secsAgo)
        : "No devices online. Last data: " . timeAgo($secsAgo),
]);
