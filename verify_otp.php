<?php
session_start();

if (!isset($_SESSION['otp'])) {
    header("Location: forgot_password.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered = $_POST['otp'];

    if (time() > $_SESSION['otp_expire']) {
        $message = "OTP expired.";
    } elseif ($entered == $_SESSION['otp']) {
        header("Location: reset_password.php");
        exit();
    } else {
        $message = "Invalid OTP.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="css/style.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="main-container">
<div class="container">
<div class="row justify-content-center">

<div class="col-md-5">
<div class="login-card p-4 text-center">

<h4 class="fw-bold" style="color:#5a3d82;">Enter OTP</h4>

<?php if ($message): ?>
<div class="alert alert-danger"><?= $message ?></div>
<?php endif; ?>

<form method="POST">
<input type="text" name="otp" maxlength="6"
class="form-control text-center mb-3"
style="font-size:24px; letter-spacing:6px;" placeholder="------" required>

<button class="btn btn-purple w-100">Verify</button>
</form>

<p class="small text-muted mt-3">Check your email for the code</p>

</div>
</div>

</div>
</div>
</div>

</body>
</html>