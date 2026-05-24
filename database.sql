-- =============================================
-- CCS Sit-In Monitoring System - Database Setup
-- Run this in phpMyAdmin or MySQL CLI
-- =============================================

CREATE DATABASE IF NOT EXISTS sitinmonitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sitinmonitoring;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    middlename VARCHAR(100) DEFAULT '',
    course_level VARCHAR(20),
    course VARCHAR(20),
    email VARCHAR(150) UNIQUE NOT NULL,
    address TEXT,
    password VARCHAR(255) NOT NULL,
    profile_pic VARCHAR(255) DEFAULT NULL,
    role ENUM('student','admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sitins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL,
    purpose VARCHAR(255) DEFAULT NULL,
    lab VARCHAR(50) DEFAULT NULL,
    sit_in_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    sit_out_time DATETIME DEFAULT NULL,
    duration_minutes INT DEFAULT NULL,
    status ENUM('active','completed') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    posted_by VARCHAR(100) DEFAULT 'Admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default admin (password: Admin@123)
INSERT IGNORE INTO students (student_id, lastname, firstname, course_level, course, email, address, password, role)
VALUES ('ADMIN001','Administrator','System','N/A','N/A','admin@ccs.edu.ph','CCS Building',
'$2y$10$TKh8H1.PfunHt21Y.GxO7uGQlHzHUMRrjWFBYBFb8q5LNQ.YFp7Py','admin');

INSERT IGNORE INTO announcements (title, content) VALUES
('Welcome to CCS Sit-In Monitoring','This system tracks student sit-in sessions. Please follow lab rules.'),
('Lab Rules Reminder','No food/drinks inside. Keep the area clean. Log out after your session.');
