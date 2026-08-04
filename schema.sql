-- HR Management System Database Schema & Initial Data
-- Database Name: hrgogemini

CREATE DATABASE IF NOT EXISTS hrgogemini CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hrgogemini;

-- Drop existing tables if re-initialising (in reverse order of dependency)
DROP TABLE IF EXISTS leave_balances;
DROP TABLE IF EXISTS leave_requests;
DROP TABLE IF EXISTS attendances;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;

-- 1. ตารางแผนก (departments)
CREATE TABLE departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตารางผู้ใช้งาน/พนักงาน (users)
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    emp_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('employee', 'manager', 'admin') DEFAULT 'employee',
    dept_id INT,
    shift_type ENUM('day', 'night') DEFAULT 'day',
    shift_start_time TIME DEFAULT '08:00:00',
    shift_end_time TIME DEFAULT '17:00:00',
    ot_cap_time TIME DEFAULT '20:00:00',
    avatar_url VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางบันทึกเวลาเข้า-ออกงาน (attendances)
CREATE TABLE attendances (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    work_date DATE NOT NULL,
    check_in_time DATETIME NOT NULL,
    check_out_time DATETIME DEFAULT NULL,
    ip_address VARCHAR(45),
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    check_in_photo VARCHAR(255) DEFAULT NULL,
    check_out_photo VARCHAR(255) DEFAULT NULL,
    work_hours DECIMAL(4,2) DEFAULT 0.00,
    ot_hours DECIMAL(4,2) DEFAULT 0.00,
    late_minutes INT DEFAULT 0,
    status ENUM('on_time', 'late', 'absent') DEFAULT 'on_time',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตารางคำขอลางาน (leave_requests)
CREATE TABLE leave_requests (
    leave_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type ENUM('sick', 'personal', 'vacation') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. ตารางตั้งค่าระบบ (system_settings)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value) VALUES
('company_lat', '13.756330'),
('company_lng', '100.501815'),
('max_distance_meters', '300'),
('enable_location_check', '1'),
('enable_ip_check', '0')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Insert Seed Data: แผนก (Departments)
INSERT INTO departments (dept_id, dept_name) VALUES
(1, 'เทคโนโลยีสารสนเทศ (IT)'),
(2, 'ทรัพยากรบุคคล (HR)'),
(3, 'การตลาด (Marketing)'),
(4, 'ฝ่ายปฏิบัติการ (Operations)');

-- Insert Seed Data: ผู้ใช้งาน (Users) - ทุกคนใช้รหัสผ่าน "password123"
-- Password Hash: $2y$10$vU5MIkcO/4iM9xngnjuNAugW75Jy4v2iuAwx6B8fhFm1M0z3A8Vta
INSERT INTO users (user_id, emp_code, name, password_hash, role, dept_id, is_active) VALUES
(1, 'EMP001', 'Admin System (แอดมินระบบ)', '$2y$10$vU5MIkcO/4iM9xngnjuNAugW75Jy4v2iuAwx6B8fhFm1M0z3A8Vta', 'admin', 2, 1),
(2, 'EMP002', 'Manager IT (หัวหน้า IT)', '$2y$10$vU5MIkcO/4iM9xngnjuNAugW75Jy4v2iuAwx6B8fhFm1M0z3A8Vta', 'manager', 1, 1),
(3, 'EMP003', 'Somchai Jaidee (พนักงาน IT)', '$2y$10$vU5MIkcO/4iM9xngnjuNAugW75Jy4v2iuAwx6B8fhFm1M0z3A8Vta', 'employee', 1, 1),
(4, 'EMP004', 'Somsri Deejaew (พนักงาน HR)', '$2y$10$vU5MIkcO/4iM9xngnjuNAugW75Jy4v2iuAwx6B8fhFm1M0z3A8Vta', 'employee', 2, 1);

-- Insert Seed Data: โควตาวันลา (Leave Balances) สำหรับทุกคน
INSERT INTO leave_balances (user_id, leave_type, total_quota, used_days) VALUES
(1, 'sick', 30, 0), (1, 'personal', 6, 0), (1, 'vacation', 10, 0),
(2, 'sick', 30, 0), (2, 'personal', 6, 0), (2, 'vacation', 10, 0),
(3, 'sick', 30, 0), (3, 'personal', 6, 0), (3, 'vacation', 10, 0),
(4, 'sick', 30, 0), (4, 'personal', 6, 0), (4, 'vacation', 10, 0);
