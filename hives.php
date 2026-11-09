<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'get_recommendations.php';
checkAuth();
$hives = getAllHives($conn);
$totalHives = count($hives);
$normalCount = 0; $warnCount = 0; $noDataCount = 0;
$isAdmin   = isAdmin();
$canCheck  = in_array($_SESSION['role'] ?? '', ['admin', 'beekeeper']);

// Returns [bg, labelColor, valueColor] for a sensor tile based on thresholds
function sensorTileStyle($type, $value) {
    if ($value === null) return ['#F9FAFB', '#9CA3AF', '#6B7280'];
    $v = (float)$value;
    $green  = ['#ECFDF5', '#16A34A', '#15803D'];
    $orange = ['#FFF7ED', '#F97316', '#EA580C'];
    $red    = ['#FEF2F2', '#EF4444', '#DC2626'];
    switch ($type) {
        case 'temp':
            if ($v >= 33 && $v <= 36) return $green;
            if ($v > 38 || $v < 18)  return $red;
            return $orange;
        case 'humidity':
            if ($v >= 40 && $v <= 95) return $green;
            if ($v > 98 || $v < 30)  return $red;
            return $orange;
        case 'co2':
            if ($v <= 4000) return $green;
            if ($v > 6000)  return $red;
            return $orange;
    }
    return ['#F9FAFB', '#9CA3AF', '#6B7280'];
}

// Pre-calculate stats before HTML renders
foreach ($hives as $_hive) {
    $_sensor = getLatestSensorData($conn, $_hive['id']);
    if (!$_sensor) { $noDataCount++; continue; }
    // Use freq-based colony status directly from sensor_data
    $_colonyStatus = classifyColonyByFreq($_sensor['dominant_freq'] ?? null);
    $_audio = ['colony_status' => $_colonyStatus, 'confidence' => 100];
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
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
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
                        // Classify colony status from live dominant_freq — instant, no CNN needed
                        $colonyStatus = classifyColonyByFreq($sensor['dominant_freq'] ?? null);
                        $audio = ['colony_status' => $colonyStatus, 'confidence' => 100,
                                  'dominant_freq' => $sensor['dominant_freq'] ?? 0];
                        $badge = getHiveStatusBadge($colonyStatus);
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
                                <?php
                                    [$tBg,$tLc,$tVc] = sensorTileStyle('temp',     $sensor['temperature']);
                                    [$hBg,$hLc,$hVc] = sensorTileStyle('humidity', $sensor['humidity']);
                                    [$cBg,$cLc,$cVc] = sensorTileStyle('co2',      $sensor['co2']);
                                ?>
                                <div class="grid grid-cols-3 gap-2 mb-3">
                                    <!-- Temperature -->
                                    <div class="rounded-xl p-2.5" id="tile-temp-<?php echo $hive['id']; ?>" style="background:<?php echo $tBg; ?>;">
                                        <p class="text-xs font-bold mb-0.5" id="tile-label-temp-<?php echo $hive['id']; ?>" style="color:<?php echo $tLc; ?>;">Temp</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:<?php echo $tVc; ?>;" id="temp-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['temperature'], 1); ?>°C</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-temp-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <!-- Humidity -->
                                    <div class="rounded-xl p-2.5" id="tile-humidity-<?php echo $hive['id']; ?>" style="background:<?php echo $hBg; ?>;">
                                        <p class="text-xs font-bold mb-0.5" id="tile-label-humidity-<?php echo $hive['id']; ?>" style="color:<?php echo $hLc; ?>;">Humidity</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:<?php echo $hVc; ?>;" id="humid-<?php echo $hive['id']; ?>"><?php echo number_format($sensor['humidity'], 1); ?>%</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-humid-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                    <!-- CO₂ -->
                                    <div class="rounded-xl p-2.5" id="tile-co2-<?php echo $hive['id']; ?>" style="background:<?php echo $cBg; ?>;">
                                        <p class="text-xs font-bold mb-0.5" id="tile-label-co2-<?php echo $hive['id']; ?>" style="color:<?php echo $cLc; ?>;">CO₂</p>
                                        <p class="font-black leading-tight" style="font-size:15px;color:<?php echo $cVc; ?>;" id="co2-<?php echo $hive['id']; ?>"><?php echo $sensor['co2']; ?>ppm</p>
                                        <div style="height:42px;position:relative;margin-top:3px;">
                                            <canvas id="chart-co2-<?php echo $hive['id']; ?>"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg mb-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <p class="text-xs text-gray-600">Sound Activity</p>
                                        <p class="text-sm font-bold" id="sound-val-<?php echo $hive['id']; ?>"><?php echo $sensor ? round($sensor['sound_level'] ?? 0) : 0; ?>%</p>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden mb-2">
                                        <div class="h-full bg-yellow-500" id="sound-bar-<?php echo $hive['id']; ?>" style="width:<?php echo $sensor ? round($sensor['sound_level'] ?? 0) : 0; ?>%"></div>
                                    </div>
                                    <!-- Colony status — from live Hz, instant -->
                                    <?php
                                        $conclusionMap = [
                                            'normal'    => ['🟢', 'Colony Normal',     '#16a34a', '#dcfce7'],
                                            'swarming'  => ['🟠', 'Swarming Detected', '#c2410c', '#ffedd5'],
                                            'queenless' => ['🔴', 'Queenless Detected','#dc2626', '#fee2e2'],
                                        ];
                                        $c = $conclusionMap[$colonyStatus];
                                        $freqDisplay = $sensor['dominant_freq'] ? round($sensor['dominant_freq']) . ' Hz' : '— Hz';
                                    ?>
                                    <div id="colony-conclusion-<?php echo $hive['id']; ?>"
                                         class="flex items-center justify-between px-2 py-1.5 rounded-lg text-xs font-semibold"
                                         style="background:<?php echo $c[3]; ?>;color:<?php echo $c[2]; ?>;">
                                        <span class="flex items-center gap-1.5">
                                            <span id="colony-icon-<?php echo $hive['id']; ?>"><?php echo $c[0]; ?></span>
                                            <span id="colony-text-<?php echo $hive['id']; ?>"><?php echo $c[1]; ?></span>
                                        </span>
                                        <span id="colony-freq-<?php echo $hive['id']; ?>" class="opacity-60 font-normal"><?php echo $freqDisplay; ?></span>
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
                                    $hOk = $sensor['humidity'] >= 40 && $sensor['humidity'] <= 95;
                                    $cOk = $sensor['co2'] <= 4000;
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
                                                    id="btn-start-cam-<?php echo $hive['id']; ?>"
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
                                                    <button onclick="stopCamera(event, <?php echo $hive['id']; ?>)"
                                                            id="btn-stop-cam-<?php echo $hive['id']; ?>"
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

                                <!-- Audio Analysis -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <i class="fas fa-volume-up text-yellow-500"></i> Audio Analysis & Colony Status
                                </p>
                                <div id="modal-audio-box-<?php echo $hive['id']; ?>"
                                     class="rounded-xl p-3 mb-4 flex gap-4"
                                     style="background:#FFFBEB;border:1px solid #FDE68A;">
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2 font-semibold">Colony Status</p>
                                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-sm font-bold"
                                              style="background:white;border:1.5px solid #FDE68A;color:#92400E;">
                                            <i class="<?php echo $badge['icon']; ?> text-yellow-600"></i>
                                            <span id="modal-colony-label-<?php echo $hive['id']; ?>"><?php echo $badge['text']; ?></span>
                                        </span>
                                        <p class="text-xs text-gray-500 mt-2">
                                            <i class="fas fa-wave-square text-yellow-500 mr-1"></i>
                                            Dominant: <strong id="modal-colony-freq-<?php echo $hive['id']; ?>" class="text-gray-700"><?php echo $sensor['dominant_freq'] ? round($sensor['dominant_freq']) . ' Hz' : '— Hz'; ?></strong>
                                        </p>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-500 mb-2 font-semibold">Sound Activity</p>
                                        <div class="flex items-center gap-3 mb-1">
                                            <div class="flex-1 h-3 bg-yellow-100 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full" style="width:<?php echo $sensor ? round($sensor['sound_level'] ?? 0) : 0; ?>%;background:linear-gradient(90deg,#F59E0B,#F97316);"></div>
                                            </div>
                                            <span class="text-xl font-black text-yellow-600"><?php echo $sensor ? round($sensor['sound_level'] ?? 0) : 0; ?>%</span>
                                        </div>
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-ruler-horizontal text-yellow-500 mr-1"></i>
                                            Normal &lt;450 Hz · Queenless 450–600 · Swarming &gt;600
                                        </p>
                                    </div>
                                </div>

                                <!-- Recommendations -->
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                    <i class="fas fa-lightbulb text-yellow-500"></i> Smart Recommendations
                                </p>
                                <div style="display:flex;flex-direction:column;gap:10px;">

                                    <!-- Insect detection recommendation (shown live when camera detects insects) -->
                                    <div id="modal-insect-rec-<?php echo $hive['id']; ?>"
                                         class="rec-item"
                                         style="display:none;background:#FFF1F2;border-left-color:#F87171;">
                                        <div style="display:flex;align-items:flex-start;gap:12px;">
                                            <div style="width:36px;height:36px;border-radius:10px;background:white;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                                <i class="fas fa-bug" style="color:#EF4444;"></i>
                                            </div>
                                            <div style="flex:1;">
                                                <p style="font-weight:700;color:#EF4444;font-size:14px;margin-bottom:2px;">
                                                    Non-Bee Insect Detected at Entrance
                                                </p>
                                                <p style="font-size:13px;color:#6B7280;margin-bottom:8px;" id="modal-insect-rec-msg-<?php echo $hive['id']; ?>">
                                                    Insects detected at hive entrance by camera.
                                                </p>
                                                <div style="display:flex;flex-direction:column;gap:4px;">
                                                    <p style="font-size:12px;color:#374151;display:flex;align-items:flex-start;gap:6px;">
                                                        <i class="fas fa-arrow-right" style="color:#EF4444;margin-top:3px;font-size:10px;"></i>
                                                        Inspect entrance for wasps, hornets, or robber bees
                                                    </p>
                                                    <p style="font-size:12px;color:#374151;display:flex;align-items:flex-start;gap:6px;">
                                                        <i class="fas fa-arrow-right" style="color:#EF4444;margin-top:3px;font-size:10px;"></i>
                                                        Consider installing an entrance reducer to limit access
                                                    </p>
                                                    <p style="font-size:12px;color:#374151;display:flex;align-items:flex-start;gap:6px;">
                                                        <i class="fas fa-arrow-right" style="color:#EF4444;margin-top:3px;font-size:10px;"></i>
                                                        Check for robbing behavior — guard bees fighting at entrance
                                                    </p>
                                                    <p style="font-size:12px;color:#374151;display:flex;align-items:flex-start;gap:6px;">
                                                        <i class="fas fa-arrow-right" style="color:#EF4444;margin-top:3px;font-size:10px;"></i>
                                                        Remove any nearby food sources attracting insects
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
                                <!-- Footer -->
                                <?php
                                    $lastCheckedAt  = $hive['last_checked_at'] ?? null;
                                    $checkedElapsed = $lastCheckedAt ? time_elapsed_string($lastCheckedAt) : null;
                                    $checkerName = null;
                                    if ($lastCheckedAt && !empty($hive['last_checked_by'])) {
                                        $cr = $conn->query("SELECT username FROM users WHERE id = " . (int)$hive['last_checked_by']);
                                        if ($cr) { $crow = $cr->fetch_assoc(); $checkerName = $crow['username'] ?? null; }
                                    }
                                ?>
                                <div style="margin-top:14px;padding:10px 14px;background:#F8FAFC;border-radius:10px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                                    <div style="display:flex;flex-direction:column;gap:3px;">
                                        <p style="font-size:12px;color:#94A3B8;margin:0;">
                                            <i class="far fa-clock mr-1"></i>Last updated: <strong style="color:#64748B;"><?php echo time_elapsed_string($sensor['timestamp']); ?></strong>
                                        </p>
                                        <p style="font-size:12px;color:#94A3B8;margin:0;" id="modal-last-checked-<?php echo $hive['id']; ?>">
                                            <?php if ($checkedElapsed): ?>
                                            <i class="fas fa-check-circle mr-1" style="color:#22C55E;"></i>Last checked: <strong style="color:#64748B;"><?php echo $checkedElapsed; ?></strong>
                                            <?php if ($checkerName): ?><span style="color:#CBD5E1;"> by <?php echo htmlspecialchars($checkerName); ?></span><?php endif; ?>
                                            <?php else: ?>
                                            <i class="fas fa-circle mr-1" style="color:#CBD5E1;font-size:8px;"></i><span style="color:#CBD5E1;">Not yet checked</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                        <?php if ($canCheck): ?>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <span style="font-size:11px;color:#94A3B8;font-style:italic;white-space:nowrap;">Click if already checked the hive</span>
                                            <button id="btn-check-<?php echo $hive['id']; ?>"
                                                    onclick="markChecked(<?php echo $hive['id']; ?>)"
                                                    style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;color:#fff;background:linear-gradient(135deg,#22C55E,#16A34A);border:none;cursor:pointer;padding:5px 12px;border-radius:8px;transition:opacity 0.2s;box-shadow:0 2px 8px rgba(34,197,94,0.25);"
                                                    onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                                                <i class="fas fa-check"></i> Mark as Checked
                                            </button>
                                        </div>
                                        <?php endif; ?>
                                        <button onclick="closeModal(<?php echo $hive['id']; ?>)"
                                                style="font-size:13px;font-weight:600;color:#F59E0B;background:none;border:none;cursor:pointer;padding:4px 8px;border-radius:8px;transition:background 0.2s;"
                                                onmouseover="this.style.background='#FEF3C7'" onmouseout="this.style.background='none'">
                                            Close <i class="fas fa-times ml-1"></i>
                                        </button>
                                    </div>
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
            connectCameraImgs(hiveId);
        }

        function closeModal(hiveId) {
            const modal = document.getElementById('modal-' + hiveId);
            if (!modal) return;
            // Disconnect only this hive's camera
            clearTimeout(_retryTimer[hiveId]);
            const img = document.getElementById('modal-cam-' + hiveId);
            if (img) { img.onload = null; img.onerror = null; img.src = ''; }
            setCamOnline(hiveId, false);
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
    // ── MJPEG Camera — per-hive streams ─────────────────────
    <?php
    $serverAddr = ($_SERVER['SERVER_ADDR'] === '::1') ? '127.0.0.1' : $_SERVER['SERVER_ADDR'];
    $streamPorts = [];
    $cameraUrls  = [];
    foreach ($hives as $h) {
        if (isset($h['stream_port']) && $h['stream_port']) {
            $port = (int)$h['stream_port'];
        } else {
            $camIdx = isset($h['camera_index']) ? (int)$h['camera_index'] : 1;
            $port = 5049 + $camIdx;
        }
        $streamPorts[$h['id']] = $port;
        if (!empty($h['camera_url'])) {
            $cameraUrls[$h['id']] = rtrim($h['camera_url'], '/');
        }
    }
    ?>
    const _SERVER_ADDR = '<?php echo $serverAddr; ?>';
    const STREAM_PORTS = <?php echo json_encode($streamPorts); ?>;
    const CAMERA_URLS  = <?php echo json_encode($cameraUrls); ?>;
    function streamUrl(hiveId)  {
        if (CAMERA_URLS[hiveId]) return CAMERA_URLS[hiveId] + '/stream';
        return 'http://' + _SERVER_ADDR + ':' + (STREAM_PORTS[hiveId] ?? 5050) + '/stream';
    }
    function metaUrl(hiveId)    {
        if (CAMERA_URLS[hiveId]) return CAMERA_URLS[hiveId] + '/meta';
        return 'http://' + _SERVER_ADDR + ':' + (STREAM_PORTS[hiveId] ?? 5050) + '/meta';
    }

    const _camOnline  = {};  // {hiveId: bool}
    const _retryTimer = {};  // {hiveId: timeoutId}

    function setCamOnline(hiveId, online) {
        if (_camOnline[hiveId] === online) return;
        _camOnline[hiveId] = online;
        const img  = document.getElementById('modal-cam-'         + hiveId);
        const off  = document.getElementById('modal-cam-offline-' + hiveId);
        const ov   = document.getElementById('modal-cam-overlay-' + hiveId);
        const hint = document.getElementById('modal-cam-hint-'    + hiveId);
        const tm   = document.getElementById('modal-cam-time-'    + hiveId);
        if (!img) return;
        if (online) {
            img.style.display = 'block';
            if (off)  off.style.display  = 'none';
            if (ov)   ov.classList.remove('hidden');
            if (hint) hint.classList.remove('hidden');
            if (tm)   tm.textContent = new Date().toLocaleTimeString();
        } else {
            img.style.display = 'none';
            if (off)  off.style.display = '';
            if (ov)   ov.classList.add('hidden');
            if (hint) hint.classList.add('hidden');
        }
        const lbImg = document.getElementById('camLightboxImg');
        if (lbImg && online) lbImg.src = streamUrl(hiveId);
    }

    function connectCameraImgs(hiveId) {
        clearTimeout(_retryTimer[hiveId]);
        const img = document.getElementById('modal-cam-' + hiveId);
        if (!img) return;
        img.onload = function() { setCamOnline(hiveId, true); };
        img.onerror = function() {
            setCamOnline(hiveId, false);
            img.src = '';
            _retryTimer[hiveId] = setTimeout(() => connectCameraImgs(hiveId), 3000);
        };
        img.src = streamUrl(hiveId) + '?t=' + Date.now();
    }
    function fetchWithTimeout(url, ms) {
        const ctrl = new AbortController();
        const tid  = setTimeout(() => ctrl.abort(), ms);
        return fetch(url, { signal: ctrl.signal }).finally(() => clearTimeout(tid));
    }

    // ── Detection stats poller — per hive ────────────────────
    function refreshDetectionStats() {
        Object.keys(STREAM_PORTS).forEach(hiveId => {
            fetchWithTimeout(metaUrl(hiveId), 2000)
                .then(r => r.json())
                .then(data => {
                    const bar      = document.getElementById('modal-cam-stats-'   + hiveId);
                    const beeEl    = document.getElementById('modal-bee-count-'   + hiveId);
                    const insectEl = document.getElementById('modal-insect-count-'+ hiveId);
                    const alertEl  = document.getElementById('modal-insect-alert-'+ hiveId);
                    if (!bar) return;

                    if (!data.online) {
                        bar.style.display = 'none';
                        const recOff = document.getElementById('modal-insect-rec-' + hiveId);
                        if (recOff) recOff.style.display = 'none';
                        return;
                    }

                    bar.style.display = 'flex';
                    if (beeEl)    beeEl.textContent   = data.bee_count    ?? 0;
                    if (insectEl) insectEl.textContent = data.insect_count ?? 0;

                    const hasInsects = (data.insect_count ?? 0) > 0;
                    if (alertEl) alertEl.style.display = hasInsects ? 'flex' : 'none';

                    const insectRec    = document.getElementById('modal-insect-rec-'     + hiveId);
                    const insectRecMsg = document.getElementById('modal-insect-rec-msg-' + hiveId);
                    if (insectRec) {
                        insectRec.style.display = hasInsects ? '' : 'none';
                        if (hasInsects && insectRecMsg) {
                            const n = data.insect_count ?? 0;
                            insectRecMsg.textContent = n + ' non-bee insect' + (n > 1 ? 's' : '') +
                                ' detected at hive entrance by camera. Immediate inspection recommended.';
                        }
                    }

                    if (data.online) updateLightboxStats(data.bee_count ?? 0, data.insect_count ?? 0, data.timestamp ?? '');
                })
                .catch(() => {});
        });
    }
    refreshDetectionStats();
    setInterval(refreshDetectionStats, 5000);

    // Also update lightbox stats bar
    function updateLightboxStats(beeCount, insectCount, ts) {
        const bar = document.getElementById('camLightboxStats');
        if (!bar) return;
        bar.innerHTML =
            `<span style="color:#4ADE80;font-weight:700;font-size:12px;">🟢 Bees: ${beeCount}</span>` +
            `<span style="color:#F87171;font-weight:700;font-size:12px;margin-left:16px;">🔴 Insects: ${insectCount}</span>` +
            (insectCount > 0 ? `<span style="color:#FCA5A5;font-size:11px;margin-left:12px;"><i class="fas fa-exclamation-triangle"></i> Alert!</span>` : '');
    }

    // Returns [bg, labelColor, valueColor] for a sensor type + value
    function sensorTileColors(type, value) {
        const green  = ['#ECFDF5', '#16A34A', '#15803D'];
        const orange = ['#FFF7ED', '#F97316', '#EA580C'];
        const red    = ['#FEF2F2', '#EF4444', '#DC2626'];
        const grey   = ['#F9FAFB', '#9CA3AF', '#6B7280'];
        if (value === null || isNaN(value)) return grey;
        const v = parseFloat(value);
        if (type === 'temp') {
            if (v >= 33 && v <= 36) return green;
            if (v > 38 || v < 18)  return red;
            return orange;
        }
        if (type === 'humidity') {
            if (v >= 40 && v <= 95) return green;
            if (v > 98 || v < 30)  return red;
            return orange;
        }
        if (type === 'co2') {
            if (v <= 4000) return green;
            if (v > 6000)  return red;
            return orange;
        }
        return grey;
    }

    function applyTileColor(type, value, hiveId) {
        const [bg, lc, vc] = sensorTileColors(type, value);
        const tile  = document.getElementById(`tile-${type}-${hiveId}`);
        const label = document.getElementById(`tile-label-${type}-${hiveId}`);
        const val   = document.getElementById(type === 'humidity' ? `humid-${hiveId}` : `${type}-${hiveId}`);
        if (tile)  tile.style.background = bg;
        if (label) label.style.color = lc;
        if (val)   val.style.color = vc;
    }

    setInterval(refreshHiveCards, 15000);
    // Tell header.php's doReload() to use JS refresh instead of location.reload()
    // (header already guards against reload when .modal.active is open)
    window.__refreshPage = function() { refreshHiveCards(); };

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
                    const tVal = parseFloat(s.temperature);
                    const hVal = parseFloat(s.humidity);
                    const cVal = parseInt(s.co2);
                    const tOk  = tVal >= 33 && tVal <= 36;
                    const hOk  = hVal >= 40 && hVal <= 95;
                    const cOk  = cVal <= 4000;
                    // Update card tiles with threshold colors
                    applyTileColor('temp',     tVal, id);
                    applyTileColor('humidity', hVal, id);
                    applyTileColor('co2',      cVal, id);
                    set(`modal-temp-${id}`,   s.temperature + '°C');
                    set(`modal-humid-${id}`,  s.humidity + '%');
                    set(`modal-co2-${id}`,    s.co2 + ' ppm');
                    set(`modal-temp-status-${id}`,  tOk ? '✓ Optimal (33–36°C)' : (tVal > 38 || tVal < 18 ? '✗ Critical' : '⚠ Off range'));
                    set(`modal-humid-status-${id}`, hOk ? '✓ Normal (40–95%)'   : (hVal > 98 || hVal < 30 ? '✗ Critical' : '⚠ Off range'));
                    set(`modal-co2-status-${id}`,   cOk ? '✓ Good (≤4000 ppm)'  : (cVal > 6000            ? '✗ Critical' : '⚠ Elevated'));
                    const pillT = document.getElementById(`modal-pill-temp-${id}`);
                    if (pillT) pillT.style.background = `linear-gradient(135deg,${tOk?'#F97316,#EA580C':(tVal > 38||tVal < 18?'#EF4444,#DC2626':'#F97316,#EA580C')})`;
                    const pillH = document.getElementById(`modal-pill-humid-${id}`);
                    if (pillH) pillH.style.background = `linear-gradient(135deg,${hOk?'#3B82F6,#2563EB':(hVal > 98||hVal < 30?'#EF4444,#DC2626':'#F59E0B,#D97706')})`;
                    const pillC = document.getElementById(`modal-pill-co2-${id}`);
                    if (pillC) pillC.style.background = `linear-gradient(135deg,${cOk?'#8B5CF6,#7C3AED':(cVal > 6000?'#EF4444,#DC2626':'#F59E0B,#D97706')})`;
                    const soundPct = h.sound ?? 0;
                    set(`sound-val-${id}`, soundPct + '%');
                    const bar = document.getElementById(`sound-bar-${id}`);
                    if (bar) bar.style.width = soundPct + '%';
                    // Update colony status from live Hz
                    if (h.dominant_freq !== undefined) updateColonyDisplay(id, h.dominant_freq);
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
                            badgeEl.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold';
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
    // ── Camera start / stop — per hive ──────────────────────
    function startCamera(e, hiveId) {
        e.stopPropagation();
        const btn = document.getElementById('btn-start-cam-' + hiveId);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting…'; }
        fetch('api/camera_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=start&hive_id=' + hiveId
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start Camera'; }
                showToast('Failed to start camera: ' + (d.message || ''), 'critical');
                return;
            }
            let tries = 0;
            function tryConnect() {
                connectCameraImgs(hiveId);
                tries++;
                if (!_camOnline[hiveId] && tries < 8) {
                    setTimeout(tryConnect, 2000);
                } else {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-play"></i> Start Camera'; }
                    if (!_camOnline[hiveId]) showToast('Camera did not respond. Make sure camera_capture.py can run.', 'critical');
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

    function stopCamera(e, hiveId) {
        e.stopPropagation();
        const btn = document.getElementById('btn-stop-cam-' + hiveId);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }
        fetch('api/camera_control.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=stop&hive_id=' + hiveId
        })
        .then(r => r.json())
        .then(d => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stop"></i> Stop'; }
            if (d.success) {
                showToast('Camera stopped.', 'info');
                setCamOnline(hiveId, false);
            } else {
                showToast('Failed to stop camera: ' + (d.message || ''), 'critical');
            }
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-stop"></i> Stop'; }
            showToast('Could not reach server to stop camera.', 'critical');
        });
    }

    // ── Mark hive as physically checked ─────────────────────────
    function markChecked(hiveId) {
        const btn = document.getElementById('btn-check-' + hiveId);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…'; }
        fetch('api/check_hive.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'hive_id=' + hiveId
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showToast('Failed to save check: ' + (d.message || ''), 'critical');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Mark as Checked'; }
                return;
            }
            const el = document.getElementById('modal-last-checked-' + hiveId);
            if (el) {
                el.innerHTML = '<i class="fas fa-check-circle mr-1" style="color:#22C55E;"></i>'
                    + 'Last checked: <strong style="color:#64748B;">' + d.elapsed + '</strong>'
                    + ' <span style="color:#CBD5E1;">by you</span>';
            }
            if (btn) {
                btn.disabled = false;
                btn.style.background = 'linear-gradient(135deg,#6B7280,#4B5563)';
                btn.innerHTML = '<i class="fas fa-check-double"></i> Checked ✓';
                setTimeout(() => {
                    btn.style.background = 'linear-gradient(135deg,#22C55E,#16A34A)';
                    btn.innerHTML = '<i class="fas fa-check"></i> Mark as Checked';
                }, 3000);
            }
            showToast('Hive marked as checked!', 'success');
        })
        .catch(() => {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Mark as Checked'; }
            showToast('Could not reach server.', 'critical');
        });
    }

    // Colony status is now derived live from dominant_freq — no CNN/upload needed
    const colonyStatusMap = {
        'normal':    ['🟢', 'Colony Normal',     '#16a34a', '#dcfce7'],
        'swarming':  ['🟠', 'Swarming Detected', '#c2410c', '#ffedd5'],
        'queenless': ['🔴', 'Queenless Detected','#dc2626', '#fee2e2'],
    };

    function classifyByFreq(hz) {
        if (!hz || hz <= 0) return 'normal';
        if (hz > 600) return 'swarming';
        if (hz > 450) return 'queenless';
        return 'normal';
    }

    function updateColonyDisplay(hiveId, hz) {
        const status = classifyByFreq(hz);
        const c = colonyStatusMap[status] || colonyStatusMap['normal'];
        const freqTxt = hz ? Math.round(hz) + ' Hz' : '— Hz';
        // Card badge
        const conc = document.getElementById('colony-conclusion-' + hiveId);
        const icon = document.getElementById('colony-icon-' + hiveId);
        const text = document.getElementById('colony-text-' + hiveId);
        const freq = document.getElementById('colony-freq-' + hiveId);
        if (conc) { conc.style.background = c[3]; conc.style.color = c[2]; }
        if (icon) icon.textContent = c[0];
        if (text) text.textContent = c[1];
        if (freq) freq.textContent = freqTxt;
        // Modal
        const mLabel = document.getElementById('modal-colony-label-' + hiveId);
        const mFreq  = document.getElementById('modal-colony-freq-'  + hiveId);
        if (mLabel) mLabel.textContent = c[1];
        if (mFreq)  mFreq.textContent  = freqTxt;
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

