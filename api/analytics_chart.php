<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
header('Content-Type: application/json');

$range  = $_GET['range'] ?? '24h';
$hiveId = $_GET['hive']  ?? 'all';

$presets = [
    '1h'  => ['cond' => 'NOW() - INTERVAL 1 HOUR',  'group' => '%Y-%m-%d %H:%i', 'label' => '%H:%i', 'trunc' => 'minute'],
    '6h'  => ['cond' => 'NOW() - INTERVAL 6 HOUR',  'group' => '%Y-%m-%d %H',    'label' => '%H:00', 'trunc' => 'hour'],
    '24h' => ['cond' => 'NOW() - INTERVAL 24 HOUR', 'group' => '%Y-%m-%d %H',    'label' => '%H:00', 'trunc' => 'hour'],
    '7d'  => ['cond' => 'NOW() - INTERVAL 7 DAY',   'group' => '%Y-%m-%d',       'label' => '%m/%d', 'trunc' => 'day'],
    '30d' => ['cond' => 'NOW() - INTERVAL 30 DAY',  'group' => '%Y-%m-%d',       'label' => '%m/%d', 'trunc' => 'day'],
];

$whereParts = [];
$params     = [];
$types      = '';

if ($range === 'custom' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $from  = $_GET['from'];
    $to    = $_GET['to'];
    $whereParts[] = 'timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
    $params[]     = $from;
    $params[]     = $to;
    $types       .= 'ss';
    $days         = max(1, (strtotime($to) - strtotime($from)) / 86400);
    $groupFmt     = $days <= 1 ? '%Y-%m-%d %H' : '%Y-%m-%d';
    $labelFmt     = $days <= 1 ? '%H:00'        : '%m/%d';
} else {
    $p            = $presets[$range] ?? $presets['24h'];
    $whereParts[] = "timestamp >= {$p['cond']}";
    $groupFmt     = $p['group'];
    $labelFmt     = $p['label'];
}

if ($hiveId !== 'all' && ctype_digit((string)$hiveId)) {
    $whereParts[] = 'hive_id = ?';
    $params[]     = (int)$hiveId;
    $types       .= 'i';
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$sql = "SELECT
            DATE_FORMAT(timestamp, '$labelFmt')   AS label,
            ROUND(AVG(temperature), 1)            AS avg_temp,
            ROUND(AVG(humidity), 1)               AS avg_humidity,
            ROUND(AVG(co2))                       AS avg_co2,
            ROUND(AVG(sound_level), 1)            AS avg_sound
        FROM sensor_data
        $whereSQL
        GROUP BY DATE_FORMAT(timestamp, '$groupFmt')
        ORDER BY MIN(timestamp) ASC
        LIMIT 120";

$rows = [];
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
if ($result) {
    while ($r = $result->fetch_assoc()) $rows[] = $r;
}

echo json_encode(['success' => true, 'rows' => $rows]);
