<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
header('Content-Type: application/json');

$range  = $_GET['range']  ?? '24h';
$hiveId = $_GET['hive']   ?? 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$presets = [
    '1h'  => 'NOW() - INTERVAL 1 HOUR',
    '6h'  => 'NOW() - INTERVAL 6 HOUR',
    '24h' => 'NOW() - INTERVAL 24 HOUR',
    '7d'  => 'NOW() - INTERVAL 7 DAY',
    '30d' => 'NOW() - INTERVAL 30 DAY',
];

$whereParts = [];
$params     = [];
$types      = '';

if ($range === 'custom' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $whereParts[] = 'sd.timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
    $params[]     = $from;
    $params[]     = $to;
    $types       .= 'ss';
} elseif (isset($presets[$range])) {
    $whereParts[] = "sd.timestamp >= {$presets[$range]}";
}

if ($hiveId !== 'all' && ctype_digit((string)$hiveId)) {
    $whereParts[] = 'sd.hive_id = ?';
    $params[]     = (int)$hiveId;
    $types       .= 'i';
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Total count
$countSQL = "SELECT COUNT(*) AS total FROM sensor_data sd $whereSQL";
if ($params) {
    $stmt = $conn->prepare($countSQL);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
} else {
    $total = $conn->query($countSQL)->fetch_assoc()['total'];
}

// Fetch rows
$dataSQL = "SELECT sd.id, sd.temperature, sd.humidity, sd.co2, sd.sound_level,
                   sd.timestamp, h.name AS hive_name, h.hive_id AS hive_code
            FROM sensor_data sd
            JOIN hives h ON h.id = sd.hive_id
            $whereSQL
            ORDER BY sd.timestamp DESC
            LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes  = $types . 'ii';

$stmt = $conn->prepare($dataSQL);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = [
        'hive_name'   => $r['hive_name'],
        'hive_code'   => $r['hive_code'],
        'temperature' => (float)$r['temperature'],
        'humidity'    => (float)$r['humidity'],
        'co2'         => (int)$r['co2'],
        'sound_level' => (float)$r['sound_level'],
        'timestamp'   => $r['timestamp'],
    ];
}

echo json_encode([
    'success' => true,
    'rows'    => $rows,
    'total'   => (int)$total,
    'page'    => $page,
    'pages'   => (int)ceil($total / $limit),
    'limit'   => $limit,
]);
