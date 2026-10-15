<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'config.php';

$token   = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$userId  = null;

// Ensure reset table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
)");

if ($token !== '') {
    $stmt = $conn->prepare(
        "SELECT pr.id, pr.user_id, u.username, u.full_name
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $valid  = true;
        $userId = (int)$row['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPass  = $_POST['new_password']     ?? '';
            $confPass = $_POST['confirm_password'] ?? '';

            if (strlen($newPass) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($newPass !== $confPass) {
                $error = 'Passwords do not match.';
            } else {
                $hash  = password_hash($newPass, PASSWORD_DEFAULT);
                $upd   = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->bind_param("si", $hash, $userId);
                $upd->execute();
                $upd->close();

                // Mark token used
                $mrk = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $mrk->bind_param("s", $token);
                $mrk->execute();
                $mrk->close();

                // Log
                $conn->query("INSERT INTO activity_log (user_id, event_type, details, severity)
                              VALUES ($userId, 'Password Reset', 'User reset their password via reset link', 'info')");

                $success = 'Password updated successfully! You can now sign in.';
                $valid   = false; // hide form
            }
        }
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
} else {
    $error = 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — BeeGuard IoT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; }
        .login-bg {
            background-image: url('honeybglog.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: fixed; inset: 0;
        }
        .login-overlay {
            background: linear-gradient(135deg, rgba(15,10,0,0.7) 0%, rgba(30,15,0,0.55) 100%);
            position: fixed; inset: 0;
            backdrop-filter: blur(2px);
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(32px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .login-card { animation: slideUp 0.55s cubic-bezier(0.34,1.56,0.64,1) both; }
        .form-input {
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            transition: all 0.25s;
            font-size: 15px;
            background: #F8FAFC;
        }
        .form-input:focus {
            outline: none;
            border-color: #F59E0B;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(245,158,11,0.18);
        }
        .submit-btn {
            background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%);
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            box-shadow: 0 6px 24px rgba(245,158,11,0.4);
            transition: all 0.25s;
        }
        .submit-btn:hover { box-shadow: 0 8px 32px rgba(245,158,11,0.5); transform: translateY(-2px); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="login-bg"></div>
    <div class="login-overlay"></div>

    <div class="login-card relative z-10 w-full max-w-[420px]">
        <!-- Logo -->
        <div class="text-center mb-7">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl text-4xl mb-4"
                 style="background:linear-gradient(135deg,#F59E0B,#F97316);">🐝</div>
            <h1 class="text-4xl font-black text-white tracking-tight">BeeGuard</h1>
            <p class="text-yellow-300/80 text-sm font-medium mt-1">Reset Your Password</p>
        </div>

        <div class="bg-white rounded-3xl overflow-hidden shadow-2xl">
            <div class="px-8 pt-8 pb-5">
                <h2 class="text-2xl font-bold text-gray-900">Set New Password</h2>
                <p class="text-gray-400 text-sm mt-1">Enter a new password for your account</p>
            </div>

            <?php if ($error && !$valid): ?>
            <div class="mx-8 mb-4 rounded-xl p-3.5 flex items-center gap-3"
                 style="background:#FFF1F2;border:1px solid #FECDD3;">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <p class="text-sm text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="mx-8 mb-4 rounded-xl p-3.5 flex items-center gap-3"
                 style="background:#F0FDF4;border:1px solid #BBF7D0;">
                <i class="fas fa-check-circle text-green-500"></i>
                <p class="text-sm text-green-700 font-medium"><?= htmlspecialchars($success) ?></p>
            </div>
            <div class="px-8 pb-8">
                <a href="login.php"
                   class="submit-btn block text-center text-white py-3.5 rounded-xl">
                    <i class="fas fa-sign-in-alt"></i> Back to Login
                </a>
            </div>
            <?php elseif ($valid): ?>
            <form method="POST" class="px-8 pb-8" id="resetForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <?php if ($error): ?>
                <div class="mb-4 rounded-xl p-3.5 flex items-center gap-3"
                     style="background:#FFF1F2;border:1px solid #FECDD3;">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <p class="text-sm text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
                </div>
                <?php endif; ?>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">New Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="new_password" id="newPwd"
                               placeholder="Min. 6 characters"
                               class="form-input w-full pl-10 pr-12 py-3" required minlength="6">
                        <button type="button" onclick="toggleField('newPwd','newPwdIcon')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye text-sm" id="newPwdIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="confirm_password" id="confPwd"
                               placeholder="Repeat new password"
                               class="form-input w-full pl-10 pr-12 py-3" required>
                        <button type="button" onclick="toggleField('confPwd','confPwdIcon')"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye text-sm" id="confPwdIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn w-full text-white py-3.5">
                    <i class="fas fa-key"></i> Reset Password
                </button>

                <p class="text-center mt-4 text-sm text-gray-400">
                    <a href="login.php" class="text-yellow-600 hover:text-yellow-700 font-medium">
                        <i class="fas fa-arrow-left text-xs"></i> Back to Login
                    </a>
                </p>
            </form>
            <?php else: ?>
            <div class="px-8 pb-8">
                <a href="login.php"
                   class="submit-btn block text-center text-white py-3.5 rounded-xl">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
            <?php endif; ?>

            <div class="border-t border-gray-100 px-8 py-4 flex items-center justify-between"
                 style="background:#FAFAFA;">
                <p class="text-xs text-gray-400">BeeGuard IoT Monitor v1.0</p>
                <span class="flex items-center gap-1.5 text-xs text-gray-400">
                    <span class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></span>
                    System Online
                </span>
            </div>
        </div>
    </div>

    <script>
    function toggleField(inputId, iconId) {
        const i = document.getElementById(inputId);
        const ic = document.getElementById(iconId);
        if (i.type === 'password') { i.type = 'text'; ic.className = 'fas fa-eye-slash text-sm'; }
        else { i.type = 'password'; ic.className = 'fas fa-eye text-sm'; }
    }
    </script>
</body>
</html>
