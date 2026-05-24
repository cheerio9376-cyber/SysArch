<?php
// admin/students.php
// FIXES:
//   1. students.php was calling $ins->execute() TWICE (once in ternary, once in if-block)
//      causing duplicate inserts and wrong success/error detection
//   2. Same double-execute bug on edit and delete
//   3. Bonus sessions now factored into "Remaining" column
require 'includes/admin_auth.php';
$current_page = 'students';

// ── Handle POST actions ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── ADD ──────────────────────────────────────────────
    if ($action === 'add') {
        $sid = trim($_POST['student_id']  ?? '');
        $ln  = trim($_POST['lastname']    ?? '');
        $fn  = trim($_POST['firstname']   ?? '');
        $mn  = trim($_POST['middlename']  ?? '');
        $cl  = trim($_POST['course_level']?? '');
        $co  = trim($_POST['course']      ?? '');
        $em  = trim($_POST['email']       ?? '');
        $ad  = trim($_POST['address']     ?? '');
        $pw  = $_POST['password']         ?? '';

        if (!$sid || !$ln || !$fn || !$cl || !$co || !$em || !$pw) {
            $_SESSION['error'] = "Please fill in all required fields.";
        } elseif (strlen($pw) < 6) {
            $_SESSION['error'] = "Password must be at least 6 characters.";
        } else {
            $chk = $conn->prepare("SELECT id FROM students WHERE student_id=? OR email=?");
            $chk->bind_param("ss", $sid, $em);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $_SESSION['error'] = "Student ID or Email already exists.";
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $ins  = $conn->prepare(
                    "INSERT INTO students (student_id,lastname,firstname,middlename,course_level,course,email,address,password,role)
                     VALUES (?,?,?,?,?,?,?,?,?,'student')"
                );
                $ins->bind_param("sssssssss", $sid, $ln, $fn, $mn, $cl, $co, $em, $ad, $hash);

                // ✅ FIX: call execute() ONCE and check the result
                if ($ins->execute()) {
                    $_SESSION['success'] = "Student added successfully!";
                } else {
                    $_SESSION['error'] = "Failed to add student: " . $conn->error;
                }
                $ins->close();
            }
            $chk->close();
        }
        header("Location: students.php"); exit();
    }

    // ── EDIT ─────────────────────────────────────────────
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $ln = trim($_POST['lastname']     ?? '');
        $fn = trim($_POST['firstname']    ?? '');
        $mn = trim($_POST['middlename']   ?? '');
        $cl = trim($_POST['course_level'] ?? '');
        $co = trim($_POST['course']       ?? '');
        $em = trim($_POST['email']        ?? '');
        $ad = trim($_POST['address']      ?? '');
        $pw = $_POST['password']          ?? '';

        if ($pw !== '') {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $upd  = $conn->prepare(
                "UPDATE students SET lastname=?,firstname=?,middlename=?,course_level=?,course=?,email=?,address=?,password=? WHERE id=?"
            );
            $upd->bind_param("ssssssssi", $ln, $fn, $mn, $cl, $co, $em, $ad, $hash, $id);
        } else {
            $upd = $conn->prepare(
                "UPDATE students SET lastname=?,firstname=?,middlename=?,course_level=?,course=?,email=?,address=? WHERE id=?"
            );
            $upd->bind_param("sssssssi", $ln, $fn, $mn, $cl, $co, $em, $ad, $id);
        }

        // ✅ FIX: execute ONCE
        if ($upd->execute()) {
            $_SESSION['success'] = "Student updated successfully!";
        } else {
            $_SESSION['error'] = "Update failed: " . $conn->error;
        }
        $upd->close();
        header("Location: students.php"); exit();
    }

    // ── DELETE ───────────────────────────────────────────
    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $del = $conn->prepare("DELETE FROM students WHERE id=? AND role='student'");
        $del->bind_param("i", $id);

        // ✅ FIX: execute ONCE
        if ($del->execute() && $del->affected_rows > 0) {
            $_SESSION['success'] = "Student deleted successfully.";
        } else {
            $_SESSION['error'] = "Delete failed or student not found.";
        }
        $del->close();
        header("Location: students.php"); exit();
    }
}

// ── List / Search ─────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$like   = "%$search%";

if ($search) {
    $cnt_q = $conn->prepare(
        "SELECT COUNT(*) FROM students
         WHERE role='student'
           AND (student_id LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR course LIKE ?)"
    );
    $cnt_q->bind_param("ssss", $like, $like, $like, $like);
    $cnt_q->execute();
    $total = (int)$cnt_q->get_result()->fetch_row()[0];
    $cnt_q->close();
} else {
    $total = (int)$conn->query("SELECT COUNT(*) FROM students WHERE role='student'")->fetch_row()[0];
}

// bind_param requires variables (not constants) passed by reference
$sem_start = SEM_START;
$sem_end   = SEM_END;

if ($search) {
    $stmt = $conn->prepare("
        SELECT s.*,
               (SELECT COUNT(*) FROM sitins si
                WHERE si.student_id=s.student_id
                  AND si.sit_in_time BETWEEN ? AND ?) AS sem_used,
               (SELECT COALESCE(SUM(points),0) FROM reward_points rp
                WHERE rp.student_id=s.student_id) AS total_points
        FROM students s
        WHERE s.role='student'
          AND (s.student_id LIKE ? OR s.firstname LIKE ? OR s.lastname LIKE ? OR s.course LIKE ?)
        ORDER BY s.lastname, s.firstname
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssssssii", $sem_start, $sem_end, $like, $like, $like, $like, $limit, $offset);
} else {
    $stmt = $conn->prepare("
        SELECT s.*,
               (SELECT COUNT(*) FROM sitins si
                WHERE si.student_id=s.student_id
                  AND si.sit_in_time BETWEEN ? AND ?) AS sem_used,
               (SELECT COALESCE(SUM(points),0) FROM reward_points rp
                WHERE rp.student_id=s.student_id) AS total_points
        FROM students s
        WHERE s.role='student'
        ORDER BY s.lastname, s.firstname
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssii", $sem_start, $sem_end, $limit, $offset);
}
$stmt->execute();
$students    = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total_pages = (int)ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students | CCS Admin</title>
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

    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h2>Student Management</h2>
            <small class="text-muted"><?= $total ?> total students</small>
        </div>
        <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-person-plus me-2"></i>Add Student
        </button>
    </div>

    <!-- SEARCH -->
    <div class="card-ccs p-3 mb-3">
        <form method="GET" class="d-flex gap-2">
            <div class="search-box flex-grow-1">
                <i class="bi bi-search"></i>
                <input type="text" name="search" class="form-control"
                       placeholder="Search by ID, name, or course..."
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-purple px-4"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($search): ?>
                <a href="students.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card-ccs p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table-ccs w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ID Number</th>
                        <th>Name</th>
                        <th>Year</th>
                        <th>Course</th>
                        <th>Email</th>
                        <th>Sessions Used</th>
                        <th>Remaining</th><!-- ✅ now includes bonus -->
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($students)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No students found.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $i => $s):
                        $used          = (int)$s['sem_used'];
                        $total_pts     = (int)$s['total_points'];
                        $bonus         = (int)floor($total_pts / 3);
                        $effective_lim = SEM_LIMIT + $bonus;
                        $rem           = max(0, $effective_lim - $used);
                    ?>
                    <tr>
                        <td class="text-muted small"><?= $offset + $i + 1 ?></td>
                        <td class="fw-600 small"><?= htmlspecialchars($s['student_id']) ?></td>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($s['lastname'].', '.$s['firstname']) ?></div>
                            <?php if ($s['middlename']): ?>
                                <small class="text-muted"><?= htmlspecialchars($s['middlename']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['course_level']) ?></td>
                        <td><?= htmlspecialchars($s['course']) ?></td>
                        <td class="small"><?= htmlspecialchars($s['email']) ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:6px;width:60px;">
                                    <div class="progress-bar <?= $used >= $effective_lim ? 'bg-danger' : ($used >= $effective_lim * 0.7 ? 'bg-warning' : 'bg-success') ?>"
                                         style="width:<?= min(100, ($effective_lim > 0 ? ($used / $effective_lim) * 100 : 0)) ?>%"></div>
                                </div>
                                <small>
                                    <?= $used ?>/<?= $effective_lim ?>
                                    <?php if ($bonus > 0): ?>
                                        <span class='text-success small'>(+<?= $bonus ?> bonus)</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <span class="fw-600 <?= $rem <= 5 ? 'text-danger' : 'text-success' ?>"><?= $rem ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                    onclick='openEdit(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>)' title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST"
                                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($s['firstname'])) ?>? This cannot be undone.');"
                                      class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="p-3 border-top d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> of <?= $total ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0 gap-1">
                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                            <a class="page-link rounded"
                               href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

</div></div>
<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">ID Number <span class="text-danger">*</span></label>
                    <input type="text" name="student_id" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="lastname" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="firstname" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middlename" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year Level <span class="text-danger">*</span></label>
                    <select name="course_level" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $y): ?>
                            <option><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course <span class="text-danger">*</span></label>
                    <select name="course" class="form-select" required>
                        <option value="">Select...</option>
                        <?php foreach (['BSIT','BSCS','BSCpE','BSIM','BSEMC'] as $c): ?>
                            <option><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" class="form-control"
                           required minlength="6" placeholder="Min. 6 characters">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gold px-4">
                <i class="bi bi-check-lg me-2"></i>Add Student
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <div class="modal-body p-4">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-600 text-muted">Student ID</label>
                    <input type="text" id="editStudentId" class="form-control bg-light" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" id="editLastname" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" id="editFirstname" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle Name</label>
                    <input type="text" name="middlename" id="editMiddlename" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Year Level</label>
                    <select name="course_level" id="editCourseLevel" class="form-select" required>
                        <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $y): ?>
                            <option><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course</label>
                    <select name="course" id="editCourse" class="form-select" required>
                        <?php foreach (['BSIT','BSCS','BSCpE','BSIM','BSEMC'] as $c): ?>
                            <option><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editEmail" class="form-control" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" id="editAddress" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">
                        New Password
                        <small class="text-muted fw-normal">(leave blank to keep current)</small>
                    </label>
                    <input type="password" name="password" class="form-control"
                           placeholder="Enter new password to change">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gold px-4">
                <i class="bi bi-check-lg me-2"></i>Save Changes
            </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('show');
});

function openEdit(s) {
    document.getElementById('editId').value          = s.id;
    document.getElementById('editStudentId').value   = s.student_id;
    document.getElementById('editLastname').value    = s.lastname;
    document.getElementById('editFirstname').value   = s.firstname;
    document.getElementById('editMiddlename').value  = s.middlename || '';
    document.getElementById('editCourseLevel').value = s.course_level;
    document.getElementById('editCourse').value      = s.course;
    document.getElementById('editEmail').value       = s.email;
    document.getElementById('editAddress').value     = s.address || '';
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>