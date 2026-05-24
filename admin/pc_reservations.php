<?php
// admin/pc_reservations.php — Admin view and management of PC reservations
ob_start();
require 'includes/admin_auth.php';
$current_page = 'pc_reservations';

// ── Auto-expire past PENDING reservations only ────────────
// Approved reservations are NOT auto-expired — admin or student must manage them.
$conn->query("
    UPDATE pc_reservations
    SET status='expired'
    WHERE status = 'pending'
      AND (
        reservation_date < CURDATE()
        OR (reservation_date = CURDATE() AND end_time < CURTIME())
      )
");
// ── Handle Approve ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_action = $_POST['action'] ?? '';
   $res_id = (int)($_POST['reservation_id'] ?? 0);

    if ($posted_action === 'approve' && $res_id > 0) {
        $upd = $conn->prepare("UPDATE pc_reservations SET status='approved', decline_reason=NULL WHERE id=?");
        $upd->bind_param("i", $res_id);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $_SESSION['success'] = "Reservation #$res_id approved successfully.";
        } else {
            $_SESSION['error'] = "Failed to approve reservation #$res_id.";
        }
        $upd->close();
        header("Location: pc_reservations.php");
        exit();
    }

    // ── Handle Reject / Decline (with optional reason) ────
    if ($posted_action === 'reject' && $res_id > 0) {
        $reason = trim($_POST['decline_reason'] ?? '');
        $upd = $conn->prepare("UPDATE pc_reservations SET status='rejected', decline_reason=? WHERE id=?");
        $upd->bind_param("si", $reason, $res_id);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $_SESSION['success'] = "Reservation #$res_id declined." . ($reason ? " Reason sent to student." : "");
        } else {
            $_SESSION['error'] = "Failed to decline reservation #$res_id.";
        }
        $upd->close();
        header("Location: pc_reservations.php");
        exit();
    }

    // ── Handle generic status update (cancel approved, etc.) ──
    if ($posted_action === 'update_status' && $res_id > 0) {
        $new_status = $_POST['status'] ?? '';
        $allowed    = ['approved', 'rejected', 'expired', 'cancelled'];
        if (!in_array($new_status, $allowed)) {
            $_SESSION['error'] = "Invalid status.";
        } else {
            $upd = $conn->prepare("UPDATE pc_reservations SET status=? WHERE id=?");
            $upd->bind_param("si", $new_status, $res_id);
            if ($upd->execute() && $upd->affected_rows > 0) {
                $_SESSION['success'] = "Reservation #$res_id updated to '$new_status'.";
            } else {
                $_SESSION['error'] = "Update failed.";
            }
            $upd->close();
        }
        header("Location: pc_reservations.php?" . http_build_query([
            'lab'    => $_GET['lab']    ?? '',
            'date'   => $_GET['date']   ?? '',
            'status' => $_GET['status'] ?? '',
        ]));
        exit();
    }
}

// ── Handle delete ─────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id  = (int)$_GET['delete'];
    $del = $conn->prepare("DELETE FROM pc_reservations WHERE id=?");
    $del->bind_param("i", $id);
    $del->execute();
    $_SESSION['success'] = 'Reservation deleted.';
    header("Location: pc_reservations.php");
    exit();
}

// ── Filters ───────────────────────────────────────────────
$filter_lab    = trim($_GET['lab']    ?? '');
$filter_date   = trim($_GET['date']   ?? '');
$filter_status = trim($_GET['status'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;

$where  = [];
$params = [];
$types  = '';

if ($filter_lab)    { $where[] = "r.lab=?";              $params[] = $filter_lab;    $types .= 's'; }
if ($filter_date)   { $where[] = "r.reservation_date=?"; $params[] = $filter_date;   $types .= 's'; }
if ($filter_status) { $where[] = "r.status=?";           $params[] = $filter_status; $types .= 's'; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$cnt_q = $conn->prepare(
    "SELECT COUNT(*) FROM pc_reservations r JOIN students s ON r.student_id=s.student_id $whereSQL"
);
if ($params) $cnt_q->bind_param($types, ...$params);
$cnt_q->execute();
$total       = (int)$cnt_q->get_result()->fetch_row()[0];
$total_pages = max(1, (int)ceil($total / $per_page));

// Rows
$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . 'ii';
$rq = $conn->prepare("
    SELECT r.*, CONCAT(s.firstname,' ',s.lastname) AS student_name, s.course_level, s.course
    FROM pc_reservations r
    JOIN students s ON r.student_id = s.student_id
    $whereSQL
    ORDER BY
        CASE r.status WHEN 'pending' THEN 0 ELSE 1 END ASC,
        r.reservation_date ASC,
        r.start_time ASC
    LIMIT ? OFFSET ?
");
$rq->bind_param($all_types, ...$all_params);
$rq->execute();
$records = $rq->get_result()->fetch_all(MYSQLI_ASSOC);

// PC grid
$selected_lab  = $filter_lab  ?: 'Lab 524';
$selected_date = $filter_date ?: date('Y-m-d');
$pc_stmt = $conn->prepare("
    SELECT pc_number, start_time, end_time, student_id, status
    FROM pc_reservations
    WHERE lab=? AND reservation_date=? AND status IN ('pending','approved','in_use')
");
$pc_stmt->bind_param("ss", $selected_lab, $selected_date);
$pc_stmt->execute();
$reserved_pcs = [];
foreach ($pc_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    $reserved_pcs[$r['pc_number']] = $r;
}
$pc_stmt->close();

$sitin_pc_stmt = $conn->prepare("
    SELECT pc_number, student_id, sit_in_time as start_time, NULL as end_time
    FROM sitins
    WHERE lab=? AND DATE(sit_in_time)=? AND status='active'
");
$sitin_pc_stmt->bind_param("ss", $selected_lab, $selected_date);
$sitin_pc_stmt->execute();
foreach ($sitin_pc_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
    if (!isset($reserved_pcs[$r['pc_number']])) {
        $reserved_pcs[$r['pc_number']] = array_merge($r, ['status' => 'in_use']);
    }
}
$sitin_pc_stmt->close();

$pending_count = (int)$conn->query("SELECT COUNT(*) FROM pc_reservations WHERE status='pending'")->fetch_row()[0];
$labs = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530', 'Mac Lab'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Reservations | CCS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root { --purple:#5a3d82; --gold:#d4a017; }
        .btn-approve { background:#27ae60;color:#fff;border:none;font-size:.8rem; }
        .btn-approve:hover { background:#219a52;color:#fff; }
        .btn-reject  { background:#fff;color:#e74c3c;border:1px solid #e74c3c;font-size:.8rem; }
        .btn-reject:hover { background:#e74c3c;color:#fff; }
        .pending-row { background:#fff8e1!important; }
        .pending-row:hover { background:#fff3cd!important; }
        .decline-reason-badge { font-size:.7rem;max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:middle; }
    </style>
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="admin-content">

    <?php include 'includes/admin_alerts.php'; ?>

    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2>PC Reservations</h2>
            <small class="text-muted">
                <?= $total ?> reservation<?= $total !== 1 ? 's' : '' ?>
                <?php if ($pending_count > 0): ?>
                    &nbsp;&bull;&nbsp;<span class="text-warning fw-600"><?= $pending_count ?> pending approval</span>
                <?php endif; ?>
            </small>
        </div>
    </div>

    <!-- ── PC Grid ── -->
    <div class="card-ccs p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="fw-bold mb-0" style="color:var(--purple);">
                <i class="bi bi-grid-3x3-gap me-2"></i>PC Map — <?= htmlspecialchars($selected_lab) ?>
            </h5>
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <select name="lab" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($labs as $l): ?>
                        <option <?= $selected_lab===$l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= $selected_date ?>" onchange="this.form.submit()">
                <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
            </form>
        </div>
        <div class="pc-grid-admin">
            <?php for ($pc = 1; $pc <= 50; $pc++):
                $r = $reserved_pcs[$pc] ?? null;
                if (!$r)                           { $cls = 'pca-available'; $title = "PC $pc — Available"; }
                elseif ($r['status']==='pending')  { $cls = 'pca-pending';   $title = "PC $pc — Pending ({$r['student_id']})"; }
                elseif ($r['status']==='approved') { $cls = 'pca-approved';  $title = "PC $pc — Approved ({$r['student_id']}) {$r['start_time']}–{$r['end_time']}"; }
                elseif ($r['status']==='in_use')   { $cls = 'pca-inuse';     $title = "PC $pc — In Use ({$r['student_id']})"; }
                else                               { $cls = 'pca-available'; $title = "PC $pc"; }
            ?>
                <div class="pca-btn <?= $cls ?>" title="<?= htmlspecialchars($title) ?>">
                    <span class="pca-num"><?= $pc ?></span>
                    <?php if ($r): ?>
                        <span class="pca-time"><?= substr($r['start_time'],0,5) ?>–<?= substr($r['end_time'] ?? '',0,5) ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="mt-2 small text-muted d-flex gap-3 flex-wrap">
            <span><span class="pca-leg pca-available"></span> Available (<?= 50 - count($reserved_pcs) ?>)</span>
            <span><span class="pca-leg pca-pending"></span> Pending (<?= count(array_filter($reserved_pcs, fn($r)=>$r['status']==='pending')) ?>)</span>
            <span><span class="pca-leg pca-approved"></span> Approved (<?= count(array_filter($reserved_pcs, fn($r)=>$r['status']==='approved')) ?>)</span>
            <span><span class="pca-leg pca-inuse"></span> In Use (<?= count(array_filter($reserved_pcs, fn($r)=>$r['status']==='in_use')) ?>)</span>
        </div>
    </div>

    <!-- ── Filters ── -->
    <div class="card-ccs p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="lab" class="form-select form-select-sm">
                    <option value="">All Labs</option>
                    <?php foreach ($labs as $l): ?>
                        <option <?= $filter_lab===$l ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="date" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="pending"   <?= $filter_status==='pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="approved"  <?= $filter_status==='approved'  ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected"  <?= $filter_status==='rejected'  ? 'selected' : '' ?>>Rejected</option>
                    <option value="in_use"    <?= $filter_status==='in_use'    ? 'selected' : '' ?>>In Use</option>
                    <option value="cancelled" <?= $filter_status==='cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="expired"   <?= $filter_status==='expired'   ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-purple btn-sm flex-grow-1">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                <a href="pc_reservations.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>

    <!-- ── Table ── -->
    <div class="card-ccs p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table-ccs w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>PC / Lab</th>
                        <th>Date</th>
                        <th>Time Slot</th>
                        <th>Purpose</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No reservations match your filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $i => $r):
                        $is_pending = $r['status'] === 'pending';
                        $bc = match($r['status']) {
                            'pending'   => 'warning',
                            'approved'  => 'success',
                            'rejected'  => 'danger',
                            'cancelled' => 'secondary',
                            'in_use'    => 'info',
                            'expired'   => 'secondary',
                            default     => 'light'
                        };
                    ?>
                    <tr class="<?= $is_pending ? 'pending-row' : '' ?>">
                        <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="fw-600 small"><?= htmlspecialchars($r['student_name']) ?></div>
                            <div class="text-muted" style="font-size:.76rem;">
                                <?= $r['student_id'] ?> &bull; <?= $r['course_level'] ?>
                            </div>
                        </td>
                        <td>
                            <strong>PC <?= $r['pc_number'] ?></strong>
                            <div class="text-muted small"><?= htmlspecialchars($r['lab']) ?></div>
                        </td>
                        <td><small><?= date('M d, Y', strtotime($r['reservation_date'])) ?></small></td>
                        <td>
                            <small>
                                <?= date('g:i A', strtotime($r['start_time'])) ?>–<?= date('g:i A', strtotime($r['end_time'])) ?>
                            </small>
                        </td>
                        <td><small><?= htmlspecialchars($r['purpose']) ?></small></td>
                        <td><small class="text-muted"><?= date('M d, g:i A', strtotime($r['created_at'])) ?></small></td>
                        <td>
                            <span class="badge bg-<?= $bc ?>">
                                <?= ucfirst($r['status']) ?>
                                <?php if ($is_pending): ?>
                                    <i class="bi bi-exclamation-circle ms-1"></i>
                                <?php endif; ?>
                            </span>
                            <!-- Show decline reason snippet -->
                            <?php if ($r['status'] === 'rejected' && !empty($r['decline_reason'])): ?>
                                <div class="mt-1">
                                    <span class="text-danger decline-reason-badge"
                                          title="<?= htmlspecialchars($r['decline_reason']) ?>">
                                        <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($r['decline_reason']) ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <?php if ($is_pending): ?>
                                    <!-- ✅ APPROVE -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve">
                                      <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-approve px-2 py-0"
                                                onclick="return confirm('Approve reservation #<?= $r['id'] ?> for <?= htmlspecialchars(addslashes($r['student_name'])) ?>?')">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                    </form>

                                    <!-- ✅ DECLINE (opens modal with reason field) -->
                                    <button type="button" class="btn btn-sm btn-reject px-2 py-0"
                                            data-bs-toggle="modal" data-bs-target="#declineModal"
                                            data-id="<?= $r['id'] ?>"
                                            data-student="<?= htmlspecialchars(addslashes($r['student_name'])) ?>"
                                            data-pc="PC <?= $r['pc_number'] ?> – <?= htmlspecialchars($r['lab']) ?>">
                                        <i class="bi bi-x-lg"></i> Decline
                                    </button>

                                <?php elseif ($r['status'] === 'approved'): ?>
                                    <!-- Cancel an approved reservation -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="btn btn-sm btn-outline-warning py-0 px-2"
                                                onclick="return confirm('Cancel this approved reservation?')"
                                                title="Cancel">
                                            <i class="bi bi-slash-circle"></i> Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- DELETE -->
                                <a href="pc_reservations.php?delete=<?= $r['id'] ?>"
                                   class="btn btn-sm btn-outline-danger py-0 px-2"
                                   onclick="return confirm('Permanently delete reservation #<?= $r['id'] ?>?')"
                                   title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
                Showing <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> of <?= $total ?>
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $qs = http_build_query([
                        'lab'    => $filter_lab,
                        'date'   => $filter_date,
                        'status' => $filter_status
                    ]);
                    for ($p = 1; $p <= min($total_pages, 10); $p++): ?>
                        <li class="page-item <?= $p===$page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&<?= $qs ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div></div>

<!-- ══════════════════════════════════════════
     DECLINE MODAL — admin enters reason here
════════════════════════════════════════════ -->
<div class="modal fade" id="declineModal" tabindex="-1" aria-labelledby="declineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="declineForm">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reservation_id" id="declineResId">
                
                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title text-danger" id="declineModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Decline Reservation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-3 small" id="declineModalSubtitle"></p>

                    <label class="form-label fw-600">Reason for declining <span class="text-muted fw-normal">(optional but recommended)</span></label>
                    <textarea name="decline_reason" id="declineReason" class="form-control" rows="3"
                              maxlength="300"
                              placeholder="e.g. PC is reserved for a class, please choose another time slot…"></textarea>
                    <div class="form-text">This reason will be visible to the student on their reservation page.</div>

                    <!-- Quick reason shortcuts -->
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <span class="text-muted small me-1">Quick:</span>
                        <?php
                        $quick_reasons = [
                            'PC reserved for class use',
                            'Time slot conflict',
                            'Lab not available on that date',
                            'Please choose another PC',
                        ];
                        foreach ($quick_reasons as $qr): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 quick-reason"
                                    data-reason="<?= htmlspecialchars($qr) ?>"
                                    style="font-size:.75rem;">
                                <?= htmlspecialchars($qr) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Decline this reservation?')">
                        <i class="bi bi-x-lg me-1"></i>Decline Reservation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('show');
});

// Decline Modal — populate fields when opened
const declineModal = document.getElementById('declineModal');
declineModal.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('declineResId').value          = btn.dataset.id;
    document.getElementById('declineModalSubtitle').textContent =
        `Student: ${btn.dataset.student} | ${btn.dataset.pc}`;
    document.getElementById('declineReason').value = ''; // reset each time
});

// Quick reason buttons
document.querySelectorAll('.quick-reason').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('declineReason').value = this.dataset.reason;
    });
});
</script>

<style>
.pc-grid-admin { display:grid;grid-template-columns:repeat(auto-fill,minmax(68px,1fr));gap:5px; }
.pca-btn { border-radius:7px;padding:5px 4px;text-align:center;font-size:.7rem;border:2px solid transparent;transition:all .15s; }
.pca-available { background:#e8f5e9;color:#1b5e20;border-color:#a5d6a7; }
.pca-pending   { background:#fff8e1;color:#b8860b;border-color:#ffe082; }
.pca-approved  { background:#e8f5e9;color:#1b5e20;border-color:#66bb6a; }
.pca-inuse     { background:#e3f2fd;color:#0d47a1;border-color:#90caf9; }
.pca-num  { display:block;font-size:.82rem;font-weight:700; }
.pca-time { display:block;font-size:.6rem;line-height:1.2; }
.pca-leg  { display:inline-block;width:11px;height:11px;border-radius:3px;margin-right:4px;vertical-align:middle; }
</style>
</body>
</html>