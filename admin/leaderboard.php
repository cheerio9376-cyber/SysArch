<?php
// admin/leaderboard.php — Leaderboard ranked by composite score
// Score = (points 60%) + (hours 20%) + (completed tasks 20%)
require 'includes/admin_auth.php';
$current_page = 'leaderboard';

$filter_course = trim($_GET['course'] ?? '');
$filter_level  = trim($_GET['level']  ?? '');
$limit         = 50;

$where_parts = ["s.role='student'"];
$params      = [];
$types       = '';

if ($filter_course) {
    $where_parts[] = "s.course = ?";
    $params[]      = $filter_course;
    $types        .= 's';
}
if ($filter_level) {
    $where_parts[] = "s.course_level = ?";
    $params[]      = $filter_level;
    $types        .= 's';
}

$where = implode(' AND ', $where_parts);

$sql = "
    SELECT
        s.student_id, s.firstname, s.lastname, s.course, s.course_level,
        COALESCE(SUM(rp.points), 0)                                          AS total_points,
        FLOOR(COALESCE(SUM(rp.points), 0) / 3)                              AS bonus_sessions,
        COUNT(DISTINCT rp.id)                                                AS award_count,
        COALESCE(SUM(si.duration_minutes), 0)                               AS total_minutes,
        COUNT(CASE WHEN si.status = 'completed' THEN 1 END)                 AS completed_tasks,
        COUNT(CASE WHEN si.status IN ('completed','incomplete') THEN 1 END) AS total_sessions
    FROM students s
    LEFT JOIN reward_points rp ON rp.student_id = s.student_id
    LEFT JOIN sitins si        ON si.student_id  = s.student_id
    WHERE $where
    GROUP BY s.student_id
    HAVING total_points > 0 OR total_minutes > 0 OR completed_tasks > 0
";

$stmt = $conn->prepare($sql);
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Composite score
$max_pts       = max(1, !empty($raw) ? max(array_column($raw, 'total_points'))    : 1);
$max_minutes   = max(1, !empty($raw) ? max(array_column($raw, 'total_minutes'))   : 1);
$max_completed = max(1, !empty($raw) ? max(array_column($raw, 'completed_tasks')) : 1);

foreach ($raw as &$r) {
    $r['score']       = round(
        ($r['total_points']    / $max_pts)       * 100 * 0.60 +
        ($r['total_minutes']   / $max_minutes)   * 100 * 0.20 +
        ($r['completed_tasks'] / $max_completed) * 100 * 0.20,
        1
    );
    $r['total_hours'] = round($r['total_minutes'] / 60, 1);
}
unset($r);

usort($raw, fn($a,$b) => $b['score'] <=> $a['score'] ?: $b['total_points'] <=> $a['total_points']);
$leaders   = array_slice($raw, 0, $limit);
$top_score = $leaders[0]['score'] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard | CCS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Podium */
        .podium-wrap { display:flex; align-items:flex-end; justify-content:center; gap:16px; margin-bottom:8px; }
        .podium-item { text-align:center; flex:1; max-width:200px; }
        .podium-avatar {
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-weight:700; color:#fff; margin:0 auto 10px;
        }
        .podium-item.rank-1 .podium-avatar { width:72px;height:72px;font-size:1.4rem;background:linear-gradient(135deg,#d4a017,#f0c842);box-shadow:0 4px 16px rgba(212,160,23,.4); }
        .podium-item.rank-2 .podium-avatar { width:58px;height:58px;font-size:1.1rem;background:linear-gradient(135deg,#8e9eab,#b0bec5); }
        .podium-item.rank-3 .podium-avatar { width:52px;height:52px;font-size:1rem;background:linear-gradient(135deg,#a07040,#c49464); }
        .podium-block { border-radius:10px 10px 0 0; color:#fff; font-weight:700; font-size:.9rem; display:flex; align-items:center; justify-content:center; }
        .podium-item.rank-1 .podium-block { background:#5a3d82; height:90px; }
        .podium-item.rank-2 .podium-block { background:#7b5fb3; height:65px; }
        .podium-item.rank-3 .podium-block { background:#9580b8; height:48px; }
        .podium-name  { font-size:.85rem; font-weight:600; color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; margin:0 auto 3px; }
        .podium-score { font-size:.78rem; color:#5a3d82; font-weight:600; margin-bottom:6px; }

        /* Rank badges */
        .lb-rank-badge { width:32px;height:32px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem; }
        .rank-gold   { background:#fff3cd;color:#a07010;border:2px solid #d4a017; }
        .rank-silver { background:#f0f0f0;color:#607080;border:2px solid #aaa; }
        .rank-bronze { background:#fdf0e8;color:#7a4a20;border:2px solid #c49464; }
        .rank-other  { background:#f4efff;color:#5a3d82;border:1px solid #c8b0f0; }

        /* Score bar */
        .score-bar-wrap { height:6px;background:#eee;border-radius:99px;overflow:hidden;margin-top:4px; }
        .score-bar      { height:100%;border-radius:99px;background:linear-gradient(90deg,#5a3d82,#d4a017); }

        /* Course badges */
        .course-badge { font-size:.72rem;font-weight:600;padding:3px 10px;border-radius:20px;display:inline-block; }
        .badge-bsit  { background:#e8f4fd;color:#0d6efd; }
        .badge-bscs  { background:#e8fff0;color:#198754; }
        .badge-bscpe { background:#fff3cd;color:#a07010; }
        .badge-bsim  { background:#fce8ff;color:#8a2be2; }
        .badge-bsemc { background:#fde8e8;color:#c0392b; }
        .badge-other { background:#f4efff;color:#5a3d82; }

        .lb-table tbody tr { transition:background .12s; }
        .lb-table tbody tr:hover { background:#f4efff; }
        .empty-lb { text-align:center;padding:60px 20px;color:#aaa; }
        .empty-lb i { font-size:3rem;margin-bottom:12px;display:block;color:#d0c0f0; }

        .filter-bar { background:#fff;border:1px solid #e8e0f5;border-radius:14px;padding:14px 18px;margin-bottom:20px; }
    </style>
</head>
<body>
<?php include 'includes/admin_navbar.php'; ?>
<div class="admin-wrapper">
<div class="admin-content">

    <?php include 'includes/admin_alerts.php'; ?>

    <!-- Page header — matches Student Management style -->
    <div class="page-header d-flex justify-content-between align-items-start mb-4">
        <div>
            <h2>Leaderboard</h2>
            <small class="text-muted">Students ranked by composite score &bull; Points 60% &bull; Hours 20% &bull; Tasks 20%</small>
        </div>
    </div>

    <?php if (!empty($leaders)): ?>

    <!-- Podium Top 3 -->
    <div class="card-ccs p-4 mb-4">
        <h6 class="fw-bold mb-4 text-center" style="color:#5a3d82;letter-spacing:.5px;text-transform:uppercase;">Top Students</h6>
        <div class="podium-wrap">
            <?php
            $p = array_slice($leaders, 0, 3);
            $podium_order = [];
            if (isset($p[1])) $podium_order[] = ['data'=>$p[1],'rank'=>2];
            if (isset($p[0])) $podium_order[] = ['data'=>$p[0],'rank'=>1];
            if (isset($p[2])) $podium_order[] = ['data'=>$p[2],'rank'=>3];
            foreach ($podium_order as $po):
                $pd = $po['data']; $rk = $po['rank'];
                $initials = strtoupper(substr($pd['firstname'],0,1).substr($pd['lastname'],0,1));
            ?>
            <div class="podium-item rank-<?= $rk ?>">
                <div class="podium-avatar"><?= $initials ?></div>
                <div class="podium-name" title="<?= htmlspecialchars($pd['firstname'].' '.$pd['lastname']) ?>">
                    <?= htmlspecialchars($pd['firstname'].' '.$pd['lastname']) ?>
                </div>
                <div class="podium-score"><?= number_format($pd['score'],1) ?> pts</div>
                <div class="podium-block">#<?= $rk ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="filter-bar d-flex flex-wrap gap-2 align-items-center">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center w-100">
            <span class="fw-600 small me-auto" style="color:#5a3d82;">Filter Rankings</span>
            <select name="course" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Courses</option>
                <?php foreach (['BSIT','BSCS','BSCpE','BSIM','BSEMC'] as $c): ?>
                    <option <?= $filter_course===$c?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <select name="level" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Year Levels</option>
                <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $y): ?>
                    <option <?= $filter_level===$y?'selected':'' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-purple btn-sm px-3">Apply</button>
            <?php if ($filter_course || $filter_level): ?>
                <a href="leaderboard.php" class="btn btn-outline-secondary btn-sm px-3">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Rankings Table — matches Students page table style -->
    <div class="card-ccs p-0 overflow-hidden">
        <div class="table-responsive">
            <table class="table-ccs w-100 lb-table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Points <small style="opacity:.7;font-weight:400;">(60%)</small></th>
                        <th>Hours <small style="opacity:.7;font-weight:400;">(20%)</small></th>
                        <th>Tasks Done <small style="opacity:.7;font-weight:400;">(20%)</small></th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($leaders)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-trophy d-block fs-1 mb-2" style="color:#d0c0f0;"></i>
                        No ranked students yet.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($leaders as $i => $st):
                    $rank       = $i + 1;
                    $initials   = strtoupper(substr($st['firstname'],0,1).substr($st['lastname'],0,1));
                    $score_pct  = $top_score > 0 ? round(($st['score'] / $top_score) * 100) : 0;
                    $badge_class = match(strtolower($st['course'])) {
                        'bsit'  => 'badge-bsit',  'bscs'  => 'badge-bscs',
                        'bscpe' => 'badge-bscpe', 'bsim'  => 'badge-bsim',
                        'bsemc' => 'badge-bsemc', default => 'badge-other'
                    };
                    $rank_class = match($rank) { 1=>'rank-gold', 2=>'rank-silver', 3=>'rank-bronze', default=>'rank-other' };
                    $avatar_bg  = match($rank) {
                        1 => 'linear-gradient(135deg,#d4a017,#f0c842)',
                        2 => 'linear-gradient(135deg,#8e9eab,#b0bec5)',
                        3 => 'linear-gradient(135deg,#a07040,#c49464)',
                        default => 'linear-gradient(135deg,#9580b8,#5a3d82)'
                    };
                ?>
                <tr>
                    <td class="align-middle ps-3">
                        <span class="lb-rank-badge <?= $rank_class ?>"><?= $rank ?></span>
                    </td>
                    <td class="align-middle py-3">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:36px;height:36px;border-radius:50%;background:<?= $avatar_bg ?>;
                                display:flex;align-items:center;justify-content:center;
                                font-size:.75rem;font-weight:700;color:#fff;flex-shrink:0;">
                                <?= $initials ?>
                            </div>
                            <div>
                                <div class="fw-600 small"><?= htmlspecialchars($st['firstname'].' '.$st['lastname']) ?></div>
                                <div class="text-muted" style="font-size:.72rem;"><?= $st['student_id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="align-middle">
                        <span class="course-badge <?= $badge_class ?>"><?= htmlspecialchars($st['course']) ?></span>
                    </td>
                    <td class="align-middle small text-muted"><?= htmlspecialchars($st['course_level']) ?></td>
                    <td class="align-middle">
                        <div class="fw-600 small" style="color:#5a3d82;"><?= number_format($st['total_points']) ?> pts</div>
                    </td>
                    <td class="align-middle">
                        <div class="fw-600 small" style="color:#5a3d82;"><?= $st['total_hours'] ?>h</div>
                    </td>
                    <td class="align-middle">
                        <div class="fw-600 small" style="color:#5a3d82;"><?= $st['completed_tasks'] ?></div>
                    </td>
                    <td class="align-middle" style="min-width:130px;">
                        <div class="fw-700 small" style="color:#5a3d82;"><?= number_format($st['score'],1) ?></div>
                        <div class="score-bar-wrap">
                            <div class="score-bar" style="width:<?= $score_pct ?>%;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div></div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>