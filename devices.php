<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

// Auto-mark devices offline if no sync in last 5 minutes
$conn->query(
    "UPDATE edge_devices
     SET status = 'offline'
     WHERE last_sync < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
       AND status != 'offline'"
);

$devices = getEdgeDevices($conn);
$onlineCount = count(array_filter($devices, fn($d) => $d['status'] == 'online'));
$warningCount = count(array_filter($devices, fn($d) => $d['status'] == 'warning'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edge Devices - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-8 py-5 pl-10">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                      style="background:linear-gradient(135deg,#06B6D4,#0891B2);box-shadow:0 4px 14px rgba(6,182,212,0.4);">
                    <i class="fas fa-microchip text-white text-base"></i>
                </span>
                Edge Devices
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Monitor and manage IoT sensors and edge computing devices</p>
        </div>
        
        <div class="p-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">
                <div class="stat-card rounded-2xl p-5 text-white relative overflow-hidden"
                     style="background:linear-gradient(135deg,#06B6D4 0%,#0891B2 100%);box-shadow:0 8px 32px rgba(6,182,212,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Total Devices</p>
                            <h2 class="text-4xl font-black" id="statTotal">—</h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-microchip text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white relative overflow-hidden"
                     style="background:linear-gradient(135deg,#10B981 0%,#059669 100%);box-shadow:0 8px 32px rgba(16,185,129,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Online</p>
                            <h2 class="text-4xl font-black" id="statOnline">—</h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-wifi text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white relative overflow-hidden"
                     style="background:linear-gradient(135deg,#F59E0B 0%,#F97316 100%);box-shadow:0 8px 32px rgba(245,158,11,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Warnings</p>
                            <h2 class="text-4xl font-black" id="statWarning">—</h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center mb-4">
                <p class="text-xs text-gray-500">
                    <i class="fas fa-sync-alt mr-1 text-green-500"></i>
                    Auto-refreshing every 10s &bull; <span id="lastChecked">checking...</span>
                </p>
                <span id="liveDot" class="flex items-center gap-2 text-xs font-semibold text-green-600">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> LIVE
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="deviceGrid">
                <div class="col-span-2 py-12 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                    <p>Loading devices...</p>
                </div>
            </div>

            <?php if (isAdmin()): ?>
            <!-- Beehive Management -->
            <div class="bg-white rounded-xl p-6 mt-8 border-2 border-green-200">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-bold text-lg flex items-center gap-2">
                        <i class="fas fa-house text-green-600"></i>
                        Beehive Management
                    </h3>
                    <button onclick="openModal('addHiveModal')"
                        class="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg transition">
                        <i class="fas fa-plus"></i> Add New Hive
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-6">Manage your registered beehives and assign ESP32 devices.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Current Hives -->
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                            <i class="fas fa-list text-green-600"></i> Registered Hives
                        </p>
                        <div id="hiveList" class="space-y-2 min-h-32 max-h-64 overflow-y-auto border-2 border-green-100 rounded-xl p-3 bg-green-50">
                            <p class="text-sm text-gray-400 text-center mt-6">Loading hives...</p>
                        </div>
                    </div>

                    <!-- Detected ESP32 Devices -->
                    <div>
                        <div class="flex justify-between items-center mb-3">
                            <p class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                <i class="fas fa-microchip text-yellow-600"></i> Detected New ESP32 Devices
                            </p>
                            <button onclick="loadDetectedDevices()"
                                class="text-xs text-yellow-600 hover:text-yellow-800 font-semibold flex items-center gap-1">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div id="detectedDevices" class="space-y-2 min-h-32 max-h-64 overflow-y-auto border-2 border-yellow-100 rounded-xl p-3 bg-yellow-50">
                            <p class="text-sm text-gray-400 text-center mt-6">Scanning for devices...</p>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            ESP32 devices that send data but are not yet assigned to a hive appear here.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    <?php if (isAdmin()): ?>
    <!-- Add Hive Modal -->
    <div id="addHiveModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-house text-green-600"></i> Add New Hive
                </h3>
                <button onclick="closeHiveModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="addHiveMsg" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Hive Name <span class="text-red-500">*</span></label>
                <input id="newHiveName" type="text" placeholder="e.g. Hive Alpha"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-green-400">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Hive ID Code <span class="text-red-500">*</span></label>
                <input id="newHiveCode" type="text" placeholder="e.g. HIVE-001"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-green-400"
                    oninput="this.value = this.value.toUpperCase()">
                <p class="text-xs text-gray-500 mt-1">Unique identifier for this hive</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Location</label>
                <input id="newHiveLocation" type="text" placeholder="e.g. North Garden"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-green-400">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Assign ESP32 Device</label>
                <input id="newHiveDevice" type="text" placeholder="e.g. ESP32-001 (optional)"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-green-400">
                <p class="text-xs text-gray-500 mt-1">Device ID from detected devices list or firmware config.h</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeHiveModal()" class="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                <button onclick="saveNewHive()" class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold text-sm transition">
                    <i class="fas fa-plus mr-1"></i> Add Hive
                </button>
            </div>
        </div>
    </div>

    <!-- Register Detected Device Modal -->
    <div id="registerDeviceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl w-full max-w-md p-8 shadow-xl">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-microchip text-yellow-600"></i> Register ESP32 Device
                </h3>
                <button onclick="closeModal('registerDeviceModal')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="registerDeviceMsg" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            <p class="text-sm text-gray-600 mb-4">
                Detected device: <strong id="registerDeviceId" class="text-yellow-700"></strong>
            </p>
            <input type="hidden" id="registerDeviceIdHidden">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Hive Name <span class="text-red-500">*</span></label>
                <input id="regHiveName" type="text" placeholder="e.g. Hive Beta"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Hive ID Code <span class="text-red-500">*</span></label>
                <input id="regHiveCode" type="text" placeholder="e.g. HIVE-002"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400"
                    oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Location</label>
                <input id="regHiveLocation" type="text" placeholder="e.g. South Field"
                    class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal('registerDeviceModal')" class="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                <button onclick="saveRegisteredDevice()" class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold text-sm transition">
                    <i class="fas fa-check mr-1"></i> Register & Create Hive
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </main>

    <script>
        function fetchDevices() {
            fetch('api/device_status.php')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;

                    document.getElementById('statTotal').textContent   = data.total;
                    document.getElementById('statOnline').textContent  = data.online;
                    document.getElementById('statWarning').textContent = data.warning;
                    document.getElementById('lastChecked').textContent = 'Last checked: ' + data.checked_at;

                    const grid = document.getElementById('deviceGrid');
                    if (!data.devices.length) {
                        grid.innerHTML = `<div class="col-span-2 py-12 text-center text-gray-400">
                            <i class="fas fa-microchip text-3xl mb-3"></i>
                            <p>No devices registered yet.</p>
                        </div>`;
                        return;
                    }

                    grid.innerHTML = data.devices.map(d => {
                        const isOnline  = d.status === 'online';
                        const isWarning = d.status === 'warning';

                        const statusBg   = isOnline ? 'bg-green-100 text-green-800'
                                         : isWarning ? 'bg-yellow-100 text-yellow-800'
                                         : 'bg-red-100 text-red-800';
                        const dotColor   = isOnline ? 'bg-green-500 animate-pulse'
                                         : isWarning ? 'bg-yellow-500 animate-pulse'
                                         : 'bg-red-500';
                        const statusText = isOnline ? 'Online' : isWarning ? 'Warning' : 'Offline';

                        // ── Battery ──────────────────────────────────
                        let batteryHtml = '';
                        if (d.battery_level !== null) {
                            const bv  = Math.round(d.battery_level);
                            const bc  = bv > 50 ? '#22C55E' : bv > 20 ? '#F59E0B' : '#EF4444';
                            const bi  = bv > 75 ? 'fa-battery-full' : bv > 50 ? 'fa-battery-three-quarters'
                                      : bv > 25 ? 'fa-battery-half' : bv > 10 ? 'fa-battery-quarter' : 'fa-battery-empty';
                            batteryHtml = `
                            <div class="mb-3">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs text-gray-500 flex items-center gap-1">
                                        <i class="fas ${bi}" style="color:${bc}"></i> Battery
                                    </span>
                                    <span class="text-xs font-bold" style="color:${bc}">${bv}%</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all" style="width:${bv}%;background:${bc}"></div>
                                </div>
                            </div>`;
                        }

                        // ── CPU + Memory ──────────────────────────────
                        let perfHtml = '';
                        if (d.cpu_usage !== null || d.memory_usage !== null) {
                            const mkBar = (label, val) => {
                                const v  = Math.round(val);
                                const c  = v < 50 ? '#22C55E' : v < 80 ? '#F59E0B' : '#EF4444';
                                return `<div class="flex-1">
                                    <div class="flex justify-between mb-1">
                                        <span class="text-xs text-gray-500">${label}</span>
                                        <span class="text-xs font-bold" style="color:${c}">${v}%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full transition-all" style="width:${v}%;background:${c}"></div>
                                    </div>
                                </div>`;
                            };
                            perfHtml = `<div class="flex gap-4 mb-3">
                                ${d.cpu_usage    !== null ? mkBar('CPU',    d.cpu_usage)    : ''}
                                ${d.memory_usage !== null ? mkBar('Memory', d.memory_usage) : ''}
                            </div>`;
                        }

                        // ── Sensor connection status ──────────────────
                        const sensorDefs = [
                            { key: 'temperature', icon: 'fa-thermometer-half', label: 'Temperature Sensor' },
                            { key: 'humidity',    icon: 'fa-tint',             label: 'Humidity Sensor'    },
                            { key: 'co2',         icon: 'fa-cloud',            label: 'CO₂ Sensor'         },
                            { key: 'sound',       icon: 'fa-microphone',       label: 'Sound Sensor'       },
                            { key: 'camera',      icon: 'fa-video',            label: 'Camera'             },
                        ];
                        const sensors = d.sensors || {};
                        const readingsHtml = `
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            ${sensorDefs.map((s, idx) => {
                                const connected = sensors[s.key] === true;
                                const bg     = connected ? '#f0fdf4' : '#fef2f2';
                                const border = connected ? '#bbf7d0' : '#fecaca';
                                const ic     = connected ? '#16a34a' : '#dc2626';
                                const dot    = connected ? '#22c55e' : '#ef4444';
                                const label  = connected ? 'Connected' : 'Not Connected';
                                const span   = (idx === sensorDefs.length - 1 && sensorDefs.length % 2 !== 0) ? ' style="grid-column:span 2;"' : '';
                                return `<div class="rounded-lg p-2.5"${span} style="background:${bg};border:1px solid ${border}${span ? ';' : ''}">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <i class="fas ${s.icon} text-xs" style="color:${ic};"></i>
                                        <span class="text-xs text-gray-500 font-medium">${s.label}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span style="width:7px;height:7px;border-radius:50%;background:${dot};display:inline-block;flex-shrink:0;"></span>
                                        <span class="text-xs font-bold" style="color:${ic};">${label}</span>
                                    </div>
                                </div>`;
                            }).join('')}
                        </div>`;

                        // ── Downtime label ────────────────────────────
                        const syncLabel = isOnline
                            ? `<span class="text-green-600 font-semibold" data-sync-ts="${d.last_sync_ts}" data-sync-online="1">Syncing actively</span>`
                            : `<span class="text-red-500 font-semibold" data-sync-ts="${d.last_sync_ts}" data-sync-online="0">Offline for <span class="sync-elapsed">…</span></span>`;

                        return `
                        <div class="bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl overflow-hidden shadow-lg">
                            <div class="bg-yellow-500 p-4 flex justify-between items-center">
                                <div class="text-white">
                                    <h3 class="font-bold">${d.device_name}</h3>
                                    <p class="text-sm opacity-90">${d.device_id}</p>
                                </div>
                                <span class="${statusBg} px-3 py-1 rounded-lg text-xs font-bold flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full ${dotColor}"></span>${statusText}
                                </span>
                            </div>
                            <div class="bg-white p-4">
                                <div class="grid grid-cols-2 gap-3 mb-4">
                                    <div class="bg-gray-50 p-2 rounded">
                                        <p class="text-xs text-gray-600">Device Type</p>
                                        <p class="font-bold text-sm">${d.device_type}</p>
                                    </div>
                                    <div class="bg-gray-50 p-2 rounded">
                                        <p class="text-xs text-gray-600">Assigned Hive</p>
                                        <p class="font-bold text-sm">${d.hive_name}</p>
                                    </div>
                                </div>
                                ${readingsHtml}
                                ${batteryHtml}
                                ${perfHtml}
                                <div class="bg-gray-50 p-3 rounded-lg mb-3">
                                    <p class="text-xs text-gray-600 mb-1">Firmware: <span class="font-bold">${d.firmware ?? '—'}</span></p>
                                    <p class="text-xs text-gray-500">
                                        Last sync: ${syncLabel} &bull; ${d.last_sync}
                                    </p>
                                </div>
                            </div>
                        </div>`;
                    }).join('');

                    // Update live downtime counters
                    updateSyncCounters();
                })
                .catch(() => {
                    document.getElementById('lastChecked').textContent = 'Connection error';
                });
        }

        function fmtDuration(secs) {
            if (secs < 60)   return secs + 's';
            if (secs < 3600) return Math.floor(secs / 60) + 'm ' + (secs % 60) + 's';
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            return h + 'h ' + (m > 0 ? m + 'm' : '');
        }

        function updateSyncCounters() {
            const now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('[data-sync-ts]').forEach(el => {
                const ts     = parseInt(el.dataset.syncTs);
                const online = el.dataset.syncOnline === '1';
                if (!ts) return;
                const diff = now - ts;
                if (!online) {
                    const span = el.querySelector('.sync-elapsed');
                    if (span) span.textContent = fmtDuration(diff);
                }
            });
        }

        // Load immediately then poll every 10 seconds
        fetchDevices();
        setInterval(fetchDevices, 10000);
        setInterval(updateSyncCounters, 1000);

        <?php if (isAdmin()): ?>
        // Hive management
        function loadHives() {
            fetch('api/get_hives.php')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('hiveList');
                    if (!data.hives || data.hives.length === 0) {
                        container.innerHTML = '<p class="text-sm text-gray-400 text-center mt-6">No hives registered yet. Add your first hive!</p>';
                        return;
                    }
                    container.innerHTML = data.hives.map(h => `
                        <div class="flex items-center gap-3 p-2 bg-white rounded-lg border border-green-100">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-house text-green-600 text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate">${h.name}</p>
                                <p class="text-xs text-gray-500">${h.hive_id}${h.location ? ' &bull; ' + h.location : ''}${h.device ? ' &bull; <span class="text-yellow-600">' + h.device + '</span>' : ''}</p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold ${h.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}">${h.status}</span>
                            <button onclick="removeHive(${h.id}, '${h.name.replace(/'/g,"\\\'")}')"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-500 hover:text-red-700 transition flex-shrink-0" title="Remove hive">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </div>
                    `).join('');
                })
                .catch(() => {
                    document.getElementById('hiveList').innerHTML = '<p class="text-sm text-red-400 text-center mt-6">Failed to load hives.</p>';
                });
        }

        function loadDetectedDevices() {
            fetch('api/get_hives.php?action=detected')
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('detectedDevices');
                    if (!data.detected || data.detected.length === 0) {
                        container.innerHTML = '<p class="text-sm text-gray-400 text-center mt-6">No new devices detected. Connect an ESP32 to see it here.</p>';
                        return;
                    }
                    container.innerHTML = data.detected.map(d => `
                        <div class="flex items-center gap-3 p-2 bg-white rounded-lg border border-yellow-200">
                            <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-microchip text-yellow-600 text-xs"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold truncate">${d.device_id}</p>
                                <p class="text-xs text-gray-500">Last seen: ${d.last_seen} &bull; ${d.times_seen}x</p>
                            </div>
                            <button onclick="openRegisterModal('${d.device_id}')"
                                class="text-xs px-2 py-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold transition flex-shrink-0">
                                Register
                            </button>
                            <button onclick="dismissDetected('${d.device_id}')"
                                class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-500 hover:text-red-700 transition flex-shrink-0" title="Dismiss">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    `).join('');
                })
                .catch(() => {
                    document.getElementById('detectedDevices').innerHTML = '<p class="text-sm text-red-400 text-center mt-6">Failed to check devices.</p>';
                });
        }

        function saveNewHive() {
            const name     = document.getElementById('newHiveName').value.trim();
            const hiveCode = document.getElementById('newHiveCode').value.trim();
            const location = document.getElementById('newHiveLocation').value.trim();
            const device   = document.getElementById('newHiveDevice').value.trim();

            if (!name || !hiveCode) {
                showHiveMsg('addHiveMsg', 'error', 'Hive Name and Hive ID Code are required.');
                return;
            }

            fetch('api/add_hive.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `name=${encodeURIComponent(name)}&hive_id=${encodeURIComponent(hiveCode)}&location=${encodeURIComponent(location)}&device_id=${encodeURIComponent(device)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showHiveMsg('addHiveMsg', 'success', data.message);
                    document.getElementById('newHiveName').value = '';
                    document.getElementById('newHiveCode').value = '';
                    document.getElementById('newHiveLocation').value = '';
                    document.getElementById('newHiveDevice').value = '';
                    loadHives();
                    loadDetectedDevices();
                    setTimeout(() => closeHiveModal(), 1500);
                } else {
                    showHiveMsg('addHiveMsg', 'error', data.message);
                }
            })
            .catch(() => showHiveMsg('addHiveMsg', 'error', 'Request failed. Try again.'));
        }

        function openRegisterModal(deviceId) {
            document.getElementById('registerDeviceId').textContent  = deviceId;
            document.getElementById('registerDeviceIdHidden').value  = deviceId;
            document.getElementById('regHiveName').value     = '';
            document.getElementById('regHiveCode').value     = '';
            document.getElementById('regHiveLocation').value = '';
            document.getElementById('registerDeviceMsg').classList.add('hidden');
            document.getElementById('registerDeviceModal').classList.remove('hidden');
        }

        function saveRegisteredDevice() {
            const device   = document.getElementById('registerDeviceIdHidden').value;
            const name     = document.getElementById('regHiveName').value.trim();
            const hiveCode = document.getElementById('regHiveCode').value.trim();
            const location = document.getElementById('regHiveLocation').value.trim();

            if (!name || !hiveCode) {
                showHiveMsg('registerDeviceMsg', 'error', 'Hive Name and Hive ID Code are required.');
                return;
            }

            fetch('api/add_hive.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `name=${encodeURIComponent(name)}&hive_id=${encodeURIComponent(hiveCode)}&location=${encodeURIComponent(location)}&device_id=${encodeURIComponent(device)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showHiveMsg('registerDeviceMsg', 'success', data.message);
                    loadHives();
                    loadDetectedDevices();
                    setTimeout(() => closeModal('registerDeviceModal'), 1500);
                } else {
                    showHiveMsg('registerDeviceMsg', 'error', data.message);
                }
            })
            .catch(() => showHiveMsg('registerDeviceMsg', 'error', 'Request failed. Try again.'));
        }

        function showHiveMsg(elId, type, msg) {
            const el = document.getElementById(elId);
            el.classList.remove('hidden','bg-green-50','border-green-200','text-green-800','bg-red-50','border-red-200','text-red-800');
            el.className = type === 'success'
                ? 'mb-4 p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800'
                : 'mb-4 p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            el.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'times'}-circle mr-2"></i>${msg}`;
        }

        function closeHiveModal() {
            document.getElementById('addHiveModal').classList.add('hidden');
            document.getElementById('addHiveMsg').classList.add('hidden');
        }

        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            const msgMap = { registerDeviceModal: 'registerDeviceMsg' };
            if (msgMap[id]) document.getElementById(msgMap[id]).classList.add('hidden');
        }
        window.addEventListener('click', e => {
            ['registerDeviceModal','addHiveModal'].forEach(id => {
                if (e.target === document.getElementById(id)) {
                    if (id === 'addHiveModal') closeHiveModal();
                    else closeModal(id);
                }
            });
        });

        function removeEdgeDevice(deviceId) {
            adminDelete(`Remove ESP32 "${deviceId}" from the system? It will appear in Detected Devices again if it sends data.`, () => {
                fetch('api/remove_device.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=edge_device&device_id=${encodeURIComponent(deviceId)}`
                })
                .then(r => r.json())
                .then(d => { if (d.success) { fetchDevices(); loadHives(); } })
                .catch(() => {});
            });
        }

        function removeHive(hiveId, hiveName) {
            adminDelete(`Remove hive "${hiveName}"? This will unlink its ESP32 device but keep historical sensor data.`, () => {
                fetch('api/remove_device.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=hive&hive_id=${hiveId}`
                })
                .then(r => r.json())
                .then(d => { if (d.success) { loadHives(); loadDetectedDevices(); fetchDevices(); } })
                .catch(() => {});
            });
        }

        function dismissDetected(deviceId) {
            adminDelete(`Dismiss device "${deviceId}" from the detected list?`, () => {
                fetch('api/remove_device.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=detected&device_id=${encodeURIComponent(deviceId)}`
                })
                .then(r => r.json())
                .then(d => { if (d.success) loadDetectedDevices(); })
                .catch(() => {});
            });
        }

        loadHives();
        loadDetectedDevices();
        <?php endif; ?>
    </script>
</body>
</html>