<?php
$current_page = basename($_SERVER['PHP_SELF']);

$_unreadAlerts  = 0;
$_totalHives    = 0;
$_unreadChat    = 0;
$_pendingDevices = 0;
$_systemOnline  = false;
$_activeHives   = 0;
if (isset($conn)) {
    $r = $conn->query("SELECT COUNT(*) as c FROM alerts WHERE is_read = 0");
    if ($r) $_unreadAlerts = (int)$r->fetch_assoc()['c'];
    $r2 = $conn->query("SELECT COUNT(*) as c FROM hives WHERE status = 'active'");
    if ($r2) $_totalHives = (int)$r2->fetch_assoc()['c'];
    // Check if any sensor data received in last 5 minutes
    $r3 = $conn->query("SELECT COUNT(DISTINCT hive_id) as c FROM sensor_data WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    if ($r3) { $row3 = $r3->fetch_assoc(); $_activeHives = (int)$row3['c']; $_systemOnline = $_activeHives > 0; }
    if (isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $rc  = $conn->query("SELECT COUNT(*) AS c FROM group_messages
                             WHERE id > COALESCE((SELECT last_read_id FROM chat_reads WHERE user_id = $uid), 0)
                             AND user_id != $uid");
        if ($rc) $_unreadChat = (int)$rc->fetch_assoc()['c'];
    }
    // Pending unregistered ESP32s (admin only)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $rd = $conn->query("SELECT COUNT(*) as c FROM detected_devices WHERE device_id NOT IN (SELECT COALESCE(device_id,'') FROM edge_devices)");
        if ($rd) $_pendingDevices = (int)$rd->fetch_assoc()['c'];
    }
}

function navLink($page, $current, $href, $icon, $label, $badge = null) {
    $active = $page === $current ? 'active' : '';
    $badgeHtml = '';
    if ($badge !== null && $badge > 0) {
        if ($icon === 'fa-bell') {
            $color = 'bg-red-500 text-white';
        } elseif ($icon === 'fa-microchip') {
            $color = 'bg-blue-500 text-white';
        } else {
            $color = 'bg-yellow-400 text-gray-900';
        }
        $badgeHtml = "<span class='sidebar-badge ml-auto {$color} text-xs px-2 py-0.5 rounded-full font-bold min-w-[20px] text-center'>{$badge}</span>";
    }
    return "<a href='{$href}' title='{$label}' class='sidebar-nav-link {$active} flex items-center gap-3 px-4 py-2.5 mb-1 text-sm font-medium'>
        <i class='fas {$icon} w-5 text-center text-yellow-200/60'></i>
        <span class='sidebar-label text-yellow-50'>{$label}</span>
        {$badgeHtml}
    </a>";
}
?>
<!-- Global CSS + Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/beeguard.css">

<aside id="mainSidebar" class="fixed left-0 top-0 bottom-0 w-64 overflow-y-auto z-40 flex flex-col">

    <!-- Logo -->
    <div class="px-5 py-6 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="sidebar-logo-wrap w-11 h-11 flex items-center justify-center text-xl flex-shrink-0">
                🐝
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-white font-bold text-lg leading-tight tracking-wide">BeeGuard</h1>
                <p class="text-yellow-400/70 text-xs font-medium">IoT Monitor</p>
            </div>
            <!-- Close button (visible on mobile only) -->
            <button onclick="toggleSidebar()"
                class="lg:hidden w-8 h-8 flex items-center justify-center rounded-lg text-yellow-400/50 hover:text-white hover:bg-yellow-800/30 transition-all flex-shrink-0"
                title="Close menu">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
    </div>

    <!-- Divider -->
    <div class="mx-5 border-t border-yellow-800/20 mb-3"></div>

    <!-- Navigation -->
    <nav class="px-3 flex-1">
        <p class="text-yellow-600/50 text-xs font-bold uppercase tracking-widest px-4 mb-2">Main</p>

        <?php echo navLink('dashboard.php',       $current_page, 'dashboard.php',       'fa-home',           'Dashboard'); ?>
        <?php echo navLink('hives.php',           $current_page, 'hives.php',           'fa-layer-group',    'Live Hives'); ?>
        <?php echo navLink('analytics.php',       $current_page, 'analytics.php',       'fa-chart-line',     'Analytics'); ?>
        <?php echo navLink('alerts.php',          $current_page, 'alerts.php',          'fa-bell',           'Alerts', $_unreadAlerts ?: null); ?>

        <p class="text-yellow-600/50 text-xs font-bold uppercase tracking-widest px-4 mb-2 mt-5">Monitoring</p>

        <?php echo navLink('activity.php',        $current_page, 'activity.php',        'fa-list-alt',       'Activity Log'); ?>
        <?php echo navLink('devices.php',         $current_page, 'devices.php',         'fa-microchip',      'Edge Devices', $_pendingDevices ?: null); ?>
        <?php echo navLink('export.php',          $current_page, 'export.php',          'fa-download',       'Data Export'); ?>
        <?php echo navLink('inspection_notes.php',$current_page, 'inspection_notes.php','fa-clipboard-list', 'Inspection Notes'); ?>
        <?php echo navLink('chat.php',            $current_page, 'chat.php',            'fa-comments',       'Group Chat', $_unreadChat ?: null); ?>

        <div class="border-t border-yellow-800/20 my-4"></div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <p class="text-yellow-600/50 text-xs font-bold uppercase tracking-widest px-4 mb-2">Admin</p>
        <?php echo navLink('users.php',           $current_page, 'users.php',           'fa-users',          'Manage Users'); ?>
        <?php echo navLink('settings.php',        $current_page, 'settings.php',        'fa-cog',            'Settings'); ?>
        <div class="border-t border-yellow-800/20 my-4"></div>
        <?php endif; ?>

        <?php echo navLink('help.php',            $current_page, 'help.php',            'fa-question-circle','Help & Docs'); ?>
    </nav>

    <!-- Bottom Panel -->
    <div class="flex-shrink-0 p-4 mt-2 border-t border-yellow-800/20">

        <!-- System Status -->
        <div class="sidebar-status p-3 mb-3">
            <div class="flex items-center gap-2 mb-1">
                <?php if ($_systemOnline): ?>
                    <span class="online-dot w-2 h-2 bg-green-400 rounded-full flex-shrink-0 relative"></span>
                    <span class="text-xs font-bold text-green-300">System Online</span>
                <?php elseif ($_totalHives > 0): ?>
                    <span class="w-2 h-2 bg-yellow-400 rounded-full flex-shrink-0 animate-pulse"></span>
                    <span class="text-xs font-bold text-yellow-300">No Signal</span>
                <?php else: ?>
                    <span class="w-2 h-2 bg-red-400 rounded-full flex-shrink-0"></span>
                    <span class="text-xs font-bold text-red-300">No Hives</span>
                <?php endif; ?>
            </div>
            <p class="text-xs text-yellow-300/60">
                <?php if ($_systemOnline): ?>
                    <?php echo $_activeHives; ?>/<?php echo $_totalHives; ?> hive<?php echo $_totalHives !== 1 ? 's' : ''; ?> sending data
                <?php elseif ($_totalHives > 0): ?>
                    <?php echo $_totalHives; ?> hive<?php echo $_totalHives !== 1 ? 's' : ''; ?> — ESP32 offline
                <?php else: ?>
                    No active hives configured
                <?php endif; ?>
            </p>
        </div>

        <!-- User -->
        <div class="flex items-center gap-3">
            <?php
            $displayName = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'User');
            $initials    = strtoupper(substr($displayName, 0, 1));
            $parts = explode(' ', trim($displayName));
            if (count($parts) > 1) $initials = strtoupper(substr($parts[0],0,1) . substr($parts[count($parts)-1],0,1));
            $roleLabel = ($_SESSION['role'] ?? 'beekeeper') === 'admin' ? 'Administrator' : 'Beekeeper';
            ?>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0"
                 style="background:linear-gradient(135deg,#F59E0B,#F97316);color:#fff;box-shadow:0 4px 12px rgba(245,158,11,0.4)">
                <?php echo $initials; ?>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($displayName); ?></p>
                <p class="text-xs text-yellow-400/60"><?php echo $roleLabel; ?></p>
            </div>
            <a href="logout.php" title="Logout"
               class="w-8 h-8 flex items-center justify-center rounded-lg text-yellow-400/50 hover:text-red-400 hover:bg-red-900/20 transition-all">
                <i class="fas fa-sign-out-alt text-sm"></i>
            </a>
        </div>
    </div>
</aside>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<!-- Admin Delete Confirmation Modal (global) -->
<div id="adminDeleteModal" class="hidden fixed inset-0 bg-black/60 z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-sm p-6 shadow-2xl">
        <!-- Step 1: Yes / No -->
        <div id="adminDeleteStep1">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500 text-lg"></i>
                </div>
                <div>
                    <h3 id="adminDeleteTitle" class="font-bold text-gray-900 text-base">Confirm Delete</h3>
                    <p class="text-xs text-gray-400">This action cannot be undone</p>
                </div>
            </div>
            <p id="adminDeleteMsg" class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3 mb-5"></p>
            <div class="flex gap-3">
                <button onclick="closeAdminDeleteModal()"
                    class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">
                    No, Cancel
                </button>
                <button id="adminDeleteYesBtn" onclick="adminDeleteGoStep2()"
                    class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold transition">
                    Yes, Delete
                </button>
            </div>
        </div>
        <!-- Step 2: Password -->
        <div id="adminDeleteStep2" class="hidden">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-11 h-11 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-lock text-orange-500 text-lg"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-900 text-base">Verify Identity</h3>
                    <p class="text-xs text-gray-400">Enter your admin password to confirm</p>
                </div>
            </div>
            <input id="adminDeletePw" type="password" placeholder="Enter your admin password"
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:outline-none focus:border-red-400 text-sm mb-1">
            <p id="adminDeletePwErr" class="hidden text-xs text-red-600 mb-1 px-1"></p>
            <div class="flex gap-3 mt-4">
                <button onclick="closeAdminDeleteModal()"
                    class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button onclick="adminDeleteConfirm()" id="adminDeleteConfirmBtn"
                    class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
                    <i id="adminDeleteConfirmIcon" class="fas fa-trash text-xs"></i> <span id="adminDeleteConfirmLabel">Confirm Delete</span>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
function adminDelete(message, callback, actionLabel) {
    const label = actionLabel || 'Delete';
    const icon  = label.toLowerCase() === 'delete' ? 'fa-trash' : 'fa-check-circle';
    document.getElementById('adminDeleteTitle').textContent      = 'Confirm ' + label;
    document.getElementById('adminDeleteYesBtn').textContent     = 'Yes, ' + label;
    document.getElementById('adminDeleteConfirmLabel').textContent = 'Confirm ' + label;
    document.getElementById('adminDeleteConfirmIcon').className  = 'fas ' + icon + ' text-xs';
    document.getElementById('adminDeleteMsg').textContent = message;
    document.getElementById('adminDeleteStep1').classList.remove('hidden');
    document.getElementById('adminDeleteStep2').classList.add('hidden');
    document.getElementById('adminDeletePw').value = '';
    document.getElementById('adminDeletePwErr').classList.add('hidden');
    document.getElementById('adminDeleteModal').classList.remove('hidden');
    window._adminDeleteCb = callback;
}
function adminDeleteGoStep2() {
    document.getElementById('adminDeleteStep1').classList.add('hidden');
    document.getElementById('adminDeleteStep2').classList.remove('hidden');
    setTimeout(() => document.getElementById('adminDeletePw').focus(), 50);
}
function closeAdminDeleteModal() {
    document.getElementById('adminDeleteModal').classList.add('hidden');
    window._adminDeleteCb = null;
}
function adminDeleteConfirm() {
    const pw    = document.getElementById('adminDeletePw').value;
    const errEl = document.getElementById('adminDeletePwErr');
    const btn   = document.getElementById('adminDeleteConfirmBtn');
    errEl.classList.add('hidden');
    if (!pw) { errEl.textContent = 'Please enter your password.'; errEl.classList.remove('hidden'); return; }
    const confirmLabel = document.getElementById('adminDeleteConfirmLabel').textContent;
    const confirmIcon  = document.getElementById('adminDeleteConfirmIcon').className;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Verifying...';
    fetch('api/verify_password.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'password=' + encodeURIComponent(pw)
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = `<i class="${confirmIcon}"></i> ${confirmLabel}`;
        if (d.success) {
            const cb = window._adminDeleteCb;
            closeAdminDeleteModal();
            if (cb) cb();
        } else {
            errEl.textContent = d.message || 'Incorrect password.';
            errEl.classList.remove('hidden');
            document.getElementById('adminDeletePw').value = '';
            document.getElementById('adminDeletePw').focus();
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = `<i class="${confirmIcon}"></i> ${confirmLabel}`;
        errEl.textContent = 'Verification failed. Try again.';
        errEl.classList.remove('hidden');
    });
}
document.addEventListener('click', e => {
    const m = document.getElementById('adminDeleteModal');
    if (m && e.target === m) closeAdminDeleteModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeAdminDeleteModal();
    if (e.key === 'Enter' && document.getElementById('adminDeleteStep2') &&
        !document.getElementById('adminDeleteStep2').classList.contains('hidden')) {
        adminDeleteConfirm();
    }
});
</script>
<?php endif; ?>
