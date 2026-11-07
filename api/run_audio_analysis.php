<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');
ob_clean();

define('ML_DIR',     realpath(__DIR__ . '/../ml'));
define('SOUNDS_DIR', realpath(__DIR__ . '/../uploads/sounds'));

$_cnn_keras = ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.keras';
$_cnn_h5    = ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.h5';
define('MODEL_FILE', file_exists($_cnn_keras) ? $_cnn_keras : $_cnn_h5);

$LABELS = [
    'normal'    => 'Normal',
    'queenless' => 'Queenless',
    'swarming'  => 'Swarming',
];

try {
    $hiveId = (int)($_REQUEST['hive_id'] ?? 0);
    if ($hiveId < 1) {
        echo json_encode(['success' => false, 'message' => 'hive_id is required.']);
        exit;
    }

    if (!file_exists(MODEL_FILE)) {
        echo json_encode(['success' => false, 'message' => 'Model not trained yet. Use the Retrain Model button first.']);
        exit;
    }

    // Pick the most recent sound file from uploads
    $res = $conn->query("SELECT stored_filename FROM sound_uploads ORDER BY uploaded_at DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No sound files found in training dataset.']);
        exit;
    }

    $filePath = SOUNDS_DIR . DIRECTORY_SEPARATOR . $row['stored_filename'];
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Sound file not found on disk: ' . $row['stored_filename']]);
        exit;
    }

    $python  = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $script  = ML_DIR . DIRECTORY_SEPARATOR . 'predict.py';
    $output  = [];
    $retCode = 0;

    exec(
        $python . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($filePath) . ' --json 2>&1',
        $output,
        $retCode
    );

    $raw    = trim(implode("\n", $output));
    $result = json_decode($raw, true);

    if (!is_array($result) || isset($result['error'])) {
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Prediction failed.',
        ]);
        exit;
    }

    $status     = $result['colony_status'] ?? '';
    $confidence = round((float)($result['confidence'] ?? 0), 2);
    $freq       = $result['frequency'] ?? [];
    $soundLevel = (float)($freq['sound_level'] ?? min((float)($freq['rms'] ?? 0) * 500, 100.0));
    $domFreq    = isset($freq['dominant_freq']) ? (float)$freq['dominant_freq'] : 0.0;

    $stmt = $conn->prepare(
        "INSERT INTO audio_analysis (hive_id, colony_status, confidence, dominant_freq, sound_level, timestamp)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param('isddd', $hiveId, $status, $confidence, $domFreq, $soundLevel);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        'success'    => true,
        'status'     => $status,
        'label'      => $LABELS[$status] ?? ucfirst($status),
        'confidence' => $confidence,
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
