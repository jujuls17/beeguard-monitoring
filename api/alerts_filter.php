<?php
session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();
header('Content-Type: application/json');

$tab      = ($_GET['tab']      ?? 'active') === 'history' ? 'history' : 'active';
$severity = $_GET['severity']  ?? 'all';
$hiveId   = $_GET['hive_id']   ?? 'all';
$type     = $_GET['type']      ?? 'all';
$range    = $_GET['range']     ?? 'all';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 50;
$offset   = ($page - 1) * $limit;

$presets = [
    '1h'  => 'NOW() - INTERVAL 1 HOUR',
    '24h' => 'NOW() - INTERVAL 24 HOUR',
    '7d'  => 'NOW() - INTERVAL 7 DAY',
    '30d' => 'NOW() - INTERVAL 30 DAY',
];

$whereParts = [];
$params     = [];
$types      = '';

// Active vs history
$whereParts[] = $tab === 'history' ? 'a.is_read = 1' : 'a.is_read = 0';

// Severity filter
$valid_sev = ['critical','warning','info'];
if (in_array($severity, $valid_sev)) {
    $whereParts[] = 'a.severity = ?';
    $params[]     = $severity;
    $types       .= 's';
}

// Hive filter
if ($hiveId !== 'all' && ctype_digit((string)$hiveId)) {
    $whereParts[] = 'a.hive_id = ?';
    $params[]     = (int)$hiveId;
    $types       .= 'i';
}

// Alert type filter
if ($type !== 'all' && preg_match('/^[a-z_]+$/', $type)) {
    $whereParts[] = 'a.alert_type = ?';
    $params[]     = $type;
    $types       .= 's';
}

// Time range filter
if ($range === 'custom' && !empty($_GET['from']) && !empty($_GET['to'])) {
    $whereParts[] = 'a.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)';
    $params[]     = $_GET['from'];
    $params[]     = $_GET['to'];
    $types       .= 'ss';
} elseif (isset($presets[$range])) {
    $whereParts[] = "a.created_at >= {$presets[$range]}";
}

$whereSQL = 'WHERE ' . implode(' AND ', $whereParts);

// Total count
$countSQL = "SELECT COUNT(*) AS total FROM alerts a JOIN hives h ON h.id = a.hive_id $whereSQL";
if ($params) {
    $stmt = $conn->prepare($countSQL);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = (int)$stmt->get_result()->fetch_assoc()['total'];
} else {
    $total = (int)$conn->query($countSQL)->fetch_assoc()['total'];
}

// Fetch rows
$dataSQL = "SELECT a.id, a.alert_type, a.severity, a.message, a.recommended_action,
                   a.is_read, a.created_at,
                   h.name AS hive_name, h.hive_id AS hive_code
            FROM alerts a
            JOIN hives h ON h.id = a.hive_id
            $whereSQL
            ORDER BY a.created_at DESC
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
        'id'                 => (int)$r['id'],
        'alert_type'         => $r['alert_type'],
        'severity'           => $r['severity'],
        'message'            => $r['message'],
        'recommended_action' => $r['recommended_action'],
        'created_at'         => $r['created_at'],
        'hive_name'          => $r['hive_name'],
        'hive_code'          => $r['hive_code'],
    ];
}

// Also return distinct alert types for the filter dropdown (from all alerts)
$typesRes = $conn->query("SELECT DISTINCT alert_type FROM alerts WHERE alert_type IS NOT NULL ORDER BY alert_type");
$alertTypes = [];
while ($tr = $typesRes->fetch_assoc()) $alertTypes[] = $tr['alert_type'];

echo json_encode([
    'success'     => true,
    'rows'        => $rows,
    'total'       => $total,
    'page'        => $page,
    'pages'       => (int)ceil($total / $limit),
    'alert_types' => $alertTypes,
]);
