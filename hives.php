<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'get_recommendations.php';
checkAuth();
$hives = getAllHives($conn);
$totalHives = count($hives);
$normalCount = 0; $warnCount = 0; $noDataCount = 0;
$isAdmin = isAdmin();

// Pre-calculate stats before HTML renders
foreach ($hives as $_hive) {
    $_sensor = getLatestSensorData($conn, $_hive['id']);
    if (!$_sensor) { $noDataCount++; continue; }
    $_audio = getLatestAudioAnalysis($conn, $_hive['id']);
    $_recs = getHiveRecommendations($_sensor, $_audio);
    $_hasCritical = false; $_hasWarnings = false;
    foreach ($_recs as $_r) {
        if ($_r['type'] === 'critical' || $_r['type'] === 'warning') $_hasWarnings = true;
        if ($_r['type'] === 'critical') $_hasCritical = true;
    }
    if ($_hasCritical || $_hasWarnings) { $warnCount++; } else { $normalCount++; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Hives - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        @keyframes slideInRight {
            from { opacity:0; transform:translateX(40px); }
            to   { opacity:1; transform:translateX(0); }
        }
        .hive-card {
            transition: box-shadow 0.18s ease, border-color 0.18s ease;
            cursor: pointer;
        }
        .hive-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            border-color: rgba(245,158,11,0.3) !important;
        }

        /* ── Modal overlay ── */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 99999;
            background: rgba(10,6,0,0.7);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            overflow-y: auto;           /* scroll happens on OVERLAY */
            padding: 24px 16px;
        }
        .modal.active { display: block; } /* block so overlay scrolls */

        /* ── Modal card ── */
        .modal-content {
            background: #fff;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;             /* centered, no height limit */
            border-radius: 24px !important;
            box-shadow: 0 32px 80px rgba(0,0,0,0.45) !important;
            border: none !important;
            transform: none !important;
            position: relative;
            animation: modal-slide-in 0.35s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        .modal-content:hover { transform: none !important; }

        @keyframes modal-slide-in {
            from { opacity:0; transform:translateY(-18px) scale(0.96); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }

        /* ── Sensor stat pill inside modal ── */
        .sensor-pill {
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        @media (max-width: 480px) {
            .sensor-pill {
                border-radius: 12px;
                padding: 10px 8px;
                gap: 2px;
            }
            .sensor-pill .text-xl { font-size: 15px !important; }
            .sensor-pill .text-xs { font-size: 10px !important; }
            .sensor-pill canvas,
            .sensor-pill div[style*="height:40px"] { display: none !important; }
        }

        /* ── Recommendation item ── */
        .rec-item {
            border-radius: 14px;
            padding: 16px;
            border-left: 4px solid;
            transition: box-shadow 0.2s;
        }
        .rec-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }

        /* Scrollbar inside modal */
        .modal-content::-webkit-scrollbar { width: 5px; }
        .modal-content::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }
        @media (max-width: 480px) {
            .modal-content > div:not(:first-child) { padding: 12px 14px !important; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-6 py-4 pl-8">
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center"
                      style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                    <i class="fas fa-layer-group text-white text-sm"></i>
                </span>
                Live Hives Monitor
            </h1>
            <p class="text-gray-500 text-xs mt-0.5">Real-time sensor readings from all connected hives</p>
        </div>
        <div class="p-4 sm:p-6">
            <!-- Hive Summary Stats -->
            <div class="grid grid-cols-3 sm:grid-cols-3 gap-3 mb-5" id="hiveSummaryRow">
                <div class="bg-white rounded-xl p-3 flex items-center gap-3 border border-gray-100">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                        <i class="fas fa-layer-group text-white text-xs"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-gray-800"><?php echo $totalHives; ?></p>
                        <p class="text-xs text-gray-400">Total Hives</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 flex items-center gap-3 border border-gray-100">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-green-50">
                        <i class="fas fa-check-circle text-green-500 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-green-600"><?php echo $normalCount; ?></p>
                        <p class="text-xs text-gray-400">Normal</p>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 flex items-center gap-3 border border-gray-100">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 bg-red-50">
                        <i class="fas fa-exclamation-triangle text-red-400 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-red-500"><?php echo $warnCount; ?></p>
                        <p class="text-xs text-gray-400">Need Attention</p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl px-3 py-2.5 mb-5 border border-gray-100">
                <div class="flex flex-wrap gap-2 items-center">
                    <div class="flex items-center gap-2 flex-1 min-w-0 border rounded-lg px-3 py-1.5 bg-gray-50 focus-within:ring-2 focus-within:ring-yellow-400 focus-within:border-yellow-400">
                        <i class="fas fa-search text-gray-400 text-xs flex-shrink-0"></i>
                        <input type="text" id="searchInput" placeholder="Search hives..." class="flex-1 min-w-0 bg-transparent text-sm outline-none" onkeyup="filterHives()">
                    </div>
                    <select id="statusFilter" class="px-3 py-1.5 border rounded-lg text-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-yellow-400" onchange="filterHives()">
                        <option value="">All Status</option>
                        <option value="normal">Normal</option>
                        <option value="warning">Warning</option>
                        <option value="critical">Critical</option>
                    </select>
                    <span id="filterCount" class="text-xs text-gray-400 ml-auto"></span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4" id="hivesContainer">
                <?php foreach ($hives as $hive): ?>
                    <?php
                        $sensor = getLatestSensorData($conn, $hive['id']);
                        $audio = getLatestAudioAnalysis($conn, $hive['id']);
                        $badge = $audio ? getHiveStatusBadge($audio['colony_status']) : null;
                        $recommendations = getHiveRecommendations($sensor, $audio);
                        $hasWarnings = false;
                        $hasCritical = false;
                        foreach ($recommendations as $rec) {
                            if ($rec['type'] == 'critical' || $rec['type'] == 'warning') {
                                $hasWarnings = true;
                            }
                            if ($rec['type'] == 'critical') {
                                $hasCritical = true;
                            }
                        }
                        // Online = last sensor data received within 2 minutes
                        $isOnline = false;
                        if ($sensor && !empty($sensor['timestamp'])) {
                            $lastSeen = strtotime($sensor['timestamp']);
                            $isOnline = (time() - $lastSeen) <= 120;
                        }
                    ?>
                    <div class="hive-card bg-white rounded-xl overflow-hidden border border-gray-100"
                         style="<?php echo $hasCritical ? 'border-left:3px solid #EF4444;' : ($hasWarnings ? 'border-left:3px solid #F59E0B;' : 'border-left:3px solid #10B981;'); ?>"
                         onclick="openHiveModal(<?php echo $hive['id']; ?>)"
                         data-hive-name="<?php echo htmlspecialchars($hive['name']); ?>"
                         data-hive-id="<?php echo htmlspecialchars($hive['hive_id']); ?>"
                         data-status="<?php echo $hasCritical ? 'critical' : ($hasWarnings ? 'warning' : 'normal'); ?>">
                        <div class="px-4 py-3 flex justify-between items-center border-b border-gray-100">
                            <div>
                                <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($hive['name']); ?></h3>
                                <p class="text-xs text-gray-400 font-mono"><?php echo htmlspecialchars($hive['hive_id']); ?></p>
                                <span id="online-badge-<?php echo $hive['id']; ?>"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold mt-1"
                                    style="background:<?php echo $isOnline ? '#dcfce7' : '#fee2e2'; ?>;color:<?php echo $isOnline ? '#16a34a' : '#dc2626'; ?>;">
                                    <span style="width:6px;height:6px;border-radius:50%;background:<?php echo $isOnline ? '#16a34a' : '#dc2626'; ?>;display:inline-block;"></span>
                                    <span id="online-badge-text-<?php echo $hive['id']; ?>"><?php echo $isOnline ? 'Online' : 'Offline'; ?></span>
                                </span>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <span id="badge-<?php echo $hive['id']; ?>"
                                    class="<?php echo $badge ? '' : 'hidden'; ?> inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold"
                                    style="<?php echo $badge ? 'background:'.$badge['bg'].';color:'.$badge['color'].';' : ''; ?>">
                                    <i id="badge-icon-<?php echo $hive['id']; ?>" class="<?php echo $badge ? $badge['icon'] : ''; ?> text-xs"></i>
                                    <span id="badge-text-<?php echo $hive['id']; ?>"><?php echo $badge ? htmlspecialchars($badge['text']) : ''; ?></span>
                                </span>
                                <span id="issues-<?php echo $hive['id']; ?>"
                                    class="<?php echo $hasWarnings ? '' : 'hidden'; ?> bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full text-xs font-semibold flex items-center gap-1">
                                    <i class="fas fa-exclamation-triangle text-xs"></i>
                                    <span id="issues-count-<?php echo $hive['id']; ?>">
                                        <?php echo count(array_filter($recommendations, fn($r) => $r['type'] != 'success')); ?>
                                    </span> Issues
                                </span>
                            </div>
                        </div>
                        <div class="p-4" id="card-body-<?php echo $hive['id']; ?>">
                            <?php if ($sensor): ?>
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <!-- Temperature -->
                                    <div class="rounded-xl p-2.5" style="background:#EFF6FF;">
                                        <p class="text-xs font-bold mb-0.5" style="color:#60A5FA;">Temp</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:#2563EB;" id="temp-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['temperature'], 1); ?>°C</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-temp-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <!-- Humidity -->
                                    <div class="rounded-xl p-2.5" style="background:#FFF1F2;">
                                        <p class="text-xs font-bold mb-0.5" style="color:#F87171;">Humidity</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:#DC2626;" id="humid-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['humidity'], 1); ?>%</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-humid-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <!-- CO₂ -->
                                    <div class="rounded-xl p-2.5" style="background:#FFFBEB;">
                                        <p class="text-xs font-bold mb-0.5" style="color:#FBBF24;">CO₂</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:#D97706;" id="co2-<?php echo $hive['id']; ?>"><?php echo $sensor['co2']; ?>ppm</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-co2-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg mb-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="text-xs text-gray-600">Sound Activity</p>
                                        <p class="text-sm font-bold" id="sound-val-<?php echo $hive['id']; ?>"><?php echo $sensor ? round($sensor['sound_level']) : 0; ?>%</p>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden mb-2">
                                        <div class="h-full bg-yellow-500" id="sound-bar-<?php echo $hive['id']; ?>" style="width:<?php echo $sensor ? round($sensor['sound_level']) : 0; ?>%"></div>
                                    </div>
                                    <!-- Colony status conclusion — visible on card -->
                                    <?php
                                        $colonyStatus = $audio['colony_status'] ?? 'normal';
                                        $conclusionMap = [
                                            'normal'    => ['🟢', 'Colony Normal',     '#16a34a', '#dcfce7'],
                                            'swarming'  => ['🟠', 'Swarming Detected', '#c2410c', '#ffedd5'],
                                            'queenless' => ['🔴', 'Queenless Detected','#dc2626', '#fee2e2'],
                                        ];
                                        $c = $conclusionMap[$colonyStatus] ?? ['⚪', ucfirst(str_replace('_',' ',$colonyStatus)), '#6b7280', '#f3f4f6'];
                                    ?>
                                    <div id="colony-conclusion-<?php echo $hive['id']; ?>"
                                         class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-semibold"
                                         style="background:<?php echo $audio ? $c[3] : '#f3f4f6'; ?>;color:<?php echo $audio ? $c[2] : '#9ca3af'; ?>;">
                                        <span id="colony-icon-<?php echo $hive['id']; ?>"><?php echo $audio ? $c[0] : '⚪'; ?></span>
                                        <span>
                                            <span class="opacity-60 font-normal">Sound Analysis: </span>
                                            <span id="colony-text-<?php echo $hive['id']; ?>"><?php echo $audio ? $c[1] : 'No analysis yet'; ?></span>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <span><i class="fas fa-clock mr-1"></i><span id="time-<?php echo $hive['id']; ?>"><?php echo time_elapsed_string($sensor['timestamp']); ?></span></span>
                                    <span class="text-yellow-600 font-semibold cursor-pointer hover:underline" onclick="openHiveModal(<?php echo $hive['id']; ?>)">Click for details →</span>
                                </div>
                            <?php else: ?>
                                <div id="no-data-<?php echo $hive['id']; ?>" class="py-6 text-center text-gray-400 text-sm">
                                    <i class="fas fa-satellite-dish mb-2 text-2xl"></i>
                                    <p>No sensor data — ESP32 not connected</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Hive Detail Modal -->
                    <div id="modal-<?php echo $hive['id']; ?>" class="modal" onclick="if(event.target===this)closeModal(<?php echo $hive['id']; ?>)">
                        <div class="modal-content">

                            <!-- Header -->
                            <div class="relative overflow-hidden"
                                 style="background:linear-gradient(135deg,#F59E0B 0%,#F97316 100%);padding:18px 20px 16px;">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center text-xl">🐝</div>
                                        <div>
                                            <h2 class="text-lg font-bold text-white"><?php echo htmlspecialchars($hive['name']); ?></h2>
                                            <p class="text-yellow-100 text-xs font-mono mt-0.5"><?php echo htmlspecialchars($hive['hive_id']); ?></p>
                                            <?php if (!empty($hive['location'])): ?>
                                            <p class="text-yellow-200 text-xs mt-0.5"><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($hive['location']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="closeModal(<?php echo $hive['id']; ?>)"
                                            class="w-8 h-8 bg-white/20 hover:bg-white/35 rounded-lg flex items-center justify-center text-white transition-all flex-shrink-0">
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                </div>
                                <?php if ($badge): ?>
                                <div class="mt-2 inline-flex items-center gap-1.5 bg-white/20 text-white px-2.5 py-1 rounded-lg text-xs font-semibold">
                                    <i class="<?php echo $badge['icon']; ?> text-xs"></i><?php echo $badge['text']; ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Body -->
                            <div style="padding:18px 20px;">
                                <?php if ($sensor): ?>

                                <!-- Sensor pills -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                    <i class="fas fa-chart-line text-yellow-500"></i> Real-Time Sensor Data
                                </p>
                                <div class="grid grid-cols-3 gap-3 mb-4">
                                    <?php
                                    $tOk = $sensor['temperature'] >= 33 && $sensor['temperature'] <= 36;
                                    $hOk = $sensor['humidity'] <= 75;
                                    $cOk = $sensor['co2'] <= 2000;
                                    ?>
                                    <div class="sensor-pill text-white" id="modal-pill-temp-<?php echo $hive['id']; ?>" style="background:linear-gradient(135deg,<?php echo $tOk?'#F97316,#EA580C':'#EF4444,#DC2626';?>);box-shadow:0 2px 10px rgba(<?php echo $tOk?'249,115,22':'239,68,68';?>,0.25);">
                                        <span class="text-xs opacity-80 font-medium">Temperature</span>
                                        <span class="text-xl font-bold" id="modal-temp-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['temperature'],1); ?>°C</span>
                                        <span class="text-xs opacity-70" id="modal-temp-status-<?php echo $hive['id']; ?>"><?php echo $tOk?'✓ Optimal':'⚠ Off range'; ?></span>
                                        <div style="height:40px;position:relative;margin-top:4px;">
                                            <canvas id="modal-chart-temp-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <div class="sensor-pill text-white" id="modal-pill-humid-<?php echo $hive['id']; ?>" style="background:linear-gradient(135deg,<?php echo $hOk?'#3B82F6,#2563EB':'#F59E0B,#D97706';?>);box-shadow:0 2px 10px rgba(<?php echo $hOk?'59,130,246':'245,158,11';?>,0.25);">
                                        <span class="text-xs opacity-80 font-medium">Humidity</span>
                                        <span class="text-xl font-bold" id="modal-humid-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['humidity'],1); ?>%</span>
                                        <span class="text-xs opacity-70" id="modal-humid-status-<?php echo $hive['id']; ?>"><?php echo $hOk?'✓ Normal':'⚠ Too humid'; ?></span>
                                        <div style="height:40px;position:relative;margin-top:4px;">
                                            <canvas id="modal-chart-humid-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <div class="sensor-pill text-white" id="modal-pill-co2-<?php echo $hive['id']; ?>" style="background:linear-gradient(135deg,<?php echo $cOk?'#8B5CF6,#7C3AED':'#EF4444,#DC2626';?>);box-shadow:0 2px 10px rgba(<?php echo $cOk?'139,92,246':'239,68,68';?>,0.25);">
                                        <span class="text-xs opacity-80 font-medium">CO₂</span>
                                        <span class="text-xl font-bold" id="modal-co2-<?php echo $hive['id']; ?>"><?php echo $sensor['co2']; ?> <span style="font-size:12px;font-weight:500;">ppm</span></span>
                                        <span class="text-xs opacity-70" id="modal-co2-status-<?php echo $hive['id']; ?>"><?php echo $cOk?'✓ Good':'⚠ Elevated'; ?></span>
                                        <div style="height:40px;position:relative;margin-top:4px;">
                                            <canvas id="modal-chart-co2-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <!-- Camera Feed inside modal -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <i class="fas fa-video text-yellow-500"></i> Hive Entrance Camera
                                </p>
                                <div class="rounded-xl overflow-hidden mb-4" style="background:#0F172A;position:relative;">
                                    <!-- Clickable wrapper to maximize -->
                                    <div id="modal-cam-wrap-<?php echo $hive['id']; ?>"
                                         onclick="expandCam(<?php echo $hive['id']; ?>)"
                                         style="cursor:pointer;position:relative;">
                                        <!-- MJPEG live stream — zero delay, browser handles it natively -->
                                        <img id="modal-cam-<?php echo $hive['id']; ?>"
                                             alt="Live camera feed"
                                             class="w-full object-contain block"
                                             style="max-height:220px;display:none;">
                                        <!-- Offline state -->
                                        <div id="modal-cam-offline-<?php echo $hive['id']; ?>"
                                             class="flex flex-col items-center justify-center py-10">
                                            <i class="fas fa-video-slash text-2xl mb-3" style="color:#334155;"></i>
                                            <p class="text-xs font-semibold mb-1" style="color:#475569;">Camera offline</p>
                                            <button onclick="startCamera(event, <?php echo $hive['id']; ?>)"
                                                    id="btn-start-cam"
                                                    class="mt-2 flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold transition-all"
                                                    style="background:#F59E0B;color:#fff;border:none;cursor:pointer;">
                                                <i class="fas fa-play"></i> Start Camera
                                            </button>
                                        </div>
                                        <!-- Expand hint -->
                                        <div id="modal-cam-hint-<?php echo $hive['id']; ?>"
                                             class="hidden"
                                             style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.55);color:white;font-size:11px;font-weight:600;padding:4px 9px;border-radius:8px;pointer-events:none;">
                                            <i class="fas fa-expand-alt mr-1"></i>Click to expand
                                        </div>
                                        <!-- Live overlay -->
                                        <div id="modal-cam-overlay-<?php echo $hive['id']; ?>"
                                             class="hidden"
                                             style="position:absolute;bottom:0;left:0;right:0;padding:6px 12px;background:linear-gradient(0deg,rgba(0,0,0,0.65),transparent);">
                                            <div class="flex items-center justify-between">
                                                <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.9);">
                                                    <i class="fas fa-circle text-green-400 mr-1" style="font-size:6px;"></i>LIVE
                                                </span>
                                                <div class="flex items-center gap-2">
                                                    <span id="modal-cam-time-<?php echo $hive['id']; ?>" style="font-size:11px;color:rgba(255,255,255,0.6);"></span>
                                                    <button onclick="stopCamera(event)"
                                                            id="btn-stop-cam"
                                                            style="background:rgba(239,68,68,0.85);color:#fff;border:none;border-radius:6px;padding:3px 9px;font-size:10px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;">
                                                        <i class="fas fa-stop"></i> Stop
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Detection stats bar -->
                                    <div id="modal-cam-stats-<?php echo $hive['id']; ?>"
                                         style="display:none;align-items:center;justify-content:space-between;padding:8px 14px;background:#0F172A;border-top:1px solid rgba(255,255,255,0.06);">
                                        <div style="display:flex;align-items:center;gap:16px;">
                                            <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#4ADE80;">
                                                <span style="width:10px;height:10px;border-radius:2px;background:#4ADE80;display:inline-block;"></span>
                                                Bees: <span id="modal-bee-count-<?php echo $hive['id']; ?>">0</span>
                                            </span>
                                            <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#F87171;">
                                                <span style="width:10px;height:10px;border-radius:2px;background:#F87171;display:inline-block;"></span>
                                                Insects: <span id="modal-insect-count-<?php echo $hive['id']; ?>">0</span>
                                            </span>
                                        </div>
                                        <div id="modal-insect-alert-<?php echo $hive['id']; ?>"
                                             style="display:none;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#FCA5A5;background:rgba(239,68,68,0.15);padding:3px 10px;border-radius:20px;border:1px solid rgba(239,68,68,0.3);">
                                            <i class="fas fa-exclamation-triangle" style="font-size:10px;"></i> Insect alert!
                                        </div>
                                    </div>
                                </div>

                                <?php if ($audio): ?>
                                <!-- Audio Analysis -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <i class="fas fa-volume-up text-yellow-500"></i> Audio Analysis & Colony Status
                                </p>
                                <div class="rounded-xl p-3 mb-4 flex gap-4" style="background:#FFFBEB;border:1px solid #FDE68A;">
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2 font-semibold">Colony Status</p>
                                        <?php if ($badge): ?>
                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-bold"
                                              style="background:white;border:1.5px solid #FDE68A;color:#92400E;">
                                            <i class="<?php echo $badge['icon']; ?> text-yellow-600"></i>
                                            <?php echo $badge['text']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-brain text-yellow-500 mr-1"></i>
                                            AI Confidence: <strong class="text-gray-700"><?php echo round($audio['confidence']); ?>%</strong>
                                        </p>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2 font-semibold">Sound Activity</p>
                                        <div class="flex items-center gap-3 mb-1">
                                            <div class="flex-1 h-3 bg-yellow-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full" style="width:<?php echo $sensor ? round($sensor['sound_level']) : 0; ?>%;background:linear-gradient(90deg,#F59E0B,#F97316);"></div>
                                            </div>
                                            <span class="text-xl font-black text-yellow-600"><?php echo $sensor ? round($sensor['sound_level']) : 0; ?>%</span>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-microphone text-yellow-500 mr-1"></i>
                                            <?php echo $audio['dominant_freq']; ?> Hz dominant
                                        </p>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Recommendations -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <i class="fas fa-lightbulb text-yellow-500"></i> Smart Recommendations
                                </p>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <?php foreach ($recommendations as $rec):
                                        $rc = $rec['type'];
                                        $bg   = $rc==='critical'?'#FFF1F2':($rc==='warning'?'#FFFBEB':($rc==='success'?'#F0FDF4':'#EFF6FF'));
                                        $bd   = $rc==='critical'?'#F87171':($rc==='warning'?'#FCD34D':($rc==='success'?'#4ADE80':'#60A5FA'));
                                        $ic   = $rc==='critical'?'#EF4444':($rc==='warning'?'#F59E0B':($rc==='success'?'#22C55E':'#3B82F6'));
                                    ?>
                                    <div class="rec-item" style="background:<?php echo $bg; ?>;border-left-color:<?php echo $bd; ?>;">
                                        <div style="display:flex;align-items:flex-start;gap:12px;">
                                            <div style="width:36px;height:36px;border-radius:10px;background:white;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <i class="fas <?php echo $rec['icon']; ?>" style="color:<?php echo $ic; ?>;"></i>
                                            </div>
                                            <div style="flex:1;">
                                                <p style="font-weight:700;color:<?php echo $ic; ?>;font-size:14px;margin-bottom:2px;"><?php echo $rec['title']; ?></p>
                                                <p style="font-size:13px;color:#6B7280;margin-bottom:8px;"><?php echo $rec['message']; ?></p>
                                                <?php if (!empty($rec['actions'])): ?>
                                                <div style="display:flex;flex-direction:column;gap:4px;">
                                                    <?php foreach ($rec['actions'] as $action): ?>
                                                    <p style="font-size:12px;color:#374151;display:flex;align-items:flex-start;gap:6px;">
                                                        <i class="fas fa-arrow-right" style="color:<?php echo $ic; ?>;margin-top:3px;font-size:10px;"></i>
                                                        <?php echo htmlspecialchars($action); ?>
                                                    </p>
                                                    <?php endforeach; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Footer -->
                                <div style="margin-top:14px;padding:10px 14px;background:#F8FAFC;border-radius:10px;display:flex;align-items:center;justify-content:space-between;">
                                    <p style="font-size:12px;color:#94A3B8;">
                                        <i class="far fa-clock mr-1"></i>Last updated: <strong style="color:#64748B;"><?php echo time_elapsed_string($sensor['timestamp']); ?></strong>
                                    </p>
                                    <button onclick="closeModal(<?php echo $hive['id']; ?>)"
                                            style="font-size:13px;font-weight:600;color:#F59E0B;background:none;border:none;cursor:pointer;padding:4px 8px;border-radius:8px;transition:background 0.2s;"
                                            onmouseover="this.style.background='#FEF3C7'" onmouseout="this.style.background='none'">
                                        Close <i class="fas fa-times ml-1"></i>
                                    </button>
                                </div>

                                <?php else: ?>
                                <div style="text-align:center;padding:48px 24px;">
                                    <div style="width:64px;height:64px;background:#F1F5F9;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                        <i class="fas fa-satellite-dish" style="color:#94A3B8;font-size:28px;"></i>
                                    </div>
                                    <p style="font-weight:700;color:#374151;margin-bottom:4px;">No sensor data yet</p>
                                    <p style="font-size:13px;color:#94A3B8;">ESP32 not connected — check device status</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div id="noMatchMsg" style="display:none;" class="col-span-3 py-16 text-center text-gray-400"><i class="fas fa-search text-4xl mb-3 block opacity-40"></i><p class="font-semibold">No hives match your filter</p></div>
            </div>
        </div>
    </main>
    
    <script>
        // Teleport all modals to <body> so they escape any stacking context
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));

            // Auto-open hive modal when navigated from dashboard
            const params = new URLSearchParams(window.location.search);
            const hiveParam = params.get('hive');
            if (hiveParam) openHiveModal(parseInt(hiveParam, 10));
        });

        function openHiveModal(hiveId) {
            const modal = document.getElementById('modal-' + hiveId);
            if (!modal) return;
            modal.classList.add('active');
            modal.scrollTop = 0;
            document.body.style.overflow = 'hidden';
            // Start camera stream only when modal is open
            connectCameraImgs();
        }

        function closeModal(hiveId) {
            const modal = document.getElementById('modal-' + hiveId);
            if (!modal) return;
            // Stop camera stream to prevent browser from keeping tab in loading state
            clearTimeout(_retryTimer);
            document.querySelectorAll('[id^="modal-cam-"]').forEach(img => {
                if (/^modal-cam-\d+$/.test(img.id)) { img.onload = null; img.onerror = null; img.src = ''; }
            });
            setCamOnline(false);
            // Fade out
            modal.style.opacity = '0';
            modal.style.transition = 'opacity 0.2s ease';
            setTimeout(() => {
                modal.classList.remove('active');
                modal.style.opacity = '';
                modal.style.transition = '';
                document.body.style.overflow = '';
            }, 200);
        }

        // Close when clicking the overlay background (not the card)
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
                const idStr = e.target.id.replace('modal-', '');
                closeModal(parseInt(idStr));
            }
        });
        
        // Filter Hives
        function filterHives() {
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
            const cards = document.querySelectorAll('.hive-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-hive-name').toLowerCase();
                const id = card.getAttribute('data-hive-id').toLowerCase();
                const status = card.getAttribute('data-status').toLowerCase();
                
                const matchesSearch = name.includes(searchText) || id.includes(searchText);
                const matchesStatus = !statusFilter || status === statusFilter;
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            const visible = [...cards].filter(c => c.style.display !== 'none').length;
            document.getElementById('filterCount').textContent = visible + ' of ' + cards.length + ' hive' + (cards.length !== 1 ? 's' : '');
            const noMatch = document.getElementById('noMatchMsg');
            if (noMatch) noMatch.style.display = visible === 0 ? 'block' : 'none';
        }
    // ── MJPEG Camera — zero delay live stream ────────────────
    const STREAM_URL = 'http://<?php echo $_SERVER['SERVER_ADDR'] === '::1' ? '127.0.0.1' : $_SERVER['SERVER_ADDR']; ?>:5050/stream';
    const META_URL   = 'http://<?php echo $_SERVER['SERVER_ADDR'] === '::1' ? '127.0.0.1' : $_SERVER['SERVER_ADDR']; ?>:5050/meta';
    let _camOnline   = false;
    let _retryTimer  = null;

    function setCamOnline(online) {
        if (_camOnline === online) return;
        _camOnline = online;
        document.querySelectorAll('[id^="modal-cam-"]').forEach(el => {
            if (!/^modal-cam-\d+$/.test(el.id)) return;
            const hiveId = el.id.replace('modal-cam-', '');
            const off  = document.getElementById('modal-cam-offline-' + hiveId);
            const ov   = document.getElementById('modal-cam-overlay-' + hiveId);
            const hint = document.getElementById('modal-cam-hint-'    + hiveId);
            const tm   = document.getElementById('modal-cam-time-'    + hiveId);
            if (online) {
                el.style.display = 'block';
                if (off)  off.style.display = 'none';
                if (ov)   ov.classList.remove('hidden');
                if (hint) hint.classList.remove('hidden');
                if (tm)   tm.textContent = new Date().toLocaleTimeString();
            } else {
                el.style.display = 'none';
                if (off)  off.style.display = '';
                if (ov)   ov.classList.add('hidden');
                if (hint) hint.classList.add('hidden');
            }
        });
        const lbImg = document.getElementById('camLightboxImg');
        if (lbImg && online && lbImg.src !== STREAM_URL) lbImg.src = STREAM_URL;
    }

    // Connect img elements directly to MJPEG stream.
    // img onload/onerror is more reliable than fetch/ping:
    //   • img src loads cross-origin without CORS restrictions
    //   • onload fires when the first frame arrives  → camera online
    //   • onerror fires when connection refused/lost → camera offline
    function connectCameraImgs() {
        clearTimeout(_retryTimer);
        document.querySelectorAll('[id^="modal-cam-"]').forEach(img => {
            if (!/^modal-cam-\d+$/.test(img.id)) return;
            img.onload = function() {
                setCamOnline(true);
            };
            img.onerror = function() {
                setCamOnline(false);
                img.src = '';
                _retryTimer = setTimeout(connectCameraImgs, 3000);
            };
            // Always (re)set src so browser re-tries the connection
            img.src = STREAM_URL + '?t=' + Date.now();
        });
    }
    function fetchWithTimeout(url, ms) {
        const ctrl = new AbortController();
        const tid  = setTimeout(() => ctrl.abort(), ms);
        return fetch(url, { signal: ctrl.signal }).finally(() => clearTimeout(tid));
    }

    // ── Detection stats poller (every 3 seconds) ─────────────
    function refreshDetectionStats() {
        fetchWithTimeout(META_URL, 2000)
            .then(r => r.json())
            .then(data => {
                // Update every hive modal's detection bar
                document.querySelectorAll('[id^="modal-cam-stats-"]').forEach(bar => {
                    const hiveId     = bar.id.replace('modal-cam-stats-', '');
                    const beeEl      = document.getElementById('modal-bee-count-'    + hiveId);
                    const insectEl   = document.getElementById('modal-insect-count-' + hiveId);
                    const alertEl    = document.getElementById('modal-insect-alert-' + hiveId);

                    if (!data.online) {
                        bar.style.display = 'none';
                        return;
                    }

                    bar.style.display = 'flex';
                    if (beeEl)    beeEl.textContent    = data.bee_count    ?? 0;
                    if (insectEl) insectEl.textContent  = data.insect_count ?? 0;

                    const hasInsects = (data.insect_count ?? 0) > 0;
                    if (alertEl) {
                        alertEl.style.display = hasInsects ? 'flex' : 'none';
                    }

                });

                // Update lightbox stats bar
                if (data.online) updateLightboxStats(data.bee_count ?? 0, data.insect_count ?? 0, data.timestamp ?? '');
            })
            .catch(() => {});
    }
    refreshDetectionStats();
    setInterval(refreshDetectionStats, 1000);

    // Also update lightbox stats bar
    function updateLightboxStats(beeCount, insectCount, ts) {
        const bar = document.getElementById('camLightboxStats');
        if (!bar) return;
        bar.innerHTML =
            `<span style="color:#4ADE80;font-weight:700;font-size:12px;">🟢 Bees: ${beeCount}</span>` +
            `<span style="color:#F87171;font-weight:700;font-size:12px;margin-left:16px;">🔴 Insects: ${insectCount}</span>` +
            (insectCount > 0 ? `<span style="color:#FCA5A5;font-size:11px;margin-left:12px;"><i class="fas fa-exclamation-triangle"></i> Alert!</span>` : '');
    }

    // Auto-refresh every 5 seconds
    setInterval(refreshHiveCards, 15000);

    // ── Sparkline charts ─────────────────────────────────────
    function loadHiveCharts() {
        fetch('api/hive_trends.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                for (const [hiveId, t] of Object.entries(data.trends)) {
                    drawSparkline('chart-temp-'  + hiveId, t.labels, t.temp,
                        '#3B82F6', 'rgba(59,130,246,{a})');
                    drawSparkline('chart-humid-' + hiveId, t.labels, t.humidity,
                        '#EF4444', 'rgba(239,68,68,{a})');
                    drawSparkline('chart-co2-'   + hiveId, t.labels, t.co2,
                        '#F59E0B', 'rgba(245,158,11,{a})');
                    // Modal pill sparklines (white line on colored background)
                    drawSparkline('modal-chart-temp-'  + hiveId, t.labels, t.temp,
                        'rgba(255,255,255,0.9)', 'rgba(255,255,255,{a})');
                    drawSparkline('modal-chart-humid-' + hiveId, t.labels, t.humidity,
                        'rgba(255,255,255,0.9)', 'rgba(255,255,255,{a})');
                    drawSparkline('modal-chart-co2-'   + hiveId, t.labels, t.co2,
                        'rgba(255,255,255,0.9)', 'rgba(255,255,255,{a})');
                }
            })
            .catch(() => {});
    }

    function drawSparkline(canvasId, labels, values, lineColor, fillTpl) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        // Destroy previous chart on this canvas
        const prev = Chart.getChart(canvas);
        if (prev) prev.destroy();

        const ctx = canvas.getContext('2d');
        const h   = canvas.parentElement.offsetHeight || 42;
        const grad = ctx.createLinearGradient(0, 0, 0, h);
        grad.addColorStop(0, fillTpl.replace('{a}', '0.45'));
        grad.addColorStop(1, fillTpl.replace('{a}', '0.02'));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    data: values,
                    borderColor: lineColor,
                    backgroundColor: grad,
                    borderWidth: 1.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: lineColor,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15,10,0,0.78)',
                        titleColor: '#fde68a',
                        bodyColor: '#fff',
                        padding: 7,
                        cornerRadius: 8,
                        displayColors: false,
                    }
                },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: false }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });
    }

    // Initial load + refresh every 60 s (charts don't need 5-second cadence)
    loadHiveCharts();
    setInterval(loadHiveCharts, 60000);

    // ── Toast notification ─────────────────────────────────
    function showToast(msg, type = 'warning') {
        const t = document.createElement('div');
        const colors = {
            warning:  'background:linear-gradient(135deg,#F59E0B,#F97316)',
            critical: 'background:linear-gradient(135deg,#EF4444,#DC2626)',
            success:  'background:linear-gradient(135deg,#22C55E,#16A34A)',
        };
        t.style.cssText = `position:fixed;top:80px;right:24px;z-index:99999;${colors[type]||colors.warning};
            color:white;padding:12px 20px;border-radius:14px;font-size:13px;font-weight:600;
            box-shadow:0 8px 32px rgba(0,0,0,0.25);animation:slideInRight 0.3s ease;max-width:320px;`;
        t.innerHTML = `<i class="fas fa-${type==='critical'?'exclamation-circle':type==='success'?'check-circle':'exclamation-triangle'} mr-2"></i>${msg}`;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.4s'; setTimeout(()=>t.remove(),400); }, 4000);
    }

    // Track previous critical count to detect new ones
    let _prevCritCount = <?php echo $warnCount; ?>;
    // Override refresh to detect critical changes
    function refreshHiveCards() {
        fetch('api/live_hives.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                let newCrit = 0;
                data.hives.forEach(h => {
                    if (h.issues > 0) newCrit++;
                    const id = h.id;
                    if (!h.sensor) return;
                    const s = h.sensor;
                    const set = (elId, val) => { const el = document.getElementById(elId); if (el) el.textContent = val; };
                    set(`temp-${id}`, s.temperature + '°C');
                    set(`humid-${id}`, s.humidity + '%');
                    set(`co2-${id}`, s.co2 + 'ppm');
                    set(`time-${id}`, s.elapsed);
                    // Update modal sensor pills too
                    const tVal = parseFloat(s.temperature), tOk = tVal >= 33 && tVal <= 36;
                    const hVal = parseFloat(s.humidity),    hOk = hVal <= 75;
                    const cVal = parseInt(s.co2),            cOk = cVal <= 2000;
                    set(`modal-temp-${id}`,   s.temperature + '°C');
                    set(`modal-humid-${id}`,  s.humidity + '%');
                    set(`modal-co2-${id}`,    s.co2 + ' ppm');
                    set(`modal-temp-status-${id}`,  tOk ? '✓ Optimal (33–36°C)' : '⚠ Check ventilation');
                    set(`modal-humid-status-${id}`, hOk ? '✓ Normal range'       : '⚠ Too humid');
                    set(`modal-co2-status-${id}`,   cOk ? '✓ Good (<2000)'       : '⚠ Elevated');
                    const pillT = document.getElementById(`modal-pill-temp-${id}`);
                    if (pillT) pillT.style.background = `linear-gradient(135deg,${tOk?'#F97316,#EA580C':'#EF4444,#DC2626'})`;
                    const pillH = document.getElementById(`modal-pill-humid-${id}`);
                    if (pillH) pillH.style.background = `linear-gradient(135deg,${hOk?'#3B82F6,#2563EB':'#F59E0B,#D97706'})`;
                    const pillC = document.getElementById(`modal-pill-co2-${id}`);
                    if (pillC) pillC.style.background = `linear-gradient(135deg,${cOk?'#8B5CF6,#7C3AED':'#EF4444,#DC2626'})`;
                    const soundPct = h.sound ?? 0;
                    set(`sound-val-${id}`, soundPct + '%');
                    const bar = document.getElementById(`sound-bar-${id}`);
                    if (bar) bar.style.width = soundPct + '%';
                    // Update colony conclusion badge
                    const conclusionEl = document.getElementById(`colony-conclusion-${id}`);
                    const conclusionIcon = document.getElementById(`colony-icon-${id}`);
                    const conclusionText = document.getElementById(`colony-text-${id}`);
                    if (conclusionEl) {
                        const conclusionMap = {
                            'normal':    ['🟢', 'Colony Normal',     '#16a34a', '#dcfce7'],
                            'swarming':  ['🟠', 'Swarming Detected', '#c2410c', '#ffedd5'],
                            'queenless': ['🔴', 'Queenless Detected','#dc2626', '#fee2e2'],
                        };
                        if (h.badge) {
                            const raw = h.badge.raw_status || 'normal';
                            const c = conclusionMap[raw] || ['⚪', h.badge.text, '#6b7280', '#f3f4f6'];
                            conclusionEl.style.background = c[3];
                            conclusionEl.style.color = c[2];
                            if (conclusionIcon) conclusionIcon.textContent = c[0];
                            if (conclusionText) conclusionText.textContent = c[1];
                        }
                    }
                    const badgeEl = document.getElementById(`badge-${id}`);
                    const badgeIcon = document.getElementById(`badge-icon-${id}`);
                    const badgeText = document.getElementById(`badge-text-${id}`);
                    if (badgeEl) {
                        if (h.badge) {
                            badgeEl.className = `inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold ${h.badge.bg} ${h.badge.color}`;
                            badgeEl.style.background = h.badge.bg;
                            badgeEl.style.color = h.badge.color;
                            badgeEl.classList.remove('hidden');
                            if (badgeIcon) badgeIcon.className = `${h.badge.icon} text-xs`;
                            if (badgeText) badgeText.textContent = h.badge.text;
                        } else {
                            badgeEl.classList.add('hidden');
                        }
                    }
                    const issuesEl = document.getElementById(`issues-${id}`);
                    const issuesCount = document.getElementById(`issues-count-${id}`);
                    if (issuesEl) {
                        if (h.issues > 0) { issuesEl.classList.remove('hidden'); if (issuesCount) issuesCount.textContent = h.issues; }
                        else issuesEl.classList.add('hidden');
                    }
                    // Update online/offline badge
                    const onlineBadge = document.getElementById(`online-badge-${id}`);
                    const onlineBadgeText = document.getElementById(`online-badge-text-${id}`);
                    if (onlineBadge) {
                        const isOnline = h.sensor && h.is_online;
                        onlineBadge.style.background = isOnline ? '#dcfce7' : '#fee2e2';
                        onlineBadge.style.color = isOnline ? '#16a34a' : '#dc2626';
                        const dot = onlineBadge.querySelector('span');
                        if (dot) dot.style.background = isOnline ? '#16a34a' : '#dc2626';
                        if (onlineBadgeText) onlineBadgeText.textContent = isOnline ? 'Online' : 'Offline';
                    }
                });
                if (newCrit > _prevCritCount) {
                    showToast(`⚠ ${newCrit - _prevCritCount} new hive issue(s) detected!`, 'critical');
                }
                _prevCritCount = newCrit;
            })
            .catch(() => {});
    }
    // ── Camera start / stop ──────────────────────────────────
    function startCamera(e, hiveId) {
        e.stopPropagation();
        const btn = document.getElementById('btn-start-cam');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…'; }
        fetch('api/camera_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=start&hive_id=' + (hiveId || 1)
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start Camera'; }
                showToast('Failed to start camera: ' + (d.message || ''), 'critical');
                return;
            }
            // Keep spinner, retry stream every 2s for up to 15s
            let tries = 0;
            function tryConnect() {
                connectCameraImgs();
                tries++;
                if (!_camOnline && tries < 8) {
                    setTimeout(tryConnect, 2000);
                } else {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start Camera'; }
                    if (!_camOnline) showToast('Camera did not respond. Make sure camera_capture.py can run.', 'critical');
                }
            }
            showToast('Camera starting…', 'info');
            setTimeout(tryConnect, 2000);
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start Camera'; }
            showToast('Could not reach server to start camera.', 'critical');
        });
    }

    function stopCamera(e) {
        e.stopPropagation();
        const btn = document.getElementById('btn-stop-cam');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        fetch('api/camera_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=stop'
        })
        .then(r => r.json())
        .then(d => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stop"></i> Stop'; }
            if (d.success) {
                showToast('Camera stopped.', 'info');
                setCamOnline(false);
            } else {
                showToast('Failed to stop camera: ' + (d.message || ''), 'critical');
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stop"></i> Stop'; }
            showToast('Could not reach server to stop camera.', 'critical');
        });
    }

    // ── Camera expand / minimize ─────────────────────────────
    function expandCam(hiveId) {
        const img = document.getElementById('modal-cam-' + hiveId);
        if (!img || getComputedStyle(img).display === 'none') return; // offline — don't open
        const lb    = document.getElementById('camLightbox');
        const lbImg = document.getElementById('camLightboxImg');
        lbImg.src   = img.src;
        const ts    = document.getElementById('modal-cam-time-' + hiveId);
        document.getElementById('camLightboxTime').textContent = ts ? ts.textContent : '';
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeCamLightbox() {
        document.getElementById('camLightbox').style.display = 'none';
        document.body.style.overflow = '';
    }

    // Close lightbox on Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeCamLightbox();
    });
    </script>

    <!-- ── Camera Fullscreen Lightbox ────────────────────────── -->
    <div id="camLightbox"
         onclick="if(event.target===this)closeCamLightbox()"
         style="display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,0.92);
                backdrop-filter:blur(8px);flex-direction:column;align-items:center;justify-content:center;">
        <!-- Top bar -->
        <div style="width:100%;max-width:1200px;display:flex;align-items:center;justify-content:space-between;padding:12px 20px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <i class="fas fa-circle" style="color:#4ADE80;font-size:8px;"></i>
                <span style="color:white;font-size:13px;font-weight:700;">LIVE — Hive Entrance Camera</span>
                <span id="camLightboxTime" style="color:rgba(255,255,255,0.5);font-size:12px;margin-left:8px;"></span>
            </div>
            <button onclick="closeCamLightbox()"
                    style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.12);
                           border:none;color:white;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.25)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                <i class="fas fa-compress-alt"></i>
            </button>
        </div>
        <!-- Full image -->
        <img id="camLightboxImg" alt="Camera fullscreen"
             style="max-width:92vw;max-height:74vh;object-fit:contain;border-radius:14px;box-shadow:0 32px 80px rgba(0,0,0,0.6);">

        <!-- Detection stats in lightbox -->
        <div id="camLightboxStats"
             style="margin-top:12px;display:flex;align-items:center;gap:0;background:rgba(255,255,255,0.06);
                    border-radius:12px;padding:8px 20px;min-width:260px;justify-content:center;">
            <span style="color:#94A3B8;font-size:12px;">No detection data yet</span>
        </div>

        <p style="color:rgba(255,255,255,0.3);font-size:11px;margin-top:10px;">
            Click outside or press <kbd style="background:rgba(255,255,255,0.12);padding:1px 6px;border-radius:4px;color:rgba(255,255,255,0.5);">Esc</kbd> to minimize
        </p>
    </div>
</body>
</html>

