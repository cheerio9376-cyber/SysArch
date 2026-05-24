<?php
session_start();
require 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id      = trim($_POST['student_id'] ?? '');
    $lastname        = trim($_POST['lastname'] ?? '');
    $firstname       = trim($_POST['firstname'] ?? '');
    $middlename      = trim($_POST['middlename'] ?? '');
    $course_level    = $_POST['course_level'] ?? '';
    $course          = $_POST['course'] ?? '';
    $email           = trim($_POST['email'] ?? '');
    $address         = trim($_POST['address'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm_password= $_POST['confirm_password'] ?? '';

    if (!$student_id || !$lastname || !$firstname || !$course_level || !$course || !$email || !$address || !$password) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $chk = $conn->prepare("SELECT id FROM students WHERE student_id = ? OR email = ?");
        $chk->bind_param("ss", $student_id, $email);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Student ID or Email is already registered.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare("INSERT INTO students (student_id,lastname,firstname,middlename,course_level,course,email,address,password,role) VALUES (?,?,?,?,?,?,?,?,?,'student')");
            $ins->bind_param("sssssssss", $student_id, $lastname, $firstname, $middlename, $course_level, $course, $email, $address, $hash);
            if ($ins->execute()) {
                $success = "Registration successful! You can now <a href='index.php'>login here</a>.";
                $_POST = [];
            } else {
                $error = "Registration failed. Please try again.";
            }
            $ins->close();
        }
        $chk->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | CCS Sit-In Monitoring</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar navbar-dark fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <img src="images/CCSLogo1.png" class="logo me-2">
            <span class="fw-bold">College of Computer Studies</span>
        </a>
        <a href="index.php" class="btn btn-pastel-yellow btn-sm px-3 ms-auto">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
        </a>
    </div>
</nav>

<div class="container mt-5 pt-5 pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="register-card p-4 p-md-5 shadow">
                <div class="text-center mb-4">
                    <img src="images/CCSLogo1.png" width="64" class="mb-2">
                    <h3 class="fw-bold" style="color:#5a3d82;">Create Account</h3>
                    <p class="text-muted small">Register to access the CCS Sit-In Monitoring System</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?= $success ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">ID Number <span class="text-danger">*</span></label>
                            <input type="text" name="student_id" class="form-control" placeholder="e.g. 2021-00001" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middlename" class="form-control" value="<?= htmlspecialchars($_POST['middlename'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Year Level <span class="text-danger">*</span></label>
                            <select name="course_level" class="form-select" required>
                                <option value="">Select Year</option>
                                <?php foreach (['1st Year','2nd Year','3rd Year','4th Year'] as $yr): ?>
                                    <option <?= ($_POST['course_level'] ?? '') == $yr ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Course <span class="text-danger">*</span></label>
                            <select name="course" class="form-select" required>
                                <option value="">Select Course</option>
                                <?php foreach (['BSIT','BSCS','BSCpE','BSIM','BSEMC'] as $c): ?>
                                    <option <?= ($_POST['course'] ?? '') == $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" required minlength="6">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn login-btn w-100 py-2">
                                <i class="bi bi-person-plus me-2"></i>Create Account
                            </button>
                        </div>
                    </div>
                </form>

                <p class="text-center mt-4 mb-0 small">
                    Already have an account? <a href="index.php" style="color:#5a3d82;font-weight:600;">Sign in here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
