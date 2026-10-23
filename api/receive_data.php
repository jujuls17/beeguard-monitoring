<?php
/**
 * BeeGuard IoT - ESP32 Data Receiver
 * ESP32 POSTs JSON sensor data to this endpoint.
 *
 * Expected POST body (JSON):
 * {
 *   "api_key":       "BEEGUARD_SECRET_2026",
 *   "device_id":     "ESP32-001",
 *   "hive_id":       1,
 *   "temperature":   35.2,
 *   "humidity":      61.0,
 *   "co2":           1200,
 *   "sound_level":   72.0,
 *   "dominant_freq": 250.0
 * }
 */

require_once '../config.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// API key check
define('API_KEY', 'BEEGUARD_SECRET_2026');
if (($data['api_key'] ?? '') !== API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Required fields (hive_id is optional — server can resolve it from device_id)
$required = ['device_id']; // sensors may be null if physically disconnected
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$deviceId     = $conn->real_escape_string($data['device_id']);
$hiveId       = (int)($data['hive_id'] ?? 0);
$temp         = isset($data['temperature']) && $data['temperature'] !== null ? round((float)$data['temperature'], 2) : null;
$humidity     = isset($data['humidity'])    && $data['humidity']    !== null ? round((float)$data['humidity'],    2) : null;
$co2          = isset($data['co2'])         && $data['co2']         !== null ? (int)$data['co2']                        : null;
$soundLevel   = isset($data['sound_level']) && $data['sound_level'] !== null ? round((float)$data['sound_level'], 2) : null;
$dominantFreq = round((float)($data['dominant_freq'] ?? 0), 2);

// Sanity range checks
if (($temp !== null && ($temp < 0 || $temp > 60)) || ($humidity !== null && ($humidity < 0 || $humidity > 100)) || ($co2 !== null && ($co2 < 0 || $co2 > 10000)) || ($soundLevel !== null && $soundLevel < 0) || $dominantFreq < 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Sensor values out of range']);
    exit;
}

// Ensure detected_devices table exists
$conn->query("CREATE TABLE IF NOT EXISTS detected_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    times_seen INT DEFAULT 1
)");

// Resolve hive_id: if not provided (or 0), look it up from registered edge_devices
if ($hiveId === 0) {
    $lookup = $conn->prepare("SELECT hive_id FROM edge_devices WHERE device_id = ? LIMIT 1");
    $lookup->bind_param('s', $deviceId);
    $lookup->execute();
    $lookupRow = $lookup->get_result()->fetch_assoc();
    $lookup->close();

    if ($lookupRow && (int)$lookupRow['hive_id'] > 0) {
        $hiveId = (int)$lookupRow['hive_id'];
    } else {
        // Device is completely unknown — log it and ask firmware to register
        $conn->query(
            "INSERT INTO detected_devices (device_id, times_seen)
             VALUES ('$deviceId', 1)
             ON DUPLICATE KEY UPDATE times_seen = times_seen + 1, last_seen = NOW()"
        );
        echo json_encode([
            'success'          => false,
            'needs_registration' => true,
            'message'          => 'Device not assigned to a hive. Visit the Devices page in BeeGuard to register this ESP32.',
            'device_id'        => $data['device_id'],
        ]);
        exit;
    }
}

// Insert sensor data
$stmt = $conn->prepare(
    "INSERT INTO sensor_data (hive_id, temperature, humidity, co2, sound_level)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("iddid", $hiveId, $temp, $humidity, $co2, $soundLevel);
$inserted = $stmt->execute();
$stmt->close();

if (!$inserted) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB insert failed: ' . $conn->error]);
    exit;
}

// Update edge device last_sync and status
$conn->query(
    "UPDATE edge_devices SET status = 'online', last_sync = NOW()
     WHERE device_id = '$deviceId'"
);

// If still not registered in edge_devices (device knew its hive_id but isn't in the table), track it
if ($conn->affected_rows === 0) {
    $conn->query(
        "INSERT INTO detected_devices (device_id, times_seen)
         VALUES ('$deviceId', 1)
         ON DUPLICATE KEY UPDATE times_seen = times_seen + 1, last_seen = NOW()"
    );
}

// ── Alert helpers ─────────────────────────────────────────────────────────
function insertAlertIfNew(mysqli $db, int $hiveId, string $type, string $sev, string $msg, string $action): void {
    $chk = $db->prepare("SELECT id FROM alerts WHERE hive_id = ? AND alert_type = ? AND is_read = 0 LIMIT 1");
    $chk->bind_param("is", $hiveId, $type);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) { $chk->close(); return; }
    $chk->close();
    $ins = $db->prepare("INSERT INTO alerts (hive_id, alert_type, severity, message, recommended_action) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("issss", $hiveId, $type, $sev, $msg, $action);
    $ins->execute();
    $ins->close();
}

function dismissAlertType(mysqli $db, int $hiveId, string $type): void {
    $db->query("UPDATE alerts SET is_read = 1 WHERE hive_id = {$hiveId} AND alert_type = '{$type}' AND is_read = 0");
}

// Fetch hive name for human-readable alert messages
$hiveRow  = $conn->query("SELECT name FROM hives WHERE id = {$hiveId} LIMIT 1")->fetch_assoc();
$hiveName = $hiveRow ? htmlspecialchars($hiveRow['name']) : "Hive #{$hiveId}";

// ── Temperature alerts ────────────────────────────────────────────────────
// Paper spec: Optimal 33–36°C. Warning <33 or >36. Critical <18 or >38.
if ($temp === null) { /* DHT22 disconnected — skip */ } elseif ($temp >= 33 && $temp <= 36) {
    dismissAlertType($conn, $hiveId, 'temperature');
} else {
    if ($temp > 38) {
        insertAlertIfNew($conn, $hiveId, 'temperature', 'critical',
            "Temperature critically high at {$temp}°C on {$hiveName}. Colony at heat stress risk.",
            "Immediately open entrance and add top ventilation. Provide shade if the hive is in direct sun. Heat stress can halt brood production — check queen activity within 24 hours."
        );
    } elseif ($temp > 36) {
        insertAlertIfNew($conn, $hiveId, 'temperature', 'warning',
            "Temperature elevated at {$temp}°C on {$hiveName}. Above optimal range (33–36°C).",
            "Increase hive ventilation and reduce direct sun exposure. Monitor closely over the next 2 hours. Ensure bees have access to water for evaporative cooling."
        );
    } elseif ($temp < 18) {
        insertAlertIfNew($conn, $hiveId, 'temperature', 'critical',
            "Temperature critically low at {$temp}°C on {$hiveName}. Colony at hypothermia risk.",
            "Insulate the hive immediately with foam or wrapping material. Reduce entrance to a 1-bee gap to retain heat. Verify cluster size is sufficient — a small colony may need combining with another to survive."
        );
    } elseif ($temp < 33) {
        insertAlertIfNew($conn, $hiveId, 'temperature', 'warning',
            "Temperature too low at {$temp}°C on {$hiveName}. Below optimal brood range (33–36°C).",
            "Reduce entrance size to help bees retain heat. Add insulation above the inner cover. If late-season, verify honey stores are adequate for cluster survival through cold nights."
        );
    }
}

// ── Humidity alerts ───────────────────────────────────────────────────────
// Paper spec: Optimal 40–95%. Warning <40 or >95. Critical <30 or >98.
if ($humidity === null) { /* DHT22 disconnected — skip */ } elseif ($humidity >= 40 && $humidity <= 95) {
    dismissAlertType($conn, $hiveId, 'humidity');
} else {
    if ($humidity > 98) {
        insertAlertIfNew($conn, $hiveId, 'humidity', 'critical',
            "Humidity critically high at {$humidity}% on {$hiveName}. Risk of fungal disease and brood death.",
            "Open all ventilation immediately. Check for water ingress or roof leaks. Inspect brood frames for chalkbrood (white mummified larvae) or nosema. Remove entrance reducer if it is blocking airflow."
        );
    } elseif ($humidity > 95) {
        insertAlertIfNew($conn, $hiveId, 'humidity', 'warning',
            "High humidity at {$humidity}% on {$hiveName}. Above optimal range (40–95%).",
            "Improve ventilation by propping the lid slightly or opening the screened bottom board. Monitor for condensation on the inner cover. Watch brood frames for early signs of chalkbrood."
        );
    } elseif ($humidity < 30) {
        insertAlertIfNew($conn, $hiveId, 'humidity', 'critical',
            "Humidity critically low at {$humidity}% on {$hiveName}. Bees at dehydration and nectar-curing risk.",
            "Provide a water source immediately within 50m of the hive. Consider a boardman feeder with clean water placed at the entrance. Check for severe nectar dearth — bees may be fanning excessively to manage heat."
        );
    } elseif ($humidity < 40) {
        insertAlertIfNew($conn, $hiveId, 'humidity', 'warning',
            "Low humidity at {$humidity}% on {$hiveName}. Below optimal range (40–95%).",
            "Ensure a clean water source is accessible within 50m. Monitor nectar curing — bees need adequate humidity to properly ripen and seal honey. Check if colony is consuming water stores rapidly."
        );
    }
}

// ── CO₂ alerts ────────────────────────────────────────────────────────────
// Inside-hive CO₂ reference ranges (sensor mounted inside the hive):
//   Normal healthy colony: 1000–4000 ppm  — bees actively regulate ventilation
//   Elevated (poor airflow): 4000–6000 ppm — needs attention
//   Critical (severe issue): >6000 ppm     — immediate action required
if ($co2 <= 4000) {
    dismissAlertType($conn, $hiveId, 'co2');
} else {
    if ($co2 > 6000) {
        insertAlertIfNew($conn, $hiveId, 'co2', 'critical',
            "CO₂ critically high at {$co2}ppm on {$hiveName}. Severe ventilation failure.",
            "Open all entrances and ventilation slots immediately. Remove entrance reducer completely. Inspect for overcrowding — consider adding a honey super or splitting the colony. CO₂ this high can suffocate brood."
        );
    } else {
        insertAlertIfNew($conn, $hiveId, 'co2', 'warning',
            "CO₂ elevated at {$co2}ppm on {$hiveName}. Above optimal inside-hive level (≤4000ppm).",
            "Improve hive ventilation — open the screened bottom board or prop the lid slightly. Check if the entrance reducer is too restrictive for current colony size. Monitor over the next hour."
        );
    }
}

// ── Colony status is set by CNN inference in audio_service.py ────
// sound_level and dominant_freq are stored as raw readings only.
// detectColonyStatus() has been removed per Chapter 3 specification.
// Colony status must NOT be set from rule-based heuristics here.

// Log to activity
$logMsg = "ESP32 {$deviceId}: T={$temp}°C H={$humidity}% CO2={$co2}ppm Sound={$soundLevel}% Freq={$dominantFreq}Hz";
$logStmt = $conn->prepare(
    "INSERT INTO activity_log (hive_id, event_type, details, severity)
     VALUES (?, 'Data Collection', ?, 'info')"
);
$logStmt->bind_param("is", $hiveId, $logMsg);
$logStmt->execute();
$logStmt->close();

echo json_encode([
    'success'   => true,
    'message'   => 'Data received',
    'hive_id'   => $hiveId,
    'timestamp' => date('Y-m-d H:i:s'),
]);
