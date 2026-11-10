<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

header('Content-Type: application/json');
ob_clean();

define('ML_DIR',      realpath(__DIR__ . '/../ml'));
define('LOG_FILE',    ML_DIR . DIRECTORY_SEPARATOR . 'training.log');
define('STATUS_FILE', ML_DIR . DIRECTORY_SEPARATOR . 'training_status.json');
// Primary: .keras (TF SavedModel format); fallback: .h5
define('MODEL_FILE',        ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.keras');
define('MODEL_FILE_LEGACY', ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'beeguard_cnn.h5');
define('SEEN_FILE',   ML_DIR . DIRECTORY_SEPARATOR . 'worker_seen.txt');
// Hosted server (Linux, no TensorFlow): training runs on the BeeGuard computer via ml_worker.py.
// The local XAMPP laptop (Windows) still trains directly. Create ml/LOCAL_TRAINING to train on a Linux box (e.g. Raspberry Pi).
define('HOSTED_MODE', PHP_OS_FAMILY !== 'Windows' && !file_exists(ML_DIR . DIRECTORY_SEPARATOR . 'LOCAL_TRAINING'));

function workerOnline(): bool {
    return file_exists(SEEN_FILE) && (time() - (int)file_get_contents(SEEN_FILE)) <= 60;
}
function setTrainingError(string $msg): void {
    file_put_contents(STATUS_FILE, json_encode(['status' => 'error', 'updated' => time()]));
    file_put_contents(LOG_FILE, "[ERROR] $msg
", FILE_APPEND);
}

$action = $_REQUEST['action'] ?? 'status';

try {
    if ($action === 'start') {
        echo json_encode(startTraining());
    } else {
        echo json_encode(getStatus());
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── Dataset ───────────────────────────────────────────────────
// Counts the files train_model.py actually trains on: ml/data/audio/{class}/*.wav|*.mp3
function getDatasetCounts(): array {
    $counts = [];
    foreach (['normal', 'queenless', 'swarming'] as $cls) {
        $dir = ML_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'audio' . DIRECTORY_SEPARATOR . $cls;
        $files = array_merge(glob($dir . '/*.wav') ?: [], glob($dir . '/*.mp3') ?: []);
        $counts[$cls] = count($files);
    }
    return $counts;
}

// ── Status ────────────────────────────────────────────────────
function getStatus(): array {
    $classCounts = getDatasetCounts();
    $count       = array_sum($classCounts);

    // Model metrics (saved by train_model.py)
    $metricsFile = ML_DIR . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . 'metrics.json';
    $metrics = file_exists($metricsFile) ? (json_decode(file_get_contents($metricsFile), true) ?? []) : [];

    $modelExists = file_exists(MODEL_FILE) || file_exists(MODEL_FILE_LEGACY);
    $activeModel = file_exists(MODEL_FILE) ? MODEL_FILE : (file_exists(MODEL_FILE_LEGACY) ? MODEL_FILE_LEGACY : null);
    $modelTime   = $activeModel ? date('M d, Y H:i', filemtime($activeModel)) : null;

    $status   = 'idle';
    $logLines = [];

    if (file_exists(STATUS_FILE)) {
        $s      = json_decode(file_get_contents(STATUS_FILE), true) ?? [];
        $status = $s['status'] ?? 'idle';
        $last   = (int)($s['updated'] ?? $s['requested'] ?? filemtime(STATUS_FILE));
        // Never leave the button stuck: give up on a request nobody picked up, or a run that went silent
        if ($status === 'queued' && time() - $last > 90) {
            setTrainingError('The BeeGuard computer did not start the training. Make sure ml_worker.py is running on it, then try again.');
            $status = 'error';
        } elseif ($status === 'running' && time() - $last > 900) {
            setTrainingError('Training stopped sending updates. Check the BeeGuard computer, then try again.');
            $status = 'error';
        }
    }

    if (file_exists(LOG_FILE)) {
        $logContent = file_get_contents(LOG_FILE);
        $all        = array_values(array_filter(explode("\n", $logContent), 'strlen'));
        $logLines   = array_slice($all, -25);

        if ($status === 'running') {
            if (str_contains($logContent, '[Done] Training complete!')) {
                $status = 'done';
                file_put_contents(STATUS_FILE, json_encode(['status' => 'done']));
            } elseif (str_contains($logContent, '[ERROR]') || str_contains($logContent, 'Traceback')) {
                $status = 'error';
                file_put_contents(STATUS_FILE, json_encode(['status' => 'error']));
            }
        }
    }

    return [
        'success'      => true,
        'status'       => $status,
        'sample_count' => $count,
        'class_counts' => $classCounts,
        'model_exists' => $modelExists,
        'model_time'   => $modelTime,
        'metrics'      => $metrics,
        'log'          => $logLines,
        'hosted_mode'  => HOSTED_MODE,
        'worker_online'=> workerOnline(),
    ];
}

// ── Start Training ─────────────────────────────────────────────
function startTraining(): array {
    global $conn;

    $count = array_sum(getDatasetCounts());

    if ($count < 2) {
        return ['success' => false, 'message' => "At least 2 sound samples required. You have {$count}."];
    }

    // Hosted server: hand the job to the BeeGuard computer (ml_worker.py)
    if (HOSTED_MODE) {
        $cur = getStatus()['status'];   // also clears stale queued/running states
        if ($cur === 'queued' || $cur === 'running') {
            return ['success' => false, 'message' => 'Training is already running. Please wait.'];
        }
        if (!workerOnline()) {
            return ['success' => false, 'message' => 'The BeeGuard computer is not connected. Start ml_worker.py on the laptop, wait a few seconds, then try again.'];
        }
        file_put_contents(LOG_FILE, "[Queue] Sent to the BeeGuard computer. Training starts in a few seconds...
");
        file_put_contents(STATUS_FILE, json_encode(['status' => 'queued', 'requested' => time(), 'updated' => time()]));
        $uname = $_SESSION['username'] ?? 'admin';
        logActivity($conn, null, 'ML Model Retrain',
            "Admin '$uname' started ML model retraining with $count sound samples (on the BeeGuard computer)", 'info');
        return ['success' => true, 'message' => 'Training sent to the BeeGuard computer.'];
    }

    // Guard: already running
    if (file_exists(STATUS_FILE)) {
        $s = json_decode(file_get_contents(STATUS_FILE), true) ?? [];
        if (($s['status'] ?? '') === 'running') {
            $log = file_exists(LOG_FILE) ? file_get_contents(LOG_FILE) : '';
            $done = str_contains($log, '[Done] Training complete!') || str_contains($log, 'Traceback');
            if (!$done) {
                return ['success' => false, 'message' => 'Training is already running. Please wait.'];
            }
        }
    }

    $scriptPath = ML_DIR . DIRECTORY_SEPARATOR . 'train_model.py';
    if (!file_exists($scriptPath)) {
        return ['success' => false, 'message' => 'Training script (ml/train_model.py) not found.'];
    }

    // Clear log and write running state
    file_put_contents(LOG_FILE, '');
    file_put_contents(STATUS_FILE, json_encode(['status' => 'running', 'started' => date('Y-m-d H:i:s')]));

    $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';

    if (PHP_OS_FAMILY === 'Windows') {
        // Write a batch file so quoting stays clean
        $bat  = ML_DIR . DIRECTORY_SEPARATOR . 'run_training.bat';
        $req  = ML_DIR . DIRECTORY_SEPARATOR . 'requirements.txt';
        file_put_contents($bat,
            "@echo off\r\n" .
            "set PYTHONIOENCODING=utf-8\r\n" .
            "echo [Setup] Installing Python dependencies...\r\n" .
            $python . " -m pip install --no-cache-dir --prefer-binary -r \"" . $req . "\" >> \"" . LOG_FILE . "\" 2>&1\r\n" .
            $python . " \"" . $scriptPath . "\" >> \"" . LOG_FILE . "\" 2>&1\r\n"
        );
        pclose(popen('start /B "" "' . $bat . '"', 'r'));
    } else {
        $req = ML_DIR . DIRECTORY_SEPARATOR . 'requirements.txt';
        exec($python . " -m pip install --no-cache-dir --prefer-binary -r " . escapeshellarg($req) . " >> " . escapeshellarg(LOG_FILE) . " 2>&1 && " .
             $python . " " . escapeshellarg($scriptPath) . " >> " . escapeshellarg(LOG_FILE) . " 2>&1 &");
    }

    $uname = $_SESSION['username'] ?? 'admin';
    logActivity($conn, null, 'ML Model Retrain',
        "Admin '$uname' started ML model retraining with $count sound samples", 'info');

    return ['success' => true, 'message' => 'Training started! This may take a few minutes depending on dataset size.'];
}
