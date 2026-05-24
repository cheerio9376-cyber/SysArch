<?php
// admin/includes/admin_auth.php
session_start();
require '../config/db.php';
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}
