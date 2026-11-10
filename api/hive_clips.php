<?php
/**
 * BeeGuard IoT - Hive mic clips for the local CNN worker (cnn_worker.py)
 *
 * All calls need the header  X-Api-Key: <same key as receive_data.php>
 *   GET  ?action=list              → clips waiting to be checked (oldest first)
 *   GET  ?action=get&name=<file>   → the .wav file itself
 *   POST ?action=done&name=<file>  → remove the clip once it has been checked
 */

ob_start();
ini_set('display_errors', 0);

define('API_KEY', 'BEEGUARD_SECRET_2026');
define('CLIP_DIR', __DIR__ . '/../uploads/hive_clips');

function out(int $code, array $body): void {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code($code);
    echo json_encode($body);
    exit;
}

if (($_SERVER['HTTP_X_API_KEY'] ?? '') !== API_KEY) out(401, ['success' => false, 'message' => 'Unauthorized']);

$action = $_GET['action'] ?? 'list';
$name   = basename($_GET['name'] ?? '');
$valid  = (bool)preg_match('/^hive(\d+)_(\d{8}_\d{6})(_[A-Za-z0-9_-]+)?\.wav$/', $name, $m);

if ($action === 'list') {
    $clips = [];
    foreach (glob(CLIP_DIR . '/hive*_*.wav') ?: [] as $path) {
        $file = basename($path);
        if (!preg_match('/^hive(\d+)_(\d{8}_\d{6})/', $file, $mm)) continue;
        $ts = DateTime::createFromFormat('Ymd_His', $mm[2]);
        $clips[] = [
            'name'        => $file,
            'hive_id'     => (int)$mm[1],
            'recorded_at' => $ts ? $ts->format('Y-m-d H:i:s') : null,
            'bytes'       => filesize($path),
        ];
    }
    usort($clips, fn($a, $b) => strcmp($a['recorded_at'] ?? '', $b['recorded_at'] ?? ''));
    out(200, ['success' => true, 'clips' => $clips]);
}

if (!$valid || !is_file(CLIP_DIR . '/' . $name)) out(404, ['success' => false, 'message' => 'Clip not found']);

if ($action === 'get') {
    ob_end_clean();
    header('Content-Type: audio/wav');
    header('Content-Length: ' . filesize(CLIP_DIR . '/' . $name));
    readfile(CLIP_DIR . '/' . $name);
    exit;
}

if ($action === 'done' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    @unlink(CLIP_DIR . '/' . $name);
    out(200, ['success' => true]);
}

out(400, ['success' => false, 'message' => 'Unknown action']);
