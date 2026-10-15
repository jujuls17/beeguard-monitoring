<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');
ob_clean();

try {
    $totalHives     = count(getAllHives($conn));
    $healthyCount   = countHealthyHives($conn);
    $healthyPercent = $totalHives > 0 ? round(($healthyCount / $totalHives) * 100) : 0;
    $avgTemp        = getAverageTemperature($conn);
    $alertCount     = count(getActiveAlerts($conn));

    // Avg humidity
    $rh = $conn->query("SELECT AVG(humidity) AS h FROM sensor_data WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $avgHumidity = $rh ? round((float)($rh->fetch_assoc()['h'] ?? 0), 1) : null;
    if (!$avgHumidity) $avgHumidity = null;

    // Avg CO2
    $avgCO2 = getAvgCO2($conn);

    // Online devices
    $rd = $conn->query("SELECT COUNT(*) AS c FROM edge_devices WHERE status = 'online'");
    $onlineDevices = $rd ? (int)$rd->fetch_assoc()['c'] : 0;

    // Recent activity (last 6)
    $activity = [];
    $ra = $conn->query(
        "SELECT al.event_type, al.details, al.severity, al.timestamp,
                h.name AS hive_name, u.username
         FROM activity_log al
         LEFT JOIN hives h ON al.hive_id = h.id
         LEFT JOIN users u ON al.user_id = u.id
         ORDER BY al.timestamp DESC LIMIT 6"
    );
    if ($ra) {
        while ($row = $ra->fetch_assoc()) {
            $activity[] = [
                'event_type' => $row['event_type'],
                'details'    => $row['details'],
                'severity'   => $row['severity'],
                'hive_name'  => $row['hive_name'],
                'username'   => $row['username'],
                'timestamp'  => $row['timestamp'],
                'elapsed'    => time_elapsed_string($row['timestamp']),
            ];
        }
    }

    echo json_encode([
        'success'        => true,
        'totalHives'     => $totalHives,
        'healthyCount'   => $healthyCount,
        'healthyPercent' => $healthyPercent,
        'avgTemp'        => $avgTemp,
        'avgHumidity'    => $avgHumidity,
        'avgCO2'         => $avgCO2,
        'alertCount'     => $alertCount,
        'onlineDevices'  => $onlineDevices,
        'activity'       => $activity,
        'refreshed_at'   => date('H:i:s'),
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
