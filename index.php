<?php
session_start();
require 'config/db.php';

// Redirect logged-in users straight to their dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit();
}

// ── Leaderboard top 6 ─────────────────────────────────────────────────────
$leaders   = [];
$top_score = 1;
$lb_sql = "
    SELECT
        s.student_id, s.firstname, s.lastname, s.course, s.course_level,
        COALESCE(SUM(rp.points), 0)                              AS total_points,
        COALESCE(SUM(si.duration_minutes), 0)                   AS total_minutes,
        COUNT(CASE WHEN si.status = 'completed' THEN 1 END)     AS completed_tasks
    FROM students s
    LEFT JOIN reward_points rp ON rp.student_id = s.student_id
    LEFT JOIN sitins si        ON si.student_id  = s.student_id
    WHERE s.role = 'student'
    GROUP BY s.student_id
    HAVING total_points > 0 OR total_minutes > 0 OR completed_tasks > 0
    LIMIT 20
";
$lb_result = $conn->query($lb_sql);
$raw = $lb_result ? $lb_result->fetch_all(MYSQLI_ASSOC) : [];
if (!empty($raw)) {
    $max_pts       = max(1, max(array_column($raw, 'total_points')));
    $max_minutes   = max(1, max(array_column($raw, 'total_minutes')));
    $max_completed = max(1, max(array_column($raw, 'completed_tasks')));
    foreach ($raw as &$r) {
        $r['score'] = round(
            ($r['total_points']    / $max_pts)       * 100 * 0.60 +
            ($r['total_minutes']   / $max_minutes)   * 100 * 0.20 +
            ($r['completed_tasks'] / $max_completed) * 100 * 0.20, 1
        );
        $r['total_hours'] = round($r['total_minutes'] / 60, 1);
    }
    unset($r);
    usort($raw, fn($a, $b) => $b['score'] <=> $a['score'] ?: $b['total_points'] <=> $a['total_points']);
    $leaders   = array_slice($raw, 0, 6);
    $top_score = $leaders[0]['score'] ?? 1;
}

// ── Quick stats ───────────────────────────────────────────────────────────
$total_students = $conn->query("SELECT COUNT(*) FROM students WHERE role='student'")->fetch_row()[0] ?? 0;
$total_sessions = $conn->query("SELECT COUNT(*) FROM sitins")->fetch_row()[0] ?? 0;
$total_hours    = round(($conn->query("SELECT COALESCE(SUM(duration_minutes),0) FROM sitins")->fetch_row()[0] ?? 0) / 60);
$total_points   = $conn->query("SELECT COALESCE(SUM(points),0) FROM reward_points WHERE points > 0")->fetch_row()[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-In Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* ── Root Tokens (mirrors index.php palette) ── */
        :root {
            --purple-deep:   #5a3d82;
            --purple-mid:    #7a5aaa;
            --purple-soft:   #9b7ec8;
            --purple-light:  #ede9f7;
            --purple-faint:  #f5f2fc;
            --gold:          #c8961a;
            --gold-light:    #f0c842;
            --gold-faint:    #fdf6e3;
            --white:         #ffffff;
            --grey-text:     #6b6b8a;
            --border:        #e2dcf5;
            --text-dark:     #1a1035;
            --navbar-h:      68px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--white);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* ── Navbar — identical structure & height to index.php ── */
        /* Override Bootstrap navbar so both pages are pixel-perfect the same */
        .navbar {
            font-family: 'Poppins', sans-serif !important;
        }
        .lp-nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            list-style: none;
            margin: 0; padding: 0;
        }
        .lp-nav-links a {
            font-size: 13.5px;
            font-weight: 500;
            color: rgba(255,255,255,.85) !important;
            text-decoration: none;
            transition: color .2s;
        }
        .lp-nav-links a:hover { color: #fff !important; }

        /* Mobile nav — dark purple, appears below the navbar */
        .lp-mobile-nav {
            display: none;
            flex-direction: column;
            background: var(--purple-deep);
            border-top: 1px solid rgba(255,255,255,.12);
            padding: 8px 0 16px;
            position: fixed;
            top: 56px; left: 0; right: 0;
            z-index: 999;
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
        }
        .lp-mobile-nav.open { display: flex; }
        .lp-mobile-nav a {
            padding: 13px 24px;
            font-size: 14px;
            font-weight: 500;
            color: rgba(255,255,255,.85);
            text-decoration: none;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .lp-mobile-nav a:hover { color: #fff; background: rgba(255,255,255,.05); }
        .lp-mobile-nav-actions {
            display: flex; gap: 10px;
            padding: 14px 24px 0;
        }
        @media (max-width: 991px) {
            .lp-nav-links { display: none !important; }
        }

        /* ── Hero ── */
        .lp-hero {
            padding: calc(56px + 60px) 0 72px; /* 56px = Bootstrap navbar height */
            position: relative;
            overflow: hidden;
            background: var(--white);
        }
        .lp-hero::before {
            content: '';
            position: absolute;
            top: -80px; right: -160px;
            width: 640px; height: 640px;
            background: radial-gradient(circle, rgba(90,61,130,.07) 0%, transparent 68%);
            pointer-events: none;
        }
        .lp-hero::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -80px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(200,150,26,.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .lp-hero-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center;
            gap: 60px;
        }
        .lp-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--gold-faint);
            border: 1px solid #e8c96a;
            border-radius: 6px;
            padding: 5px 14px;
            font-size: 10.5px;
            font-weight: 700;
            color: var(--gold);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 20px;
        }
        .lp-hero-badge-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--gold);
        }
        .lp-hero-title {
            font-size: 50px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--purple-deep);
            margin-bottom: 20px;
            letter-spacing: -1.5px;
        }
        .lp-hero-title span {
            color: var(--purple-soft);
            display: block;
        }
        .lp-hero-desc {
            font-size: 15px;
            font-weight: 400;
            color: var(--grey-text);
            line-height: 1.8;
            margin-bottom: 36px;
            max-width: 430px;
        }
        .lp-hero-cta { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-hero-primary {
            background: var(--purple-deep);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            padding: 13px 28px;
            border-radius: 9px;
            border: none;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-hero-primary:hover {
            background: var(--purple-mid);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(90,61,130,.28);
        }
        .btn-hero-ghost {
            background: transparent;
            color: var(--purple-deep);
            font-family: 'Poppins', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            padding: 12px 26px;
            border-radius: 9px;
            border: 2px solid var(--border);
            text-decoration: none;
            transition: all .2s;
        }
        .btn-hero-ghost:hover {
            border-color: var(--purple-soft);
            color: var(--purple-deep);
            background: var(--purple-faint);
        }

        /* Dashboard mockup */
        .lp-mockup {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(90,61,130,.13), 0 4px 14px rgba(90,61,130,.06);
            overflow: hidden;
        }
        .lp-mock-topbar {
            background: var(--purple-deep);
            padding: 13px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .lp-mock-topbar-title {
            color: rgba(255,255,255,.9);
            font-size: 12px;
            font-weight: 600;
        }
        .lp-mock-topbar-date {
            color: rgba(255,255,255,.5);
            font-size: 11px;
        }
        .lp-mock-body { padding: 20px; }
        .lp-mock-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 18px;
        }
        .lp-mock-stat {
            background: var(--purple-faint);
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 14px 12px;
        }
        .lp-mock-stat-num {
            font-size: 22px;
            font-weight: 700;
            color: var(--purple-deep);
            line-height: 1;
            margin-bottom: 4px;
        }
        .lp-mock-stat-lbl {
            font-size: 10px;
            color: var(--grey-text);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .lp-mock-row-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--grey-text);
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 10px;
        }
        .lp-mock-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid var(--purple-faint);
            font-size: 12px;
        }
        .lp-mock-row:last-child { border-bottom: none; }
        .lp-mock-row-name { font-weight: 600; color: var(--text-dark); }
        .lp-mock-row-meta { color: var(--grey-text); font-size: 11px; }
        .lp-pill {
            font-size: 10px; font-weight: 700;
            padding: 3px 10px; border-radius: 20px;
        }
        .lp-pill-active { background: #dcfce7; color: #166534; }
        .lp-pill-done   { background: var(--gold-faint); color: var(--gold); }

        /* ── Stats band ── */
        .lp-stats-band {
            background: var(--purple-deep);
            padding: 52px 0;
        }
        .lp-stats-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }
        .lp-sb-item {
            text-align: center;
            padding: 10px 20px;
            border-right: 1px solid rgba(255,255,255,.12);
        }
        .lp-sb-item:last-child { border-right: none; }
        .lp-sb-num {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 6px;
            letter-spacing: -1px;
        }
        .lp-sb-lbl {
            font-size: 11.5px;
            color: rgba(255,255,255,.55);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        /* ── Generic section ── */
        .lp-section { padding: 88px 0; }
        .lp-section-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px;
        }
        .lp-kicker {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            color: var(--purple-soft);
            margin-bottom: 10px;
        }
        .lp-heading {
            font-size: 34px;
            font-weight: 800;
            color: var(--purple-deep);
            line-height: 1.15;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
        }
        .lp-sub {
            font-size: 14.5px;
            color: var(--grey-text);
            line-height: 1.75;
            max-width: 500px;
        }

        /* ── Features ── */
        .lp-features-bg { background: var(--purple-faint); }
        .lp-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 52px;
        }
        .lp-feature-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px 24px;
            transition: box-shadow .25s, transform .25s;
        }
        .lp-feature-card:hover {
            box-shadow: 0 12px 36px rgba(90,61,130,.12);
            transform: translateY(-4px);
        }
        .lp-feature-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: var(--purple-faint);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
            font-size: 20px;
            color: var(--purple-deep);
        }
        .lp-feature-title {
            font-size: 14.5px;
            font-weight: 700;
            color: var(--purple-deep);
            margin-bottom: 8px;
        }
        .lp-feature-desc {
            font-size: 13px;
            color: var(--grey-text);
            line-height: 1.7;
        }

        /* ── Leaderboard ── */
        .lp-lb-wrap {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-top: 40px;
        }
        .lp-lb-table { width: 100%; border-collapse: collapse; }
        .lp-lb-table thead tr {
            background: var(--purple-faint);
            border-bottom: 1px solid var(--border);
        }
        .lp-lb-table th {
            padding: 12px 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: var(--grey-text);
            text-align: left;
        }
        .lp-lb-table td {
            padding: 14px 20px;
            border-bottom: 1px solid var(--purple-faint);
            font-size: 13px;
            vertical-align: middle;
        }
        .lp-lb-table tbody tr:last-child td { border-bottom: none; }
        .lp-lb-table tbody tr:hover { background: var(--purple-faint); }
        .lp-rank { font-size: 17px; font-weight: 700; color: var(--border); }
        .lp-rank-1 { color: var(--gold); }
        .lp-rank-2 { color: #9eaab8; }
        .lp-rank-3 { color: #c49464; }
        .lp-init {
            width: 34px; height: 34px;
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #fff;
            background: var(--purple-mid); flex-shrink: 0;
        }
        .lp-init-gold   { background: linear-gradient(135deg, #c8961a, #f0c842); }
        .lp-init-silver { background: linear-gradient(135deg, #7a8a96, #b0bec5); }
        .lp-init-bronze { background: linear-gradient(135deg, #a07040, #c49464); }
        .lp-sname { font-weight: 600; color: var(--text-dark); font-size: 13px; }
        .lp-sid   { font-size: 11px; color: var(--grey-text); }
        .lp-course-tag {
            font-size: 11px; font-weight: 600;
            padding: 3px 10px; border-radius: 5px;
            background: var(--purple-faint); color: var(--purple-mid);
        }
        .lp-score-val { font-weight: 700; font-size: 13px; color: var(--purple-deep); margin-bottom: 4px; }
        .lp-bar-bg { height: 4px; background: var(--border); border-radius: 99px; overflow: hidden; }
        .lp-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--purple-mid), var(--gold)); }
        .lp-empty { text-align: center; padding: 56px 20px; color: var(--grey-text); font-size: 14px; }
        .lp-lb-meta {
            font-size: 12.5px;
            color: var(--grey-text);
            margin-top: 8px;
        }

        /* ── About ── */
        .lp-about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 72px;
            align-items: center;
        }
        .lp-about-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 28px;
        }
        .lp-about-card {
            background: var(--purple-faint);
            border: 1px solid var(--border);
            border-radius: 11px;
            padding: 22px 18px;
        }
        .lp-about-card-title {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--purple-deep);
            margin-bottom: 6px;
        }
        .lp-about-card-desc {
            font-size: 12.5px;
            color: var(--grey-text);
            line-height: 1.6;
        }
        .lp-checks { margin-top: 28px; }
        .lp-check {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-bottom: 14px;
        }
        .lp-check-dot {
            width: 20px; height: 20px; flex-shrink: 0;
            border-radius: 50%;
            background: var(--gold-faint);
            border: 1.5px solid var(--gold-light);
            display: flex; align-items: center; justify-content: center;
            margin-top: 1px;
        }
        .lp-check-dot::after {
            content: '';
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--gold);
        }
        .lp-check-text { font-size: 13.5px; color: #4a4070; line-height: 1.6; }

        /* ── CTA band ── */
        .lp-cta-band {
            background: var(--purple-deep);
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }
        .lp-cta-band::before {
            content: '';
            position: absolute;
            top: -100px; right: -100px;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(200,150,26,.14) 0%, transparent 65%);
            pointer-events: none;
        }
        .lp-cta-inner { max-width: 600px; margin: 0 auto; padding: 0 32px; text-align: center; }
        .lp-cta-heading {
            font-size: 38px;
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
            letter-spacing: -1px;
            margin-bottom: 14px;
        }
        .lp-cta-sub {
            font-size: 14.5px;
            color: rgba(255,255,255,.6);
            line-height: 1.75;
            margin-bottom: 32px;
        }
        .lp-cta-btns { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .btn-cta-gold {
            background: var(--gold);
            color: #fff;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 700;
            padding: 13px 30px;
            border-radius: 9px;
            border: none;
            text-decoration: none;
            transition: all .2s;
        }
        .btn-cta-gold:hover {
            background: var(--gold-light);
            color: var(--text-dark);
            transform: translateY(-2px);
        }
        .btn-cta-outline {
            background: transparent;
            color: rgba(255,255,255,.85);
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 9px;
            border: 2px solid rgba(255,255,255,.25);
            text-decoration: none;
            transition: all .2s;
        }
        .btn-cta-outline:hover {
            border-color: rgba(255,255,255,.55);
            color: #fff;
        }

        /* ── Contact ── */
        .lp-contact-bg { background: var(--purple-faint); }
        .lp-contact-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 48px;
        }
        .lp-contact-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px 24px;
            text-align: center;
        }
        .lp-contact-label {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .9px;
            color: var(--purple-soft);
            margin-bottom: 8px;
        }
        .lp-contact-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-dark);
        }

        /* ── Footer ── */
        .lp-footer {
            background: var(--white);
            border-top: 1px solid var(--border);
            padding: 24px 32px;
            text-align: center;
        }
        .lp-footer p { font-size: 12px; color: var(--grey-text); }

        /* ── Responsive ── */
        @media (max-width: 991px) {
            .lp-nav-links { display: none; }
            .lp-hamburger  { display: flex; }
            .lp-hero-inner { grid-template-columns: 1fr; gap: 0; }
            .lp-hero-title { font-size: 36px; }
            .lp-mockup    { display: none; }
            .lp-stats-inner { grid-template-columns: repeat(2, 1fr); }
            .lp-sb-item   { border-right: none; border-bottom: 1px solid rgba(255,255,255,.12); padding: 16px; }
            .lp-features-grid { grid-template-columns: 1fr 1fr; }
            .lp-about-grid { grid-template-columns: 1fr; gap: 40px; }
            .lp-contact-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .lp-features-grid { grid-template-columns: 1fr; }
            .lp-about-cards   { grid-template-columns: 1fr; }
            .lp-hero-title    { font-size: 28px; }
            .lp-heading       { font-size: 26px; }
            .lp-cta-heading   { font-size: 28px; }
        }
    </style>
</head>
<body>

<!-- ══ NAVBAR — same Bootstrap structure as index.php ══ -->
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="landing.php">
            <img src="images/CCSLogo2.png" class="logo me-2" alt="CCS">
            <span class="fw-bold">College of Computer Studies</span>
        </a>
        <!-- Desktop nav links -->
        <ul class="lp-nav-links d-none d-lg-flex mx-auto">
            <li><a href="#home">Home</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#leaderboard">Leaderboard</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <!-- Right actions -->
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="login1.php"    class="btn btn-pastel-yellow btn-sm px-3">Login</a>
            <a href="register.php" class="btn btn-outline-pastel-yellow btn-sm px-3">Register</a>
            <!-- Mobile hamburger -->
            <button class="btn btn-link text-white d-lg-none ms-2 p-0" id="lpHamburger" aria-label="Menu">
                <i class="bi bi-list fs-4"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile dropdown -->
<nav class="lp-mobile-nav" id="lpMobileNav">
    <a href="#home">Home</a>
    <a href="#features">Features</a>
    <a href="#leaderboard">Leaderboard</a>
    <a href="#about">About</a>
    <a href="#contact">Contact</a>
    <div class="lp-mobile-nav-actions">
        <a href="index.php"    class="btn btn-pastel-yellow btn-sm px-3" style="flex:1;text-align:center;">Login</a>
        <a href="register.php" class="btn btn-outline-pastel-yellow btn-sm px-3" style="flex:1;text-align:center;">Register</a>
    </div>
</nav>

<!-- ══ HERO ══ -->
<section class="lp-hero" id="home">
    <div class="lp-hero-inner">
        <div>
            <div class="lp-hero-badge">
                <span class="lp-hero-badge-dot"></span>
                CCS Laboratory Management
            </div>
            <h1 class="lp-hero-title">
                Sit-In Monitoring<br>
                <span>Made Simple.</span>
            </h1>
            <p class="lp-hero-desc">
                A centralized platform for managing laboratory sit-ins, and rewarding student engagement across the College of Computer Studies.
            </p>
            <div class="lp-hero-cta">
                <a href="index.php"      class="btn-hero-primary">Get Started</a>
                <a href="#leaderboard"   class="btn-hero-ghost">View Leaderboard</a>
            </div>
        </div>

        <!-- Dashboard Mockup -->
        <div class="lp-mockup">
            <div class="lp-mock-topbar">
                <span class="lp-mock-topbar-title">Admin Dashboard</span>
                <span class="lp-mock-topbar-date"><?= date('M d, Y') ?></span>
            </div>
            <div class="lp-mock-body">
                <div class="lp-mock-stats">
                    <div class="lp-mock-stat">
                        <div class="lp-mock-stat-num"><?= number_format($total_students) ?></div>
                        <div class="lp-mock-stat-lbl">Students</div>
                    </div>
                    <div class="lp-mock-stat">
                        <div class="lp-mock-stat-num"><?= number_format($total_sessions) ?></div>
                        <div class="lp-mock-stat-lbl">Sessions</div>
                    </div>
                    <div class="lp-mock-stat">
                        <div class="lp-mock-stat-num"><?= number_format($total_hours) ?>h</div>
                        <div class="lp-mock-stat-lbl">Hours</div>
                    </div>
                </div>
                <div class="lp-mock-row-label">Recent Activity</div>
                <?php
                $recent_sql = "SELECT s.firstname, s.lastname, si.status FROM sitins si JOIN students s ON s.student_id = si.student_id ORDER BY si.sit_in_time DESC LIMIT 3";
                $recent_res  = $conn->query($recent_sql);
                $recent_rows = $recent_res ? $recent_res->fetch_all(MYSQLI_ASSOC) : [];
                if (empty($recent_rows)) $recent_rows = [['firstname'=>'—','lastname'=>'','status'=>'active'],['firstname'=>'—','lastname'=>'','status'=>'completed']];
                foreach ($recent_rows as $row):
                    $pill  = $row['status'] === 'active' ? 'lp-pill-active' : 'lp-pill-done';
                    $label = ucfirst($row['status']);
                ?>
                <div class="lp-mock-row">
                    <div>
                        <div class="lp-mock-row-name"><?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?></div>
                        <div class="lp-mock-row-meta">Sit-in session</div>
                    </div>
                    <span class="lp-pill <?= $pill ?>"><?= $label ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ══ STATS BAND ══ -->
<div class="lp-stats-band">
    <div class="lp-stats-inner">
        <?php foreach ([
            [number_format($total_students), 'Active Students'],
            [number_format($total_sessions), 'Sessions Logged'],
            [number_format($total_hours).'h','Hours Tracked'],
            [number_format($total_points),   'Points Awarded'],
        ] as $s): ?>
        <div class="lp-sb-item">
            <div class="lp-sb-num"><?= $s[0] ?></div>
            <div class="lp-sb-lbl"><?= $s[1] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ══ FEATURES ══ -->
<section class="lp-section lp-features-bg" id="features">
    <div class="lp-section-inner">
        <div class="lp-kicker">Features</div>
        <h2 class="lp-heading">Everything You Need to<br>Manage Laboratory Access</h2>
        <p class="lp-sub">A complete monitoring solution designed for university laboratory environments.</p>
        <div class="lp-features-grid">
            <?php foreach ([
                ['bi-activity',            'Real-Time Monitoring',   'Track laboratory sit-ins instantly as students log in and out of sessions across all CCS labs.'],
                ['bi-people',              'Student Management',     'Manage student profiles, enrollment details, and academic records from one unified dashboard.'],
                ['bi-clock-history',       'Attendance Tracking',    'Automated time-in/out logging with precise duration tracking and full session history.'],
                ['bi-calendar3',           'Lab Scheduling',         'Reserve PCs and schedule sit-in sessions across multiple labs with conflict detection.'],
                ['bi-file-earmark-bar-graph','Session Reports',      'Generate comprehensive reports on student usage, lab activity, and compliance metrics.'],
                ['bi-bar-chart-line',      'Student Analytics',      'Visual insights into student performance, engagement trends, and leaderboard scores.'],
            ] as $f): ?>
            <div class="lp-feature-card">
                <div class="lp-feature-icon"><i class="bi <?= $f[0] ?>"></i></div>
                <div class="lp-feature-title"><?= $f[1] ?></div>
                <div class="lp-feature-desc"><?= $f[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ LEADERBOARD ══ -->
<section class="lp-section" id="leaderboard">
    <div class="lp-section-inner">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:8px;">
            <div>
                <div class="lp-kicker">Leaderboard</div>
                <h2 class="lp-heading" style="margin-bottom:6px;">Top Performing Students</h2>
                <p class="lp-lb-meta">Ranked by composite score — Points (60%) · Hours (20%) · Completed Tasks (20%)</p>
            </div>
            <a href="leaderboard_public.php" class="btn-hero-ghost" style="white-space:nowrap;">Full Rankings</a>
        </div>

        <?php if (!empty($leaders)): ?>
        <div class="lp-lb-wrap">
            <table class="lp-lb-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Course</th>
                        <th>Year</th>
                        <th>Points</th>
                        <th>Hours</th>
                        <th>Tasks</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($leaders as $i => $st):
                    $rank      = $i + 1;
                    $initials  = strtoupper(substr($st['firstname'],0,1).substr($st['lastname'],0,1));
                    $score_pct = $top_score > 0 ? round(($st['score'] / $top_score) * 100) : 0;
                    $rk = match($rank){ 1=>'lp-rank-1',2=>'lp-rank-2',3=>'lp-rank-3',default=>'' };
                    $ic = match($rank){ 1=>'lp-init-gold',2=>'lp-init-silver',3=>'lp-init-bronze',default=>'' };
                ?>
                <tr>
                    <td><span class="lp-rank <?= $rk ?>"><?= $rank ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="lp-init <?= $ic ?>"><?= $initials ?></div>
                            <div>
                                <div class="lp-sname"><?= htmlspecialchars($st['firstname'].' '.$st['lastname']) ?></div>
                                <div class="lp-sid"><?= $st['student_id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="lp-course-tag"><?= htmlspecialchars($st['course']) ?></span></td>
                    <td style="color:var(--grey-text);font-size:12.5px;"><?= htmlspecialchars($st['course_level']) ?></td>
                    <td style="font-weight:700;color:var(--purple-deep);"><?= number_format($st['total_points']) ?></td>
                    <td style="font-weight:700;color:var(--purple-deep);"><?= $st['total_hours'] ?>h</td>
                    <td style="font-weight:700;color:var(--purple-deep);"><?= $st['completed_tasks'] ?></td>
                    <td style="min-width:110px;">
                        <div class="lp-score-val"><?= number_format($st['score'],1) ?></div>
                        <div class="lp-bar-bg"><div class="lp-bar-fill" style="width:<?= $score_pct ?>%;"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="lp-lb-wrap"><div class="lp-empty">No ranked students yet.</div></div>
        <?php endif; ?>
    </div>
</section>

<!-- ══ ABOUT ══ -->
<section class="lp-section" style="background:var(--purple-faint);" id="about">
    <div class="lp-section-inner">
        <div class="lp-about-grid">
            <div>
                <div class="lp-kicker">About</div>
                <h2 class="lp-heading">Built for CCS Students &amp; Administrators</h2>
                <p style="font-size:14px;color:var(--grey-text);line-height:1.8;margin-top:10px;">
                    The CCS Sit-In Monitoring System streamlines how the College of Computer Studies manages student laboratory usage. From real-time attendance to reward points and leaderboards, everything is designed to encourage engagement and accountability.
                </p>
                <div class="lp-checks">
                    <?php foreach ([
                        'Streamlined sit-in management across all CCS laboratories',
                        'Gamified points system to encourage active participation',
                        'Comprehensive reporting for academic compliance and oversight',
                        'Role-based access for students and administrators',
                    ] as $c): ?>
                    <div class="lp-check">
                        <div class="lp-check-dot"></div>
                        <span class="lp-check-text"><?= $c ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="lp-about-cards">
                    <?php foreach ([
                        ['Fast &amp; Reliable', 'Real-time updates with minimal latency across all active sessions.'],
                        ['Secure Access',        'Role-based authentication ensures data integrity and privacy.'],
                        ['Academic Focus',       'Workflows designed specifically for university environments.'],
                        ['Growth Insights',      'Track progress, improvement, and engagement over time.'],
                    ] as $c): ?>
                    <div class="lp-about-card">
                        <div class="lp-about-card-title"><?= $c[0] ?></div>
                        <div class="lp-about-card-desc"><?= $c[1] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ CTA ══ -->
<section class="lp-cta-band">
    <div class="lp-cta-inner">
        <h2 class="lp-cta-heading">Ready to Get Started?</h2>
        <p class="lp-cta-sub">Join the CCS Sit-In Monitoring System and stay on top of your laboratory sessions.</p>
        <div class="lp-cta-btns">
            <a href="index.php"    class="btn-cta-gold">Login to Your Account</a>
            <a href="register.php" class="btn-cta-outline">Create an Account</a>
        </div>
    </div>
</section>

<!-- ══ CONTACT ══ -->
<section class="lp-section lp-contact-bg" id="contact">
    <div class="lp-section-inner">
        <div class="lp-kicker">Contact</div>
        <h2 class="lp-heading">Get In Touch</h2>
        <p class="lp-sub">Have questions about the system? Reach out to the CCS admin team.</p>
        <div class="lp-contact-grid">
            <?php foreach ([
                ['bi-envelope', 'Email',    'ccs@university.edu.ph'],
                ['bi-telephone','Phone',    '+63 32 400-0000'],
                ['bi-geo-alt',  'Location', 'CCS Building, Room 524'],
            ] as $c): ?>
            <div class="lp-contact-card">
                <div class="lp-contact-label"><i class="bi <?= $c[0] ?> me-1"></i><?= $c[1] ?></div>
                <div class="lp-contact-value"><?= $c[2] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ FOOTER ══ -->
<footer class="lp-footer">
    <p>&copy; <?= date('Y') ?> College of Computer Studies &mdash; CCS Sit-In Monitoring System</p>
</footer>

<script>
    // Hamburger toggle
    const ham = document.getElementById('lpHamburger');
    const mnav = document.getElementById('lpMobileNav');
    ham.addEventListener('click', () => mnav.classList.toggle('open'));
    mnav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mnav.classList.remove('open')));

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const t = document.querySelector(a.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
        });
    });
</script>
</body>
</html>
