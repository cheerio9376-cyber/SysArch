<?php
// admin/sitin.php — Sit-In Management
date_default_timezone_set('Asia/Manila');

require 'includes/admin_auth.php';
$conn->query("SET time_zone = '+08:00'");

$current_page = 'sitin';

// Handle manual sit-in registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register') {
    $sid     = trim($_POST['student_id'] ?? '');
    $purpose = trim($_POST['purpose']    ?? '');
    $lab     = trim($_POST['lab']        ?? '');
    $pc      = trim($_POST['pc_number']  ?? '');

    if (!$sid) {
        $_SESSION['error'] = "Student ID is required.";
    } elseif (!is_numeric($pc) || $pc < 1 || $pc > 50) {
        $_SESSION['error'] = "PC number must be between 1 and 50.";
    } else {
        $chk = $conn->prepare("SELECT student_id, firstname, lastname FROM students WHERE student_id=? AND role='student'");
        $chk->bind_param("s", $sid);
        $chk->execute();
        $student = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$student) {
            $_SESSION['error'] = "Student ID '$sid' not found.";
        } else {
            $act = $conn->prepare("SELECT id FROM sitins WHERE student_id=? AND status='active'");
            $act->bind_param("s", $sid);
            $act->execute();
            $act->store_result();
            $already_active = $act->num_rows > 0;
            $act->close();

            if ($already_active) {
                $_SESSION['error'] = "Student already has an active sit-in session.";
            } else {
                $pts_q = $conn->prepare("SELECT COALESCE(SUM(points),0) AS pts FROM reward_points WHERE student_id=?");
                $pts_q->bind_param("s", $sid);
                $pts_q->execute();
                $total_pts = (int)$pts_q->get_result()->fetch_assoc()['pts'];
                $pts_q->close();
                $bonus           = (int)floor($total_pts / 3);
                $effective_limit = SEM_LIMIT + $bonus;

                $lim = $conn->prepare(
                    "SELECT COUNT(*) AS cnt FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?"
                );
                $sem_start = SEM_START;
                $sem_end   = SEM_END;
                $lim->bind_param("sss", $sid, $sem_start, $sem_end);
                $lim->execute();
                $count = (int)$lim->get_result()->fetch_assoc()['cnt'];
                $lim->close();

                if ($count >= $effective_limit) {
                    $_SESSION['error'] = "Student has reached their semester limit ($effective_limit sessions).";
                } else {
                    $ins = $conn->prepare(
                        "INSERT INTO sitins (student_id, purpose, lab, pc_number, sit_in_time, status)
                         VALUES (?, ?, ?, ?, NOW(), 'active')"
                    );
                    $ins->bind_param("sssi", $sid, $purpose, $lab, $pc);
                    if ($ins->execute()) {
                        $_SESSION['success'] = "Sit-in registered for " . $student['firstname'] . " " . $student['lastname'] . "!";
                    } else {
                        $_SESSION['error'] = "Failed to register sit-in.";
                    }
                    $ins->close();
                }
            }
        }
    }
    header("Location: sitin.php");
    exit();
}

// AJAX: fetch student info
if (isset($_GET['fetch_student'])) {
    header('Content-Type: application/json');
    $sid = trim($_GET['student_id'] ?? '');
    if (!$sid) { echo json_encode(['error' => 'No ID provided']); exit; }

    $s = $conn->prepare(
        "SELECT s.firstname, s.lastname, s.course_level, s.course,
                (SELECT COALESCE(SUM(points),0) FROM reward_points WHERE student_id=s.student_id) AS total_points,
                (SELECT COUNT(*) FROM sitins WHERE student_id=s.student_id AND sit_in_time BETWEEN ? AND ?) AS sem_used
         FROM students s WHERE s.student_id=? AND s.role='student'"
    );
    $s->bind_param("sss", SEM_START, SEM_END, $sid);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();

    if ($row) {
        $bonus           = (int)floor((int)$row['total_points'] / 3);
        $effective_limit = SEM_LIMIT + $bonus;
        $remaining       = max(0, $effective_limit - (int)$row['sem_used']);
        $row['bonus_sessions']  = $bonus;
        $row['effective_limit'] = $effective_limit;
        $row['remaining']       = $remaining;
        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'Student not found']);
    }
    exit;
}

// Current active sit-ins
$active = $conn->query("
    SELECT si.id, si.student_id, si.purpose, si.lab, si.pc_number, si.sit_in_time,
           s.firstname, s.lastname, s.course_level, s.course
    FROM sitins si
    JOIN students s ON s.student_id = si.student_id
    WHERE si.status = 'active'
    ORDER BY si.sit_in_time ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sit-In Management | CCS Admin</title>
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
        <h2>Sit-In Management</h2>
        <small class="text-muted">Monitor and manage current sit-in sessions</small>
    </div>

    <div class="row g-3">
        <!-- REGISTER FORM -->
        <div class="col-lg-4">
            <div class="card-ccs p-4">
                <h5 class="fw-bold mb-3" style="color:#5a3d82;"><i class="bi bi-plus-circle me-2"></i>Register Sit-In</h5>
                <form method="POST" id="sitinForm">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label class="form-label">Student ID <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" name="student_id" id="studentIdInput"
                                   class="form-control" placeholder="Enter ID..." required>
                            <button type="button" class="btn btn-purple" onclick="lookupStudent()">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </div>

                    <div id="studentPreview" class="alert alert-info py-2 small mb-3 d-none"></div>
                    <div id="studentError" class="alert alert-danger py-2 small mb-3 d-none">
                        <i class="bi bi-exclamation-triangle me-2"></i>Student not found.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Purpose <span class="text-danger">*</span></label>
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
                        <label class="form-label">Laboratory <span class="text-danger">*</span></label>
                        <select name="lab" class="form-select" required>
                            <option value="">Select lab...</option>
                            <option>Lab 524</option>
                            <option>Lab 526</option>
                            <option>Lab 528</option>
                            <option>Lab 530</option>
                            <option>Mac Lab</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">PC Number <span class="text-danger">*</span></label>
                        <input type="number" name="pc_number" class="form-control" placeholder="Enter PC number (1–50)..." min="1" max="50" required>
                    </div>
                    <button type="submit" class="btn btn-gold w-100 py-2">
                        <i class="bi bi-play-fill me-2"></i>Register Sit-In
                    </button>
                </form>
            </div>
        </div>

        <!-- ACTIVE SIT-INS TABLE -->
        <div class="col-lg-8">
            <div class="card-ccs p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0" style="color:#5a3d82;">
                        Active Sessions
                        <span class="badge ms-2" style="background:#5a3d82;"><?= count($active) ?></span>
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>

                <?php if (empty($active)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle-fill fs-1 text-success mb-3 d-block"></i>
                        <p class="text-muted">No active sit-ins at the moment.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-ccs w-100">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Purpose</th>
                                    <th>Lab</th>
                                    <th>PC#</th>
                                    <th>Time In</th>
                                    <th>Elapsed</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($active as $a):
                                $elapsed = max(0, floor((time() - strtotime($a['sit_in_time'])) / 60));
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-600 small"><?= htmlspecialchars($a['lastname'] . ', ' . $a['firstname']) ?></div>
                                        <div class="text-muted" style="font-size:0.76rem;"><?= $a['student_id'] ?></div>
                                    </td>
                                    <td><small><?= $a['course_level'] ?> <?= $a['course'] ?></small></td>
                                    <td><small><?= htmlspecialchars($a['purpose'] ?? '—') ?></small></td>
                                    <td><small><?= htmlspecialchars($a['lab'] ?? '—') ?></small></td>
                                    <td><small><?= $a['pc_number'] ?? '—' ?></small></td>
                                    <td><small><?= date('g:i A', strtotime($a['sit_in_time'])) ?></small></td>
                                    <td>
                                        <span class="text-warning fw-600 small">
                                            <?php
                                                if ($elapsed >= 60) {
                                                    echo floor($elapsed / 60) . 'h ' . ($elapsed % 60) . 'm';
                                                } elseif ($elapsed === 0) {
                                                    echo 'Just now';
                                                } else {
                                                    echo $elapsed . 'm';
                                                }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger py-0 px-2"
                                            onclick="openEndModal(
                                                <?= $a['id'] ?>,
                                                '<?= htmlspecialchars(addslashes($a['firstname'] . ' ' . $a['lastname'])) ?>',
                                                '<?= htmlspecialchars(addslashes($a['student_id'])) ?>'
                                            )">
                                            <i class="bi bi-stop-fill me-1"></i>End
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div></div>

<!-- ═══════════════════════════════════════════════
     END SESSION MODAL
     ═══════════════════════════════════════════════ -->
<div class="modal fade" id="endSessionModal" tabindex="-1" aria-labelledby="endSessionLabel" aria-modal="true" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0" style="border-radius:14px;overflow:hidden;">

      <div class="modal-header py-3" style="background:#5a3d82;">
        <h5 class="modal-title text-white fw-bold" id="endSessionLabel">
          <i class="bi bi-stop-circle me-2"></i>End Session
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-4">

        <!-- Student banner -->
        <div class="rounded-3 px-3 py-2 mb-4 small fw-bold"
             style="background:#f4efff;color:#5a3d82;border:1px solid #d6c9f0;"
             id="endStudentBanner">—</div>

        <!-- STEP 1: Complete / Incomplete -->
        <div id="endStep1">
          <p class="fw-bold mb-3" style="color:#333;font-size:.95rem;">
            Did the student complete their task?
          </p>
          <div class="d-flex gap-3 mb-3">
            <button type="button" class="btn flex-grow-1 py-3 fw-bold end-status-btn"
                    id="btnComplete"
                    style="border:2px solid #198754;color:#198754;border-radius:10px;background:#fff;transition:all .2s;"
                    onclick="selectStatus('completed')">
              <i class="bi bi-check-circle-fill me-2"></i>Completed
            </button>
            <button type="button" class="btn flex-grow-1 py-3 fw-bold end-status-btn"
                    id="btnIncomplete"
                    style="border:2px solid #dc3545;color:#dc3545;border-radius:10px;background:#fff;transition:all .2s;"
                    onclick="selectStatus('incomplete')">
              <i class="bi bi-x-circle-fill me-2"></i>Incomplete
            </button>
          </div>

        </div>

        <!-- STEP 2: Award points + confirm (revealed after status picked) -->
        <div id="endStep2" class="d-none">
          <hr class="my-3">

          <!-- Award points section (hidden for incomplete) -->
          <div id="awardSection">
            <p class="fw-bold mb-1" style="color:#333;font-size:.9rem;">
              <i class="bi bi-award-fill me-1" style="color:#d4a017;"></i>
              Award Points? <span class="fw-normal text-muted small">(optional)</span>
            </p>
            <p class="small text-muted mb-3">
              Every <strong>3 points</strong> unlocks 1 extra sit-in session.
            </p>
            <div class="row g-2 mb-2">
              <div class="col-4">
                <label class="form-label small fw-bold">Points</label>
                <input type="number" id="endPoints" class="form-control form-control-sm"
                       min="1" max="100" placeholder="1–100"
                       oninput="updateEndPreview()">
              </div>
              <div class="col-8">
                <label class="form-label small fw-bold">Reason</label>
                <input type="text" id="endReason" class="form-control form-control-sm"
                       placeholder="e.g. Finished project early"
                       oninput="clearReasonError()">
              </div>
            </div>
            <div id="endPointsPreview" class="d-none rounded-3 px-3 py-2 mb-3 small fw-bold"
                 style="background:#e8f5e9;border:1px solid #a5d6a7;color:#1b5e20;"></div>
          </div>

          <!-- Confirm button -->
          <button type="button" class="btn w-100 py-2 fw-bold mt-1"
                  id="confirmEndBtn"
                  style="background:#5a3d82;color:#fff;border:none;border-radius:10px;"
                  onclick="confirmEndSession()">
            <i class="bi bi-check2 me-2"></i>Confirm &amp; End Session
          </button>
        </div>

      </div>
    </div>
  </div>
</div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Sidebar toggle ──
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('show');
});

// ── Student ID lookup ──
function lookupStudent() {
    const sid = document.getElementById('studentIdInput').value.trim();
    if (!sid) return;
    fetch('sitin.php?fetch_student=1&student_id=' + encodeURIComponent(sid))
        .then(r => r.json())
        .then(data => {
            const preview = document.getElementById('studentPreview');
            const errDiv  = document.getElementById('studentError');
            if (data.error) {
                preview.classList.add('d-none');
                errDiv.classList.remove('d-none');
            } else {
                preview.innerHTML =
                    '<i class="bi bi-person-check me-2"></i>' +
                    '<strong>' + data.firstname + ' ' + data.lastname + '</strong> — ' +
                    data.course_level + ' ' + data.course +
                    '<br><small>Sessions: ' + data.sem_used + ' / ' + data.effective_limit +
                    (data.bonus_sessions > 0
                        ? ' <span class="badge bg-success">+' + data.bonus_sessions + ' bonus</span>'
                        : '') +
                    ' &nbsp;|&nbsp; Remaining: <strong>' + data.remaining + '</strong></small>';
                preview.classList.remove('d-none');
                errDiv.classList.add('d-none');
            }
        })
        .catch(() => document.getElementById('studentError').classList.remove('d-none'));
}

document.getElementById('studentIdInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); lookupStudent(); }
});

// ── Auto-refresh every 90 seconds ──
setInterval(() => location.reload(), 90000);

// ════════════════════════════════════════════════════
// END SESSION MODAL
// ════════════════════════════════════════════════════
let _endId     = null;
let _endName   = '';
let _endStatus = '';

function openEndModal(id, name, studentId) {
    _endId     = id;
    _endName   = name;
    _endStatus = '';

    // Reset UI
    document.getElementById('endStudentBanner').textContent = name + ' (' + studentId + ')';
    document.getElementById('endStep2').classList.add('d-none');
    document.getElementById('endPoints').value = '';
    document.getElementById('endReason').value = '';
    document.getElementById('endPointsPreview').classList.add('d-none');
    document.getElementById('endReason').style.borderColor = '';
    resetStatusButtons();

    new bootstrap.Modal(document.getElementById('endSessionModal')).show();
}

function resetStatusButtons() {
    const c = document.getElementById('btnComplete');
    const i = document.getElementById('btnIncomplete');
    c.style.background  = '#fff';
    c.style.color       = '#198754';
    c.style.borderColor = '#198754';
    i.style.background  = '#fff';
    i.style.color       = '#dc3545';
    i.style.borderColor = '#dc3545';
}

function selectStatus(status) {
    _endStatus = status;
    resetStatusButtons();

    if (status === 'completed') {
        const b = document.getElementById('btnComplete');
        b.style.background  = '#198754';
        b.style.color       = '#fff';
        b.style.borderColor = '#198754';
        document.getElementById('awardSection').classList.remove('d-none');
    } else {
        const b = document.getElementById('btnIncomplete');
        b.style.background  = '#dc3545';
        b.style.color       = '#fff';
        b.style.borderColor = '#dc3545';
        document.getElementById('awardSection').classList.add('d-none');
        document.getElementById('endPointsPreview').classList.add('d-none');
    }

    // Reveal Step 2
    document.getElementById('endStep2').classList.remove('d-none');
}

function updateEndPreview() {
    const pts   = parseInt(document.getElementById('endPoints').value) || 0;
    const bonus = Math.floor(pts / 3);
    const el    = document.getElementById('endPointsPreview');
    if (pts > 0) {
        el.textContent = pts + ' point' + (pts > 1 ? 's' : '') +
            (bonus > 0
                ? ' → unlocks ' + bonus + ' extra session' + (bonus > 1 ? 's' : '')
                : ' (need ' + (3 - pts % 3) + ' more for next bonus session)');
        el.classList.remove('d-none');
    } else {
        el.classList.add('d-none');
    }
}

function clearReasonError() {
    document.getElementById('endReason').style.borderColor = '';
}

function confirmEndSession() {
    if (!_endStatus) {
        alert('Please select Completed or Incomplete first.');
        return;
    }

    const points = parseInt(document.getElementById('endPoints').value) || 0;
    const reason = document.getElementById('endReason').value.trim();

    // If points are entered, reason is required
    if (points > 0 && reason.length < 3) {
        document.getElementById('endReason').style.borderColor = '#dc3545';
        document.getElementById('endReason').focus();
        return;
    }

    const payload = {
        action:            'end',
        id:                _endId,
        completion_status: _endStatus,
    };
    if (points > 0 && reason) {
        payload.points = points;
        payload.reason = reason;
    }

    const btn = document.getElementById('confirmEndBtn');
    btn.disabled   = true;
    btn.innerHTML  = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';

    fetch('../api/admin_sitin.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('endSessionModal')).hide();
            location.reload();
        } else {
            alert(d.error || 'Failed to end session.');
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-check2 me-2"></i>Confirm & End Session';
        }
    })
    .catch(err => {
        alert('Network error: ' + err);
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-check2 me-2"></i>Confirm & End Session';
    });
}
</script>
</body>
</html>