<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

$currentUserId = (int)$_SESSION['user_id'];
$isAdmin       = isAdmin();
$myName        = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($_SESSION['username'] ?? 'User');
$parts         = explode(' ', trim($myName));
$myInitials    = strtoupper(substr($parts[0], 0, 1) . (count($parts) > 1 ? substr($parts[count($parts)-1], 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Group Chat — BeeGuard IoT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        #chatBox::-webkit-scrollbar { width: 4px; }
        #chatBox::-webkit-scrollbar-track { background: transparent; }
        #chatBox::-webkit-scrollbar-thumb { background: #E2E8F0; border-radius: 4px; }
        .msg-row:hover .msg-delete { opacity: 1 !important; }
        .msg-delete { opacity: 0; transition: opacity 0.15s; }

        /* ── Mobile: pin input bar to bottom ── */
        @media (max-width:767px) {
            #chatInputBar {
                position: fixed !important;
                bottom: 0; left: 0; right: 0;
                z-index: 20;
                border-top: 1px solid #f1f5f9;
            }
            #chatBox {
                padding-bottom: 72px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50" id="chatBody" style="overflow:hidden;height:100dvh;">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main id="chatPage" style="margin-left:256px; height:calc(100dvh - 4rem); display:flex; flex-direction:column;">

        <!-- Page Header -->
        <div class="page-header px-4 sm:px-8 py-2 sm:py-4 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-2 sm:gap-3">
                <span class="w-7 h-7 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center"
                      style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                    <i class="fas fa-comments text-white text-xs sm:text-base"></i>
                </span>
                <div>
                    <h1 class="text-sm sm:text-xl font-bold text-gray-900">Group Chat</h1>
                    <p class="hidden sm:block text-xs text-gray-400 font-medium" id="memberSubtitle">Admin & Beekeeper Communication</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <button id="membersBtn" onclick="toggleMemberPanel()"
                    class="flex items-center gap-1 sm:gap-2 bg-gray-100 hover:bg-gray-200 rounded-xl px-2 sm:px-3 py-1 sm:py-1.5 text-xs font-semibold text-gray-600 transition">
                    <i class="fas fa-users text-xs"></i>
                    <span id="memberCount">—</span>
                </button>
                <div id="onlineIndicator" class="flex items-center gap-1 sm:gap-2 bg-green-50 border border-green-200 rounded-xl px-2 sm:px-3 py-1 sm:py-1.5">
                    <span class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-green-400 rounded-full animate-pulse"></span>
                    <span class="text-xs font-semibold text-green-700">Live</span>
                </div>
                <div id="msgCount" class="text-xs text-gray-400 px-2 py-1 bg-white border rounded-xl hidden"></div>
            </div>
        </div>

        <!-- Chat area + optional member panel -->
        <div class="flex flex-1 overflow-hidden">

            <!-- Chat Column -->
            <div class="flex flex-col flex-1 overflow-hidden">

                <!-- Load Older button -->
                <div id="loadOlderWrap" class="hidden flex-shrink-0 flex justify-center py-2 bg-gray-50 border-b border-gray-100">
                    <button onclick="loadOlderMessages()"
                        class="flex items-center gap-2 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 hover:bg-yellow-100 rounded-full px-4 py-1.5 font-semibold transition">
                        <i class="fas fa-chevron-up"></i> Load older messages
                    </button>
                </div>

                <!-- Messages -->
                <div id="chatBox" class="flex-1 overflow-y-auto px-6 py-4" style="background:#f8fafc;">
                    <div class="text-center py-12">
                        <i class="fas fa-spinner fa-spin text-yellow-400 text-2xl"></i>
                        <p class="text-sm text-gray-400 mt-3">Loading messages…</p>
                    </div>
                </div>

                <!-- Input Bar -->
                <div id="chatInputBar" class="flex-shrink-0 bg-white border-t border-gray-100 px-3 sm:px-6 py-2 sm:py-3"
                     style="padding-bottom:max(0.5rem, env(safe-area-inset-bottom));">
                    <form id="chatForm" class="flex items-center gap-2 sm:gap-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center font-bold text-sm flex-shrink-0 text-white"
                             style="background:<?php echo $isAdmin ? 'linear-gradient(135deg,#8B5CF6,#6D28D9)' : 'linear-gradient(135deg,#F59E0B,#F97316)'; ?>">
                            <?php echo htmlspecialchars($myInitials); ?>
                        </div>
                        <div class="flex-1 relative">
                            <textarea id="msgInput"
                                   placeholder="Type a message…"
                                   maxlength="1000"
                                   rows="1"
                                   autocomplete="off"
                                   class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 sm:px-4 py-2 sm:py-2.5 text-sm focus:outline-none focus:ring-2 focus:border-transparent transition resize-none overflow-hidden"
                                   style="--tw-ring-color:#F59E0B; max-height:100px;"></textarea>
                            <span id="charCount" class="hidden absolute right-3 bottom-2 text-xs text-gray-300"></span>
                        </div>
                        <button type="submit"
                                class="flex-shrink-0 w-11 h-11 sm:px-5 sm:py-2.5 sm:w-auto sm:h-auto rounded-xl text-white font-semibold transition hover:opacity-90 active:scale-95 flex items-center justify-center"
                                style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                            <i class="fas fa-paper-plane text-base"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Member Sidebar Panel -->
            <div id="memberPanel" class="hidden flex-shrink-0 w-56 bg-white border-l border-gray-100 overflow-y-auto flex-col">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Participants</p>
                </div>
                <div id="memberList" class="p-3 space-y-1">
                    <div class="text-xs text-gray-400 text-center py-4">Loading…</div>
                </div>
            </div>

        </div>
    </main>

<script>
const ME        = <?php echo $currentUserId; ?>;
const IS_ADMIN  = <?php echo $isAdmin ? 'true' : 'false'; ?>;
const MY_ROLE   = '<?php echo $isAdmin ? 'admin' : 'beekeeper'; ?>';

let lastId      = 0;
let firstId     = null;
let atBottom    = true;
let totalLoaded = 0;
let hasOlder    = false;
let loadingOlder = false;

const chatBox = document.getElementById('chatBox');

chatBox.addEventListener('scroll', () => {
    atBottom = (chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight) < 80;
});

function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderNl(s) {
    return escHtml(s).replace(/\n/g, '<br>');
}

function formatTime(ts) {
    const d   = new Date(ts.replace(' ', 'T'));
    const now = new Date();
    const diff = (now - d) / 1000;
    if (diff < 60)    return 'just now';
    if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
    return d.toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ' ' +
           d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
}

function formatFullTime(ts) {
    const d = new Date(ts.replace(' ', 'T'));
    return d.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric', year:'numeric'}) +
           ' at ' + d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
}

function dateLabel(ts) {
    const d   = new Date(ts.replace(' ', 'T'));
    const now = new Date();
    const today     = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today - 86400000);
    const msgDay    = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    if (+msgDay === +today)     return 'Today';
    if (+msgDay === +yesterday) return 'Yesterday';
    return d.toLocaleDateString('en-US', {weekday:'long', month:'long', day:'numeric'});
}

function getInitials(fullName, username) {
    if (fullName && fullName.trim()) {
        const parts = fullName.trim().split(' ');
        return (parts[0][0] + (parts.length > 1 ? parts[parts.length-1][0] : '')).toUpperCase();
    }
    return (username || 'U').slice(0, 2).toUpperCase();
}

function buildDateSep(dateStr) {
    const sep = document.createElement('div');
    sep.className = 'flex items-center gap-3 my-4 date-sep';
    sep.dataset.date = dateStr;
    sep.innerHTML = `<div class="flex-1 h-px bg-gray-200"></div>
        <span class="text-xs text-gray-400 font-medium whitespace-nowrap">${escHtml(dateStr)}</span>
        <div class="flex-1 h-px bg-gray-200"></div>`;
    return sep;
}

function buildMessageEl(m) {
    const isMine     = parseInt(m.user_id) === ME;
    const isAdminMsg = m.role === 'admin';
    const initials   = getInitials(m.full_name, m.username);
    const name       = m.full_name || m.username;
    const canDelete  = isMine || IS_ADMIN;

    const avatarStyle = isAdminMsg
        ? 'background:linear-gradient(135deg,#8B5CF6,#6D28D9)'
        : 'background:linear-gradient(135deg,#F59E0B,#F97316)';

    const badge = isAdminMsg
        ? `<span class="inline-flex items-center gap-1 text-xs px-1.5 py-0.5 rounded font-bold bg-purple-100 text-purple-700"><i class="fas fa-shield-alt text-xs"></i>Admin</span>`
        : `<span class="text-xs px-1.5 py-0.5 rounded font-semibold bg-yellow-100 text-yellow-700">Beekeeper</span>`;

    const bubbleClass = isMine
        ? 'text-white rounded-2xl rounded-tr-sm px-4 py-2.5'
        : 'bg-white border border-gray-100 text-gray-800 rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm';
    const bubbleStyle = isMine
        ? 'style="background:linear-gradient(135deg,#F59E0B,#F97316)"'
        : '';

    const deleteBtn = canDelete
        ? `<button class="msg-delete flex-shrink-0 w-6 h-6 flex items-center justify-center rounded-lg text-gray-300 hover:text-red-400 hover:bg-red-50 transition self-start mt-2"
                   onclick="deleteMsg(${m.id}, this)" title="Delete message">
               <i class="fas fa-trash text-xs"></i>
           </button>`
        : '';

    const el = document.createElement('div');
    el.id = 'msg-' + m.id;
    el.className = 'flex gap-2 mb-3 msg-row ' + (isMine ? 'flex-row-reverse' : '');
    el.innerHTML = `
        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-xs flex-shrink-0 text-white mt-0.5"
             style="${avatarStyle}">
            ${escHtml(initials)}
        </div>
        <div class="max-w-[70%] flex flex-col gap-1 ${isMine ? 'items-end' : 'items-start'}">
            <div class="flex items-center gap-1.5 ${isMine ? 'flex-row-reverse' : ''}">
                <span class="text-xs font-semibold text-gray-700">${escHtml(name)}</span>
                ${badge}
            </div>
            <div class="${bubbleClass} text-sm leading-relaxed break-words" ${bubbleStyle}>
                ${renderNl(m.message)}
            </div>
            <span class="text-xs text-gray-400 mt-0.5" title="${escHtml(formatFullTime(m.created_at))}">${formatTime(m.created_at)}</span>
        </div>
        ${deleteBtn}`;
    return el;
}

// Track which dates we've shown so we can insert separators
let lastRenderedDate = null;

function renderMessages(msgs, isInitial, prepend = false) {
    if (isInitial) {
        chatBox.innerHTML = '';
        totalLoaded = 0;
        lastRenderedDate = null;
        firstId = null;
        lastId  = 0;
    }

    if (isInitial && msgs.length === 0) {
        chatBox.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full text-center py-16">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl mb-4"
                     style="background:linear-gradient(135deg,#FEF3C7,#FDE68A);">💬</div>
                <p class="font-semibold text-gray-600">No messages yet</p>
                <p class="text-sm text-gray-400 mt-1">Be the first to say hello!</p>
            </div>`;
        return;
    }

    if (prepend) {
        // Insert older messages at top — preserve scroll position
        const prevScrollH = chatBox.scrollHeight;

        const fragment = document.createDocumentFragment();
        let prependDate = null;

        msgs.forEach(m => {
            if (document.getElementById('msg-' + m.id)) return;
            const dl = dateLabel(m.created_at);
            if (prependDate !== dl) {
                fragment.appendChild(buildDateSep(dl));
                prependDate = dl;
            }
            fragment.appendChild(buildMessageEl(m));
            if (firstId === null || m.id < firstId) firstId = parseInt(m.id);
            totalLoaded++;
        });

        const firstChild = chatBox.firstChild;
        chatBox.insertBefore(fragment, firstChild);
        chatBox.scrollTop = chatBox.scrollHeight - prevScrollH;
        updateMsgCounter();
        return;
    }

    const wasAtBottom = atBottom;
    const fragment = document.createDocumentFragment();

    msgs.forEach(m => {
        if (document.getElementById('msg-' + m.id)) return;

        const dl = dateLabel(m.created_at);
        if (dl !== lastRenderedDate) {
            fragment.appendChild(buildDateSep(dl));
            lastRenderedDate = dl;
        }

        fragment.appendChild(buildMessageEl(m));
        lastId = Math.max(lastId, parseInt(m.id));
        if (firstId === null) firstId = parseInt(m.id);
        totalLoaded++;
    });

    chatBox.appendChild(fragment);
    updateMsgCounter();

    if (isInitial || wasAtBottom) {
        chatBox.scrollTop = chatBox.scrollHeight;
        atBottom = true;
    }
}

function updateMsgCounter() {
    const counter = document.getElementById('msgCount');
    if (counter) {
        counter.textContent = totalLoaded + ' message' + (totalLoaded !== 1 ? 's' : '');
        counter.classList.remove('hidden');
    }
}

// ── Load older messages ─────────────────────────────────────
function loadOlderMessages() {
    if (loadingOlder || firstId === null) return;
    loadingOlder = true;
    const btn = document.querySelector('#loadOlderWrap button');
    if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Loading…';

    fetch('api/chat.php?action=messages&before=' + firstId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            if (data.messages.length === 0) {
                document.getElementById('loadOlderWrap').classList.add('hidden');
                hasOlder = false;
                return;
            }
            renderMessages(data.messages, false, true);
            if (data.messages.length < 50) {
                document.getElementById('loadOlderWrap').classList.add('hidden');
                hasOlder = false;
            }
        })
        .catch(() => {})
        .finally(() => {
            loadingOlder = false;
            const b = document.querySelector('#loadOlderWrap button');
            if (b) b.innerHTML = '<i class="fas fa-chevron-up"></i> Load older messages';
        });
}

// ── Initial load ────────────────────────────────────────────
function initialLoad() {
    fetch('api/chat.php?action=messages')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                chatBox.innerHTML = `<div class="text-center py-12 text-red-400 text-sm">Failed to load messages.</div>`;
                return;
            }
            renderMessages(data.messages, true);
            if (data.has_older) {
                hasOlder = true;
                document.getElementById('loadOlderWrap').classList.remove('hidden');
            }
            markRead();
        })
        .catch(() => {
            chatBox.innerHTML = `<div class="text-center py-12 text-red-400 text-sm">Connection error.</div>`;
        });
}

// ── Poll for new messages ───────────────────────────────────
function pollMessages() {
    if (lastId === 0 && totalLoaded === 0) return;
    fetch('api/chat.php?action=messages&after=' + lastId)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.messages.length) return;
            renderMessages(data.messages, false);
            markRead();
        })
        .catch(() => {});
}

// ── Mark read ───────────────────────────────────────────────
function markRead() {
    fetch('api/chat.php?action=mark_read', { method: 'POST' }).catch(() => {});
}

// ── Delete message ──────────────────────────────────────────
function deleteMsg(id, btn) {
    const doDelete = () => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('api/chat.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const el = document.getElementById('msg-' + id);
                    if (el) el.remove();
                    totalLoaded = Math.max(0, totalLoaded - 1);
                    updateMsgCounter();
                }
            })
            .catch(() => {});
    };
    if (typeof window.adminDelete === 'function') {
        window.adminDelete('Delete this message? This action cannot be undone.', doDelete);
    } else {
        if (confirm('Delete this message?')) doDelete();
    }
}

// ── Send ────────────────────────────────────────────────────
document.getElementById('chatForm').addEventListener('submit', e => {
    e.preventDefault();
    const input = document.getElementById('msgInput');
    const msg   = input.value.trim();
    if (!msg) return;

    input.value = '';
    input.style.height = '';
    input.disabled = true;
    updateCharCount('');

    const fd = new FormData();
    fd.append('action', 'send');
    fd.append('message', msg);

    fetch('api/chat.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            input.disabled = false;
            input.focus();
            if (data.success) pollMessages();
        })
        .catch(() => { input.disabled = false; });
});

document.getElementById('msgInput').addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('chatForm').dispatchEvent(new Event('submit'));
    }
});

// Auto-grow textarea + char counter
const msgInput = document.getElementById('msgInput');
function updateCharCount(val) {
    const remaining = 1000 - val.length;
    const cc = document.getElementById('charCount');
    if (val.length > 800) {
        cc.textContent = remaining + ' left';
        cc.classList.remove('hidden');
        cc.style.color = remaining < 100 ? '#EF4444' : '#F59E0B';
    } else {
        cc.classList.add('hidden');
    }
}
msgInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    updateCharCount(this.value);
});

// ── Members panel ───────────────────────────────────────────
let membersVisible = false;

function toggleMemberPanel() {
    membersVisible = !membersVisible;
    const panel = document.getElementById('memberPanel');
    if (membersVisible) {
        panel.classList.remove('hidden');
        panel.classList.add('flex');
        loadMembers();
    } else {
        panel.classList.add('hidden');
        panel.classList.remove('flex');
    }
}

function loadMembers() {
    fetch('api/chat.php?action=members')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            document.getElementById('memberCount').textContent = data.total;
            document.getElementById('memberSubtitle').textContent =
                data.total + ' participant' + (data.total !== 1 ? 's' : '');

            const admins    = data.members.filter(m => m.role === 'admin');
            const keepers   = data.members.filter(m => m.role !== 'admin');

            function memberCard(m) {
                const initials = m.full_name
                    ? m.full_name.trim().split(' ').map(p => p[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()
                    : m.username.slice(0, 2).toUpperCase();
                const isA = m.role === 'admin';
                const avatarStyle = isA
                    ? 'background:linear-gradient(135deg,#8B5CF6,#6D28D9)'
                    : 'background:linear-gradient(135deg,#F59E0B,#F97316)';
                const isMe = parseInt(m.id) === ME;
                return `<div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="${avatarStyle}">${escHtml(initials)}</div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-gray-700 truncate">${escHtml(m.full_name || m.username)}${isMe ? ' <span class="text-gray-400">(you)</span>' : ''}</p>
                        <p class="text-xs text-gray-400">${isA ? 'Admin' : 'Beekeeper'}</p>
                    </div>
                </div>`;
            }

            const html = (admins.length ? `<p class="text-xs font-bold text-gray-400 px-2 pt-1 pb-0.5 uppercase tracking-wide">Admins</p>` + admins.map(memberCard).join('') : '') +
                         (keepers.length ? `<p class="text-xs font-bold text-gray-400 px-2 pt-3 pb-0.5 uppercase tracking-wide">Beekeepers</p>` + keepers.map(memberCard).join('') : '');
            document.getElementById('memberList').innerHTML = html || '<p class="text-xs text-gray-400 text-center py-4">No members</p>';
        })
        .catch(() => {});
}

// ── Init ────────────────────────────────────────────────────
// Load member count immediately
fetch('api/chat.php?action=members')
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('memberCount').textContent = d.total;
            document.getElementById('memberSubtitle').textContent =
                d.total + ' participant' + (d.total !== 1 ? 's' : '');
        }
    }).catch(() => {});

initialLoad();
setInterval(pollMessages, 3000);
setInterval(markRead, 15000);

// ── Mobile keyboard handler ──────────────────────────────────
// When soft keyboard opens on Android/iPhone, shrink chat to fit
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', () => {
        const chatPage = document.getElementById('chatPage');
        const headerH  = document.getElementById('mainHeader')?.offsetHeight || 52;
        const vvH      = Math.round(window.visualViewport.height);
        const fullH    = Math.round(window.innerHeight);
        // Keyboard is open if visualViewport is significantly smaller
        if (vvH < fullH - 100) {
            chatPage.style.height = (vvH - headerH) + 'px';
        } else {
            chatPage.style.height = '';
        }
        // Always scroll to bottom when keyboard opens/closes
        const box = document.getElementById('chatBox');
        if (box) setTimeout(() => { box.scrollTop = box.scrollHeight; }, 50);
    });
}
</script>
</body>
</html>
