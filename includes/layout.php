<?php
// includes/layout.php
// Usage: include this AFTER setting $active_page and fetching $student data
// Required vars: $sid, $firstname, $lastname, $course_level, $course, $profile_pic, $active_page
// Optional: $active_sitin (array), $remaining (int), $new_count (int)

$active_page   = $active_page   ?? '';
$new_count     = $new_count     ?? 0;
$remaining     = $remaining     ?? SEM_LIMIT;
$active_sitin  = $active_sitin  ?? null;

$avatar_url = $profile_pic
    ? 'uploads/' . $profile_pic
    : 'https://ui-avatars.com/api/?name=' . urlencode($firstname . '+' . $lastname) . '&background=5a3d82&color=fff&size=80';

$nav_items = [
    ['id'=>'dashboard',   'icon'=>'bi-grid-1x2',         'label'=>'Dashboard',           'url'=>'dashboard.php'],
    ['id'=>'reservation', 'icon'=>'bi-calendar-plus',    'label'=>'Reservation',         'url'=>'reservation.php'],
    ['id'=>'history',     'icon'=>'bi-clock-history',    'label'=>'Sit-In History',      'url'=>'history.php'],
    ['id'=>'rewards',     'icon'=>'bi-trophy',           'label'=>'Rewards & Points',    'url'=>'rewards.php'],
    ['id'=>'rules',       'icon'=>'bi-file-earmark-text','label'=>'Rules & Regulations', 'url'=>'rules.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($active_page) ?> | CCS Sit-In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/layout.css">
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

<!-- ====== TOP NAVBAR ====== -->
<nav class="top-navbar">
    <div class="top-navbar-inner">
        <a href="dashboard.php" class="brand">
            <img src="images/CCSLogo2.png" class="brand-logo" alt="CCS">
            <span class="brand-name">College of Computer Studies</span>
        </a>

        <div class="navbar-right">
            <!-- Announcement Bell -->
            <button class="icon-btn position-relative" data-bs-toggle="modal" data-bs-target="#announcementModal" title="Announcements">
                <i class="bi bi-bell"></i>
                <?php if ($new_count > 0): ?>
                    <span class="notif-dot"><?= $new_count > 9 ? '9+' : $new_count ?></span>
                <?php endif; ?>
            </button>

            <!-- Profile Dropdown -->
            <div class="dropdown">
                <button class="profile-trigger dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="<?= $avatar_url ?>" class="nav-avatar" alt="<?= htmlspecialchars($firstname) ?>">
                    <span class="nav-username d-none d-md-inline"><?= htmlspecialchars($firstname) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end nav-dropdown shadow-sm">
                    <li class="dropdown-header-item">
                        <img src="<?= $avatar_url ?>" class="dropdown-avatar" alt="">
                        <div>
                            <div class="fw-600"><?= htmlspecialchars($firstname . ' ' . $lastname) ?></div>
                            <small class="text-muted"><?= $course_level ?> · <?= $course ?></small>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item" href="edit_profile.php"><i class="bi bi-pencil me-2"></i>Edit Profile</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- ====== SIDEBAR ====== -->
<aside class="sidebar">
    <!-- Student Mini Card -->
    <div class="sidebar-profile">
        <img src="<?= $avatar_url ?>" class="sidebar-avatar" alt="">
        <div class="sidebar-profile-info">
            <div class="sidebar-name"><?= htmlspecialchars($firstname . ' ' . $lastname) ?></div>
            <div class="sidebar-sub"><?= $course_level ?> · <?= $sid ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Main</div>
        <?php foreach (array_slice($nav_items, 0, 2) as $item): ?>
        <a href="<?= $item['url'] ?>" class="sidebar-link <?= $active_page === $item['id'] ? 'active' : '' ?>">
            <span><?= $item['label'] ?></span>
            <?php if ($item['id'] === 'reservation'): ?>
                <span class="sidebar-badge">New</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-section-label">Records</div>
        <?php foreach (array_slice($nav_items, 2, 1) as $item): ?>
        <a href="<?= $item['url'] ?>" class="sidebar-link <?= $active_page === $item['id'] ? 'active' : '' ?>">
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>

        <div class="sidebar-section-label">More</div>
        <?php foreach (array_slice($nav_items, 3) as $item): ?>
        <a href="<?= $item['url'] ?>" class="sidebar-link <?= $active_page === $item['id'] ? 'active' : '' ?>">
            <span><?= $item['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <!-- Active session indicator -->
        <div class="session-status <?= $active_sitin ? 'is-active' : '' ?>" id="sidebarStatus">
            <span class="status-dot"></span>
            <span class="status-text"><?= $active_sitin ? 'Session active' : 'No active session' ?></span>
</aside>

<!-- ====== ANNOUNCEMENT MODAL ====== -->
<?php if (isset($announcements) && is_array($announcements)): ?>
<div class="modal fade" id="announcementModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-megaphone-fill me-2"></i>Announcements</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3">
        <?php if (empty($announcements)): ?>
          <p class="text-muted text-center py-4">No announcements yet.</p>
        <?php else: ?>
          <?php foreach ($announcements as $ann): ?>
            <div class="announcement-card mb-3">
                <h6><?= htmlspecialchars($ann['title']) ?></h6>
                <p><?= htmlspecialchars($ann['content']) ?></p>
                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('M d, Y', strtotime($ann['created_at'])) ?></small>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ====== MAIN CONTENT WRAPPER ====== -->
<main class="main-content">
    <!-- Flash Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mx-4 mt-3">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="page-content">