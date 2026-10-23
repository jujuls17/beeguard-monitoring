<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
session_write_close();
header('Content-Type: application/json');

// Beekeepers can only see hive activities — force tab to 'hive'
$tab = (!isAdmin() || !in_array($_GET['tab'] ?? '', ['hive', 'user'])) ? 'hive' : $_GET['tab'];
$range  = $_GET['range']   ?? '7d';
$hiveId = $_GET['hive_id'] ?? 'all';
$userId = $_GET['user_id'] ?? 'all';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$presets = [
    '1h'  => 'NOW() - INTERVAL 1 HOUR',
    '6h'  => 'NOW() - INTERVAL 6 HOUR',
    '24h' => 'NOW() - INTERVAL 24 HOUR',
    '7d'  => 'NOW() - INTERVAL 7 DAY',
    '30d' => 'NOW() - INTERVAL 30 DAY',
];

$whereParts = [];
$params     = [];
$types      = '';

// Time range
if ($range === 'custom' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $whereParts[] = 'al.timestamp BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
    $params[]     = $_GET['from'];
    $params[]     = $_GET['to'];
    $types       .= 'ss';
} elseif (isset($presets[$range])) {
    $whereParts[] = "al.timestamp >= {$presets[$range]}";
}

// Tab filter
if ($tab === 'hive') {
    $whereParts[] = 'al.hive_id IS NOT NULL';
    if ($hiveId !== 'all' && ctype_digit((string)$hiveId)) {
        $whereParts[] = 'al.hive_id = ?';
        $params[]     = (int)$hiveId;
        $types       .= 'i';
    }
} else {
    // User/admin tab — exclude automated sensor/camera noise
    $whereParts[] = "al.event_type NOT IN ('Data Collection', 'camera_insect_detected')";
    if ($userId !== 'all' && ctype_digit((string)$userId)) {
        $whereParts[] = 'al.user_id = ?';
        $params[]     = (int)$userId;
        $types       .= 'i';
    }
}

$whereSQL = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Count total
$countSQL = "SELECT COUNT(*) AS total FROM activity_log al $whereSQL";
if ($params) {
    $stmt = $conn->prepare($countSQL);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    $total = (int)$conn->query($countSQL)->fetch_assoc()['total'];
}

// Fetch rows
$dataSQL = "SELECT al.id, al.hive_id, al.user_id, al.event_type, al.details, al.severity, al.timestamp,
                   h.name AS hive_name, h.hive_id AS hive_code,
                   u.username, u.full_name, u.role AS user_role
            FROM activity_log al
            LEFT JOIN hives h ON h.id = al.hive_id
            LEFT JOIN users u ON u.id = al.user_id
            $whereSQL
            ORDER BY al.timestamp DESC
            LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$limit, $offset]);
$dataTypes  = $types . 'ii';

$stmt = $conn->prepare($dataSQL);
$stmt->bind_param($dataTypes, ...$dataParams);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($r = $result->fetch_assoc()) {
    $rows[] = [
        'id'         => (int)$r['id'],
        'event_type' => $r['event_type'],
        'details'    => $r['details'],
        'severity'   => $r['severity'],
        'timestamp'  => $r['timestamp'],
        'hive_name'  => $r['hive_name'],
        'hive_code'  => $r['hive_code'],
        'username'   => $r['username'],
        'full_name'  => $r['full_name'],
        'user_role'  => $r['user_role'],
    ];
}

echo json_encode([
    'success' => true,
    'rows'    => $rows,
    'total'   => $total,
    'page'    => $page,
    'pages'   => (int)ceil($total / $limit),
]);
