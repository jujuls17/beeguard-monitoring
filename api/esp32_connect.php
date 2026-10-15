<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');

$result = $conn->query(
    "SELECT d.device_id, d.device_name, d.status,
            h.name AS hive_name,
            UNIX_TIMESTAMP(d.last_sync) AS last_sync_ts,
            DATE_FORMAT(d.last_sync, '%h:%i:%s %p') AS last_sync_fmt
     FROM edge_devices d
     LEFT JOIN hives h ON d.hive_id = h.id
     ORDER BY d.id ASC"
);

$devices = [];
if ($result) {
    while ($r = $result->fetch_assoc()) {
        $devices[] = [
            'device_id'    => $r['device_id'],
            'device_name'  => $r['device_name'],
            'status'       => $r['status'],
            'hive_name'    => $r['hive_name'] ?? 'Unassigned',
            'last_sync_ts' => (int)$r['last_sync_ts'],
            'last_sync_fmt'=> $r['last_sync_fmt'] ?? 'Never',
        ];
    }
}

echo json_encode(['success' => true, 'devices' => $devices]);
