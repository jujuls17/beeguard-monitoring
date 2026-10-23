<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

$hives = getAllHives($conn);

// Stat counts (always full scope, unfiltered)
$activeAlerts  = getActiveAlerts($conn);
$historyAlerts = getAlertHistory($conn, 9999);
$criticalCount = count(array_filter($activeAlerts, fn($a) => $a['severity'] === 'critical'));
$warningCount  = count(array_filter($activeAlerts, fn($a) => $a['severity'] === 'warning'));
$totalActive   = count($activeAlerts);
$totalHistory  = count($historyAlerts);

// Predefined alert types (covers all possible sensor/colony events)
$alertTypeLabels = [
    'temperature'    => 'Temperature',
    'humidity'       => 'Humidity',
    'co2'            => 'CO₂ Level',
    'swarming'       => 'Swarming',
    'queenless'      => 'Queenless',
    'camera_insect'  => 'Camera Insect',
];
$hiddenTypes = ['colony_status', 'device_offline', 'camera_insect_detected'];
// Merge with any extra types that exist in DB but aren't predefined
$typesRes = $conn->query("SELECT DISTINCT alert_type FROM alerts WHERE alert_type IS NOT NULL ORDER BY alert_type");
while ($tr = $typesRes->fetch_assoc()) {
    $t = $tr['alert_type'];
    if (!isset($alertTypeLabels[$t]) && !in_array($t, $hiddenTypes)) {
        $alertTypeLabels[$t] = ucwords(str_replace('_', ' ', $t));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts — BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .filter-select {
            font-size:13px; border:1.5px solid #E2E8F0; border-radius:8px;
            padding:6px 12px; background:white; cursor:pointer; color:#374151;
        }
        .filter-select:focus { outline:none; border-color:#F59E0B; box-shadow:0 0 0 2px rgba(245,158,11,0.15); }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main class="min-h-screen">

        <!-- Page Header -->
        <div class="page-header px-8 py-5 flex items-center justify-between">
            <div class="pl-2">
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                          style="background:linear-gradient(135deg,#EF4444,#DC2626);box-shadow:0 4px 14px rgba(239,68,68,0.4);">
                        <i class="fas fa-bell text-white text-base"></i>
                    </span>
                    Alert Management
                </h1>
                <p class="text-gray-500 text-sm mt-0.5">Monitor and manage all hive alerts and notifications</p>
            </div>
            <?php if ($totalActive > 0): ?>
            <button onclick="dismissAll()"
                class="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all"
                style="background:linear-gradient(135deg,#EF4444,#DC2626);box-shadow:0 4px 14px rgba(239,68,68,0.3);">
                <i class="fas fa-check-double"></i> Dismiss All
            </button>
            <?php endif; ?>
        </div>

        <div class="p-8">

            <!-- Stat Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#6B7280,#4B5563);box-shadow:0 8px 32px rgba(107,114,128,0.3);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Active Alerts</p>
                            <h2 class="text-4xl font-black" id="statTotal"><?php echo $totalActive; ?></h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-bell text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#EF4444,#DC2626);box-shadow:0 8px 32px rgba(239,68,68,0.3);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Critical</p>
                            <h2 class="text-4xl font-black" id="statCritical"><?php echo $criticalCount; ?></h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-exclamation-circle text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#F59E0B,#D97706);box-shadow:0 8px 32px rgba(245,158,11,0.3);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Warnings</p>
                            <h2 class="text-4xl font-black" id="statWarning"><?php echo $warningCount; ?></h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#3B82F6,#2563EB);box-shadow:0 8px 32px rgba(59,130,246,0.3);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Dismissed</p>
                            <h2 class="text-4xl font-black"><?php echo $totalHistory; ?></h2>
                        </div>
                        <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                            <i class="fas fa-history text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs + Filter Card -->
            <div class="bg-white rounded-xl shadow-sm mb-6 overflow-hidden">

                <!-- Tab row -->
                <div class="px-5 pt-4 pb-3 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100">
                    <div class="flex gap-1 bg-gray-100 p-1 rounded-lg">
                        <button id="tab-active" onclick="switchTab('active')"
                            class="tab-btn px-4 py-2 rounded-md text-sm font-semibold transition-all">
                            <i class="fas fa-bell mr-1.5"></i>Active
                            <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full bg-red-100 text-red-600 font-bold" id="activeBadge"><?php echo $totalActive; ?></span>
                        </button>
                        <button id="tab-history" onclick="switchTab('history')"
                            class="tab-btn px-4 py-2 rounded-md text-sm font-semibold transition-all">
                            <i class="fas fa-history mr-1.5"></i>History
                            <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full bg-gray-200 text-gray-600 font-bold" id="histBadge"><?php echo $totalHistory; ?></span>
                        </button>
                    </div>
                    <span id="resultCount" class="text-xs text-gray-400"></span>
                </div>

                <!-- Filter bar -->
                <div class="px-5 py-3 flex flex-wrap items-center gap-3" style="background:#FAFAFA;border-top:1px solid #F3F4F6;">
                    <select id="fHive" onchange="reloadAlerts()" class="filter-select">
                        <option value="all">All Hives</option>
                        <?php foreach ($hives as $h): ?>
                        <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="fType" onchange="reloadAlerts()" class="filter-select">
                        <option value="all">All Types</option>
                        <?php foreach ($alertTypeLabels as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="fSeverity" onchange="reloadAlerts()" class="filter-select">
                        <option value="all">All Severity</option>
                        <option value="critical">Critical</option>
                        <option value="warning">Warning</option>
                        <option value="info">Info</option>
                    </select>

                    <select id="fRange" onchange="reloadAlerts()" class="filter-select">
                        <option value="all">All Time</option>
                        <option value="1h">Last 1 Hour</option>
                        <option value="24h">Last 24 Hours</option>
                        <option value="7d">Last 7 Days</option>
                        <option value="30d">Last 30 Days</option>
                    </select>

                    <button onclick="clearFilters()"
                            class="text-xs font-semibold px-3 py-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 transition">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>
            </div>

            <!-- Alert List -->
            <div id="alertPanel">
                <div class="flex items-center justify-center py-12 text-gray-400">
                    <i class="fas fa-spinner fa-spin text-2xl mr-3"></i> Loading alerts...
                </div>
            </div>

            <!-- Pagination -->
            <div id="alertPagination" class="flex flex-wrap items-center justify-between gap-3 mt-4">
                <span id="alertPageInfo" class="text-xs text-gray-500"></span>
                <div id="alertPageBtns" class="flex gap-1"></div>
            </div>

        </div>
    </main>

    <script>
    // ── State ────────────────────────────────────────────────────
    let state = {
        tab:      'active',
        hive_id:  'all',
        type:     'all',
        severity: 'all',
        range:    'all',
        page:     1,
    };

    // ── Tab switch ────────────────────────────────────────────────
    function switchTab(tab) {
        state.tab  = tab;
        state.page = 1;
        document.querySelectorAll('.tab-btn').forEach(btn => {
            const isActive = btn.id === 'tab-' + tab;
            btn.className = isActive
                ? 'tab-btn px-4 py-2 rounded-md text-sm font-semibold bg-white shadow-sm text-gray-900 transition-all'
                : 'tab-btn px-4 py-2 rounded-md text-sm font-semibold text-gray-500 hover:text-gray-700 transition-all';
        });
        reloadAlerts();
    }

    // ── Filter helpers ────────────────────────────────────────────
    function reloadAlerts() {
        state.hive_id  = document.getElementById('fHive').value;
        state.type     = document.getElementById('fType').value;
        state.severity = document.getElementById('fSeverity').value;
        state.range    = document.getElementById('fRange').value;
        state.page     = 1;
        fetchAlerts();
    }

    function clearFilters() {
        document.getElementById('fHive').value     = 'all';
        document.getElementById('fType').value     = 'all';
        document.getElementById('fSeverity').value = 'all';
        document.getElementById('fRange').value    = 'all';
        state.hive_id = 'all'; state.type = 'all'; state.severity = 'all'; state.range = 'all'; state.page = 1;
        fetchAlerts();
    }

    function goPage(p) { state.page = p; fetchAlerts(); }

    // ── Fetch & render ────────────────────────────────────────────
    function fetchAlerts() {
        const panel = document.getElementById('alertPanel');
        panel.innerHTML = `<div class="flex items-center justify-center py-12 text-gray-400">
            <i class="fas fa-spinner fa-spin text-2xl mr-3"></i> Loading...</div>`;

        const p = new URLSearchParams({
            tab:      state.tab,
            hive_id:  state.hive_id,
            type:     state.type,
            severity: state.severity,
            range:    state.range,
            page:     state.page,
        });

        fetch('api/alerts_filter.php?' + p)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                document.getElementById('resultCount').textContent =
                    data.total.toLocaleString() + ' result' + (data.total !== 1 ? 's' : '');

                if (!data.rows.length) {
                    panel.innerHTML = emptyState(state.tab);
                    document.getElementById('alertPageInfo').textContent = '';
                    document.getElementById('alertPageBtns').innerHTML  = '';
                    return;
                }

                panel.innerHTML = state.tab === 'active'
                    ? renderActiveList(data.rows)
                    : renderHistoryList(data.rows);

                renderPagination(data.page, data.pages, data.total);
            })
            .catch(() => {
                panel.innerHTML = `<div class="bg-white rounded-xl p-10 text-center text-red-400">
                    <i class="fas fa-exclamation-circle text-3xl mb-3 block"></i>Failed to load alerts.</div>`;
            });
    }

    // ── Empty states ──────────────────────────────────────────────
    function emptyState(tab) {
        if (tab === 'active') return `
            <div class="bg-white rounded-xl p-12 text-center text-gray-400">
                <div class="w-20 h-20 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check-circle text-green-400 text-4xl"></i>
                </div>
                <p class="text-lg font-semibold text-gray-600">No active alerts</p>
                <p class="text-sm mt-1">All hives are operating normally.</p>
            </div>`;
        return `
            <div class="bg-white rounded-xl p-12 text-center text-gray-400">
                <div class="w-20 h-20 bg-gray-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-history text-gray-300 text-4xl"></i>
                </div>
                <p class="text-lg font-semibold text-gray-600">No dismissed alerts</p>
                <p class="text-sm mt-1">Dismissed alerts will appear here.</p>
            </div>`;
    }

    // ── Icon / color helpers ──────────────────────────────────────
    function alertColor(sev) {
        return sev === 'critical' ? 'red' : sev === 'warning' ? 'yellow' : 'blue';
    }
    function alertIcon(type) {
        type = (type||'').toLowerCase();
        if (type.includes('temp'))   return 'fa-thermometer-half';
        if (type.includes('humid'))  return 'fa-tint';
        if (type.includes('co2'))    return 'fa-cloud';
        if (type.includes('queen'))  return 'fa-crown';
        if (type.includes('swarm'))  return 'fa-wind';
        if (type.includes('colony')) return 'fa-spider';
        return 'fa-exclamation-triangle';
    }
    function severityColors(sev) {
        if (sev === 'critical') return { bg:'#FEE2E2', text:'#991B1B', border:'#F87171' };
        if (sev === 'warning')  return { bg:'#FEF3C7', text:'#92400E', border:'#FCD34D' };
        return                         { bg:'#DBEAFE', text:'#1E40AF', border:'#93C5FD' };
    }
    function timeAgo(ts) {
        const diff = Math.floor((Date.now() - new Date(ts)) / 1000);
        if (diff < 60)   return diff + 's ago';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400)return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
    }
    function esc(s) { return String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    // ── Active list ────────────────────────────────────────────────
    function renderActiveList(rows) {
        return `<div class="space-y-4">${rows.map(a => {
            const c = alertColor(a.severity);
            const col = severityColors(a.severity);
            const icon = alertIcon(a.alert_type);
            const typeLabel = (a.alert_type||'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
            const recAction = a.recommended_action
                ? `<div class="rounded-lg px-4 py-3 mb-3 text-sm" style="background:${col.bg};border:1px solid ${col.border};">
                       <p class="text-xs font-bold mb-1" style="color:${col.text};">
                           <i class="fas fa-lightbulb mr-1"></i>Recommended Action
                       </p>
                       <p class="text-gray-700">${esc(a.recommended_action)}</p>
                   </div>` : '';
            return `<div id="alert-${a.id}" class="bg-white rounded-xl shadow-sm overflow-hidden transition-all"
                         style="border-left:4px solid ${col.border};">
                <div class="p-5">
                    <div class="flex flex-wrap justify-between items-start gap-2 mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                 style="background:${col.bg};">
                                <i class="fas ${icon}" style="color:${col.border};"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                                    ${esc(a.hive_name)}
                                    <span class="text-xs text-gray-400 font-normal font-mono">${esc(a.hive_code)}</span>
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">${timeAgo(a.created_at)}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                                  style="background:${col.bg};color:${col.text};">
                                ${a.severity.toUpperCase()}
                            </span>
                            <span class="text-xs text-gray-500 px-2.5 py-1 bg-gray-100 rounded-full font-medium">
                                ${esc(typeLabel)}
                            </span>
                        </div>
                    </div>
                    <p class="text-gray-700 text-sm mb-3">${esc(a.message)}</p>
                    ${recAction}
                    <div class="flex justify-end">
                        <button onclick="dismissAlert(${a.id})"
                            class="flex items-center gap-1.5 text-sm text-gray-400 hover:text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg transition font-semibold">
                            <i class="fas fa-times"></i> Dismiss
                        </button>
                    </div>
                </div>
            </div>`;
        }).join('')}</div>`;
    }

    // ── History list ──────────────────────────────────────────────
    function renderHistoryList(rows) {
        return `<div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gray-700 flex items-center gap-2">
                    <i class="fas fa-history text-gray-400"></i> Dismissed Alerts
                </h3>
                <p class="text-xs text-gray-400">Most recent first</p>
            </div>
            <div class="divide-y divide-gray-50">
            ${rows.map(a => {
                const col = severityColors(a.severity);
                const icon = alertIcon(a.alert_type);
                const typeLabel = (a.alert_type||'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase());
                return `<div id="hist-${a.id}" class="px-5 py-4 flex flex-wrap items-start gap-3 hover:bg-gray-50 transition">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
                         style="background:${col.bg};">
                        <i class="fas ${icon} text-sm" style="color:${col.border};"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-0.5">
                            <span class="font-semibold text-gray-700 text-sm">${esc(a.hive_name)}</span>
                            <span class="text-xs text-gray-400 font-mono">${esc(a.hive_code)}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-bold"
                                  style="background:${col.bg};color:${col.text};">
                                ${a.severity.toUpperCase()}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">
                                ${esc(typeLabel)}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 truncate">${esc(a.message)}</p>
                        <p class="text-xs text-gray-400 mt-1"><i class="far fa-clock mr-1"></i>${timeAgo(a.created_at)}</p>
                    </div>
                    <button onclick="restoreAlert(${a.id})"
                        class="flex-shrink-0 text-xs text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 px-2.5 py-1.5 rounded-lg transition font-semibold flex items-center gap-1">
                        <i class="fas fa-undo"></i> Restore
                    </button>
                </div>`;
            }).join('')}
            </div>
        </div>`;
    }

    // ── Pagination ────────────────────────────────────────────────
    function renderPagination(page, pages, total) {
        const from = (page-1)*50+1, to = Math.min(page*50, total);
        document.getElementById('alertPageInfo').textContent =
            total ? `Showing ${from}–${to} of ${total.toLocaleString()} alerts` : '';
        const btns = document.getElementById('alertPageBtns');
        if (pages <= 1) { btns.innerHTML = ''; return; }
        const base = 'text-xs font-semibold px-3 py-1.5 rounded-lg border transition';
        const act  = 'style="background:linear-gradient(135deg,#EF4444,#DC2626);color:white;border-color:transparent;"';
        const idle = 'style="background:white;color:#6B7280;border-color:#E2E8F0;"';
        let h = `<button class="${base}" ${page===1?'disabled style="opacity:.4;cursor:default;"':idle} onclick="goPage(${page-1})">
                    <i class="fas fa-chevron-left"></i></button>`;
        for (let i=1;i<=pages;i++) {
            if (i===1||i===pages||(i>=page-2&&i<=page+2))
                h += `<button class="${base}" ${i===page?act:idle} onclick="goPage(${i})">${i}</button>`;
            else if (i===page-3||i===page+3)
                h += `<span class="text-xs text-gray-400 px-1">…</span>`;
        }
        h += `<button class="${base}" ${page===pages?'disabled style="opacity:.4;cursor:default;"':idle} onclick="goPage(${page+1})">
                 <i class="fas fa-chevron-right"></i></button>`;
        btns.innerHTML = h;
    }

    // ── Dismiss single ────────────────────────────────────────────
    function dismissAlert(id) {
        const el = document.getElementById('alert-' + id);
        if (!el) return;
        fetch('dismiss_alert.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:'action=dismiss&alert_id='+id
        })
        .then(r=>r.json())
        .then(d => {
            if (!d.success) { alert(d.message); return; }
            el.style.cssText='opacity:0;transform:translateX(30px);transition:all 0.3s ease;';
            setTimeout(()=>{
                el.remove();
                fetchAlerts();
                const total = parseInt(document.getElementById('statTotal').textContent) - 1;
                document.getElementById('statTotal').textContent = Math.max(0, total);
                document.getElementById('activeBadge').textContent = Math.max(0, total);
            }, 320);
        })
        .catch(()=>alert('Network error.'));
    }

    // ── Dismiss all ────────────────────────────────────────────────
    function dismissAll() {
        const cards = document.querySelectorAll('[id^="alert-"]');
        if (!cards.length) return;
        const doAction = () => {
            fetch('dismiss_alert.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=dismiss_all'})
            .then(r=>r.json())
            .then(d=>{ if(d.success) fetchAlerts(); else alert(d.message); })
            .catch(()=>alert('Network error.'));
        };
        if (typeof adminDelete === 'function') {
            adminDelete('Dismiss all ' + cards.length + ' active alert(s)?', doAction);
        } else {
            if (confirm('Dismiss all '+cards.length+' active alert(s)?')) doAction();
        }
    }

    // ── Restore ────────────────────────────────────────────────────
    function restoreAlert(id) {
        const el = document.getElementById('hist-'+id);
        fetch('dismiss_alert.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=restore&alert_id='+id})
        .then(r=>r.json())
        .then(d=>{
            if (!d.success) { alert(d.message); return; }
            if (el) { el.style.opacity='0.3'; el.style.transition='opacity 0.3s'; setTimeout(()=>{ el.remove(); fetchAlerts(); },320); }
        })
        .catch(()=>alert('Network error.'));
    }

    // ── Init ───────────────────────────────────────────────────────
    switchTab('active');

    // ── Auto-refresh every 30s ────────────────────────────────────
    let _lastAlertCount = <?php echo $totalActive; ?>;
    setInterval(() => {
        fetchAlerts();
        // Also refresh stat cards
        fetch('api/alerts_filter.php?tab=active&page=1')
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                const total    = d.total;
                const crit     = d.rows.filter(a => a.severity === 'critical').length;
                const warn     = d.rows.filter(a => a.severity === 'warning').length;
                document.getElementById('statTotal').textContent   = total;
                document.getElementById('statCritical').textContent = crit;
                document.getElementById('statWarning').textContent  = warn;
                document.getElementById('activeBadge').textContent  = total;
                if (total > _lastAlertCount) {
                    showAlertToast(total - _lastAlertCount + ' new alert(s) received!');
                }
                _lastAlertCount = total;
            }).catch(() => {});
    }, 30000);

    function showAlertToast(msg) {
        const t = document.createElement('div');
        t.style.cssText = 'position:fixed;top:80px;right:24px;z-index:99999;background:linear-gradient(135deg,#EF4444,#DC2626);color:white;padding:12px 20px;border-radius:14px;font-size:13px;font-weight:600;box-shadow:0 8px 32px rgba(0,0,0,0.25);max-width:320px;';
        t.innerHTML = `<i class="fas fa-bell mr-2"></i>${msg}`;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity='0'; t.style.transition='opacity 0.4s'; setTimeout(()=>t.remove(),400); }, 4000);
    }
    </script>
</body>
</html>
