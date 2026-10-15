<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

$hives = getAllHives($conn);
$rows  = [];

foreach ($hives as $hive) {
    $sensor = getLatestSensorData($conn, $hive['id']);
    $audio  = getLatestAudioAnalysis($conn, $hive['id']);
    $badge  = $audio ? getHiveStatusBadge($audio['colony_status']) : null;

    $rows[] = [
        'name'        => $hive['name'],
        'hive_id'     => $hive['hive_id'],
        'sensor'      => $sensor ? [
            'temperature' => (float)$sensor['temperature'],
            'humidity'    => (float)$sensor['humidity'],
            'co2'         => (int)$sensor['co2'],
            'sound_level' => (float)$sensor['sound_level'],
            'timestamp'   => $sensor['timestamp'],
            'elapsed'     => time_elapsed_string($sensor['timestamp']),
        ] : null,
        'badge' => $badge ? [
            'text'       => $badge['text'],
            'bg'         => $badge['bg'],
            'text_color' => $badge['color'],
        ] : null,
    ];
}

echo json_encode(['success' => true, 'hives' => $rows, 'updated_at' => date('H:i:s')]);
