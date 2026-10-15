<?php
ob_start();
ini_set('display_errors', 0);

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');
ob_clean();
ini_set('display_errors', 0);
error_reporting(0);

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'messages':   echo json_encode(getMessages());  break;
        case 'send':       echo json_encode(sendMessage());  break;
        case 'mark_read':  echo json_encode(markRead());     break;
        case 'unread':     echo json_encode(getUnread());    break;
        case 'delete':     echo json_encode(deleteMessage()); break;
        case 'members':    echo json_encode(getMembers());   break;
        default:           echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// ── Fetch messages ──────────────────────────────────────────
function getMessages(): array {
    global $conn;
    $after  = (int)($_GET['after']  ?? 0);
    $before = (int)($_GET['before'] ?? 0);
    $limit  = 50;

    if ($after > 0) {
        $stmt = $conn->prepare(
            "SELECT m.id, m.user_id, m.message, m.created_at,
                    u.username, u.full_name, u.role
             FROM group_messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.id > ?
             ORDER BY m.created_at ASC
             LIMIT 100"
        );
        $stmt->bind_param('i', $after);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        return ['success' => true, 'messages' => $messages];
    }

    if ($before > 0) {
        $stmt = $conn->prepare(
            "SELECT m.id, m.user_id, m.message, m.created_at,
                    u.username, u.full_name, u.role
             FROM group_messages m
             JOIN users u ON m.user_id = u.id
             WHERE m.id < ?
             ORDER BY m.created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('ii', $before, $limit);
        $stmt->execute();
        $messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        return ['success' => true, 'messages' => $messages];
    }

    // Initial load: last $limit messages
    $r     = $conn->query("SELECT COUNT(*) AS c FROM group_messages");
    $total = $r ? (int)$r->fetch_assoc()['c'] : 0;

    $stmt = $conn->prepare(
        "SELECT m.id, m.user_id, m.message, m.created_at,
                u.username, u.full_name, u.role
         FROM group_messages m
         JOIN users u ON m.user_id = u.id
         ORDER BY m.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $messages = array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    return ['success' => true, 'messages' => $messages, 'total' => $total, 'has_older' => $total > $limit];
}

// ── Send message ────────────────────────────────────────────
function sendMessage(): array {
    global $conn;
    $message = trim($_POST['message'] ?? '');
    $userId  = (int)$_SESSION['user_id'];

    if (!$message) return ['success' => false, 'message' => 'Message cannot be empty.'];
    if (strlen($message) > 1000) return ['success' => false, 'message' => 'Message too long (max 1000 characters).'];

    $stmt = $conn->prepare("INSERT INTO group_messages (user_id, message) VALUES (?, ?)");
    $stmt->bind_param('is', $userId, $message);
    if (!$stmt->execute()) return ['success' => false, 'message' => 'Failed to send message.'];

    $newId = $conn->insert_id;
    updateReadPosition($userId, $newId);

    return ['success' => true, 'id' => $newId];
}

// ── Mark all read ───────────────────────────────────────────
function markRead(): array {
    global $conn;
    $userId = (int)$_SESSION['user_id'];

    $r     = $conn->query("SELECT MAX(id) AS max_id FROM group_messages");
    $maxId = $r ? (int)($r->fetch_assoc()['max_id'] ?? 0) : 0;
    if ($maxId) updateReadPosition($userId, $maxId);

    return ['success' => true];
}

// ── Unread count ────────────────────────────────────────────
function getUnread(): array {
    global $conn;
    $userId = (int)$_SESSION['user_id'];
    $count  = countUnreadMessages($conn, $userId);
    return ['success' => true, 'count' => $count];
}

// ── Delete message ──────────────────────────────────────────
function deleteMessage(): array {
    global $conn;
    $userId = (int)$_SESSION['user_id'];
    $msgId  = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$msgId) return ['success' => false, 'message' => 'Invalid message.'];

    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    if ($isAdmin) {
        $stmt = $conn->prepare("DELETE FROM group_messages WHERE id = ?");
        $stmt->bind_param('i', $msgId);
    } else {
        $stmt = $conn->prepare("DELETE FROM group_messages WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $msgId, $userId);
    }
    $stmt->execute();
    if ($stmt->affected_rows === 0) return ['success' => false, 'message' => 'Not allowed.'];
    return ['success' => true];
}

// ── Members list ────────────────────────────────────────────
function getMembers(): array {
    global $conn;
    $r = $conn->query(
        "SELECT id, username, full_name, role FROM users ORDER BY role ASC, username ASC"
    );
    $members = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    return ['success' => true, 'members' => $members, 'total' => count($members)];
}

// ── Helpers ─────────────────────────────────────────────────
function updateReadPosition(int $userId, int $msgId): void {
    global $conn;
    $stmt = $conn->prepare(
        "INSERT INTO chat_reads (user_id, last_read_id) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, ?)"
    );
    $stmt->bind_param('iii', $userId, $msgId, $msgId);
    $stmt->execute();
}

function countUnreadMessages(mysqli $conn, int $userId): int {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM group_messages
         WHERE id > COALESCE((SELECT last_read_id FROM chat_reads WHERE user_id = ?), 0)
         AND user_id != ?"
    );
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    return (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}
