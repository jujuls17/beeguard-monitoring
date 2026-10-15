<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

// Ensure detected_devices table exists
$conn->query("CREATE TABLE IF NOT EXISTS detected_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL UNIQUE,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    times_seen INT DEFAULT 1
)");

$action = $_GET['action'] ?? 'hives';

if ($action === 'detected') {
    $result = $conn->query(
        "SELECT device_id, last_seen, times_seen FROM detected_devices ORDER BY last_seen DESC LIMIT 20"
    );
    $detected = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['last_seen'] = date('M d, H:i', strtotime($row['last_seen']));
            $detected[] = $row;
        }
    }
    echo json_encode(['detected' => $detected]);
    exit;
}

// Default: return all active hives with assigned device
$result = $conn->query(
    "SELECT h.*, ed.device_id as device
     FROM hives h
     LEFT JOIN edge_devices ed ON ed.hive_id = h.id
     ORDER BY h.id ASC"
);
$hives = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $hives[] = [
            'id'       => $row['id'],
            'name'     => $row['name'],
            'hive_id'  => $row['hive_id'],
            'location' => $row['location'] ?? '',
            'status'   => $row['status'],
            'device'   => $row['device'] ?? '',
        ];
    }
}
echo json_encode(['hives' => $hives]);
