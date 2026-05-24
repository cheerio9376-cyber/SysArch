<?php
session_start();
session_unset();
session_destroy();
setcookie('student_id', '', time() - 3600, '/');
header('Location: index.php');
exit;
