<?php
session_start();
if (isset($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare(
        "SELECT id, full_name, username, password, role, account_status
         FROM users WHERE username = ? LIMIT 1"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if ($user['account_status'] === 'inactive') {
                $error = "Your account is inactive. Please contact the administrator.";
            } else {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['role']      = $user['role'];
                // Log login event
                $uid  = (int)$user['id'];
                $ulog = $conn->real_escape_string($username);
                $conn->query("INSERT INTO activity_log (user_id, event_type, details, severity)
                              VALUES ($uid, 'user_login', 'User logged in: $ulog', 'info')");
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BeeGuard IoT</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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

        /* Floating hex particles */
        .particle {
            position: fixed;
            width: 6px; height: 6px;
            background: rgba(245,158,11,0.3);
            border-radius: 50%;
            animation: float-particle linear infinite;
            pointer-events: none;
        }
        @keyframes float-particle {
            0%   { transform: translateY(100vh) scale(0); opacity:0; }
            10%  { opacity:1; }
            90%  { opacity:0.6; }
            100% { transform: translateY(-100px) scale(1); opacity:0; }
        }

        /* Card entrance */
        @keyframes slideUp {
            from { opacity:0; transform:translateY(32px) scale(0.97); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        .login-card { animation: slideUp 0.55s cubic-bezier(0.34,1.56,0.64,1) both; }

        /* Bee float */
        @keyframes bee-float {
            0%,100% { transform:translateY(0) rotate(-5deg); }
            50%      { transform:translateY(-14px) rotate(5deg); }
        }
        .bee-float { animation: bee-float 3.5s ease-in-out infinite; }

        /* Input focus glow */
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

        /* Submit button */
        .submit-btn {
            background: linear-gradient(135deg, #F59E0B 0%, #F97316 100%);
            border-radius: 10px;
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.01em;
            box-shadow: 0 6px 24px rgba(245,158,11,0.4);
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }
        .submit-btn:hover {
            box-shadow: 0 8px 32px rgba(245,158,11,0.5);
            transform: translateY(-2px);
        }
        .submit-btn:active { transform: translateY(0) scale(0.98); }
        .submit-btn::after {
            content:'';
            position:absolute; inset:0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 60%);
        }

        /* Shimmer on logo */
        @keyframes shimmer-logo {
            0%,100% { box-shadow: 0 8px 32px rgba(245,158,11,0.4); }
            50%      { box-shadow: 0 12px 48px rgba(245,158,11,0.7); }
        }
        .logo-glow { animation: shimmer-logo 3s ease-in-out infinite; }

        /* Error shake */
        @keyframes shake {
            0%,100% { transform:translateX(0); }
            20%,60% { transform:translateX(-6px); }
            40%,80% { transform:translateX(6px); }
        }
        .shake { animation: shake 0.4s ease; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">

    <!-- BG + overlay -->
    <div class="login-bg"></div>
    <div class="login-overlay"></div>

    <!-- Floating particles -->
    <div class="particle" style="left:10%;animation-duration:8s;animation-delay:0s;"></div>
    <div class="particle" style="left:25%;animation-duration:11s;animation-delay:2s;width:4px;height:4px;"></div>
    <div class="particle" style="left:50%;animation-duration:9s;animation-delay:1s;width:8px;height:8px;"></div>
    <div class="particle" style="left:70%;animation-duration:12s;animation-delay:3s;"></div>
    <div class="particle" style="left:85%;animation-duration:10s;animation-delay:0.5s;width:5px;height:5px;"></div>
    <div class="particle" style="left:40%;animation-duration:7s;animation-delay:4s;"></div>

    <!-- Login Card -->
    <div class="login-card relative z-10 w-full max-w-[420px]">

        <!-- Logo -->
        <div class="text-center mb-7">
            <div class="logo-glow bee-float inline-flex items-center justify-center w-20 h-20 rounded-2xl text-4xl mb-4"
                 style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                🐝
            </div>
            <h1 class="text-4xl font-black text-white tracking-tight" style="text-shadow:0 2px 20px rgba(0,0,0,0.5);">
                BeeGuard
            </h1>
            <p class="text-yellow-300/80 text-sm font-medium mt-1" style="text-shadow:0 1px 8px rgba(0,0,0,0.5);">
                Edge-Enabled Hive Health Monitoring
            </p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-3xl overflow-hidden shadow-2xl" style="box-shadow:0 24px 80px rgba(0,0,0,0.35);">

            <!-- Card header -->
            <div class="px-8 pt-8 pb-5">
                <h2 class="text-2xl font-bold text-gray-900">Welcome back</h2>
                <p class="text-gray-400 text-sm mt-1">Sign in to access your hive dashboard</p>
            </div>


            <!-- Error -->
            <?php if (isset($error)): ?>
            <div class="mx-8 mb-4 rounded-xl p-3.5 flex items-center gap-3 shake"
                 style="background:#FFF1F2;border:1px solid #FECDD3;">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <p class="text-sm text-red-700 font-medium"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="px-8 pb-8" id="loginForm">

                <!-- Username -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-user text-sm"></i>
                        </span>
                        <input type="text" name="username" placeholder="Enter username"
                               class="form-input w-full pl-10 pr-4 py-3"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input type="password" name="password" id="pwdInput" placeholder="Enter password"
                               class="form-input w-full pl-10 pr-12 py-3" required>
                        <button type="button" onclick="togglePwd()"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="fas fa-eye text-sm" id="pwdIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end mb-6">
                    <button type="button" onclick="openForgotModal()"
                            class="text-sm text-yellow-600 hover:text-yellow-700 font-semibold transition-colors">
                        Forgot password?
                    </button>
                </div>

                <!-- Submit -->
                <button type="submit" class="submit-btn w-full text-white py-3.5" id="submitBtn">
                    <span id="btnText" class="relative z-10 flex items-center justify-center gap-2">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </span>
                </button>
            </form>

            <!-- Footer -->
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

    <!-- Forgot Password Modal -->
    <div id="forgotModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" style="animation:slideUp 0.3s ease both;">

            <!-- Header -->
            <div class="px-6 pt-6 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-lg"
                         style="background:linear-gradient(135deg,#F59E0B,#F97316);">
                        <i class="fas fa-key"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 text-lg" id="forgotTitle">Forgot Password</h3>
                        <p class="text-gray-400 text-xs" id="forgotSubtitle">Enter your username to continue</p>
                    </div>
                </div>
                <button onclick="closeForgotModal()"
                        class="absolute top-4 right-4 text-gray-400 hover:text-gray-600 w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Error box (shared) -->
            <div id="forgotError" class="hidden mx-6 mt-4 rounded-xl p-3 flex items-center gap-2"
                 style="background:#FFF1F2;border:1px solid #FECDD3;">
                <i class="fas fa-exclamation-circle text-red-500 text-sm flex-shrink-0"></i>
                <span class="text-sm text-red-700 font-medium" id="forgotErrorMsg"></span>
            </div>

            <!-- Step 1: Enter username -->
            <div id="forgotStep1" class="px-6 py-5">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
                <div class="relative mb-4">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-user text-sm"></i>
                    </span>
                    <input type="text" id="forgotUsername" placeholder="Enter your username"
                           class="form-input w-full pl-10 pr-4 py-3"
                           onkeydown="if(event.key==='Enter')stepGetQuestion()">
                </div>
                <button onclick="stepGetQuestion()" id="forgotBtn"
                        class="submit-btn w-full text-white py-3 rounded-xl text-sm font-bold">
                    <i class="fas fa-arrow-right text-xs mr-1"></i> Continue
                </button>
            </div>

            <!-- Step 2: Security question -->
            <div id="forgotStep2" class="hidden px-6 py-5">
                <div class="mb-4 p-3 rounded-xl" style="background:#FFFBEB;border:1px solid #FDE68A;">
                    <p class="text-xs text-yellow-700 font-semibold mb-1"><i class="fas fa-shield-alt mr-1"></i> Security Question</p>
                    <p class="text-sm text-yellow-900 font-medium" id="forgotQuestion"></p>
                </div>
                <!-- Attempt dots -->
                <div class="flex items-center gap-1.5 mb-3">
                    <span class="text-xs text-gray-500 font-medium mr-1">Attempts:</span>
                    <span class="w-3 h-3 rounded-full bg-green-400" id="dot1"></span>
                    <span class="w-3 h-3 rounded-full bg-green-400" id="dot2"></span>
                    <span class="w-3 h-3 rounded-full bg-green-400" id="dot3"></span>
                </div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Your Answer</label>
                <div class="relative mb-4">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-comment text-sm"></i>
                    </span>
                    <input type="text" id="forgotAnswer" placeholder="Type your answer here"
                           class="form-input w-full pl-10 pr-4 py-3"
                           onkeydown="if(event.key==='Enter')stepVerifyAnswer()">
                </div>
                <button onclick="stepVerifyAnswer()" id="forgotVerifyBtn"
                        class="submit-btn w-full text-white py-3 rounded-xl text-sm font-bold">
                    <i class="fas fa-check text-xs mr-1"></i> Verify Answer
                </button>
                <button onclick="forgotGoBack()"
                        class="w-full mt-2 py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-50 transition-colors border border-gray-200">
                    <i class="fas fa-arrow-left text-xs mr-1"></i> Back
                </button>
            </div>

            <!-- Step 3: Reset link -->
            <div id="forgotStep3" class="hidden px-6 py-5">
                <div class="text-center mb-4">
                    <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-check text-green-500 text-2xl"></i>
                    </div>
                    <p class="text-gray-800 font-bold text-base">Identity Verified!</p>
                    <p class="text-gray-500 text-xs mt-1">Click below to set your new password.</p>
                </div>
                <a id="forgotResetBtn" href="#" target="_blank"
                   class="submit-btn block text-center text-white py-3 rounded-xl text-sm font-bold mb-2">
                    <i class="fas fa-key text-xs mr-1"></i> Reset My Password
                </a>
                <button onclick="closeForgotModal()"
                        class="w-full py-2 rounded-xl text-sm text-gray-500 hover:bg-gray-50 transition-colors border border-gray-200">
                    Close
                </button>
            </div>

            <!-- Step locked -->
            <div id="forgotStepLocked" class="hidden px-6 py-8 text-center">
                <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-lock text-red-500 text-2xl"></i>
                </div>
                <p class="text-gray-800 font-bold text-base">Too Many Attempts</p>
                <p class="text-gray-500 text-xs mt-2 leading-relaxed">You have exceeded 3 incorrect attempts.<br>Please contact your administrator to reset your password.</p>
                <button onclick="closeForgotModal()"
                        class="mt-5 w-full py-2.5 rounded-xl text-sm font-semibold border border-gray-200 hover:bg-gray-50 transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
    function togglePwd() {
        const i = document.getElementById('pwdInput');
        const ic = document.getElementById('pwdIcon');
        if (i.type === 'password') { i.type = 'text'; ic.className = 'fas fa-eye-slash text-sm'; }
        else { i.type = 'password'; ic.className = 'fas fa-eye text-sm'; }
    }

    document.getElementById('loginForm').addEventListener('submit', function() {
        const btn  = document.getElementById('submitBtn');
        const txt  = document.getElementById('btnText');
        btn.disabled = true;
        txt.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
    });

    let forgotAttempts = 0;
    let forgotResetUrl = '';

    function forgotShowStep(step) {
        ['forgotStep1','forgotStep2','forgotStep3','forgotStepLocked'].forEach(id => {
            document.getElementById(id).classList.add('hidden');
        });
        document.getElementById(step).classList.remove('hidden');
        document.getElementById('forgotError').classList.add('hidden');
    }

    function forgotShowError(msg) {
        const box = document.getElementById('forgotError');
        document.getElementById('forgotErrorMsg').textContent = msg;
        box.classList.remove('hidden');
    }

    function updateAttemptDots(attemptsLeft) {
        const dots = ['dot1','dot2','dot3'];
        dots.forEach((id, i) => {
            document.getElementById(id).className = 'w-3 h-3 rounded-full ' +
                (i < attemptsLeft ? 'bg-green-400' : 'bg-red-300');
        });
    }

    function openForgotModal() {
        document.getElementById('forgotModal').classList.remove('hidden');
        forgotAttempts = 0;
        forgotResetUrl = '';
        document.getElementById('forgotUsername').value = '';
        document.getElementById('forgotTitle').textContent    = 'Forgot Password';
        document.getElementById('forgotSubtitle').textContent = 'Enter your username to continue';
        forgotShowStep('forgotStep1');
        setTimeout(() => document.getElementById('forgotUsername').focus(), 100);
    }

    function closeForgotModal() {
        document.getElementById('forgotModal').classList.add('hidden');
    }

    function forgotGoBack() {
        forgotAttempts = 0;
        document.getElementById('forgotTitle').textContent    = 'Forgot Password';
        document.getElementById('forgotSubtitle').textContent = 'Enter your username to continue';
        forgotShowStep('forgotStep1');
    }

    function stepGetQuestion() {
        const username = document.getElementById('forgotUsername').value.trim();
        if (!username) { forgotShowError('Please enter your username.'); return; }

        const btn = document.getElementById('forgotBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Checking...';

        const fd = new FormData();
        fd.append('action', 'get_question');
        fd.append('username', username);

        fetch('api/forgot_password.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-arrow-right text-xs mr-1"></i> Continue';
                if (!d.success) { forgotShowError(d.message || 'Something went wrong.'); return; }

                document.getElementById('forgotQuestion').textContent = d.question;
                document.getElementById('forgotAnswer').value = '';
                document.getElementById('forgotTitle').textContent    = 'Security Question';
                document.getElementById('forgotSubtitle').textContent = 'Answer to verify your identity';
                updateAttemptDots(3);
                forgotShowStep('forgotStep2');
                setTimeout(() => document.getElementById('forgotAnswer').focus(), 100);
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-arrow-right text-xs mr-1"></i> Continue';
                forgotShowError('Network error. Please try again.');
            });
    }

    function stepVerifyAnswer() {
        const answer = document.getElementById('forgotAnswer').value.trim();
        if (!answer) { forgotShowError('Please enter your answer.'); return; }

        const btn = document.getElementById('forgotVerifyBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs mr-1"></i> Verifying...';

        const fd = new FormData();
        fd.append('action', 'verify_answer');
        fd.append('answer', answer);

        fetch('api/forgot_password.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check text-xs mr-1"></i> Verify Answer';

                if (d.locked) {
                    forgotShowStep('forgotStepLocked');
                    return;
                }
                if (!d.success) {
                    forgotAttempts++;
                    const left = d.attempts_left ?? (3 - forgotAttempts);
                    updateAttemptDots(left);
                    forgotShowError(d.message || 'Incorrect answer.');
                    document.getElementById('forgotAnswer').value = '';
                    document.getElementById('forgotAnswer').focus();
                    return;
                }

                // Success
                const base = window.location.origin + window.location.pathname.replace('login.php','');
                forgotResetUrl = base + 'reset_password.php?token=' + d.token;
                document.getElementById('forgotResetBtn').href = forgotResetUrl;
                document.getElementById('forgotTitle').textContent    = 'Identity Verified';
                document.getElementById('forgotSubtitle').textContent = 'You can now reset your password';
                forgotShowStep('forgotStep3');
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check text-xs mr-1"></i> Verify Answer';
                forgotShowError('Network error. Please try again.');
            });
    }

    function copyResetLink() {
        navigator.clipboard.writeText(forgotResetUrl).then(() => {
            const btn = document.getElementById('copyBtn');
            btn.innerHTML = '<i class="fas fa-check text-xs mr-1"></i> Copied!';
            btn.style.background = '#F0FDF4';
            btn.style.borderColor = '#86EFAC';
            btn.style.color = '#15803D';
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-copy text-xs mr-1"></i> Copy Link';
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.style.color = '';
            }, 2000);
        });
    }
    </script>
</body>
</html>
