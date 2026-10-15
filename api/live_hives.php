<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

$hives = getAllHives($conn);
$data  = [];

foreach ($hives as $hive) {
    $sensor = getLatestSensorData($conn, $hive['id']);
    $audio  = getLatestAudioAnalysis($conn, $hive['id']);
    $badge  = $audio ? getHiveStatusBadge($audio['colony_status']) : null;

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
        'sound'       => $audio ? round((float)$audio['sound_level']) : null,
        'badge'       => $badge ? [
            'bg'         => $badge['bg'],
            'color'      => $badge['color'],
            'icon'       => $badge['icon'],
            'text'       => $badge['text'],
            'raw_status' => $audio['colony_status'] ?? 'normal',
        ] : null,
        'issues'      => $issues,
    ];
}

echo json_encode(['success' => true, 'hives' => $data]);
