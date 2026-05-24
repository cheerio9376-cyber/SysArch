<?php
// feedback.php — Student feedback (text-only, no star rating)

session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

require 'config/db.php';

$sid = $_SESSION['student_id'];

$stmt = $conn->prepare("SELECT * FROM students WHERE student_id=?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$firstname = $student['firstname'];
$lastname = $student['lastname'];
$course_level = $student['course_level'];
$course = $student['course'];
$profile_pic = $student['profile_pic'];


// ── Handle POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sitin_id = (int)($_POST['sitin_id'] ?? 0);
    $category = trim($_POST['category'] ?? 'General');
    $message = trim($_POST['message'] ?? '');

    if (strlen($message) < 5) {
        $_SESSION['error'] = 'Please write a feedback message (min. 5 characters).';
    } else {

        // rating column: insert NULL since removed in UI
        $ins = $conn->prepare("
            INSERT INTO feedback 
            (student_id, sitin_id, rating, category, message, created_at) 
            VALUES (?, ?, NULL, ?, ?, NOW())
        ");

        $ins->bind_param("siss", $sid, $sitin_id, $category, $message);

        if ($ins->execute()) {
            $_SESSION['success'] = 'Thank you for your feedback!';
        } else {
            $_SESSION['error'] = 'Could not submit feedback. Please try again.';
        }

        $ins->close();
    }

    header("Location: feedback.php");
    exit();
}


// ── Fetch completed sessions ─────────────────────────────
$ss = $conn->prepare("
    SELECT id, purpose, lab, sit_in_time 
    FROM sitins 
    WHERE student_id=? AND status='completed' 
    ORDER BY sit_in_time DESC 
    LIMIT 20
");

$ss->bind_param("s", $sid);
$ss->execute();
$sessions = $ss->get_result()->fetch_all(MYSQLI_ASSOC);
$ss->close();


// ── Fetch previous feedback ─────────────────────────────
$fb = $conn->prepare("
    SELECT f.*, s.purpose, s.lab 
    FROM feedback f 
    LEFT JOIN sitins s ON f.sitin_id = s.id 
    WHERE f.student_id=? 
    ORDER BY f.created_at DESC 
    LIMIT 10
");

$fb->bind_param("s", $sid);
$fb->execute();
$feedbacks = $fb->get_result()->fetch_all(MYSQLI_ASSOC);
$fb->close();


// ── Announcements ───────────────────────────────────────
$ann_res = $conn->query("
    SELECT title, content, created_at 
    FROM announcements 
    ORDER BY created_at DESC
");

$announcements = $ann_res->fetch_all(MYSQLI_ASSOC);

$new_count = 0;
foreach ($announcements as $a) {
    if (strtotime($a['created_at']) > ($_SESSION['ann_last_seen'] ?? 0)) {
        $new_count++;
    }
}


// ── Categories ──────────────────────────────────────────
$categories = [
    'General',
    'Facilities',
    'Equipment',
    'Internet / Network',
    'Cleanliness'
];

$active_page = 'feedback';

include 'includes/layout.php';
?>


<div class="page-header">
    <h2><i class="bi bi-chat-square-text me-2"></i>Feedback</h2>
    <p>Share your experience and help us improve lab facilities and services.</p>
</div>


<div class="row g-4">

    <!-- ── Feedback Form ── -->
    <div class="col-lg-5">
        <div class="ccs-card">
            <div class="ccs-card-title">
                <i class="bi bi-pencil"></i> Submit Feedback
            </div>

            <form method="POST" action="feedback.php">

                <div class="row g-2 mb-3">
                    <div class="col-12">
                        <label class="form-label">
                            Related Session 
                            <span class="text-muted small">(optional)</span>
                        </label>

                        <select name="sitin_id" class="form-select">
                            <option value="0">Not session-specific</option>

                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= date('M d, Y g:i A', strtotime($s['sit_in_time'])) ?>
                                    — <?= htmlspecialchars($s['purpose']) ?>
                                    <?= $s['lab'] ? ' ('.$s['lab'].')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Category</label>

                        <select name="category" class="form-select">
                            <?php foreach ($categories as $cat): ?>
                                <option><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Your Feedback <span class="text-danger">*</span>
                    </label>

                    <textarea 
                        name="message" 
                        class="form-control" 
                        rows="6" 
                        minlength="5" 
                        required
                        placeholder="Share your experience, suggestions, or concerns..."
                    ></textarea>

                    <div class="form-text">
                        Minimum 5 characters. Your feedback helps us improve.
                    </div>
                </div>

                <button type="submit" class="btn btn-gold w-100 py-2">
                    <i class="bi bi-send me-2"></i>
                    Submit Feedback
                </button>
            </form>
        </div>
    </div>


    <!-- ── Previous Feedback ── -->
    <div class="col-lg-7">
        <div class="ccs-card">
            <div class="ccs-card-title">
                <i class="bi bi-archive"></i> My Previous Feedback
            </div>

            <?php if (empty($feedbacks)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-chat-right-text fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted">
                        You haven't submitted any feedback yet.
                    </p>
                </div>

            <?php else: ?>
                <div class="fb-list">

                    <?php foreach ($feedbacks as $fb): ?>
                        <div class="fb-card">

                            <div class="fb-card-header">
                                <span class="fb-category">
                                    <?= htmlspecialchars($fb['category']) ?>
                                </span>

                                <span class="ms-auto fb-date">
                                    <?= date('M d, Y', strtotime($fb['created_at'])) ?>
                                </span>
                            </div>

                            <?php if ($fb['purpose']): ?>
                                <div class="fb-session-ref">
                                    <i class="bi bi-tag me-1"></i>
                                    <?= htmlspecialchars($fb['purpose']) ?>
                                    <?= $fb['lab'] ? ' — '.$fb['lab'] : '' ?>
                                </div>
                            <?php endif; ?>

                            <div class="fb-message">
                                <?= nl2br(htmlspecialchars($fb['message'])) ?>
                            </div>

                        </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>
        </div>
    </div>

</div>


<style>
.fb-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.fb-card {
    background: #fafafa;
    border: 1px solid rgba(90,61,130,.08);
    border-left: 4px solid var(--purple);
    border-radius: 12px;
    padding: 14px;
    transition: transform .15s;
}

.fb-card:hover {
    transform: translateX(3px);
}

.fb-card-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.fb-category {
    font-size: 0.75rem;
    background: var(--purple-soft);
    color: var(--purple);
    padding: 3px 12px;
    border-radius: 20px;
    font-weight: 600;
}

.fb-date {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.fb-session-ref {
    font-size: 0.8rem;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.fb-message {
    font-size: 0.875rem;
    color: #444;
    line-height: 1.7;
}
</style>

<?php include 'includes/layout_footer.php'; ?>