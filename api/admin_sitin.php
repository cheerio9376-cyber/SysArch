<?php
// api/admin_sitin.php
session_start();
header('Content-Type: application/json');

date_default_timezone_set('Asia/Manila'); // ✅ FIX: match PHP timezone to DB

$is_admin = (
    (isset($_SESSION['admin_id']) && $_SESSION['role'] === 'admin') ||
    (isset($_SESSION['student_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
);

if (!$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$config_path = dirname(__DIR__) . '/config/db.php';
if (!file_exists($config_path)) {
    $config_path = __DIR__ . '/../config/db.php';
}
require $config_path;

$conn->query("SET time_zone = '+08:00'"); // ✅ FIX: match MySQL session timezone

// ── Robust input: accept JSON body OR POST fields ──
$data = null;
$raw  = file_get_contents('php://input');
if ($raw && trim($raw) !== '') {
    $data = json_decode($raw, true);
}
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid or empty request body.']);
    exit;
}

$action = $data['action'] ?? '';
$id     = (int)($data['id'] ?? 0);

if ($action === 'end' && $id > 0) {

    // ✅ FIX: read completion_status from payload instead of hardcoding 'completed'
    $completion_status = $data['completion_status'] ?? 'completed';
    if (!in_array($completion_status, ['completed', 'incomplete'])) {
        $completion_status = 'completed';
    }

    $award_points = isset($data['points']) ? (int)$data['points'] : 0;
    $award_reason = trim($data['reason'] ?? '');
    $admin_id     = $_SESSION['admin_id'] ?? $_SESSION['student_id'] ?? null;

    // Verify session exists and is active
    $q = $conn->prepare(
        "SELECT id, student_id, sit_in_time FROM sitins WHERE id=? AND status='active'"
    );
    $q->bind_param("i", $id);
    $q->execute();
    $sitin = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$sitin) {
        echo json_encode(['success' => false, 'error' => 'Session not found or already ended.']);
        exit;
    }

    // ✅ FIX: both time() and strtotime() are now Asia/Manila so subtraction is correct
    $duration   = max(0, (int)round((time() - strtotime($sitin['sit_in_time'])) / 60));
    $student_id = $sitin['student_id'];

    // ✅ FIX: use $completion_status variable, not hardcoded string
    $upd = $conn->prepare(
        "UPDATE sitins
         SET sit_out_time     = NOW(),
             duration_minutes = ?,
             status           = ?
         WHERE id = ?"
    );
    $upd->bind_param("isi", $duration, $completion_status, $id);

    if (!$upd->execute() || $upd->affected_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Failed to update session.']);
        $upd->close();
        exit;
    }
    $upd->close();

    // Award points if provided
    $points_awarded = 0;
    if ($award_points > 0 && $award_reason !== '' && $admin_id) {
        $ins = $conn->prepare(
            "INSERT INTO reward_points (student_id, points, reason, admin_id, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $ins->bind_param("sisi", $student_id, $award_points, $award_reason, $admin_id);
        if ($ins->execute()) {
            $points_awarded = $award_points;
        }
        $ins->close();
    }

    echo json_encode([
        'success'        => true,
        'duration'       => $duration,
        'status'         => $completion_status,
        'points_awarded' => $points_awarded,
    ]);

} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request. Expected action=end and a valid id.']);
}