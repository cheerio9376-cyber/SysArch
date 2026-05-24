<?php
session_start();
require 'config/db.php';

require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';
require __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);

    if (!$student_id) {
        $message = "Please enter your Student ID.";
        $type = "danger";
    } else {
        $stmt = $conn->prepare("SELECT email FROM students WHERE student_id=?");
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $otp = rand(100000, 999999);

            $_SESSION['otp'] = $otp;
            $_SESSION['reset_student'] = $student_id;
            $_SESSION['otp_expire'] = time() + 300;

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'yourgmail@gmail.com';
                $mail->Password = 'your_app_password';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('yourgmail@gmail.com', 'CCS Sit-In Monitoring');
                $mail->addAddress($row['email']);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Code';

                $mail->Body = "
                    <div style='font-family:Poppins;text-align:center'>
                        <h2 style='color:#5a3d82;'>CCS Sit-In Monitoring</h2>
                        <p>Your verification code is:</p>
                        <h1 style='color:#d4a017;letter-spacing:3px;'>$otp</h1>
                        <p>This code expires in 5 minutes.</p>
                    </div>
                ";

                $mail->send();

                header("Location: verify_otp.php");
                exit();

            } catch (Exception $e) {
                $message = "Failed to send email.";
                $type = "danger";
            }

        } else {
            $message = "Student ID not found.";
            $type = "danger";
        }
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

<div class="auth-wrapper">
    <div class="auth-card">

        <h4 class="auth-title">Forgot Password</h4>
        <p class="auth-subtitle">Enter your Student ID to receive OTP</p>

        <!-- ERROR -->
        <?php if(!empty($message)): ?>
            <div class="alert alert-danger text-center">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3 input-icon">
                <i class="bi bi-person"></i>
                <input type="text" name="student_id" class="form-control" placeholder="Student ID" required>
            </div>

            <button type="submit" class="btn btn-gold auth-btn">
                Send Verification Code
            </button>

        </form>

        <div class="auth-footer">
            <a href="index.php">Back to Login</a>
        </div>

    </div>
</div>