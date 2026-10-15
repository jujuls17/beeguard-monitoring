<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Step 1: get security question for username ───────────────
if ($action === 'get_question') {
    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter your username.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, full_name, account_status, security_question FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with that username.']);
        exit;
    }
    if ($user['account_status'] === 'inactive') {
        echo json_encode(['success' => false, 'message' => 'This account is inactive. Contact your administrator.']);
        exit;
    }
    if (empty($user['security_question'])) {
        echo json_encode(['success' => false, 'message' => 'No security question set for this account. Contact your administrator.']);
        exit;
    }

    // Store in session for verification
    $_SESSION['fp_username']  = $username;
    $_SESSION['fp_user_id']   = (int)$user['id'];
    $_SESSION['fp_attempts']  = 0;
    $_SESSION['fp_locked']    = false;

    echo json_encode([
        'success'   => true,
        'question'  => $user['security_question'],
        'full_name' => $user['full_name'],
    ]);
    exit;
}

// ── Step 2: verify answer ────────────────────────────────────
if ($action === 'verify_answer') {
    $answer = trim($_POST['answer'] ?? '');

    if (empty($_SESSION['fp_username']) || empty($_SESSION['fp_user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    if (!empty($_SESSION['fp_locked'])) {
        echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please try again later.', 'locked' => true]);
        exit;
    }
    if ($answer === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter your answer.']);
        exit;
    }

    $userId = (int)$_SESSION['fp_user_id'];
    $stmt   = $conn->prepare("SELECT security_answer FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $_SESSION['fp_attempts'] = ($_SESSION['fp_attempts'] ?? 0) + 1;
    $attemptsLeft = 3 - (int)$_SESSION['fp_attempts'];

    // Case-insensitive answer comparison
    $correct = $row && password_verify(strtolower($answer), $row['security_answer']);

    if (!$correct) {
        if ($attemptsLeft <= 0) {
            $_SESSION['fp_locked'] = true;
            unset($_SESSION['fp_username'], $_SESSION['fp_user_id']);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please try again later.', 'locked' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => "Incorrect answer. {$attemptsLeft} attempt(s) remaining.", 'attempts_left' => $attemptsLeft]);
        }
        exit;
    }

    // Correct — generate reset token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Invalidate old tokens
    $d = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    $d->bind_param("i", $userId);
    $d->execute();
    $d->close();

    $ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $ins->bind_param("iss", $userId, $token, $expires);
    $ins->execute();
    $ins->close();

    // Clear session state
    unset($_SESSION['fp_username'], $_SESSION['fp_user_id'], $_SESSION['fp_attempts'], $_SESSION['fp_locked']);

    echo json_encode(['success' => true, 'token' => $token]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
