<?php
// Returns latest camera detection metadata (bee/insect counts + timestamp)
header('Content-Type: application/json');
$meta = @file_get_contents(__DIR__ . '/../uploads/camera/meta.json');
if (!$meta) {
    echo json_encode(['online' => false]);
    exit;
}
$data = json_decode($meta, true);
if (!$data) {
    echo json_encode(['online' => false]);
    exit;
}

// Consider camera offline if last frame is older than 30 seconds
$age = time() - strtotime($data['timestamp']);
$data['online'] = $age < 30;
echo json_encode($data);
