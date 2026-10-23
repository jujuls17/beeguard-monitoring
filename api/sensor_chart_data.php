<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();
header('Content-Type: application/json');

$range  = $_GET['range']   ?? '24h';
$hiveId = $_GET['hive_id'] ?? 'all';

// Determine interval and grouping format based on range
$intervals = [
    '1h'  => ['interval' => 'NOW() - INTERVAL 1 HOUR',  'fmt' => '%H:%i',     'trunc' => 'DATE_FORMAT(timestamp,\'%Y-%m-%d %H:%i:00\')'],
    '6h'  => ['interval' => 'NOW() - INTERVAL 6 HOUR',  'fmt' => '%H:%i',     'trunc' => "DATE_FORMAT(DATE_SUB(timestamp, INTERVAL MINUTE(timestamp) MOD 15 MINUTE), '%Y-%m-%d %H:%i:00')"],
    '24h' => ['interval' => 'NOW() - INTERVAL 24 HOUR', 'fmt' => '%H:00',     'trunc' => 'DATE_FORMAT(timestamp,\'%Y-%m-%d %H:00:00\')'],
    '7d'  => ['interval' => 'NOW() - INTERVAL 7 DAY',   'fmt' => '%m/%d %H:00','trunc'=> "DATE_FORMAT(DATE_SUB(timestamp, INTERVAL HOUR(timestamp) MOD 6 HOUR), '%Y-%m-%d %H:00:00')"],
    '30d' => ['interval' => 'NOW() - INTERVAL 30 DAY',  'fmt' => '%m/%d',     'trunc' => 'DATE_FORMAT(timestamp,\'%Y-%m-%d 00:00:00\')'],
];

$cfg = $intervals[$range] ?? $intervals['24h'];

$hiveWhere = '';
$params    = [];
$types     = '';
if ($hiveId !== 'all' && ctype_digit((string)$hiveId)) {
    $hiveWhere = 'AND hive_id = ?';
    $params[]  = (int)$hiveId;
    $types    .= 'i';
}

$sql = "SELECT
            {$cfg['trunc']} AS bucket,
            DATE_FORMAT({$cfg['trunc']}, '{$cfg['fmt']}') AS label,
            ROUND(AVG(temperature),1)  AS temp,
            ROUND(AVG(humidity),1)     AS humidity,
            ROUND(AVG(co2),0)          AS co2,
            ROUND(AVG(sound_level),1)  AS sound
        FROM sensor_data
        WHERE timestamp >= {$cfg['interval']}
          {$hiveWhere}
        GROUP BY bucket
        ORDER BY bucket ASC";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

$labels  = [];
$temp    = [];
$humidity= [];
$co2     = [];
$sound   = [];

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $labels[]   = $r['label'];
        $temp[]     = $r['temp'] !== null   ? (float)$r['temp']     : null;
        $humidity[] = $r['humidity'] !== null ? (float)$r['humidity'] : null;
        $co2[]      = $r['co2'] !== null     ? (float)$r['co2']      : null;
        $sound[]    = $r['sound'] !== null   ? (float)$r['sound']    : null;
    }
}

// Hive list for dropdown
$hivesRes = $conn->query("SELECT id, name, hive_id AS hive_code FROM hives ORDER BY name");
$hives = [];
while ($h = $hivesRes->fetch_assoc()) $hives[] = $h;

echo json_encode([
    'success' => true,
    'labels'  => $labels,
    'temp'    => $temp,
    'humidity'=> $humidity,
    'co2'     => $co2,
    'sound'   => $sound,
    'hives'   => $hives,
]);
