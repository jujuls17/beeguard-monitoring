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

$weekOffset = max(0, min(52, (int)($_GET['week_offset'] ?? 0)));

// Find Sunday of the target week
$todayDow     = (int)date('w'); // 0=Sun
$thisSunday   = mktime(0,0,0, date('n'), date('j') - $todayDow);
$targetSunday = $thisSunday - ($weekOffset * 7 * 86400);

$dayAbbr  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
$weekDays  = [];
$weekDates = [];
for ($i = 0; $i <= 6; $i++) {
    $ts          = $targetSunday + ($i * 86400);
    $weekDays[]  = $dayAbbr[$i];
    $weekDates[] = date('Y-m-d', $ts);
}

$startDate = $weekDates[0];
$endDate   = $weekDates[6];

$stmt = $conn->prepare(
    "SELECT DATE(timestamp) AS day_date,
            ROUND(AVG(temperature),1) AS avg_temp,
            ROUND(AVG(humidity),1)    AS avg_humidity,
            ROUND(AVG(co2),0)         AS avg_co2
     FROM sensor_data
     WHERE DATE(timestamp) BETWEEN ? AND ?
       AND (temperature IS NOT NULL OR humidity IS NOT NULL OR co2 IS NOT NULL)
     GROUP BY day_date"
);
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$trendByDate = [];
while ($row = $res->fetch_assoc()) {
    $trendByDate[$row['day_date']] = $row;
}

$temps  = [];
$humids = [];
$co2s   = [];
foreach ($weekDates as $date) {
    $temps[]  = isset($trendByDate[$date]) ? (float)$trendByDate[$date]['avg_temp']      : null;
    $humids[] = isset($trendByDate[$date]) ? (float)$trendByDate[$date]['avg_humidity']   : null;
    $co2s[]   = isset($trendByDate[$date]) ? (int)$trendByDate[$date]['avg_co2']          : null;
}

if ($weekOffset === 0)      $weekLabel = 'This Week';
elseif ($weekOffset === 1)  $weekLabel = 'Last Week';
else                        $weekLabel = "{$weekOffset} Weeks Ago";

$dateRange = date('M j', strtotime($startDate)) . ' – ' . date('M j', strtotime($endDate));

echo json_encode([
    'success'     => true,
    'week_offset' => $weekOffset,
    'week_label'  => $weekLabel,
    'date_range'  => $dateRange,
    'days'        => $weekDays,
    'temp'        => $temps,
    'humidity'    => $humids,
    'co2'         => $co2s,
]);
