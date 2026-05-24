<?php
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: index.php"); exit(); }
require 'config/db.php';

$sid = $_SESSION['student_id'];

// ── Handle Feedback POST ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback_submit'])) {
    $sitin_id = (int)($_POST['sitin_id'] ?? 0);
    $category = trim($_POST['category'] ?? 'General');
    $message  = trim($_POST['message'] ?? '');

    if (strlen($message) < 5) {
        $_SESSION['error'] = 'Please write a feedback message (min. 5 characters).';
    } else {
        // Verify this sitin belongs to this student and is completed
        $chk = $conn->prepare("SELECT id FROM sitins WHERE id=? AND student_id=? AND status='completed'");
        $chk->bind_param("is", $sitin_id, $sid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $ins = $conn->prepare("INSERT INTO feedback (student_id, sitin_id, rating, category, message, created_at) VALUES (?, ?, NULL, ?, ?, NOW())");
            $ins->bind_param("siss", $sid, $sitin_id, $category, $message);
            if ($ins->execute()) {
                $_SESSION['success'] = 'Thank you for your feedback!';
            } else {
                $_SESSION['error'] = 'Could not submit feedback. Please try again.';
            }
            $ins->close();
        } else {
            $_SESSION['error'] = 'Invalid session for feedback.';
        }
        $chk->close();
    }
    header("Location: history.php?" . http_build_query(array_diff_key($_GET, ['page'=>''])));
    exit();
}
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$firstname   = $student['firstname'];
$lastname    = $student['lastname'];
$course_level= $student['course_level'];
$course      = $student['course'];
$profile_pic = $student['profile_pic'];

// ── Filters ──────────────────────────────────────────────
$filter_purpose = trim($_GET['purpose'] ?? '');
$filter_lab     = trim($_GET['lab'] ?? '');
$filter_status  = trim($_GET['status'] ?? '');
$filter_from    = trim($_GET['from'] ?? '');
$filter_to      = trim($_GET['to'] ?? '');

// ── Pagination ───────────────────────────────────────────
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── Build query ──────────────────────────────────────────
$where = ["student_id = ?"];
$params = [$sid];
$types  = "s";

if ($filter_purpose) { $where[] = "purpose = ?";   $params[] = $filter_purpose; $types .= "s"; }
if ($filter_lab)     { $where[] = "lab = ?";        $params[] = $filter_lab;     $types .= "s"; }
if ($filter_status)  { $where[] = "status = ?";     $params[] = $filter_status;  $types .= "s"; }
if ($filter_from)    { $where[] = "DATE(sit_in_time) >= ?"; $params[] = $filter_from; $types .= "s"; }
if ($filter_to)      { $where[] = "DATE(sit_in_time) <= ?"; $params[] = $filter_to;   $types .= "s"; }

$whereSQL = implode(" AND ", $where);

// Total count
$cnt_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM sitins WHERE $whereSQL");
$cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total_rows = (int)$cnt_stmt->get_result()->fetch_assoc()['cnt'];
$cnt_stmt->close();

$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Rows
$data_stmt = $conn->prepare("SELECT id,purpose,lab,pc_number,sit_in_time,sit_out_time,duration_minutes,status FROM sitins WHERE $whereSQL ORDER BY sit_in_time DESC LIMIT ? OFFSET ?");
$params[] = $per_page; $types .= "i";
$params[] = $offset;   $types .= "i";
$data_stmt->bind_param($types, ...$params);
$data_stmt->execute();
$rows = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$data_stmt->close();

// ── Already-given feedback IDs ───────────────────────────
$fb_res = $conn->prepare("SELECT sitin_id FROM feedback WHERE student_id=?");
$fb_res->bind_param("s", $sid);
$fb_res->execute();
$fb_rows = $fb_res->get_result()->fetch_all(MYSQLI_ASSOC);
$fb_res->close();
$already_rated = array_column($fb_rows, 'sitin_id');

// ── Announcements (for bell)
$ann_res       = $conn->query("SELECT title,content,created_at FROM announcements ORDER BY created_at DESC");
$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);
$new_count     = 0;
foreach ($announcements as $a) {
    if (strtotime($a['created_at']) > ($_SESSION['ann_last_seen'] ?? 0)) $new_count++;
}

$active_page = 'history';
include 'includes/layout.php';
?>

<div class="page-header">
    <h2>Sit-In History</h2>
    <p>Complete record of all your sit-in sessions.</p>
</div>


<!-- ── Filters ───────────────────────────────────────── -->
<div class="ccs-card mb-3">
    <form method="GET" action="history.php" class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-600">Purpose</label>
            <select name="purpose" class="form-select form-select-sm">
                <option value="">All Purposes</option>
                <?php foreach(['C Programming','Java Programming','Web Development','Database','Research / Thesis','Online Class','Assignment','Other'] as $p): ?>
                    <option <?= $filter_purpose === $p ? 'selected' : '' ?>><?= $p ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-600">Lab</label>
            <select name="lab" class="form-select form-select-sm">
                <option value="">All Labs</option>
                <?php foreach(['Lab 524','Lab 526','Lab 528','Lab 530','Mac Lab'] as $l): ?>
                    <option <?= $filter_lab === $l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-600">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="active"    <?= $filter_status === 'active'    ? 'selected' : '' ?>>Active</option>
                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </div>
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-600">From</label>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_from) ?>">
        </div>
        <div class="col-sm-6 col-md-2">
            <label class="form-label small fw-600">To</label>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($filter_to) ?>">
        </div>
        <div class="col-sm-6 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-gold btn-sm flex-grow-1">Filter</button>
            <a href="history.php" class="btn btn-outline-secondary btn-sm"></a>
        </div>
    </form>
</div>

<!-- ── Table ─────────────────────────────────────────── -->
<div class="ccs-card">
    <div class="ccs-card-title">
        Sessions
        <span class="ms-auto small text-muted fw-normal">
            Showing <?= min($offset + 1, $total_rows) ?>–<?= min($offset + $per_page, $total_rows) ?> of <?= $total_rows ?>
        </span>
    </div>

    <?php if (empty($rows)): ?>
        <div class="text-center py-5">
            <p class="text-muted">No sessions match your filters.</p>
            <a href="history.php" class="btn btn-gold btn-sm">Clear Filters</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="ccs-table w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date & Time In</th>
                        <th>Time Out</th>
                        <th>Purpose</th>
                        <th>Lab</th>
                        <th>PC Number</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Feedback</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
    <td class="text-muted small"><?= $offset + $i + 1 ?></td>

    <td><?= date('M d, Y g:i A', strtotime($r['sit_in_time'])) ?></td>

    <td><?= $r['sit_out_time'] ? date('g:i A', strtotime($r['sit_out_time'])) : '—' ?></td>

    <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>

    <td><?= htmlspecialchars($r['lab'] ?? '—') ?></td>

    <!-- ✅ PC NUMBER -->
    <td>PC <?= htmlspecialchars($r['pc_number'] ?? '—') ?></td>

    <!-- ✅ DURATION -->
    <td>
        <?php if ($r['duration_minutes'] !== null):
            $h = floor($r['duration_minutes'] / 60);
            $m = $r['duration_minutes'] % 60;
            echo ($h > 0 ? $h.'h ' : '') . $m.'m';
        elseif ($r['status'] === 'active'):
            echo '<span class="text-warning fw-600">Active</span>';
        else:
            echo '—';
        endif; ?>
    </td>

    <!-- ✅ STATUS -->
    <td>
        <span class="status-badge <?= $r['status'] ?>">
            <?= ucfirst($r['status']) ?>
        </span>
    </td>

    <!-- ✅ FEEDBACK -->
    <td>
        <?php if ($r['status'] === 'completed'): ?>
            <?php if (in_array($r['id'], $already_rated)): ?>
                <span class="fb-given-badge">Given</span>
            <?php else: ?>
                <button class="btn btn-feedback btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#feedbackModal"
                        data-sitin="<?= $r['id'] ?>"
                        data-label="<?= htmlspecialchars(date('M d, Y', strtotime($r['sit_in_time'])) . ' — ' . ($r['purpose'] ?? '') . ($r['lab'] ? ' (' . $r['lab'] . ')' : ''), ENT_QUOTES) ?>">
                    Give Feedback
                </button>
            <?php endif; ?>
        <?php else: ?>
            <span class="text-muted small">—</span>
        <?php endif; ?>
    </td>
</tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-3 d-flex justify-content-center">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">← Prev</a>
                </li>
                <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next →</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* ── Rewards-style summary cards ── */
.rw-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
@media (max-width: 900px) { .rw-summary-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .rw-summary-grid { grid-template-columns: 1fr 1fr; } }

.rw-card {
    background: #fff;
    border-radius: 14px;
    padding: 20px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 12px rgba(90,61,130,.07);
    border: 1px solid rgba(90,61,130,.06);
    transition: transform .15s, box-shadow .15s;
}
.rw-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(90,61,130,.13); }
.rw-card__icon {
    width: 48px; height: 48px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; flex-shrink: 0;
}
.rw-card--points   .rw-card__icon { background: #f3eeff; color: #5a3d82; }
.rw-card--remaining .rw-card__icon { background: #fff8e1; color: #d4a017; }
.rw-card--limit    .rw-card__icon { background: #e3f2fd; color: #1976d2; }
.rw-card--bonus    .rw-card__icon { background: #e8f5e9; color: #27ae60; }
.rw-card__number { font-size: 1.75rem; font-weight: 700; line-height: 1.1; }
.rw-card--points   .rw-card__number { color: #5a3d82; }
.rw-card--remaining .rw-card__number { color: #d4a017; }
.rw-card--limit    .rw-card__number { color: #1976d2; }
.rw-card--bonus    .rw-card__number { color: #27ae60; }
.rw-card__label { font-size: .78rem; color: #888; font-weight: 500; margin-top: 2px; }
.rw-card__sub   { font-size: .72rem; color: #aaa; margin-top: 2px; }

.ccs-table { border-collapse:separate;border-spacing:0; }
.ccs-table thead th { background:var(--purple);color:#fff;font-size:0.8rem;font-weight:600;padding:10px 12px; }
.ccs-table thead th:first-child { border-radius:10px 0 0 0; }
.ccs-table thead th:last-child  { border-radius:0 10px 0 0; }
.ccs-table tbody td { padding:9px 12px;border-bottom:1px solid #f0e9ff;font-size:0.875rem;vertical-align:middle; }
.ccs-table tbody tr:hover td { background:var(--purple-soft); }
.ccs-table tbody tr:last-child td { border-bottom:none; }
.status-badge { padding:4px 10px;border-radius:20px;font-size:0.75rem;font-weight:600; }
.status-badge.active    { background:#d4edda;color:#155724; }
.status-badge.completed { background:#e2e3e5;color:#383d41; }
.page-item.active .page-link { background:var(--purple);border-color:var(--purple); }
.page-link { color:var(--purple); }
</style>

<!-- ── Feedback Modal ─────────────────────────────────── -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="feedbackModalLabel">
          Give Feedback
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2">
        <p class="text-muted small mb-3" id="feedbackSessionLabel"></p>
        <form method="POST" action="history.php?<?= htmlspecialchars(http_build_query($_GET)) ?>">
          <input type="hidden" name="feedback_submit" value="1">
          <input type="hidden" name="sitin_id" id="feedbackSitinId" value="">

          <div class="mb-3">
            <label class="form-label fw-600">Category</label>
            <select name="category" class="form-select">
              <?php foreach(['General','Facilities','Equipment','Internet / Network','Staff & Service','Cleanliness'] as $cat): ?>
                <option><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-600">Your Feedback <span class="text-danger">*</span></label>
            <textarea name="message" class="form-control" rows="5" minlength="5" required
              placeholder="Share your experience, suggestions, or concerns..."></textarea>
            <div class="form-text">Minimum 5 characters.</div>
          </div>

          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gold px-4">
              Submit
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Populate modal with the sitin id + label when triggered
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('feedbackModal');
    modal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('feedbackSitinId').value   = btn.dataset.sitin;
        document.getElementById('feedbackSessionLabel').textContent = 'Session: ' + btn.dataset.label;
    });
    // Reset textarea on close
    modal.addEventListener('hidden.bs.modal', function () {
        modal.querySelector('textarea').value = '';
    });
});
</script>

<style>
.btn-feedback {
    background: var(--purple-soft, #f4efff);
    color: var(--purple, #5a3d82);
    border: 1px solid rgba(90,61,130,.2);
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 12px;
    white-space: nowrap;
    transition: background .15s, color .15s;
}
.btn-feedback:hover {
    background: var(--purple, #5a3d82);
    color: #fff;
}
.fb-given-badge {
    display: inline-flex;
    align-items: center;
    background: #e8f5e9;
    color: #27ae60;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
}
</style>

<?php include 'includes/layout_footer.php'; ?>