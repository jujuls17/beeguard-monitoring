<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
ob_clean();

$hives = getAllHives($conn);
$rows  = [];

foreach ($hives as $hive) {
    $sensor       = getLatestSensorData($conn, $hive['id']);
    $colonyStatus = classifyColonyByFreq($sensor['dominant_freq'] ?? null);
    $badge        = getHiveStatusBadge($colonyStatus);

    $rows[] = [
        'name'        => $hive['name'],
        'hive_id'     => $hive['hive_id'],
        'sensor'      => $sensor ? [
            'temperature'   => (float)$sensor['temperature'],
            'humidity'      => (float)$sensor['humidity'],
            'co2'           => (int)$sensor['co2'],
            'sound_level'   => (float)($sensor['sound_level'] ?? 0),
            'dominant_freq' => (float)($sensor['dominant_freq'] ?? 0),
            'timestamp'     => $sensor['timestamp'],
            'elapsed'       => time_elapsed_string($sensor['timestamp']),
        ] : null,
        'badge' => [
            'text'       => $badge['text'],
            'bg'         => $badge['bg'],
            'text_color' => $badge['color'],
        ],
    ];
}

echo json_encode(['success' => true, 'hives' => $rows, 'updated_at' => date('H:i:s')]);
