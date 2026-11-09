<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

$hives = getAllHives($conn);

// Users list for filter (admin only)
$users = [];
if (isAdmin()) {
    $res = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY role, username");
    while ($r = $res->fetch_assoc()) $users[] = $r;
}

// Count badges for tabs
$hiveCount = (int)$conn->query("SELECT COUNT(*) c FROM activity_log WHERE hive_id IS NOT NULL AND timestamp >= NOW() - INTERVAL 1 HOUR")->fetch_assoc()['c'];
$userCount  = (int)$conn->query("SELECT COUNT(*) c FROM activity_log WHERE event_type NOT IN ('Data Collection','camera_insect_detected') AND timestamp >= NOW() - INTERVAL 1 HOUR")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - BeeGuard</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .filter-btn {
            font-size: 12px; font-weight: 700; padding: 6px 12px;
            border-radius: 8px; border: 1px solid #E2E8F0;
            background: white; color: #6B7280; cursor: pointer; transition: all 0.15s;
        }
        .filter-btn.active {
            background: linear-gradient(135deg,#F59E0B,#F97316);
            color: white; border-color: transparent;
        }
        .filter-btn-user.active {
            background: linear-gradient(135deg,#6366F1,#8B5CF6);
            color: white; border-color: transparent;
        }
        .input-sm {
            font-size: 13px; border: 1.5px solid #E2E8F0; border-radius: 8px;
            padding: 7px 12px; background: white;
        }
        .input-sm:focus { outline: none; border-color: #F59E0B; }
        .log-row { cursor: pointer; }
        .log-row:hover td { background: rgba(245,158,11,0.06); }
        #logDetailModal { display:none; position:fixed; inset:0; z-index:99999; background:rgba(10,6,0,0.65); backdrop-filter:blur(4px); overflow-y:auto; padding:24px 16px; }
        #logDetailModal.active { display:block; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-4 sm:px-8 py-4 sm:py-5">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                      style="background:linear-gradient(135deg,#10B981,#059669);box-shadow:0 4px 14px rgba(16,185,129,0.4);">
                    <i class="fas fa-list-alt text-white text-base"></i>
                </span>
                Activity Log
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Complete history of all system, hive, and user activities</p>
        </div>

        <div class="p-4 sm:p-6">

            <!-- Tabs -->
            <div class="flex gap-2 mb-6">
                <button onclick="switchTab('hive')" id="tab-hive"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-yellow-500 text-white">
                    <i class="fas fa-house"></i> Hive Activities
                    <span id="badge-hive" class="bg-white text-yellow-600 text-xs font-bold px-2 py-0.5 rounded-full"><?php echo number_format($hiveCount); ?></span>
                </button>
                <?php if (isAdmin()): ?>
                <button onclick="switchTab('user')" id="tab-user"
                    class="flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-white text-gray-600 border border-gray-300 hover:bg-gray-50">
                    <i class="fas fa-users"></i> User & Admin
                    <span id="badge-user" class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full"><?php echo number_format($userCount); ?></span>
                </button>
                <?php endif; ?>
            </div>

            <!-- ── HIVE ACTIVITIES PANEL ── -->
            <div id="panel-hive">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 flex items-center gap-2" style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                        <i class="fas fa-house text-white"></i>
                        <span class="text-white font-bold">Hive Activities</span>
                        <span class="ml-auto text-yellow-100 text-xs">ESP32 sensor data, alerts, inspection events</span>
                    </div>

                    <!-- Filters -->
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2"
                         style="background:#FFFBEB;">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-500 whitespace-nowrap">Hive</label>
                            <select id="hiveHiveFilter" onchange="loadHiveLog()" class="input-sm">
                                <option value="all">All Hives</option>
                                <?php foreach ($hives as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <select id="hiveRangeSelect" class="input-sm" onchange="setHiveRange(this.value)">
                            <option value="1h" selected>1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                            <option value="3mo">3 Months</option>
                            <option value="6mo">6 Months</option>
                        </select>
                        <div class="flex flex-wrap items-center gap-2 ml-auto">
                            <input type="date" id="hiveFrom" class="input-sm">
                            <span class="text-gray-400 text-xs font-semibold">to</span>
                            <input type="date" id="hiveTo" class="input-sm">
                            <button onclick="applyHiveCustom()"
                                    class="text-xs font-bold px-3 py-1.5 rounded-lg text-white"
                                    style="background:linear-gradient(135deg,#3B82F6,#6366F1);">Apply</button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead style="background:#FEF3C7;">
                                <tr class="border-b-2 border-yellow-100">
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold whitespace-nowrap">Timestamp</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Hive</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Event</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Details</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Severity</th>
                                    <?php if (isAdmin()): ?><th class="px-3 py-3"></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody id="hiveLogBody">
                                <tr><td colspan="6" class="py-10 text-center text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="hivePagination" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-gray-100">
                        <span id="hivePageInfo" class="text-xs text-gray-500"></span>
                        <div id="hivePageBtns" class="flex gap-1"></div>
                    </div>
                </div>
            </div>

            <!-- ── USER & ADMIN PANEL ── -->
            <div id="panel-user" class="hidden">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 flex items-center gap-2"
                         style="background:linear-gradient(135deg,#6366F1,#8B5CF6);">
                        <i class="fas fa-users text-white"></i>
                        <span class="text-white font-bold">User & Admin Activities</span>
                        <span class="ml-auto text-indigo-100 text-xs">Login/logout, user management, settings, syncs</span>
                    </div>

                    <!-- Filters -->
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2"
                         style="background:#EEF2FF;">
                        <?php if (isAdmin()): ?>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-500 whitespace-nowrap">User</label>
                            <select id="userUserFilter" onchange="loadUserLog()" class="input-sm">
                                <option value="all">All Users</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?>
                                    (<?php echo $u['role']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <select id="userRangeSelect" class="input-sm" onchange="setUserRange(this.value)">
                            <option value="1h" selected>1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                            <option value="3mo">3 Months</option>
                            <option value="6mo">6 Months</option>
                        </select>
                        <div class="flex flex-wrap items-center gap-2 ml-auto">
                            <input type="date" id="userFrom" class="input-sm">
                            <span class="text-gray-400 text-xs font-semibold">to</span>
                            <input type="date" id="userTo" class="input-sm">
                            <button onclick="applyUserCustom()"
                                    class="text-xs font-bold px-3 py-1.5 rounded-lg text-white"
                                    style="background:linear-gradient(135deg,#3B82F6,#6366F1);">Apply</button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead style="background:#EEF2FF;">
                                <tr class="border-b-2 border-indigo-100">
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold whitespace-nowrap">Timestamp</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">User</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Event</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Details</th>
                                    <th class="px-5 py-3 text-left text-gray-600 font-semibold">Severity</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody id="userLogBody">
                                <tr><td colspan="6" class="py-10 text-center text-gray-400">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="userPagination" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-t border-gray-100">
                        <span id="userPageInfo" class="text-xs text-gray-500"></span>
                        <div id="userPageBtns" class="flex gap-1"></div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- ── Log Detail Modal ── -->
    <div id="logDetailModal" onclick="if(event.target===this)closeLogDetail()">
        <div style="background:#fff;width:100%;max-width:560px;margin:0 auto;border-radius:20px;box-shadow:0 24px 80px rgba(0,0,0,0.35);animation:modalIn 0.25s cubic-bezier(0.34,1.56,0.64,1) both;">
            <div class="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                <h3 class="font-bold text-base text-gray-800 flex items-center gap-2">
                    <i id="ldIcon" class="fas fa-info-circle text-blue-500"></i>
                    <span id="ldEvent" class="truncate">—</span>
                </h3>
                <button onclick="closeLogDetail()" class="w-9 h-9 rounded-xl hover:bg-gray-100 flex items-center justify-center transition flex-shrink-0">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gray-50 rounded-xl p-3">
                        <p class="text-xs text-gray-400 font-semibold mb-1">Timestamp</p>
                        <p class="text-sm font-semibold text-gray-800" id="ldTimestamp">—</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3">
                        <p class="text-xs text-gray-400 font-semibold mb-1">Severity</p>
                        <p id="ldSeverity">—</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3" id="ldHiveBlock">
                        <p class="text-xs text-gray-400 font-semibold mb-1">Hive</p>
                        <p class="text-sm font-semibold text-gray-800" id="ldHive">—</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3" id="ldUserBlock">
                        <p class="text-xs text-gray-400 font-semibold mb-1">User</p>
                        <p class="text-sm font-semibold text-gray-800" id="ldUser">—</p>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-xs text-gray-400 font-semibold mb-2">Full Details</p>
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap break-words" id="ldDetails">—</p>
                </div>
            </div>
            <div class="px-6 pb-6">
                <button onclick="closeLogDetail()" class="w-full py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Close</button>
            </div>
        </div>
    </div>

    <script>
    const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const logMap  = {};   // id → row data, populated on each render

    // ── State ────────────────────────────────────────────────────
    const hiveState = { range: '1h', hive_id: 'all', page: 1, customFrom: '', customTo: '' };
    const userState = { range: '1h', user_id: 'all', page: 1, customFrom: '', customTo: '' };
    let currentTab  = 'hive';

    // ── Tab switch ────────────────────────────────────────────────
    function switchTab(tab) {
        currentTab = tab;
        document.getElementById('panel-hive').classList.toggle('hidden', tab !== 'hive');
        document.getElementById('panel-user').classList.toggle('hidden', tab !== 'user');

        document.getElementById('tab-hive').className = tab === 'hive'
            ? 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-yellow-500 text-white'
            : 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-white text-gray-600 border border-gray-300 hover:bg-gray-50';

        const tabUser = document.getElementById('tab-user');
        if (tabUser) {
            tabUser.className = tab === 'user'
                ? 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition text-white'
                : 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-white text-gray-600 border border-gray-300 hover:bg-gray-50';
            tabUser.style.background = tab === 'user' ? 'linear-gradient(135deg,#6366F1,#8B5CF6)' : '';
        }

        if (tab === 'user') loadUserLog();
    }

    // ── Severity helpers ──────────────────────────────────────────
    const sevIcon = { critical:'fa-exclamation-circle', warning:'fa-exclamation-triangle', success:'fa-check-circle', info:'fa-info-circle' };
    const sevStyle = {
        critical: { bg:'#FEE2E2', text:'#991B1B', icon:'#EF4444' },
        warning:  { bg:'#FEF3C7', text:'#92400E', icon:'#F59E0B' },
        success:  { bg:'#D1FAE5', text:'#065F46', icon:'#10B981' },
        info:     { bg:'#DBEAFE', text:'#1E40AF', icon:'#3B82F6' },
    };
    function sevBadge(s) {
        const st = sevStyle[s] || sevStyle.info;
        return `<span style="background:${st.bg};color:${st.text};padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;">${(s||'info').toUpperCase()}</span>`;
    }
    function sevIconEl(s) {
        const st = sevStyle[s] || sevStyle.info;
        const ic = sevIcon[s] || 'fa-info-circle';
        return `<i class="fas ${ic} mr-1.5" style="color:${st.icon}"></i>`;
    }

    // ── Pagination renderer ────────────────────────────────────────
    function renderPagination(infoId, btnsId, page, pages, total, limit, goFn) {
        const from = (page-1)*limit+1, to = Math.min(page*limit, total);
        document.getElementById(infoId).textContent =
            total ? `Showing ${from}–${to} of ${total.toLocaleString()} records` : 'No records found';
        const btns = document.getElementById(btnsId);
        if (pages <= 1) { btns.innerHTML = ''; return; }
        const base = 'text-xs font-semibold px-3 py-1.5 rounded-lg border transition';
        const act  = 'style="background:linear-gradient(135deg,#F59E0B,#F97316);color:white;border-color:transparent;"';
        const idle = 'style="background:white;color:#6B7280;border-color:#E2E8F0;"';
        let h = `<button class="${base}" ${page===1?'disabled style="opacity:.4;cursor:default;"':idle}
                    onclick="${goFn}(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
        for (let i=1;i<=pages;i++) {
            if (i===1||i===pages||(i>=page-2&&i<=page+2))
                h += `<button class="${base}" ${i===page?act:idle} onclick="${goFn}(${i})">${i}</button>`;
            else if (i===page-3||i===page+3)
                h += `<span class="text-xs text-gray-400 px-1">…</span>`;
        }
        h += `<button class="${base}" ${page===pages?'disabled style="opacity:.4;cursor:default;"':idle}
                    onclick="${goFn}(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
        btns.innerHTML = h;
    }

    // ── HIVE LOG ──────────────────────────────────────────────────
    function loadHiveLog() {
        hiveState.hive_id = document.getElementById('hiveHiveFilter').value;
        const savedScroll = window.scrollY;
        const tbody = document.getElementById('hiveLogBody');
        tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>`;

        const p = new URLSearchParams({ tab:'hive', range:hiveState.range, hive_id:hiveState.hive_id, page:hiveState.page });
        if (hiveState.range === 'custom') { p.set('from', hiveState.customFrom); p.set('to', hiveState.customTo); }

        fetch('api/activity_filter.php?' + p)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                document.getElementById('badge-hive').textContent = d.total.toLocaleString();
                if (!d.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-gray-400 text-sm">
                        <i class="fas fa-satellite-dish text-3xl mb-3 block"></i>No hive activity for the selected period.</td></tr>`;
                    document.getElementById('hivePageInfo').textContent = '';
                    document.getElementById('hivePageBtns').innerHTML = '';
                    requestAnimationFrame(() => window.scrollTo({ top: savedScroll, behavior: 'instant' }));
                    return;
                }
                tbody.innerHTML = d.rows.map(r => {
                    logMap[r.id] = r;
                    const ts = new Date(r.timestamp).toLocaleString();
                    const hive = r.hive_name
                        ? `<span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded text-xs font-mono">${esc(r.hive_code)}</span>
                           <span class="text-xs text-gray-500 ml-1">${esc(r.hive_name)}</span>`
                        : `<span class="text-gray-400">—</span>`;
                    const delBtn = isAdmin
                        ? `<button onclick="event.stopPropagation();deleteLog(${r.id},'hive')"
                               class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 transition" title="Delete log">
                               <i class="fas fa-trash text-xs"></i>
                           </button>`
                        : '';
                    return `<tr class="log-row border-b border-gray-50 transition" id="log-row-${r.id}" onclick="openLogDetail(${r.id})">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">${ts}</td>
                        <td class="px-5 py-3">${hive}</td>
                        <td class="px-5 py-3 whitespace-nowrap">${sevIconEl(r.severity)}${esc(r.event_type)}</td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs truncate">${esc(r.details)}</td>
                        <td class="px-5 py-3">${sevBadge(r.severity)}</td>
                        <td class="px-3 py-3">${delBtn}</td>
                    </tr>`;
                }).join('');
                renderPagination('hivePageInfo','hivePageBtns', d.page, d.pages, d.total, 50, 'hiveGoPage');
                requestAnimationFrame(() => {
                    window.scrollTo({ top: savedScroll, behavior: 'instant' });
                    if (_openLogId && logMap[_openLogId]) openLogDetail(_openLogId);
                });
            });
    }

    function setHiveRange(val) {
        hiveState.range = val;
        hiveState.page  = 1;
        loadHiveLog();
    }

    function applyHiveCustom() {
        const f = document.getElementById('hiveFrom').value;
        const t = document.getElementById('hiveTo').value;
        if (!f || !t) { alert('Please select both From and To dates.'); return; }
        if (f > t)    { alert('"From" must be before "To".'); return; }
        document.getElementById('hiveRangeSelect').value = '';
        hiveState.range = 'custom'; hiveState.customFrom = f; hiveState.customTo = t; hiveState.page = 1;
        loadHiveLog();
    }

    function hiveGoPage(p) { hiveState.page = p; loadHiveLog(); }

    // ── USER LOG ──────────────────────────────────────────────────
    function loadUserLog() {
        const uSel = document.getElementById('userUserFilter');
        userState.user_id = uSel ? uSel.value : 'all';
        const savedScroll = window.scrollY;
        const tbody = document.getElementById('userLogBody');
        tbody.innerHTML = `<tr><td colspan="6" class="py-10 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>`;

        const p = new URLSearchParams({ tab:'user', range:userState.range, user_id:userState.user_id, page:userState.page });
        if (userState.range === 'custom') { p.set('from', userState.customFrom); p.set('to', userState.customTo); }

        fetch('api/activity_filter.php?' + p)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                document.getElementById('badge-user').textContent = d.total.toLocaleString();
                if (!d.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="py-12 text-center text-gray-400 text-sm">
                        <i class="fas fa-clipboard-list text-3xl mb-3 block"></i>No activity for the selected period.</td></tr>`;
                    document.getElementById('userPageInfo').textContent = '';
                    document.getElementById('userPageBtns').innerHTML = '';
                    requestAnimationFrame(() => window.scrollTo({ top: savedScroll, behavior: 'instant' }));
                    return;
                }
                tbody.innerHTML = d.rows.map(r => {
                    logMap[r.id] = r;
                    const ts = new Date(r.timestamp).toLocaleString();
                    const uname = r.username
                        ? `<div class="flex items-center gap-2">
                               <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                    style="background:linear-gradient(135deg,${r.user_role==='admin'?'#6366F1,#8B5CF6':'#F59E0B,#F97316'})">
                                   ${esc((r.full_name||r.username||'?').substring(0,2).toUpperCase())}
                               </div>
                               <div>
                                   <p class="font-semibold text-gray-800 text-xs">${esc(r.full_name||r.username)}</p>
                                   <p class="text-xs text-gray-400">@${esc(r.username)} · ${r.user_role||'—'}</p>
                               </div>
                           </div>`
                        : `<span class="text-gray-400 text-xs italic">System</span>`;
                    const delBtn = `<button onclick="event.stopPropagation();deleteLog(${r.id},'user')"
                               class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-400 hover:text-red-600 transition" title="Delete log">
                               <i class="fas fa-trash text-xs"></i>
                           </button>`;
                    return `<tr class="log-row border-b border-gray-50 transition" id="log-row-${r.id}" onclick="openLogDetail(${r.id})">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">${ts}</td>
                        <td class="px-5 py-3">${uname}</td>
                        <td class="px-5 py-3 whitespace-nowrap">${sevIconEl(r.severity)}${esc(r.event_type)}</td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs truncate">${esc(r.details)}</td>
                        <td class="px-5 py-3">${sevBadge(r.severity)}</td>
                        <td class="px-3 py-3">${delBtn}</td>
                    </tr>`;
                }).join('');
                renderPagination('userPageInfo','userPageBtns', d.page, d.pages, d.total, 50, 'userGoPage');
                requestAnimationFrame(() => {
                    window.scrollTo({ top: savedScroll, behavior: 'instant' });
                    if (_openLogId && logMap[_openLogId]) openLogDetail(_openLogId);
                });
            });
    }

    function setUserRange(val) {
        userState.range = val;
        userState.page  = 1;
        loadUserLog();
    }

    function applyUserCustom() {
        const f = document.getElementById('userFrom').value;
        const t = document.getElementById('userTo').value;
        if (!f || !t) { alert('Please select both From and To dates.'); return; }
        if (f > t)    { alert('"From" must be before "To".'); return; }
        document.getElementById('userRangeSelect').value = '';
        userState.range = 'custom'; userState.customFrom = f; userState.customTo = t; userState.page = 1;
        loadUserLog();
    }

    function userGoPage(p) { userState.page = p; loadUserLog(); }

    // ── Log detail modal ──────────────────────────────────────
    let _openLogId = null;

    function openLogDetail(id) {
        const row = logMap[id];
        if (!row) return;
        _openLogId = id;
        const iconMap = { critical:'fa-exclamation-circle', warning:'fa-exclamation-triangle', success:'fa-check-circle', info:'fa-info-circle' };
        const colorMap = { critical:'text-red-500', warning:'text-yellow-500', success:'text-green-500', info:'text-blue-500' };
        const sev = row.severity || 'info';

        document.getElementById('ldEvent').textContent     = row.event_type || '—';
        document.getElementById('ldTimestamp').textContent = new Date(row.timestamp).toLocaleString();
        document.getElementById('ldDetails').textContent   = row.details || '—';

        const ldIcon = document.getElementById('ldIcon');
        ldIcon.className = `fas ${iconMap[sev] || 'fa-info-circle'} ${colorMap[sev] || 'text-blue-500'}`;

        document.getElementById('ldSeverity').innerHTML = sevBadge(sev);

        const hiveBlock = document.getElementById('ldHiveBlock');
        const userBlock = document.getElementById('ldUserBlock');

        if (row.hive_name) {
            hiveBlock.classList.remove('hidden');
            document.getElementById('ldHive').innerHTML =
                `<span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded text-xs font-mono mr-1">${esc(row.hive_code)}</span>${esc(row.hive_name)}`;
        } else {
            hiveBlock.classList.add('hidden');
        }

        if (row.username) {
            userBlock.classList.remove('hidden');
            document.getElementById('ldUser').textContent =
                (row.full_name || row.username) + (row.user_role ? ' (' + row.user_role + ')' : '');
        } else {
            userBlock.classList.add('hidden');
        }

        document.getElementById('logDetailModal').classList.add('active');
    }

    function closeLogDetail() {
        document.getElementById('logDetailModal').classList.remove('active');
        _openLogId = null;
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeLogDetail();
    });

    function deleteLog(id, tab) {
        adminDelete('Delete this activity log entry? This cannot be undone.', () => {
            fetch('api/delete_activity.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + id
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    const row = document.getElementById('log-row-' + id);
                    if (row) row.remove();
                } else {
                    alert(d.message || 'Failed to delete log.');
                }
            })
            .catch(() => alert('Network error. Please try again.'));
        });
    }

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Set default "To" dates to today
    const today = new Date().toISOString().slice(0,10);
    document.getElementById('hiveTo').value = today;
    document.getElementById('userTo').value = today;

    // Initial load
    loadHiveLog();
    </script>
</body>
</html>
