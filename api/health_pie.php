<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();
header('Content-Type: application/json');
ob_clean();

$date   = $_GET['date']    ?? date('Y-m-d');
$hiveId = (int)($_GET['hive_id'] ?? 0);

$counts = ['Healthy' => 0, 'Warning' => 0, 'Critical' => 0];
$lastTs = null;

function classifyRow($t, $h, $c): string {
    $isCritical = ($t !== null && ($t < 18 || $t > 38))
               || ($h !== null && ($h < 30  || $h > 98))
               || ($c !== null && $c > 6000);
    if ($isCritical) return 'Critical';
    $isWarning = ($t !== null && ($t < 33 || $t > 36))
              || ($h !== null && ($h < 40  || $h > 95))
              || ($c !== null && $c > 4000);
    return $isWarning ? 'Warning' : 'Healthy';
}

function fetchAndClassify($conn, string $date, int $hiveId, array &$counts, &$lastTs): void {
    if ($hiveId > 0) {
        // Single hive: classify each individual reading
        $stmt = $conn->prepare(
            "SELECT temperature, humidity, co2, timestamp
             FROM sensor_data
             WHERE DATE(timestamp) = ?
               AND hive_id = ?
               AND (temperature IS NOT NULL OR humidity IS NOT NULL OR co2 IS NOT NULL)
             ORDER BY timestamp ASC"
        );
        $stmt->bind_param('si', $date, $hiveId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $t = $row['temperature'] !== null ? (float)$row['temperature'] : null;
            $h = $row['humidity']    !== null ? (float)$row['humidity']    : null;
            $c = $row['co2']         !== null ? (int)$row['co2']           : null;
            $counts[classifyRow($t, $h, $c)]++;
            $lastTs = $row['timestamp'];
        }
        $stmt->close();
    } else {
        // All Hives: average sensors across all hives per 1-minute bucket, then classify
        $stmt = $conn->prepare(
            "SELECT
                 DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:00') AS bucket,
                 ROUND(AVG(temperature), 2) AS avg_temp,
                 ROUND(AVG(humidity), 2)    AS avg_humidity,
                 ROUND(AVG(co2), 0)         AS avg_co2,
                 MAX(timestamp)             AS last_ts
             FROM sensor_data
             WHERE DATE(timestamp) = ?
               AND (temperature IS NOT NULL OR humidity IS NOT NULL OR co2 IS NOT NULL)
             GROUP BY bucket
             ORDER BY bucket ASC"
        );
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $t = $row['avg_temp']     !== null ? (float)$row['avg_temp']     : null;
            $h = $row['avg_humidity'] !== null ? (float)$row['avg_humidity'] : null;
            $c = $row['avg_co2']      !== null ? (int)$row['avg_co2']        : null;
            $counts[classifyRow($t, $h, $c)]++;
            $lastTs = $row['last_ts'];
        }
        $stmt->close();
    }
}

fetchAndClassify($conn, $date, $hiveId, $counts, $lastTs);
$total = array_sum($counts);

// Fallback to most recent day with data if today has no readings
$fallbackDate = null;
if ($total === 0 && $date === date('Y-m-d')) {
    $hiveWhere = $hiveId > 0 ? "AND hive_id = {$hiveId}" : '';
    $fbRes = $conn->query(
        "SELECT DATE(timestamp) AS d FROM sensor_data
         WHERE DATE(timestamp) < CURDATE() {$hiveWhere}
         ORDER BY timestamp DESC LIMIT 1"
    );
    $fbRow = $fbRes ? $fbRes->fetch_assoc() : null;
    if ($fbRow) {
        $fallbackDate = $fbRow['d'];
        fetchAndClassify($conn, $fallbackDate, $hiveId, $counts, $lastTs);
        $total = array_sum($counts);
    }
}

echo json_encode([
    'success'       => true,
    'date'          => $date,
    'fallback_date' => $fallbackDate,
    'hive_id'       => $hiveId,
    'mode'          => $hiveId > 0 ? 'single' : 'average',
    'counts'        => $counts,
    'total'         => $total,
    'last_reading'  => $lastTs ? date('H:i', strtotime($lastTs)) : null,
    'updated_at'    => date('H:i:s'),
]);
