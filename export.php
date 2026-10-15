<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

// Live record counts for preview
$counts = [];
$ranges = [7 => 'INTERVAL 7 DAY', 30 => 'INTERVAL 30 DAY', 90 => 'INTERVAL 90 DAY', 180 => 'INTERVAL 180 DAY', 365 => 'INTERVAL 1 YEAR', 0 => null];

$countSensor      = (int)$conn->query("SELECT COUNT(*) c FROM sensor_data")->fetch_assoc()['c'];
$countAudio       = (int)$conn->query("SELECT COUNT(*) c FROM audio_analysis")->fetch_assoc()['c'];
$countAlerts      = (int)$conn->query("SELECT COUNT(*) c FROM alerts")->fetch_assoc()['c'];
$countLog         = (int)$conn->query("SELECT COUNT(*) c FROM activity_log")->fetch_assoc()['c'];
$countInspections = (int)$conn->query("SELECT COUNT(*) c FROM inspection_notes")->fetch_assoc()['c'];
$totalAll         = $countSensor + $countAudio + $countAlerts + $countLog + $countInspections;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Export - BeeGuard</title>
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
                      style="background:linear-gradient(135deg,#F59E0B,#F97316);box-shadow:0 4px 14px rgba(245,158,11,0.4);">
                    <i class="fas fa-download text-white text-base"></i>
                </span>
                Data Export
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Export and download historical hive data for analysis</p>
        </div>

        <div class="p-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <!-- Export Configuration -->
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="font-bold text-lg mb-5 flex items-center gap-2">
                        <i class="fas fa-cog text-yellow-600"></i> Export Configuration
                    </h3>

                    <!-- Data Type -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data Type</label>
                        <select id="dataType" onchange="updatePreview()"
                            class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-yellow-500 focus:outline-none">
                            <option value="all">All Data</option>
                            <option value="sensor">Environmental Data (Temperature, Humidity, CO₂, Sound)</option>
                            <option value="audio">Audio Analysis & Colony Status</option>
                            <option value="alerts">Alerts History</option>
                            <option value="activity">Activity Log</option>
                            <option value="inspection">Inspection Notes</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date Range</label>
                        <select id="dateRange" onchange="updatePreview()"
                            class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-yellow-500 focus:outline-none">
                            <option value="7">Last 7 Days</option>
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 3 Months</option>
                            <option value="180">Last 6 Months</option>
                            <option value="365">Last Year</option>
                            <option value="0">All Time</option>
                        </select>
                    </div>

                    <!-- Hive Filter -->
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Hive</label>
                        <select id="hiveFilter" onchange="updatePreview()"
                            class="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-yellow-500 focus:outline-none">
                            <option value="0">All Hives</option>
                            <?php
                            $hives = getAllHives($conn);
                            foreach ($hives as $h):
                            ?>
                            <option value="<?php echo $h['id']; ?>">
                                <?php echo htmlspecialchars($h['name']); ?> (<?php echo htmlspecialchars($h['hive_id']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Format -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Export Format</label>
                        <div class="grid grid-cols-3 gap-3">
                            <button id="btn-csv" onclick="selectFormat('csv')"
                                class="px-4 py-3 bg-yellow-500 text-white rounded-lg font-semibold hover:bg-yellow-600 transition flex items-center justify-center gap-2">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                            <button id="btn-word" onclick="selectFormat('word')"
                                class="px-4 py-3 border-2 border-gray-300 text-gray-600 rounded-lg font-semibold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                <i class="fas fa-file-word"></i> Word
                            </button>
                            <button id="btn-pdf" onclick="selectFormat('pdf')"
                                class="px-4 py-3 border-2 border-gray-300 text-gray-600 rounded-lg font-semibold hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">CSV — Excel/Sheets &bull; Word — editable document (.doc) &bull; PDF — print/report</p>
                    </div>

                    <!-- Preview -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-5">
                        <p class="text-sm font-semibold text-yellow-900 mb-2 flex items-center gap-2">
                            <i class="fas fa-eye"></i> Export Preview
                        </p>
                        <ul class="text-sm text-yellow-800 space-y-1" id="previewList">
                            <li>• Data Type: <strong id="prev-type">All Data</strong></li>
                            <li>• Date Range: <strong id="prev-range">Last 7 Days</strong></li>
                            <li>• Hive: <strong id="prev-hive">All Hives</strong></li>
                            <li>• Format: <strong id="prev-format">CSV</strong></li>
                            <li>• Estimated Records: <strong id="prev-count">—</strong></li>
                        </ul>
                    </div>

                    <div id="exportMsg" class="hidden mb-4 p-3 rounded-lg text-sm"></div>

                    <!-- Export Button -->
                    <button onclick="doExport()" id="exportBtn"
                        class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-4 rounded-lg font-bold text-lg flex items-center justify-center gap-2 transition">
                        <i class="fas fa-download" id="exportIcon"></i>
                        <span id="exportBtnText">Export & Download</span>
                    </button>
                </div>

                <!-- Right column -->
                <div>
                    <!-- Available Data Summary -->
                    <div class="bg-white rounded-xl p-6 shadow-sm mb-6">
                        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
                            <i class="fas fa-database text-yellow-600"></i> Available Data
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center p-3 bg-orange-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-thermometer-half text-orange-500"></i>
                                    <span class="text-sm font-semibold">Environmental Readings</span>
                                </div>
                                <span class="text-sm font-bold text-orange-600"><?php echo number_format($countSensor); ?> records</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-volume-up text-yellow-500"></i>
                                    <span class="text-sm font-semibold">Audio Analysis</span>
                                </div>
                                <span class="text-sm font-bold text-yellow-600"><?php echo number_format($countAudio); ?> records</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-red-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-bell text-red-500"></i>
                                    <span class="text-sm font-semibold">Alerts</span>
                                </div>
                                <span class="text-sm font-bold text-red-600"><?php echo number_format($countAlerts); ?> records</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-list text-blue-500"></i>
                                    <span class="text-sm font-semibold">Activity Log</span>
                                </div>
                                <span class="text-sm font-bold text-blue-600"><?php echo number_format($countLog); ?> records</span>
                            </div>
                            <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg border-t-2 border-gray-300">
                                <span class="text-sm font-bold text-gray-700">Total</span>
                                <span class="text-sm font-bold text-gray-900"><?php echo number_format($totalAll); ?> records</span>
                            </div>
                        </div>
                    </div>

                    <!-- Guidelines -->
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                        <h3 class="font-bold text-lg mb-3 text-blue-900 flex items-center gap-2">
                            <i class="fas fa-info-circle"></i> Export Guidelines
                        </h3>
                        <ul class="text-sm text-blue-800 space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-0.5 text-blue-600"></i>
                                <span>CSV is recommended for Excel, Google Sheets, or Python/pandas</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-0.5 text-blue-600"></i>
                                <span>Word (.doc) creates an editable document you can open in Microsoft Word or LibreOffice</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-0.5 text-blue-600"></i>
                                <span>Files download immediately — no server storage</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check-circle mt-0.5 text-blue-600"></i>
                                <span>Select a specific hive to narrow the export</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Recent Downloads -->
                    <div class="bg-white rounded-xl p-5 shadow-sm mt-6">
                        <h3 class="font-bold text-base mb-3 flex items-center gap-2">
                            <i class="fas fa-history text-yellow-600"></i> Recent Downloads
                        </h3>
                        <div id="recentDownloads" class="space-y-2">
                            <p class="text-sm text-gray-400 text-center py-4">No downloads yet this session</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        // DB record counts from PHP
        const dbCounts = {
            sensor:     <?php echo $countSensor; ?>,
            audio:      <?php echo $countAudio; ?>,
            alerts:     <?php echo $countAlerts; ?>,
            activity:   <?php echo $countLog; ?>,
            inspection: <?php echo $countInspections; ?>,
            all:        <?php echo $totalAll; ?>
        };

        const typeLabels = {
            all:        'All Data',
            sensor:     'Environmental Data',
            audio:      'Audio Analysis',
            alerts:     'Alerts History',
            activity:   'Activity Log',
            inspection: 'Inspection Notes'
        };

        const rangeLabels = {
            '7':'Last 7 Days','30':'Last 30 Days','90':'Last 3 Months',
            '180':'Last 6 Months','365':'Last Year','0':'All Time'
        };

        let selectedFormat = 'csv';

        function selectFormat(fmt) {
            selectedFormat = fmt;
            ['csv','word','pdf'].forEach(f => {
                const btn = document.getElementById('btn-' + f);
                if (f === fmt) {
                    btn.className = 'px-4 py-3 bg-yellow-500 text-white rounded-lg font-semibold hover:bg-yellow-600 transition flex items-center justify-center gap-2';
                } else {
                    btn.className = 'px-4 py-3 border-2 border-gray-300 text-gray-600 rounded-lg font-semibold hover:bg-gray-50 transition flex items-center justify-center gap-2';
                }
            });
            updatePreview();
        }

        function addRecentDownload(type, range, hive, fmt, time) {
            const container = document.getElementById('recentDownloads');
            const fmtColor = {CSV:'bg-green-100 text-green-700', WORD:'bg-blue-100 text-blue-700', PDF:'bg-red-100 text-red-700'};
            const entry = document.createElement('div');
            entry.className = 'flex items-center gap-3 p-2.5 bg-gray-50 rounded-lg';
            entry.innerHTML = `
                <i class="fas fa-file-download text-yellow-500"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate">${type} — ${range}</p>
                    <p class="text-xs text-gray-400">${hive} &bull; ${time}</p>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full ${fmtColor[fmt]||'bg-gray-100 text-gray-600'}">${fmt}</span>`;
            const placeholder = container.querySelector('p');
            if (placeholder) placeholder.remove();
            container.insertBefore(entry, container.firstChild);
            // Keep max 5
            while (container.children.length > 5) container.removeChild(container.lastChild);
        }

        function updatePreview() {
            const type  = document.getElementById('dataType').value;
            const range = document.getElementById('dateRange').value;
            const hive  = document.getElementById('hiveFilter');
            const hiveTxt = hive.options[hive.selectedIndex].text;

            document.getElementById('prev-type').textContent   = typeLabels[type];
            document.getElementById('prev-range').textContent  = rangeLabels[range];
            document.getElementById('prev-hive').textContent   = hiveTxt;
            document.getElementById('prev-format').textContent = selectedFormat.toUpperCase();
            document.getElementById('prev-count').textContent  = dbCounts[type].toLocaleString() + ' (approx)';
        }

        function doExport() {
            const type   = document.getElementById('dataType').value;
            const range  = document.getElementById('dateRange').value;
            const hiveId = document.getElementById('hiveFilter').value;
            const btn    = document.getElementById('exportBtn');
            const icon   = document.getElementById('exportIcon');
            const txt    = document.getElementById('exportBtnText');

            btn.disabled = true;
            icon.className = 'fas fa-spinner fa-spin';
            txt.textContent = 'Generating...';

            const url = `api/export_data.php?type=${type}&range=${range}&hive_id=${hiveId}&format=${selectedFormat}`;

            if (selectedFormat === 'pdf') {
                window.open(url, '_blank');
            } else {
                const a = document.createElement('a');
                a.href = url;
                a.download = '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }

            // Log to recent downloads
            const now = new Date().toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
            const hiveEl = document.getElementById('hiveFilter');
            const hiveTxt = hiveEl.options[hiveEl.selectedIndex].text;
            addRecentDownload(typeLabels[type], rangeLabels[range], hiveTxt, selectedFormat.toUpperCase(), now);

            setTimeout(() => {
                btn.disabled = false;
                icon.className = 'fas fa-download';
                txt.textContent = 'Export & Download';
            }, 2000);
        }

        updatePreview();
    </script>
</body>
</html>
