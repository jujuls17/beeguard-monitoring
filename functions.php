<?php
// functions.php - Helper Functions for BeeGuard IoT Monitor

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireAdmin() {
    checkAuth();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function getUserById($conn, $id) {
    $stmt = $conn->prepare(
        "SELECT u.*, h.name AS assigned_hive_name
         FROM users u
         LEFT JOIN hives h ON u.assigned_hive_id = h.id
         WHERE u.id = ?"
    );
    if (!$stmt) return null;
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getAllUsers($conn) {
    $sql = "SELECT u.*, h.name AS assigned_hive_name
            FROM users u
            LEFT JOIN hives h ON u.assigned_hive_id = h.id
            ORDER BY u.created_at DESC";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getInspectionNotes($conn, $hive_id = null, $user_id = null) {
    $where = [];
    $params = "";
    $binds  = [];
    if ($hive_id) { $where[] = "n.hive_id = ?"; $params .= "i"; $binds[] = $hive_id; }
    if ($user_id) { $where[] = "n.user_id = ?"; $params .= "i"; $binds[] = $user_id; }
    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";
    $sql = "SELECT n.*, h.name AS hive_name, h.hive_id AS hive_code,
                   u.username AS inspector_username, u.full_name AS inspector_name,
                   u.role AS inspector_role
            FROM inspection_notes n
            JOIN hives h ON n.hive_id = h.id
            JOIN users u ON n.user_id = u.id
            $whereSql
            ORDER BY n.inspection_date DESC, n.created_at DESC";
    if ($binds) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($params, ...$binds);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAllHives($conn) {
    $sql = "SELECT * FROM hives WHERE status = 'active' ORDER BY id ASC";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getLatestSensorData($conn, $hive_id) {
    $sql = "SELECT * FROM sensor_data WHERE hive_id = ? ORDER BY timestamp DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $hive_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getLatestAudioAnalysis($conn, $hive_id) {
    $sql = "SELECT * FROM audio_analysis WHERE hive_id = ? ORDER BY timestamp DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param("i", $hive_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getActiveAlerts($conn) {
    $sql = "SELECT a.*, h.name as hive_name, h.hive_id as hive_code 
            FROM alerts a 
            JOIN hives h ON a.hive_id = h.id 
            WHERE a.is_read = 0 
            ORDER BY a.created_at DESC";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getAlertsByFilter($conn, $filter = 'all') {
    $valid = ['critical', 'warning', 'info'];

    // Always restrict to active (unread) alerts so dismissed ones stay gone
    if (in_array($filter, $valid)) {
        $stmt = $conn->prepare(
            "SELECT a.*, h.name AS hive_name, h.hive_id AS hive_code
             FROM alerts a
             JOIN hives h ON a.hive_id = h.id
             WHERE a.is_read = 0 AND a.severity = ?
             ORDER BY a.created_at DESC LIMIT 50"
        );
        $stmt->bind_param('s', $filter);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $result = $conn->query(
        "SELECT a.*, h.name AS hive_name, h.hive_id AS hive_code
         FROM alerts a
         JOIN hives h ON a.hive_id = h.id
         WHERE a.is_read = 0
         ORDER BY a.created_at DESC LIMIT 50"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getAlertHistory($conn, $limit = 50) {
    $stmt = $conn->prepare(
        "SELECT a.*, h.name AS hive_name, h.hive_id AS hive_code
         FROM alerts a
         JOIN hives h ON a.hive_id = h.id
         WHERE a.is_read = 1
         ORDER BY a.created_at DESC LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getActivityLog($conn, $limit = 50) {
    $sql = "SELECT * FROM activity_log ORDER BY timestamp DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function logActivity($conn, $hive_id, $event_type, $details, $severity = 'info', $user_id = null) {
    try {
        $uid = $user_id ?? ($_SESSION['user_id'] ?? null);
        $stmt = $conn->prepare(
            "INSERT INTO activity_log (hive_id, user_id, event_type, details, severity)
             VALUES (?, ?, ?, ?, ?)"
        );
        if ($stmt) {
            $stmt->bind_param("iisss", $hive_id, $uid, $event_type, $details, $severity);
            $stmt->execute();
        }
    } catch (Throwable $e) {}
}

function getEdgeDevices($conn) {
    $sql = "SELECT d.*, h.name as hive_name 
            FROM edge_devices d 
            LEFT JOIN hives h ON d.hive_id = h.id 
            ORDER BY d.status DESC, d.id ASC";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function countHealthyHives($conn) {
    $hives   = getAllHives($conn);
    $healthy = 0;
    foreach ($hives as $hive) {
        $sensor = getLatestSensorData($conn, $hive['id']);
        $audio  = getLatestAudioAnalysis($conn, $hive['id']);
        if (!$sensor) continue;

        // Same scoring as getHiveHealthScore — healthy = score >= 80
        $s = 100;
        if ($sensor['temperature'] > 37 || $sensor['temperature'] < 32) $s -= 5;
        if ($sensor['co2'] > 3000)    $s -= 5;
        if ($sensor['humidity'] > 75) $s -= 3;
        if ($audio && $audio['colony_status'] !== 'normal') $s -= 8;
        if (max(0, $s) >= 80) $healthy++;
    }
    return $healthy;
}

function getAverageTemperature($conn) {
    // Average the LATEST reading from each hive (not all historical readings)
    // 1 hive  → returns that hive's current temperature
    // N hives → returns the average of each hive's latest temperature
    $sql = "SELECT AVG(temperature) AS avg_temp
            FROM sensor_data
            WHERE id IN (
                SELECT MAX(id) FROM sensor_data GROUP BY hive_id
            )";
    $result = $conn->query($sql);
    if (!$result) return null;
    $row = $result->fetch_assoc();
    return $row['avg_temp'] !== null ? round((float)$row['avg_temp'], 1) : null;
}

function time_elapsed_string($datetime) {
    if (!$datetime) return 'never';
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

function getHiveStatusBadge($status) {
    $badges = [
        'normal'      => ['text' => 'Normal',       'bg' => '#D1FAE5', 'color' => '#065F46', 'icon' => 'fas fa-check-circle'],
        'queenless'   => ['text' => 'Queenless',    'bg' => '#FEE2E2', 'color' => '#991B1B', 'icon' => 'fas fa-crown'],
        'swarming'    => ['text' => 'Swarming',     'bg' => '#FFEDD5', 'color' => '#9A3412', 'icon' => 'fas fa-arrows-alt'],
    ];
    return $badges[$status] ?? $badges['normal'];
}

function getHealthDistribution($conn) {
    $sql = "SELECT colony_status, COUNT(*) as count
            FROM audio_analysis a
            JOIN (
                SELECT hive_id, MAX(timestamp) as max_time
                FROM audio_analysis
                GROUP BY hive_id
            ) latest ON a.hive_id = latest.hive_id AND a.timestamp = latest.max_time
            GROUP BY colony_status";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getHiveHealthScore($conn) {
    $hives  = getAllHives($conn);
    $total  = count($hives);
    if ($total === 0) return ['score' => 0, 'detail' => 'No hives'];

    $hiveScores = [];
    $issues     = 0;

    foreach ($hives as $hive) {
        $sensor = getLatestSensorData($conn, $hive['id']);
        $audio  = getLatestAudioAnalysis($conn, $hive['id']);
        if (!$sensor) continue; // skip hives with no data

        $s = 100;
        if ($sensor['temperature'] > 37 || $sensor['temperature'] < 32) { $s -= 5; $issues++; }
        if ($sensor['co2'] > 3000)    { $s -= 5; $issues++; }
        if ($sensor['humidity'] > 75) { $s -= 3; $issues++; }
        if ($audio && $audio['colony_status'] !== 'normal') { $s -= 8; $issues++; }
        $hiveScores[] = max(0, $s);
    }

    if (empty($hiveScores)) return ['score' => 0, 'detail' => 'No data yet'];

    // Average of per-hive scores (1 hive = that hive's score; N hives = average)
    $avg    = (int) round(array_sum($hiveScores) / count($hiveScores));
    $detail = $issues === 0 ? 'All hives healthy' : "{$issues} issue" . ($issues > 1 ? 's' : '') . " detected";
    return ['score' => $avg, 'detail' => $detail, 'issues' => $issues];
}

function getWeeklyTrends($conn) {
    // Fetch data for the current calendar week (Sunday–Saturday)
    $sql = "SELECT
                DATE(timestamp)            AS day_date,
                DAYNAME(timestamp)         AS day_name,
                DAYOFWEEK(timestamp)       AS day_num,
                ROUND(AVG(temperature), 1) AS avg_temp,
                ROUND(AVG(humidity), 1)    AS avg_humidity,
                ROUND(AVG(co2))            AS avg_co2
            FROM sensor_data
            WHERE DATE(timestamp) BETWEEN
                DATE_SUB(CURDATE(), INTERVAL DAYOFWEEK(CURDATE())-1 DAY)
                AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAYOFWEEK(CURDATE())-1 DAY), INTERVAL 6 DAY)
            GROUP BY DATE(timestamp), DAYNAME(timestamp), DAYOFWEEK(timestamp)
            ORDER BY DATE(timestamp) ASC";
    $result = $conn->query($sql);
    if (!$result) return [];
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getAvgCO2($conn) {
    $sql = "SELECT ROUND(AVG(co2)) AS avg_co2
            FROM sensor_data
            WHERE id IN (
                SELECT MAX(id) FROM sensor_data GROUP BY hive_id
            )";
    $result = $conn->query($sql);
    if (!$result) return null;
    $row = $result->fetch_assoc();
    return $row['avg_co2'] !== null ? (int)$row['avg_co2'] : null;
}

function getAvgSoundLevel($conn) {
    $sql = "SELECT ROUND(AVG(sound_level), 1) as avg_sound FROM sensor_data
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    $result = $conn->query($sql);
    if (!$result) return 0;
    $row = $result->fetch_assoc();
    return (float)($row['avg_sound'] ?? 0);
}

function getTotalReadings($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM sensor_data");
    if (!$result) return 0;
    return (int)$result->fetch_assoc()['total'];
}
?>
