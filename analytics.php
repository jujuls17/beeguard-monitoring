<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

function getTempRange(?float $t): string {
    if ($t === null) return 'no-data';
    if ($t < 18 || $t > 37) return 'critical';
    if ($t < 32 || $t > 36) return 'warning';
    return 'normal';
}
function getTempRangeBadge(?float $t): string {
    $range = getTempRange($t);
    $map = [
        'normal'  => ['Normal',   'rgba(255,255,255,0.25)', '#fff', 'rgba(255,255,255,0.4)', 'fa-check-circle'],
        'warning' => ['Warning',  'rgba(254,215,0,0.35)',   '#fff', 'rgba(254,215,0,0.5)',   'fa-exclamation-triangle'],
        'critical'=> ['Critical', 'rgba(239,68,68,0.35)',   '#fff', 'rgba(239,68,68,0.5)',   'fa-times-circle'],
        'no-data' => ['No data',  'rgba(255,255,255,0.15)', 'rgba(255,255,255,0.7)', 'rgba(255,255,255,0.2)', 'fa-minus-circle'],
    ];
    [$label, $bg, $color, $border, $icon] = $map[$range];
    return "<span style=\"display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;background:$bg;color:$color;border:1px solid $border\">
                <i class=\"fas $icon\" style=\"font-size:10px\"></i> $label
            </span>";
}

// ── Live data from DB ──────────────────────────────────────────
$avgTemp      = getAverageTemperature($conn);
$avgCO2       = getAvgCO2($conn);
$avgSound     = getAvgSoundLevel($conn);
$healthScore  = getHiveHealthScore($conn);
$distribution = getHealthDistribution($conn);
$weeklyTrends = getWeeklyTrends($conn);
$totalReadings = getTotalReadings($conn);
$totalHives   = count(getAllHives($conn));
$healthyCount = countHealthyHives($conn);

// ── Build chart data ───────────────────────────────────────────
// Health Distribution pie
$statusLabels = [
    'normal'    => 'Normal',
    'swarming'  => 'Swarming',
    'queenless' => 'Queenless',
];
$statusColors = [
    'normal'    => '#10B981',
    'swarming'  => '#F97316',
    'queenless' => '#EF4444',
];

$totalDist  = array_sum(array_column($distribution, 'count'));
$pieLabels  = [];
$pieData    = [];
$pieColors  = [];
foreach ($distribution as $row) {
    $s = $row['colony_status'];
    $pct = $totalDist > 0 ? round($row['count'] / $totalDist * 100) : 0;
    $pieLabels[]  = ($statusLabels[$s] ?? ucfirst($s)) . ": {$pct}%";
    $pieData[]    = $row['count'];
    $pieColors[]  = $statusColors[$s] ?? '#6B7280';
}

// Weekly bar chart — always show Sun→Mon→Tue→Wed→Thu→Fri→Sat (current calendar week)
$dayAbbr = ['Sunday'=>'Sun','Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat'];

// Index DB results by date string for quick lookup
$trendByDate = [];
foreach ($weeklyTrends as $row) {
    $trendByDate[$row['day_date']] = $row;
}

// Find this week's Sunday (PHP date('w'): 0=Sun … 6=Sat)
$todayDow  = (int)date('w');
$weekDays  = [];
$weekTemps = [];
$weekHumids = [];
$weekCO2   = [];
for ($i = 0; $i <= 6; $i++) {
    // i=0 → Sunday, i=1 → Monday, … i=6 → Saturday
    $daysOffset = $i - $todayDow;
    $date       = date('Y-m-d', strtotime("{$daysOffset} days"));
    $dayName    = date('l',     strtotime("{$daysOffset} days"));
    $weekDays[]   = $dayAbbr[$dayName] ?? substr($dayName, 0, 3);
    $weekTemps[]  = isset($trendByDate[$date]) ? (float)$trendByDate[$date]['avg_temp']     : 0;
    $weekHumids[] = isset($trendByDate[$date]) ? (float)$trendByDate[$date]['avg_humidity']  : 0;
    $weekCO2[]    = isset($trendByDate[$date]) ? (int)$trendByDate[$date]['avg_co2']         : 0;
}

// ── Sound activity — last 24 h, hourly averages ───────────────
$soundRows = [];
$sqRes = $conn->query(
    "SELECT DATE_FORMAT(timestamp, '%H:00') AS hour_label,
            HOUR(timestamp)                  AS hour_num,
            ROUND(AVG(sound_level), 1)       AS avg_sound
     FROM sensor_data
     WHERE timestamp >= NOW() - INTERVAL 24 HOUR
     GROUP BY HOUR(timestamp)
     ORDER BY hour_num ASC"
);
if ($sqRes) {
    while ($r = $sqRes->fetch_assoc()) $soundRows[] = $r;
}
$soundLabels = array_column($soundRows, 'hour_label');
$soundValues = array_map('floatval', array_column($soundRows, 'avg_sound'));

// Temperature status text
$tempStatus = $avgTemp === null
    ? 'No data yet'
    : (($avgTemp >= 33 && $avgTemp <= 36) ? '✓ Optimal range (33–36°C)' : '⚠ Outside range (33–36°C)');

$scoreColor = $healthScore['score'] >= 80 ? 'text-green-600' : ($healthScore['score'] >= 60 ? 'text-yellow-600' : 'text-red-600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-6 py-4 pl-8">
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center"
                      style="background:linear-gradient(135deg,#8B5CF6,#7C3AED);">
                    <i class="fas fa-chart-line text-white text-sm"></i>
                </span>
                Analytics & Insights
            </h1>
            <p class="text-gray-500 text-xs mt-0.5">Live data from all connected hives</p>
        </div>

        <div class="p-4 sm:p-6">

            <!-- Top stat cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-0" id="statCardsGrid">

                <!-- Avg Temperature -->
                <div class="stat-card rounded-xl p-4 text-white cursor-pointer select-none transition-all hover:brightness-105"
                     style="background:linear-gradient(135deg,#F97316 0%,#EA580C 100%);box-shadow:0 2px 12px rgba(249,115,22,0.25);"
                     onclick="toggleCard('temp')">
                    <div class="flex items-start justify-between mb-1.5">
                        <p class="text-xs font-medium opacity-80">Avg Temperature</p>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-chevron-down text-white/60 text-xs transition-transform duration-300" id="chevron-temp"></i>
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-thermometer-half text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold mb-1" id="statAvgTemp"><?php echo $avgTemp !== null ? $avgTemp . '°C' : '—'; ?></h2>
                    <div id="statTempBadge"><?php echo getTempRangeBadge($avgTemp); ?></div>
                </div>

                <!-- Health Score -->
                <div class="stat-card rounded-xl p-4 text-white cursor-pointer select-none transition-all hover:brightness-105"
                     style="background:linear-gradient(135deg,#10B981 0%,#059669 100%);box-shadow:0 2px 12px rgba(16,185,129,0.25);"
                     onclick="toggleCard('health')">
                    <div class="flex items-start justify-between mb-1.5">
                        <p class="text-xs font-medium opacity-80">Health Score</p>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-chevron-down text-white/60 text-xs transition-transform duration-300" id="chevron-health"></i>
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-heartbeat text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold mb-1" id="statHealthScore"><?php echo $healthScore['score']; ?>/100</h2>
                    <span class="text-xs opacity-80" id="statHealthDetail"><?php echo $healthScore['detail']; ?></span>
                </div>

                <!-- Avg CO₂ -->
                <div class="stat-card rounded-xl p-4 text-white cursor-pointer select-none transition-all hover:brightness-105"
                     style="background:linear-gradient(135deg,#8B5CF6 0%,#7C3AED 100%);box-shadow:0 2px 12px rgba(139,92,246,0.25);"
                     onclick="toggleCard('co2')">
                    <div class="flex items-start justify-between mb-1.5">
                        <p class="text-xs font-medium opacity-80">Avg CO₂ Level</p>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-chevron-down text-white/60 text-xs transition-transform duration-300" id="chevron-co2"></i>
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-wind text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold mb-1" id="statAvgCO2"><?php echo $avgCO2 ?: '—'; ?><?php echo $avgCO2 ? ' ppm' : ''; ?></h2>
                    <span class="text-xs opacity-80" id="statCO2Status">
                        <?php if ($avgCO2 > 2000): ?>⚠ Elevated
                        <?php elseif ($avgCO2 > 0): ?>✓ Normal
                        <?php else: ?>No data yet<?php endif; ?>
                    </span>
                </div>

                <!-- Healthy Hives -->
                <div class="stat-card rounded-xl p-4 text-white cursor-pointer select-none transition-all hover:brightness-105"
                     style="background:linear-gradient(135deg,#F59E0B 0%,#D97706 100%);box-shadow:0 2px 12px rgba(245,158,11,0.25);"
                     onclick="toggleCard('hives')">
                    <div class="flex items-start justify-between mb-1.5">
                        <p class="text-xs font-medium opacity-80">Healthy Hives</p>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-chevron-down text-white/60 text-xs transition-transform duration-300" id="chevron-hives"></i>
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check-circle text-sm"></i>
                            </div>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold mb-1" id="statHealthy"><?php echo $healthyCount; ?>/<?php echo $totalHives; ?></h2>
                    <span class="text-xs opacity-80" id="statReadings"><?php echo $totalReadings; ?> total readings</span>
                </div>
            </div>

            <!-- Expandable detail panels (full width, below grid) -->
            <?php
            $cardDefs = [
                'temp'   => ['#F97316','#EA580C','rgba(249,115,22,0.18)','Avg Temperature — Per Hive Breakdown'],
                'health' => ['#10B981','#059669','rgba(16,185,129,0.18)','Health Score — Per Hive Breakdown'],
                'co2'    => ['#8B5CF6','#7C3AED','rgba(139,92,246,0.18)','Avg CO₂ Level — Per Hive Breakdown'],
                'hives'  => ['#F59E0B','#D97706','rgba(245,158,11,0.18)','Healthy Hives — Per Hive Breakdown'],
            ];
            foreach ($cardDefs as $key => [$c1, $c2, $bg, $title]): ?>
            <div id="detail-panel-<?php echo $key; ?>"
                 style="max-height:0;overflow:hidden;transition:max-height 0.4s cubic-bezier(0.4,0,0.2,1);margin-top:0;">
                <div class="rounded-2xl p-5 mt-4 mb-0"
                     style="background:<?php echo $bg; ?>;border:2px solid <?php echo $c1; ?>33;">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-sm" style="color:<?php echo $c1; ?>"><?php echo $title; ?></h3>
                        <button onclick="toggleCard('<?php echo $key; ?>')"
                                class="text-xs font-semibold px-3 py-1 rounded-lg text-white transition"
                                style="background:linear-gradient(135deg,<?php echo $c1; ?>,<?php echo $c2; ?>);">
                            ✕ Collapse
                        </button>
                    </div>
                    <div id="detail-content-<?php echo $key; ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div class="text-center py-4 text-gray-400 text-sm col-span-full">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading hive data…
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="mb-8"></div>

            <!-- Charts row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                <!-- Health Distribution -->
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <h3 class="font-bold mb-1">Hive Health Distribution</h3>
                    <p class="text-xs text-gray-500 mb-4">Based on latest audio analysis per hive</p>
                    <?php if (empty($pieData)): ?>
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                            <i class="fas fa-satellite-dish text-4xl mb-3"></i>
                            <p class="text-sm">Waiting for ESP32 data...</p>
                            <p class="text-xs mt-1">Connect your ESP32 to see live distribution</p>
                        </div>
                    <?php else: ?>
                        <div class="flex justify-center">
                            <div style="width:280px; height:280px;">
                                <canvas id="healthChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Weekly Trends -->
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <h3 class="font-bold mb-1">Weekly Environmental Trends</h3>
                    <p class="text-xs text-gray-500 mb-4">7-day average temperature & humidity</p>
                    <?php if (empty($weeklyTrends)): ?>
                        <div class="flex flex-col items-center justify-center h-48 text-gray-400">
                            <i class="fas fa-satellite-dish text-4xl mb-3"></i>
                            <p class="text-sm">Waiting for ESP32 data...</p>
                            <p class="text-xs mt-1">Data will appear after first sensor reading</p>
                        </div>
                    <?php else: ?>
                        <div style="height:250px;">
                            <canvas id="weeklyChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Sensors Line Graph -->
            <div class="bg-white rounded-xl p-5 shadow-sm mb-5" id="sensorChartCard">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="font-bold flex items-center gap-2">
                            <i class="fas fa-chart-line text-blue-500"></i>
                            All Sensors — Trend
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5" id="sensorChartSubtitle">Temperature, Humidity, CO₂ &amp; Sound over time</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <select id="sensorChartHive"
                            class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 cursor-pointer">
                            <option value="all">All Hives</option>
                            <?php foreach (getAllHives($conn) as $h): ?>
                            <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="sensorChartRange"
                            class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-300 cursor-pointer">
                            <option value="1h" selected>1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                        </select>
                        <button id="sensorChartRefreshBtn" onclick="loadSensorChart()"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-bold rounded-lg transition">
                            <i class="fas fa-sync-alt text-xs" id="sensorChartRefreshIcon"></i> Refresh
                        </button>
                    </div>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap gap-4 mb-3 text-xs text-gray-500" id="sensorChartLegend">
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#F97316;margin-right:4px;vertical-align:middle;"></span>Temp (°C)</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#3B82F6;margin-right:4px;vertical-align:middle;"></span>Humidity (%)</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#8B5CF6;margin-right:4px;vertical-align:middle;"></span>CO₂ (ppm ÷ 100)</span>
                    <span><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#F59E0B;margin-right:4px;vertical-align:middle;"></span>Sound (%)</span>
                </div>

                <div id="sensorChartWrap" style="height:260px; position:relative;">
                    <canvas id="sensorChart"></canvas>
                    <div id="sensorChartEmpty" class="flex flex-col items-center justify-center h-full text-gray-400" style="display:none!important;">
                        <i class="fas fa-chart-area text-4xl mb-3"></i>
                        <p class="text-sm">No sensor data for this range</p>
                    </div>
                </div>
            </div>

            <!-- Sound Activity Line Graph -->
            <div class="bg-white rounded-xl p-5 shadow-sm mb-5">
                <div class="flex justify-between items-center mb-1">
                    <div>
                        <h3 class="font-bold flex items-center gap-2">
                            <i class="fas fa-wave-square text-purple-500"></i>
                            Sound Activity — Last 24 Hours
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Hourly average sound level from MAX4466 microphone sensor (%)</p>
                    </div>
                    <span class="text-xs text-gray-400">Auto-refreshes with page</span>
                </div>

                <?php if (empty($soundRows)): ?>
                    <div class="flex flex-col items-center justify-center h-48 text-gray-400 mt-4">
                        <i class="fas fa-microphone-slash text-4xl mb-3"></i>
                        <p class="text-sm">No sound data in the last 24 hours</p>
                        <p class="text-xs mt-1">Sound readings will appear once ESP32 sends data</p>
                    </div>
                <?php else: ?>
                    <div style="height:220px; margin-top:16px;">
                        <canvas id="soundChart"></canvas>
                    </div>
                    <div class="flex items-center gap-6 mt-3 text-xs text-gray-500">
                        <span><span class="inline-block w-3 h-3 rounded-full bg-purple-500 mr-1"></span>Sound Level (%)</span>
                        <span>Peak: <strong class="text-purple-700"><?php echo max($soundValues); ?>%</strong></span>
                        <span>Avg: <strong class="text-purple-700"><?php echo round(array_sum($soundValues)/count($soundValues),1); ?>%</strong></span>
                        <span>Min: <strong class="text-purple-700"><?php echo min($soundValues); ?>%</strong></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Per-hive live readings table -->
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-center mb-1">
                    <h3 class="font-bold">Live Hive Readings</h3>
                    <div class="flex items-center gap-3">
                        <span id="lastUpdated" class="text-xs text-gray-400"></span>
                        <button id="refreshBtn" onclick="refreshReadings()"
                            class="flex items-center gap-2 px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-bold rounded-lg transition">
                            <i class="fas fa-sync-alt" id="refreshIcon"></i> Refresh
                        </button>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mb-4">Most recent sensor data per hive from ESP32 — auto-refreshes every 15s</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-gray-100">
                                <th class="text-left py-3 px-4 text-gray-600 font-semibold">Hive</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">Temperature</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">Humidity</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">CO₂</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">Sound</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">Status</th>
                                <th class="text-center py-3 px-4 text-gray-600 font-semibold">Last Update</th>
                            </tr>
                        </thead>
                        <tbody id="liveReadingsBody">
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400 text-sm">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>


        </div>
    </main>

    <script>
    function getTempRange(t) {
        if (t === null || t === undefined) return 'no-data';
        if (t < 18 || t > 37) return 'critical';
        if (t < 32 || t > 36) return 'warning';
        return 'normal';
    }
    function tempRangeBadge(t) {
        const map = {
            'normal'  : ['Normal',   'rgba(255,255,255,0.25)', '#fff', 'rgba(255,255,255,0.4)', 'fa-check-circle'],
            'warning' : ['Warning',  'rgba(254,215,0,0.35)',   '#fff', 'rgba(254,215,0,0.5)',   'fa-exclamation-triangle'],
            'critical': ['Critical', 'rgba(239,68,68,0.35)',   '#fff', 'rgba(239,68,68,0.5)',   'fa-times-circle'],
            'no-data' : ['No data',  'rgba(255,255,255,0.15)', 'rgba(255,255,255,0.7)', 'rgba(255,255,255,0.2)', 'fa-minus-circle'],
        };
        const r = map[getTempRange(t)];
        return `<span style="display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;background:${r[1]};color:${r[2]};border:1px solid ${r[3]}">
                    <i class="fas ${r[4]}" style="font-size:10px"></i> ${r[0]}
                </span>`;
    }

        <?php if (!empty($pieData)): ?>
        new Chart(document.getElementById('healthChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($pieLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($pieData); ?>,
                    backgroundColor: <?php echo json_encode($pieColors); ?>
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 }, boxWidth: 12, padding: 10 }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if (!empty($weeklyTrends)): ?>
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($weekDays); ?>,
                datasets: [
                    {
                        label: 'Avg Temperature (°C)',
                        data: <?php echo json_encode($weekTemps); ?>,
                        backgroundColor: '#F59E0B',
                        yAxisID: 'yTemp',
                        borderRadius: 4,
                    },
                    {
                        label: 'Avg Humidity (%)',
                        data: <?php echo json_encode($weekHumids); ?>,
                        backgroundColor: '#60A5FA',
                        yAxisID: 'yHumid',
                        borderRadius: 4,
                    },
                    {
                        label: 'Avg CO₂ (ppm)',
                        data: <?php echo json_encode($weekCO2); ?>,
                        backgroundColor: '#A78BFA',
                        yAxisID: 'yCO2',
                        borderRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    yTemp: {
                        type: 'linear',
                        position: 'left',
                        min: 25, max: 42,
                        title: { display: true, text: 'Temp (°C)', color: '#F59E0B' },
                        ticks: { color: '#F59E0B' },
                        grid: { color: 'rgba(245,158,11,0.1)' }
                    },
                    yHumid: {
                        type: 'linear',
                        position: 'right',
                        min: 0, max: 100,
                        title: { display: true, text: 'Humidity (%)', color: '#60A5FA' },
                        ticks: { color: '#60A5FA' },
                        grid: { drawOnChartArea: false }
                    },
                    yCO2: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        title: { display: true, text: 'CO₂ (ppm)', color: '#A78BFA' },
                        ticks: { color: '#A78BFA' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
        <?php endif; ?>
        <?php if (!empty($soundRows)): ?>
        new Chart(document.getElementById('soundChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($soundLabels); ?>,
                datasets: [{
                    label: 'Avg Sound Level (%)',
                    data: <?php echo json_encode($soundValues); ?>,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139,92,246,0.12)',
                    pointBackgroundColor: '#8B5CF6',
                    pointBorderColor: '#fff',
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' Sound: ' + ctx.parsed.y + '%'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 }, color: '#6b7280' }
                    },
                    y: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Sound Level (%)', color: '#8B5CF6', font: { size: 11 } },
                        ticks: { color: '#8B5CF6', font: { size: 11 }, callback: v => v + '%' },
                        grid: { color: 'rgba(139,92,246,0.08)' }
                    }
                }
            }
        });
        <?php endif; ?>

        // ── All-Sensors Chart ──────────────────────────────────────
        let sensorChartInstance = null;

        function loadSensorChart() {
            const range  = document.getElementById('sensorChartRange').value;
            const hiveId = document.getElementById('sensorChartHive').value;
            const icon   = document.getElementById('sensorChartRefreshIcon');
            icon.classList.add('fa-spin');

            fetch(`api/sensor_chart_data.php?range=${range}&hive_id=${hiveId}`)
                .then(r => r.json())
                .then(d => {
                    icon.classList.remove('fa-spin');
                    if (!d.success || !d.labels.length) {
                        document.getElementById('sensorChartWrap').style.opacity = '0.4';
                        return;
                    }
                    document.getElementById('sensorChartWrap').style.opacity = '1';

                    const isAllHives = hiveId === 'all';
                    const pre = isAllHives ? 'Avg ' : '';
                    const sub = document.getElementById('sensorChartSubtitle');
                    if (sub) sub.textContent = isAllHives
                        ? 'Average across all hives — Temperature, Humidity, CO₂ & Sound'
                        : 'Temperature, Humidity, CO₂ & Sound over time';

                    const mkDs = (label, data, color) => ({
                        label, data,
                        borderColor: color,
                        backgroundColor: 'transparent',
                        pointRadius: 0,
                        pointHoverRadius: 4,
                        pointHoverBackgroundColor: color,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        borderWidth: 1.5,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'yLeft',
                        spanGaps: true,
                    });

                    const datasets = [
                        mkDs(`${pre}Temp (°C)`,    d.temp,    '#F97316'),
                        mkDs(`${pre}Humidity (%)`, d.humidity,'#3B82F6'),
                        mkDs(`${pre}CO₂ ÷100`,    d.co2 ? d.co2.map(v => v !== null ? +(v/100).toFixed(1) : null) : [], '#8B5CF6'),
                        mkDs(`${pre}Sound (%)`,    d.sound,   '#F59E0B'),
                    ];

                    if (sensorChartInstance) {
                        sensorChartInstance.data.labels   = d.labels;
                        sensorChartInstance.data.datasets = datasets;
                        sensorChartInstance.update('none');
                    } else {
                        sensorChartInstance = new Chart(document.getElementById('sensorChart'), {
                            type: 'line',
                            data: { labels: d.labels, datasets },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: { mode: 'index', intersect: false },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: 'rgba(17,24,39,0.85)',
                                        titleColor: '#f9fafb',
                                        bodyColor: '#d1d5db',
                                        padding: 10,
                                        cornerRadius: 8,
                                        displayColors: true,
                                        boxWidth: 8, boxHeight: 8,
                                        callbacks: {
                                            label: ctx => {
                                                const ds = ctx.dataset.label;
                                                const v  = ctx.parsed.y;
                                                if (v === null) return null;
                                                if (ds === 'CO₂ ÷100') return `  CO₂: ${(v*100).toFixed(0)} ppm`;
                                                if (ds === 'Temp (°C)')    return `  Temp: ${v}°C`;
                                                if (ds === 'Humidity (%)') return `  Humidity: ${v}%`;
                                                if (ds === 'Sound (%)')    return `  Sound: ${v}%`;
                                                return `  ${ds}: ${v}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        border: { display: false },
                                        grid: { color: 'rgba(0,0,0,0.04)', drawTicks: false },
                                        ticks: { font: { size: 10 }, color: '#9ca3af', maxTicksLimit: 8, padding: 6 }
                                    },
                                    yLeft: {
                                        type: 'linear',
                                        position: 'left',
                                        border: { display: false },
                                        grid: { color: 'rgba(0,0,0,0.04)', drawTicks: false },
                                        ticks: { font: { size: 10 }, color: '#9ca3af', maxTicksLimit: 6, padding: 6 }
                                    }
                                }
                            }
                        });
                    }
                })
                .catch(() => icon.classList.remove('fa-spin'));
        }

        // Auto-reload when dropdowns change
        document.getElementById('sensorChartRange').addEventListener('change', loadSensorChart);
        document.getElementById('sensorChartHive').addEventListener('change', loadSensorChart);

        // Initial load
        loadSensorChart();

        function refreshReadings() {
            const btn  = document.getElementById('refreshBtn');
            const icon = document.getElementById('refreshIcon');
            btn.disabled = true;
            icon.classList.add('fa-spin');

            fetch('api/live_readings.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    const tbody = document.getElementById('liveReadingsBody');

                    if (!data.hives.length) {
                        tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-gray-400 text-sm">
                            <i class="fas fa-satellite-dish mr-2"></i>No hives registered yet.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = data.hives.map(h => {
                        if (!h.sensor) {
                            return `<tr class="border-b border-gray-50">
                                <td class="py-3 px-4">
                                    <p class="font-semibold">${h.name}</p>
                                    <p class="text-xs text-gray-400">${h.hive_id}</p>
                                </td>
                                <td colspan="6" class="py-3 px-4 text-center text-gray-400 text-xs">
                                    <i class="fas fa-satellite-dish mr-1"></i>No data — ESP32 not yet connected
                                </td>
                            </tr>`;
                        }
                        const s = h.sensor;
                        const tempColor  = (s.temperature > 36 || s.temperature < 33) ? 'text-red-600' : 'text-green-600';
                        const humidColor = s.humidity > 70 ? 'text-yellow-600' : 'text-blue-600';
                        const co2Color   = s.co2 > 2000 ? 'text-red-600' : 'text-purple-600';
                        const soundPct   = Math.round(s.sound_level);
                        const badge      = h.badge
                            ? `<span class="${h.badge.bg} ${h.badge.text_color} px-2 py-1 rounded text-xs font-bold">${h.badge.text}</span>`
                            : `<span class="text-gray-400 text-xs">—</span>`;
                        return `<tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                            <td class="py-3 px-4">
                                <p class="font-semibold">${h.name}</p>
                                <p class="text-xs text-gray-400">${h.hive_id}</p>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="font-bold ${tempColor}">${s.temperature.toFixed(1)}°C</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="font-bold ${humidColor}">${s.humidity.toFixed(1)}%</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="font-bold ${co2Color}">${s.co2.toLocaleString()} ppm</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-yellow-500" style="width:${soundPct}%"></div>
                                    </div>
                                    <span class="text-xs font-bold">${soundPct}%</span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center">${badge}</td>
                            <td class="py-3 px-4 text-center text-xs text-gray-500">${s.elapsed}</td>
                        </tr>`;
                    }).join('');

                    document.getElementById('lastUpdated').textContent = 'Updated ' + data.updated_at;
                })
                .catch(() => {
                    document.getElementById('liveReadingsBody').innerHTML =
                        `<tr><td colspan="7" class="py-6 text-center text-red-400 text-sm">
                            <i class="fas fa-exclamation-circle mr-1"></i>Failed to load data. Check connection.
                        </td></tr>`;
                })
                .finally(() => {
                    icon.classList.remove('fa-spin');
                    btn.disabled = false;
                });
        }

        // Initial load + auto-refresh every 30 seconds
        refreshReadings();
        setInterval(refreshReadings, 15000);

        // ── Auto-refresh top stat cards every 60 s ──────────────────
        function refreshStats() {
            fetch('api/analytics_stats.php')
                .then(r => r.json())
                .then(d => {
                    if (!d.success) return;
                    document.getElementById('statAvgTemp').textContent  = d.avg_temp ? d.avg_temp + '°C' : '—';
                    document.getElementById('statTempBadge').innerHTML  = tempRangeBadge(d.avg_temp);
                    document.getElementById('statHealthScore').textContent = d.health.score + '/100';
                    document.getElementById('statHealthDetail').textContent = d.health.detail;
                    const co2El = document.getElementById('statAvgCO2');
                    co2El.textContent = d.avg_co2 ? d.avg_co2.toLocaleString() + ' ppm' : '—';
                    document.getElementById('statCO2Status').textContent = d.avg_co2 > 2000
                        ? "⚠ Elevated"
                        : (d.avg_co2 > 0 ? "✓ Normal" : 'No data yet');
                    document.getElementById('statHealthy').textContent = d.healthy + '/' + d.total_hives;
                    document.getElementById('statReadings').textContent = d.total_readings.toLocaleString() + ' total readings';
                })
                .catch(() => {});
        }
        setInterval(refreshStats, 60000);

        // ── Expandable stat cards ────────────────────────────────────
        let _activeCard   = null;
        let _cardLoaded   = {};

        const cardColors = {
            temp:   '#F97316',
            health: '#10B981',
            co2:    '#8B5CF6',
            hives:  '#F59E0B',
        };

        function toggleCard(type) {
            const panel   = document.getElementById(`detail-panel-${type}`);
            const chevron = document.getElementById(`chevron-${type}`);
            const isOpen  = _activeCard === type;

            // Collapse any open panel first
            if (_activeCard) {
                const prev = document.getElementById(`detail-panel-${_activeCard}`);
                const prevChev = document.getElementById(`chevron-${_activeCard}`);
                prev.style.maxHeight = '0';
                prevChev.style.transform = 'rotate(0deg)';
            }

            if (isOpen) {
                _activeCard = null;
                return;
            }

            // Open this panel
            _activeCard = type;
            panel.style.maxHeight = '800px';
            chevron.style.transform = 'rotate(180deg)';

            // Load data if not yet loaded
            if (!_cardLoaded[type]) {
                _cardLoaded[type] = true;
                loadCardDetails(type);
            }
        }

        function loadCardDetails(type) {
            fetch('api/live_readings.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success || !data.hives.length) return;
                    renderCardDetails(type, data.hives);
                })
                .catch(() => {});
        }

        function hiveHealthScore(h) {
            // Must match PHP getHiveHealthScore() deduction rules exactly
            if (!h.sensor) return null;
            const s = h.sensor;
            let score = 100;
            const t  = parseFloat(s.temperature);
            const hm = parseFloat(s.humidity);
            const c  = parseInt(s.co2);
            if (t > 37 || t < 32)  score -= 5;   // PHP: temperature out of range
            if (c > 3000)           score -= 5;   // PHP: co2 > 3000
            if (hm > 75)            score -= 3;   // PHP: humidity > 75
            if (h.badge && h.badge.text && h.badge.text !== 'Normal') score -= 8; // PHP: colony not normal
            return Math.max(0, score);
        }

        function renderCardDetails(type, hives) {
            const el = document.getElementById(`detail-content-${type}`);
            const color = cardColors[type];
            let html = '';

            if (type === 'temp') {
                const vals = hives.filter(h => h.sensor).map(h => parseFloat(h.sensor.temperature));
                const avg  = vals.length ? (vals.reduce((a,b)=>a+b,0)/vals.length).toFixed(1) : '—';
                html += `<div class="col-span-full mb-2 px-1 text-xs font-semibold" style="color:${color}">
                    Average: <span class="text-lg font-black">${avg}°C</span>
                    <span class="opacity-60 font-normal ml-1">calculated from ${vals.length} hive(s)</span>
                </div>`;
                hives.forEach(h => {
                    const t = h.sensor ? parseFloat(h.sensor.temperature) : null;
                    const ok = t !== null && t >= 33 && t <= 36;
                    html += `<div class="rounded-xl p-3 bg-white/70 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">${h.name}</p>
                            <p class="text-xs text-gray-400 font-mono">${h.hive_id}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-lg" style="color:${ok?'#16a34a':'#dc2626'}">${t !== null ? t.toFixed(1)+'°C' : '—'}</p>
                            <p class="text-xs" style="color:${ok?'#16a34a':'#dc2626'}">${t !== null ? (ok?'✓ Optimal':'⚠ Off range') : 'No data'}</p>
                        </div>
                    </div>`;
                });

            } else if (type === 'health') {
                const scores = hives.map(h => hiveHealthScore(h)).filter(s => s !== null);
                const avg = scores.length ? Math.round(scores.reduce((a,b)=>a+b,0)/scores.length) : null;
                // Sync top stat card to match per-hive average
                if (avg !== null) {
                    const el = document.getElementById('statHealthScore');
                    if (el) el.textContent = avg + '/100';
                }
                html += `<div class="col-span-full mb-2 px-1 text-xs font-semibold" style="color:${color}">
                    Overall Score: <span class="text-lg font-black">${avg !== null ? avg+'/100' : '—'}</span>
                    <span class="opacity-60 font-normal ml-1">${scores.length === 1 ? '(1 hive)' : 'average across '+scores.length+' hives'}</span>
                </div>`;
                hives.forEach((h, i) => {
                    const score = hiveHealthScore(h);
                    const status = h.badge ? h.badge.text : (h.sensor ? 'Normal' : 'No data');
                    const scoreColor = score === null ? '#9ca3af' : score >= 80 ? '#16a34a' : score >= 60 ? '#d97706' : '#dc2626';
                    html += `<div class="rounded-xl p-3 bg-white/70 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">${h.name}</p>
                            <p class="text-xs text-gray-400 font-mono">${h.hive_id}</p>
                            <p class="text-xs mt-0.5" style="color:${color}">${status}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-lg" style="color:${scoreColor}">${score !== null ? score+'/100' : '—'}</p>
                        </div>
                    </div>`;
                });

            } else if (type === 'co2') {
                const vals = hives.filter(h => h.sensor).map(h => parseInt(h.sensor.co2));
                const avg  = vals.length ? Math.round(vals.reduce((a,b)=>a+b,0)/vals.length) : '—';
                html += `<div class="col-span-full mb-2 px-1 text-xs font-semibold" style="color:${color}">
                    Average: <span class="text-lg font-black">${avg !== '—' ? avg.toLocaleString()+' ppm' : '—'}</span>
                    <span class="opacity-60 font-normal ml-1">from ${vals.length} hive(s)</span>
                </div>`;
                hives.forEach(h => {
                    const c = h.sensor ? parseInt(h.sensor.co2) : null;
                    const ok = c !== null && c <= 2000;
                    html += `<div class="rounded-xl p-3 bg-white/70 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">${h.name}</p>
                            <p class="text-xs text-gray-400 font-mono">${h.hive_id}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-lg" style="color:${ok?'#7c3aed':'#dc2626'}">${c !== null ? c.toLocaleString()+' ppm' : '—'}</p>
                            <p class="text-xs" style="color:${ok?'#7c3aed':'#dc2626'}">${c !== null ? (ok?'✓ Normal':'⚠ Elevated') : 'No data'}</p>
                        </div>
                    </div>`;
                });

            } else if (type === 'hives') {
                // Healthy = score >= 80 (matches PHP countHealthyHives)
                const withScores = hives.map(h => ({ ...h, score: hiveHealthScore(h) }));
                const healthy    = withScores.filter(h => h.score !== null && h.score >= 80).length;
                const total      = hives.length;
                // Sync top card
                const statEl = document.getElementById('statHealthy');
                if (statEl) statEl.textContent = healthy + '/' + total;
                html += `<div class="col-span-full mb-2 px-1 text-xs font-semibold" style="color:${color}">
                    Healthy: <span class="text-lg font-black">${healthy}/${total}</span>
                    <span class="opacity-60 font-normal ml-1">hives with score ≥ 80/100</span>
                </div>`;
                withScores.forEach(h => {
                    const noData     = h.score === null;
                    const isHealthy  = !noData && h.score >= 80;
                    const statusText = noData ? 'No data' : (isHealthy ? 'Healthy' : (h.badge ? h.badge.text : 'Needs attention'));
                    const statusColor = noData ? '#9ca3af' : (isHealthy ? '#16a34a' : '#dc2626');
                    html += `<div class="rounded-xl p-3 bg-white/70 flex items-center justify-between gap-2">
                        <div>
                            <p class="font-bold text-gray-800 text-sm">${h.name}</p>
                            <p class="text-xs text-gray-400 font-mono">${h.hive_id}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-black text-sm" style="color:${statusColor}">${isHealthy ? '✓' : noData ? '—' : '⚠'} ${statusText}</p>
                            <p class="text-xs text-gray-400">${h.score !== null ? 'Score: '+h.score+'/100' : ''}</p>
                        </div>
                    </div>`;
                });
            }

            if (!html) {
                html = `<p class="col-span-full text-center text-gray-400 text-sm py-4">No hive data available.</p>`;
            }
            el.innerHTML = html;
        }
    </script>
</body>
</html>
