<?php
// config/db.php — Database connection + global constants
// FIXES:
//   1. SEM_START / SEM_END / SEM_LIMIT defined here so all pages can use them
//   2. Added error reporting for easier debugging (disable in production)

$host   = 'localhost';
$dbname = 'sitinmonitoring';
$user   = 'root';       // ← change to your DB username
$pass   = '';           // ← change to your DB password

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    // In production, log this instead of dying with details
    error_log("DB connection failed: " . $conn->connect_error);
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

// ── Semester configuration ────────────────────────────────
// Update these at the start of every semester
define('SEM_START', '2026-01-01 00:00:00');
define('SEM_END',   '2026-12-31 23:59:59');
define('SEM_LIMIT', 30);   // base sit-in sessions per semester