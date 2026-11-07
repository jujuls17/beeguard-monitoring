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

$hives = getAllHives($conn);
$data  = [];

foreach ($hives as $hive) {
    $sensor       = getLatestSensorData($conn, $hive['id']);
    $colonyStatus = classifyColonyByFreq($sensor['dominant_freq'] ?? null);
    $audio        = ['colony_status' => $colonyStatus, 'confidence' => 100];
    $badge        = getHiveStatusBadge($colonyStatus);

    $recommendations = getHiveRecommendations($sensor, $audio);
    $issues = 0;
    foreach ($recommendations as $rec) {
        if ($rec['type'] === 'critical' || $rec['type'] === 'warning') $issues++;
    }

    $isOnline = false;
    if ($sensor && !empty($sensor['timestamp'])) {
        $isOnline = (time() - strtotime($sensor['timestamp'])) <= 120;
    }

    $data[] = [
        'id'          => $hive['id'],
        'is_online'   => $isOnline,
        'sensor'      => $sensor ? [
            'temperature' => number_format((float)$sensor['temperature'], 1),
            'humidity'    => number_format((float)$sensor['humidity'], 1),
            'co2'         => (int)$sensor['co2'],
            'elapsed'     => time_elapsed_string($sensor['timestamp']),
        ] : null,
        'sound'         => $sensor ? round((float)($sensor['sound_level'] ?? 0)) : null,
        'dominant_freq' => $sensor ? (float)($sensor['dominant_freq'] ?? 0) : null,
        'badge'         => [
            'bg'         => $badge['bg'],
            'color'      => $badge['color'],
            'icon'       => $badge['icon'],
            'text'       => $badge['text'],
            'raw_status' => $colonyStatus,
        ],
        'issues'      => $issues,
    ];
}

echo json_encode(['success' => true, 'hives' => $data]);
