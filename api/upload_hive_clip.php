<?php
/**
 * BeeGuard IoT - Hive mic clip upload (from the ESP32)
 *
 * Every few minutes the ESP32 records a short clip (5 s) from the hive's MAX4466
 * microphone and POSTs it here as a .wav file. The clip is only stored here.
 * The CNN runs on the local server (laptop): cnn_worker.py downloads new clips
 * via api/hive_clips.php, classifies them, and posts the result to
 * api/update_colony_status.php.
 *
 * POST  /api/upload_hive_clip.php?device_id=ESP32-001&hive_id=1
 * Headers: X-Api-Key: <same key as receive_data.php>
 *          Content-Type: audio/wav
 * Body:    the raw .wav file
 */

ob_start();
ini_set('display_errors', 0);
require_once '../config.php';

header('Content-Type: application/json');
ob_clean();

define('API_KEY', 'BEEGUARD_SECRET_2026');
define('CLIP_DIR', __DIR__ . '/../uploads/hive_clips');
define('MAX_CLIP_BYTES', 600 * 1024);   // a 5 s 8-bit 22 kHz clip is ~110 KB
define('KEEP_PENDING_PER_HIVE', 5);     // if the laptop is off, don't pile up clips

function out(int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['success' => false, 'message' => 'Method not allowed']);
if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== API_KEY) out(401, ['success' => false, 'message' => 'Unauthorized']);

$deviceId = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['device_id'] ?? '');
$hiveId   = (int)($_GET['hive_id'] ?? 0);

// Same rule as receive_data.php: if the board doesn't say its hive, look it up
if ($hiveId === 0 && $deviceId !== '') {
    $q = $conn->prepare("SELECT hive_id FROM edge_devices WHERE device_id = ? LIMIT 1");
    $q->bind_param('s', $deviceId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();
    $hiveId = $row ? (int)$row['hive_id'] : 0;
}
if ($hiveId <= 0) out(422, ['success' => false, 'message' => 'Unknown hive']);

$wav = file_get_contents('php://input');
$len = strlen($wav);
if ($len < 1000 || $len > MAX_CLIP_BYTES) out(413, ['success' => false, 'message' => "Bad clip size ($len bytes)"]);
if (substr($wav, 0, 4) !== 'RIFF' || substr($wav, 8, 4) !== 'WAVE') out(415, ['success' => false, 'message' => 'Not a WAV file']);

if (!is_dir(CLIP_DIR)) mkdir(CLIP_DIR, 0755, true);
// Clips are only for the laptop worker (it downloads them with the API key), never public
if (!file_exists(CLIP_DIR . '/.htaccess')) file_put_contents(CLIP_DIR . '/.htaccess', "Require all denied\nDeny from all\n");

$name = sprintf('hive%d_%s%s.wav', $hiveId, date('Ymd_His'), $deviceId !== '' ? '_' . $deviceId : '');
if (file_put_contents(CLIP_DIR . '/' . $name, $wav) === false) out(500, ['success' => false, 'message' => 'Could not save clip']);

// Keep only the newest few waiting clips for this hive
$pending = glob(CLIP_DIR . "/hive{$hiveId}_*.wav") ?: [];
sort($pending);
while (count($pending) > KEEP_PENDING_PER_HIVE) @unlink(array_shift($pending));

out(200, ['success' => true, 'message' => 'Clip stored', 'file' => $name, 'hive_id' => $hiveId, 'bytes' => $len]);
