<?php
ob_start();                        // buffer any stray PHP output
ini_set('display_errors', 0);     // never mix error HTML into JSON

session_start();
require_once '../config.php';
require_once '../functions.php';
requireAdmin();

header('Content-Type: application/json');
ob_clean();                        // discard anything output during includes

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':          echo json_encode(listUsers());        break;
        case 'get':           echo json_encode(getUser());          break;
        case 'create':        echo json_encode(createUser());       break;
        case 'update':        echo json_encode(updateUser());       break;
        case 'delete':        echo json_encode(deleteUser());       break;
        case 'toggle_status': echo json_encode(toggleStatus());     break;
        case 'bulk':          echo json_encode(bulkAction());       break;
        default:              echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ── List ────────────────────────────────────────────────────
function listUsers() {
    global $conn;
    return ['success' => true, 'users' => getAllUsers($conn)];
}

// ── Get single ──────────────────────────────────────────────
function getUser() {
    global $conn;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) return ['success' => false, 'message' => 'Missing user ID'];
    $user = getUserById($conn, $id);
    if (!$user) return ['success' => false, 'message' => 'User not found'];
    unset($user['password'], $user['security_answer']); // never expose hashes
    return ['success' => true, 'user' => $user];
}

// ── Create ──────────────────────────────────────────────────
function createUser() {
    global $conn;
    $full_name  = trim($_POST['full_name']        ?? '');
    $username   = trim($_POST['username']         ?? '');
    $email      = trim($_POST['email']            ?? '');
    $password   = $_POST['password']              ?? '';
    $contact    = trim($_POST['contact_number']   ?? '');
    $hive_id    = (int)($_POST['assigned_hive_id'] ?? 0) ?: null;
    $status     = in_array($_POST['account_status'] ?? '', ['active','inactive'])
                  ? $_POST['account_status'] : 'active';
    $sec_q      = trim($_POST['security_question'] ?? '');
    $sec_a      = trim($_POST['security_answer']   ?? '');

    if (!$username || !$email || !$password) {
        return ['success' => false, 'message' => 'Username, email, and password are required.'];
    }
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }
    if (!$sec_q || !$sec_a) {
        return ['success' => false, 'message' => 'Security question and answer are required.'];
    }

    // Unique check
    $chk = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $chk->bind_param("ss", $username, $email);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists.'];
    }

    $hash    = password_hash($password, PASSWORD_DEFAULT);
    $ansHash = password_hash(strtolower($sec_a), PASSWORD_DEFAULT);
    $role    = 'beekeeper';

    $stmt = $conn->prepare(
        "INSERT INTO users
         (full_name, username, email, password, contact_number, assigned_hive_id, account_status, role, security_question, security_answer)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "sssssissss",
        $full_name, $username, $email, $hash, $contact, $hive_id, $status, $role, $sec_q, $ansHash
    );

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Database error: ' . $stmt->error];
    }

    logActivity($conn, null, 'user_created',
        "New beekeeper account created: $username", 'info');

    return ['success' => true, 'message' => 'User account created successfully.', 'id' => $conn->insert_id];
}

// ── Update ──────────────────────────────────────────────────
function updateUser() {
    global $conn;
    $id        = (int)($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name']         ?? '');
    $username  = trim($_POST['username']          ?? '');
    $email     = trim($_POST['email']             ?? '');
    $password  = $_POST['password']               ?? '';
    $contact   = trim($_POST['contact_number']    ?? '');
    $hive_id   = (int)($_POST['assigned_hive_id'] ?? 0) ?: null;
    $status    = in_array($_POST['account_status'] ?? '', ['active','inactive'])
                 ? $_POST['account_status'] : 'active';
    $sec_q     = trim($_POST['security_question'] ?? '');
    $sec_a     = trim($_POST['security_answer']   ?? '');

    if (!$id) return ['success' => false, 'message' => 'Missing user ID.'];
    if (!$username || !$email) return ['success' => false, 'message' => 'Username and email are required.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['success' => false, 'message' => 'Invalid email address.'];

    // Unique check (exclude self)
    $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
    $chk->bind_param("ssi", $username, $email, $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already in use by another account.'];
    }

    // Build SET clauses and params list — keep them in sync, add $id last
    $setClauses = ['full_name=?','username=?','email=?','contact_number=?','assigned_hive_id=?','account_status=?'];
    $types      = 'ssssss';
    $params     = [$full_name, $username, $email, $contact, $hive_id, $status];

    if ($password) {
        if (strlen($password) < 6) return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        $setClauses[] = 'password=?';
        $types       .= 's';
        $params[]     = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($sec_q) {
        $setClauses[] = 'security_question=?';
        $types       .= 's';
        $params[]     = $sec_q;
    }
    if ($sec_a) {
        $setClauses[] = 'security_answer=?';
        $types       .= 's';
        $params[]     = password_hash(strtolower($sec_a), PASSWORD_DEFAULT);
    }

    // WHERE id=? always last
    $types   .= 'i';
    $params[] = $id;

    $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $setClauses) . " WHERE id=?");
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Database error: ' . $stmt->error];
    }

    logActivity($conn, null, 'user_updated', "User account updated: $username", 'info');
    return ['success' => true, 'message' => 'User account updated successfully.'];
}

// ── Toggle status ───────────────────────────────────────────
function toggleStatus() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'message' => 'Missing user ID.'];

    $row = $conn->query("SELECT account_status, role, username FROM users WHERE id = $id")->fetch_assoc();
    if (!$row) return ['success' => false, 'message' => 'User not found.'];
    if ($row['role'] === 'admin') return ['success' => false, 'message' => 'Cannot change admin status.'];

    $newStatus = $row['account_status'] === 'active' ? 'inactive' : 'active';
    $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ?");
    $stmt->bind_param('si', $newStatus, $id);
    $stmt->execute();

    logActivity($conn, null, 'user_status_changed',
        "User {$row['username']} status changed to $newStatus", 'info');

    return ['success' => true, 'new_status' => $newStatus,
            'message' => "Status changed to $newStatus."];
}

// ── Bulk action ──────────────────────────────────────────────
function bulkAction() {
    global $conn;
    $type = $_POST['type'] ?? '';
    $ids  = json_decode($_POST['ids'] ?? '[]', true);
    if (!is_array($ids) || !$ids) return ['success' => false, 'message' => 'No users selected.'];

    $safeIds = array_map('intval', $ids);
    $selfId  = (int)$_SESSION['user_id'];
    $safeIds = array_filter($safeIds, fn($id) => $id !== $selfId);
    if (!$safeIds) return ['success' => false, 'message' => 'Cannot apply bulk action to your own account.'];

    $in = implode(',', $safeIds);

    if ($type === 'activate') {
        $conn->query("UPDATE users SET account_status='active' WHERE id IN ($in) AND role != 'admin'");
        $count = $conn->affected_rows;
        logActivity($conn, null, 'bulk_activate', "Admin bulk activated $count user accounts", 'info');
        return ['success' => true, 'message' => 'Selected users activated.'];
    }
    if ($type === 'deactivate') {
        $conn->query("UPDATE users SET account_status='inactive' WHERE id IN ($in) AND role != 'admin'");
        $count = $conn->affected_rows;
        logActivity($conn, null, 'bulk_deactivate', "Admin bulk deactivated $count user accounts", 'warning');
        return ['success' => true, 'message' => 'Selected users deactivated.'];
    }
    if ($type === 'delete') {
        $conn->query("DELETE FROM users WHERE id IN ($in) AND role != 'admin'");
        $count = $conn->affected_rows;
        logActivity($conn, null, 'bulk_delete', "Bulk deleted $count user accounts", 'warning');
        return ['success' => true, 'message' => "$count user(s) deleted."];
    }

    return ['success' => false, 'message' => 'Unknown bulk action.'];
}

// ── Delete ──────────────────────────────────────────────────
function deleteUser() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'message' => 'Missing user ID.'];

    if ($id === (int)$_SESSION['user_id']) {
        return ['success' => false, 'message' => 'You cannot delete your own account.'];
    }

    $row = $conn->query("SELECT username, role FROM users WHERE id = $id")->fetch_assoc();
    if (!$row) return ['success' => false, 'message' => 'User not found.'];
    if ($row['role'] === 'admin') {
        return ['success' => false, 'message' => 'Admin accounts cannot be deleted here.'];
    }

    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) {
        return ['success' => false, 'message' => 'Could not delete user.'];
    }

    $uname = $row['username'];
    logActivity($conn, null, 'user_deleted',
        "Beekeeper account deleted: $uname", 'warning');

    return ['success' => true, 'message' => 'User account deleted.'];
}

