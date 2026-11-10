<?php
ob_start();
ini_set('display_errors', 0);
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();
session_write_close();

$uploadDir = __DIR__ . '/uploads/sounds/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Ensure table exists
$conn->query("
    CREATE TABLE IF NOT EXISTS sound_uploads (
        id INT PRIMARY KEY AUTO_INCREMENT,
        original_filename VARCHAR(255) NOT NULL,
        stored_filename VARCHAR(255) NOT NULL,
        classification ENUM('normal','queenless','swarming') NOT NULL,
        file_size_kb DECIMAL(10,2),
        uploaded_by INT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_classification (classification),
        INDEX idx_uploaded_at (uploaded_at DESC)
    )
");

header('Content-Type: application/json');
ob_clean();

$labelMap = [
    'normal'    => 'Normal',
    'queenless' => 'Queenless',
    'swarming'  => 'Swarming',
];

// PREDICTION action — result for an uploaded sound classified by the BeeGuard computer (ml_worker.py)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'prediction') {
    $name = basename($_GET['name'] ?? '');
    $predFile = $uploadDir . $name . '.pred.json';
    if ($name !== '' && file_exists($predFile)) {
        $pred = json_decode(file_get_contents($predFile), true);
        echo json_encode(['success' => true, 'ready' => true, 'prediction' => $pred]);
    } else {
        echo json_encode(['success' => true, 'ready' => false,
                          'pending' => $name !== '' && file_exists($uploadDir . $name . '.pending')]);
    }
    exit;
}

// LIST action
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
    $sql = "SELECT id, original_filename, stored_filename, classification, file_size_kb, uploaded_at
            FROM sound_uploads ORDER BY classification ASC, uploaded_at DESC LIMIT 200";
    $result = $conn->query($sql);
    $uploads = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $uploads[] = [
                'id'                 => $row['id'],
                'filename'           => $row['original_filename'],
                'stored_filename'    => $row['stored_filename'],
                'classification'     => $labelMap[$row['classification']] ?? $row['classification'],
                'classification_key' => $row['classification'],
                'size'               => $row['file_size_kb'] . ' KB',
                'uploaded_at'        => date('M d, Y H:i', strtotime($row['uploaded_at'])),
            ];
        }
    }
    echo json_encode(['success' => true, 'uploads' => $uploads]);
    exit;
}

// DELETE action
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Missing ID.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT stored_filename FROM sound_uploads WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'File not found.']);
        exit;
    }
    $filePath = $uploadDir . $row['stored_filename'];
    $del = $conn->prepare("DELETE FROM sound_uploads WHERE id = ?");
    $del->bind_param('i', $id);
    if (!$del->execute()) {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
        exit;
    }
    if (file_exists($filePath)) @unlink($filePath);
    @unlink($filePath . '.pending');
    @unlink($filePath . '.pred.json');
    foreach (glob(__DIR__ . '/ml/data/audio/*/' . basename($row['stored_filename'])) ?: [] as $copy) @unlink($copy);
    $uname = $_SESSION['username'] ?? 'user';
    logActivity($conn, null, 'Sound Deleted',
        "User '$uname' deleted sound file: {$row['stored_filename']}", 'warning');
    echo json_encode(['success' => true, 'message' => 'Sound file deleted.']);
    exit;
}

// UPLOAD action
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$allowedExt = ['wav', 'mp3', 'm4a', 'ogg'];
$maxSize    = 50 * 1024 * 1024; // 50MB

if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file           = $_FILES['audio_file'];
$ext            = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$classification = trim($_POST['classification'] ?? '');

if (!array_key_exists($classification, $labelMap)) {
    echo json_encode(['success' => false, 'message' => 'Invalid classification.']);
    exit;
}
if (!in_array($ext, $allowedExt)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: WAV, MP3, M4A, OGG.']);
    exit;
}
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 50MB.']);
    exit;
}

$storedName = uniqid('sound_', true) . '.' . $ext;
$destPath   = $uploadDir . $storedName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Check server permissions.']);
    exit;
}

$originalName = $conn->real_escape_string(basename($file['name']));
$fileSizeKb   = round($file['size'] / 1024, 2);
$userId       = $_SESSION['user_id'];

$stmt = $conn->prepare("INSERT INTO sound_uploads (original_filename, stored_filename, classification, file_size_kb, uploaded_by) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param('sssdi', $originalName, $storedName, $classification, $fileSizeKb, $userId);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'File saved but database record failed: ' . $conn->error]);
    $stmt->close();
    exit;
}
$stmt->close();

// Copy into the training dataset (train_model.py only reads .wav/.mp3 from ml/data/audio/{class}/)
if (in_array($ext, ['wav', 'mp3'])) {
    $datasetDir = __DIR__ . '/ml/data/audio/' . $classification . '/';
    if (!is_dir($datasetDir)) mkdir($datasetDir, 0755, true);
    @copy($destPath, $datasetDir . $storedName);
}

$uname = $_SESSION['username'] ?? 'user';
$labelDisplay = $labelMap[$classification] ?? $classification;
logActivity($conn, null, 'Sound Uploaded',
    "User '$uname' uploaded sound file: $originalName (Classification: $labelDisplay, Size: {$fileSizeKb}KB)", 'info');

$response = [
    'success'          => true,
    'stored_filename'  => $storedName,
    'prediction'       => null,
    'message'          => 'Sound file uploaded! Train the model in Settings to enable auto-classification.',
];

// Auto-predict if a trained CNN model already exists
$modelPath  = __DIR__ . '/ml/models/beeguard_cnn.keras';
$modelPathH5 = __DIR__ . '/ml/models/beeguard_cnn.h5';
$scriptPath = __DIR__ . '/ml/predict.py';
$activeModel = file_exists($modelPath) ? $modelPath : (file_exists($modelPathH5) ? $modelPathH5 : null);

$canExec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
$hostedMode = PHP_OS_FAMILY !== 'Windows' && !file_exists(__DIR__ . '/ml/LOCAL_TRAINING');
if ($activeModel && file_exists($scriptPath) && $canExec && !$hostedMode) {
    $python = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $out    = [];
    exec(
        $python . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($destPath) . ' --json 2>&1',
        $out
    );
    $pred = json_decode(trim(implode("\n", $out)), true);

    if (is_array($pred) && !isset($pred['error'])) {
        $labelMapPredict = [
            'normal'    => 'Normal',
            'queenless' => 'Queenless',
            'swarming'  => 'Swarming',
        ];
        $status = $pred['colony_status'] ?? '';
        $response['prediction'] = [
            'status'     => $status,
            'label'      => $labelMapPredict[$status] ?? ucfirst(str_replace('_', ' ', $status)),
            'confidence' => $pred['confidence'] ?? 0,
            'all_probs'  => $pred['all_probabilities'] ?? [],
        ];
        $response['message'] = 'Sound uploaded and classified by the CNN model!';
    }
}

// Hosted server: the BeeGuard computer (ml_worker.py) classifies it and the page picks up the result
if ($response['prediction'] === null && $hostedMode && $activeModel) {
    @touch($destPath . '.pending');
    $response['pending_prediction'] = $storedName;
    $response['message'] = 'Sound uploaded. The BeeGuard computer is classifying it with the CNN model...';
}

echo json_encode($response);
