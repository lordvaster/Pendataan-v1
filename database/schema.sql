-- Author: Zeday @join.co.id
-- ============================================
-- Database Schema for Sistem Pendataan Wajib Pajak Kendaraan
-- Kabupaten Barito Timur, Kalimantan Tengah
-- ============================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS db_pajak_kendaraan 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE db_pajak_kendaraan;

-- ============================================
-- Table: wajib_pajak
-- Description: Stores taxpayer and vehicle information
-- ============================================

CREATE TABLE IF NOT EXISTS wajib_pajak (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL COMMENT 'Nama lengkap wajib pajak',
    nik VARCHAR(16) NOT NULL COMMENT 'Nomor Induk Kependudukan (16 digit)',
    no_hp VARCHAR(20) NOT NULL COMMENT 'Nomor HP/telepon',
    alamat TEXT NOT NULL COMMENT 'Alamat lengkap',
    kecamatan VARCHAR(100) NOT NULL COMMENT 'Nama kecamatan',
    desa VARCHAR(100) NOT NULL COMMENT 'Nama desa/kelurahan',
    no_polisi VARCHAR(20) NOT NULL COMMENT 'Nomor polisi kendaraan',
    jenis_kendaraan VARCHAR(50) NOT NULL COMMENT 'Jenis kendaraan (Roda 2, Roda 4, dll)',
    kondisi VARCHAR(100) NOT NULL COMMENT 'Kondisi kendaraan',
    ktp_path VARCHAR(255) DEFAULT NULL COMMENT 'Path file KTP',
    stnk_path VARCHAR(255) DEFAULT NULL COMMENT 'Path file STNK',
    foto_kendaraan_path VARCHAR(255) DEFAULT NULL COMMENT 'Path foto kendaraan',
    tanggal_input TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal input data',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Tanggal update terakhir',
    
    -- Indexes for better performance
    INDEX idx_nik (nik),
    INDEX idx_no_polisi (no_polisi),
    INDEX idx_kecamatan (kecamatan),
    INDEX idx_jenis_kendaraan (jenis_kendaraan),
    INDEX idx_tanggal_input (tanggal_input),
    
    -- Unique constraints
    UNIQUE KEY unique_nik (nik),
    UNIQUE KEY unique_no_polisi (no_polisi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data wajib pajak kendaraan bermotor';

-- ============================================
-- Table: users
-- Description: Stores user accounts for system access
-- ============================================

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL COMMENT 'Username untuk login',
    password VARCHAR(255) NOT NULL COMMENT 'Password (hashed with bcrypt)',
    role ENUM('administrator', 'view_only') DEFAULT 'view_only' COMMENT 'User role',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal pembuatan akun',
    last_login TIMESTAMP NULL DEFAULT NULL COMMENT 'Tanggal login terakhir',
    is_active TINYINT(1) DEFAULT 1 COMMENT 'Status aktif user (1=active, 0=inactive)',
    
    -- Indexes
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    
    -- Unique constraint
    UNIQUE KEY unique_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts';

-- ============================================
-- Table: audit_log (Optional - for tracking changes)
-- Description: Stores audit trail of data changes
-- ============================================

CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID user yang melakukan aksi',
    action VARCHAR(50) NOT NULL COMMENT 'Jenis aksi (INSERT, UPDATE, DELETE)',
    table_name VARCHAR(50) NOT NULL COMMENT 'Nama tabel yang diubah',
    record_id INT NOT NULL COMMENT 'ID record yang diubah',
    old_values TEXT DEFAULT NULL COMMENT 'Nilai lama (JSON format)',
    new_values TEXT DEFAULT NULL COMMENT 'Nilai baru (JSON format)',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address user',
    user_agent TEXT DEFAULT NULL COMMENT 'User agent browser',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu aksi dilakukan',
    
    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_table_name (table_name),
    INDEX idx_record_id (record_id),
    INDEX idx_created_at (created_at),
    
    -- Foreign key
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail log';

-- ============================================
-- Insert Default Admin User
-- Username: admin
-- Password: admin123 (CHANGE THIS IMMEDIATELY!)
-- ============================================

INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrator')
ON DUPLICATE KEY UPDATE username=username;

-- Note: Default password is 'admin123' - CHANGE THIS IMMEDIATELY after first login!
-- To generate new password hash, use: password_hash('your_password', PASSWORD_DEFAULT);

-- ============================================
-- Sample Data for Kecamatan (Optional)
-- ============================================

-- You can insert sample data here if needed
-- Example:
-- INSERT INTO wajib_pajak (nama, nik, no_hp, alamat, kecamatan, desa, no_polisi, jenis_kendaraan, kondisi) VALUES
-- ('John Doe', '1234567890123456', '081234567890', 'Jl. Example No. 1', 'Dusun Timur', 'Tamiang Layang', 'KH 1234 AB', 'Roda 2', 'Baik (Masih bisa digunakan)');

-- ============================================
-- Views (Optional - for reporting)
-- ============================================

-- View: Summary by Kecamatan
CREATE OR REPLACE VIEW v_summary_kecamatan AS
SELECT 
    kecamatan,
    COUNT(*) as total_kendaraan,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 2' THEN 1 ELSE 0 END) as roda_2,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 4' THEN 1 ELSE 0 END) as roda_4,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 3' THEN 1 ELSE 0 END) as roda_3,
    SUM(CASE WHEN jenis_kendaraan LIKE 'Roda 6%' THEN 1 ELSE 0 END) as roda_6_plus
FROM wajib_pajak
GROUP BY kecamatan
ORDER BY total_kendaraan DESC;

-- View: Summary by Jenis Kendaraan
CREATE OR REPLACE VIEW v_summary_jenis AS
SELECT 
    jenis_kendaraan,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak), 2) as persentase
FROM wajib_pajak
GROUP BY jenis_kendaraan
ORDER BY total DESC;

-- View: Summary by Kondisi
CREATE OR REPLACE VIEW v_summary_kondisi AS
SELECT 
    kondisi,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak), 2) as persentase
FROM wajib_pajak
GROUP BY kondisi
ORDER BY total DESC;

-- ============================================
-- Stored Procedures (Optional)
-- ============================================

DELIMITER //

-- Procedure: Get statistics
CREATE PROCEDURE IF NOT EXISTS sp_get_statistics()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM wajib_pajak) as total_kendaraan,
        (SELECT COUNT(*) FROM wajib_pajak WHERE jenis_kendaraan = 'Roda 2') as total_roda_2,
        (SELECT COUNT(*) FROM wajib_pajak WHERE jenis_kendaraan = 'Roda 4') as total_roda_4,
        (SELECT COUNT(DISTINCT kecamatan) FROM wajib_pajak) as total_kecamatan,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users;
END //

DELIMITER ;

-- ============================================
-- Triggers (Optional - for audit logging)
-- ============================================

DELIMITER //

-- Trigger: After insert on wajib_pajak
CREATE TRIGGER IF NOT EXISTS tr_wajib_pajak_after_insert
AFTER INSERT ON wajib_pajak
FOR EACH ROW
BEGIN
    -- You can add audit logging here if needed
    -- INSERT INTO audit_log (user_id, action, table_name, record_id, new_values)
    -- VALUES (?, 'INSERT', 'wajib_pajak', NEW.id, JSON_OBJECT(...));
END //

-- Trigger: After update on wajib_pajak
CREATE TRIGGER IF NOT EXISTS tr_wajib_pajak_after_update
AFTER UPDATE ON wajib_pajak
FOR EACH ROW
BEGIN
    -- You can add audit logging here if needed
    -- INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values)
    -- VALUES (?, 'UPDATE', 'wajib_pajak', NEW.id, JSON_OBJECT(...), JSON_OBJECT(...));
END //

DELIMITER ;

-- ============================================
-- Grants and Permissions
-- ============================================

-- Create application user (optional)
-- CREATE USER IF NOT EXISTS 'pajak_user'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON db_pajak_kendaraan.* TO 'pajak_user'@'localhost';
-- FLUSH PRIVILEGES;

-- ============================================
-- Maintenance Queries
-- ============================================

-- Optimize tables (run periodically)
-- OPTIMIZE TABLE wajib_pajak;
-- OPTIMIZE TABLE users;
-- OPTIMIZE TABLE audit_log;

-- Analyze tables (run periodically)
-- ANALYZE TABLE wajib_pajak;
-- ANALYZE TABLE users;
-- ANALYZE TABLE audit_log;

-- ============================================
-- Backup Command (run from command line)
-- ============================================

-- mysqldump -u root -p db_pajak_kendaraan > backup_$(date +%Y%m%d_%H%M%S).sql

-- ============================================
-- Restore Command (run from command line)
-- ============================================

-- mysql -u root -p db_pajak_kendaraan < backup_file.sql

-- ============================================
-- End of Schema
-- ============================================

-- Show table information
SHOW TABLES;
SELECT 'Database schema created successfully!' as status;
