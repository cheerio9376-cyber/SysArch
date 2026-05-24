<?php
// api/sit_in.php — Handles student sit-in start/end

// ── 1. Buffer ALL output so stray warnings/notices never corrupt JSON ──
ob_start();

// ── 2. Suppress display of errors (log them, never print them) ──
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// ── 3. Session before any output ──
session_start();

// ── 4. Discard anything that slipped out above, then lock to JSON ──
ob_clean();
header('Content-Type: application/json; charset=utf-8');

// ── 5. Central JSON exit helper ──
function send(array $payload): void {
    ob_end_clean();
    echo json_encode($payload);
    exit;
}

// ── 6. DB ──
$config_path = dirname(__DIR__) . '/config/db.php';
if (!file_exists($config_path)) {
    $config_path = __DIR__ . '/../config/db.php';
}
require $config_path;

// ── 7. Auth ──
if (!isset($_SESSION['student_id'])) {
    send(['success' => false, 'error' => 'Not logged in']);
}

$sid = $_SESSION['student_id'];

// ── 8. Parse JSON body ──
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input) || !isset($input['action'])) {
    send(['success' => false, 'error' => 'Invalid JSON request']);
}

$action = $input['action'];

// ════════════════════════════════════════════════════════
// START SESSION
// ════════════════════════════════════════════════════════
if ($action === 'start') {
    // ── ADMIN-ONLY: Students can no longer self-start a sit-in ──
    // The admin registers sit-ins via admin/sitin.php.
    // The student dashboard will show a "locked" screen until the admin starts their session.
    send([
        'success' => false,
        'error'   => 'Sit-in sessions must be started by a lab administrator. Please approach the admin to register your sit-in.'
    ]);
}

// ── ORIGINAL start logic kept below but unreachable (renamed action guard) ──
if ($action === '__start_legacy__') {

    $purpose   = trim($input['purpose']   ?? '');
    $lab       = trim($input['lab']       ?? '');
    $pc_number = (int)($input['pc_number'] ?? 0);

    if (!$purpose)       send(['success' => false, 'error' => 'Purpose is required']);
    if (!$lab)           send(['success' => false, 'error' => 'Lab is required']);
    if ($pc_number <= 0) send(['success' => false, 'error' => 'PC number is required']);

    // Already have an active session?
    $chk = $conn->prepare("SELECT id FROM sitins WHERE student_id=? AND status='active'");
    $chk->bind_param("s", $sid);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        send(['success' => false, 'error' => 'You already have an active session']);
    }
    $chk->close();

    // Count this semester's sessions against the base limit only
    // Points/bonus sessions are admin-managed and not checked here
    $sem_start = SEM_START;
    $sem_end   = SEM_END;
    $sem_stmt  = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?"
    );
    $sem_stmt->bind_param("sss", $sid, $sem_start, $sem_end);
    $sem_stmt->execute();
    $sem_count = (int)$sem_stmt->get_result()->fetch_assoc()['cnt'];
    $sem_stmt->close();

    if ($sem_count >= SEM_LIMIT) {
        send([
            'success' => false,
            'error'   => "Session limit reached for this semester ($sem_count / " . SEM_LIMIT . ")."
        ]);
    }

    // Insert — bind order: string, string, string, int → "sssi"
    $ins = $conn->prepare(
        "INSERT INTO sitins (student_id, purpose, lab, pc_number, sit_in_time, status)
         VALUES (?, ?, ?, ?, NOW(), 'active')"
    );
    $ins->bind_param("sssi", $sid, $purpose, $lab, $pc_number);

    if ($ins->execute()) {
        $new_id = $conn->insert_id;
        $ins->close();
        send(['success' => true, 'sitin_id' => $new_id]);
    } else {
        $err = $conn->error;
        $ins->close();
        send(['success' => false, 'error' => 'Database error: ' . $err]);
    }
}

// ════════════════════════════════════════════════════════
// END SESSION
// ════════════════════════════════════════════════════════
if ($action === 'end') {

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) send(['success' => false, 'error' => 'Invalid session id']);

    // Fetch sit_in_time first so we can compute duration in PHP as a reliable fallback
    $fetch = $conn->prepare("SELECT sit_in_time FROM sitins WHERE id=? AND student_id=? AND status='active'");
    $fetch->bind_param("is", $id, $sid);
    $fetch->execute();
    $row = $fetch->get_result()->fetch_assoc();
    $fetch->close();

    if (!$row) {
        send(['success' => false, 'error' => 'Failed to end session or session not found.']);
    }

    // Compute duration: use TIMESTAMPDIFF but also calculate in PHP as a safety net.
    // GREATEST(..., 1) ensures sessions under 1 minute still show as 1m instead of 0.
    $php_duration = max(1, (int)round((time() - strtotime($row['sit_in_time'])) / 60));

    $upd = $conn->prepare(
        "UPDATE sitins
         SET sit_out_time     = NOW(),
             duration_minutes = GREATEST(TIMESTAMPDIFF(MINUTE, sit_in_time, NOW()), ?),
             status           = 'completed'
         WHERE id=? AND student_id=? AND status='active'"
    );
    $upd->bind_param("iis", $php_duration, $id, $sid);

    if ($upd->execute() && $upd->affected_rows > 0) {
        $upd->close();
        send(['success' => true]);
    } else {
        $upd->close();
        send(['success' => false, 'error' => 'Failed to end session or session not found.']);
    }
}

// ════════════════════════════════════════════════════════
// UNKNOWN ACTION
// ════════════════════════════════════════════════════════
send(['success' => false, 'error' => 'Unknown action: ' . htmlspecialchars($action)]);