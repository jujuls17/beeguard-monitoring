<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

$hives = getAllHives($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(10,6,0,0.7);
            backdrop-filter: blur(4px);
            overflow-y: auto;
            padding: 24px 16px;
        }
        .modal-overlay.active { display: block; }
        .modal-card {
            background: #fff;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            border-radius: 20px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.4);
            animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        .modal-card.wide { max-width: 720px; }
        @keyframes modalIn {
            from { opacity:0; transform:translateY(-16px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .badge-active   { background:#D1FAE5; color:#065F46; }
        .badge-inactive { background:#FEE2E2; color:#991B1B; }
        .badge-admin    { background:#EDE9FE; color:#5B21B6; }
        .badge-beekeeper{ background:#FEF3C7; color:#92400E; }
        .tbl-row:hover { background:#FFFBEB; }
        .tbl-row td { padding: 14px 16px; border-bottom: 1px solid #F3F4F6; font-size:14px; }
        .input-field {
            width:100%; padding:10px 14px; border:1.5px solid #E5E7EB;
            border-radius:10px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s;
            background:#F9FAFB;
        }
        .input-field:focus { outline:none; border-color:#F59E0B; box-shadow:0 0 0 3px rgba(245,158,11,0.15); background:#fff; }
        .form-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>

    <main class="min-h-screen">
        <div class="page-header px-8 py-5 pl-10">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center"
                      style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);box-shadow:0 4px 14px rgba(139,92,246,0.4);">
                    <i class="fas fa-users text-white text-base"></i>
                </span>
                Manage Users
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Create, edit, and manage beekeeper accounts</p>
        </div>

        <div class="p-8">

            <!-- Stats row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8" id="statsRow">
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#8B5CF6 0%,#7C3AED 100%);box-shadow:0 8px 32px rgba(139,92,246,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Total Users</p>
                            <p class="text-3xl font-black" id="statTotal">—</p>
                        </div>
                        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users text-lg"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#10B981 0%,#059669 100%);box-shadow:0 8px 32px rgba(16,185,129,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Active</p>
                            <p class="text-3xl font-black" id="statActive">—</p>
                        </div>
                        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-check text-lg"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#EF4444 0%,#DC2626 100%);box-shadow:0 8px 32px rgba(239,68,68,0.3);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Inactive</p>
                            <p class="text-3xl font-black" id="statInactive">—</p>
                        </div>
                        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-user-slash text-lg"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card rounded-2xl p-5 text-white" style="background:linear-gradient(135deg,#F59E0B 0%,#F97316 100%);box-shadow:0 8px 32px rgba(245,158,11,0.35);">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium opacity-80 mb-1">Beekeepers</p>
                            <p class="text-3xl font-black" id="statBeekeeper">—</p>
                        </div>
                        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-leaf text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table card -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="px-4 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <h2 class="font-bold text-gray-800 text-lg">User Accounts</h2>
                        <span id="userCount" class="text-xs bg-purple-100 text-purple-700 font-bold px-2.5 py-1 rounded-full">—</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="text" id="searchUsers" placeholder="Search users..."
                               oninput="filterTable()"
                               class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-yellow-400 w-40">
                        <select id="filterRole" onchange="filterTable()"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-yellow-400">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="beekeeper">Beekeeper</option>
                        </select>
                        <select id="filterStatus" onchange="filterTable()"
                                class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-yellow-400">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <button onclick="exportUsersCSV()"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-sm text-white transition"
                                style="background:linear-gradient(135deg,#10B981,#059669);">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <button onclick="openCreate()"
                                class="flex items-center gap-1.5 px-3 py-2 rounded-lg font-semibold text-sm text-white transition"
                                style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);">
                            <i class="fas fa-user-plus"></i> New User
                        </button>
                    </div>
                </div>

                <!-- Bulk action bar (hidden until rows selected) -->
                <div id="bulkBar" class="hidden px-4 py-2.5 flex items-center gap-3 border-b border-yellow-100" style="background:#FFFBEB;">
                    <span id="bulkCount" class="text-sm font-bold text-yellow-700"></span>
                    <button onclick="bulkActivate()" class="text-xs px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 rounded-lg font-semibold transition">
                        <i class="fas fa-check mr-1"></i>Activate
                    </button>
                    <button onclick="bulkDeactivate()" class="text-xs px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-semibold transition">
                        <i class="fas fa-ban mr-1"></i>Deactivate
                    </button>
                    <button onclick="bulkDelete()" class="text-xs px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold transition">
                        <i class="fas fa-trash mr-1"></i>Delete Selected
                    </button>
                    <button onclick="clearSelection()" class="ml-auto text-xs text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times mr-1"></i>Clear
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="usersTable">
                        <thead>
                            <tr style="background:#F9FAFB;">
                                <th class="px-4 py-3 w-8"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" class="rounded"></th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">#</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">User</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Assigned Hive</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Created</th>
                                <th class="text-left px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTbody">
                            <tr><td colspan="9" class="text-center py-12 text-gray-400">
                                <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading users...
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- ── Create/Edit Modal ── -->
    <div id="userFormModal" class="modal-overlay" onclick="if(event.target===this)closeFormModal()">
        <div class="modal-card">
            <div class="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2" id="formModalTitle">
                    <i class="fas fa-user-plus text-purple-600"></i> New User
                </h3>
                <button onclick="closeFormModal()" class="w-9 h-9 rounded-xl hover:bg-gray-100 flex items-center justify-center transition">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="formMsg" class="hidden mb-4 p-3 rounded-xl text-sm"></div>
                <input type="hidden" id="editUserId">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="form-label">Full Name</label>
                        <input type="text" id="fFullName" placeholder="e.g. Juan dela Cruz" class="input-field">
                    </div>
                    <div id="fUsernameRow">
                        <label class="form-label">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="fUsername" placeholder="username" class="input-field" autocomplete="off">
                    </div>
                    <div>
                        <label class="form-label">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" id="fEmail" placeholder="user@example.com" class="input-field" autocomplete="off">
                    </div>
                    <div id="fPasswordRow">
                        <label class="form-label" id="fPasswordLabel">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="fPassword" placeholder="Min. 6 characters" class="input-field" autocomplete="new-password">
                    </div>
                    <div>
                        <label class="form-label">Contact Number</label>
                        <input type="text" id="fContact" placeholder="+63 9XX XXX XXXX" class="input-field">
                    </div>
                    <div>
                        <label class="form-label">Assigned Hive (optional)</label>
                        <select id="fHive" class="input-field">
                            <option value="">— None —</option>
                            <?php foreach ($hives as $h): ?>
                            <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?> (<?php echo htmlspecialchars($h['hive_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Security Question -->
                    <div class="col-span-2 border-t border-gray-100 pt-4 mt-1">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-2">
                            <i class="fas fa-shield-alt text-yellow-500"></i> Security Question (for password recovery)
                        </p>
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="form-label">Security Question <span class="text-red-500" id="fSecQRequired">*</span></label>
                                <select id="fSecQuestion" class="input-field">
                                    <option value="">— Select a question —</option>
                                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                                    <option value="What is your dog's name?">What is your dog's name?</option>
                                    <option value="What is your cat's name?">What is your cat's name?</option>
                                    <option value="What was your childhood nickname?">What was your childhood nickname?</option>
                                    <option value="What is the name of your first pet?">What is the name of your first pet?</option>
                                    <option value="What is the name of your elementary school?">What is the name of your elementary school?</option>
                                    <option value="What city were you born in?">What city were you born in?</option>
                                    <option value="What is your favorite food?">What is your favorite food?</option>
                                    <option value="What is your father's middle name?">What is your father's middle name?</option>
                                    <option value="What is the name of your best friend?">What is the name of your best friend?</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Answer <span class="text-red-500" id="fSecARequired">*</span></label>
                                <input type="text" id="fSecAnswer" placeholder="Your answer (not case-sensitive)" class="input-field">
                                <p class="text-xs text-gray-400 mt-1"><i class="fas fa-info-circle mr-1"></i>Answer is not case-sensitive. Will be used for forgot password.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-2 p-3 rounded-xl flex items-center gap-3" style="background:#EDE9FE;">
                        <i class="fas fa-info-circle text-purple-600"></i>
                        <p class="text-sm text-purple-800" id="fRoleNote">Role is set to <strong>Beekeeper</strong> for all new accounts.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeFormModal()" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Cancel</button>
                <button onclick="submitUserForm()" id="formSubmitBtn"
                        class="flex-1 py-2.5 text-white rounded-xl text-sm font-bold transition"
                        style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);">
                    <i class="fas fa-save mr-1"></i> Save User
                </button>
            </div>
        </div>
    </div>

    <!-- ── View User Modal ── -->
    <div id="viewUserModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
        <div class="modal-card wide">
            <div class="rounded-t-xl overflow-hidden" style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);padding:24px;">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl font-black text-white"
                             style="background:rgba(255,255,255,0.2);" id="viewAvatar">JD</div>
                        <div>
                            <h2 class="text-xl font-black text-white" id="viewFullName">—</h2>
                            <p class="text-purple-200 text-sm font-mono mt-0.5" id="viewUsername">—</p>
                        </div>
                    </div>
                    <button onclick="closeViewModal()" class="w-9 h-9 bg-white/20 hover:bg-white/35 rounded-xl flex items-center justify-center text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Email</p>
                        <p class="font-semibold text-gray-700" id="viewEmail">—</p>
                    </div>
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Contact</p>
                        <p class="font-semibold text-gray-700" id="viewContact">—</p>
                    </div>
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Role</p>
                        <p id="viewRole">—</p>
                    </div>
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Status</p>
                        <p id="viewStatus">—</p>
                    </div>
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Assigned Hive</p>
                        <p class="font-semibold text-gray-700" id="viewHive">—</p>
                    </div>
                    <div class="p-4 rounded-xl" style="background:#F8FAFC;">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-wider mb-1">Date Created</p>
                        <p class="font-semibold text-gray-700" id="viewCreated">—</p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeViewModal()" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Close</button>
                <button id="viewEditBtn" onclick=""
                        class="flex-1 py-2.5 text-white rounded-xl text-sm font-bold transition"
                        style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                    <i class="fas fa-edit mr-1"></i> Edit This User
                </button>
            </div>
        </div>
    </div>

    <script>
    let allUsers = [];
    const SELF_ID = <?php echo (int)$_SESSION['user_id']; ?>;

    // ── Load ─────────────────────────────────────────────────
    function loadUsers() {
        fetch('api/user_crud.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                allUsers = data.users;
                filterTable();
                updateStats(allUsers);
            });
    }

    function updateStats(users) {
        document.getElementById('statTotal').textContent     = users.length;
        document.getElementById('statActive').textContent    = users.filter(u => u.account_status === 'active').length;
        document.getElementById('statInactive').textContent  = users.filter(u => u.account_status === 'inactive').length;
        document.getElementById('statBeekeeper').textContent = users.filter(u => u.role === 'beekeeper').length;
    }

    // ── Filter ───────────────────────────────────────────────
    function filterTable() {
        const q      = document.getElementById('searchUsers').value.toLowerCase();
        const role   = document.getElementById('filterRole').value;
        const status = document.getElementById('filterStatus').value;

        const filtered = allUsers.filter(u => {
            const matchQ = !q || (u.full_name||'').toLowerCase().includes(q)
                || (u.username||'').toLowerCase().includes(q)
                || (u.email||'').toLowerCase().includes(q)
                || (u.contact_number||'').toLowerCase().includes(q)
                || (u.assigned_hive_name||'').toLowerCase().includes(q);
            const matchRole   = !role   || u.role === role;
            const matchStatus = !status || u.account_status === status;
            return matchQ && matchRole && matchStatus;
        });
        renderTable(filtered);
        document.getElementById('userCount').textContent = filtered.length + ' user' + (filtered.length !== 1 ? 's' : '');
        clearSelection();
    }

    // ── Render ───────────────────────────────────────────────
    function renderTable(users) {
        const tbody = document.getElementById('usersTbody');
        if (!users.length) {
            tbody.innerHTML = `<tr><td colspan="9" class="text-center py-12 text-gray-400">
                <i class="fas fa-users text-3xl mb-3 block"></i>No users found.</td></tr>`;
            return;
        }
        tbody.innerHTML = users.map((u, i) => {
            const initials   = ((u.full_name || u.username || '?').substring(0,2)).toUpperCase();
            const isSelf     = u.id == SELF_ID;
            const isAdmin    = u.role === 'admin';
            const canAct     = !isAdmin && !isSelf;

            const statusBadge = isAdmin
                ? `<span class="text-gray-400 text-xs">—</span>`
                : u.account_status === 'active'
                    ? `<span class="badge-active px-2.5 py-1 rounded-lg text-xs font-bold">Active</span>`
                    : `<span class="badge-inactive px-2.5 py-1 rounded-lg text-xs font-bold">Inactive</span>`;

            const roleBadge = isAdmin
                ? `<span class="badge-admin px-2.5 py-1 rounded-lg text-xs font-bold">Admin</span>`
                : `<span class="badge-beekeeper px-2.5 py-1 rounded-lg text-xs font-bold">Beekeeper</span>`;

            const toggleTitle = u.account_status === 'active' ? 'Deactivate' : 'Activate';
            const toggleIcon  = u.account_status === 'active' ? 'fa-user-slash' : 'fa-user-check';
            const toggleColor = u.account_status === 'active'
                ? 'text-orange-400 hover:bg-orange-50 hover:text-orange-600'
                : 'text-green-500 hover:bg-green-50 hover:text-green-700';

            const toggleBtn = canAct
                ? `<button onclick="quickToggle(${u.id})"
                       class="w-8 h-8 rounded-lg flex items-center justify-center ${toggleColor} transition" title="${toggleTitle}">
                       <i class="fas ${toggleIcon} text-xs"></i>
                   </button>`
                : '';

            const deleteBtn = canAct
                ? `<button onclick="openDelete(${u.id}, '${escHtml(u.full_name || u.username)}')"
                       class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 hover:text-red-600 transition" title="Delete">
                       <i class="fas fa-trash text-xs"></i>
                   </button>`
                : '';

            const cb = canAct
                ? `<input type="checkbox" class="row-cb rounded" data-id="${u.id}" onchange="onRowCheck()">`
                : '';

            return `<tr class="tbl-row">
                <td class="px-4 py-3">${cb}</td>
                <td class="px-4 py-3 text-gray-400 font-mono text-xs">${i+1}</td>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
                             style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);">${initials}</div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">${escHtml(u.full_name || '—')}</p>
                            <p class="text-xs text-gray-500">@${escHtml(u.username)}</p>
                            <p class="text-xs text-gray-400">${escHtml(u.email)}</p>
                        </div>
                    </div>
                </td>
                <td class="text-gray-600 text-sm">${escHtml(u.contact_number || '—')}</td>
                <td class="text-gray-600 text-sm">${u.assigned_hive_name || '<span class="text-gray-400">—</span>'}</td>
                <td>${roleBadge}</td>
                <td>${statusBadge}</td>
                <td class="text-gray-500 text-xs">${u.created_at ? u.created_at.substring(0,10) : '—'}</td>
                <td>
                    <div class="flex items-center gap-1">
                        <button onclick="openView(${u.id})"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-400 hover:bg-blue-50 hover:text-blue-600 transition" title="View">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                        <button onclick="openEdit(${u.id})"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-yellow-500 hover:bg-yellow-50 hover:text-yellow-700 transition" title="Edit">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        ${toggleBtn}
                        ${deleteBtn}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ── Quick status toggle ───────────────────────────────────
    function quickToggle(id) {
        const u = allUsers.find(x => x.id == id);
        if (!u) return;
        const action = u.account_status === 'active' ? 'Deactivate' : 'Activate';
        adminDelete(`${action} user "${u.full_name || u.username}"?`, () => {
            fetch('api/user_crud.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'toggle_status', id })
            })
            .then(r => r.json())
            .then(d => { if (d.success) loadUsers(); else alert(d.message); })
            .catch(() => {});
        }, action);
    }

    // ── Bulk actions ──────────────────────────────────────────
    function getSelectedIds() {
        return [...document.querySelectorAll('.row-cb:checked')].map(cb => cb.dataset.id);
    }

    function onRowCheck() {
        const ids = getSelectedIds();
        const bar = document.getElementById('bulkBar');
        if (ids.length) {
            bar.classList.remove('hidden');
            document.getElementById('bulkCount').textContent = ids.length + ' selected';
        } else {
            bar.classList.add('hidden');
        }
        const allCbs = document.querySelectorAll('.row-cb');
        document.getElementById('selectAll').checked = allCbs.length > 0 && ids.length === allCbs.length;
    }

    function toggleSelectAll(cb) {
        document.querySelectorAll('.row-cb').forEach(el => el.checked = cb.checked);
        onRowCheck();
    }

    function clearSelection() {
        document.querySelectorAll('.row-cb').forEach(el => el.checked = false);
        const sa = document.getElementById('selectAll');
        if (sa) sa.checked = false;
        document.getElementById('bulkBar').classList.add('hidden');
    }

    function doBulk(type, msg, actionLabel) {
        const ids = getSelectedIds();
        if (!ids.length) return;
        adminDelete(msg + ` (${ids.length} user${ids.length>1?'s':''})`, () => {
            fetch('api/user_crud.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'bulk', type, ids: JSON.stringify(ids) })
            })
            .then(r => r.json())
            .then(d => { if (d.success) loadUsers(); else alert(d.message); })
            .catch(() => {});
        }, actionLabel);
    }

    function bulkActivate()   { doBulk('activate',   'Activate selected users',   'Activate'); }
    function bulkDeactivate() { doBulk('deactivate', 'Deactivate selected users', 'Deactivate'); }
    function bulkDelete()     { doBulk('delete',     'Permanently delete selected users', 'Delete'); }

    // ── Export CSV ────────────────────────────────────────────
    function exportUsersCSV() {
        const header = ['Full Name','Username','Email','Contact','Role','Status','Assigned Hive','Created'];
        const lines  = [header.join(',')];
        allUsers.forEach(u => {
            lines.push([
                '"' + (u.full_name||'').replace(/"/g,'""') + '"',
                u.username, u.email,
                '"' + (u.contact_number||'').replace(/"/g,'""') + '"',
                u.role, u.account_status,
                '"' + (u.assigned_hive_name||'').replace(/"/g,'""') + '"',
                u.created_at ? u.created_at.substring(0,10) : '',
            ].join(','));
        });
        const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
        const a    = document.createElement('a');
        a.href     = URL.createObjectURL(blob);
        a.download = 'beeguard_users_' + new Date().toISOString().slice(0,10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }

    // ── Create modal ─────────────────────────────────────────
    function openCreate() {
        document.getElementById('editUserId').value = '';
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-user-plus text-purple-600 mr-2"></i>New Beekeeper Account';
        document.getElementById('fPasswordLabel').innerHTML = 'Password <span class="text-red-500">*</span>';
        document.getElementById('fRoleNote').innerHTML = 'Role is set to <strong>Beekeeper</strong> for all new accounts.';
        clearForm();
        document.getElementById('formMsg').classList.add('hidden');
        document.getElementById('userFormModal').classList.add('active');
    }

    function openEdit(id) {
        const u = allUsers.find(x => x.id == id);
        if (!u) return;
        document.getElementById('editUserId').value   = u.id;
        document.getElementById('fFullName').value    = u.full_name   || '';
        document.getElementById('fUsername').value    = u.username    || '';
        document.getElementById('fEmail').value       = u.email       || '';
        document.getElementById('fPassword').value    = '';
        document.getElementById('fContact').value     = u.contact_number || '';
        document.getElementById('fHive').value        = u.assigned_hive_id || '';
        document.getElementById('fSecQuestion').value = u.security_question || '';
        document.getElementById('fSecAnswer').value   = '';
        // On edit: security question optional (already set), mark as optional
        document.getElementById('fSecQRequired').style.display = 'none';
        document.getElementById('fSecARequired').style.display = 'none';
        const isAdminUser = u.role === 'admin';
        document.getElementById('fUsernameRow').style.display = isAdminUser ? 'none' : '';
        document.getElementById('fPasswordRow').style.display = isAdminUser ? 'none' : '';
        document.getElementById('fPasswordLabel').innerHTML = 'Password <span class="text-gray-400 font-normal">(leave blank to keep)</span>';
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-user-edit text-yellow-500 mr-2"></i>Edit User';
        document.getElementById('fRoleNote').innerHTML = isAdminUser
            ? '<i class="fas fa-shield-alt text-purple-600 mr-1"></i>Username and password can only be changed in <strong>Settings</strong>.'
            : 'Role is set to <strong>Beekeeper</strong> for all new accounts.';
        document.getElementById('formMsg').classList.add('hidden');
        document.getElementById('userFormModal').classList.add('active');
    }

    function closeFormModal() { document.getElementById('userFormModal').classList.remove('active'); }

    function clearForm() {
        ['fFullName','fUsername','fEmail','fPassword','fContact','fSecAnswer'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('fHive').value        = '';
        document.getElementById('fSecQuestion').value = '';
        document.getElementById('fUsernameRow').style.display = '';
        document.getElementById('fPasswordRow').style.display = '';
        document.getElementById('fSecQRequired').style.display  = '';
        document.getElementById('fSecARequired').style.display  = '';
    }

    function submitUserForm() {
        const id   = document.getElementById('editUserId').value;
        const body = new URLSearchParams({
            action: id ? 'update' : 'create', id,
            full_name:         document.getElementById('fFullName').value.trim(),
            username:          document.getElementById('fUsername').value.trim(),
            email:             document.getElementById('fEmail').value.trim(),
            password:          document.getElementById('fPassword').value,
            contact_number:    document.getElementById('fContact').value.trim(),
            assigned_hive_id:  document.getElementById('fHive').value,
            security_question: document.getElementById('fSecQuestion').value,
            security_answer:   document.getElementById('fSecAnswer').value.trim(),
        });
        const btn = document.getElementById('formSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
        fetch('api/user_crud.php', { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                showFormMsg(data.success ? 'success' : 'error', data.message);
                if (data.success) { loadUsers(); setTimeout(closeFormModal, 1400); }
            })
            .catch(() => showFormMsg('error', 'Network error.'))
            .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save User'; });
    }

    function showFormMsg(type, msg) {
        const el = document.getElementById('formMsg');
        el.className = type === 'success'
            ? 'mb-4 p-3 rounded-xl text-sm bg-green-50 border border-green-200 text-green-800'
            : 'mb-4 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800';
        el.innerHTML = `<i class="fas fa-${type==='success'?'check':'times'}-circle mr-2"></i>${msg}`;
    }

    // ── View modal ───────────────────────────────────────────
    function openView(id) {
        fetch(`api/user_crud.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                const u = data.user;
                document.getElementById('viewAvatar').textContent   = ((u.full_name||u.username||'?').substring(0,2)).toUpperCase();
                document.getElementById('viewFullName').textContent = u.full_name || '(No name)';
                document.getElementById('viewUsername').textContent = '@' + u.username;
                document.getElementById('viewEmail').textContent    = u.email || '—';
                document.getElementById('viewContact').textContent  = u.contact_number || '—';
                document.getElementById('viewHive').textContent     = u.assigned_hive_name || '—';
                document.getElementById('viewCreated').textContent  = u.created_at ? u.created_at.substring(0,10) : '—';
                document.getElementById('viewRole').innerHTML = u.role === 'admin'
                    ? `<span class="badge-admin px-2.5 py-1 rounded-lg text-xs font-bold">Admin</span>`
                    : `<span class="badge-beekeeper px-2.5 py-1 rounded-lg text-xs font-bold">Beekeeper</span>`;
                document.getElementById('viewStatus').innerHTML = u.role === 'admin'
                    ? `<span class="text-gray-400 text-sm font-semibold">—</span>`
                    : u.account_status === 'active'
                        ? `<span class="badge-active px-2.5 py-1 rounded-lg text-xs font-bold">Active</span>`
                        : `<span class="badge-inactive px-2.5 py-1 rounded-lg text-xs font-bold">Inactive</span>`;
                document.getElementById('viewEditBtn').onclick = () => { closeViewModal(); openEdit(u.id); };
                document.getElementById('viewUserModal').classList.add('active');
            });
    }

    function closeViewModal() { document.getElementById('viewUserModal').classList.remove('active'); }

    // ── Delete (uses global adminDelete from sidebar) ─────────
    function openDelete(id, name) {
        adminDelete(`Delete user "${name}"? All their inspection notes will also be removed.`, () => {
            fetch('api/user_crud.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete', id })
            })
            .then(r => r.json())
            .then(d => { if (d.success) loadUsers(); else alert(d.message); })
            .catch(() => {});
        });
    }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    loadUsers();
    </script>
</body>
</html>
