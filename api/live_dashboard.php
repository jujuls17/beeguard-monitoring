<?php
/**
 * BeeGuard - Live Dashboard Data
 * Returns lightweight stats for client-side DOM updates (no full page reload).
 */
session_start();
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$totalHives     = count(getAllHives($conn));
$healthyCount   = countHealthyHives($conn);
$healthyPercent = $totalHives > 0 ? round(($healthyCount / $totalHives) * 100) : 0;
$avgTemp        = getAverageTemperature($conn);
$alerts         = getActiveAlerts($conn);
$alertCount     = count($alerts);

// Avg humidity
$avgHumidity = null;
$rh = $conn->query("SELECT AVG(s.humidity) as avg FROM sensor_data s
    INNER JOIN (SELECT hive_id, MAX(timestamp) as mt FROM sensor_data GROUP BY hive_id) lx
    ON s.hive_id = lx.hive_id AND s.timestamp = lx.mt");
if ($rh) { $row = $rh->fetch_assoc(); $avgHumidity = $row['avg'] !== null ? round((float)$row['avg'], 1) : null; }

// Avg CO2
$avgCO2 = null;
$rc = $conn->query("SELECT AVG(s.co2_level) as avg FROM sensor_data s
    INNER JOIN (SELECT hive_id, MAX(timestamp) as mt FROM sensor_data GROUP BY hive_id) lx
    ON s.hive_id = lx.hive_id AND s.timestamp = lx.mt");
if ($rc) { $row = $rc->fetch_assoc(); $avgCO2 = $row['avg'] !== null ? round((float)$row['avg']) : null; }

// Online devices
$onlineDevices = 0;
$rd = $conn->query("SELECT COUNT(*) as c FROM edge_devices WHERE status = 'online'");
if ($rd) $onlineDevices = (int)$rd->fetch_assoc()['c'];

// Unread alert count for badge
$unreadAlerts = 0;
$ru = $conn->query("SELECT COUNT(*) as c FROM alerts WHERE is_read = 0");
if ($ru) $unreadAlerts = (int)$ru->fetch_assoc()['c'];

// Temp badge label
function tempBadge($t) {
    if ($t === null) return ['—', 'no-data'];
    if ($t < 18 || $t > 37) return ['Critical', 'critical'];
    if ($t < 32 || $t > 36) return ['Warning',  'warning'];
    return ['Normal', 'normal'];
}
[$tempLabel, $tempStatus] = tempBadge($avgTemp);

echo json_encode([
    'success'        => true,
    'totalHives'     => $totalHives,
    'healthyPercent' => $healthyPercent,
    'healthyCount'   => $healthyCount,
    'avgTemp'        => $avgTemp,
    'tempLabel'      => $tempLabel,
    'tempStatus'     => $tempStatus,
    'alertCount'     => $alertCount,
    'unreadAlerts'   => $unreadAlerts,
    'avgHumidity'    => $avgHumidity,
    'avgCO2'         => $avgCO2,
    'onlineDevices'  => $onlineDevices,
    'ts'             => time(),
]);
