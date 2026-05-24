<?php
session_start();
if (!isset($_SESSION['student_id']))          { header("Location: login.php"); exit(); }
if (($_SESSION['role'] ?? '') === 'admin')    { header("Location: admin/dashboard.php"); exit(); }  // ✅ FIXED: added parentheses
date_default_timezone_set('Asia/Manila');

// Your existing DB connection code...
require 'config/db.php';


$sid = $_SESSION['student_id'];

// ══════════════════════════════════════════════════════════════
// SIT-IN GATE — students can only access the dashboard if the
// admin has already started an active sit-in session for them.
// ══════════════════════════════════════════════════════════════
$_gate_q = $conn->prepare(
    "SELECT id FROM sitins WHERE student_id = ? AND status = 'active' LIMIT 1"
);
$_gate_q->bind_param("s", $sid);
$_gate_q->execute();
$_gate_active = $_gate_q->get_result()->fetch_assoc();
$_gate_q->close();

if (!$_gate_active) {
    $__gate_lim = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?"
    );
    $__gate_sem_start = SEM_START;
    $__gate_sem_end   = SEM_END;
    $__gate_lim->bind_param("sss", $sid, $__gate_sem_start, $__gate_sem_end);
    $__gate_lim->execute();
    $__gate_used = (int)$__gate_lim->get_result()->fetch_assoc()['cnt'];
    $__gate_lim->close();

    $__gate_pts = $conn->prepare(
        "SELECT COALESCE(SUM(points),0) AS pts FROM reward_points WHERE student_id=?"
    );
    $__gate_pts->bind_param("s", $sid);
    $__gate_pts->execute();
    $__gate_total_pts = (int)$__gate_pts->get_result()->fetch_assoc()['pts'];
    $__gate_pts->close();

    $__gate_bonus     = (int)floor($__gate_total_pts / 3);
    $__gate_eff_limit = SEM_LIMIT + $__gate_bonus;
    $__gate_remaining = max(0, $__gate_eff_limit - $__gate_used);
    $__gate_name      = $_SESSION['firstname'] ?? 'Student';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting for Sit-In | CCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body { background:#f4efff; min-height:100vh; display:flex; flex-direction:column; }
        .gate-wrap { flex:1; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
        .gate-card { background:#fff; border-radius:20px; box-shadow:0 8px 40px rgba(90,61,130,.13); max-width:500px; width:100%; padding:2.5rem 2rem 2rem; text-align:center; }
        .gate-icon { width:88px; height:88px; border-radius:50%; background:linear-gradient(135deg,#5a3d82,#9c6dd8); display:flex; align-items:center; justify-content:center; margin:0 auto 1.4rem; animation:glow 2.5s ease-in-out infinite; }
        @keyframes glow { 0%,100%{box-shadow:0 0 0 0 rgba(90,61,130,.4)} 50%{box-shadow:0 0 0 16px rgba(90,61,130,0)} }
        .stat-pill { display:inline-flex; align-items:center; gap:.4rem; background:#f4efff; color:#5a3d82; border:1.5px solid #d6c9f0; border-radius:50px; padding:.3rem .9rem; font-size:.8rem; font-weight:600; }
        .step-row { display:flex; align-items:flex-start; gap:.85rem; background:#fafafa; border-radius:10px; padding:.7rem 1rem; margin-bottom:.5rem; text-align:left; }
        .step-num { flex-shrink:0; width:26px; height:26px; border-radius:50%; background:#5a3d82; color:#fff; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:700; }
        .progress-bar-anim { height:4px; background:#e9ecef; border-radius:4px; overflow:hidden; margin-top:1.1rem; }
        .progress-bar-fill { height:100%; background:linear-gradient(90deg,#5a3d82,#9c6dd8); border-radius:4px; animation:shrink 30s linear infinite; }
        @keyframes shrink { from{width:100%} to{width:0%} }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#5a3d82;">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="images/CCSLogo2.png" style="height:32px;" class="me-2">
            <span class="fw-bold">College of Computer Studies</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white small opacity-75"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($__gate_name) ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-light ms-2"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>
<div class="gate-wrap">
    <div class="gate-card">
        <div class="gate-icon"><i class="bi bi-person-workspace text-white" style="font-size:2.2rem;"></i></div>
        <h4 class="fw-bold mb-1" style="color:#5a3d82;">No Active Sit-In Session</h4>
        <p class="text-muted mb-3" style="font-size:.88rem;">
            Hi <strong><?= htmlspecialchars($__gate_name) ?></strong>!
            Your dashboard is locked until a lab administrator registers your sit-in.
        </p>
        <div class="d-flex justify-content-center gap-2 flex-wrap mb-4">
            <span class="stat-pill"><i class="bi bi-calendar-check"></i> Used: <strong><?= $__gate_used ?> / <?= $__gate_eff_limit ?></strong></span>
            <span class="stat-pill"><i class="bi bi-hourglass-split"></i> Remaining: <strong><?= $__gate_remaining ?></strong></span>
        </div>
        <div class="mb-4 text-start">
            <p class="fw-semibold mb-2" style="color:#333;font-size:.85rem;">What to do:</p>
            <div class="step-row"><div class="step-num">1</div><div class="small text-muted">Go to the <strong>lab administrator</strong> at the front desk.</div></div>
            <div class="step-row"><div class="step-num">2</div><div class="small text-muted">Give them your <strong>Student ID (<?= htmlspecialchars($sid) ?>)</strong>, your purpose, and your PC number.</div></div>
            <div class="step-row"><div class="step-num">3</div><div class="small text-muted">Once the admin registers you, this page <strong>unlocks automatically</strong>.</div></div>
        </div>
        <button onclick="location.reload()" class="btn w-100 py-2 fw-bold" style="background:#5a3d82;color:#fff;border-radius:10px;border:none;">
            <i class="bi bi-arrow-clockwise me-2"></i>Check Now
        </button>
        <div class="progress-bar-anim"><div class="progress-bar-fill"></div></div>
        <p class="text-muted mt-1 mb-0" style="font-size:.72rem;">Auto-checking every 30 seconds&hellip;</p>
    </div>
</div>
<footer style="text-align:center;padding:1rem;font-size:.8rem;color:#999;">&copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(() => location.reload(), 30000);</script>
</body>
</html>
<?php
    exit();
}
// ── Gate passed: active sit-in exists, dashboard loads normally ──
if (!isset($_SESSION['ann_last_seen'])) $_SESSION['ann_last_seen'] = 0;

// ── Student ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$firstname    = $student['firstname'];
$lastname     = $student['lastname'];
$course_level = $student['course_level'];
$course       = $student['course'];
$email        = $student['email'];
$profile_pic  = $student['profile_pic'];

// ── Active sit-in ────────────────────────────────────────
$a = $conn->prepare("SELECT id, sit_in_time, purpose, lab, pc_number FROM sitins WHERE student_id=? AND status='active' LIMIT 1");
$a->bind_param("s", $sid);
$a->execute();
$active_sitin = $a->get_result()->fetch_assoc();
$a->close();

$elapsed_min = 0;
if ($active_sitin) {
    $elapsed_min = max(0, floor((time() - strtotime($active_sitin['sit_in_time'])) / 60));
}

// ── Semester stats ───────────────────────────────────────
$sem_start = SEM_START;
$sem_end   = SEM_END;
$s2 = $conn->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(duration_minutes),0) as total_min FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?");
$s2->bind_param("sss", $sid, $sem_start, $sem_end);
$s2->execute();
$sem = $s2->get_result()->fetch_assoc();
$s2->close();

// ── Today ────────────────────────────────────────────────
$t = $conn->prepare("SELECT COUNT(*) as cnt FROM sitins WHERE student_id=? AND DATE(sit_in_time)=CURDATE()");
$t->bind_param("s", $sid);
$t->execute();
$today_count = (int)$t->get_result()->fetch_assoc()['cnt'];
$t->close();

// ── All-time ─────────────────────────────────────────────
$tt = $conn->prepare("SELECT COUNT(*) as cnt FROM sitins WHERE student_id=?");
$tt->bind_param("s", $sid);
$tt->execute();
$total_count = (int)$tt->get_result()->fetch_assoc()['cnt'];
$tt->close();

// ── Recent history (5) ───────────────────────────────────
$h = $conn->prepare("SELECT id,purpose,lab,pc_number,sit_in_time,sit_out_time,duration_minutes,status FROM sitins WHERE student_id=? ORDER BY sit_in_time DESC LIMIT 5");
$h->bind_param("s", $sid);
$h->execute();
$history = $h->get_result()->fetch_all(MYSQLI_ASSOC);
$h->close();

// ── Announcements ────────────────────────────────────────
$ann_res      = $conn->query("SELECT title,content,created_at FROM announcements ORDER BY created_at DESC");
$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);
$new_count = 0;
foreach ($announcements as $ann) {
    if (strtotime($ann['created_at']) > $_SESSION['ann_last_seen']) $new_count++;
}

// ── Rewards points ───────────────────────────────────────
$rp = $conn->prepare("SELECT COALESCE(SUM(points),0) as pts FROM reward_points WHERE student_id=?");
$rp->bind_param("s", $sid);
$rp->execute();
$points = (int)$rp->get_result()->fetch_assoc()['pts'];
$rp->close();

$sem_count       = (int)$sem['cnt'];
$sem_hours       = floor((int)$sem['total_min'] / 60);
$bonus_sessions  = (int)floor($points / 3);
$effective_limit = SEM_LIMIT + $bonus_sessions;
$remaining       = max(0, $effective_limit - $sem_count);
$sem_progress    = min(100, ($sem_count / $effective_limit) * 100);

$active_page = 'dashboard';
include 'includes/layout.php';
?>


<!-- ── Page Header ──────────────────────────────────── -->
<div class="page-header">
    <h2>Dashboard</h2>
    <p>Welcome back, <?= htmlspecialchars($firstname) ?>! Here's your sit-in overview.</p>
</div>

<!-- ── Hero Welcome Card ────────────────────────────── -->
<div class="ccs-card hero-card mb-4">
    <div class="row align-items-center g-3">
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-3 mb-3">
                <img src="<?= $profile_pic ? 'uploads/'.$profile_pic : 'https://ui-avatars.com/api/?name='.urlencode($firstname.'+'.$lastname).'&background=5a3d82&color=fff&size=80' ?>"
                     class="hero-avatar" alt="">
                <div>
                    <h3 class="mb-0" style="font-size:1.25rem;font-weight:700;color:var(--purple);">
                        Good <?= (int)date('H') < 12 ? 'morning' : ((int)date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars($firstname) ?>!
                    </h3>
                    <p class="mb-0 text-muted small"><?= $course_level ?> · <?= $course ?> &bull; <span class="badge bg-purple-soft text-purple px-2"><?= $sid ?></span></p>
                    <p class="mb-0 mt-1 small text-muted"><?= date('l, F d, Y') ?></p>
                </div>
            </div>

            <?php if ($remaining <= 5 && $remaining > 0): ?>
                <div class="alert alert-warning py-2 small mb-0">
                    Only <strong><?= $remaining ?></strong> session(s) remaining this semester!
                </div>
            <?php elseif ($remaining == 0): ?>
                <div class="alert alert-danger py-2 small mb-0">
                    Session limit reached for this semester.
                </div>
            <?php endif; ?>
        </div>


    </div>
</div>

<!-- ── Stats Row ─────────────────────────────────────── -->
<div class="rw-summary-grid mb-4">

    <div class="rw-card rw-card--points">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $sem_count ?></div>
            <div class="rw-card__label">Semester Sessions</div>
            <div class="rw-card__sub">out of <?= SEM_LIMIT + $bonus_sessions ?> limit</div>
        </div>
    </div>

    <div class="rw-card rw-card--remaining">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $remaining ?></div>
            <div class="rw-card__label">Sessions Remaining</div>
            <div class="rw-card__sub"><?= SEM_LIMIT ?> base + <?= $bonus_sessions ?> bonus</div>
        </div>
    </div>

    <div class="rw-card rw-card--limit">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $sem_hours ?>h</div>
            <div class="rw-card__label">Total Study Time</div>
            <div class="rw-card__sub">this semester</div>
        </div>
    </div>

    <div class="rw-card rw-card--bonus">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $bonus_sessions ?></div>
            <div class="rw-card__label">Bonus Sessions</div>
            <div class="rw-card__sub">from <?= $points ?> pts</div>
        </div>
    </div>

</div>

<!-- ── History + Announcements ───────────────────────── -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="ccs-card">
            <div class="ccs-card-title">
                Recent Sessions
                <a href="history.php" class="ms-auto btn btn-sm btn-outline-secondary" style="font-size:0.78rem;">View All</a>
            </div>
            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <p class="text-muted">No sessions yet. Start your first sit-in!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="ccs-table w-100">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Lab</th>
                                <th>PC #</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $r): ?>
                            <tr>
                                <td><?= date('M d, g:i A', strtotime($r['sit_in_time'])) ?></td>
                                <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($r['lab'] ?? '—') ?></td>
                                <td><?= !empty($r['pc_number']) ? 'PC '.htmlspecialchars($r['pc_number']) : '—' ?></td>
                                <td>
                                    <?php if ($r['duration_minutes'] !== null):
                                        $h = floor($r['duration_minutes'] / 60);
                                        $m = $r['duration_minutes'] % 60;
                                        echo ($h > 0 ? $h.'h ' : '') . $m.'m';
                                    elseif ($r['status'] === 'active'):
                                        echo '<span class="text-warning fw-bold">Active</span>';
                                    else: echo '—';
                                    endif; ?>
                                </td>
                                <td><span class="status-badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Announcements -->
    <div class="col-lg-4">
        <div class="ccs-card h-100">
            <div class="ccs-card-title">
                Announcements
                <?php if ($new_count > 0): ?>
                    <span class="ms-auto badge bg-danger"><?= $new_count ?> new</span>
                <?php endif; ?>
            </div>
            <?php if (empty($announcements)): ?>
                <p class="text-muted small">No announcements yet.</p>
            <?php else: ?>
                <?php foreach (array_slice($announcements, 0, 4) as $ann): ?>
                    <div class="ann-item mb-2">
                        <div class="ann-title"><?= htmlspecialchars($ann['title']) ?></div>
                        <div class="ann-body"><?= htmlspecialchars($ann['content']) ?></div>
                        <div class="ann-date"><?= date('M d, Y', strtotime($ann['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Start Sit-In Modal REMOVED ──────────────────────
     Sessions are now started by the admin only (admin/sitin.php).
     The gate at the top of this file ensures students only reach
     this dashboard when they already have an active session.
     ────────────────────────────────────────────────────────── -->

<style>
* { font-family: 'Poppins', sans-serif; }
.hero-card { overflow: hidden; }
.hero-avatar { width:64px;height:64px;border-radius:50%;object-fit:cover;border:3px solid var(--purple-soft); }
.bg-purple-soft { background:var(--purple-soft); }
.text-purple { color:var(--purple) !important; }
.active-session-panel { background:#fffbf0;border:1.5px solid #f6d365;border-radius:14px;padding:18px;text-align:center; }
.live-dot { display:inline-block;width:8px;height:8px;border-radius:50%;background:#d4a017;animation:pulse 1.4s infinite; }
@keyframes pulse { 0%,100%{box-shadow:0 0 0 0 rgba(212,160,23,.5)} 50%{box-shadow:0 0 0 8px rgba(212,160,23,0)} }
.timer-display { font-size:2.2rem;font-weight:700;color:var(--gold);font-variant-numeric:tabular-nums;line-height:1;margin-bottom:4px; }
.btn-end-session { background:linear-gradient(135deg,#c0392b,#e74c3c);border:none;border-radius:12px;padding:12px;color:#fff;font-weight:700;font-size:0.9rem;cursor:pointer;transition:opacity .2s;width:100%; }
.btn-end-session:hover { opacity:0.88; }
.start-session-panel { background:linear-gradient(135deg,var(--purple),var(--purple-light));border-radius:14px;padding:22px;text-align:center; }
.btn-start-session { background:linear-gradient(135deg,var(--gold),#f6d365);border:none;border-radius:12px;padding:13px 28px;font-size:0.95rem;font-weight:700;color:#fff;box-shadow:0 6px 20px rgba(212,160,23,.45);cursor:pointer;transition:all .3s;width:100%; }
.btn-start-session:hover:not(:disabled) { transform:translateY(-2px);box-shadow:0 10px 28px rgba(212,160,23,.55); }
.stat-tile { background:#fff;border-radius:16px;border:1px solid rgba(90,61,130,0.08);box-shadow:0 4px 14px rgba(90,61,130,0.07);padding:18px;transition:transform .2s; }
.stat-tile:hover { transform:translateY(-4px); }
.stat-icon-wrap { font-size:1.6rem;margin-bottom:6px; }
.stat-val { font-size:1.9rem;font-weight:700;line-height:1; }
.stat-lbl { font-size:0.8rem;color:var(--text-muted);margin-top:4px; }
.mini-progress { height:5px;border-radius:3px;background:#eee; }
.mini-progress-fill { height:100%;border-radius:3px; }
.mini-progress-fill.ok { background:#27ae60; }
.mini-progress-fill.warn { background:var(--gold); }
.mini-progress-fill.danger { background:#e74c3c; }
/* ── Rewards-style summary cards ── */
.rw-summary-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:16px; }
@media (max-width:900px) { .rw-summary-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:480px) { .rw-summary-grid { grid-template-columns:1fr 1fr; } }
.rw-card { background:#fff;border-radius:14px;padding:20px 18px;display:flex;align-items:center;gap:14px;box-shadow:0 2px 12px rgba(90,61,130,.07);border:1px solid rgba(90,61,130,.06);transition:transform .15s,box-shadow .15s; }
.rw-card:hover { transform:translateY(-2px);box-shadow:0 6px 20px rgba(90,61,130,.13); }
.rw-card__body { flex:1;min-width:0; }
.rw-card__number { font-size:1.75rem;font-weight:700;line-height:1.1; }
.rw-card--points   .rw-card__number { color:#5a3d82; }
.rw-card--remaining .rw-card__number { color:#d4a017; }
.rw-card--limit    .rw-card__number { color:#1976d2; }
.rw-card--bonus    .rw-card__number { color:#27ae60; }
.rw-card__label { font-size:.78rem;color:#888;font-weight:500;margin-top:2px; }
.rw-card__sub   { font-size:.72rem;color:#aaa;margin-top:2px; }
.ccs-table { border-collapse:separate;border-spacing:0; }
.ccs-table thead th { background:var(--purple);color:#fff;font-size:0.8rem;font-weight:600;padding:10px 12px;border:none; }
.ccs-table thead th:first-child { border-radius:10px 0 0 0; }
.ccs-table thead th:last-child  { border-radius:0 10px 0 0; }
.ccs-table tbody td { padding:9px 12px;border-bottom:1px solid #f0e9ff;font-size:0.875rem;vertical-align:middle; }
.ccs-table tbody tr:hover td { background:var(--purple-soft); }
.ccs-table tbody tr:last-child td { border-bottom:none; }
.status-badge { padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600; }
.status-badge.active    { background:#d4edda;color:#155724; }
.status-badge.completed { background:#e2e3e5;color:#383d41; }
.ann-item { border-left:3px solid var(--gold);background:var(--purple-soft);border-radius:0 10px 10px 0;padding:10px 12px; }
.ann-title { font-size:0.85rem;font-weight:600;color:var(--purple);margin-bottom:3px; }
.ann-body  { font-size:0.8rem;color:#555;margin-bottom:4px; }
.ann-date  { font-size:0.73rem;color:var(--text-muted); }
.empty-state { text-align:center;padding:40px 20px; }
</style>

<?php
$extra_js = '
<script>
' . ($active_sitin ? '
// ── Persistent timer using sessionStorage ──────────────
// Key includes the sitin ID so a new session always resets to 0.
const TIMER_KEY = "sitin_timer_' . $active_sitin["id"] . '";

// Reuse stored start time if same session, otherwise record now (first visit).
if (!sessionStorage.getItem(TIMER_KEY)) {
    sessionStorage.setItem(TIMER_KEY, Date.now().toString());
}
const timerStartMs = parseInt(sessionStorage.getItem(TIMER_KEY), 10);

function updateTimer() {
    const el = document.getElementById("elapsedTimer");
    if (!el) return;
    const totalSeconds = Math.floor((Date.now() - timerStartMs) / 1000);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    el.textContent =
        String(h).padStart(2,"0") + ":" +
        String(m).padStart(2,"0") + ":" +
        String(s).padStart(2,"0");
}

updateTimer();
setInterval(updateTimer, 1000);
' : '') . '
// confirmStartSitIn() removed — sessions are now admin-started only.
function endSitIn(id) {
    if (!confirm("End your current sit-in session?")) return;
    fetch("api/sit_in.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "end", id })
    })
    .then(r => r.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (data.success) {
                // Clear the stored timer so next session starts from 00:00:00
                sessionStorage.removeItem("sitin_timer_" + id);
                location.reload();
            } else { alert(data.error || "Failed to end session."); }
        } catch(e) {
            alert("Server returned invalid response:\n\n" + text.substring(0, 500));
        }
    })
    .catch(e => alert("Network error: " + e.message));
}
    
</script>';

include 'includes/layout_footer.php';
?>