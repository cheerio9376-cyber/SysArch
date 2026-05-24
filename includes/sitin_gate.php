<?php
/**
 * includes/sitin_gate.php
 *
 * Drop this at the TOP of dashboard.php (after session_start + db include).
 *
 * Logic:
 *  - If the student has an ACTIVE sit-in → let them through normally.
 *  - If NOT → show a full-page "locked" screen telling them to ask the admin,
 *    then exit so nothing else in dashboard.php renders.
 *
 * The locked screen still shows the navbar and lets them log out.
 */

if (!isset($_SESSION['student_id'])) {
    header("Location: ../index.php");
    exit();
}

$_gate_sid = $_SESSION['student_id'];

$_gate_q = $conn->prepare(
    "SELECT id, lab, pc_number, purpose, sit_in_time
     FROM sitins
     WHERE student_id = ? AND status = 'active'
     LIMIT 1"
);
$_gate_q->bind_param("s", $_gate_sid);
$_gate_q->execute();
$_gate_active = $_gate_q->get_result()->fetch_assoc();
$_gate_q->close();

if (!$_gate_active) {
    // ── Count sessions used this semester so we can show remaining ──
    $_gate_lim = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM sitins
         WHERE student_id = ? AND sit_in_time BETWEEN ? AND ?"
    );
    $_gate_lim->bind_param("sss", $_gate_sid, SEM_START, SEM_END);
    $_gate_lim->execute();
    $_gate_used = (int)$_gate_lim->get_result()->fetch_assoc()['cnt'];
    $_gate_lim->close();

    $_gate_pts = $conn->prepare(
        "SELECT COALESCE(SUM(points),0) AS pts FROM reward_points WHERE student_id = ?"
    );
    $_gate_pts->bind_param("s", $_gate_sid);
    $_gate_pts->execute();
    $_gate_total_pts  = (int)$_gate_pts->get_result()->fetch_assoc()['pts'];
    $_gate_pts->close();

    $_gate_bonus     = (int)floor($_gate_total_pts / 3);
    $_gate_eff_limit = SEM_LIMIT + $_gate_bonus;
    $_gate_remaining = max(0, $_gate_eff_limit - $_gate_used);

    $firstname = $_SESSION['firstname'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting for Sit-In | CCS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f4efff; min-height: 100vh; display: flex; flex-direction: column; }
        .gate-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(90,61,130,0.12);
            max-width: 480px;
            width: 100%;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .gate-icon {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg,#5a3d82,#9c6dd8);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2.5s infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(90,61,130,0.35); }
            50%      { box-shadow: 0 0 0 14px rgba(90,61,130,0); }
        }
        .badge-pill {
            display: inline-block;
            background: #f4efff;
            color: #5a3d82;
            border: 1.5px solid #d6c9f0;
            border-radius: 50px;
            padding: 0.35rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .step-row {
            display: flex; align-items: flex-start; gap: 0.9rem;
            background: #fafafa; border-radius: 10px; padding: 0.75rem 1rem;
            margin-bottom: 0.5rem; text-align: left;
        }
        .step-num {
            flex-shrink: 0;
            width: 26px; height: 26px; border-radius: 50%;
            background: #5a3d82; color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700;
        }
        .refresh-bar {
            height: 4px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin-top: 1.2rem;
        }
        .refresh-fill {
            height: 100%; background: linear-gradient(90deg,#5a3d82,#9c6dd8);
            border-radius: 4px;
            animation: countdown 30s linear infinite;
        }
        @keyframes countdown { from { width: 100%; } to { width: 0%; } }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#5a3d82;">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="images/CCSLogo2.png" style="height:32px;" class="me-2">
            <span class="fw-bold">College of Computer Studies</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="text-white small opacity-75">
                <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($firstname) ?>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-light ms-2">
                <i class="bi bi-box-arrow-right me-1"></i>Logout
            </a>
        </div>
    </div>
</nav>

<!-- Gate Screen -->
<div class="flex-grow-1 d-flex align-items-center justify-content-center p-3">
    <div class="gate-card">

        <div class="gate-icon">
            <i class="bi bi-person-workspace text-white" style="font-size:2.2rem;"></i>
        </div>

        <h4 class="fw-bold mb-1" style="color:#5a3d82;">
            No Active Sit-In Session
        </h4>
        <p class="text-muted mb-3" style="font-size:0.9rem;">
            Hi, <strong><?= htmlspecialchars($firstname) ?></strong>! Your dashboard will unlock
            once a lab administrator registers your sit-in session.
        </p>

        <!-- Session stats -->
        <div class="d-flex justify-content-center gap-3 mb-4">
            <div class="badge-pill">
                <i class="bi bi-calendar-check me-1"></i>
                Used: <strong><?= $_gate_used ?> / <?= $_gate_eff_limit ?></strong>
            </div>
            <div class="badge-pill">
                <i class="bi bi-hourglass-split me-1"></i>
                Remaining: <strong><?= $_gate_remaining ?></strong>
            </div>
        </div>

        <!-- Steps -->
        <div class="mb-4">
            <p class="fw-semibold mb-2 text-start" style="color:#333;font-size:.88rem;">
                What to do:
            </p>
            <div class="step-row">
                <div class="step-num">1</div>
                <div class="small text-muted">
                    Go to the <strong>lab administrator</strong> at the front desk.
                </div>
            </div>
            <div class="step-row">
                <div class="step-num">2</div>
                <div class="small text-muted">
                    Give them your <strong>Student ID</strong> and tell them your purpose and which PC you'll use.
                </div>
            </div>
            <div class="step-row">
                <div class="step-num">3</div>
                <div class="small text-muted">
                    Once the admin registers you, <strong>this page will automatically refresh</strong> and unlock your dashboard.
                </div>
            </div>
        </div>

        <button onclick="location.reload()" class="btn w-100 py-2 fw-bold"
                style="background:#5a3d82;color:#fff;border-radius:10px;">
            <i class="bi bi-arrow-clockwise me-2"></i>Check Now
        </button>

        <div class="refresh-bar">
            <div class="refresh-fill"></div>
        </div>
        <p class="text-muted mt-1" style="font-size:0.73rem;">
            Auto-refreshing every 30 seconds…
        </p>

    </div>
</div>

<footer style="text-align:center;padding:1rem;font-size:.8rem;color:#888;">
    &copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System
</footer>

<script>
// Auto-refresh every 30 seconds to check if admin has started the session
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
<?php
    exit(); // Stop dashboard.php from rendering anything else
}
// ── If we reach here, student has an active session → dashboard renders normally ──