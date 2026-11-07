<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();

header('Content-Type: application/json');
ob_clean();

$action   = $_POST['action'] ?? '';
$hiveDbId = (int)($_POST['hive_id'] ?? 1);
$scriptPath = realpath(__DIR__ . '/../camera_capture.py');

// Python 3.11 — the one with ultralytics + PyTorch installed
$pythonCmd = 'C:\\Users\\kantu\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';

// Look up camera_index for this hive
$camIdx = 1;
if ($hiveDbId > 0) {
    $r = $conn->prepare("SELECT camera_index FROM hives WHERE id = ?");
    if ($r) {
        $r->bind_param("i", $hiveDbId);
        $r->execute();
        $row = $r->get_result()->fetch_assoc();
        $r->close();
        if ($row && $row['camera_index'] !== null) $camIdx = (int)$row['camera_index'];
    }
}

// Try to get stream_port from DB; fall back to derived value (5049 + camera_index)
// camera_index=1 → port 5050 (Alpha), camera_index=2 → port 5051 (Beta)
$streamPort = 5049 + $camIdx;
$rp = $conn->query("SHOW COLUMNS FROM hives LIKE 'stream_port'");
if ($rp && $rp->num_rows > 0) {
    $rsp = $conn->prepare("SELECT stream_port FROM hives WHERE id = ?");
    if ($rsp) {
        $rsp->bind_param("i", $hiveDbId);
        $rsp->execute();
        $rowSp = $rsp->get_result()->fetch_assoc();
        $rsp->close();
        if ($rowSp && $rowSp['stream_port']) $streamPort = (int)$rowSp['stream_port'];
    }
}

// Per-hive files
$batFile = __DIR__ . "/../camera_start_hive{$hiveDbId}.bat";
$pidFile = __DIR__ . "/../camera_hive{$hiveDbId}.pid";

function isCameraRunning($port) {
    $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
    if ($sock) { fclose($sock); return true; }
    return false;
}

if ($action === 'start') {
    if (isCameraRunning($streamPort)) {
        echo json_encode(['success' => true, 'status' => 'already_running',
                          'message' => 'Camera is already running', 'port' => $streamPort]);
        exit;
    }

    $bat = "@echo off\r\n"
         . "start /B \"\" \"{$pythonCmd}\" \"{$scriptPath}\""
         . " --hive-id {$hiveDbId}"
         . " --camera-index {$camIdx}"
         . " --stream-port {$streamPort}\r\n";
    file_put_contents($batFile, $bat);

    pclose(popen("start /B \"\" cmd /c \"{$batFile}\"", 'r'));

    // Wait up to 8 s for the per-hive port to open
    $started = false;
    for ($i = 0; $i < 8; $i++) {
        sleep(1);
        if (isCameraRunning($streamPort)) { $started = true; break; }
    }

    $uname = $_SESSION['username'] ?? 'unknown';
    logActivity($conn, $hiveDbId, 'Camera Started',
        "User '{$uname}' started camera stream for hive {$hiveDbId} on port {$streamPort}", 'info');

    if ($started) {
        echo json_encode(['success' => true, 'status' => 'started',
                          'message' => 'Camera started', 'port' => $streamPort]);
    } else {
        echo json_encode(['success' => true, 'status' => 'starting',
                          'message' => 'Camera is starting, please wait…', 'port' => $streamPort]);
    }
    exit;
}

if ($action === 'stop') {
    // Kill by saved PID first
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if ($pid && is_numeric($pid)) shell_exec("taskkill /F /PID {$pid} 2>&1");
        unlink($pidFile);
    }

    // Kill the process listening on the per-hive port
    $psCmd = 'powershell -NoProfile -Command "'
           . 'try {'
           . "  \$conn = Get-NetTCPConnection -LocalPort {$streamPort} -State Listen -ErrorAction Stop;"
           . '  Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue;'
           . '} catch {}'
           . '"';
    shell_exec($psCmd . ' 2>&1');

    $uname = $_SESSION['username'] ?? 'unknown';
    logActivity($conn, $hiveDbId, 'Camera Stopped',
        "User '{$uname}' stopped camera stream for hive {$hiveDbId}", 'info');

    echo json_encode(['success' => true, 'status' => 'stopped',
                      'message' => 'Camera stopped', 'port' => $streamPort]);
    exit;
}

if ($action === 'status') {
    $running = isCameraRunning($streamPort);
    echo json_encode(['success' => true, 'running' => $running, 'port' => $streamPort]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
