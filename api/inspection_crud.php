<?php
ob_start();                        // buffer any stray PHP output
ini_set('display_errors', 0);     // never mix error HTML into JSON

session_start();
require_once '../config.php';
require_once '../functions.php';
checkAuth();

header('Content-Type: application/json');
ob_clean();                        // discard anything output during includes
ini_set('display_errors', 0);     // config.php re-enables this; turn it off again
error_reporting(0);

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':   echo json_encode(listNotes());   break;
        case 'get':    echo json_encode(getNote());     break;
        case 'create': echo json_encode(createNote());  break;
        case 'update': echo json_encode(updateNote());  break;
        case 'delete': echo json_encode(deleteNote());  break;
        default:       echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

// ── List ────────────────────────────────────────────────────
function listNotes() {
    global $conn;
    $hive_id = (int)($_GET['hive_id'] ?? 0) ?: null;
    $notes = getInspectionNotes($conn, $hive_id, null);
    return ['success' => true, 'notes' => $notes];
}

// ── Get single ──────────────────────────────────────────────
function getNote() {
    global $conn;
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) return ['success' => false, 'message' => 'Missing ID'];

    $stmt = $conn->prepare(
        "SELECT n.*, h.name AS hive_name, h.hive_id AS hive_code,
                u.username AS inspector_username, u.full_name AS inspector_name,
                u.role AS inspector_role
         FROM inspection_notes n
         JOIN hives h ON n.hive_id = h.id
         JOIN users u ON n.user_id = u.id
         WHERE n.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    if (!$note) return ['success' => false, 'message' => 'Note not found'];
    return ['success' => true, 'note' => $note];
}

// ── Create ──────────────────────────────────────────────────
function createNote() {
    global $conn;
    $hive_id         = (int)($_POST['hive_id'] ?? 0);
    $insp_date       = $_POST['inspection_date']  ?? date('Y-m-d');
    $condition       = $_POST['hive_condition']   ?? 'good';
    $strength        = $_POST['colony_strength']  ?? 'moderate';
    $observations    = trim($_POST['observations']    ?? '');
    $recommendations = trim($_POST['recommendations'] ?? '');
    $notes_txt       = trim($_POST['notes']           ?? '');
    $user_id         = (int)$_SESSION['user_id'];

    if (!$hive_id)    return ['success' => false, 'message' => 'Hive is required.'];
    if (!$insp_date)  return ['success' => false, 'message' => 'Inspection date is required.'];

    $valid_cond = ['excellent','good','fair','poor','critical'];
    $valid_str  = ['strong','moderate','weak','queenless'];
    if (!in_array($condition, $valid_cond)) $condition = 'good';
    if (!in_array($strength,  $valid_str))  $strength  = 'moderate';

    $stmt = $conn->prepare(
        "INSERT INTO inspection_notes
         (hive_id, user_id, inspection_date, hive_condition, colony_strength,
          observations, recommendations, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        "iissssss",
        $hive_id, $user_id, $insp_date, $condition, $strength,
        $observations, $recommendations, $notes_txt
    );

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Database error: ' . $stmt->error];
    }

    $newId = $conn->insert_id;
    $uname = $_SESSION['username'] ?? 'user';
    logActivity($conn, $hive_id, 'inspection_created',
        "Inspection note added by $uname for hive #$hive_id", 'info');

    return ['success' => true, 'message' => 'Inspection note saved.', 'id' => $newId];
}

// ── Update ──────────────────────────────────────────────────
function updateNote() {
    global $conn;
    $id              = (int)($_POST['id'] ?? 0);
    $hive_id         = (int)($_POST['hive_id'] ?? 0);
    $insp_date       = $_POST['inspection_date']  ?? date('Y-m-d');
    $condition       = $_POST['hive_condition']   ?? 'good';
    $strength        = $_POST['colony_strength']  ?? 'moderate';
    $observations    = trim($_POST['observations']    ?? '');
    $recommendations = trim($_POST['recommendations'] ?? '');
    $notes_txt       = trim($_POST['notes']           ?? '');

    if (!$id)      return ['success' => false, 'message' => 'Missing note ID.'];
    if (!$hive_id) return ['success' => false, 'message' => 'Hive is required.'];

    $owner = $conn->query("SELECT user_id FROM inspection_notes WHERE id = $id")->fetch_assoc();
    if (!$owner) return ['success' => false, 'message' => 'Note not found.'];
    if (!isAdmin() && (int)$owner['user_id'] !== (int)$_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Access denied.'];
    }

    $valid_cond = ['excellent','good','fair','poor','critical'];
    $valid_str  = ['strong','moderate','weak','queenless'];
    if (!in_array($condition, $valid_cond)) $condition = 'good';
    if (!in_array($strength,  $valid_str))  $strength  = 'moderate';

    $stmt = $conn->prepare(
        "UPDATE inspection_notes
         SET hive_id=?, inspection_date=?, hive_condition=?, colony_strength=?,
             observations=?, recommendations=?, notes=?
         WHERE id=?"
    );
    $stmt->bind_param(
        "issssssi",
        $hive_id, $insp_date, $condition, $strength,
        $observations, $recommendations, $notes_txt, $id
    );

    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Database error: ' . $stmt->error];
    }

    $uname = $_SESSION['username'] ?? 'user';
    logActivity($conn, $hive_id, 'Inspection Updated',
        "User '$uname' updated inspection note #$id for hive #$hive_id", 'info');

    return ['success' => true, 'message' => 'Inspection note updated.'];
}

// ── Delete ──────────────────────────────────────────────────
function deleteNote() {
    global $conn;
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return ['success' => false, 'message' => 'Missing note ID.'];

    $owner = $conn->query("SELECT user_id FROM inspection_notes WHERE id = $id")->fetch_assoc();
    if (!$owner) return ['success' => false, 'message' => 'Note not found.'];
    if (!isAdmin() && (int)$owner['user_id'] !== (int)$_SESSION['user_id']) {
        return ['success' => false, 'message' => 'Access denied.'];
    }

    $stmt = $conn->prepare("DELETE FROM inspection_notes WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        return ['success' => false, 'message' => 'Could not delete note.'];
    }

    $uname = $_SESSION['username'] ?? 'user';
    logActivity($conn, null, 'Inspection Deleted',
        "User '$uname' deleted inspection note #$id", 'warning');

    return ['success' => true, 'message' => 'Inspection note deleted.'];
}

