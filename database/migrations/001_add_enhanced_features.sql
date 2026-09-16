-- Author: Zeday @join.co.id
-- ============================================
-- Migration: Enhanced Features for PAJAK System
-- Version: 1.1.0
-- Date: 2025-01-XX
-- Description: Add support for audit logging, soft delete, backups, and user preferences
-- ============================================

USE db_pajak_kendaraan;

-- ============================================
-- STEP 1: Add columns to wajib_pajak table
-- ============================================

-- Add user tracking columns
ALTER TABLE wajib_pajak
ADD COLUMN created_by INT NULL COMMENT 'User ID yang membuat record' AFTER tanggal_input,
ADD COLUMN updated_by INT NULL COMMENT 'User ID yang terakhir update record' AFTER created_by;

-- Add soft delete columns
ALTER TABLE wajib_pajak
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp soft delete' AFTER updated_by,
ADD COLUMN deleted_by INT NULL COMMENT 'User ID yang menghapus record' AFTER deleted_at;

-- Add status column
ALTER TABLE wajib_pajak
ADD COLUMN status ENUM('active', 'inactive', 'pending', 'verified') DEFAULT 'active' COMMENT 'Status record' AFTER deleted_by;

-- Add indexes for new columns
ALTER TABLE wajib_pajak
ADD INDEX idx_created_by (created_by),
ADD INDEX idx_updated_by (updated_by),
ADD INDEX idx_deleted_at (deleted_at),
ADD INDEX idx_deleted_by (deleted_by),
ADD INDEX idx_status (status);

-- ============================================
-- STEP 2: Add foreign keys to wajib_pajak
-- ============================================

ALTER TABLE wajib_pajak
ADD CONSTRAINT fk_wajib_pajak_created_by
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT fk_wajib_pajak_updated_by
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
ADD CONSTRAINT fk_wajib_pajak_deleted_by
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- STEP 3: Enhance audit_log table
-- ============================================

ALTER TABLE audit_log
ADD COLUMN description TEXT NULL COMMENT 'Deskripsi aksi yang dilakukan' AFTER new_values,
ADD COLUMN request_data TEXT NULL COMMENT 'Data request (JSON format)' AFTER description,
ADD COLUMN response_data TEXT NULL COMMENT 'Data response (JSON format)' AFTER request_data,
ADD COLUMN execution_time DECIMAL(10,4) NULL COMMENT 'Waktu eksekusi (seconds)' AFTER response_data;

-- Add indexes for better query performance
ALTER TABLE audit_log
ADD INDEX idx_created_at_user (created_at, user_id),
ADD INDEX idx_table_action (table_name, action);

-- ============================================
-- STEP 4: Create new tables
-- ============================================

CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL COMMENT 'Nama file backup',
    filepath VARCHAR(500) NOT NULL COMMENT 'Path lengkap file backup',
    filesize BIGINT NOT NULL COMMENT 'Ukuran file dalam bytes',
    backup_type ENUM('manual', 'automatic', 'scheduled') DEFAULT 'manual' COMMENT 'Jenis backup',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Status backup',
    error_message TEXT NULL COMMENT 'Pesan error jika gagal',
    records_count INT NULL COMMENT 'Jumlah records yang di-backup',
    created_by INT NOT NULL COMMENT 'User ID yang membuat backup',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu backup dibuat',
    completed_at TIMESTAMP NULL COMMENT 'Waktu backup selesai',
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_backup_type (backup_type),
    CONSTRAINT fk_backups_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Backup history and management';

CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User ID',
    theme VARCHAR(20) DEFAULT 'light' COMMENT 'Theme preference (light/dark)',
    items_per_page INT DEFAULT 25 COMMENT 'Items per page for pagination',
    language VARCHAR(10) DEFAULT 'id' COMMENT 'Language preference',
    timezone VARCHAR(50) DEFAULT 'Asia/Makassar' COMMENT 'Timezone preference',
    date_format VARCHAR(20) DEFAULT 'Y-m-d' COMMENT 'Date format preference',
    notifications_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable notifications',
    email_notifications TINYINT(1) DEFAULT 1 COMMENT 'Enable email notifications',
    dashboard_layout JSON NULL COMMENT 'Dashboard layout preferences (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu dibuat',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu update terakhir',
    INDEX idx_user_id (user_id),
    INDEX idx_theme (theme),
    UNIQUE KEY unique_user_pref (user_id),
    CONSTRAINT fk_user_preferences_user_id
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User preferences and settings';

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NULL COMMENT 'User ID',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP address',
    user_agent TEXT NOT NULL COMMENT 'User agent',
    payload TEXT NOT NULL COMMENT 'Session data',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last activity time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Session created time',
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    CONSTRAINT fk_sessions_user_id
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Active user sessions';

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User ID penerima notifikasi',
    title VARCHAR(255) NOT NULL COMMENT 'Judul notifikasi',
    message TEXT NOT NULL COMMENT 'Isi notifikasi',
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info' COMMENT 'Tipe notifikasi',
    action_url VARCHAR(500) NULL COMMENT 'URL untuk action',
    is_read TINYINT(1) DEFAULT 0 COMMENT 'Status sudah dibaca',
    read_at TIMESTAMP NULL COMMENT 'Waktu dibaca',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu dibuat',
    expires_at TIMESTAMP NULL COMMENT 'Waktu kadaluarsa',
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type),
    CONSTRAINT fk_notifications_user_id
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User notifications';

CREATE TABLE IF NOT EXISTS analytics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL COMMENT 'Cache key',
    cache_data JSON NOT NULL COMMENT 'Cached data (JSON)',
    cache_type VARCHAR(50) NOT NULL COMMENT 'Type of cache (daily, monthly, yearly)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Cache created time',
    expires_at TIMESTAMP NOT NULL COMMENT 'Cache expiration time',
    UNIQUE KEY unique_cache_key (cache_key),
    INDEX idx_cache_type (cache_type),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Analytics data cache for performance';

CREATE TABLE IF NOT EXISTS export_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'User ID yang melakukan export',
    export_type ENUM('csv', 'excel', 'pdf') NOT NULL COMMENT 'Tipe export',
    filename VARCHAR(255) NOT NULL COMMENT 'Nama file export',
    filepath VARCHAR(500) NOT NULL COMMENT 'Path file export',
    filesize BIGINT NOT NULL COMMENT 'Ukuran file dalam bytes',
    records_count INT NOT NULL COMMENT 'Jumlah records yang di-export',
    filters JSON NULL COMMENT 'Filter yang digunakan (JSON)',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Status export',
    error_message TEXT NULL COMMENT 'Pesan error jika gagal',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu export dibuat',
    completed_at TIMESTAMP NULL COMMENT 'Waktu export selesai',
    downloaded_at TIMESTAMP NULL COMMENT 'Waktu file di-download',
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_status (status),
    INDEX idx_export_type (export_type),
    CONSTRAINT fk_export_logs_user_id
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Export activity logs';

-- ============================================
-- STEP 5: Update existing views (AFTER table modifications)
-- ============================================

-- Drop existing views
DROP VIEW IF EXISTS v_summary_kecamatan;
DROP VIEW IF EXISTS v_summary_jenis;
DROP VIEW IF EXISTS v_summary_kondisi;

-- Recreate views with soft delete filter
CREATE OR REPLACE VIEW v_summary_kecamatan AS
SELECT
    kecamatan,
    COUNT(*) as total_kendaraan,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 2' THEN 1 ELSE 0 END) as roda_2,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 4' THEN 1 ELSE 0 END) as roda_4,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 3' THEN 1 ELSE 0 END) as roda_3,
    SUM(CASE WHEN jenis_kendaraan LIKE 'Roda 6%' THEN 1 ELSE 0 END) as roda_6_plus,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_count
FROM wajib_pajak
WHERE deleted_at IS NULL
GROUP BY kecamatan
ORDER BY total_kendaraan DESC;

CREATE OR REPLACE VIEW v_summary_jenis AS
SELECT
    jenis_kendaraan,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL), 2) as persentase,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM wajib_pajak
WHERE deleted_at IS NULL
GROUP BY jenis_kendaraan
ORDER BY total DESC;

CREATE OR REPLACE VIEW v_summary_kondisi AS
SELECT
    kondisi,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL), 2) as persentase
FROM wajib_pajak
WHERE deleted_at IS NULL
GROUP BY kondisi
ORDER BY total DESC;

-- Create new view for trend analysis
CREATE OR REPLACE VIEW v_monthly_trends AS
SELECT
    DATE_FORMAT(tanggal_input, '%Y-%m') as month,
    COUNT(*) as total_registrations,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 2' THEN 1 ELSE 0 END) as roda_2_count,
    SUM(CASE WHEN jenis_kendaraan = 'Roda 4' THEN 1 ELSE 0 END) as roda_4_count,
    COUNT(DISTINCT kecamatan) as unique_kecamatan
FROM wajib_pajak
WHERE deleted_at IS NULL
GROUP BY DATE_FORMAT(tanggal_input, '%Y-%m')
ORDER BY month DESC;

-- Create view for trash/deleted items
CREATE OR REPLACE VIEW v_trash_items AS
SELECT
    wp.*,
    u.username as deleted_by_username,
    DATEDIFF(NOW(), wp.deleted_at) as days_in_trash
FROM wajib_pajak wp
LEFT JOIN users u ON wp.deleted_by = u.id
WHERE wp.deleted_at IS NOT NULL
ORDER BY wp.deleted_at DESC;

-- ============================================
-- STEP 6: Create stored procedures
-- ============================================

DELIMITER //

-- Procedure: Get dashboard statistics
DROP PROCEDURE IF EXISTS sp_get_dashboard_stats//
CREATE PROCEDURE sp_get_dashboard_stats()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL) as total_kendaraan,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL AND jenis_kendaraan = 'Roda 2') as total_roda_2,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL AND jenis_kendaraan = 'Roda 4') as total_roda_4,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL AND status = 'active') as total_active,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NOT NULL) as total_deleted,
        (SELECT COUNT(DISTINCT kecamatan) FROM wajib_pajak WHERE deleted_at IS NULL) as total_kecamatan,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL AND DATE(tanggal_input) = CURDATE()) as today_registrations,
        (SELECT COUNT(*) FROM wajib_pajak WHERE deleted_at IS NULL AND MONTH(tanggal_input) = MONTH(CURDATE()) AND YEAR(tanggal_input) = YEAR(CURDATE())) as month_registrations;
END//

-- Procedure: Get growth metrics
DROP PROCEDURE IF EXISTS sp_get_growth_metrics//
CREATE PROCEDURE sp_get_growth_metrics()
BEGIN
    DECLARE current_month_count INT;
    DECLARE previous_month_count INT;
    DECLARE growth_rate DECIMAL(10,2);

    -- Current month count
    SELECT COUNT(*) INTO current_month_count
    FROM wajib_pajak
    WHERE deleted_at IS NULL
    AND MONTH(tanggal_input) = MONTH(CURDATE())
    AND YEAR(tanggal_input) = YEAR(CURDATE());

    -- Previous month count
    SELECT COUNT(*) INTO previous_month_count
    FROM wajib_pajak
    WHERE deleted_at IS NULL
    AND MONTH(tanggal_input) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    AND YEAR(tanggal_input) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH));

    -- Calculate growth rate
    IF previous_month_count > 0 THEN
        SET growth_rate = ((current_month_count - previous_month_count) / previous_month_count) * 100;
    ELSE
        SET growth_rate = 100;
    END IF;

    SELECT
        current_month_count,
        previous_month_count,
        growth_rate,
        CASE
            WHEN growth_rate > 0 THEN 'increase'
            WHEN growth_rate < 0 THEN 'decrease'
            ELSE 'stable'
        END as trend;
END//

-- Procedure: Clean old trash items (30 days)
DROP PROCEDURE IF EXISTS sp_clean_old_trash//
CREATE PROCEDURE sp_clean_old_trash()
BEGIN
    DELETE FROM wajib_pajak
    WHERE deleted_at IS NOT NULL
    AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

    SELECT ROW_COUNT() as deleted_count;
END//

-- Procedure: Clean old analytics cache
DROP PROCEDURE IF EXISTS sp_clean_expired_cache//
CREATE PROCEDURE sp_clean_expired_cache()
BEGIN
    DELETE FROM analytics_cache
    WHERE expires_at < NOW();

    SELECT ROW_COUNT() as cleaned_count;
END//

DELIMITER ;

-- ============================================
-- STEP 7: Insert default user preferences for existing users
-- ============================================

INSERT INTO user_preferences (user_id, theme, items_per_page)
SELECT id, 'light', 25
FROM users
WHERE id NOT IN (SELECT user_id FROM user_preferences)
ON DUPLICATE KEY UPDATE user_id = user_id;

-- ============================================
-- STEP 8: Create triggers for audit logging
-- ============================================

DELIMITER //

-- Trigger: Before delete on wajib_pajak (prevent hard delete)
DROP TRIGGER IF EXISTS tr_wajib_pajak_before_delete//
CREATE TRIGGER tr_wajib_pajak_before_delete
BEFORE DELETE ON wajib_pajak
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Hard delete not allowed. Use soft delete instead.';
END//

DELIMITER ;

-- ============================================
-- STEP 9: Create additional indexes for performance optimization
-- ============================================

-- Composite indexes for common queries
ALTER TABLE wajib_pajak
ADD INDEX idx_status_deleted (status, deleted_at),
ADD INDEX idx_kecamatan_jenis (kecamatan, jenis_kendaraan),
ADD INDEX idx_tanggal_status (tanggal_input, status);

-- Full-text search indexes
ALTER TABLE wajib_pajak
ADD FULLTEXT INDEX ft_search (nama, alamat, desa);

-- ============================================
-- STEP 10: Verify migration
-- ============================================

-- Show all tables
SELECT 'Tables created/modified:' as status;
SHOW TABLES;

-- Show wajib_pajak structure
SELECT 'wajib_pajak table structure:' as status;
DESCRIBE wajib_pajak;

-- Show new tables
SELECT 'New tables:' as status;
SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'db_pajak_kendaraan'
AND TABLE_NAME IN ('backups', 'user_preferences', 'sessions', 'notifications', 'analytics_cache', 'export_logs');

-- Show views
SELECT 'Views:' as status;
SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW';

-- Show stored procedures
SELECT 'Stored Procedures:' as status;
SHOW PROCEDURE STATUS WHERE Db = 'db_pajak_kendaraan';

-- ============================================
-- Migration completed successfully!
-- ============================================

SELECT 'Migration 001_add_enhanced_features.sql completed successfully!' as status,
       NOW() as completed_at;
