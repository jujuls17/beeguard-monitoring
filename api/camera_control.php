<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

$action     = $_POST['action'] ?? '';
$hiveDbId   = (int)($_POST['hive_id'] ?? 1);
$scriptPath = realpath(__DIR__ . '/../camera_capture.py');
$batFile    = __DIR__ . '/../camera_start.bat';
$pidFile    = __DIR__ . '/../camera.pid';

// Python 3.11 — the one with ultralytics + PyTorch installed
$pythonCmd  = 'C:\\Users\\kantu\\AppData\\Local\\Programs\\Python\\Python311\\python.exe';

// Look up camera_index for this hive
$camIdx = 0;
if ($hiveDbId > 0) {
    $r = $conn->prepare("SELECT camera_index FROM hives WHERE id = ?");
    $r->bind_param("i", $hiveDbId);
    $r->execute();
    $row = $r->get_result()->fetch_assoc();
    $r->close();
    if ($row && $row['camera_index'] !== null) {
        $camIdx = (int)$row['camera_index'];
    }
}

function isCameraRunning($pidFile) {
    // Check port 5050 — most reliable (camera server always listens on it)
    $sock = @fsockopen('127.0.0.1', 5050, $errno, $errstr, 0.5);
    if ($sock) { fclose($sock); return true; }
    return false;
}

if ($action === 'start') {
    if (isCameraRunning($pidFile)) {
        echo json_encode(['success' => true, 'status' => 'already_running', 'message' => 'Camera is already running']);
        exit;
    }

    // Write a .bat that starts camera_capture.py with hive + camera index args
    $bat = "@echo off\r\n"
         . "start /B \"\" \"{$pythonCmd}\" \"{$scriptPath}\" --hive-id {$hiveDbId} --camera-index {$camIdx}\r\n";
    file_put_contents($batFile, $bat);

    pclose(popen("start /B \"\" cmd /c \"{$batFile}\"", 'r'));

    // Wait up to 4 seconds for camera port 5050 to open
    $started = false;
    for ($i = 0; $i < 8; $i++) {
        sleep(1);
        $sock = @fsockopen('127.0.0.1', 5050, $errno, $errstr, 0.5);
        if ($sock) { fclose($sock); $started = true; break; }
    }

    $uname = $_SESSION['username'] ?? 'unknown';
    logActivity($conn, null, 'Camera Started',
        "User '{$uname}' started the hive camera stream", 'info');

    if ($started) {
        echo json_encode(['success' => true, 'status' => 'started', 'message' => 'Camera started']);
    } else {
        echo json_encode(['success' => true, 'status' => 'starting', 'message' => 'Camera is starting, please wait a moment...']);
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

    // Reliable fallback: use PowerShell to find the process LISTENING on port 5050 and kill it
    $psCmd = 'powershell -NoProfile -Command "'
           . 'try {'
           . '  $conn = Get-NetTCPConnection -LocalPort 5050 -State Listen -ErrorAction Stop;'
           . '  Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue;'
           . '} catch {}'
           . '"';
    shell_exec($psCmd . ' 2>&1');

    $uname = $_SESSION['username'] ?? 'unknown';
    logActivity($conn, null, 'Camera Stopped',
        "User '{$uname}' stopped the hive camera stream", 'info');

    echo json_encode(['success' => true, 'status' => 'stopped', 'message' => 'Camera stopped']);
    exit;
}

if ($action === 'status') {
    // Check port 5050 — most reliable indicator
    $sock = @fsockopen('127.0.0.1', 5050, $errno, $errstr, 0.5);
    if ($sock) {
        fclose($sock);
        echo json_encode(['success' => true, 'running' => true]);
    } else {
        echo json_encode(['success' => true, 'running' => false]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
