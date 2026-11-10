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
$data  = [];

foreach ($hives as $hive) {
    $sensor = getLatestSensorData($conn, $hive['id']);
    $audio  = getLatestAudioAnalysis($conn, $hive['id']);

    // Colony status comes exclusively from the CNN (audio_analysis table).
    // If no CNN result exists, badge is null and the UI shows "No CNN result".
    $badge = $audio ? getHiveStatusBadge($audio['colony_status']) : null;

    $recommendations = getHiveRecommendations($sensor, $audio);
    $issues = 0;
    foreach ($recommendations as $rec) {
        if ($rec['type'] === 'critical' || $rec['type'] === 'warning') $issues++;
    }

    $isOnline = false;
    if ($sensor && !empty($sensor['timestamp'])) {
        $isOnline = (time() - strtotime($sensor['timestamp'])) <= 120;
    }

    // CNN clip time for display
    $cnnTimestamp = $audio ? $audio['timestamp'] : null;
    $cnnElapsed   = $cnnTimestamp ? time_elapsed_string($cnnTimestamp) : null;
    // Clip time: "H:i" if the clip is from today, otherwise "M d, H:i"
    $cnnTimeFormatted = null;
    $cnnAgeSec = null;
    if ($cnnTimestamp) {
        $cnnTs = strtotime($cnnTimestamp);
        $cnnTimeFormatted = (date('Y-m-d', $cnnTs) === date('Y-m-d')) ? date('H:i', $cnnTs) : date('M d, H:i', $cnnTs);
        $cnnAgeSec = max(0, time() - $cnnTs);
    }
    $sensorAgeSec = ($sensor && !empty($sensor['timestamp']))
        ? max(0, time() - strtotime($sensor['timestamp'])) : null;

    $data[] = [
        'id'          => $hive['id'],
        'is_online'   => $isOnline,
        'sensor'      => $sensor ? [
            'temperature' => number_format((float)$sensor['temperature'], 1),
            'humidity'    => number_format((float)$sensor['humidity'], 1),
            'co2'         => (int)$sensor['co2'],
            'elapsed'     => time_elapsed_string($sensor['timestamp']),
            'age_sec'     => $sensorAgeSec,
        ] : null,
        'sound'         => $sensor ? round((float)($sensor['sound_level'] ?? 0)) : null,
        'dominant_freq' => $sensor ? (float)($sensor['dominant_freq'] ?? 0) : null,
        'badge'         => $badge ? [
            'bg'         => $badge['bg'],
            'color'      => $badge['color'],
            'icon'       => $badge['icon'],
            'text'       => $badge['text'],
            'raw_status' => $audio['colony_status'],
            'cnn_time'   => $cnnTimeFormatted,
            'cnn_elapsed'=> $cnnElapsed,
            'cnn_age_sec'=> $cnnAgeSec,
        ] : null,
        'issues'      => $issues,
    ];
}

echo json_encode(['success' => true, 'hives' => $data]);
