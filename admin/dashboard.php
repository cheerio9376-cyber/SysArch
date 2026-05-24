<?php
// admin/dashboard.php — Admin Dashboard (Redesigned, matches student UI style)
require 'includes/admin_auth.php';
$current_page = 'dashboard';

// ── Handle Reservation Approve/Reject ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $res_id = (int)($_POST['reservation_id'] ?? 0);

    if ($action === 'approve' && $res_id > 0) {
        $upd = $conn->prepare("UPDATE pc_reservations SET status='approved', decline_reason=NULL WHERE id=? AND status='pending'");
        $upd->bind_param("i", $res_id);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $_SESSION['success'] = "Reservation #$res_id has been approved.";
        } else {
            $_SESSION['error'] = "Failed to approve reservation #$res_id.";
        }
        $upd->close();
        header("Location: dashboard.php");
        exit();
    }

    if ($action === 'reject' && $res_id > 0) {
        $reason = trim($_POST['decline_reason'] ?? '');
        $upd = $conn->prepare("UPDATE pc_reservations SET status='rejected', decline_reason=? WHERE id=? AND status='pending'");
        $upd->bind_param("si", $reason, $res_id);
        if ($upd->execute() && $upd->affected_rows > 0) {
            $_SESSION['success'] = "Reservation #$res_id has been rejected." . ($reason ? " Reason sent to student." : "");
        } else {
            $_SESSION['error'] = "Failed to reject reservation #$res_id.";
        }
        $upd->close();
        header("Location: dashboard.php");
        exit();
    }
}

// ── Stats ────────────────────────────────────────────────
$total_students       = $conn->query("SELECT COUNT(*) FROM students WHERE role='student'")->fetch_row()[0];
$active_sitins        = $conn->query("SELECT COUNT(*) FROM sitins WHERE status='active'")->fetch_row()[0];
$total_records        = $conn->query("SELECT COUNT(*) FROM sitins")->fetch_row()[0];
$total_ann            = $conn->query("SELECT COUNT(*) FROM announcements")->fetch_row()[0];
$total_points_awarded = $conn->query("SELECT COALESCE(SUM(points),0) FROM reward_points WHERE points > 0")->fetch_row()[0];
$pending_reservations = $conn->query("SELECT COUNT(*) FROM pc_reservations WHERE status='pending'")->fetch_row()[0];
$today_sitins         = $conn->query("SELECT COUNT(*) FROM sitins WHERE DATE(sit_in_time)=CURDATE()")->fetch_row()[0];

// ── Pending Reservations (admin needs to act on these) ───
$pending_res = $conn->query("
    SELECT r.id, r.pc_number, r.lab, r.reservation_date, r.start_time, r.end_time,
           r.purpose, r.status, r.created_at,
           CONCAT(s.firstname,' ',s.lastname) as student_name,
           s.student_id, s.course_level, s.course
    FROM pc_reservations r
    JOIN students s ON r.student_id = s.student_id
   WHERE r.status = 'pending'
    ORDER BY r.reservation_date ASC, r.start_time ASC
    LIMIT 15
")->fetch_all(MYSQLI_ASSOC);

// ── Active Sit-Ins ───────────────────────────────────────
$active_list = $conn->query("
    SELECT s.student_id, s.firstname, s.lastname, s.course_level, s.course,
         si.id as sit_id, si.purpose, si.lab, si.pc_number, si.sit_in_time
    FROM sitins si
    JOIN students s ON s.student_id = si.student_id
    WHERE si.status = 'active'
    ORDER BY si.sit_in_time DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// ── Recent Points Awards ─────────────────────────────────
$recent_awards = $conn->query("
    SELECT rp.points, rp.reason, rp.created_at,
           s.firstname, s.lastname, s.student_id,
           a.firstname as admin_first
    FROM reward_points rp
    JOIN students s ON rp.student_id = s.student_id
    LEFT JOIN admins a ON rp.admin_id = a.admin_id
    WHERE rp.points > 0
    ORDER BY rp.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── Recent Completed Sit-Ins ─────────────────────────────
$recent_records = $conn->query("
    SELECT s.firstname, s.lastname, si.purpose, si.lab, si.sit_in_time, si.sit_out_time, si.duration_minutes
    FROM sitins si
    JOIN students s ON s.student_id = si.student_id
    WHERE si.status = 'completed'
    ORDER BY si.sit_out_time DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── Announcements Preview ────────────────────────────────
$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | CCS Sit-In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ── Admin Dashboard Specific Styles ── */
        :root {
            --purple: #5a3d82;
            --purple-light: #7c5aa8;
            --purple-soft: #f3eeff;
            --gold: #d4a017;
            --gold-soft: #fff8e1;
        }

        body { background: #f5f3f8; font-family: 'Poppins', sans-serif; }

        /* Stat Cards */
        .stat-card {
            background: #fff;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(90,61,130,0.08);
            transition: all 0.2s;
            border: 1px solid rgba(90,61,130,0.06);
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(90,61,130,0.15); }
        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 12px; font-size: 1.4rem;
        }
        .stat-icon.purple { background: var(--purple-soft); }
        .stat-icon.gold   { background: var(--gold-soft); }
        .stat-icon.green  { background: #e8f5e9; }
        .stat-icon.blue   { background: #e3f2fd; }
        .stat-icon.red    { background: #fce4ec; }
        .stat-icon.teal   { background: #e0f2f1; }
        .stat-number { font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
        .stat-label  { font-size: 0.8rem; color: #888; margin-top: 2px; }

        /* Content Cards */
        .dash-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(90,61,130,0.08);
            border: 1px solid rgba(90,61,130,0.06);
        }
        .dash-card-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid rgba(90,61,130,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .dash-card-header h6 {
            font-weight: 700;
            color: var(--purple);
            margin: 0;
            font-size: 0.95rem;
        }
        .dash-card-body { padding: 0; }

        /* Pending reservation item */
        .res-item {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(90,61,130,0.06);
            transition: background 0.15s;
        }
        .res-item:hover { background: var(--purple-soft); }
        .res-item:last-child { border-bottom: none; }

        /* Active sitin table */
        .sitin-table { width: 100%; font-size: 0.83rem; }
        .sitin-table th {
            padding: 10px 14px;
            background: var(--purple-soft);
            color: var(--purple);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .sitin-table td { padding: 10px 14px; border-bottom: 1px solid rgba(90,61,130,0.06); vertical-align: middle; }
        .sitin-table tr:last-child td { border-bottom: none; }

        /* Alert badges */
        .pending-badge {
            background: #fff8e1;
            border: 1px solid #ffe082;
            color: #b8860b;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        /* Btn styles */
        .btn-approve { background: #27ae60; color: #fff; border: none; }
        .btn-approve:hover { background: #219a52; color: #fff; }
        .btn-reject  { background: #fff; color: #e74c3c; border: 1px solid #e74c3c; }
        .btn-reject:hover { background: #e74c3c; color: #fff; }
        .btn-purple  { background: var(--purple); color: #fff; border: none; }
        .btn-purple:hover { background: var(--purple-light); color: #fff; }
        .btn-gold { background: var(--gold); color: #333; border: none; }
        .btn-gold:hover { background: #c4920e; color: #333; }

        /* Award item */
        .award-item {
            padding: 12px 18px;
            border-bottom: 1px solid rgba(90,61,130,0.06);
            border-left: 3px solid var(--gold);
            transition: all 0.15s;
        }
        .award-item:hover { background: var(--gold-soft); }

        /* Page header */
        .page-header { padding: 10px 0 20px; }
        .page-header h2 { font-size: 1.4rem; font-weight: 700; color: var(--purple); margin: 0; }

        /* Layout */
        .admin-wrapper { display: flex; min-height: 100vh; }
        .admin-content { flex: 1; padding: 90px 28px 32px; max-width: 100%; }
        @media (min-width: 992px) {
            .admin-content { margin-left: 260px; }
        }

        .empty-state { text-align: center; padding: 32px 20px; color: #aaa; }
        .empty-state i { font-size: 2rem; display: block; margin-bottom: 10px; }

        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: #27ae60; display: inline-block; margin-right: 6px; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    </style>
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>

<div class="admin-wrapper">
<div class="admin-content">

    <!-- Flash Alerts -->
    <?php include 'includes/admin_alerts.php'; ?>

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2>Admin Dashboard</h2>
            <small class="text-muted"><?= date('l, F d, Y') ?></small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($pending_reservations > 0): ?>
                <span class="pending-badge"><i class="bi bi-clock me-1"></i><?= $pending_reservations ?> Pending Reservation<?= $pending_reservations > 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <a href="award_points.php" class="btn btn-sm btn-gold">Award Points</a>
            <a href="sitin.php" class="btn btn-sm btn-purple"><i class="bi bi-plus-lg me-1"></i>Register Sit-In</a>
        </div>
    </div>

    <!-- ── STAT CARDS ── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
               
                <div class="stat-number" style="color:var(--purple);"><?= $total_students ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
                
                <div class="stat-number text-success"><?= $active_sitins ?></div>
                <div class="stat-label">Active Sit-Ins</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
              
                <div class="stat-number text-info"><?= $today_sitins ?></div>
                <div class="stat-label">Today's Sit-Ins</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
               
                <div class="stat-number text-danger"><?= $pending_reservations ?></div>
                <div class="stat-label">Pending Reservations</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
          
                <div class="stat-number" style="color:var(--gold);"><?= number_format($total_points_awarded) ?></div>
                <div class="stat-label">Points Awarded</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-card">
               
                <div class="stat-number" style="color:var(--purple);"><?= $total_records ?></div>
                <div class="stat-label">Total Records</div>
            </div>
        </div>
    </div>

    <!-- ── MAIN CONTENT GRID ── -->
    <div class="row g-3">

        <!-- ── PENDING RESERVATIONS — FULL WIDTH ── -->
        <?php if (!empty($pending_res)): ?>
        <div class="col-12">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6><i class="bi bi-calendar-event me-2"></i>Pending Reservations
                        <span class="badge ms-2" style="background:var(--gold);color:#333;font-size:.75rem;"><?= count($pending_res) ?></span>
                    </h6>
                    <a href="pc_reservations.php" class="btn btn-sm btn-outline-secondary py-0">View All</a>
                </div>
                <div class="dash-card-body">
                    <?php foreach ($pending_res as $r): ?>
                    <div class="res-item">
                        <div class="row align-items-center g-2">
                            <div class="col-md-3">
                                <div class="fw-600 small"><?= htmlspecialchars($r['student_name']) ?></div>
                                <small class="text-muted"><?= $r['student_id'] ?> &bull; <?= $r['course_level'] ?> <?= $r['course'] ?></small>
                            </div>
                            <div class="col-md-2">
                                <div class="fw-600 small">PC <?= $r['pc_number'] ?></div>
                                <small class="text-muted"><?= htmlspecialchars($r['lab']) ?></small>
                            </div>
                            <div class="col-md-3">
                                <div class="small"><?= date('M d, Y', strtotime($r['reservation_date'])) ?></div>
                                <small class="text-muted"><?= date('g:i A', strtotime($r['start_time'])) ?>–<?= date('g:i A', strtotime($r['end_time'])) ?></small>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted"><?= htmlspecialchars($r['purpose']) ?></small>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <!-- APPROVE BUTTON -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-approve px-3 py-1"
                                                onclick="return confirm('Approve reservation #<?= $r['id'] ?> for <?= htmlspecialchars(addslashes($r['student_name'])) ?>?')">
                                            <i class="bi bi-check-lg me-1"></i>Approve
                                        </button>
                                    </form>
                                    <!-- REJECT BUTTON → opens modal -->
                                    <button type="button" class="btn btn-sm btn-reject px-2 py-1"
                                            data-bs-toggle="modal" data-bs-target="#dashDeclineModal"
                                            data-id="<?= $r['id'] ?>"
                                            data-student="<?= htmlspecialchars(addslashes($r['student_name'])) ?>"
                                            data-pc="PC <?= $r['pc_number'] ?> – <?= htmlspecialchars($r['lab']) ?>">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── ACTIVE SIT-INS ── -->
        <div class="col-lg-7">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6>
                        <?php if ($active_sitins > 0): ?>
                            <span class="status-dot"></span>
                        <?php endif; ?>
                        <i class="bi bi-activity me-2"></i>Active Sit-Ins
                        <span class="badge ms-2" style="background:var(--purple);font-size:.72rem;"><?= $active_sitins ?></span>
                    </h6>
                    <a href="sitin.php" class="btn btn-sm btn-purple py-0 px-3">Manage</a>
                </div>
                <div class="dash-card-body">
                    <?php if (empty($active_list)): ?>
                        <div class="empty-state">
                            <i class="bi bi-check-circle text-success"></i>
                            No active sit-ins at the moment.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="sitin-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Purpose</th>
                                        <th>Lab</th>
                                        <th>PC#</th>
                                        <th>Time In</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($active_list as $a): ?>
                                <tr>
                                    <td>
                                        <div class="fw-600"><?= htmlspecialchars($a['lastname'].', '.$a['firstname']) ?></div>
                                        <small class="text-muted"><?= $a['student_id'] ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($a['purpose'] ?? '—') ?></small></td>
                                    <td><small><?= htmlspecialchars($a['lab'] ?? '—') ?></small></td>
                                    <td><small>PC <?= htmlspecialchars($a['pc_number'] ?? '—') ?></small></td>
                                    <td><small><?= date('g:i A', strtotime($a['sit_in_time'])) ?></small></td>
                                    <td>
                                        <button class="btn btn-sm btn-danger py-0 px-2"
                                                onclick="adminEndSitin(<?= $a['sit_id'] ?>, '<?= htmlspecialchars(addslashes($a['firstname'].' '.$a['lastname'])) ?>')"
                                                title="End Session">
                                            <i class="bi bi-stop-fill"></i>
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

        <!-- ── RECENT AWARDS ── -->
        <div class="col-lg-5">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6>Recent Awards</h6>
                    <a href="award_points.php" class="btn btn-sm btn-gold py-0 px-3">Award</a>
                </div>
                <div class="dash-card-body">
                    <?php if (empty($recent_awards)): ?>
                        <div class="empty-state">
                            <i class="bi bi-trophy"></i>
                            No points awarded yet.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_awards as $award): ?>
                        <div class="award-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600 small"><?= htmlspecialchars($award['firstname'].' '.$award['lastname']) ?></div>
                                    <small class="text-muted"><?= $award['student_id'] ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success">+<?= $award['points'] ?> pts</span>
                                    <div class="text-muted" style="font-size:.72rem;"><?= date('M d, g:i A', strtotime($award['created_at'])) ?></div>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1"><?= htmlspecialchars(substr($award['reason'], 0, 55)) ?><?= strlen($award['reason']) > 55 ? '…' : '' ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── ANNOUNCEMENTS ── -->
        <div class="col-lg-6">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6><i class="bi bi-megaphone me-2"></i>Announcements</h6>
                    <a href="announcements.php" class="btn btn-sm btn-gold py-0 px-3">Manage</a>
                </div>
                <div class="dash-card-body">
                    <?php if (empty($announcements)): ?>
                        <div class="empty-state"><i class="bi bi-megaphone"></i>No announcements yet.</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                        <div class="res-item">
                            <div class="fw-600 small mb-1"><?= htmlspecialchars($ann['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars(substr($ann['content'], 0, 90)) ?><?= strlen($ann['content']) > 90 ? '…' : '' ?></small>
                            <div class="mt-1"><small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?></small></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── RECENT COMPLETED ── -->
        <div class="col-lg-6">
            <div class="dash-card">
                <div class="dash-card-header">
                    <h6>Recently Completed</h6>
                    <a href="records.php" class="btn btn-sm btn-purple py-0 px-3">All Records</a>
                </div>
                <div class="dash-card-body">
                    <?php if (empty($recent_records)): ?>
                        <div class="empty-state"><i class="bi bi-inbox"></i>No completed sessions yet.</div>
                    <?php else: ?>
                        <table class="sitin-table">
                            <thead><tr><th>Student</th><th>Purpose</th><th>Duration</th></tr></thead>
                            <tbody>
                            <?php foreach ($recent_records as $r): ?>
                            <tr>
                                <td class="fw-600"><?= htmlspecialchars($r['lastname'].', '.$r['firstname']) ?></td>
                                <td><small><?= htmlspecialchars($r['purpose'] ?? '—') ?></small></td>
                                <td><small><?= $r['duration_minutes'] ? ($r['duration_minutes'] >= 60 ? floor($r['duration_minutes']/60).'h '.($r['duration_minutes']%60).'m' : $r['duration_minutes'].'m') : '—' ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /row -->

</div><!-- /admin-content -->
</div><!-- /admin-wrapper -->

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<!-- ══════════════════════════════════════════
     DECLINE MODAL — admin enters reason here
════════════════════════════════════════════ -->
<div class="modal fade" id="dashDeclineModal" tabindex="-1" aria-labelledby="dashDeclineModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="dashDeclineForm">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reservation_id" id="dashDeclineResId">

                <div class="modal-header border-0 pb-1">
                    <h5 class="modal-title text-danger" id="dashDeclineModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Decline Reservation
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-3 small" id="dashDeclineSubtitle"></p>

                    <label class="form-label fw-bold">Reason for declining <span class="text-muted fw-normal">(optional but recommended)</span></label>
                    <textarea name="decline_reason" id="dashDeclineReason" class="form-control" rows="3"
                              maxlength="300"
                              placeholder="e.g. PC is reserved for a class, please choose another time slot…"></textarea>
                    <div class="form-text">This reason will be visible to the student on their reservation page.</div>

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
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 dash-quick-reason"
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('adminSidebar').classList.toggle('show');
});

// Decline modal — populate from button data attributes
const dashDeclineModal = document.getElementById('dashDeclineModal');
if (dashDeclineModal) {
    dashDeclineModal.addEventListener('show.bs.modal', function(e) {
        const btn = e.relatedTarget;
        document.getElementById('dashDeclineResId').value = btn.dataset.id;
        document.getElementById('dashDeclineSubtitle').textContent =
            'Student: ' + btn.dataset.student + ' | ' + btn.dataset.pc;
        document.getElementById('dashDeclineReason').value = '';
    });
}

// Quick reason buttons
document.querySelectorAll('.dash-quick-reason').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('dashDeclineReason').value = this.dataset.reason;
    });
});

function adminEndSitin(id, name) {
    if (!confirm('End sit-in session for ' + name + '?')) return;
    fetch('../api/admin_sitin.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'end', id:id})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) location.reload();
        else alert(d.error || 'Failed to end session.');
    })
    .catch(() => alert('Network error. Please try again.'));
}

// Auto-refresh every 60 seconds
setInterval(() => location.reload(), 60000);
</script>
</body>
</html>