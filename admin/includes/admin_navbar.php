<?php
// admin/includes/admin_navbar.php
$firstname = $_SESSION['firstname'] ?? 'Admin';
$lastname  = $_SESSION['lastname']  ?? '';
$current   = $current_page ?? '';

$initials = strtoupper(substr($firstname,0,1).substr($lastname,0,1));

$nav_sections = [
    'MAIN' => [
        ['page'=>'dashboard',      'icon'=>'bi-speedometer2',         'label'=>'Dashboard',        'url'=>'dashboard.php'],
        ['page'=>'students',       'icon'=>'bi-people',               'label'=>'Students',         'url'=>'students.php'],
    ],
    'MANAGEMENT' => [
        ['page'=>'sitin',          'icon'=>'bi-laptop',               'label'=>'Sit-In Management','url'=>'sitin.php'],
        ['page'=>'records',        'icon'=>'bi-journal-text',         'label'=>'Sit-In Records',   'url'=>'records.php'],
        ['page'=>'pc_reservations','icon'=>'bi-pc-display',           'label'=>'PC Reservations',  'url'=>'pc_reservations.php'],
    ],
    'INSIGHTS' => [
        ['page'=>'announcements',  'icon'=>'bi-megaphone',            'label'=>'Announcements',    'url'=>'announcements.php'],
        ['page'=>'analytics',      'icon'=>'bi-graph-up',             'label'=>'Analytics',        'url'=>'analytics.php'],
        ['page'=>'reports',        'icon'=>'bi-file-earmark-bar-graph','label'=>'Reports',         'url'=>'reports.php'],
        ['page'=>'view_feedback',  'icon'=>'bi-chat-square-text',     'label'=>'Feedback',         'url'=>'view_feedback.php'],
        ['page'=>'award_points',   'icon'=>'bi-award',                  'label'=>'Points',           'url'=>'award_points.php'],
        ['page'=>'leaderboard',    'icon'=>'bi-trophy',                 'label'=>'Leaderboard',      'url'=>'leaderboard.php'],
    ],
];
?>

<!-- ====== TOP NAVBAR ====== -->
<nav class="adm-topbar">
    <div class="adm-topbar__inner">
        <!-- Hamburger (mobile) -->
        <button class="adm-topbar__hamburger" id="adminSidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand -->
        <a href="dashboard.php" class="adm-topbar__brand">
            <img src="../images/CCSLogo1.png" class="adm-topbar__logo" alt="CCS">
            <span class="adm-topbar__brand-name">CCS Admin Panel</span>
        </a>

        <!-- Right controls -->
        <div class="adm-topbar__right">
            <span class="adm-topbar__date d-none d-md-inline"><?= date('M d, Y') ?></span>

            <!-- Profile dropdown -->
            <div class="dropdown">
                <button class="adm-topbar__profile dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="adm-topbar__avatar"><?= $initials ?></div>
                    <span class="adm-topbar__username d-none d-md-inline"><?= htmlspecialchars($firstname) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end adm-profile-dropdown shadow">
                    <li class="adm-profile-dropdown__header">
                        <div class="adm-profile-dropdown__avatar"><?= $initials ?></div>
                        <div>
                            <div class="fw-600 small"><?= htmlspecialchars($firstname.' '.$lastname) ?></div>
                            <small class="text-muted">Administrator</small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="../logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- ====== SIDEBAR ====== -->
<aside class="adm-sidebar" id="adminSidebar">

    <!-- Admin mini-card -->
    <div class="adm-sidebar__profile">
        <div class="adm-sidebar__profile-avatar"><?= $initials ?></div>
        <div>
            <div class="adm-sidebar__profile-name"><?= htmlspecialchars($firstname.' '.$lastname) ?></div>
            <div class="adm-sidebar__profile-sub">Administrator</div>
        </div>
    </div>

    <nav class="adm-sidebar__nav">
        <?php foreach ($nav_sections as $section_label => $items): ?>
            <div class="adm-sidebar__section-label"><?= $section_label ?></div>
            <?php foreach ($items as $item): ?>
                <a href="<?= $item['url'] ?>"
                   class="adm-sidebar__link <?= $current === $item['page'] ? 'active' : '' ?>">
                   
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>

<style>
/* ============================================================
   ADMIN NAVBAR + SIDEBAR — matches student layout.css style
   ============================================================ */

:root {
    --adm-purple:       #5a3d82;
    --adm-purple-light: #7b5fb3;
    --adm-purple-soft:  #f4efff;
    --adm-gold:         #d4a017;
    --adm-topbar-h:     64px;
    --adm-sidebar-w:    248px;
}

/* ── Top Navbar ── */
.adm-topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--adm-topbar-h);
    background: var(--adm-purple);
    z-index: 300;
    box-shadow: 0 2px 16px rgba(90,61,130,0.28);
}
.adm-topbar__inner {
    display: flex;
    align-items: center;
    height: 100%;
    padding: 0 18px;
    gap: 12px;
}
.adm-topbar__hamburger {
    display: none;
    background: rgba(255,255,255,0.12);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 1.2rem;
    width: 36px; height: 36px;
    align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s;
    flex-shrink: 0;
}
.adm-topbar__hamburger:hover { background: rgba(255,255,255,0.22); }
@media (max-width: 991px) { .adm-topbar__hamburger { display: flex; } }

.adm-topbar__brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #fff;
    flex-shrink: 0;
}
.adm-topbar__logo {
    width: 36px; height: 36px;
    object-fit: contain;
}
.adm-topbar__brand-name {
    font-size: .92rem;
    font-weight: 600;
    color: #fff;
    display: none;
}
@media (min-width: 640px) { .adm-topbar__brand-name { display: inline; } }

.adm-topbar__right {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 12px;
}
.adm-topbar__date {
    font-size: .78rem;
    color: rgba(255,255,255,.55);
}

/* Profile button */
.adm-topbar__profile {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.12);
    border: none;
    border-radius: 10px;
    padding: 4px 10px 4px 4px;
    color: #fff;
    cursor: pointer;
    transition: background .2s;
}
.adm-topbar__profile:hover,
.adm-topbar__profile.show { background: rgba(255,255,255,0.22); }
.adm-topbar__profile::after { border-color: rgba(255,255,255,.7) transparent transparent; }

.adm-topbar__avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: .82rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
}
.adm-topbar__username {
    font-size: .875rem;
    color: rgba(255,255,255,.9);
    font-weight: 500;
}

/* Profile dropdown */
.adm-profile-dropdown {
    min-width: 220px;
    border-radius: 14px;
    border: 1px solid rgba(90,61,130,.12);
    padding: 8px;
    margin-top: 6px !important;
}
.adm-profile-dropdown__header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 8px 12px;
}
.adm-profile-dropdown__avatar {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: var(--adm-purple);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: .85rem;
    flex-shrink: 0;
}
.adm-profile-dropdown .dropdown-item {
    border-radius: 8px;
    font-size: .875rem;
    padding: 8px 12px;
}
.adm-profile-dropdown .dropdown-item:hover { background: var(--adm-purple-soft); }
.adm-profile-dropdown .dropdown-item.text-danger:hover { background: #fdf2f2; }

/* ── Sidebar ── */
.adm-sidebar {
    position: fixed;
    top: var(--adm-topbar-h);
    left: 0;
    width: var(--adm-sidebar-w);
    height: calc(100vh - var(--adm-topbar-h));
    background: #fff;
    border-right: 1px solid rgba(90,61,130,0.1);
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    z-index: 200;
    transition: transform .3s ease;
}
@media (max-width: 991px) {
    .adm-sidebar { transform: translateX(-100%); }
    .adm-sidebar.open { transform: translateX(0); box-shadow: 4px 0 24px rgba(0,0,0,.15); }
}

/* Sidebar profile card */
.adm-sidebar__profile {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 14px;
    border-bottom: 1px solid rgba(90,61,130,.08);
}
.adm-sidebar__profile-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: var(--adm-purple-soft);
    color: var(--adm-purple);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
    font-size: .9rem;
    border: 2px solid var(--adm-purple);
    flex-shrink: 0;
}
.adm-sidebar__profile-name {
    font-size: .85rem;
    font-weight: 600;
    color: #333;
    line-height: 1.2;
}
.adm-sidebar__profile-sub {
    font-size: .72rem;
    color: #888;
    margin-top: 1px;
}

/* Nav */
.adm-sidebar__nav {
    flex: 1;
    padding: 6px 10px;
}
.adm-sidebar__section-label {
    font-size: .67rem;
    font-weight: 600;
    color: #aaa;
    letter-spacing: .8px;
    text-transform: uppercase;
    padding: 10px 6px 4px;
    margin-top: 4px;
}
.adm-sidebar__link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 10px;
    color: #555;
    font-size: .875rem;
    font-weight: 500;
    text-decoration: none;
    transition: background .15s, color .15s;
    margin-bottom: 2px;
    position: relative;
}
.adm-sidebar__link i { font-size: 1rem; width: 18px; text-align: center; flex-shrink: 0; }
.adm-sidebar__link:hover { background: var(--adm-purple-soft); color: var(--adm-purple); }
.adm-sidebar__link.active {
    background: var(--adm-purple-soft);
    color: var(--adm-purple);
    font-weight: 600;
}
.adm-sidebar__link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 3px;
    background: var(--adm-purple);
    border-radius: 0 3px 3px 0;
    margin-left: -10px;
}

/* Sidebar footer */
.adm-sidebar__footer {
    padding: 10px;
    border-top: 1px solid rgba(90,61,130,.08);
}
.adm-sidebar__logout { color: #e74c3c !important; }
.adm-sidebar__logout:hover { background: #fdf2f2 !important; color: #c0392b !important; }

/* ── Admin wrapper & content ── */
.admin-wrapper {
    padding-top: var(--adm-topbar-h);
    min-height: 100vh;
}
.admin-content {
    margin-left: var(--adm-sidebar-w);
    padding: 0 30px 30px;
    min-height: calc(100vh - var(--adm-topbar-h));
}
@media (max-width: 991px) {
    .admin-content { margin-left: 0; padding: 20px 16px; }
}

/* ── Page header ── */
.page-header {
    margin-bottom: 22px;
    padding: 18px 0 14px;
    border-bottom: 2px solid #eee6ff;
}
.page-header h2 {
    color: var(--adm-purple);
    font-weight: 700;
    margin: 0 0 2px;
    font-size: 1.35rem;
}

/* ── Footer ── */
.adm-footer {
    background: var(--adm-purple);
    color: rgba(255,255,255,.7);
    text-align: center;
    padding: 14px;
    font-size: .78rem;
    margin-left: var(--adm-sidebar-w);
    margin-top: auto;
}
@media (max-width: 991px) { .adm-footer { margin-left: 0; } }
</style>

<script>
(function () {
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar   = document.getElementById('adminSidebar');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') sidebar.classList.remove('open');
        });
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', e => {
            if (window.innerWidth < 992 &&
                !sidebar.contains(e.target) &&
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
})();
</script>