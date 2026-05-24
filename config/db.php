<?php
$host   = $_ENV['MYSQLHOST'];
$dbname = $_ENV['MYSQLDATABASE'];
$user   = $_ENV['MYSQLUSER'];
$pass   = $_ENV['MYSQLPASSWORD'];
$port   = $_ENV['MYSQLPORT'];

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

$conn->set_charset("utf8mb4");

// Semester config
define('SEM_START', '2026-01-01 00:00:00');
define('SEM_END',   '2026-12-31 23:59:59');
define('SEM_LIMIT', 30);
?>