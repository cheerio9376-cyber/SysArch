<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['reset_student'])) {
    header("Location: index.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if ($password !== $confirm) {
        $message = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE students SET password=? WHERE student_id=?");
        $stmt->bind_param("ss", $hashed, $_SESSION['reset_student']);
        $stmt->execute();

        session_destroy();

        header("Location: index.php?reset=success");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="main-container">
<div class="container">
<div class="row justify-content-center">

<div class="col-md-5">
<div class="login-card p-4">

<h4 class="text-center fw-bold" style="color:#5a3d82;">
<i class="bi bi-shield-lock me-2"></i>Reset Password
</h4>

<?php if ($message): ?>
<div class="alert alert-danger"><?= $message ?></div>
<?php endif; ?>

<form method="POST">
<div class="mb-2">
<input type="password" name="password" id="pass1" class="form-control" placeholder="New Password" required>
</div>

<div class="mb-3">
<input type="password" name="confirm" id="pass2" class="form-control" placeholder="Confirm Password" required>
</div>

<button class="btn btn-gold w-100">Reset Password</button>
</form>

</div>
</div>

</div>
</div>
</div>

</body>
</html>