<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$emailNotif   = isset($_POST['email_notifications'])  ? (int)$_POST['email_notifications']  : 0;
$pushNotif    = isset($_POST['push_notifications'])   ? (int)$_POST['push_notifications']   : 0;
$alertCrit    = isset($_POST['alert_critical'])       ? (int)$_POST['alert_critical']       : 0;
$alertWarn    = isset($_POST['alert_warnings'])       ? (int)$_POST['alert_warnings']       : 0;
$alertInfo    = isset($_POST['alert_informational'])  ? (int)$_POST['alert_informational']  : 0;
$retention    = in_array($_POST['data_retention'] ?? '', ['90','180','365','unlimited']) ? $_POST['data_retention'] : '90';
$syncInterval = in_array((int)($_POST['sync_interval'] ?? 300), [30,60,300,600,900,1800]) ? (int)$_POST['sync_interval'] : 300;
$mlConf       = max(50, min(99, (int)($_POST['ml_confidence'] ?? 85)));

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    INSERT INTO user_settings
        (user_id, email_notifications, push_notifications, alert_critical, alert_warnings, alert_informational, data_retention, sync_interval, ml_confidence)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        email_notifications  = VALUES(email_notifications),
        push_notifications   = VALUES(push_notifications),
        alert_critical       = VALUES(alert_critical),
        alert_warnings       = VALUES(alert_warnings),
        alert_informational  = VALUES(alert_informational),
        data_retention       = VALUES(data_retention),
        sync_interval        = VALUES(sync_interval),
        ml_confidence        = VALUES(ml_confidence)
");

$stmt->bind_param("iiiiiisii",
    $userId, $emailNotif, $pushNotif, $alertCrit, $alertWarn,
    $alertInfo, $retention, $syncInterval, $mlConf
);

if ($stmt->execute()) {
    $uname = $_SESSION['username'] ?? 'user';
    logActivity($conn, null, 'Settings Saved',
        "User '$uname' updated notification and system settings", 'info');
    echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save settings: ' . $conn->error]);
}
$stmt->close();
