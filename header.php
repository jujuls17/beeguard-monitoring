<?php
date_default_timezone_set('Asia/Manila');
$current_date = date('l, F j, Y');
$current_time = date('g:i:s A');

$alert_count  = 0;
$chat_count   = 0;
$notes_count  = 0;
$recent_alerts = [];
$recent_chats  = [];
$recent_notes  = [];

if (isset($conn)) {
    $uid_h = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    // ── Sensor alerts (since last bell open) ──────────────────────
    $seen_clause = $uid_h ? "AND a.created_at > COALESCE((SELECT last_alerts_seen FROM user_settings WHERE user_id = $uid_h), '1970-01-01')" : '';
    $alert_result = $conn->query("SELECT COUNT(*) AS count FROM alerts a WHERE a.is_read = 0 $seen_clause");
    if ($alert_result) $alert_count = (int)$alert_result->fetch_assoc()['count'];
    $ra = $conn->query("SELECT a.*, h.name AS hive_name FROM alerts a LEFT JOIN hives h ON a.hive_id = h.id WHERE a.is_read = 0 $seen_clause ORDER BY a.created_at DESC LIMIT 4");
    if ($ra) while ($row = $ra->fetch_assoc()) $recent_alerts[] = $row;

    // ── Unread chat messages (id > user's last_read_id, not own messages) ──
    if ($uid_h) {
        $last_read = 0;
        $cr = $conn->query("SELECT last_read_id FROM chat_reads WHERE user_id = $uid_h");
        if ($cr && $crRow = $cr->fetch_assoc()) $last_read = (int)$crRow['last_read_id'];
        $chat_result = $conn->query("SELECT COUNT(*) AS count FROM group_messages WHERE id > $last_read AND user_id != $uid_h");
        if ($chat_result) $chat_count = (int)$chat_result->fetch_assoc()['count'];
        $rc = $conn->query("SELECT gm.id, gm.message, gm.created_at, u.username FROM group_messages gm JOIN users u ON gm.user_id = u.id WHERE gm.id > $last_read AND gm.user_id != $uid_h ORDER BY gm.created_at DESC LIMIT 3");
        if ($rc) while ($row = $rc->fetch_assoc()) $recent_chats[] = $row;
    }

    // ── New inspection notes (not own, since last bell open) ───────
    if ($uid_h) {
        $seen_notes = "AND n.created_at > COALESCE((SELECT last_alerts_seen FROM user_settings WHERE user_id = $uid_h), '1970-01-01')";
        $notes_result = $conn->query("SELECT COUNT(*) AS count FROM inspection_notes n WHERE n.user_id != $uid_h $seen_notes");
        if ($notes_result) $notes_count = (int)$notes_result->fetch_assoc()['count'];
        $rn = $conn->query("SELECT n.id, n.inspection_date, n.hive_condition, n.created_at, h.name AS hive_name, u.username FROM inspection_notes n JOIN hives h ON n.hive_id = h.id JOIN users u ON n.user_id = u.id WHERE n.user_id != $uid_h $seen_notes ORDER BY n.created_at DESC LIMIT 3");
        if ($rn) while ($row = $rn->fetch_assoc()) $recent_notes[] = $row;
    }
}

$total_notif = $alert_count + $chat_count + $notes_count;
?>

<header id="mainHeader" class="fixed top-0 right-0 left-64 text-white z-30" style="transition:left 0.35s cubic-bezier(0.4,0,0.2,1);">
    <div class="flex items-center justify-between px-6 h-16">

        <!-- Left -->
        <div class="flex items-center gap-2">
            <button id="sidebarToggle" onclick="toggleSidebar()"
                class="header-action-btn w-9 h-9 bg-black/15 rounded-xl flex items-center justify-center hover:bg-black/25 transition-all"
                title="Toggle sidebar">
                <i class="fas fa-bars" id="sidebarToggleIcon" style="transition:transform 0.3s ease;"></i>
            </button>
            <!-- Date: always visible; smaller on mobile -->
            <div>
                <p class="text-xs sm:text-sm font-semibold opacity-95 leading-tight"><?php echo $current_date; ?></p>
            </div>
        </div>

        <!-- Right -->
        <div class="flex items-center gap-2">
            <!-- Live clock: always visible; compact on mobile -->
            <div class="flex items-center gap-1.5 bg-black/15 rounded-xl px-2.5 sm:px-4 py-1.5">
                <i class="fas fa-clock text-yellow-200 text-xs sm:text-sm"></i>
                <span class="font-bold text-xs sm:text-sm tracking-wide" id="liveClock"><?php echo $current_time; ?></span>
            </div>

            <!-- Bell -->
            <div class="relative">
                <button id="notificationBtn" onclick="toggleNotifications()"
                    class="header-action-btn relative w-10 h-10 bg-black/15 rounded-xl flex items-center justify-center hover:bg-black/25 transition-all">
                    <i class="fas fa-bell text-lg"></i>
                    <?php if ($total_notif > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-black rounded-full w-5 h-5 flex items-center justify-center animate-bounce"
                          style="font-size:10px;">
                        <?php echo $total_notif; ?>
                    </span>
                    <?php endif; ?>
                </button>

                <!-- Dropdown -->
                <div id="notificationDropdown"
                     class="absolute right-0 mt-3 w-[360px] bg-white text-gray-900 hidden overflow-hidden z-50"
                     style="border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">

                    <div class="p-4 flex items-center justify-between"
                         style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                        <div>
                            <h3 class="font-bold text-white text-base flex items-center gap-2">
                                <i class="fas fa-bell"></i> Notifications
                            </h3>
                            <p class="text-yellow-100 text-xs mt-0.5">
                                <?php echo $total_notif; ?> new notification<?php echo $total_notif != 1 ? 's' : ''; ?>
                                <?php if ($alert_count)  echo " · {$alert_count} alert" . ($alert_count!=1?'s':''); ?>
                                <?php if ($chat_count)   echo " · {$chat_count} chat" . ($chat_count!=1?'s':''); ?>
                                <?php if ($notes_count)  echo " · {$notes_count} note" . ($notes_count!=1?'s':''); ?>
                            </p>
                        </div>
                        <a href="alerts.php" onclick="markAlertsRead()"
                           class="text-xs bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg font-semibold transition-all">
                            View all
                        </a>
                    </div>

                    <div class="max-h-96 overflow-y-auto">
                        <?php if ($total_notif === 0): ?>
                        <div class="p-8 text-center">
                            <div class="w-16 h-16 bg-green-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-check-circle text-green-500 text-3xl"></i>
                            </div>
                            <p class="font-semibold text-gray-700">All caught up!</p>
                            <p class="text-sm text-gray-400 mt-1">No new notifications</p>
                        </div>
                        <?php else: ?>

                        <?php if (!empty($recent_alerts)): ?>
                        <div class="px-4 pt-3 pb-1">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-exclamation-triangle text-red-400"></i> Hive Alerts
                            </p>
                        </div>
                        <div class="divide-y divide-gray-50">
                        <?php foreach ($recent_alerts as $a):
                            $sc = $a['severity'] === 'critical' ? 'red' : ($a['severity'] === 'warning' ? 'yellow' : 'blue');
                        ?>
                        <a href="alerts.php" onclick="markAlertsRead()" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors block">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
                                 style="background:<?php echo $sc==='red'?'#FEE2E2':($sc==='yellow'?'#FEF3C7':'#DBEAFE'); ?>">
                                <i class="fas fa-exclamation-triangle text-xs"
                                   style="color:<?php echo $sc==='red'?'#DC2626':($sc==='yellow'?'#D97706':'#2563EB'); ?>"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($a['hive_name'] ?? 'System'); ?></p>
                                <p class="text-xs text-gray-500 mt-0.5 truncate"><?php echo htmlspecialchars($a['message']); ?></p>
                                <p class="text-xs text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i><?php echo time_elapsed_string($a['created_at']); ?></p>
                            </div>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg flex-shrink-0 mt-0.5"
                                  style="background:<?php echo $sc==='red'?'#FEE2E2':($sc==='yellow'?'#FEF3C7':'#DBEAFE'); ?>;
                                         color:<?php echo $sc==='red'?'#991B1B':($sc==='yellow'?'#92400E':'#1E40AF'); ?>">
                                <?php echo strtoupper($a['severity']); ?>
                            </span>
                        </a>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($recent_chats)): ?>
                        <div class="px-4 pt-3 pb-1 <?php echo !empty($recent_alerts)?'border-t border-gray-100':''; ?>">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-comments text-blue-400"></i> New Chat Messages
                            </p>
                        </div>
                        <div class="divide-y divide-gray-50">
                        <?php foreach ($recent_chats as $c): ?>
                        <a href="group_chat.php" onclick="markAlertsRead()" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors block">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5" style="background:#DBEAFE;">
                                <i class="fas fa-comment text-xs" style="color:#2563EB;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($c['username']); ?></p>
                                <p class="text-xs text-gray-500 mt-0.5 truncate"><?php echo htmlspecialchars($c['message']); ?></p>
                                <p class="text-xs text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i><?php echo time_elapsed_string($c['created_at']); ?></p>
                            </div>
                            <?php if ($chat_count > 1 && $c === reset($recent_chats)): ?>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-lg flex-shrink-0 mt-0.5" style="background:#DBEAFE;color:#1E40AF;">
                                <?php echo $chat_count; ?> new
                            </span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($recent_notes)): ?>
                        <div class="px-4 pt-3 pb-1 <?php echo (!empty($recent_alerts)||!empty($recent_chats))?'border-t border-gray-100':''; ?>">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-clipboard-list text-green-500"></i> New Inspection Notes
                            </p>
                        </div>
                        <div class="divide-y divide-gray-50">
                        <?php foreach ($recent_notes as $n):
                            $condColor = $n['hive_condition'] === 'excellent' ? '#16A34A' : ($n['hive_condition'] === 'good' ? '#2563EB' : ($n['hive_condition'] === 'poor' || $n['hive_condition'] === 'critical' ? '#DC2626' : '#D97706'));
                        ?>
                        <a href="inspection_notes.php" onclick="markAlertsRead()" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors block">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5" style="background:#DCFCE7;">
                                <i class="fas fa-clipboard-check text-xs" style="color:#16A34A;"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm text-gray-900"><?php echo htmlspecialchars($n['hive_name']); ?></p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?php echo htmlspecialchars($n['username']); ?> added a note
                                    · <span style="color:<?php echo $condColor; ?>;font-weight:600;"><?php echo ucfirst($n['hive_condition']); ?></span>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5"><i class="far fa-clock mr-1"></i><?php echo time_elapsed_string($n['created_at']); ?></p>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logout -->
            <a href="logout.php"
               class="header-action-btn w-10 h-10 bg-black/15 rounded-xl flex items-center justify-center hover:bg-red-500 transition-all"
               title="Logout">
                <i class="fas fa-sign-out-alt text-lg"></i>
            </a>
        </div>
    </div>
</header>

<!-- Mobile sidebar overlay — click to close -->
<div id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Header spacer -->
<div class="h-16"></div>


<script>
/* Live clock */
setInterval(() => {
    const el = document.getElementById('liveClock');
    if (el) el.textContent = new Date().toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',second:'2-digit',hour12:true});
}, 1000);

/* Mark all notifications as seen — updates last_alerts_seen timestamp */
function markAlertsRead() {
    fetch('api/mark_alerts_seen.php', {method: 'POST'}).catch(() => {});
    // Remove badge immediately
    document.querySelectorAll('#notificationBtn span').forEach(el => el.remove());
    // Close dropdown
    const dd = document.getElementById('notificationDropdown');
    if (dd) dd.classList.add('hidden');
}

/* Notification toggle */
function toggleNotifications() {
    const d = document.getElementById('notificationDropdown');
    d.classList.toggle('hidden');
}
document.addEventListener('click', e => {
    const btn = document.getElementById('notificationBtn');
    const d   = document.getElementById('notificationDropdown');
    if (btn && d && !btn.contains(e.target) && !d.contains(e.target)) d.classList.add('hidden');
});

/* Sidebar toggle */
let _sidebarOpen = window.innerWidth >= 1024;

function _isMobile() { return window.innerWidth < 1024; }

function _applyDesktopLayout(open) {
    const sb   = document.getElementById('mainSidebar');
    const hdr  = document.getElementById('mainHeader');
    const main = document.querySelector('main');
    const icon = document.getElementById('sidebarToggleIcon');
    if (sb)   sb.style.transform    = open ? 'translateX(0)' : 'translateX(-100%)';
    if (hdr)  hdr.style.left        = open ? '256px' : '0';
    if (main) main.style.marginLeft = open ? '256px' : '0';
    if (icon) icon.style.transform  = open ? 'rotate(0deg)' : 'rotate(180deg)';
}

function _clearInlineLayout() {
    const sb   = document.getElementById('mainSidebar');
    const hdr  = document.getElementById('mainHeader');
    const main = document.querySelector('main');
    if (sb)   sb.style.transform    = '';
    if (hdr)  hdr.style.left        = '';
    if (main) main.style.marginLeft = '';
}

function toggleSidebar() {
    _sidebarOpen = !_sidebarOpen;
    if (_isMobile()) {
        _clearInlineLayout();
        document.body.classList.toggle('sidebar-open', _sidebarOpen);
    } else {
        document.body.classList.remove('sidebar-open');
        _applyDesktopLayout(_sidebarOpen);
    }
}

/* Handle resize between mobile and desktop */
let _prevMobile = _isMobile();
window.addEventListener('resize', function () {
    const nowMobile = _isMobile();
    if (nowMobile === _prevMobile) return;
    _prevMobile = nowMobile;
    if (!nowMobile) {
        document.body.classList.remove('sidebar-open');
        _applyDesktopLayout(_sidebarOpen);
    } else {
        _sidebarOpen = false;
        document.body.classList.remove('sidebar-open');
        _clearInlineLayout();
    }
}, { passive: true });

/* On mobile page load: clear inline styles so CSS media query takes over */
if (_isMobile()) _clearInlineLayout();


// Synchronized page refresh — all devices reload at the same clock boundary
// (every 15s aligned to wall clock: :00, :15, :30, :45)
(function () {
    const INTERVAL = 15000;
    function doReload() {
        const modalOpen = document.querySelector('.modal.active');
        const typing    = document.activeElement && ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName);
        if (modalOpen || typing) return;
        // If the current page provides its own refresh function, use it instead of a hard reload
        // so scroll position, open panels, and filter state are preserved
        if (typeof window.__refreshPage === 'function') {
            window.__refreshPage();
        } else {
            location.reload();
        }
    }
    // Wait until the next 15s boundary, then tick every 15s
    const msUntilNext = INTERVAL - (Date.now() % INTERVAL);
    setTimeout(() => { doReload(); setInterval(doReload, INTERVAL); }, msUntilNext);
})();
</script>
