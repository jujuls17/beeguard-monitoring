<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ob_clean();

// Mark devices offline if no sync in last 5 minutes
// (ESP32 restart: 30s MQ-135 warmup + up to 50s WiFi retry = ~80s; 5min gives safe margin)
$conn->query(
    "UPDATE edge_devices
     SET status = 'offline'
     WHERE last_sync < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
       AND status != 'offline'"
);

$result = $conn->query(
    "SELECT d.*, h.name as hive_name, h.camera_index,
            sd.temperature    AS latest_temp,
            sd.humidity       AS latest_humidity,
            sd.co2            AS latest_co2,
            sd.sound_level    AS latest_sound,
            sd.timestamp      AS latest_ts
     FROM edge_devices d
     LEFT JOIN hives h ON d.hive_id = h.id
     LEFT JOIN sensor_data sd ON sd.id = (
         SELECT id FROM sensor_data
         WHERE hive_id = d.hive_id
         ORDER BY timestamp DESC LIMIT 1
     )
     ORDER BY d.status DESC, d.id ASC"
);

// Camera liveness check is not applicable on cloud hosting (no local camera process)
function isCamPortLive(int $port): bool {
    return false;
}

$devices = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // A sensor is "connected" if the device is online AND the field has a valid value
        $isOnline   = $row['status'] === 'online';
        $hasReading = $row['latest_ts'] !== null
            && (time() - strtotime($row['latest_ts'])) <= 300; // reading within last 5 min

        // Derive camera port from camera_index (5049 + index), fallback 5050
        $camIdx    = isset($row['camera_index']) ? (int)$row['camera_index'] : 1;
        $camPort   = 5049 + max(1, $camIdx);
        $cameraLive = isCamPortLive($camPort);

        $sensors = [
            'temperature' => $isOnline && $hasReading && $row['latest_temp']    !== null && (float)$row['latest_temp']    > 0,
            'humidity'    => $isOnline && $hasReading && $row['latest_humidity'] !== null && (float)$row['latest_humidity'] > 0,
            'co2'         => $isOnline && $hasReading && $row['latest_co2']      !== null && (float)$row['latest_co2']     > 0,
            'sound'       => $isOnline && $hasReading && $row['latest_sound']    !== null && (float)$row['latest_sound'] > 0,
            'camera'      => $cameraLive,
        ];

        $devices[] = [
            'id'              => $row['id'],
            'device_name'     => $row['device_name'],
            'device_id'       => $row['device_id'],
            'device_type'     => $row['device_type'],
            'hive_name'       => $row['hive_name'] ?? '—',
            'status'          => $row['status'],
            'firmware'        => $row['firmware_version'],
            'battery_level'   => $row['battery_level'] !== null ? (float)$row['battery_level'] : null,
            'cpu_usage'       => $row['cpu_usage']     !== null ? (float)$row['cpu_usage']     : null,
            'memory_usage'    => $row['memory_usage']  !== null ? (float)$row['memory_usage']  : null,
            'sensors'         => $sensors,
            'last_sync'       => $row['last_sync']
                ? date('H:i:s', strtotime($row['last_sync']))
                : 'Never',
            'last_sync_ts'    => $row['last_sync']
                ? (int)strtotime($row['last_sync'])
                : null,
            'last_sync_elapsed' => $row['last_sync']
                ? time_elapsed_string($row['last_sync'])
                : 'Never',
        ];
    }
}

$online  = count(array_filter($devices, fn($d) => $d['status'] === 'online'));
$warning = count(array_filter($devices, fn($d) => $d['status'] === 'warning'));

echo json_encode([
    'success' => true,
    'devices' => $devices,
    'total'   => count($devices),
    'online'  => $online,
    'warning' => $warning,
    'checked_at' => date('H:i:s'),
]);
