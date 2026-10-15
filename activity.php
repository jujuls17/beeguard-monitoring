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
$hiveCount = (int)$conn->query("SELECT COUNT(*) c FROM activity_log WHERE hive_id IS NOT NULL")->fetch_assoc()['c'];
$userCount  = (int)$conn->query("SELECT COUNT(*) c FROM activity_log")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - BeeGuard</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-8 py-5 pl-10">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                      style="background:linear-gradient(135deg,#10B981,#059669);box-shadow:0 4px 14px rgba(16,185,129,0.4);">
                    <i class="fas fa-list-alt text-white text-base"></i>
                </span>
                Activity Log
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Complete history of all system, hive, and user activities</p>
        </div>

        <div class="p-8">

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
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3"
                         style="background:#FFFBEB;">
                        <!-- Hive selector -->
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-500 whitespace-nowrap">Hive</label>
                            <select id="hiveHiveFilter" onchange="loadHiveLog()" class="input-sm">
                                <option value="all">All Hives</option>
                                <?php foreach ($hives as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Time presets -->
                        <select id="hiveRangeSelect" class="input-sm" onchange="setHiveRange(this.value)">
                            <option value="1h">1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d" selected>7 Days</option>
                            <option value="30d">30 Days</option>
                        </select>

                        <!-- Custom date range -->
                        <div class="flex items-center gap-2 ml-auto">
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
                                </tr>
                            </thead>
                            <tbody id="hiveLogBody">
                                <tr><td colspan="5" class="py-10 text-center text-gray-400">
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
                    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center gap-3"
                         style="background:#EEF2FF;">

                        <!-- User selector (admin only) -->
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

                        <!-- Time presets -->
                        <select id="userRangeSelect" class="input-sm" onchange="setUserRange(this.value)">
                            <option value="1h">1 Hour</option>
                            <option value="6h">6 Hours</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d" selected>7 Days</option>
                            <option value="30d">30 Days</option>
                        </select>

                        <!-- Custom date range -->
                        <div class="flex items-center gap-2 ml-auto">
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
                                </tr>
                            </thead>
                            <tbody id="userLogBody">
                                <tr><td colspan="5" class="py-10 text-center text-gray-400">
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

    <script>
    // ── State ────────────────────────────────────────────────────
    const hiveState = { range: '7d', hive_id: 'all', page: 1, customFrom: '', customTo: '' };
    const userState = { range: '7d', user_id: 'all', page: 1, customFrom: '', customTo: '' };
    let currentTab  = 'hive';

    // ── Tab switch ────────────────────────────────────────────────
    function switchTab(tab) {
        currentTab = tab;
        document.getElementById('panel-hive').classList.toggle('hidden', tab !== 'hive');
        document.getElementById('panel-user').classList.toggle('hidden', tab !== 'user');

        document.getElementById('tab-hive').className = tab === 'hive'
            ? 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-yellow-500 text-white'
            : 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-white text-gray-600 border border-gray-300 hover:bg-gray-50';

        document.getElementById('tab-user').className = tab === 'user'
            ? 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition text-white'
            : 'flex items-center gap-2 px-5 py-2.5 rounded-lg font-semibold text-sm transition bg-white text-gray-600 border border-gray-300 hover:bg-gray-50';
        if (tab === 'user') document.getElementById('tab-user').style.background = 'linear-gradient(135deg,#6366F1,#8B5CF6)';
        else document.getElementById('tab-user').style.background = '';

        if (tab === 'user') loadUserLog();
    }

    // ── Severity helpers ──────────────────────────────────────────
    const sevIcon  = { critical:'fa-exclamation-circle', warning:'fa-exclamation-triangle', success:'fa-check-circle', info:'fa-info-circle' };
    const sevColor = { critical:'red', warning:'yellow', success:'green', info:'blue' };
    function sevBadge(s) {
        const c = sevColor[s] || 'blue';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-${c}-100 text-${c}-700">${s.toUpperCase()}</span>`;
    }
    function sevIconEl(s) {
        const c = sevColor[s] || 'blue'; const i = sevIcon[s] || 'fa-info-circle';
        return `<i class="fas ${i} text-${c}-500 mr-1.5"></i>`;
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
        const tbody = document.getElementById('hiveLogBody');
        tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>`;

        const p = new URLSearchParams({ tab:'hive', range:hiveState.range, hive_id:hiveState.hive_id, page:hiveState.page });
        if (hiveState.range === 'custom') { p.set('from', hiveState.customFrom); p.set('to', hiveState.customTo); }

        fetch('api/activity_filter.php?' + p)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                document.getElementById('badge-hive').textContent = d.total.toLocaleString();
                if (!d.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-gray-400 text-sm">
                        <i class="fas fa-satellite-dish text-3xl mb-3 block"></i>No hive activity for the selected period.</td></tr>`;
                    document.getElementById('hivePageInfo').textContent = '';
                    document.getElementById('hivePageBtns').innerHTML = '';
                    return;
                }
                tbody.innerHTML = d.rows.map(r => {
                    const ts = new Date(r.timestamp).toLocaleString();
                    const hive = r.hive_name
                        ? `<span class="bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded text-xs font-mono">${esc(r.hive_code)}</span>
                           <span class="text-xs text-gray-500 ml-1">${esc(r.hive_name)}</span>`
                        : `<span class="text-gray-400">—</span>`;
                    return `<tr class="border-b border-gray-50 hover:bg-yellow-50/40 transition">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">${ts}</td>
                        <td class="px-5 py-3">${hive}</td>
                        <td class="px-5 py-3 whitespace-nowrap">${sevIconEl(r.severity)}${esc(r.event_type)}</td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs truncate" title="${esc(r.details)}">${esc(r.details)}</td>
                        <td class="px-5 py-3">${sevBadge(r.severity)}</td>
                    </tr>`;
                }).join('');
                renderPagination('hivePageInfo','hivePageBtns', d.page, d.pages, d.total, 50, 'hiveGoPage');
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
        document.querySelectorAll('#hivePresets .filter-btn').forEach(b => b.classList.remove('active'));
        hiveState.range = 'custom'; hiveState.customFrom = f; hiveState.customTo = t; hiveState.page = 1;
        loadHiveLog();
    }

    function hiveGoPage(p) { hiveState.page = p; loadHiveLog(); }

    // ── USER LOG ──────────────────────────────────────────────────
    function loadUserLog() {
        const uSel = document.getElementById('userUserFilter');
        userState.user_id = uSel ? uSel.value : 'all';
        const tbody = document.getElementById('userLogBody');
        tbody.innerHTML = `<tr><td colspan="5" class="py-10 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...</td></tr>`;

        const p = new URLSearchParams({ tab:'user', range:userState.range, user_id:userState.user_id, page:userState.page });
        if (userState.range === 'custom') { p.set('from', userState.customFrom); p.set('to', userState.customTo); }

        fetch('api/activity_filter.php?' + p)
            .then(r => r.json())
            .then(d => {
                if (!d.success) return;
                document.getElementById('badge-user').textContent = d.total.toLocaleString();
                if (!d.rows.length) {
                    tbody.innerHTML = `<tr><td colspan="5" class="py-12 text-center text-gray-400 text-sm">
                        <i class="fas fa-clipboard-list text-3xl mb-3 block"></i>No activity for the selected period.</td></tr>`;
                    document.getElementById('userPageInfo').textContent = '';
                    document.getElementById('userPageBtns').innerHTML = '';
                    return;
                }
                tbody.innerHTML = d.rows.map(r => {
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
                    return `<tr class="border-b border-gray-50 hover:bg-indigo-50/30 transition">
                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">${ts}</td>
                        <td class="px-5 py-3">${uname}</td>
                        <td class="px-5 py-3 whitespace-nowrap">${sevIconEl(r.severity)}${esc(r.event_type)}</td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs truncate" title="${esc(r.details)}">${esc(r.details)}</td>
                        <td class="px-5 py-3">${sevBadge(r.severity)}</td>
                    </tr>`;
                }).join('');
                renderPagination('userPageInfo','userPageBtns', d.page, d.pages, d.total, 50, 'userGoPage');
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
        document.querySelectorAll('#userPresets .filter-btn').forEach(b => b.classList.remove('active'));
        userState.range = 'custom'; userState.customFrom = f; userState.customTo = t; userState.page = 1;
        loadUserLog();
    }

    function userGoPage(p) { userState.page = p; loadUserLog(); }

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
