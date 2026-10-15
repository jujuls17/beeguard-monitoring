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
    $result = $conn->query(
        "SELECT hive_id,
                DATE_FORMAT(timestamp, '%H:00') AS hour_label,
                HOUR(timestamp)                 AS hour_num,
                ROUND(AVG(temperature), 1)      AS avg_temp,
                ROUND(AVG(humidity), 1)         AS avg_humidity,
                ROUND(AVG(co2))                 AS avg_co2
         FROM sensor_data
         WHERE timestamp >= NOW() - INTERVAL 24 HOUR
         GROUP BY hive_id, HOUR(timestamp)
         ORDER BY hive_id, hour_num ASC"
    );

    $byHive = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['hive_id'];
            if (!isset($byHive[$id])) {
                $byHive[$id] = ['labels' => [], 'temp' => [], 'humidity' => [], 'co2' => []];
            }
            $byHive[$id]['labels'][]   = $row['hour_label'];
            $byHive[$id]['temp'][]     = (float)$row['avg_temp'];
            $byHive[$id]['humidity'][] = (float)$row['avg_humidity'];
            $byHive[$id]['co2'][]      = (float)$row['avg_co2'];
        }
    }

    echo json_encode(['success' => true, 'trends' => $byHive]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
