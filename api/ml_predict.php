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
    $filename = basename($_REQUEST['filename'] ?? '');
    if (!$filename) {
        echo json_encode(['success' => false, 'message' => 'Filename is required.']);
        exit;
    }

    $filePath = SOUNDS_DIR . DIRECTORY_SEPARATOR . $filename;
    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'message' => 'Audio file not found on server.']);
        exit;
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
    $status = $result['colony_status'] ?? '';
    echo json_encode([
        'success'    => true,
        'status'     => $status,
        'label'      => $LABELS[$status] ?? ucfirst(str_replace('_', ' ', $status)),
        'confidence' => $result['confidence'] ?? 0,
        'all_probs'  => $result['all_probabilities'] ?? [],
        'frequency'  => $result['frequency'] ?? [],
    ]);

} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
