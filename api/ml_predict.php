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
// Try .keras first (new format), fall back to .h5
$_cnn_keras = ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.keras';
$_cnn_h5    = ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.h5';
define('MODEL_FILE', file_exists($_cnn_keras) ? $_cnn_keras : $_cnn_h5);

$LABELS = [
    'normal'    => 'Normal',
    'queenless' => 'Queenless',
    'swarming'  => 'Swarming',
];

try {
    // Accept either an uploaded file or an existing filename
    if (!empty($_FILES['audio_file']['tmp_name'])) {
        $origName = basename($_FILES['audio_file']['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['wav', 'mp3', 'ogg', 'flac', 'm4a'])) {
            echo json_encode(['success' => false, 'message' => 'Unsupported audio format.']);
            exit;
        }
        $filename = 'live_' . time() . '_' . preg_replace('/[^a-z0-9._-]/i', '_', $origName);
        $filePath = SOUNDS_DIR . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($_FILES['audio_file']['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file.']);
            exit;
        }
    } else {
        $filename = basename($_REQUEST['filename'] ?? '');
        if (!$filename) {
            echo json_encode(['success' => false, 'message' => 'Filename or audio file is required.']);
            exit;
        }
        $filePath = SOUNDS_DIR . DIRECTORY_SEPARATOR . $filename;
        if (!file_exists($filePath)) {
            echo json_encode(['success' => false, 'message' => 'Audio file not found on server.']);
            exit;
        }
    }

    if (!file_exists(MODEL_FILE)) {
        echo json_encode(['success' => false, 'message' => 'Model not trained yet. Use the Retrain Model button first.']);
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
            'message' => $result['error'] ?? 'Prediction failed. Ensure Python and dependencies are installed.',
        ]);
        exit;
    }

    global $LABELS;
    $status     = $result['colony_status'] ?? '';
    $confidence = round((float)($result['confidence'] ?? 0), 2);
    $hiveId     = (int)($_REQUEST['hive_id'] ?? 0);

    // Save to audio_analysis if a hive is specified
    if ($hiveId > 0 && $status) {
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
    }

    echo json_encode([
        'success'    => true,
        'status'     => $status,
        'label'      => $LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        'confidence' => $confidence,
        'all_probs'  => $result['all_probabilities'] ?? [],
        'frequency'  => $result['frequency'] ?? [],
        'saved'      => $hiveId > 0,
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
