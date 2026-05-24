<?php
session_start();
require 'config/db.php';

if (isset($_SESSION['student_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $password   = $_POST['password'] ?? '';
    $remember   = isset($_POST['remember']);

    if (!$student_id || !$password) {
        $error = "Please enter your ID and Password.";
    } else {
        $stmt = $conn->prepare("SELECT student_id, password, firstname, lastname, course, course_level, role FROM students WHERE student_id = ?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['student_id']  = $row['student_id'];
                $_SESSION['firstname']   = $row['firstname'];
                $_SESSION['lastname']    = $row['lastname'];
                $_SESSION['course']      = $row['course'];
                $_SESSION['course_level']= $row['course_level'];
                $_SESSION['role']        = $row['role'];

                if ($remember) setcookie("student_id", $row['student_id'], time() + 604800, "/");

                header("Location: " . ($row['role'] === 'admin' ? 'admin/dashboard.php' : 'dashboard.php'));
                exit();
            } else { $error = "Invalid ID or Password."; }
        } else { $error = "Invalid ID or Password."; }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Sit-In Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="images/CCSLogo2.png" class="logo me-2">
            <span class="fw-bold">College of Computer Studies</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a href="#" class="text-white text-decoration-none d-none d-md-inline">Home</a>
            <a href="#" class="text-white text-decoration-none d-none d-md-inline">About</a>
            <a href="#login-section" class="btn btn-pastel-yellow btn-sm px-3">Login</a>
            <a href="register.php" class="btn btn-outline-pastel-yellow btn-sm px-3">Register</a>
        </div>
    </div>
</nav>

<div class="main-container pt-5 mt-3" id="login-section">
    <div class="container">
        <div class="row align-items-center justify-content-center">

            <!-- LEFT HERO -->
            <div class="col-lg-6 mb-5 mb-lg-0 d-none d-lg-flex justify-content-center">
                <div class="hero-content">
                    <div>
                        <img src="images/CCSLogo2.png" class="logo-hero mb-3">
                        <div class="hero-text">
                            <h1 class="fw-bold">CCS Sit-In<br> Monitoring System</h1>
                            <p class="mt-3"> Welcome to the monitoring system for the College of Computer.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOGIN CARD -->
             <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
<div class="alert alert-success">
    Password successfully reset. You can now login.
</div>
<?php endif; ?>

            <div class="col-lg-4 col-md-7 col-sm-10">
                <div class="login-card p-4 p-md-5">
                    <div class="text-center mb-4">
                        <img src="images/CCSLogo1.png" width="64" class="mb-2 d-lg-none">
                        <h4 class="fw-bold" style="color:#5a3d82;">Student Login</h4>
                        <p class="text-muted small">Enter your ID and password to continue</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">ID Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-person" style="color:#5a3d82;"></i></span>
                                <input type="text" name="student_id" class="form-control" placeholder="e.g. 2021-00001" value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-lock" style="color:#5a3d82;"></i></span>
                                <input type="password" name="password" id="passwordField" class="form-control" placeholder="Enter password" required>
                                <button type="button" class="input-group-text bg-white border-start-0" onclick="togglePw()">
                                    <i class="bi bi-eye" id="eyeIcon" style="color:#5a3d82;"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                                <label for="remember" class="form-check-label small">Remember Me</label>
                            </div>
                                 <a href="forgot_password.php" class="small" style="color:#5a3d82;">
                                     Forgot Password?
                    </a>
                             </div>
                        <button type="submit" class="btn login-btn w-100 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </form>

                    <p class="text-center mt-4 mb-0 small">
                        Don't have an account? <a href="register.php" style="color:#5a3d82;font-weight:600;">Register here</a>
                    </p>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="footer">
    <small>&copy; <?= date('Y') ?> College of Computer Studies | CCS Sit-In Monitoring System</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('eyeIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { f.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>
</body>
</html>