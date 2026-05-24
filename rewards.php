<?php
// rewards.php — Student Reward Points (Improved Layout)
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

// ── Total points ──────────────────────────────────────────
$pts_stmt = $conn->prepare("SELECT COALESCE(SUM(points),0) as pts FROM reward_points WHERE student_id=?");
$pts_stmt->bind_param("s", $sid);
$pts_stmt->execute();
$total_points = (int)$pts_stmt->get_result()->fetch_assoc()['pts'];
$pts_stmt->close();

$bonus_sessions = floor($total_points / 3);

// ── Sit-in count this semester ────────────────────────────
$sem_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM sitins WHERE student_id=? AND sit_in_time BETWEEN ? AND ?");
$start = SEM_START; $end = SEM_END;
$sem_stmt->bind_param("sss", $sid, $start, $end);
$sem_stmt->execute();
$sem_count = (int)$sem_stmt->get_result()->fetch_assoc()['cnt'];
$sem_stmt->close();

$effective_limit = SEM_LIMIT + $bonus_sessions;
$remaining       = max(0, $effective_limit - $sem_count);
$pts_to_next     = 3 - ($total_points % 3);
if ($pts_to_next === 3) $pts_to_next = 0;
$progress_pct    = round(($total_points % 3) / 3 * 100);

// ── Points history ────────────────────────────────────────
$hist_stmt = $conn->prepare("
    SELECT rp.points, rp.reason, rp.created_at,
           CONCAT(a.firstname,' ',a.lastname) as awarded_by
    FROM reward_points rp
    LEFT JOIN admins a ON rp.admin_id = a.admin_id
    WHERE rp.student_id=?
    ORDER BY rp.created_at DESC
    LIMIT 20
");
$hist_stmt->bind_param("s", $sid);
$hist_stmt->execute();
$history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$hist_stmt->close();

// ── Announcements ─────────────────────────────────────────
$ann_res = $conn->query("SELECT title,content,created_at FROM announcements ORDER BY created_at DESC");
$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);
$new_count = 0;
foreach ($announcements as $a) {
    if (strtotime($a['created_at']) > ($_SESSION['ann_last_seen'] ?? 0)) $new_count++;
}

// ── Active sit-in ─────────────────────────────────────────
$active_q = $conn->prepare("SELECT id FROM sitins WHERE student_id=? AND status='active' LIMIT 1");
$active_q->bind_param("s", $sid);
$active_q->execute();
$active_sitin = $active_q->get_result()->fetch_assoc();
$active_q->close();

$active_page = 'rewards';
include 'includes/layout.php';
?>

<div class="page-header">
    <h2>Reward Points</h2>
    <p class="text-muted mb-0">Points are awarded by admin for good lab conduct. <strong>Every 3 points = 1 extra sit-in session.</strong></p>
</div>

<!-- ── TOP SUMMARY CARDS ── -->
<div class="rw-summary-grid mb-4">

    <div class="rw-card rw-card--points">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= number_format($total_points) ?></div>
            <div class="rw-card__label">Total Points</div>
        </div>
    </div>

    <div class="rw-card rw-card--bonus">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $bonus_sessions ?></div>
            <div class="rw-card__label">Bonus Sessions</div>
            <div class="rw-card__sub">3 pts = 1 session</div>
        </div>
    </div>

    <div class="rw-card rw-card--limit">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $effective_limit ?></div>
            <div class="rw-card__label">Session Limit</div>
            <div class="rw-card__sub">Base <?= SEM_LIMIT ?> + <?= $bonus_sessions ?> bonus</div>
        </div>
    </div>

    <div class="rw-card rw-card--remaining">
        <div class="rw-card__body">
            <div class="rw-card__number"><?= $remaining ?></div>
            <div class="rw-card__label">Sessions Left</div>
            <div class="rw-card__sub"><?= $sem_count ?> used this semester</div>
        </div>
    </div>

</div>

<!-- ── PROGRESS BAR ── -->
<?php if ($total_points > 0 || true): ?>
<div class="ccs-card mb-4" style="padding:20px 24px;">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <span class="fw-600" style="font-size:.9rem;">Progress to next bonus session</span>
            <?php if ($pts_to_next > 0): ?>
                <span class="text-muted small ms-2"><?= $pts_to_next ?> more point<?= $pts_to_next > 1 ? 's' : '' ?> needed</span>
            <?php else: ?>
                <span class="badge bg-success ms-2">You've earned all available bonuses!</span>
            <?php endif; ?>
        </div>
        <span class="fw-700" style="color:#5a3d82;font-size:0.95rem;"><?= $total_points % 3 ?> / 3 pts</span>
    </div>
    <div class="rw-progress-track">
        <div class="rw-progress-fill" style="width:<?= $progress_pct ?>%;"></div>
    </div>
    <?php if ($pts_to_next > 0): ?>
        <div class="small text-muted mt-1">Earn <?= $pts_to_next ?> more point<?= $pts_to_next > 1 ? 's' : '' ?> to unlock session #<?= $bonus_sessions + 1 ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── MAIN GRID: How Points Work + History ── -->
<div class="row g-4">

    <!-- How Points Work -->
    <div class="col-lg-4">
        <div class="ccs-card h-100">
            <div class="ccs-card-title">How Points Work</div>
            <div class="rw-rules">
                <div class="rw-rule">
                    <div>
                        <div class="rw-rule__title">Admin-Only</div>
                        <div class="rw-rule__desc">Points are assigned manually by admin — not automatic.</div>
                    </div>
                </div>
                <div class="rw-rule">
                    <div>
                        <div class="rw-rule__title">3 Points = 1 Session</div>
                        <div class="rw-rule__desc">Every 3 points earns you one extra sit-in beyond the base limit.</div>
                    </div>
                </div>
                <div class="rw-rule">
                    <div>
                        <div class="rw-rule__title">Lab Etiquette</div>
                        <div class="rw-rule__desc">Silence and proper behavior during sessions.</div>
                    </div>
                </div>
                <div class="rw-rule">
                    <div>
                        <div class="rw-rule__title">Equipment Care</div>
                        <div class="rw-rule__desc">Handle PCs and peripherals responsibly.</div>
                    </div>
                </div>
                <div class="rw-rule">
                    <div>
                        <div class="rw-rule__title">Good Conduct</div>
                        <div class="rw-rule__desc">Cooperation and respect for lab rules and staff.</div>
                    </div>
                </div>
                <div class="rw-rule" style="border-bottom:none;">
                    <div>
                        <div class="rw-rule__title">Admin Discretion</div>
                        <div class="rw-rule__desc">Be excellent — admin rewards outstanding behavior!</div>
                    </div>
                </div>
            </div>

            <?php if ($total_points > 0): ?>
            <!-- Mini stats -->
            <div class="rw-mini-stats mt-3">
                <div class="rw-mini-stat">
                    <span class="rw-mini-stat__num"><?= count($history) ?></span>
                    <span class="rw-mini-stat__label">Awards</span>
                </div>
                <div class="rw-mini-stat">
                    <span class="rw-mini-stat__num"><?= $bonus_sessions ?></span>
                    <span class="rw-mini-stat__label">Bonuses</span>
                </div>
                <div class="rw-mini-stat">
                    <span class="rw-mini-stat__num"><?= $effective_limit ?></span>
                    <span class="rw-mini-stat__label">Max Sessions</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Award History -->
    <div class="col-lg-8">
        <div class="ccs-card">
            <div class="ccs-card-title d-flex align-items-center">
                Award History
                <span class="badge bg-info ms-auto"><?= count($history) ?> award<?= count($history) !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (empty($history)): ?>
                <div class="rw-empty">
                    <div class="rw-empty__icon"></div>
                    <div class="rw-empty__title">No points yet</div>
                    <div class="rw-empty__desc">Be punctual, silent, and cooperative during sessions to earn reward points!</div>
                </div>
            <?php else: ?>
                <!-- Summary row -->
                <div class="rw-history-summary">
                    <div class="rw-history-summary__item">
                        <strong><?= $total_points ?></strong> total points
                    </div>
                    <div class="rw-history-summary__item">
                        Last: <?= !empty($history) ? date('M d, Y', strtotime($history[0]['created_at'])) : '—' ?>
                    </div>
                </div>

                <div class="rw-history-list">
                    <?php foreach ($history as $i => $h):
                        $sess_unlocked = floor($h['points'] / 3);
                    ?>
                    <div class="rw-history-item">
                        <!-- Left: index + date -->
                        <div class="rw-history-item__index"><?= $i + 1 ?></div>

                        <div class="rw-history-item__content">
                            <div class="rw-history-item__reason">
                                <?= htmlspecialchars(substr($h['reason'], 0, 90)) ?><?= strlen($h['reason']) > 90 ? '…' : '' ?>
                            </div>
                            <div class="rw-history-item__meta">
                                <span><?= htmlspecialchars($h['awarded_by'] ?? 'Admin') ?></span>
                                <span><?= date('M d, Y g:i A', strtotime($h['created_at'])) ?></span>
                            </div>
                        </div>

                        <!-- Right: points + session bonus -->
                        <div class="rw-history-item__right">
                            <span class="rw-pts-badge">+<?= $h['points'] ?> pts</span>
                            <?php if ($sess_unlocked > 0): ?>
                                <span class="rw-sess-badge">+<?= $sess_unlocked ?> session<?= $sess_unlocked > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($history) >= 20): ?>
                <div class="text-center py-2">
                    <small class="text-muted">Showing latest 20 awards</small>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Reward Points CSS ── -->
<style>
* { font-family: 'Poppins', sans-serif; }
/* Summary grid */
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



.rw-card__number { font-size: 1.75rem; font-weight: 700; line-height: 1.1;}
.rw-card--points .rw-card__number    { color: #5a3d82; }
.rw-card--bonus  .rw-card__number    { color: #27ae60; }
.rw-card--limit  .rw-card__number    { color: #1976d2; }
.rw-card--remaining .rw-card__number { color: #d4a017; font-weight:700;}

.rw-card__label { font-size: .78rem; color: #888; font-weight: 500; margin-top: 2px; }
.rw-card__sub   { font-size: .72rem; color: #aaa; margin-top: 2px; }

/* Progress bar */
.rw-progress-track {
    height: 10px; border-radius: 6px;
    background: rgba(90,61,130,.1);
    overflow: hidden;
}
.rw-progress-fill {
    height: 100%; border-radius: 6px;
    background: linear-gradient(90deg, #5a3d82, #27ae60);
    transition: width .4s ease;
    min-width: 4px;
}

/* Rules */
.rw-rules { display: flex; flex-direction: column; }
.rw-rule {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(90,61,130,.07);
}
.rw-rule:last-child { border-bottom: none; }
.rw-rule__title { font-size: .85rem; font-weight: 600; color: #333; }
.rw-rule__desc  { font-size: .78rem; color: #888; margin-top: 2px; }

/* Mini stats */
.rw-mini-stats {
    display: flex; gap: 0;
    border: 1px solid rgba(90,61,130,.1); border-radius: 10px; overflow: hidden;
    margin-top: 4px;
}
.rw-mini-stat {
    flex: 1; text-align: center; padding: 10px 8px;
    border-right: 1px solid rgba(90,61,130,.1);
}
.rw-mini-stat:last-child { border-right: none; }
.rw-mini-stat__num   { display: block; font-size: 1.2rem; font-weight: 700; color: #5a3d82; }
.rw-mini-stat__label { display: block; font-size: .72rem; color: #999; margin-top: 2px; }

/* Empty state */
.rw-empty { text-align: center; padding: 40px 20px; }
.rw-empty__icon { font-size: 2.5rem; color: #ddd; margin-bottom: 12px; }
.rw-empty__title { font-weight: 600; color: #aaa; margin-bottom: 6px; }
.rw-empty__desc  { font-size: .85rem; color: #bbb; }

/* History summary bar */
.rw-history-summary {
    display: flex; gap: 20px;
    padding: 10px 18px; background: #fafafa;
    border-bottom: 1px solid rgba(90,61,130,.07);
    font-size: .83rem; color: #666;
}
.rw-history-summary__item { display: flex; align-items: center; }

/* History list */
.rw-history-list { max-height: 480px; overflow-y: auto; }
.rw-history-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(90,61,130,.06);
    transition: background .12s;
}
.rw-history-item:hover { background: #fafafa; }
.rw-history-item:last-child { border-bottom: none; }

.rw-history-item__index {
    width: 28px; height: 28px; border-radius: 50%;
    background: rgba(90,61,130,.08); color: #5a3d82;
    display: flex; align-items: center; justify-content: center;
    font-size: .75rem; font-weight: 700; flex-shrink: 0; margin-top: 2px;
}
.rw-history-item__content { flex: 1; min-width: 0; }
.rw-history-item__reason {
    font-size: .875rem; color: #333; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.rw-history-item__meta {
    display: flex; gap: 14px; margin-top: 4px;
    font-size: .75rem; color: #aaa;
    flex-wrap: wrap;
}
.rw-history-item__right {
    display: flex; flex-direction: column; align-items: flex-end;
    gap: 4px; flex-shrink: 0;
}
.rw-pts-badge {
    background: #e8f5e9; color: #1b5e20;
    border-radius: 20px; padding: 3px 10px;
    font-size: .8rem; font-weight: 700;
    border: 1px solid #a5d6a7;
}
.rw-sess-badge {
    background: #e3f2fd; color: #0d47a1;
    border-radius: 20px; padding: 2px 8px;
    font-size: .72rem; font-weight: 600;
    border: 1px solid #90caf9;
}
</style>

<?php include 'includes/layout_footer.php'; ?>