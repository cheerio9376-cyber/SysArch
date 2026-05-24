<?php
session_start();
require 'config/db.php';
if (!isset($_SESSION['student_id'])) { header("Location: index.php"); exit(); }

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
    $file = $_FILES['profile_pic'];
    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = "Invalid file type. Only JPG, PNG, GIF, WEBP allowed.";
    } elseif ($file['size'] > $maxSize) {
        $_SESSION['error'] = "File too large. Max 5MB.";
    } else {
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        $filename = time() . "_" . uniqid() . "." . $ext;
        $target = "uploads/" . $filename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Delete old profile pic
            $old = $conn->prepare("SELECT profile_pic FROM students WHERE student_id = ?");
            $old->bind_param("s", $_SESSION['student_id']);
            $old->execute();
            $old_row = $old->get_result()->fetch_assoc();
            if (!empty($old_row['profile_pic']) && file_exists("uploads/" . $old_row['profile_pic'])) {
                unlink("uploads/" . $old_row['profile_pic']);
            }
            // Update DB
            $upd = $conn->prepare("UPDATE students SET profile_pic = ? WHERE student_id = ?");
            $upd->bind_param("ss", $filename, $_SESSION['student_id']);
            $upd->execute();
            $_SESSION['success'] = "Profile picture updated!";
        } else {
            $_SESSION['error'] = "Upload failed. Please try again.";
        }
    }
}
header("Location: dashboard.php");
exit();
