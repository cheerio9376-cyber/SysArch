<?php
require 'includes/admin_auth.php';
$current_page = 'announcements';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $name    = $_SESSION['firstname'].' '.$_SESSION['lastname'];

   if ($action === 'add') {
    if (!$title || !$content) {
        $_SESSION['error'] = "Title and content are required.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare("INSERT INTO announcements (title, content, posted_by) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $title, $content, $name);
            $ins->execute();
            
            if ($ins->affected_rows > 0) {
                $conn->commit();
                $_SESSION['success'] = "Announcement posted successfully!";
            } else {
                $conn->rollback();
                $_SESSION['error'] = "Failed to post announcement.";
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Database error: " . $e->getMessage();
        }
    }
}
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $upd = $conn->prepare("UPDATE announcements SET title=?, content=? WHERE id=?");
        $upd->bind_param("ssi", $title, $content, $id);
        $_SESSION[$upd->execute() ? 'success' : 'error'] = $upd->execute() ? "Updated!" : "Failed.";
        if ($upd->affected_rows >= 0) $_SESSION['success'] = "Announcement updated!";
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $del = $conn->prepare("DELETE FROM announcements WHERE id=?");
        $del->bind_param("i", $id);
        $_SESSION[$del->execute() ? 'success' : 'error'] = "Announcement deleted.";
    }
    header("Location: announcements.php"); exit();
}

$announcements = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements | CCS Admin</title>
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
            <h2>Announcements</h2>
            <small class="text-muted"><?= count($announcements) ?> announcements</small>
        </div>
        <button class="btn btn-gold" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-2"></i>New Announcement
        </button>
    </div>

    <?php if (empty($announcements)): ?>
        <div class="card-ccs p-5 text-center">
            <i class="bi bi-megaphone fs-1 text-muted mb-3 d-block"></i>
            <p class="text-muted">No announcements yet. Create one!</p>
        </div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($announcements as $ann): ?>
            <div class="col-lg-6">
                <div class="card-ccs p-4 h-100">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="fw-bold mb-0" style="color:#5a3d82;"><?= htmlspecialchars($ann['title']) ?></h5>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary py-0 px-2"
                                onclick='openEditAnn(<?= json_encode($ann) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this announcement?');" class="d-inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $ann['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <p class="text-muted mb-3" style="font-size:0.9rem;"><?= nl2br(htmlspecialchars($ann['content'])) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-auto">
                        <small class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($ann['posted_by']) ?></small>
                        <small class="text-muted"><i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($ann['created_at'])) ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div></div>
<footer class="adm-footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies &bull; CCS Sit-In Monitoring System</small>
</footer>

<!-- ADD MODAL -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>New Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-body p-4">
            <div class="mb-3">
                <label class="form-label">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="Announcement title">
            </div>
            <div class="mb-3">
                <label class="form-label">Content <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="5" required placeholder="Write your announcement here..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gold px-4">Post Announcement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editAnnId">
        <div class="modal-body p-4">
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" id="editAnnTitle" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Content</label>
                <textarea name="content" id="editAnnContent" class="form-control" rows="5" required></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-gold px-4"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('show'));
function openEditAnn(a) {
    document.getElementById('editAnnId').value = a.id;
    document.getElementById('editAnnTitle').value = a.title;
    document.getElementById('editAnnContent').value = a.content;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
