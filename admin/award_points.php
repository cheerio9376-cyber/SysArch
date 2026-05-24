<?php
// admin/award_points.php — Admin awards points ONLY (3 pts = 1 session, max 30 base)
require 'includes/admin_auth.php';
$current_page = 'award_points';

$admin_id = $_SESSION['admin_id'];

// ── Handle awarding ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'award') {
    $student_id = trim($_POST['student_id'] ?? '');
    $points     = (int)($_POST['points'] ?? 0);
    $reason     = trim($_POST['reason']  ?? '');

    if (!$student_id) {
        $_SESSION['error'] = 'Student ID is required.';
    } elseif ($points < 1 || $points > 100) {
        $_SESSION['error'] = 'Points must be between 1 and 100.';
    } elseif (strlen($reason) < 5) {
        $_SESSION['error'] = 'Please provide a reason (min. 5 characters).';
    } else {
        // Verify student exists
        $chk = $conn->prepare("SELECT student_id FROM students WHERE student_id=? AND role='student'");
        $chk->bind_param("s", $student_id); $chk->execute(); $chk->store_result();
        if ($chk->num_rows === 0) {
            $_SESSION['error'] = "Student ID '$student_id' not found.";
        } else {
            $ins = $conn->prepare("INSERT INTO reward_points (student_id,points,reason,admin_id,created_at) VALUES (?,?,?,?,NOW())");
            $ins->bind_param("sisi", $student_id, $points, $reason, $admin_id);
            if ($ins->execute()) {
                $_SESSION['success'] = "Awarded $points point" . ($points>1?'s':'') . " to $student_id. Reason: $reason";
            } else {
                $_SESSION['error'] = 'Failed to award points. Please try again.';
            }
            $ins->close();
        }
        $chk->close();
    }
    header("Location: award_points.php"); exit();
}

// ── Handle removing points ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'remove') {
    $rpid = (int)($_POST['rp_id'] ?? 0);
    $del  = $conn->prepare("DELETE FROM reward_points WHERE id=?");
    $del->bind_param("i", $rpid);
    $_SESSION[$del->execute() && $del->affected_rows > 0 ? 'success' : 'error'] =
        $del->affected_rows > 0 ? 'Award record removed.' : 'Record not found.';
    $del->close();
    header("Location: award_points.php"); exit();
}

// ── Search students ───────────────────────────────────────
$search   = trim($_GET['search'] ?? '');
$students = [];
if ($search) {
    $like = "%$search%";
    $sq   = $conn->prepare("
        SELECT s.student_id, s.firstname, s.lastname, s.course_level, s.course,
               COALESCE(SUM(rp.points),0) AS total_points,
               FLOOR(COALESCE(SUM(rp.points),0)/3) AS bonus_sessions
        FROM students s
        LEFT JOIN reward_points rp ON rp.student_id = s.student_id
        WHERE s.role='student' AND (s.student_id LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)
        GROUP BY s.student_id
        ORDER BY total_points DESC
        LIMIT 30
    ");
    $sq->bind_param("sss", $like, $like, $like);
    $sq->execute();
    $students = $sq->get_result()->fetch_all(MYSQLI_ASSOC);
    $sq->close();
}

// ── Recent awards ─────────────────────────────────────────
$recent = $conn->query("
    SELECT rp.id, rp.points, rp.reason, rp.created_at,
           s.firstname, s.lastname, s.student_id,
           CONCAT(a.firstname,' ',a.lastname) as admin_name
    FROM reward_points rp
    JOIN students s ON rp.student_id = s.student_id
    LEFT JOIN admins a ON rp.admin_id = a.admin_id
    ORDER BY rp.created_at DESC
    LIMIT 15
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Points | CCS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="admin-content">

    <?php include 'includes/admin_alerts.php'; ?>

    <div class="page-header d-flex justify-content-between align-items-center">
       <div class="page-header">
             <h2>Award Reward Points</h2>
            <small class="text-muted">Admin-controlled only &bull; Rule: <strong>3 pts = 1 extra session</strong></small>
        </div>
    </div>

    <div class="row g-4">

        <!-- ── Search & Award Form ── -->
        <div class="col-lg-5">
            <div class="card-ccs p-4 mb-3">
                <h6 class="fw-bold mb-3" style="color:#5a3d82;"><i class="bi bi-search me-2"></i>Find Student</h6>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control"
                           placeholder="Name or Student ID" value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-purple px-3">Search</button>
                </form>
            </div>

            <?php if ($students): ?>
            <div class="card-ccs p-0" style="max-height:480px;overflow-y:auto;">
                <?php foreach ($students as $s): ?>
                    <div class="p-3 border-bottom student-pick-item"
                         onclick="fillStudent('<?= $s['student_id'] ?>','<?= addslashes($s['firstname'].' '.$s['lastname']) ?>')"
                         style="cursor:pointer;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-600 small"><?= htmlspecialchars($s['firstname'].' '.$s['lastname']) ?></div>
                                <div class="text-muted" style="font-size:.76rem;"><?= $s['student_id'] ?> &bull; <?= $s['course_level'] ?></div>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success"><?= $s['total_points'] ?> pts</span>
                                <div class="text-muted" style="font-size:.72rem;">+<?= $s['bonus_sessions'] ?> sessions</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($search): ?>
                <div class="card-ccs p-4 text-center text-muted"><i class="bi bi-people me-2"></i>No students found.</div>
            <?php endif; ?>
        </div>

        <!-- ── Award Panel ── -->
        <div class="col-lg-7">
            <div class="card-ccs p-4 mb-3">
                <h6 class="fw-bold mb-3" style="color:#5a3d82;">Award Points</h6>
                <div class="alert alert-info py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Rule: Every <strong>3 points = 1 extra sit-in session</strong> beyond the base limit of <?= SEM_LIMIT ?>.
                </div>
                <form method="POST" id="awardForm">
                    <input type="hidden" name="action" value="award">
                    <div class="mb-3">
                        <label class="form-label">Student ID <span class="text-danger">*</span></label>
                        <input type="text" name="student_id" id="awardStudentId" class="form-control"
                               placeholder="Click from list or type ID" required>
                        <div class="form-text" id="awardStudentName" style="color:var(--purple);font-weight:600;"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label">Points <span class="text-danger">*</span></label>
                            <input type="number" name="points" class="form-control" min="1" max="100" placeholder="1–100" required id="ptsInput" oninput="updatePreview()">
                        </div>
                        <div class="col-8">
                            <label class="form-label">Reason <span class="text-danger">*</span></label>
                            <input type="text" name="reason" class="form-control" placeholder="e.g. Silence during session" required minlength="5">
                        </div>
                    </div>
                   
                    <button type="submit" class="btn btn-gold w-100 py-2">
                        Award Points
                    </button>
                </form>
            </div>

            <!-- ── Recent Awards ── -->
            <div class="card-ccs p-0">
                <div class="p-3 border-bottom fw-600" style="color:#5a3d82;">
                Recent Awards
                </div>
                <?php if (empty($recent)): ?>
                    <div class="p-4 text-center text-muted">No awards yet.</div>
                <?php else: ?>
                    <div style="max-height:340px;overflow-y:auto;">
                    <?php foreach ($recent as $r): ?>
                        <div class="d-flex align-items-start gap-3 p-3 border-bottom">
                            <div class="flex-grow-1">
                                <div class="fw-600 small"><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?></div>
                                <div class="text-muted" style="font-size:.76rem;"><?= $r['student_id'] ?></div>
                                <div class="text-muted" style="font-size:.78rem;"><?= htmlspecialchars(substr($r['reason'],0,60)) ?><?= strlen($r['reason'])>60?'…':'' ?></div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge bg-success">+<?= $r['points'] ?> pts</span>
                                <div class="text-muted" style="font-size:.72rem;"><?= date('M d, g:i A', strtotime($r['created_at'])) ?></div>
                                <div class="text-muted" style="font-size:.72rem;">by <?= htmlspecialchars($r['admin_name'] ?? '—') ?></div>
                            </div>
                            <form method="POST" onsubmit="return confirm('Remove this award?')">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="rp_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-1"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div></div>
<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('show'));

function fillStudent(id, name) {
    document.getElementById('awardStudentId').value = id;
    document.getElementById('awardStudentName').textContent = '✓ ' + name;
    updatePreview();
}

function updatePreview() {
    const pts = parseInt(document.getElementById('ptsInput').value) || 0;
    const bonus = Math.floor(pts / 3);
    const el = document.getElementById('awardPreview');
    const txt = document.getElementById('previewText');
    if (pts > 0) {
        txt.textContent = pts + ' point' + (pts>1?'s':'') +
            (bonus > 0 ? ' → unlocks ' + bonus + ' extra session' + (bonus>1?'s':'') : ' (need ' + (3 - pts%3) + ' more for next session)');
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}
</script>
<style>
.student-pick-item:hover { background:var(--purple-soft,#f3eeff); }
.award-preview {
    background:#e8f5e9;border:1px solid #a5d6a7;border-radius:8px;
    padding:10px 14px;font-size:.875rem;color:#1b5e20;font-weight:600;
}

.admin-wrapper {
    display: flex;
    margin-top: 0 !important;
    padding-top: 0 !important;
}

.admin-content {
    margin-top: 0 !important;
    padding-top: 20px; /* small spacing only */
}

.page-header {
    margin-top: 0 !important;
}
</style>
</body>
</html>