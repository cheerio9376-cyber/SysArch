<?php
// admin/view_feedback.php — Admin view of all student feedback (no star rating)
require 'includes/admin_auth.php';
$current_page = 'view_feedback';

$search  = trim($_GET['search']   ?? '');
$cat     = trim($_GET['category'] ?? '');
$page    = max(1,(int)($_GET['page'] ?? 1));
$limit   = 20;
$offset  = ($page-1)*$limit;

// Build WHERE
$where = []; $params = []; $types = '';
if ($search) {
    $like = "%$search%";
    $where[] = "(s.firstname LIKE ? OR s.lastname LIKE ? OR s.student_id LIKE ?)";
    $params  = array_merge($params, [$like,$like,$like]); $types .= 'sss';
}
if ($cat) { $where[] = "f.category=?"; $params[] = $cat; $types .= 's'; }

$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

// Count
$cnt_q = $conn->prepare("SELECT COUNT(*) FROM feedback f JOIN students s ON f.student_id=s.student_id $whereSQL");
if ($params) $cnt_q->bind_param($types,...$params);
$cnt_q->execute();
$total = $cnt_q->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total/$limit));

// Fetch
$all_params  = array_merge($params,[$limit,$offset]);
$all_types   = $types.'ii';
$fq = $conn->prepare("
    SELECT f.id, f.category, f.message, f.created_at,
           s.student_id, s.firstname, s.lastname, s.course_level, s.course,
           si.purpose as si_purpose, si.lab as si_lab
    FROM feedback f
    JOIN students s ON f.student_id = s.student_id
    LEFT JOIN sitins si ON f.sitin_id = si.id
    $whereSQL
    ORDER BY f.created_at DESC
    LIMIT ? OFFSET ?
");
$fq->bind_param($all_types,...$all_params);
$fq->execute();
$feedbacks = $fq->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats
$total_fb   = $conn->query("SELECT COUNT(*) FROM feedback")->fetch_row()[0];
$categories = ['General','Facilities','Equipment','Internet / Network','Staff & Service','Cleanliness'];

// Category distribution for stats bar
$cat_data = $conn->query("SELECT category, COUNT(*) as cnt FROM feedback GROUP BY category ORDER BY cnt DESC")->fetch_all(MYSQLI_ASSOC);
$cat_map  = array_column($cat_data, 'cnt', 'category');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Feedback | CCS Admin</title>
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
        <div>
            <h2>Student Feedback</h2>
            <small class="text-muted"><?= $total_fb ?> total submissions</small>
        </div>
    </div>

    <!-- ── Category Stats ── -->
   
    <!-- ── Filters ── -->
    <div class="card-ccs p-3 mb-4">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or ID" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option <?= $cat===$c?'selected':'' ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-purple flex-grow-1"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="view_feedback.php" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>

    <!-- ── Feedback Cards ── -->
    <?php if (empty($feedbacks)): ?>
        <div class="card-ccs p-5 text-center text-muted">
            <i class="bi bi-chat-right-text fs-1 d-block mb-3"></i>
            <p>No feedback records match your filters.</p>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-3">
        <?php foreach ($feedbacks as $fb): ?>
            <div class="col-lg-6">
                <div class="card-ccs p-4 h-100 fb-admin-card">
                    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                        <div>
                            <div class="fw-600"><?= htmlspecialchars($fb['firstname'].' '.$fb['lastname']) ?></div>
                            <small class="text-muted"><?= $fb['student_id'] ?> &bull; <?= $fb['course_level'] ?> <?= $fb['course'] ?></small>
                        </div>
                        <small class="text-muted"><?= date('M d, Y', strtotime($fb['created_at'])) ?></small>
                    </div>
                    <div class="mb-2">
                        <span class="fb-cat-badge"><?= htmlspecialchars($fb['category']) ?></span>
                        <?php if ($fb['si_purpose']): ?>
                            <span class="badge bg-light text-secondary">
                                <?= htmlspecialchars($fb['si_purpose']) ?><?= $fb['si_lab']?' — '.$fb['si_lab']:'' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-0 small" style="color:#444;line-height:1.75;">
                        <?= nl2br(htmlspecialchars($fb['message'])) ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="d-flex justify-content-center">
            <ul class="pagination pagination-sm mb-0">
                <?php $qs = http_build_query(['search'=>$search,'category'=>$cat]); ?>
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?>&<?= $qs ?>">← Prev</a>
                </li>
                <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
                    <li class="page-item <?= $p===$page?'active':'' ?>">
                        <a class="page-link" href="?page=<?= $p ?>&<?= $qs ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?>&<?= $qs ?>">Next →</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php endif; ?>

</div></div>

<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<style>
.fb-admin-card {
    border-left: 4px solid var(--purple);
    transition: transform .15s, box-shadow .15s;
}
.fb-admin-card:hover { transform: translateY(-2px); }
.fb-cat-badge {
    display: inline-block;
    background: var(--purple-soft, #f4efff);
    color: var(--purple, #5a3d82);
    font-size: .74rem;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    margin-bottom: 4px;
}
.page-item.active .page-link { background: var(--purple); border-color: var(--purple); }
.page-link { color: var(--purple); }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>