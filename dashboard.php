<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'get_recommendations.php';
checkAuth();

function getTempRange(?float $t): string {
    if ($t === null) return 'no-data';
    if ($t < 18 || $t > 37)          return 'critical';
    if ($t < 32 || $t > 36)          return 'warning';
    return 'normal';
}
function getTempRangeBadge(?float $t): string {
    $range = getTempRange($t);
    $map = [
        'normal'  => ['Normal',   'background:rgba(255,255,255,0.25);color:#fff;border:1px solid rgba(255,255,255,0.4)', 'fa-check-circle'],
        'warning' => ['Warning',  'background:rgba(254,215,0,0.35);color:#fff;border:1px solid rgba(254,215,0,0.5)',    'fa-exclamation-triangle'],
        'critical'=> ['Critical', 'background:rgba(239,68,68,0.35);color:#fff;border:1px solid rgba(239,68,68,0.5)',   'fa-times-circle'],
        'no-data' => ['No data',  'background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.2)', 'fa-minus-circle'],
    ];
    [$label, $style, $icon] = $map[$range];
    return "<span style=\"display:inline-flex;align-items:center;gap:4px;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;$style\">
                <i class=\"fas $icon\" style=\"font-size:10px\"></i> $label
            </span>";
}
function getTempRangeBadgeSmall(?float $t): string {
    $range = getTempRange($t);
    $map = [
        'normal'  => ['Normal',   '#16a34a', '#dcfce7'],
        'warning' => ['Warning',  '#d97706', '#fef9c3'],
        'critical'=> ['Critical', '#dc2626', '#fee2e2'],
        'no-data' => ['—',        '#9ca3af', '#f3f4f6'],
    ];
    [$label, $color, $bg] = $map[$range];
    return "<span style=\"display:inline-block;margin-top:3px;padding:1px 7px;border-radius:999px;font-size:9px;font-weight:700;color:$color;background:$bg\">$label</span>";
}

$totalHives     = count(getAllHives($conn));
$healthyCount   = countHealthyHives($conn);
$healthyPercent = $totalHives > 0 ? round(($healthyCount / $totalHives) * 100) : 0;
$avgTemp        = getAverageTemperature($conn);
$alerts         = getActiveAlerts($conn);
$hives          = getAllHives($conn);
$avgCO2         = getAvgCO2($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BeeGuard IoT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main class="min-h-screen">

        <!-- Page Header -->
        <div class="page-header px-6 py-4 flex items-center justify-between">
            <div class="pl-2">
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center"
                          style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                        <i class="fas fa-home text-white text-sm"></i>
                    </span>
                    Dashboard
                </h1>
                <p class="text-gray-500 text-xs mt-0.5">Overview of your bee farm monitoring system</p>
            </div>
            <div class="hidden sm:flex items-center gap-3">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse inline-block"></span>
                    Live monitoring active
                </div>
                <span id="lastUpdatedAgo" class="hidden"></span>
            </div>
        </div>

        <div class="p-4 sm:p-6 space-y-5 sm:space-y-6">

            <!-- Stat Cards -->
            <div id="statCardsGrid" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <a href="hives.php" class="block stat-card rounded-2xl p-4 text-white relative overflow-hidden transition hover:brightness-105"
                     style="background:linear-gradient(135deg,#F59E0B 0%,#F97316 100%);box-shadow:0 2px 12px rgba(245,158,11,0.25);">
                    <div class="flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold opacity-80">Total Hives</p>
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-layer-group text-xs"></i>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold leading-none" id="stat-totalHives"><?php echo $totalHives; ?></h2>
                        <p class="text-xs opacity-70 mt-1.5">Registered hives</p>
                    </div>
                </a>

                <a href="analytics.php" class="block stat-card rounded-2xl p-4 text-white relative overflow-hidden transition hover:brightness-105"
                     style="background:linear-gradient(135deg,#10B981 0%,#059669 100%);box-shadow:0 2px 12px rgba(16,185,129,0.25);">
                    <div class="flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold opacity-80">Healthy Hives</p>
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-check-circle text-xs"></i>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold leading-none" id="stat-healthyPercent"><?php echo $healthyPercent; ?>%</h2>
                        <p class="text-xs opacity-70 mt-1.5" id="stat-healthySub"><?php echo $healthyCount; ?> of <?php echo $totalHives; ?> hives</p>
                    </div>
                </a>

                <a href="analytics.php" class="block stat-card rounded-2xl p-4 text-white relative overflow-hidden transition hover:brightness-105"
                     style="background:linear-gradient(135deg,#F97316 0%,#EA580C 100%);box-shadow:0 2px 12px rgba(249,115,22,0.25);">
                    <div class="flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold opacity-80">Avg Temperature</p>
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-thermometer-half text-xs"></i>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold leading-none" id="stat-avgTemp"><?php echo $avgTemp !== null ? $avgTemp.'°C' : '—'; ?></h2>
                        <div class="mt-1.5" id="stat-tempBadge"><?php echo getTempRangeBadge($avgTemp); ?></div>
                    </div>
                </a>

                <a href="alerts.php" class="block stat-card rounded-2xl p-4 text-white relative overflow-hidden transition hover:brightness-105"
                     style="background:linear-gradient(135deg,#8B5CF6 0%,#7C3AED 100%);box-shadow:0 2px 12px rgba(139,92,246,0.25);">
                    <div class="flex flex-col h-full">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-xs font-semibold opacity-80">Active Alerts</p>
                            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-bell text-xs"></i>
                            </div>
                        </div>
                        <h2 class="text-3xl font-bold leading-none" id="stat-alertCount"><?php echo count($alerts); ?></h2>
                        <p class="text-xs opacity-70 mt-1.5" id="stat-alertSub">
                            <?php echo count($alerts) === 0 ? '✓ All clear' : '⚠ Needs attention'; ?>
                        </p>
                    </div>
                </a>
            </div>

            <!-- Mini Stats Row -->
            <div id="miniStatsRow" class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl px-5 py-3 flex items-center gap-4 border border-gray-100 shadow-sm">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#EFF6FF;">
                        <i class="fas fa-tint text-blue-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Avg Humidity</p>
                        <p class="font-bold text-gray-800" id="stat-avgHumidity">—</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl px-5 py-3 flex items-center gap-4 border border-gray-100 shadow-sm">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#F5F3FF;">
                        <i class="fas fa-wind text-purple-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Avg CO₂</p>
                        <p class="font-bold text-gray-800" id="stat-avgCO2">—</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl px-5 py-3 flex items-center gap-4 border border-gray-100 shadow-sm">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#ECFDF5;">
                        <i class="fas fa-microchip text-green-500"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Online Devices</p>
                        <p class="font-bold text-gray-800" id="stat-onlineDevices">—</p>
                    </div>
                </div>
            </div>


            <!-- Active Alerts -->
            <div>
                <h2 class="section-title">
                    <i class="fas fa-bell"></i> Active Alerts
                    <?php if (!empty($alerts)): ?>
                    <span class="badge badge-warning ml-auto"><?php echo count($alerts); ?> active</span>
                    <?php endif; ?>
                </h2>

                <?php if (empty($alerts)): ?>
                <div class="bg-white rounded-xl p-8 text-center border border-green-100">
                    <div class="w-14 h-14 bg-green-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                    </div>
                    <p class="text-gray-800 font-semibold">All Systems Normal</p>
                    <p class="text-gray-400 text-sm mt-1">No active alerts — your hives are healthy</p>
                </div>
                <?php else: ?>
                <div class="space-y-3" id="alertsContainer">
                    <?php foreach ($alerts as $alert):
                        $isC = $alert['severity'] === 'critical';
                        $isW = $alert['severity'] === 'warning';
                        $color = $isC ? 'red' : ($isW ? 'yellow' : 'blue');
                        $bg    = $isC ? '#FFF1F2' : ($isW ? '#FFFBEB' : '#EFF6FF');
                        $border= $isC ? '#F87171' : ($isW ? '#FCD34D' : '#60A5FA');
                    ?>
                    <div id="alert-<?php echo $alert['id']; ?>"
                         class="bg-white rounded-xl p-4 flex items-start gap-3 transition-all"
                         style="border-left:3px solid <?php echo $border; ?>;">
                        <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                             style="background:<?php echo $bg; ?>;">
                            <i class="fas fa-exclamation-triangle text-<?php echo $color; ?>-500 text-lg"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($alert['hive_name'] ?? 'Unknown Hive'); ?></h3>
                                <span class="badge badge-<?php echo $isC ? 'critical' : ($isW ? 'warning' : 'info'); ?>">
                                    <?php echo strtoupper($alert['severity']); ?>
                                </span>
                                <span class="text-xs text-gray-400 uppercase font-semibold tracking-wide"><?php echo $alert['alert_type']; ?></span>
                            </div>
                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($alert['message']); ?></p>
                            <?php if ($alert['recommended_action']): ?>
                            <p class="text-xs text-gray-500 mt-2 flex items-start gap-1">
                                <i class="fas fa-lightbulb text-yellow-500 mt-0.5"></i>
                                <?php echo htmlspecialchars($alert['recommended_action']); ?>
                            </p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 mt-2">
                                <i class="far fa-clock mr-1"></i><?php echo time_elapsed_string($alert['created_at']); ?>
                            </p>
                        </div>
                        <button onclick="dismissAlert(<?php echo $alert['id']; ?>)"
                                class="w-9 h-9 bg-gray-50 hover:bg-red-50 border border-gray-200 hover:border-red-200 rounded-xl flex items-center justify-center transition-all flex-shrink-0 group">
                            <i class="fas fa-times text-gray-400 group-hover:text-red-500 transition-colors"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Live Hive Status -->
            <div>
                <h2 class="section-title">
                    <i class="fas fa-signal"></i> Live Hive Status
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <?php foreach (array_slice($hives, 0, 6) as $hive):
                        $sensor = getLatestSensorData($conn, $hive['id']);
                        $audio  = getLatestAudioAnalysis($conn, $hive['id']);
                        $badge  = $audio ? getHiveStatusBadge($audio['colony_status']) : null;
                        $recs   = getHiveRecommendations($sensor, $audio);
                        $hasWarn= !empty(array_filter($recs, fn($r) => in_array($r['type'], ['critical','warning'])));
                        $warnCount = count(array_filter($recs, fn($r) => $r['type'] !== 'success'));
                    ?>
                    <div class="hive-card bg-white p-4 transition-all"
                         style="cursor:pointer;<?php echo $hasWarn ? 'border-color:#FCD34D;' : ''; ?>"
                         onclick="window.location='hives.php?hive=<?php echo $hive['id']; ?>'"
                         title="View <?php echo htmlspecialchars($hive['name']); ?> details">

                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                                     style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);">🐝</div>
                                <div>
                                    <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($hive['name']); ?></h3>
                                    <p class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($hive['hive_id']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php if ($badge): ?>
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg"
                                      style="background:<?php echo $badge['bg']; ?>;color:<?php echo $badge['color']; ?>">
                                    <i class="<?php echo $badge['icon']; ?> text-xs"></i>
                                    <?php echo htmlspecialchars($badge['text']); ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($hasWarn): ?>
                                <button onclick="event.stopPropagation();toggleRec('hive-<?php echo $hive['id']; ?>')"
                                        class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl transition-all"
                                        style="background:linear-gradient(135deg,#F59E0B,#F97316);color:white;box-shadow:0 3px 10px rgba(245,158,11,0.35);">
                                    <i class="fas fa-lightbulb"></i> <?php echo $warnCount; ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($sensor): ?>
                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="metric-box text-center p-3 rounded-xl" style="background:#FFF7ED;">
                                <i class="fas fa-thermometer-half text-orange-500 text-lg mb-1"></i>
                                <p class="text-xs text-gray-500 mb-0.5">Temp</p>
                                <p class="font-black text-orange-600 text-lg"><?php echo number_format($sensor['temperature'],1); ?>°</p>
                                <?php echo getTempRangeBadgeSmall($sensor['temperature']); ?>
                            </div>
                            <div class="metric-box text-center p-3 rounded-xl" style="background:#EFF6FF;">
                                <i class="fas fa-tint text-blue-500 text-lg mb-1"></i>
                                <p class="text-xs text-gray-500 mb-0.5">Humidity</p>
                                <p class="font-black text-blue-600 text-lg"><?php echo number_format($sensor['humidity'],1); ?>%</p>
                            </div>
                            <div class="metric-box text-center p-3 rounded-xl" style="background:#F5F3FF;">
                                <i class="fas fa-wind text-purple-500 text-lg mb-1"></i>
                                <p class="text-xs text-gray-500 mb-0.5">CO₂</p>
                                <p class="font-black text-purple-600 text-sm leading-tight mt-0.5"><?php echo $sensor['co2']; ?><span class="text-xs font-normal">ppm</span></p>
                            </div>
                        </div>

                        <?php if ($audio): ?>
                        <div class="mb-3">
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-xs font-semibold text-gray-500 flex items-center gap-1">
                                    <i class="fas fa-volume-up text-yellow-500"></i> Sound Activity
                                </span>
                                <span class="text-xs font-bold text-gray-700"><?php echo round($audio['sound_level']); ?>%</span>
                            </div>
                            <div class="progress-bar h-2">
                                <div class="progress-fill h-full"
                                     style="width:<?php echo round($audio['sound_level']); ?>%;background:linear-gradient(90deg,#F59E0B,#F97316);"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Recommendations -->
                        <div id="recommendations-hive-<?php echo $hive['id']; ?>" class="hidden mt-3 pt-3 border-t border-gray-100">
                            <p class="text-xs font-bold text-gray-700 mb-2 flex items-center gap-1">
                                <i class="fas fa-clipboard-list text-yellow-500"></i> Recommendations
                            </p>
                            <?php foreach ($recs as $rec): if ($rec['type'] === 'success') continue;
                                $rc = $rec['type'] === 'critical' ? 'red' : ($rec['type'] === 'warning' ? 'yellow' : 'blue');
                            ?>
                            <div class="mb-2 rounded-xl p-3 text-xs" style="background:#F8FAFC;border-left:3px solid <?php echo $rc === 'red' ? '#F87171' : ($rc === 'yellow' ? '#FCD34D' : '#60A5FA'); ?>">
                                <p class="font-bold text-gray-800 mb-1"><i class="fas <?php echo $rec['icon']; ?> mr-1"></i><?php echo $rec['title']; ?></p>
                                <p class="text-gray-500 mb-1"><?php echo $rec['message']; ?></p>
                                <?php foreach ($rec['actions'] as $act): ?>
                                <p class="text-gray-600">→ <?php echo htmlspecialchars($act); ?></p>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <p class="text-xs text-gray-400 mt-3 flex items-center gap-1">
                            <i class="far fa-clock"></i> <?php echo time_elapsed_string($sensor['timestamp']); ?>
                        </p>
                        <?php else: ?>
                        <div class="text-center py-6 text-gray-400">
                            <i class="fas fa-satellite-dish text-3xl mb-2 block"></i>
                            <p class="text-sm">No sensor data yet</p>
                            <p class="text-xs mt-1">ESP32 not connected</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
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

    function toggleRec(id) {
        const p = document.getElementById('recommendations-' + id);
        if (!p) return;
        p.classList.toggle('hidden');
        if (!p.classList.contains('hidden')) p.scrollIntoView({behavior:'smooth',block:'nearest'});
    }

    // ── Auto-refresh stats + activity ────────────────────────
    let _lastRefresh = Date.now();

    function refreshStats() {
        fetch('api/dashboard_stats.php')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                _lastRefresh = Date.now();

                // Stat cards
                document.getElementById('stat-totalHives').textContent     = d.totalHives;
                document.getElementById('stat-healthyPercent').textContent  = d.healthyPercent + '%';
                document.getElementById('stat-healthySub').textContent      = d.healthyCount + ' of ' + d.totalHives + ' hives';
                document.getElementById('stat-avgTemp').textContent = d.avgTemp ? d.avgTemp + '°C' : '—';
                document.getElementById('stat-tempBadge').innerHTML = tempRangeBadge(d.avgTemp);
                document.getElementById('stat-alertCount').textContent      = d.alertCount;
                document.getElementById('stat-alertSub').textContent        = d.alertCount === 0 ? '✓ All clear' : '⚠ Needs attention';

                // Mini stats
                document.getElementById('stat-avgHumidity').textContent     = d.avgHumidity ? d.avgHumidity + '%' : '—';
                document.getElementById('stat-avgCO2').textContent          = d.avgCO2 ? d.avgCO2 + ' ppm' : '—';
                document.getElementById('stat-onlineDevices').textContent   = d.onlineDevices;

            })
            .catch(() => {});
    }

    // Run immediately + every 30s
    refreshStats();
    setInterval(refreshStats, 30000);

    // Live "last updated Xs ago" in the header indicator
    setInterval(() => {
        const el = document.getElementById('lastUpdatedAgo');
        if (!el) return;
        const secs = Math.floor((Date.now() - _lastRefresh) / 1000);
        el.textContent = secs < 5 ? 'just now' : secs + 's ago';
    }, 1000);

    function dismissAlert(id) {
        const el = document.getElementById('alert-' + id);
        if (!el) return;
        fetch('api/dismiss_alert.php', {
            method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'alert_id='+id
        }).then(r=>r.json()).then(data => {
            if (!data.success) return;
            el.style.opacity = '0';
            el.style.transform = 'translateX(30px)';
            el.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                el.remove();
                const c = document.getElementById('alertsContainer');
                if (c && !c.querySelector('[id^="alert-"]')) {
                    c.parentElement.innerHTML = `
                    <div class="bg-white rounded-xl p-8 text-center border border-green-100">
                        <div class="w-14 h-14 bg-green-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
                        </div>
                        <p class="text-gray-800 font-semibold">All Systems Normal</p>
                        <p class="text-gray-400 text-sm mt-1">No active alerts — your hives are healthy</p>
                    </div>`;
                }
            }, 320);
        }).catch(()=>{});
    }
    </script>
</body>
</html>
