<?php
// reservation.php — Student PC/Lab Reservation with Cancel Feature
ob_start();
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: index.php"); exit(); }
require 'config/db.php';

$sid = $_SESSION['student_id'];

// ── Student info ─────────────────────────────────────────
$stmt = $conn->prepare("SELECT firstname,lastname,course_level,course,email,profile_pic FROM students WHERE student_id=?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$firstname    = $student['firstname']    ?? 'Student';
$lastname     = $student['lastname']     ?? '';
$course_level = $student['course_level'] ?? '';
$course       = $student['course']       ?? '';
$profile_pic  = $student['profile_pic']  ?? '';

// ── Handle CANCEL reservation ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $res_id = (int)($_POST['reservation_id'] ?? 0);

    if ($res_id <= 0) {
        $_SESSION['error'] = "Invalid reservation ID.";
    } else {
        $upd = $conn->prepare("
            UPDATE pc_reservations 
            SET status='cancelled' 
            WHERE id=? AND student_id=? AND status IN ('pending','approved')
        ");
        $upd->bind_param("is", $res_id, $sid);

        if ($upd->execute() && $upd->affected_rows > 0) {
            $_SESSION['success'] = "Reservation cancelled successfully.";
        } else {
            $_SESSION['error'] = "Cannot cancel this reservation.";
        }

        $upd->close();
    }

    header("Location: reservation.php");
    exit();
}

// ── Handle NEW reservation ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reserve') {
    $lab      = trim($_POST['lab']      ?? '');
    $pc_num   = (int)($_POST['pc_number'] ?? 0);
    $res_date = trim($_POST['reservation_date'] ?? '');
    $start    = trim($_POST['start_time'] ?? '');
    $end      = trim($_POST['end_time']   ?? '');
    $purpose  = trim($_POST['purpose']   ?? '');

    $errors = [];
    if (!$lab)      $errors[] = "Lab is required.";
    if (!$pc_num)   $errors[] = "PC number is required.";
    if (!$res_date) $errors[] = "Date is required.";
    if (!$start || !$end) $errors[] = "Time slot is required.";
    if (!$purpose)  $errors[] = "Purpose is required.";
    if ($res_date < date('Y-m-d')) $errors[] = "Cannot reserve for past dates.";
    if ($start >= $end) $errors[] = "End time must be after start time.";

    if (empty($errors)) {
        // Check if PC is already taken for this slot
        $conflict = $conn->prepare("
            SELECT id FROM pc_reservations
            WHERE lab=? AND pc_number=? AND reservation_date=? AND status IN ('pending','approved')
              AND NOT (end_time <= ? OR start_time >= ?)
        ");
        $conflict->bind_param("sisss", $lab, $pc_num, $res_date, $start, $end);
        $conflict->execute();
        $conflict->store_result();

        if ($conflict->num_rows > 0) {
            $_SESSION['error'] = "PC $pc_num in $lab is already reserved for that time slot. Please choose another.";
        } else {
            $ins = $conn->prepare("
                INSERT INTO pc_reservations (student_id, lab, pc_number, reservation_date, start_time, end_time, purpose, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $ins->bind_param("ssissss", $sid, $lab, $pc_num, $res_date, $start, $end, $purpose);
            if ($ins->execute()) {
                $_SESSION['success'] = "Reservation submitted! Waiting for admin approval.";
            } else {
                $_SESSION['error'] = "Failed to submit reservation. Please try again.";
            }
            $ins->close();
        }
        $conflict->close();
    } else {
        $_SESSION['error'] = implode(" ", $errors);
    }
    header("Location: reservation.php");
    exit();
}

// ── Handle START SIT-IN from approved reservation ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_sitin') {
    $res_id = (int)($_POST['reservation_id'] ?? 0);
    if ($res_id > 0) {
        $res_q = $conn->prepare("SELECT * FROM pc_reservations WHERE id=? AND student_id=? AND status='approved'");
        $res_q->bind_param("is", $res_id, $sid);
        $res_q->execute();
        $res = $res_q->get_result()->fetch_assoc();
        $res_q->close();

        if ($res) {
            $act = $conn->prepare("SELECT id FROM sitins WHERE student_id=? AND status='active'");
            $act->bind_param("s", $sid);
            $act->execute();
            $act->store_result();

            if ($act->num_rows > 0) {
                $_SESSION['error'] = "You already have an active sit-in session.";
            } else {
                $sem_start_val = SEM_START;
                $sem_end_val   = SEM_END;
                $lim = $conn->prepare("SELECT COUNT(*) FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?");
                $lim->bind_param("sss", $sid, $sem_start_val, $sem_end_val);
                $lim->execute();
                $count = $lim->get_result()->fetch_row()[0];
                $lim->close();

                $pts = $conn->prepare("SELECT COALESCE(SUM(points),0) as pts FROM reward_points WHERE student_id=?");
                $pts->bind_param("s", $sid);
                $pts->execute();
                $total_pts = (int)$pts->get_result()->fetch_assoc()['pts'];
                $pts->close();
                $bonus = floor($total_pts / 3);
                $effective_limit = SEM_LIMIT + $bonus;

                if ($count >= $effective_limit) {
                    $_SESSION['error'] = "You have reached your semester sit-in limit ($effective_limit sessions).";
                } else {
                    $conn->begin_transaction();
                    try {
                        $ins = $conn->prepare("INSERT INTO sitins (student_id, purpose, lab, pc_number, sit_in_time, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
                        $ins->bind_param("sssi", $sid, $res['purpose'], $res['lab'], $res['pc_number']);
                        $ins->execute();

                        $upd = $conn->prepare("UPDATE pc_reservations SET status='in_use' WHERE id=?");
                        $upd->bind_param("i", $res_id);
                        $upd->execute();

                        $conn->commit();
                        $_SESSION['success'] = "Sit-in session started! Welcome to " . $res['lab'] . ", PC " . $res['pc_number'] . ".";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = "Failed to start sit-in. Please try again.";
                    }
                }
            }
            $act->close();
        } else {
            $_SESSION['error'] = "Reservation not found or not approved.";
        }
    }
    header("Location: reservation.php");
    exit();
}

// ── Fetch student's reservations ─────────────────────────
// Only fetch reservations belonging to the current session's account.
// - Active/upcoming statuses: always show (pending, approved, in_use)
// - Completed/closed statuses (cancelled, rejected, expired/done): only show
//   if created within the current semester so stale history from a re-used
//   student_id or a freshly-registered account doesn't bleed through.
$my_res = $conn->prepare("
    SELECT * FROM pc_reservations
    WHERE student_id = ?
      AND (
            status IN ('pending', 'approved', 'in_use')
          OR (
                status NOT IN ('pending', 'approved', 'in_use')
            AND created_at >= ?
          )
      )
    ORDER BY created_at DESC
    LIMIT 50
");
// Use SEM_START constant (defined in config/db.php) as the cutoff so that
// only history from the current semester is shown for closed statuses.
// Falls back to 30 days ago if the constant isn't defined yet.
$history_cutoff = defined('SEM_START') ? SEM_START : date('Y-m-d', strtotime('-30 days'));
$my_res->bind_param("ss", $sid, $history_cutoff);
$my_res->execute();
$my_reservations = $my_res->get_result()->fetch_all(MYSQLI_ASSOC);
$my_res->close();

// ── Fetch available PCs for selected date/lab ──
$filter_lab  = trim($_GET['filter_lab']  ?? 'Lab 524');
$filter_date = trim($_GET['filter_date'] ?? date('Y-m-d'));

$booked_q = $conn->prepare("
    SELECT pc_number, start_time, end_time, status
    FROM pc_reservations
    WHERE lab=? AND reservation_date=? AND status IN ('pending','approved','in_use')
");
$booked_q->bind_param("ss", $filter_lab, $filter_date);
$booked_q->execute();
$booked_pcs = [];
foreach ($booked_q->get_result()->fetch_all(MYSQLI_ASSOC) as $b) {
    $booked_pcs[$b['pc_number']] = ['status' => $b['status'], 'label' => ucfirst($b['status'])];
}
$booked_q->close();

$active_q2 = $conn->prepare("
    SELECT pc_number FROM sitins
    WHERE lab=? AND DATE(sit_in_time)=? AND status='active'
");
$active_q2->bind_param("ss", $filter_lab, $filter_date);
$active_q2->execute();
foreach ($active_q2->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
    if (!isset($booked_pcs[$a['pc_number']])) {
        $booked_pcs[$a['pc_number']] = ['status' => 'in_use', 'label' => 'In Use'];
    }
}
$active_q2->close();

// ── Announcements ─────────────────────────────────────────
$ann_res = $conn->query("SELECT title,content,created_at FROM announcements ORDER BY created_at DESC");
$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);
$new_count = 0;
foreach ($announcements as $a) {
    if (strtotime($a['created_at']) > ($_SESSION['ann_last_seen'] ?? 0)) $new_count++;
}

// Check active sit-in
$active_q = $conn->prepare("SELECT id FROM sitins WHERE student_id=? AND status='active' LIMIT 1");
$active_q->bind_param("s", $sid);
$active_q->execute();
$active_sitin = $active_q->get_result()->fetch_assoc();
$active_q->close();

// ── Remaining sessions ─────────────────────────────────────
$sem_start = SEM_START;
$sem_end   = SEM_END;
$sem_q = $conn->prepare("SELECT COUNT(*) as cnt FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?");
$sem_q->bind_param("sss", $sid, $sem_start, $sem_end);
$sem_q->execute();
$sem_count = (int)$sem_q->get_result()->fetch_assoc()['cnt'];
$sem_q->close();

$pts_q = $conn->prepare("SELECT COALESCE(SUM(points),0) as pts FROM reward_points WHERE student_id=?");
$pts_q->bind_param("s", $sid);
$pts_q->execute();
$row = $pts_q->get_result()->fetch_assoc();
$total_pts = (int)$row['pts'];
$pts_q->close();

$bonus_sessions = floor($total_pts / 3);
$remaining = max(0, SEM_LIMIT + $bonus_sessions - $sem_count);

$active_page = 'reservation';
include 'includes/layout.php';
?>

<!-- ── Page Content ── -->
<div class="page-header">
    <h2>PC Reservation</h2>
    <p class="text-muted mb-0">Reserve a PC in advance. Admin approval required before you can start your session.</p>
</div>

<div class="row g-4">

    <!-- ── NEW RESERVATION FORM ── -->
    <div class="col-lg-5">
        <div class="ccs-card mb-4">
            <div class="ccs-card-title">New Reservation</div>

            <form method="POST" id="reservationForm">
                <input type="hidden" name="action" value="reserve">

                <div class="mb-3">
                    <label class="form-label fw-600">Laboratory</label>
                    <select name="lab" id="labSelect" class="form-select" required onchange="updatePCGrid()">
                        <option value="">Select Laboratory...</option>
                        <?php foreach(['Lab 524','Lab 526','Lab 528','Lab 530','Mac Lab'] as $l): ?>
                            <option value="<?= $l ?>" <?= ($filter_lab===$l)?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600">Reservation Date</label>
                    <input type="date" name="reservation_date" id="dateInput" class="form-control"
                           min="<?= date('Y-m-d') ?>" value="<?= $filter_date ?>" required onchange="updatePCGrid()">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-600">Start Time</label>
                        <input type="time" name="start_time" class="form-control" min="07:00" max="20:00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-600">End Time</label>
                        <input type="time" name="end_time" class="form-control" min="07:00" max="21:00" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600">Purpose</label>
                    <select name="purpose" class="form-select" required>
                        <option value="">Select purpose...</option>
                        <option>C Programming</option>
                        <option>Java Programming</option>
                        <option>Web Development</option>
                        <option>Database</option>
                        <option>Capstone</option>
                        <option>Online Class</option>
                        <option>Assignment</option>
                        <option>Other</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-600">PC Number</label>
                    <input type="number" name="pc_number" id="pcNumberInput" class="form-control"
                           min="1" max="50" placeholder="Enter PC # (1–50)" required>
                    <div class="form-text">Or click a PC from the grid below</div>
                </div>

                <button type="submit" class="btn btn-purple w-100 py-2">
                    Submit Reservation
                </button>
            </form>
        </div>

        <!-- ── PC Availability Grid ── -->
        <div class="ccs-card">
            <div class="ccs-card-title">PC Availability Map</div>
            <div class="d-flex gap-2 mb-3 flex-wrap">
                <form method="GET" class="d-flex gap-2 flex-wrap" id="gridFilterForm">
                    <select name="filter_lab" id="gridLabSelect" class="form-select form-select-sm" style="width:auto;" onchange="syncGridLab()">
                        <?php foreach(['Lab 524','Lab 526','Lab 528','Lab 530','Mac Lab'] as $l): ?>
                            <option value="<?= $l ?>" <?= $filter_lab===$l?'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="filter_date" class="form-control form-control-sm" style="width:auto;"
                           value="<?= $filter_date ?>" min="<?= date('Y-m-d') ?>" onchange="document.getElementById('gridFilterForm').submit()">
                </form>
            </div>
            <div class="pc-grid-student">
                <?php for ($pc = 1; $pc <= 50; $pc++):
                    $b = $booked_pcs[$pc] ?? null;
                    $status = $b['status'] ?? null;
                    if (!$b) {
                        $cls = 'pc-free'; $lbl = 'Free';
                    } elseif ($status === 'in_use') {
                        $cls = 'pc-inuse'; $lbl = 'In Use';
                    } elseif ($status === 'approved') {
                        $cls = 'pc-approved'; $lbl = 'Approved';
                    } else {
                        $cls = 'pc-booked'; $lbl = 'Pending';
                    }
                ?>
                <div class="pc-btn <?= $cls ?>"
                     title="PC <?= $pc ?> — <?= $lbl ?>"
                     <?= !$b ? "onclick=\"document.getElementById('pcNumberInput').value=$pc;document.getElementById('labSelect').value='$filter_lab';\"" : '' ?>>
                    <span class="pc-num"><?= $pc ?></span>
                    <span style="font-size:.6rem;display:block;line-height:1;"><?= $lbl ?></span>
                </div>
                <?php endfor; ?>
            </div>
            <div class="mt-2 small text-muted d-flex gap-3 flex-wrap">
                <span><span class="pc-leg pc-free"></span> Free (<?= 50 - count($booked_pcs) ?>)</span>
                <span><span class="pc-leg pc-booked"></span> Pending</span>
                <span><span class="pc-leg pc-approved"></span> Approved</span>
                <span><span class="pc-leg pc-inuse"></span> In Use (<?= count(array_filter($booked_pcs, fn($b) => $b['status'] === 'in_use')) ?>)</span>
            </div>
        </div>
    </div>

    <!-- ── MY RESERVATIONS LIST ── -->
    <div class="col-lg-7">
        <div class="ccs-card">
            <div class="ccs-card-title">
                My Reservations
                <span class="badge bg-secondary ms-auto"><?= count($my_reservations) ?></span>
            </div>

            <?php if (empty($my_reservations)): ?>
                <div class="text-center py-5">
                    <p class="text-muted">No reservations yet. Create one!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
                        <thead class="table-light">
                            <tr>
                                <th>PC / Lab</th>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                       <?php foreach ($my_reservations as $r):

    // ✅ ADD THIS LINE (normalize status)
    $status = strtolower(trim($r['status']));

    // your existing map
    $status_map = [
        'pending'   => ['warning',  'bi-clock',        'Pending'],
        'approved'  => ['success',  'bi-check-circle', 'Approved'],
        'rejected'  => ['danger',   'bi-x-circle',     'Rejected'],
        'cancelled' => ['secondary','bi-slash-circle', 'Cancelled'],
        'expired'   => ['secondary','bi-hourglass',    'Expired'],
        'in_use'    => ['info',     'bi-laptop',       'In Use'],
    ];

    // ✅ CHANGE THIS LINE (use $status instead of $r['status'])
    [$bc, $icon, $label] = $status_map[$status] ?? ['secondary','bi-question','Unknown'];
?>
                        <tr>
                            <td>
                                <strong>PC <?= $r['pc_number'] ?></strong>
                                <div class="text-muted small"><?= htmlspecialchars($r['lab']) ?></div>
                            </td>
                            <td>
                                <div><?= date('M d, Y', strtotime($r['reservation_date'])) ?></div>
                                <small class="text-muted"><?= date('g:i A', strtotime($r['start_time'])) ?>–<?= date('g:i A', strtotime($r['end_time'])) ?></small>
                            </td>
                            <td><small><?= htmlspecialchars($r['purpose']) ?></small></td>
                            <td>
                                <span class="badge bg-<?= $bc ?>">
                                    <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
                                </span>
                                <!-- Show decline reason if rejected -->
                                <?php if ($r['status'] === 'rejected' && !empty($r['decline_reason'])): ?>
                                    <div class="mt-1">
                                        <small class="text-danger" title="<?= htmlspecialchars($r['decline_reason']) ?>">
                                            <em><?= htmlspecialchars(mb_strimwidth($r['decline_reason'], 0, 40, '…')) ?></em>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                   <?php if ($status === 'approved'): ?>
                                        <!-- START SIT-IN BUTTON -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="start_sitin">
                                            <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2"
                                                    onclick="return confirm('Start your sit-in session for PC <?= $r['pc_number'] ?> in <?= htmlspecialchars($r['lab']) ?>?')"
                                                    title="Start Sit-In">
                                                Start
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                   <?php if (in_array($status, ['pending', 'approved'])): ?>
                                        <!-- CANCEL BUTTON -->
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2"
                                                    onclick="return confirm('Cancel reservation for PC <?= $r['pc_number'] ?> on <?= date("M d", strtotime($r["reservation_date"])) ?>? This cannot be undone.')"
                                                    title="Cancel Reservation">
                                                Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($r['status'] === 'rejected' && !empty($r['decline_reason'])): ?>
                                        <!-- VIEW REASON BUTTON -->
                                        <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                                data-bs-toggle="modal" data-bs-target="#reasonModal"
                                                data-reason="<?= htmlspecialchars($r['decline_reason']) ?>"
                                                data-res="<?= $r['id'] ?>"
                                                title="View Decline Reason">
                                            Reason
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Status Legend ── -->
        <div class="ccs-card mt-3">
            <div class="ccs-card-title">Reservation Status Guide</div>
            <div class="row g-2">
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fff8e1;">
                        <span class="badge bg-warning">Pending</span>
                        <small>Waiting for admin approval</small>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#e8f5e9;">
                        <span class="badge bg-success">Approved</span>
                        <small>You can start your session</small>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#fce4ec;">
                        <span class="badge bg-danger">Rejected</span>
                        <small>Admin declined (see reason)</small>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#ede7f6;">
                        <span class="badge bg-secondary">Cancelled</span>
                        <small>You cancelled it</small>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#e3f2fd;">
                        <span class="badge bg-info">In Use</span>
                        <small>Currently in session</small>
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f5f5f5;">
                        <span class="badge bg-secondary">Expired</span>
                        <small>Time slot has passed</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Decline Reason Modal (view only) ── -->
<div class="modal fade" id="reasonModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">
                    Reservation #<span id="modalResId"></span> — Declined
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-1 small">Reason from admin:</p>
                <div class="alert alert-danger mb-0" id="modalReasonText"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.pc-grid-student { display:grid;grid-template-columns:repeat(auto-fill,minmax(52px,1fr));gap:5px; }
.pc-btn { border-radius:6px;padding:5px 3px;text-align:center;font-size:.7rem;border:2px solid transparent;transition:all .15s; }
.pc-free     { background:#e8f5e9;color:#1b5e20;border-color:#a5d6a7;cursor:pointer; }
.pc-free:hover { background:#c8e6c9;transform:scale(1.05); }
.pc-booked   { background:#fff8e1;color:#b8860b;border-color:#ffe082;cursor:not-allowed; }
.pc-approved { background:#e8f5e9;color:#1b5e20;border-color:#66bb6a;cursor:not-allowed; }
.pc-inuse    { background:#ffebee;color:#b71c1c;border-color:#ef9a9a;cursor:not-allowed; }
.pc-num { display:block;font-size:.8rem;font-weight:700; }
.pc-leg { display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:4px;vertical-align:middle; }
.pc-leg.pc-free     { background:#a5d6a7; }
.pc-leg.pc-booked   { background:#ffe082; }
.pc-leg.pc-approved { background:#66bb6a; }
.pc-leg.pc-inuse    { background:#ef9a9a; }
</style>

<script>
// Sync form lab select → grid lab select and reload grid
function updatePCGrid() {
    const lab  = document.getElementById('labSelect').value;
    const date = document.getElementById('dateInput').value;
    const gridLab = document.getElementById('gridLabSelect');
    if (gridLab && lab) gridLab.value = lab;
    if (lab && date) {
        const url = new URL(window.location.href);
        url.searchParams.set('filter_lab', lab);
        url.searchParams.set('filter_date', date);
        window.location.href = url.toString();
    }
}

// Sync grid lab select → form lab select
function syncGridLab() {
    const gridLab = document.getElementById('gridLabSelect').value;
    const formLab = document.getElementById('labSelect');
    if (formLab && gridLab) formLab.value = gridLab;
    document.getElementById('gridFilterForm').submit();
}

// On page load, sync form lab to match grid (from URL params)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const labParam = urlParams.get('filter_lab');
    if (labParam) {
        const formLab = document.getElementById('labSelect');
        if (formLab) formLab.value = labParam;
    }

    // Populate reason modal
    const reasonModal = document.getElementById('reasonModal');
    if (reasonModal) {
        reasonModal.addEventListener('show.bs.modal', function(e) {
            const btn = e.relatedTarget;
            document.getElementById('modalResId').textContent    = btn.dataset.res;
            document.getElementById('modalReasonText').textContent = btn.dataset.reason;
        });
    }
});
</script>

<?php include 'includes/layout_footer.php'; ?>