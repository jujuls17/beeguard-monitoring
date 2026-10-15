<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
requireAdmin();

// Fetch current user (with full_name and role)
$stmt = $conn->prepare("SELECT username, email, full_name, role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();


// Ensure settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    email_notifications TINYINT(1) DEFAULT 1,
    push_notifications TINYINT(1) DEFAULT 1,
    alert_critical TINYINT(1) DEFAULT 1,
    alert_warnings TINYINT(1) DEFAULT 1,
    alert_informational TINYINT(1) DEFAULT 0,
    data_retention VARCHAR(20) DEFAULT '90',
    sync_interval INT DEFAULT 5,
    ml_confidence INT DEFAULT 85,
    auto_backup TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Load or create user settings
$stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$s = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$s) {
    $conn->query("INSERT INTO user_settings (user_id) VALUES ({$_SESSION['user_id']})");
    $s = ['email_notifications'=>1,'push_notifications'=>1,'alert_critical'=>1,'alert_warnings'=>1,'alert_informational'=>0,'data_retention'=>'90','sync_interval'=>5,'ml_confidence'=>85,'auto_backup'=>1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BeeGuard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include 'sidebar.php'; ?>
    <?php include 'header.php'; ?>
    <main class="min-h-screen">
        <div class="page-header px-6 py-4">
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center"
                      style="background:linear-gradient(135deg,#6B7280,#4B5563);">
                    <i class="fas fa-cog text-white text-sm"></i>
                </span>
                Settings
            </h1>
            <p class="text-gray-500 text-xs mt-0.5">Configure system preferences and notifications</p>
        </div>

        <div class="p-4 sm:p-6">
            <div id="settingsMsg" class="hidden mb-4 p-4 rounded-lg text-sm"></div>

            <!-- Profile Header Card -->
            <?php
            $dn = $currentUser['full_name'] ?: $currentUser['username'];
            $parts = explode(' ', trim($dn));
            $ini = strtoupper(substr($parts[0],0,1).(count($parts)>1 ? substr($parts[count($parts)-1],0,1) : ''));
            $isAdminUser = $currentUser['role'] === 'admin';
            ?>
            <div class="bg-white rounded-xl p-4 mb-5 flex items-center gap-4 border border-gray-100">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                     style="background:<?php echo $isAdminUser ? 'linear-gradient(135deg,#8B5CF6,#6D28D9)' : 'linear-gradient(135deg,#F59E0B,#F97316)'; ?>">
                    <?php echo htmlspecialchars($ini); ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($dn); ?></p>
                    <p class="text-xs text-gray-500 truncate profile-email">@<?php echo htmlspecialchars($currentUser['username']); ?> &bull; <?php echo htmlspecialchars($currentUser['email']); ?></p>
                </div>
                <div class="flex-shrink-0 flex items-center gap-2">
                    <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?php echo $isAdminUser ? 'bg-purple-100 text-purple-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                        <i class="fas <?php echo $isAdminUser ? 'fa-shield-alt' : 'fa-leaf'; ?> mr-1"></i>
                        <?php echo ucfirst($currentUser['role']); ?>
                    </span>
                    <button onclick="openModal('profileModal')"
                        class="text-xs px-2.5 py-1 rounded-full font-semibold bg-gray-100 hover:bg-gray-200 text-gray-600 transition">
                        <i class="fas fa-pen mr-1"></i>Edit
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="bg-white rounded-xl p-5 border border-gray-100">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2 text-sm"><i class="fas fa-bell text-yellow-500"></i>Notification Preferences</h3>

                    <div class="flex justify-between items-center pb-4 mb-4 border-b">
                        <div>
                            <p class="font-semibold text-sm">Push Notifications</p>
                            <p class="text-xs text-gray-500">Browser push notifications</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="pushNotif" <?php echo $s['push_notifications'] ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>

                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-2">Alert Types</p>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="alertCritical" <?php echo $s['alert_critical'] ? 'checked' : ''; ?> class="w-4 h-4 accent-yellow-500 rounded">
                                <span class="text-sm text-gray-700">Critical Alerts</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="alertWarnings" <?php echo $s['alert_warnings'] ? 'checked' : ''; ?> class="w-4 h-4 accent-yellow-500 rounded">
                                <span class="text-sm text-gray-700">Warnings</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="alertInfo" <?php echo $s['alert_informational'] ? 'checked' : ''; ?> class="w-4 h-4 accent-yellow-500 rounded">
                                <span class="text-sm text-gray-700">Informational</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-gray-100">
                    <h3 class="font-semibold text-gray-900 mb-4 flex items-center gap-2 text-sm"><i class="fas fa-sliders-h text-yellow-500"></i>System Configuration</h3>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Data Retention Period (days)</label>
                        <select id="dataRetention" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-yellow-400">
                            <option value="90"  <?php echo $s['data_retention']==='90'  ? 'selected' : ''; ?>>90 Days</option>
                            <option value="180" <?php echo $s['data_retention']==='180' ? 'selected' : ''; ?>>180 Days</option>
                            <option value="365" <?php echo $s['data_retention']==='365' ? 'selected' : ''; ?>>365 Days</option>
                            <option value="unlimited" <?php echo $s['data_retention']==='unlimited' ? 'selected' : ''; ?>>Unlimited</option>
                        </select>
                    </div>


                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            ML Model Confidence Threshold: <span id="thresholdVal" class="text-yellow-600 font-bold"><?php echo $s['ml_confidence']; ?>%</span>
                        </label>
                        <input type="range" id="mlConfidence" min="50" max="99" value="<?php echo $s['ml_confidence']; ?>" class="w-full accent-yellow-500"
                            oninput="document.getElementById('thresholdVal').textContent = this.value + '%'">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>50%</span><span>99%</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ML Model Training Section -->
            <div class="bg-white rounded-xl p-5 mt-5 border border-gray-100">
                <!-- Header row -->
                <div class="flex items-start justify-between mb-1 gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-900 text-sm flex items-center gap-2">
                            <i class="fas fa-brain text-yellow-500"></i>
                            ML Model — Bee Sound Classifier
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Upload labelled bee sounds, then retrain the CNN to classify colony health in real time.</p>
                    </div>
                    <span id="modelStatusBadge" class="flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-semibold bg-gray-100 text-gray-500 mt-1">
                        <i class="fas fa-circle-notch fa-spin mr-1"></i>Checking…
                    </span>
                </div>

                <!-- Model info bar + Retrain button -->
                <div id="modelInfoBar" class="hidden mt-4 mb-5 space-y-3">
                    <!-- Stats row -->
                    <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-xl flex items-center justify-between gap-4 flex-wrap">
                        <p id="modelInfoText" class="text-xs text-gray-600"></p>
                        <button id="retrainBtn" onclick="retrainModel()"
                            class="flex-shrink-0 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all">
                            <i class="fas fa-sync-alt"></i> Retrain Model
                        </button>
                    </div>
                    <!-- Accuracy metrics (shown only when model exists) -->
                    <div id="metricsRow" class="hidden grid grid-cols-3 gap-3">
                        <div class="bg-green-50 border border-green-100 rounded-xl p-3 text-center">
                            <p class="text-xs text-gray-400 mb-1">Validation Accuracy</p>
                            <p class="text-xl font-bold text-green-600" id="metricValAcc">—</p>
                        </div>
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-center">
                            <p class="text-xs text-gray-400 mb-1">Samples Used</p>
                            <p class="text-xl font-bold text-blue-600" id="metricSamples">—</p>
                        </div>
                        <div class="bg-purple-50 border border-purple-100 rounded-xl p-3 text-center">
                            <p class="text-xs text-gray-400 mb-1">Classes Trained</p>
                            <p class="text-xl font-bold text-purple-600" id="metricClasses">—</p>
                        </div>
                    </div>
                    <!-- Per-class breakdown -->
                    <div id="classBreakdown" class="hidden bg-white border border-gray-200 rounded-xl p-4">
                        <p class="text-xs font-bold text-gray-600 mb-3 flex items-center gap-2">
                            <i class="fas fa-chart-bar text-yellow-500"></i> Dataset Breakdown
                            <span id="balanceWarning" class="hidden ml-auto text-xs font-semibold text-orange-600 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-full">
                                <i class="fas fa-exclamation-triangle mr-1"></i>Imbalanced dataset
                            </span>
                        </p>
                        <div id="classBreakdownBars" class="space-y-2"></div>
                    </div>
                </div>

                <!-- Training status panel (shown during/after training) -->
                <div id="trainingPanel" class="hidden mb-5 rounded-xl overflow-hidden border border-gray-200">
                    <!-- Status bar -->
                    <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <span id="trainingStatusBadge" class="inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full font-bold bg-yellow-100 text-yellow-700">
                            <i class="fas fa-circle-notch fa-spin"></i>Training in progress…
                        </span>
                        <span class="text-xs text-gray-400">Model will be ready when complete</span>
                    </div>
                    <!-- Error detail — only visible on failure -->
                    <div id="trainingErrorBox" class="hidden bg-red-50 px-4 py-3">
                        <p class="text-xs font-bold text-red-700 mb-1"><i class="fas fa-exclamation-circle mr-1"></i>Error Details</p>
                        <div id="trainingErrorLog" class="font-mono text-xs text-red-600 space-y-0.5 max-h-36 overflow-y-auto"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <!-- Upload Form -->
                    <div>
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Sound Classification <span class="text-red-500">*</span></label>
                            <select id="soundClassification" class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400 text-sm">
                                <option value="normal">Normal</option>
                                <option value="queenless">Queenless</option>
                                <option value="swarming">Swarming</option>
                                <option value="varroa_mite">Pest Detected</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Select the condition that matches this sound sample</p>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Upload Audio File <span class="text-red-500">*</span></label>
                            <div id="dropZone" class="border border-dashed border-yellow-300 rounded-xl p-5 text-center cursor-pointer hover:bg-yellow-50 transition-colors" onclick="document.getElementById('audioFile').click()">
                                <i class="fas fa-upload text-yellow-500 text-2xl mb-2"></i>
                                <p class="text-sm font-medium text-gray-700">Click to upload or drag and drop</p>
                                <p class="text-xs text-gray-400 mt-0.5">WAV, MP3, M4A, OGG (max 50MB)</p>
                                <p id="fileName" class="text-sm text-yellow-600 mt-2 hidden"></p>
                            </div>
                            <input type="file" id="audioFile" accept=".wav,.mp3,.m4a,.ogg" class="hidden" onchange="handleFileSelect(this)">
                        </div>

                        <button id="uploadBtn" onclick="uploadSound()" disabled
                            class="w-full py-3 bg-gray-300 text-gray-500 rounded-lg font-semibold flex items-center justify-center gap-2 cursor-not-allowed transition-all">
                            <i class="fas fa-upload"></i> Upload Sound Sample
                        </button>

                        <div id="uploadProgress" class="hidden mt-4">
                            <div class="flex justify-between text-xs text-gray-600 mb-1">
                                <span>Uploading…</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div id="progressBar" class="h-full bg-yellow-500 transition-all" style="width:0%"></div>
                            </div>
                        </div>

                        <div id="uploadResult" class="hidden mt-4 p-3 rounded-lg text-sm"></div>

                        <!-- ML prediction result shown after upload -->
                        <div id="predictionResult" class="hidden mt-4 p-4 rounded-xl border-2"></div>
                    </div>

                    <!-- Recent Uploads -->
                    <div>
                        <p class="text-xs font-semibold text-gray-600 mb-2">Recent Uploads</p>
                        <div id="uploadsWrapper" class="border border-gray-100 rounded-xl p-3 bg-gray-50 min-h-48 max-h-96 overflow-y-auto">
                            <div id="recentUploads">
                                <p class="text-sm text-gray-400 text-center mt-8">No sound files uploaded yet</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-<?php echo isAdmin() ? '3' : '2'; ?> gap-4 mt-5">
                <div class="bg-white rounded-xl p-4 text-center border border-gray-100">
                    <div class="w-9 h-9 bg-yellow-50 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-user text-yellow-500"></i>
                    </div>
                    <h3 class="font-semibold text-sm mb-0.5">Account</h3>
                    <p class="text-xs text-gray-400 mb-3">Update your username and email</p>
                    <button onclick="openModal('profileModal')" class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-xs font-semibold transition">Edit Profile</button>
                </div>
                <div class="bg-white rounded-xl p-4 text-center border border-gray-100">
                    <div class="w-9 h-9 bg-yellow-50 rounded-lg flex items-center justify-center mx-auto mb-2">
                        <i class="fas fa-shield-alt text-yellow-500"></i>
                    </div>
                    <h3 class="font-semibold text-sm mb-0.5">Security</h3>
                    <p class="text-xs text-gray-400 mb-3">Change your login password</p>
                    <button onclick="openModal('passwordModal')" class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-xs font-semibold transition">Change Password</button>
                </div>
                <?php if (isAdmin()): ?>
                <div class="bg-white rounded-xl p-4 text-center border border-purple-100">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mx-auto mb-2"
                         style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);">
                        <i class="fas fa-users text-white text-sm"></i>
                    </div>
                    <h3 class="font-semibold text-sm mb-0.5">Manage Users</h3>
                    <p class="text-xs text-gray-400 mb-3">Create and manage beekeeper accounts</p>
                    <a href="users.php"
                       class="inline-block px-3 py-1.5 text-white rounded-lg text-xs font-semibold transition"
                       style="background:linear-gradient(135deg,#8B5CF6,#6D28D9);">
                        <i class="fas fa-arrow-right mr-1"></i> User Manager
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Edit Profile Modal -->
            <div id="profileModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-base font-semibold flex items-center gap-2"><i class="fas fa-user text-yellow-500"></i> Edit Profile</h3>
                        <button onclick="closeModal('profileModal')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100"><i class="fas fa-times"></i></button>
                    </div>
                    <div id="profileMsg" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                        <input id="editUsername" type="text" value="<?php echo htmlspecialchars($currentUser['username']); ?>"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input id="editEmail" type="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
                    </div>
                    <div class="flex gap-3">
                        <button onclick="closeModal('profileModal')" class="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                        <button onclick="saveProfile()" class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold text-sm transition">Save Changes</button>
                    </div>
                </div>
            </div>

            <!-- Change Password Modal -->
            <div id="passwordModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
                <div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-base font-semibold flex items-center gap-2"><i class="fas fa-shield-alt text-yellow-500"></i> Change Password</h3>
                        <button onclick="closeModal('passwordModal')" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100"><i class="fas fa-times"></i></button>
                    </div>
                    <div id="passwordMsg" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Current Password</label>
                        <input id="currentPassword" type="password" placeholder="Enter current password"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">New Password</label>
                        <input id="newPassword" type="password" placeholder="Enter new password"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400"
                            oninput="checkPasswordStrength(this.value)">
                        <div id="pwStrengthBar" class="hidden mt-2">
                            <div class="h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div id="pwStrengthFill" class="h-full rounded-full transition-all duration-300" style="width:0%"></div>
                            </div>
                            <p id="pwStrengthLabel" class="text-xs mt-1 font-semibold"></p>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Confirm New Password</label>
                        <input id="confirmPassword" type="password" placeholder="Confirm new password"
                            class="w-full px-4 py-2 border-2 border-gray-200 rounded-lg focus:outline-none focus:border-yellow-400">
                    </div>
                    <div class="flex gap-3">
                        <button onclick="closeModal('passwordModal')" class="flex-1 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">Cancel</button>
                        <button onclick="savePassword()" class="flex-1 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold text-sm transition">Update Password</button>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-5">
                <button onclick="window.location.reload()" class="px-4 py-2 border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition">Cancel</button>
                <button onclick="saveSettings()" class="px-4 py-2 bg-yellow-500 text-white rounded-lg text-sm font-semibold hover:bg-yellow-600 transition">
                    <i class="fas fa-save mr-1.5"></i>Save Changes
                </button>
            </div>
        </div>
    </main>

    <script>
        function syncNow() {
            const btn  = document.getElementById('syncNowBtn');
            const icon = document.getElementById('syncIcon');
            const res  = document.getElementById('syncResult');

            btn.disabled = true;
            icon.classList.add('fa-spin');
            res.classList.add('hidden');

            fetch('api/sync_devices.php', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    res.classList.remove('hidden');
                    if (data.success) {
                        const r = data.last_reading;
                        let html = '';

                        const devColor = data.online > 0 ? 'text-green-700' : 'text-red-600';
                        const devIcon  = data.online > 0 ? 'fa-wifi' : 'fa-wifi-slash';
                        html += `<div class="flex items-center gap-2 mb-2">
                                    <i class="fas ${devIcon} ${devColor}"></i>
                                    <span class="${devColor} font-semibold">${data.online}/${data.total} device(s) online</span>
                                 </div>`;

                        if (r) {
                            const fresh      = r.seconds_ago <= 120;
                            const freshColor = fresh ? 'text-green-600' : 'text-yellow-600';
                            const freshIcon  = fresh ? 'fa-check-circle' : 'fa-clock';
                            html += `<div class="border-t border-gray-200 pt-2 mt-1 grid grid-cols-2 gap-x-4 gap-y-1">
                                        <div class="col-span-2 flex items-center gap-1 ${freshColor} font-semibold mb-1">
                                            <i class="fas ${freshIcon} text-xs"></i>
                                            Last data from <strong class="ml-1">${r.hive}</strong>:&nbsp;${r.time_ago}
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-thermometer-half text-orange-400 text-xs"></i>
                                            Temp: <strong>${r.temperature}°C</strong>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-tint text-blue-400 text-xs"></i>
                                            Humidity: <strong>${r.humidity}%</strong>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-wind text-gray-400 text-xs"></i>
                                            CO2: <strong>${r.co2} ppm</strong>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <i class="fas fa-volume-up text-yellow-500 text-xs"></i>
                                            Sound: <strong>${r.sound_level} dB</strong>
                                        </div>
                                     </div>`;
                        } else {
                            html += `<div class="text-gray-400 text-xs mt-1"><i class="fas fa-database mr-1"></i>No sensor data received yet.</div>`;
                        }

                        res.className = 'mb-2 p-3 rounded-xl text-xs bg-gray-50 border border-gray-200 text-gray-700';
                        res.innerHTML = html;
                    } else {
                        res.className = 'mb-2 p-2 rounded-lg text-xs bg-red-50 border border-red-200 text-red-800';
                        res.innerHTML = '<i class="fas fa-times-circle mr-1"></i>' + data.message;
                    }
                })
                .catch(() => {
                    res.className = 'mb-2 p-2 rounded-lg text-xs bg-red-50 border border-red-200 text-red-800';
                    res.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Network error.';
                    res.classList.remove('hidden');
                })
                .finally(() => {
                    icon.classList.remove('fa-spin');
                    btn.disabled = false;
                });
        }

        function saveSettings() {
            const data = new URLSearchParams({
                push_notifications:     document.getElementById('pushNotif').checked     ? 1 : 0,
                alert_critical:         document.getElementById('alertCritical').checked ? 1 : 0,
                alert_warnings:         document.getElementById('alertWarnings').checked ? 1 : 0,
                alert_informational:    document.getElementById('alertInfo').checked     ? 1 : 0,
                data_retention:         document.getElementById('dataRetention').value,
                ml_confidence:          document.getElementById('mlConfidence').value,
            });

            fetch('save_settings.php', { method: 'POST', body: data })
                .then(r => r.json())
                .then(res => {
                    const el = document.getElementById('settingsMsg');
                    el.classList.remove('hidden','bg-green-50','border-green-200','text-green-800','bg-red-50','border-red-200','text-red-800');
                    if (res.success) {
                        el.className = 'mb-4 p-4 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800';
                        el.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + res.message;
                    } else {
                        el.className = 'mb-4 p-4 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
                        el.innerHTML = '<i class="fas fa-times-circle mr-2"></i>' + res.message;
                    }
                    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    setTimeout(() => el.classList.add('hidden'), 4000);
                })
                .catch(() => {
                    const el = document.getElementById('settingsMsg');
                    el.className = 'mb-4 p-4 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
                    el.innerHTML = '<i class="fas fa-times-circle mr-2"></i>Network error. Please try again.';
                    el.classList.remove('hidden');
                });
        }

        // Drag and drop
        const dropZone = document.getElementById('dropZone');
        dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('bg-yellow-50'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('bg-yellow-50'));
        dropZone.addEventListener('drop', e => {
            e.preventDefault();
            dropZone.classList.remove('bg-yellow-50');
            const file = e.dataTransfer.files[0];
            if (file) setFile(file);
        });

        function handleFileSelect(input) {
            if (input.files[0]) setFile(input.files[0]);
        }

        function setFile(file) {
            const allowed = ['audio/wav', 'audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/x-m4a'];
            const ext = file.name.split('.').pop().toLowerCase();
            const allowedExt = ['wav', 'mp3', 'm4a', 'ogg'];
            if (!allowedExt.includes(ext)) {
                showResult('error', 'Invalid file type. Please upload WAV, MP3, M4A, or OGG.');
                return;
            }
            if (file.size > 50 * 1024 * 1024) {
                showResult('error', 'File too large. Maximum size is 50MB.');
                return;
            }
            document.getElementById('fileName').textContent = '✓ ' + file.name;
            document.getElementById('fileName').classList.remove('hidden');
            const btn = document.getElementById('uploadBtn');
            btn.disabled = false;
            btn.className = 'w-full py-3 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg font-semibold flex items-center justify-center gap-2 cursor-pointer transition-all';
            // Store file for upload
            btn._file = file;
        }

        function uploadSound() {
            const btn = document.getElementById('uploadBtn');
            const file = btn._file;
            if (!file) return;

            const classification = document.getElementById('soundClassification').value;
            const formData = new FormData();
            formData.append('audio_file', file);
            formData.append('classification', classification);

            document.getElementById('uploadProgress').classList.remove('hidden');
            document.getElementById('uploadResult').classList.add('hidden');
            document.getElementById('predictionResult').classList.add('hidden');
            btn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    document.getElementById('progressBar').style.width = pct + '%';
                    document.getElementById('progressPercent').textContent = pct + '%';
                }
            };
            xhr.onload = () => {
                document.getElementById('uploadProgress').classList.add('hidden');
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        showUploadResult('success', res.message);
                        if (res.prediction) showPrediction(res.prediction);
                        loadRecentUploads();
                        loadModelStatus();
                        document.getElementById('audioFile').value = '';
                        document.getElementById('fileName').classList.add('hidden');
                        btn._file = null;
                        btn.disabled = true;
                        btn.className = 'w-full py-3 bg-gray-300 text-gray-500 rounded-lg font-semibold flex items-center justify-center gap-2 cursor-not-allowed transition-all';
                    } else {
                        showUploadResult('error', res.message);
                        btn.disabled = false;
                    }
                } catch(e) {
                    showUploadResult('error', 'Upload failed. Please try again.');
                    btn.disabled = false;
                }
            };
            xhr.onerror = () => {
                document.getElementById('uploadProgress').classList.add('hidden');
                showUploadResult('error', 'Network error. Please try again.');
                btn.disabled = false;
            };
            xhr.open('POST', 'upload_sound.php');
            xhr.send(formData);
        }

        function showUploadResult(type, msg) {
            const el = document.getElementById('uploadResult');
            if (type === 'success') {
                el.className = 'mt-4 p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800';
                el.innerHTML = '<i class="fas fa-check-circle mr-2"></i>' + msg;
            } else {
                el.className = 'mt-4 p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
                el.innerHTML = '<i class="fas fa-times-circle mr-2"></i>' + msg;
            }
            el.classList.remove('hidden');
        }

        function showPrediction(pred) {
            const el = document.getElementById('predictionResult');
            const colorMap = {
                normal:      { bg:'#D1FAE5', border:'#6EE7B7', text:'#065F46', icon:'fa-check-circle' },
                queenless:   { bg:'#FEE2E2', border:'#FCA5A5', text:'#991B1B', icon:'fa-times-circle' },
                swarming:    { bg:'#EDE9FE', border:'#C4B5FD', text:'#5B21B6', icon:'fa-arrow-circle-right' },
                varroa_mite: { bg:'#FEE2E2', border:'#FCA5A5', text:'#991B1B', icon:'fa-bug' },
            };
            const c = colorMap[pred.status] || { bg:'#F3F4F6', border:'#D1D5DB', text:'#374151', icon:'fa-question-circle' };
            el.style.cssText = `background:${c.bg};border-color:${c.border};color:${c.text}`;
            el.className = 'mt-4 p-4 rounded-xl border-2';

            const probs = Object.entries(pred.all_probs || {}).sort(([,a],[,b]) => b - a);
            el.innerHTML = `
                <div class="flex items-center gap-2 mb-3">
                    <i class="fas ${c.icon}"></i>
                    <span class="font-bold text-sm">ML Prediction: ${escHtml(pred.label)}</span>
                    <span class="ml-auto text-xs font-bold opacity-75">${pred.confidence}% confidence</span>
                </div>
                <div class="space-y-1.5">
                    ${probs.map(([k, v]) => `
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-36 truncate opacity-80">${escHtml(k.replace(/_/g,' '))}</span>
                            <div class="flex-1 h-1.5 rounded-full" style="background:rgba(0,0,0,0.12)">
                                <div class="h-full rounded-full" style="width:${v}%;background:currentColor;opacity:0.65;transition:width 0.6s ease"></div>
                            </div>
                            <span class="w-10 text-right font-mono">${v}%</span>
                        </div>`).join('')}
                </div>
                ${(pred.frequency && !pred.frequency.error) ? `
                <div class="mt-3 pt-3 border-t" style="border-color:rgba(0,0,0,0.12)">
                    <p class="text-xs font-semibold mb-2 opacity-80"><i class="fas fa-wave-square mr-1"></i>Frequency Analysis</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div class="flex justify-between"><span class="opacity-70">Dominant Freq</span><span class="font-mono font-semibold">${pred.frequency.dominant_freq} Hz</span></div>
                        <div class="flex justify-between"><span class="opacity-70">Mean Freq</span><span class="font-mono font-semibold">${pred.frequency.mean_freq} Hz</span></div>
                        <div class="flex justify-between"><span class="opacity-70">Spectral Centroid</span><span class="font-mono font-semibold">${pred.frequency.spectral_centroid} Hz</span></div>
                        <div class="flex justify-between"><span class="opacity-70">Sound Level</span><span class="font-mono font-semibold">${pred.frequency.sound_level}%</span></div>
                        <div class="flex justify-between"><span class="opacity-70">Freq Range</span><span class="font-mono font-semibold">${pred.frequency.freq_min}–${pred.frequency.freq_max} Hz</span></div>
                        <div class="flex justify-between"><span class="opacity-70">Duration</span><span class="font-mono font-semibold">${pred.frequency.duration_sec}s @ ${pred.frequency.sample_rate} Hz</span></div>
                    </div>
                </div>` : ''}`;
            el.classList.remove('hidden');
        }

        const _isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        let _allUploads = [];
        let _activeCategory = 'all';
        let _currentAudio = null;
        let _currentBtn   = null;

        const CAT_COLORS = {
            normal:      { bg:'#D1FAE5', text:'#065F46', dot:'#10B981' },
            queenless:   { bg:'#FEE2E2', text:'#991B1B', dot:'#EF4444' },
            swarming:    { bg:'#EDE9FE', text:'#5B21B6', dot:'#8B5CF6' },
            varroa_mite: { bg:'#FFF7ED', text:'#9A3412', dot:'#F97316' },
        };

        function playSound(btn, url) {
            // Stop whatever is playing
            if (_currentAudio && !_currentAudio.paused) {
                _currentAudio.pause();
                if (_currentBtn) {
                    _currentBtn.innerHTML = '<i class="fas fa-play text-xs"></i>';
                    _currentBtn.style.background = '';
                }
                if (_currentBtn === btn) { _currentAudio = null; _currentBtn = null; return; }
            }
            _currentAudio = new Audio(url);
            _currentBtn   = btn;
            btn.innerHTML = '<i class="fas fa-pause text-xs"></i>';
            btn.style.background = '#FEF3C7';
            _currentAudio.play().catch(() => {});
            _currentAudio.onended = () => {
                btn.innerHTML = '<i class="fas fa-play text-xs"></i>';
                btn.style.background = '';
                _currentAudio = null; _currentBtn = null;
            };
        }

        function renderUploads() {
            const container = document.getElementById('recentUploads');
            const filtered  = _activeCategory === 'all'
                ? _allUploads
                : _allUploads.filter(u => u.classification_key === _activeCategory);

            if (filtered.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-400 text-center py-6">No sounds in this category</p>';
                return;
            }
            container.innerHTML = filtered.map(u => {
                const c   = CAT_COLORS[u.classification_key] || { bg:'#F3F4F6', text:'#374151', dot:'#9CA3AF' };
                const url = `uploads/sounds/${encodeURIComponent(u.stored_filename)}`;
                return `
                <div class="flex items-center gap-2 p-2.5 bg-white rounded-xl mb-2 border border-gray-100 shadow-sm" id="sound-row-${u.id}">
                    <button onclick="playSound(this,'${url}')"
                            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg transition"
                            style="background:#FEF9C3;color:#92400E" title="Play / Pause">
                        <i class="fas fa-play text-xs"></i>
                    </button>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold truncate text-gray-800">${escHtml(u.filename)}</p>
                        <p class="text-xs text-gray-400">${u.size} &bull; ${u.uploaded_at}</p>
                    </div>
                    <span class="flex-shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full"
                          style="background:${c.bg};color:${c.text}">${escHtml(u.classification)}</span>
                    <button onclick="deleteSound(${u.id})"
                            class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 hover:text-red-600 transition"
                            title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>`;
            }).join('');
        }

        function setCategory(key) {
            _activeCategory = key;
            document.querySelectorAll('.cat-tab').forEach(t => {
                const active = t.dataset.cat === key;
                t.style.background    = active ? '#F59E0B' : '#F3F4F6';
                t.style.color         = active ? '#fff'    : '#6B7280';
                t.style.fontWeight    = active ? '700'     : '500';
            });
            renderUploads();
        }

        function loadRecentUploads() {
            fetch('upload_sound.php?action=list')
                .then(r => r.json())
                .then(data => {
                    if (!data.uploads || data.uploads.length === 0) {
                        document.getElementById('uploadsWrapper').innerHTML = '<p class="text-sm text-gray-400 text-center py-6">No sound files uploaded yet</p>';
                        return;
                    }
                    _allUploads = data.uploads;

                    // Count per category
                    const counts = {};
                    _allUploads.forEach(u => { counts[u.classification_key] = (counts[u.classification_key] || 0) + 1; });

                    // Build category tabs above the list
                    const tabs = [
                        { key:'all',         label:'All',          count: _allUploads.length },
                        { key:'normal',       label:'Normal',       count: counts['normal']      || 0 },
                        { key:'queenless',    label:'Queenless',    count: counts['queenless']   || 0 },
                        { key:'swarming',     label:'Swarming',     count: counts['swarming']    || 0 },
                        { key:'varroa_mite',  label:'Pest',         count: counts['varroa_mite'] || 0 },
                    ];

                    const tabsHtml = `
                    <div class="flex flex-wrap gap-1.5 mb-3">
                        ${tabs.map(t => `
                        <button class="cat-tab text-xs px-2.5 py-1 rounded-full transition"
                                data-cat="${t.key}"
                                style="background:${t.key==='all'?'#F59E0B':'#F3F4F6'};color:${t.key==='all'?'#fff':'#6B7280'};font-weight:${t.key==='all'?'700':'500'}"
                                onclick="setCategory('${t.key}')">
                            ${t.label} <span class="opacity-70">(${t.count})</span>
                        </button>`).join('')}
                    </div>
                    <div id="recentUploads"></div>`;

                    const wrapper = document.getElementById('uploadsWrapper');
                    if (wrapper) { wrapper.innerHTML = tabsHtml; }

                    renderUploads();
                })
                .catch(() => {});
        }

        function deleteSound(id) {
            const doDelete = () => {
                if (_currentAudio && !_currentAudio.paused) _currentAudio.pause();
                fetch('upload_sound.php?action=delete&id=' + id)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            _allUploads = _allUploads.filter(u => u.id !== id);
                            if (_allUploads.length === 0) {
                                document.getElementById('uploadsWrapper').innerHTML =
                                    '<p class="text-sm text-gray-400 text-center py-6">No sound files uploaded yet</p>';
                            } else {
                                loadRecentUploads();
                            }
                        } else {
                            alert('Error: ' + (data.message || 'Could not delete file.'));
                        }
                    })
                    .catch(() => alert('Connection error while deleting.'));
            };
            if (typeof window.adminDelete === 'function') {
                window.adminDelete('Delete this sound file? This action cannot be undone.', doDelete);
            } else {
                if (confirm('Delete this sound file? This cannot be undone.')) doDelete();
            }
        }

        // ── ML Model Status & Training ────────────────────────────────
        let _trainingPollTimer = null;

        function loadModelStatus() {
            fetch('api/ml_train.php?action=status')
                .then(r => r.json())
                .then(data => {
                    if (!data.success) return;
                    updateModelStatusUI(data);
                    if (data.status === 'running') startTrainingPoll();
                })
                .catch(() => {});
        }

        const CLASS_LABELS = {
            normal:      'Normal',
            queenless:   'Queenless',
            swarming:    'Swarming',
            varroa_mite: 'Pest Detected',
        };
        const CLASS_COLORS = {
            normal:      '#22C55E',
            queenless:   '#EF4444',
            swarming:    '#8B5CF6',
            varroa_mite: '#EC4899',
        };

        function updateModelStatusUI(data) {
            const badge      = document.getElementById('modelStatusBadge');
            const infoBar    = document.getElementById('modelInfoBar');
            const infoText   = document.getElementById('modelInfoText');
            const retrainBtn = document.getElementById('retrainBtn');

            if (data.model_exists) {
                badge.className = 'flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-semibold bg-green-100 text-green-700 mt-1';
                badge.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Model Ready';
            } else {
                badge.className = 'flex-shrink-0 text-xs px-3 py-1.5 rounded-full font-semibold bg-gray-100 text-gray-500 mt-1';
                badge.innerHTML = '<i class="fas fa-times-circle mr-1"></i>Not Trained';
            }

            infoBar.classList.remove('hidden');
            const samplePart = `<i class="fas fa-database mr-1 text-yellow-600"></i>${data.sample_count} sound sample${data.sample_count !== 1 ? 's' : ''} in dataset`;
            const modelPart  = data.model_exists
                ? `<i class="fas fa-brain mr-1 text-yellow-600 ml-3"></i>Last trained: ${data.model_time}`
                : `<i class="fas fa-brain mr-1 text-gray-400 ml-3"></i>No trained model`;
            infoText.innerHTML = samplePart + modelPart;

            if (data.status === 'running') {
                retrainBtn.disabled = true;
                retrainBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Training…';
                retrainBtn.className = 'flex-shrink-0 px-4 py-2 bg-gray-300 text-gray-500 text-sm font-bold rounded-lg flex items-center gap-2 cursor-not-allowed';
            } else {
                retrainBtn.disabled = false;
                retrainBtn.innerHTML = '<i class="fas fa-sync-alt mr-1"></i>Retrain Model';
                retrainBtn.className = 'flex-shrink-0 px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold rounded-lg flex items-center gap-2 transition-all';
            }

            // ── Accuracy metrics ──────────────────────────────────
            const metricsRow = document.getElementById('metricsRow');
            const m = data.metrics || {};
            if (data.model_exists && m.val_accuracy !== undefined) {
                document.getElementById('metricValAcc').textContent  = m.val_accuracy + '%';
                document.getElementById('metricSamples').textContent = m.sample_count ?? data.sample_count;
                document.getElementById('metricClasses').textContent = (m.classes || []).length || '—';
                metricsRow.classList.remove('hidden');
            } else {
                metricsRow.classList.add('hidden');
            }

            // ── Per-class breakdown ───────────────────────────────
            const breakdown     = document.getElementById('classBreakdown');
            const barsContainer = document.getElementById('classBreakdownBars');
            const counts = data.class_counts || {};
            const total  = Object.values(counts).reduce((a, b) => a + b, 0);

            if (total > 0) {
                breakdown.classList.remove('hidden');
                const maxCount = Math.max(...Object.values(counts));
                const allClasses = ['normal','queenless','swarming','varroa_mite'];
                barsContainer.innerHTML = allClasses.map(cls => {
                    const count = counts[cls] || 0;
                    const pct   = total > 0 ? Math.round((count / total) * 100) : 0;
                    const barW  = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
                    const color = CLASS_COLORS[cls] || '#F59E0B';
                    const perClassAcc = m.per_class && m.per_class[cls] !== undefined
                        ? `<span class="text-xs text-gray-400 ml-1">${m.per_class[cls]}% F1</span>` : '';
                    return `<div class="flex items-center gap-3">
                        <span class="text-xs text-gray-600 w-36 truncate flex-shrink-0">${CLASS_LABELS[cls] || cls}</span>
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="width:${barW}%;background:${color}"></div>
                        </div>
                        <span class="text-xs font-bold text-gray-700 w-6 text-right">${count}</span>
                        <span class="text-xs text-gray-400 w-8">${pct}%</span>
                        ${perClassAcc}
                    </div>`;
                }).join('');

                // Balance warning: flag if any class is 3x smaller than largest
                const warn = document.getElementById('balanceWarning');
                const minCount = Math.min(...allClasses.map(c => counts[c] || 0).filter(v => v > 0));
                if (maxCount > 0 && minCount > 0 && maxCount / minCount >= 3) {
                    warn.classList.remove('hidden');
                } else {
                    warn.classList.add('hidden');
                }
            } else {
                breakdown.classList.add('hidden');
            }

            if (data.log && data.log.length > 0) {
                showTrainingPanel(data.log, data.status);
            }
        }

        function retrainModel() {
            if (!confirm('Start retraining the CNN model? This may take a few minutes.')) return;
            const btn = document.getElementById('retrainBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1"></i>Starting…';

            fetch('api/ml_train.php?action=start')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showTrainingPanel([], 'running');
                        startTrainingPoll();
                    } else {
                        alert('Could not start training: ' + data.message);
                        loadModelStatus();
                    }
                })
                .catch(() => { alert('Network error.'); loadModelStatus(); });
        }

        function showTrainingPanel(logLines, status) {
            const panel    = document.getElementById('trainingPanel');
            const badge    = document.getElementById('trainingStatusBadge');
            const errBox   = document.getElementById('trainingErrorBox');
            const errLog   = document.getElementById('trainingErrorLog');
            panel.classList.remove('hidden');

            const cfg = {
                running: ['bg-yellow-100 text-yellow-700', '<i class="fas fa-circle-notch fa-spin"></i>Training in progress…'],
                done:    ['bg-green-100 text-green-700',   '<i class="fas fa-check-circle"></i>Complete!'],
                error:   ['bg-red-100 text-red-700',       '<i class="fas fa-times-circle"></i>Training Failed'],
            };
            const [cls, html] = cfg[status] || cfg.running;
            badge.className = `inline-flex items-center gap-2 text-sm px-3 py-1.5 rounded-full font-bold ${cls}`;
            badge.innerHTML = html;

            // Show error detail box only on failure
            if (status === 'error' && logLines.length > 0) {
                const errorLines = logLines.filter(l =>
                    /error|traceback|exception|failed/i.test(l)
                );
                const toShow = errorLines.length > 0 ? errorLines : logLines.slice(-10);
                errLog.innerHTML = toShow.map(l => `<div>${escHtml(l)}</div>`).join('');
                errBox.classList.remove('hidden');
            } else {
                errBox.classList.add('hidden');
            }
        }

        function startTrainingPoll() {
            if (_trainingPollTimer) clearInterval(_trainingPollTimer);
            _trainingPollTimer = setInterval(() => {
                fetch('api/ml_train.php?action=status')
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) return;
                        updateModelStatusUI(data);
                        if (data.status !== 'running') {
                            clearInterval(_trainingPollTimer);
                            _trainingPollTimer = null;
                        }
                    })
                    .catch(() => {});
            }, 3000);
        }

        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        function checkPasswordStrength(val) {
            const bar   = document.getElementById('pwStrengthBar');
            const fill  = document.getElementById('pwStrengthFill');
            const label = document.getElementById('pwStrengthLabel');
            if (!val) { bar.classList.add('hidden'); return; }
            bar.classList.remove('hidden');

            let score = 0;
            if (val.length >= 8)  score++;
            if (val.length >= 12) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { pct:'20%', color:'#EF4444', text:'Weak',      cls:'text-red-500' },
                { pct:'40%', color:'#F97316', text:'Fair',      cls:'text-orange-500' },
                { pct:'60%', color:'#F59E0B', text:'Moderate',  cls:'text-yellow-600' },
                { pct:'80%', color:'#84CC16', text:'Strong',    cls:'text-lime-600' },
                { pct:'100%',color:'#22C55E', text:'Very Strong',cls:'text-green-600' },
            ];
            const lvl = levels[Math.min(score - 1, 4)] || levels[0];
            fill.style.width = lvl.pct;
            fill.style.background = lvl.color;
            label.textContent = lvl.text;
            label.className = 'text-xs mt-1 font-semibold ' + lvl.cls;
        }

        // Init on page load
        loadRecentUploads();
        loadModelStatus();

        // Modal helpers
        function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            const msgMap = { profileModal: 'profileMsg', passwordModal: 'passwordMsg' };
            if (msgMap[id]) document.getElementById(msgMap[id]).classList.add('hidden');
            if (id === 'passwordModal') {
                document.getElementById('pwStrengthBar').classList.add('hidden');
            }
        }
        window.addEventListener('click', e => {
            ['profileModal','passwordModal'].forEach(id => {
                if (e.target === document.getElementById(id)) closeModal(id);
            });
        });

        function showModalMsg(elId, type, msg) {
            const el = document.getElementById(elId);
            el.classList.remove('hidden','bg-green-50','border-green-200','text-green-800','bg-red-50','border-red-200','text-red-800');
            el.className = type === 'success'
                ? 'mb-4 p-3 rounded-lg text-sm bg-green-50 border border-green-200 text-green-800'
                : 'mb-4 p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-800';
            el.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'times'}-circle mr-2"></i>${msg}`;
        }

        function saveProfile() {
            const username = document.getElementById('editUsername').value.trim();
            const email    = document.getElementById('editEmail').value.trim();
            if (!username || !email) { showModalMsg('profileMsg','error','Username and email are required.'); return; }

            fetch('update_profile.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `username=${encodeURIComponent(username)}&email=${encodeURIComponent(email)}`
            })
            .then(r => r.json())
            .then(data => {
                showModalMsg('profileMsg', data.success ? 'success' : 'error', data.message);
            })
            .catch(() => showModalMsg('profileMsg','error','Request failed. Try again.'));
        }

        function savePassword() {
            const current  = document.getElementById('currentPassword').value;
            const newPass  = document.getElementById('newPassword').value;
            const confirm  = document.getElementById('confirmPassword').value;
            if (!current || !newPass || !confirm) { showModalMsg('passwordMsg','error','All fields are required.'); return; }
            if (newPass !== confirm) { showModalMsg('passwordMsg','error','New passwords do not match.'); return; }
            if (newPass.length < 6) { showModalMsg('passwordMsg','error','Password must be at least 6 characters.'); return; }

            fetch('update_password.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                body: `current=${encodeURIComponent(current)}&newpass=${encodeURIComponent(newPass)}`
            })
            .then(r => r.json())
            .then(data => {
                showModalMsg('passwordMsg', data.success ? 'success' : 'error', data.message);
                if (data.success) {
                    document.getElementById('currentPassword').value = '';
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                }
            })
            .catch(() => showModalMsg('passwordMsg','error','Request failed. Try again.'));
        }
    </script>
</body>
</html>