SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS attendance_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance_db;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  person_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS people (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  department VARCHAR(100) NOT NULL DEFAULT 'Chưa phân lớp',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  person_id INT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  check_in DATETIME NULL,
  check_out DATETIME NULL,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_person_date (person_id, attendance_date),
  KEY idx_attendance_date (attendance_date),
  CONSTRAINT fk_attendance_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO people (code, full_name, department) VALUES
('SV001', 'Nguyễn Minh Anh', 'CNTT K23A'),
('SV002', 'Trần Hoàng Nam', 'CNTT K23A'),
('SV003', 'Lê Thu Hà', 'CNTT K23A'),
('SV004', 'Phạm Đức Long', 'CNTT K23A'),
('SV005', 'Hoàng Tuấn Kiệt', 'CNTT K23A'),
('SV006', 'Vũ Thảo My', 'CNTT K23B'),
('SV007', 'Đặng Quang Huy', 'CNTT K23B'),
('SV008', 'Bùi Ngọc Ánh', 'CNTT K23B'),
('SV009', 'Đỗ Gia Bảo', 'CNTT K23B'),
('SV010', 'Ngô Phương Linh', 'CNTT K23B'),
('SV011', 'Phan Văn Đạt', 'CNTT K23C'),
('SV012', 'Dương Thị Mai', 'CNTT K23C'),
('SV013', 'Hồ Tiến Dũng', 'CNTT K23C'),
('SV014', 'Lý Bảo Trâm', 'CNTT K23C'),
('SV015', 'Trịnh Công Minh', 'CNTT K23C');