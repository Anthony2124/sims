-- Create database and tables for SIMS
CREATE DATABASE IF NOT EXISTS sims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sims;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','teacher','student') NOT NULL,
  is_verified TINYINT(1) DEFAULT 0,
  verify_token VARCHAR(255) DEFAULT NULL,
  verify_expires DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(50) UNIQUE,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  phone VARCHAR(50),
  address TEXT,
  course VARCHAR(100),
  year_level VARCHAR(20),
  birthdate DATE,
  status ENUM('active','inactive') DEFAULT 'active',
  deleted_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
