<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['student_id'])) { header("Location: index.php"); exit(); }

$sid = $_SESSION['student_id'];
$error = ''; $success = '';

// Fetch current data
$stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->bind_param("s", $sid);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname  = trim($_POST['firstname'] ?? '');
    $lastname   = trim($_POST['lastname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $new_pass   = $_POST['new_password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';
    $cur_pass   = $_POST['current_password'] ?? '';

    if (!$firstname || !$lastname || !$email) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check email uniqueness (except self)
        $chk = $conn->prepare("SELECT id FROM students WHERE email = ? AND student_id != ?");
        $chk->bind_param("ss", $email, $sid);
        $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Email is already in use by another account.";
        } else {
            // Handle password change
            if ($new_pass !== '') {
                if (!password_verify($cur_pass, $student['password'])) {
                    $error = "Current password is incorrect.";
                } elseif (strlen($new_pass) < 6) {
                    $error = "New password must be at least 6 characters.";
                } elseif ($new_pass !== $confirm) {
                    $error = "New passwords do not match.";
                } else {
                    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE students SET firstname=?,lastname=?,middlename=?,email=?,address=?,password=? WHERE student_id=?");
                    $upd->bind_param("sssssss", $firstname, $lastname, $middlename, $email, $address, $hash, $sid);
                    if ($upd->execute()) { $success = "Profile and password updated successfully!"; }
                    else { $error = "Update failed."; }
                }
            } else {
                $upd = $conn->prepare("UPDATE students SET firstname=?,lastname=?,middlename=?,email=?,address=? WHERE student_id=?");
                $upd->bind_param("ssssss", $firstname, $lastname, $middlename, $email, $address, $sid);
                if ($upd->execute()) {
                    $_SESSION['firstname'] = $firstname;
                    $_SESSION['lastname']  = $lastname;
                    $success = "Profile updated successfully!";
                } else { $error = "Update failed."; }
            }
            // Re-fetch updated data
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
            $stmt->bind_param("s", $sid);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | CCS Sit-In</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="images/CCSLogo1.png" class="logo me-2">College of Computer Studies
        </a>
        <div class="ms-auto">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container py-4 mt-2">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card-ccs p-4 p-md-5">
                <div class="text-center mb-4">
                    <!-- Profile Pic -->
                    <label for="profileInput" style="cursor:pointer;" title="Click to change photo">
                        <img src="<?= $student['profile_pic'] ? 'uploads/'.$student['profile_pic'] : 'https://ui-avatars.com/api/?name='.urlencode($student['firstname'].'+'.$student['lastname']).'&background=5a3d82&color=fff&size=100' ?>"
                             class="profile-avatar mb-2" id="previewImg">
                        <div class="small text-muted mt-1"><i class="bi bi-camera me-1"></i>Change Photo</div>
                    </label>
                    <form id="uploadForm" action="upload_profile.php" method="POST" enctype="multipart/form-data">
                        <input type="file" name="profile_pic" id="profileInput" hidden accept="image/*">
                    </form>
                    <h5 class="mt-2 fw-bold" style="color:#5a3d82;"><?= htmlspecialchars($student['firstname'].' '.$student['lastname']) ?></h5>
                    <small class="text-muted"><?= $student['student_id'] ?> &bull; <?= $student['course_level'] ?> <?= $student['course'] ?></small>
                </div>

                <?php if ($error): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

                <form method="POST">
                    <h6 class="fw-bold mb-3 pb-2 border-bottom" style="color:#5a3d82;"><i class="bi bi-person me-2"></i>Personal Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($student['lastname']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($student['firstname']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middlename" class="form-control" value="<?= htmlspecialchars($student['middlename'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($student['address'] ?? '') ?>">
                        </div>
                    </div>

                    <h6 class="fw-bold mb-3 pb-2 border-bottom" style="color:#5a3d82;"><i class="bi bi-shield-lock me-2"></i>Change Password <small class="text-muted fw-normal">(leave blank to keep current)</small></h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Min. 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-gold flex-grow-1 py-2">
                            <i class="bi bi-check-lg me-2"></i>Save Changes
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary py-2 px-4">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="footer"><small>&copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System</small></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('profileInput').addEventListener('change', function() {
    if (this.files[0]) {
        document.getElementById('previewImg').src = URL.createObjectURL(this.files[0]);
        document.getElementById('uploadForm').submit();
    }
});
</script>
</body>
</html>
