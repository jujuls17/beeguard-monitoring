<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
checkAuth();

$hives = getAllHives($conn);
$isAdmin = isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection Notes - BeeGuard</title>
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
            max-width: 640px;
            margin: 0 auto;
            border-radius: 20px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.4);
            animation: modalIn 0.3s cubic-bezier(0.34,1.56,0.64,1) both;
        }
        @keyframes modalIn {
            from { opacity:0; transform:translateY(-16px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .input-field {
            width:100%; padding:10px 14px; border:1.5px solid #E5E7EB;
            border-radius:10px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s;
            background:#F9FAFB;
        }
        .input-field:focus { outline:none; border-color:#10B981; box-shadow:0 0 0 3px rgba(16,185,129,0.15); background:#fff; }
        textarea.input-field { resize:vertical; min-height:80px; }
        .form-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
        .note-card { transition: transform 0.2s, box-shadow 0.2s; }
        .note-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.1); }
        .cond-excellent { background:#D1FAE5; color:#065F46; }
        .cond-good      { background:#DBEAFE; color:#1E40AF; }
        .cond-fair      { background:#FEF3C7; color:#92400E; }
        .cond-poor      { background:#FEE2E2; color:#991B1B; }
        .cond-critical  { background:#FCA5A5; color:#7F1D1D; }
        .str-strong     { background:#D1FAE5; color:#065F46; }
        .str-moderate   { background:#DBEAFE; color:#1E40AF; }
        .str-weak       { background:#FEF3C7; color:#92400E; }
        .str-queenless  { background:#FEE2E2; color:#991B1B; }
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
                    <i class="fas fa-clipboard-list text-white text-base"></i>
                </span>
                Inspection Notes
            </h1>
            <p class="text-gray-500 text-sm mt-0.5">Record hive inspections, observations, and recommendations</p>
        </div>

        <div class="p-8">

            <!-- Stats + Controls -->
            <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
                <div class="flex gap-5">
                    <div class="bg-white rounded-xl px-5 py-3 shadow-sm flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#D1FAE5;">
                            <i class="fas fa-clipboard-check text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Total Inspections</p>
                            <p class="font-black text-gray-800 text-xl" id="statTotal">—</p>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl px-5 py-3 shadow-sm flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#DBEAFE;">
                            <i class="fas fa-calendar-check text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">This Month</p>
                            <p class="font-black text-gray-800 text-xl" id="statMonth">—</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <select id="filterHive" onchange="applyFilters()"
                            class="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-green-400">
                        <option value="">All Hives</option>
                        <?php foreach ($hives as $h): ?>
                        <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="filterCond" onchange="applyFilters()"
                            class="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-green-400">
                        <option value="">All Conditions</option>
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="critical">Critical</option>
                    </select>
                    <button onclick="openCreate()"
                            class="flex items-center gap-2 px-5 py-2 rounded-xl font-semibold text-sm text-white transition shadow-sm"
                            style="background:linear-gradient(135deg,#10B981,#059669);">
                        <i class="fas fa-plus"></i> New Inspection
                    </button>
                </div>
            </div>

            <!-- Notes Grid -->
            <div id="notesGrid" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="col-span-2 py-16 text-center text-gray-400">
                    <i class="fas fa-spinner fa-spin text-3xl mb-3 block"></i>
                    Loading inspection notes...
                </div>
            </div>
        </div>
    </main>

    <!-- ── Create / Edit Modal ── -->
    <div id="noteFormModal" class="modal-overlay" onclick="if(event.target===this)closeFormModal()">
        <div class="modal-card">
            <div class="px-6 py-5 flex items-center justify-between border-b border-gray-100">
                <h3 class="font-bold text-lg text-gray-800 flex items-center gap-2" id="formModalTitle">
                    <i class="fas fa-plus text-green-600"></i> New Inspection
                </h3>
                <button onclick="closeFormModal()" class="w-9 h-9 rounded-xl hover:bg-gray-100 flex items-center justify-center transition">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="formMsg" class="hidden mb-4 p-3 rounded-xl text-sm"></div>
                <input type="hidden" id="editNoteId">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">Hive <span class="text-red-500">*</span></label>
                        <select id="fHive" class="input-field">
                            <option value="">— Select Hive —</option>
                            <?php foreach ($hives as $h): ?>
                            <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?> (<?php echo htmlspecialchars($h['hive_id']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Inspection Date <span class="text-red-500">*</span></label>
                        <input type="date" id="fDate" class="input-field" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="form-label">Hive Condition</label>
                        <select id="fCondition" class="input-field">
                            <option value="excellent">Excellent</option>
                            <option value="good" selected>Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Colony Strength</label>
                        <select id="fStrength" class="input-field">
                            <option value="strong">Strong</option>
                            <option value="moderate" selected>Moderate</option>
                            <option value="weak">Weak</option>
                            <option value="queenless">Queenless</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Observations</label>
                        <textarea id="fObservations" class="input-field" placeholder="Describe what you observed during the inspection..."></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Recommendations</label>
                        <textarea id="fRecommendations" class="input-field" placeholder="What actions are recommended based on this inspection?"></textarea>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Additional Notes</label>
                        <textarea id="fNotes" class="input-field" placeholder="Any other remarks, measurements, or observations..."></textarea>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeFormModal()" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Cancel</button>
                <button onclick="submitNoteForm()" id="formSubmitBtn"
                        class="flex-1 py-2.5 text-white rounded-xl text-sm font-bold transition"
                        style="background:linear-gradient(135deg,#10B981,#059669);">
                    <i class="fas fa-save mr-1"></i> Save Inspection
                </button>
            </div>
        </div>
    </div>

    <!-- ── View Note Modal ── -->
    <div id="viewNoteModal" class="modal-overlay" onclick="if(event.target===this)closeViewModal()">
        <div class="modal-card" style="max-width:680px;">
            <div class="rounded-t-xl overflow-hidden" style="background:linear-gradient(135deg,#10B981,#059669);padding:24px;">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-xl font-black text-white flex items-center gap-2">
                            <i class="fas fa-clipboard-list"></i>
                            <span id="viewHiveName">—</span>
                        </h2>
                        <p class="text-green-100 text-sm mt-1">
                            <i class="fas fa-calendar mr-1"></i><span id="viewDate">—</span>
                            &bull; Inspector: <span id="viewInspector">—</span>
                        </p>
                    </div>
                    <button onclick="closeViewModal()" class="w-9 h-9 bg-white/20 hover:bg-white/35 rounded-xl flex items-center justify-center text-white transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="flex gap-2 mt-3">
                    <span id="viewCondBadge" class="px-3 py-1 rounded-lg text-xs font-bold bg-white/20 text-white">—</span>
                    <span id="viewStrBadge"  class="px-3 py-1 rounded-lg text-xs font-bold bg-white/20 text-white">—</span>
                </div>
            </div>
            <div class="p-6 space-y-5">
                <div id="viewObsBlock">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-eye text-green-500"></i> Observations
                    </p>
                    <p id="viewObservations" class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap bg-gray-50 rounded-xl p-4">—</p>
                </div>
                <div id="viewRecBlock">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-lightbulb text-yellow-500"></i> Recommendations
                    </p>
                    <p id="viewRecommendations" class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap bg-yellow-50 rounded-xl p-4">—</p>
                </div>
                <div id="viewNotesBlock">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i class="fas fa-sticky-note text-blue-500"></i> Additional Notes
                    </p>
                    <p id="viewNotes" class="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap bg-blue-50 rounded-xl p-4">—</p>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3">
                <button onclick="closeViewModal()" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Close</button>
                <button id="viewEditBtn" class="flex-1 py-2.5 text-white rounded-xl text-sm font-bold transition"
                        style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                    <i class="fas fa-edit mr-1"></i> Edit Note
                </button>
            </div>
        </div>
    </div>

    <!-- ── Delete Confirm ── -->
    <div id="deleteModal" class="modal-overlay" onclick="if(event.target===this)closeDeleteModal()">
        <div class="modal-card" style="max-width:400px;">
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-red-500 text-2xl"></i>
                </div>
                <h3 class="font-bold text-lg text-gray-800 mb-2">Delete Inspection Note?</h3>
                <p class="text-sm text-gray-500 mb-6">This inspection record will be permanently deleted and cannot be recovered.</p>
                <div class="flex gap-3">
                    <button onclick="closeDeleteModal()" class="flex-1 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Cancel</button>
                    <button onclick="confirmDelete()" id="deleteConfirmBtn"
                            class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-bold transition">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    let allNotes = [];
    let deleteTargetId = null;
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    const condLabel  = { excellent:'Excellent', good:'Good', fair:'Fair', poor:'Poor', critical:'Critical' };
    const strLabel   = { strong:'Strong Colony', moderate:'Moderate', weak:'Weak Colony', queenless:'Queenless' };
    const condIcon   = { excellent:'fa-star', good:'fa-check-circle', fair:'fa-exclamation-circle', poor:'fa-times-circle', critical:'fa-skull-crossbones' };
    const condColors = {
        excellent: { card:'#D1FAE5', border:'#059669', badge:'cond-excellent' },
        good:      { card:'#DBEAFE', border:'#2563EB', badge:'cond-good' },
        fair:      { card:'#FEF3C7', border:'#D97706', badge:'cond-fair' },
        poor:      { card:'#FEE2E2', border:'#DC2626', badge:'cond-poor' },
        critical:  { card:'#FCA5A5', border:'#7F1D1D', badge:'cond-critical' },
    };

    function loadNotes() {
        fetch('api/inspection_crud.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('notesGrid').innerHTML = `
                        <div class="col-span-2 py-20 text-center text-gray-400">
                            <i class="fas fa-exclamation-circle text-3xl mb-4 block text-red-400"></i>
                            <p class="font-semibold text-lg mb-1 text-red-500">Failed to load notes</p>
                            <p class="text-sm">${escHtml(data.message || 'Unknown server error')}</p>
                        </div>`;
                    return;
                }
                allNotes = data.notes;
                applyFilters();
                updateStats();
            })
            .catch(() => {
                document.getElementById('notesGrid').innerHTML = `
                    <div class="col-span-2 py-20 text-center text-gray-400">
                        <i class="fas fa-exclamation-circle text-3xl mb-4 block text-red-400"></i>
                        <p class="font-semibold text-lg mb-1 text-red-500">Connection error</p>
                        <p class="text-sm">Could not reach the server. Please refresh the page.</p>
                    </div>`;
            });
    }

    function updateStats() {
        document.getElementById('statTotal').textContent = allNotes.length;
        const thisMonth = new Date().toISOString().substring(0,7);
        const count = allNotes.filter(n => n.inspection_date.substring(0,7) === thisMonth).length;
        document.getElementById('statMonth').textContent = count;
    }

    function applyFilters() {
        const hive = document.getElementById('filterHive').value;
        const cond = document.getElementById('filterCond').value;
        let filtered = allNotes;
        if (hive) filtered = filtered.filter(n => n.hive_id == hive);
        if (cond) filtered = filtered.filter(n => n.hive_condition === cond);
        renderNotes(filtered);
    }

    function renderNotes(notes) {
        const grid = document.getElementById('notesGrid');
        if (!notes.length) {
            grid.innerHTML = `<div class="col-span-2 py-20 text-center text-gray-400">
                <i class="fas fa-clipboard text-4xl mb-4 block"></i>
                <p class="font-semibold text-lg mb-1">No inspection notes yet</p>
                <p class="text-sm">Click "New Inspection" to record your first hive inspection.</p>
            </div>`;
            return;
        }

        grid.innerHTML = notes.map(n => {
            const c = condColors[n.hive_condition] || condColors.good;
            const shortObs = (n.observations || '').substring(0, 120) + ((n.observations || '').length > 120 ? '...' : '');
            const inspector = n.inspector_name || n.inspector_username || '—';
            const isAdminNote = n.inspector_role === 'admin';
            const canEdit = isAdmin || n.user_id == <?php echo (int)$_SESSION['user_id']; ?>;

            const adminBadge = isAdminNote
                ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-purple-100 text-purple-700 ml-1">
                       <i class="fas fa-shield-alt text-xs"></i> Admin
                   </span>`
                : '';

            return `<div class="note-card bg-white rounded-2xl overflow-hidden shadow-sm border-l-4" style="border-left-color:${c.border};">
                <div class="p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                                <i class="fas fa-hive text-green-500 text-sm"></i>
                                ${escHtml(n.hive_name)}${adminBadge}
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <i class="fas fa-calendar mr-1"></i>${n.inspection_date}
                                &bull; <i class="fas fa-user mr-1"></i>${escHtml(inspector)}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5 items-end">
                            <span class="${c.badge} px-2.5 py-1 rounded-lg text-xs font-bold">
                                <i class="fas ${condIcon[n.hive_condition] || 'fa-circle'} mr-1"></i>${condLabel[n.hive_condition] || n.hive_condition}
                            </span>
                            <span class="str-${n.colony_strength} px-2.5 py-1 rounded-lg text-xs font-bold">
                                ${strLabel[n.colony_strength] || n.colony_strength}
                            </span>
                        </div>
                    </div>
                    ${shortObs ? `<p class="text-sm text-gray-600 leading-relaxed mb-4">${escHtml(shortObs)}</p>` : ''}
                    <div class="flex gap-2">
                        <button onclick="openView(${n.id})"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold border border-gray-200 hover:bg-gray-50 transition text-gray-600">
                            <i class="fas fa-eye mr-1"></i> View
                        </button>
                        ${canEdit ? `
                        <button onclick="openEdit(${n.id})"
                                class="flex-1 py-2 rounded-xl text-xs font-semibold border border-yellow-300 hover:bg-yellow-50 transition text-yellow-700">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <button onclick="openDelete(${n.id})"
                                class="py-2 px-3 rounded-xl text-xs font-semibold border border-red-200 hover:bg-red-50 transition text-red-500">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    // ── Create modal ─────────────────────────────────────────
    function openCreate() {
        document.getElementById('editNoteId').value = '';
        document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-plus text-green-600 mr-2"></i>New Inspection';
        document.getElementById('fDate').value = new Date().toISOString().substring(0,10);
        document.getElementById('fHive').value       = '';
        document.getElementById('fCondition').value  = 'good';
        document.getElementById('fStrength').value   = 'moderate';
        document.getElementById('fObservations').value    = '';
        document.getElementById('fRecommendations').value = '';
        document.getElementById('fNotes').value           = '';
        document.getElementById('formMsg').classList.add('hidden');
        document.getElementById('noteFormModal').classList.add('active');
    }

    function openEdit(id) {
        fetch(`api/inspection_crud.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.message); return; }
                const n = data.note;
                document.getElementById('editNoteId').value       = n.id;
                document.getElementById('fHive').value            = n.hive_id;
                document.getElementById('fDate').value            = n.inspection_date;
                document.getElementById('fCondition').value       = n.hive_condition;
                document.getElementById('fStrength').value        = n.colony_strength;
                document.getElementById('fObservations').value    = n.observations    || '';
                document.getElementById('fRecommendations').value = n.recommendations || '';
                document.getElementById('fNotes').value           = n.notes           || '';
                document.getElementById('formModalTitle').innerHTML = '<i class="fas fa-edit text-yellow-500 mr-2"></i>Edit Inspection';
                document.getElementById('formMsg').classList.add('hidden');
                document.getElementById('noteFormModal').classList.add('active');
            });
    }

    function closeFormModal() {
        document.getElementById('noteFormModal').classList.remove('active');
    }

    function submitNoteForm() {
        const id = document.getElementById('editNoteId').value;
        const isEdit = !!id;
        const body = new URLSearchParams({
            action:           isEdit ? 'update' : 'create',
            id:               id,
            hive_id:          document.getElementById('fHive').value,
            inspection_date:  document.getElementById('fDate').value,
            hive_condition:   document.getElementById('fCondition').value,
            colony_strength:  document.getElementById('fStrength').value,
            observations:     document.getElementById('fObservations').value,
            recommendations:  document.getElementById('fRecommendations').value,
            notes:            document.getElementById('fNotes').value,
        });

        const btn = document.getElementById('formSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';

        fetch('api/inspection_crud.php', { method: 'POST', body })
            .then(r => r.json())
            .then(data => {
                const el = document.getElementById('formMsg');
                el.className = data.success
                    ? 'mb-4 p-3 rounded-xl text-sm bg-green-50 border border-green-200 text-green-800'
                    : 'mb-4 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800';
                el.innerHTML = `<i class="fas fa-${data.success?'check':'times'}-circle mr-2"></i>${data.message}`;
                el.classList.remove('hidden');
                if (data.success) { loadNotes(); setTimeout(closeFormModal, 1400); }
            })
            .catch(() => {
                const el = document.getElementById('formMsg');
                el.className = 'mb-4 p-3 rounded-xl text-sm bg-red-50 border border-red-200 text-red-800';
                el.innerHTML = '<i class="fas fa-times-circle mr-2"></i>Network error. Please try again.';
                el.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Inspection';
            });
    }

    // ── View modal ───────────────────────────────────────────
    function openView(id) {
        fetch(`api/inspection_crud.php?action=get&id=${id}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.message); return; }
                const n = data.note;
                const c = condColors[n.hive_condition] || condColors.good;
                document.getElementById('viewHiveName').textContent   = n.hive_name || '—';
                document.getElementById('viewDate').textContent       = n.inspection_date;
                document.getElementById('viewCondBadge').textContent  = condLabel[n.hive_condition] || n.hive_condition;
                document.getElementById('viewStrBadge').textContent   = strLabel[n.colony_strength] || n.colony_strength;
                document.getElementById('viewObservations').textContent    = n.observations    || 'None recorded.';
                document.getElementById('viewRecommendations').textContent = n.recommendations || 'None recorded.';
                document.getElementById('viewNotes').textContent          = n.notes           || 'None recorded.';

                const canEdit = isAdmin || n.user_id == <?php echo (int)$_SESSION['user_id']; ?>;
                const editBtn = document.getElementById('viewEditBtn');
                const inspectorLabel = document.getElementById('viewInspector');
                inspectorLabel.innerHTML = escHtml(n.inspector_name || n.inspector_username || '—')
                    + (n.inspector_role === 'admin'
                        ? ' <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-bold bg-white/30 text-white"><i class="fas fa-shield-alt text-xs"></i> Admin</span>'
                        : '');
                if (canEdit) {
                    editBtn.style.display = '';
                    editBtn.onclick = () => { closeViewModal(); openEdit(n.id); };
                } else {
                    editBtn.style.display = 'none';
                }
                document.getElementById('viewNoteModal').classList.add('active');
            });
    }

    function closeViewModal() {
        document.getElementById('viewNoteModal').classList.remove('active');
    }

    // ── Delete ───────────────────────────────────────────────
    function openDelete(id) {
        const doDelete = () => {
            fetch('api/inspection_crud.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete', id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) loadNotes();
                else alert(data.message);
            })
            .catch(() => alert('Network error. Please try again.'));
        };
        if (typeof window.adminDelete === 'function') {
            window.adminDelete('Delete this inspection note? This action cannot be undone.', doDelete);
        } else {
            if (confirm('Delete this inspection note? This cannot be undone.')) doDelete();
        }
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
    }
    function confirmDelete() { closeDeleteModal(); }

    function escHtml(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    loadNotes();
    </script>
</body>
</html>
