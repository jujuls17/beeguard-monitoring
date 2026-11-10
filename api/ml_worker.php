<?php
/**
 * BeeGuard - ML bridge for the BeeGuard computer (laptop running ml_worker.py)
 *
 * The hosted site cannot run Python/TensorFlow, so training and sound classification
 * run locally on the BeeGuard computer. This endpoint lets that computer:
 *   GET  ?action=poll                         → is training requested? which uploads need a prediction?
 *   GET  ?action=dataset                      → list the training dataset files (ml/data/audio/{class}/)
 *   GET  ?action=file&kind=dataset&cls=&name= → download one dataset file
 *   GET  ?action=file&kind=upload&name=       → download one uploaded sound (uploads/sounds/)
 *   POST ?action=status      (JSON {status, log[]})       → training progress
 *   POST ?action=result      (multipart model + metrics)  → new model and its results
 *   POST ?action=prediction&name=  (JSON prediction)      → CNN result for an uploaded sound
 * Header: X-Api-Key (same shared key as the ESP32 / receive_data.php).
 */

ini_set('display_errors', 0);
define('API_KEY', 'BEEGUARD_SECRET_2026');

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== API_KEY) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$ML_DIR      = realpath(__DIR__ . '/../ml');
$MODELS_DIR  = $ML_DIR . '/models';
$AUDIO_DIR   = $ML_DIR . '/data/audio';
$SOUNDS_DIR  = __DIR__ . '/../uploads/sounds';
$STATUS_FILE = $ML_DIR . '/training_status.json';
$LOG_FILE    = $ML_DIR . '/training.log';
$SEEN_FILE   = $ML_DIR . '/worker_seen.txt';
$CLASSES     = ['normal', 'queenless', 'swarming'];

function out(array $a, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($a);
    exit;
}
function safeName(string $n): bool {
    return (bool)preg_match('/^[A-Za-z0-9][A-Za-z0-9._ -]{0,200}\.(wav|mp3|m4a|ogg)$/i', $n) && strpos($n, '..') === false;
}
function readStatus(string $f): array {
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?? []) : [];
}

$action = $_GET['action'] ?? '';
@file_put_contents($SEEN_FILE, (string)time());   // heartbeat: the BeeGuard computer is connected

switch ($action) {

case 'poll':
    $s = readStatus($STATUS_FILE);
    $pending = [];
    foreach (glob($SOUNDS_DIR . '/*.pending') ?: [] as $p) {
        $name = basename($p, '.pending');
        if (safeName($name) && file_exists($SOUNDS_DIR . '/' . $name)) $pending[] = $name;
        else @unlink($p);
    }
    out(['success' => true, 'train' => ['status' => $s['status'] ?? 'idle', 'requested' => $s['requested'] ?? null], 'predict' => $pending]);

case 'dataset':
    $files = [];
    foreach ($CLASSES as $cls) {
        foreach (array_merge(glob("$AUDIO_DIR/$cls/*.wav") ?: [], glob("$AUDIO_DIR/$cls/*.mp3") ?: []) as $f) {
            $files[] = ['cls' => $cls, 'name' => basename($f), 'size' => filesize($f)];
        }
    }
    out(['success' => true, 'files' => $files]);

case 'file':
    $name = $_GET['name'] ?? '';
    if (!safeName($name)) out(['success' => false, 'message' => 'Bad name'], 400);
    if (($_GET['kind'] ?? '') === 'dataset') {
        $cls = $_GET['cls'] ?? '';
        if (!in_array($cls, $CLASSES, true)) out(['success' => false, 'message' => 'Bad class'], 400);
        $path = "$AUDIO_DIR/$cls/$name";
    } else {
        $path = "$SOUNDS_DIR/$name";
    }
    if (!is_file($path)) out(['success' => false, 'message' => 'Not found'], 404);
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;

case 'status':
    $in = json_decode(file_get_contents('php://input'), true) ?? [];
    $st = $in['status'] ?? '';
    if (!in_array($st, ['running', 'done', 'error'], true)) out(['success' => false, 'message' => 'Bad status'], 400);
    $s = readStatus($STATUS_FILE);
    $s['status']  = $st;
    $s['updated'] = time();
    file_put_contents($STATUS_FILE, json_encode($s));
    if (isset($in['log']) && is_array($in['log'])) {
        $lines = array_slice(array_map(fn($l) => mb_substr((string)$l, 0, 300), $in['log']), -60);
        file_put_contents($LOG_FILE, implode("\n", $lines) . "\n");
    }
    out(['success' => true]);

case 'result':
    // Whitelisted result files only; the old model is kept as .bak first.
    $allowed = ['beeguard_cnn.keras', 'metrics.json', 'classification_report.txt',
                'confusion_matrix.csv', 'confusion_matrix.png', 'training_history.png'];
    if (empty($_FILES['beeguard_cnn_keras']) || $_FILES['beeguard_cnn_keras']['error'] !== UPLOAD_ERR_OK) {
        out(['success' => false, 'message' => 'Model file missing'], 400);
    }
    if (file_exists("$MODELS_DIR/beeguard_cnn.keras")) {
        @copy("$MODELS_DIR/beeguard_cnn.keras", "$MODELS_DIR/beeguard_cnn.keras.bak");
    }
    $saved = [];
    foreach ($allowed as $fname) {
        $field = str_replace('.', '_', $fname);
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            if (move_uploaded_file($_FILES[$field]['tmp_name'], "$MODELS_DIR/$fname")) $saved[] = $fname;
        }
    }
    out(['success' => true, 'saved' => $saved]);

case 'prediction':
    $name = $_GET['name'] ?? '';
    if (!safeName($name)) out(['success' => false, 'message' => 'Bad name'], 400);
    $pred = json_decode(file_get_contents('php://input'), true);
    if (!is_array($pred)) out(['success' => false, 'message' => 'Bad JSON'], 400);
    file_put_contents("$SOUNDS_DIR/$name.pred.json", json_encode($pred));
    @unlink("$SOUNDS_DIR/$name.pending");
    out(['success' => true]);

default:
    out(['success' => false, 'message' => 'Unknown action'], 400);
}
