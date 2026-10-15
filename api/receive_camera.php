<?php
/**
 * BeeGuard - Camera Frame Receiver + Insect Detection Alerts
 * POST /api/receive_camera.php
 */

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

define('CAM_API_KEY', 'BEEGUARD_SECRET_2026');

if (($_POST['api_key'] ?? '') !== CAM_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Save image only when provided (MJPEG mode sends stats-only posts)
if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload = $_FILES['image'];
    $finfo  = finfo_open(FILEINFO_MIME_TYPE);
    $mime   = finfo_file($finfo, $upload['tmp_name']);
    finfo_close($finfo);
    if (in_array($mime, ['image/jpeg', 'image/png'])) {
        $savePath = __DIR__ . '/../uploads/camera/latest_frame.jpg';
        move_uploaded_file($upload['tmp_name'], $savePath);
    }
}

// ── Detection data from Python script ─────────────────────────
$hiveId      = (int)($_POST['hive_id']      ?? 1);
$beeCount    = (int)($_POST['bee_count']    ?? 0);
$insectCount = (int)($_POST['insect_count'] ?? 0);
$humanCount  = (int)($_POST['human_count']  ?? 0);
$objectCount = (int)($_POST['object_count'] ?? 0);
$detections  = [];

if (!empty($_POST['detections'])) {
    $decoded = json_decode($_POST['detections'], true);
    if (is_array($decoded)) {
        $detections = $decoded;
    }
}

$ts = date('Y-m-d H:i:s');

// ── Save metadata ──────────────────────────────────────────────
$camDir = __DIR__ . '/../uploads/camera';
if (!is_dir($camDir)) mkdir($camDir, 0755, true);

$meta = [
    'timestamp'    => $ts,
    'hive_id'      => $hiveId,
    'bee_count'    => $beeCount,
    'insect_count' => $insectCount,
    'human_count'  => $humanCount,
    'object_count' => $objectCount,
    'total'        => count($detections),
    'detections'   => $detections,
];
file_put_contents($camDir . '/meta.json', json_encode($meta));

// ── Alert: non-bee insects detected ───────────────────────────
if ($insectCount > 0) {
    // Throttle: check if an insect camera alert was created in the last 5 minutes
    $throttle = $conn->prepare(
        "SELECT id FROM alerts
         WHERE hive_id = ? AND alert_type = 'camera_insect'
           AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
         LIMIT 1"
    );

    $shouldAlert = true;
    if ($throttle) {
        $throttle->bind_param('i', $hiveId);
        $throttle->execute();
        $throttle->store_result();
        if ($throttle->num_rows > 0) {
            $shouldAlert = false; // already alerted recently
        }
        $throttle->close();
    }

    if ($shouldAlert) {
        $msg  = "Camera detected {$insectCount} non-bee insect(s) at hive entrance";
        $sev  = $insectCount >= 3 ? 'critical' : 'warning';

        // Check if alerts table has alert_type column; insert alert
        $stmt = $conn->prepare(
            "INSERT INTO alerts (hive_id, alert_type, message, severity, is_read, created_at)
             VALUES (?, 'camera_insect', ?, ?, 0, NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('iss', $hiveId, $msg, $sev);
            $stmt->execute();
            $stmt->close();
        }

        // Log activity
        logActivity($conn, $hiveId, 'camera_insect_detected',
            "Camera: {$beeCount} bee(s), {$insectCount} other insect(s) detected",
            $sev);
    }
}

echo json_encode([
    'success'      => true,
    'saved_at'     => $ts,
    'bee_count'    => $beeCount,
    'insect_count' => $insectCount,
]);
