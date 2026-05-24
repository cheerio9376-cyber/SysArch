<?php
require 'includes/admin_auth.php';
$current_page = 'records';

$admin_id = $_SESSION['admin_id'];

// ── Handle awarding points inline from Records ──────────────
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
        $chk = $conn->prepare("SELECT student_id FROM students WHERE student_id=? AND role='student'");
        $chk->bind_param("s", $student_id); $chk->execute(); $chk->store_result();
        if ($chk->num_rows === 0) {
            $_SESSION['error'] = "Student ID '$student_id' not found.";
        } else {
            $ins = $conn->prepare("INSERT INTO reward_points (student_id,points,reason,admin_id,created_at) VALUES (?,?,?,?,NOW())");
            $ins->bind_param("sisi", $student_id, $points, $reason, $admin_id);
            if ($ins->execute()) {
                $_SESSION['success'] = "Awarded $points point" . ($points>1?'s':'') . " to $student_id.";
            } else {
                $_SESSION['error'] = 'Failed to award points.';
            }
            $ins->close();
        }
        $chk->close();
    }

    // Return to same filtered page
    $qs = http_build_query([
        'search' => $_POST['_search'] ?? '',
        'status' => $_POST['_status'] ?? '',
        'date'   => $_POST['_date']   ?? '',
        'page'   => $_POST['_page']   ?? 1,
    ]);
    header("Location: records.php?$qs"); exit();
}

$search   = trim($_GET['search'] ?? '');
$status   = $_GET['status'] ?? '';
$date     = $_GET['date'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 20;
$offset   = ($page - 1) * $limit;

// Build WHERE
$where = []; $params = []; $types = '';
if ($search) {
    $like = "%$search%";
    $where[] = "(s.student_id LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ?)";
    $params = array_merge($params, [$like,$like,$like]);
    $types .= 'sss';
}
if ($status) { $where[] = "si.status=?"; $params[] = $status; $types .= 's'; }
if ($date)   { $where[] = "DATE(si.sit_in_time)=?"; $params[] = $date; $types .= 's'; }
$whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

// Count
$cnt_stmt = $conn->prepare("SELECT COUNT(*) FROM sitins si JOIN students s ON s.student_id=si.student_id $whereSQL");
if ($params) { $cnt_stmt->bind_param($types, ...$params); }
$cnt_stmt->execute();
$total = $cnt_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total / $limit);

// Fetch records
$all_params  = array_merge($params, [$limit, $offset]);
$all_types   = $types . 'ii';
$stmt = $conn->prepare("
    SELECT si.id, si.student_id, s.firstname, s.lastname, s.course_level, s.course,
           si.purpose, si.lab, si.pc_number,
           si.sit_in_time, si.sit_out_time, si.duration_minutes, si.status
    FROM sitins si
    JOIN students s ON s.student_id = si.student_id
    $whereSQL
    ORDER BY si.sit_in_time DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Records | CCS Admin</title>
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

<div class="page-header">
    <h2>Sit-In Records</h2>
    <small class="text-muted"><?= $total ?> total records</small>
</div>

<!-- FILTERS -->
<div class="card-ccs p-3 mb-3">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" name="search" class="form-control"
                   placeholder="Search student name or ID..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="active"     <?= $status=='active'     ? 'selected':'' ?>>Active</option>
            <option value="completed"  <?= $status=='completed'  ? 'selected':'' ?>>Completed</option>
            <option value="incomplete" <?= $status=='incomplete' ? 'selected':'' ?>>Incomplete</option>
        </select>
    </div>
    <div class="col-md-3">
        <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-purple flex-grow-1">
            <i class="bi bi-funnel me-1"></i>Filter
        </button>
        <a href="records.php" class="btn btn-outline-secondary">Clear</a>
    </div>
</form>
</div>

<!-- TABLE -->
<div class="card-ccs p-0 overflow-hidden">
<div class="table-responsive">
<table class="table-ccs w-100">
<thead>
<tr>
    <th>#</th>
    <th>Student</th>
    <th>Course</th>
    <th>Purpose</th>
    <th>Lab</th>
    <th>PC</th>
    <th>Time In</th>
    <th>Time Out</th>
    <th>Duration</th>
    <th>Status</th>
    <th>Points</th>
</tr>
</thead>
<tbody>
<?php if (empty($records)): ?>
<tr>
    <td colspan="11" class="text-center text-muted py-4">No records found.</td>
</tr>
<?php else: ?>
<?php foreach ($records as $i => $r): ?>
<tr>
    <td class="text-muted small"><?= $offset + $i + 1 ?></td>

    <td>
        <div class="fw-600 small"><?= htmlspecialchars($r['lastname'].', '.$r['firstname']) ?></div>
        <div class="text-muted" style="font-size:0.76rem;"><?= $r['student_id'] ?></div>
    </td>

    <td><small><?= $r['course_level'] ?> <?= $r['course'] ?></small></td>
    <td><small><?= htmlspecialchars($r['purpose'] ?? '—') ?></small></td>
    <td><small><?= htmlspecialchars($r['lab'] ?? '—') ?></small></td>
    <td><small>PC <?= htmlspecialchars($r['pc_number'] ?? '—') ?></small></td>

    <td><small><?= date('M d, Y g:i A', strtotime($r['sit_in_time'])) ?></small></td>

    <td>
        <small>
        <?= $r['sit_out_time']
            ? date('M d, Y g:i A', strtotime($r['sit_out_time']))
            : '—' ?>
        </small>
    </td>

    <td>
        <?php if ($r['duration_minutes'] !== null): ?>
            <small>
                <?= $r['duration_minutes'] >= 60
                    ? floor($r['duration_minutes']/60).'h '.($r['duration_minutes']%60).'m'
                    : $r['duration_minutes'].'m' ?>
            </small>
        <?php elseif ($r['status'] === 'active'): ?>
            <small class="text-warning fw-600">Ongoing</small>
        <?php else: ?>
            <small>—</small>
        <?php endif; ?>
    </td>

    <td>
        <span class="badge-<?= $r['status'] === 'incomplete' ? 'incomplete' : $r['status'] ?>">
            <?= $r['status'] === 'incomplete' ? 'Incomplete' : ucfirst($r['status']) ?>
        </span>
    </td>

    <!-- Award Points Button -->
    <td>
        <button type="button"
            class="btn btn-sm btn-award"
            title="Award Points"
            onclick="openAwardModal(
                '<?= htmlspecialchars(addslashes($r['student_id'])) ?>',
                '<?= htmlspecialchars(addslashes($r['firstname'].' '.$r['lastname'])) ?>'
            )">
           Award
        </button>
    </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- PAGINATION -->
<?php if ($total_pages > 1): ?>
<div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted">
        Showing <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> of <?= $total ?>
    </small>
    <nav>
        <ul class="pagination pagination-sm mb-0 gap-1">
            <?php
            $qs = http_build_query(['search'=>$search,'status'=>$status,'date'=>$date]);
            for ($p = 1; $p <= min($total_pages, 10); $p++): ?>
                <li class="page-item <?= $p==$page ? 'active' : '' ?>">
                    <a class="page-link rounded" href="?page=<?= $p ?>&<?= $qs ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>
</div>

</div>
</div>

<!-- ── Award Points Modal ── -->
<div class="modal fade" id="awardModal" tabindex="-1" aria-labelledby="awardModalLabel">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header" style="background:var(--purple,#5a3d82);border-radius:12px 12px 0 0;">
        <h5 class="modal-title text-white" id="awardModalLabel">
            Award Reward Points
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            Rule: Every <strong>3 points = 1 extra sit-in session</strong> beyond the base limit of <?= SEM_LIMIT ?>.
        </div>

        <form method="POST" id="awardForm">
            <input type="hidden" name="action"   value="award">
            <input type="hidden" name="_search"  value="<?= htmlspecialchars($search) ?>">
            <input type="hidden" name="_status"  value="<?= htmlspecialchars($status) ?>">
            <input type="hidden" name="_date"    value="<?= htmlspecialchars($date) ?>">
            <input type="hidden" name="_page"    value="<?= $page ?>">

            <div class="mb-3">
                <label class="form-label fw-600">Student</label>
                <div class="p-2 rounded" style="background:#f4efff;color:#5a3d82;font-weight:600;" id="awardStudentDisplay">—</div>
                <input type="hidden" name="student_id" id="awardStudentId">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-4">
                    <label class="form-label">Points <span class="text-danger">*</span></label>
                    <input type="number" name="points" id="awardPointsInput"
                           class="form-control" min="1" max="100" placeholder="1–100"
                           required oninput="updateAwardPreview()">
                </div>
                <div class="col-8">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" name="reason" class="form-control"
                           placeholder="e.g. Excellent behavior" required minlength="5">
                </div>
            </div>

            <div class="award-preview mb-3" id="awardPreview" style="display:none;">
                <i class="bi bi-calculator me-1"></i>
                <span id="awardPreviewText"></span>
            </div>

            <button type="submit" class="btn w-100 py-2 fw-600"
                    style="background:var(--gold,#d4a017);color:#fff;border:none;border-radius:10px;">
                Award Points
            </button>
        </form>
      </div>
    </div>
  </div>
</div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openAwardModal(studentId, studentName) {
    document.getElementById('awardStudentId').value      = studentId;
    document.getElementById('awardStudentDisplay').textContent = studentName + ' (' + studentId + ')';
    document.getElementById('awardPointsInput').value    = '';
    document.getElementById('awardPreview').style.display = 'none';
    new bootstrap.Modal(document.getElementById('awardModal')).show();
}

function updateAwardPreview() {
    const pts   = parseInt(document.getElementById('awardPointsInput').value) || 0;
    const bonus = Math.floor(pts / 3);
    const el    = document.getElementById('awardPreview');
    const txt   = document.getElementById('awardPreviewText');
    if (pts > 0) {
        txt.textContent = pts + ' point' + (pts > 1 ? 's' : '') +
            (bonus > 0
                ? ' → unlocks ' + bonus + ' extra session' + (bonus > 1 ? 's' : '')
                : ' (need ' + (3 - pts % 3) + ' more for next session)');
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}
</script>

<style>
.btn-award {
    background: linear-gradient(135deg, #5a3d82, #7b5fb3);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: .78rem;
    padding: 4px 10px;
    transition: opacity .2s, transform .15s;
}
.btn-award:hover { opacity: .9; transform: translateY(-1px); color: #fff; }
.badge-incomplete {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 600;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
}
.award-preview {
    background: #e8f5e9;
    border: 1px solid #a5d6a7;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: .875rem;
    color: #1b5e20;
    font-weight: 600;
}
.page-item.active .page-link { background: var(--purple,#5a3d82); border-color: var(--purple,#5a3d82); }
.page-link { color: var(--purple,#5a3d82); }

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