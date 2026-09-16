-- Author: Zeday @join.co.id
-- ============================================
-- Rollback Migration: Enhanced Features for PAJAK System
-- Version: 1.1.0
-- Date: 2025-01-XX
-- Description: Rollback all changes from 001_add_enhanced_features.sql
-- WARNING: This will remove all data in new tables!
-- ============================================

USE db_pajak_kendaraan;

-- ============================================
-- STEP 1: Drop triggers
-- ============================================

DROP TRIGGER IF EXISTS tr_wajib_pajak_after_insert;
DROP TRIGGER IF EXISTS tr_wajib_pajak_after_update;
DROP TRIGGER IF EXISTS tr_wajib_pajak_before_delete;

-- ============================================
-- STEP 2: Drop stored procedures
-- ============================================

DROP PROCEDURE IF EXISTS sp_get_dashboard_stats;
DROP PROCEDURE IF EXISTS sp_get_growth_metrics;
DROP PROCEDURE IF EXISTS sp_clean_old_trash;
DROP PROCEDURE IF EXISTS sp_clean_expired_cache;

-- ============================================
-- STEP 3: Drop views
-- ============================================

DROP VIEW IF EXISTS v_summary_kecamatan;
DROP VIEW IF EXISTS v_summary_jenis;
DROP VIEW IF EXISTS v_summary_kondisi;
DROP VIEW IF EXISTS v_monthly_trends;
DROP VIEW IF EXISTS v_trash_items;

-- ============================================
-- STEP 4: Drop new tables
-- ============================================

DROP TABLE IF EXISTS export_logs;
DROP TABLE IF EXISTS analytics_cache;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS backups;

-- ============================================
-- STEP 5: Remove foreign keys from wajib_pajak
-- ============================================

ALTER TABLE wajib_pajak 
DROP FOREIGN KEY IF EXISTS fk_wajib_pajak_created_by,
DROP FOREIGN KEY IF EXISTS fk_wajib_pajak_updated_by,
DROP FOREIGN KEY IF EXISTS fk_wajib_pajak_deleted_by;

-- ============================================
-- STEP 6: Remove indexes from wajib_pajak
-- ============================================

ALTER TABLE wajib_pajak 
DROP INDEX IF EXISTS idx_created_by,
DROP INDEX IF EXISTS idx_updated_by,
DROP INDEX IF EXISTS idx_deleted_at,
DROP INDEX IF EXISTS idx_deleted_by,
DROP INDEX IF EXISTS idx_status,
DROP INDEX IF EXISTS idx_status_deleted,
DROP INDEX IF EXISTS idx_kecamatan_jenis,
DROP INDEX IF EXISTS idx_tanggal_status,
DROP INDEX IF EXISTS ft_search;

-- ============================================
-- STEP 7: Remove columns from wajib_pajak
-- ============================================

ALTER TABLE wajib_pajak 
DROP COLUMN IF EXISTS status,
DROP COLUMN IF EXISTS deleted_by,
DROP COLUMN IF EXISTS deleted_at,
DROP COLUMN IF EXISTS updated_by,
DROP COLUMN IF EXISTS created_by;

-- ============================================
-- STEP 8: Remove columns from audit_log
-- ============================================

ALTER TABLE audit_log 
DROP INDEX IF EXISTS idx_created_at_user,
DROP INDEX IF EXISTS idx_table_action;

ALTER TABLE audit_log 
DROP COLUMN IF EXISTS execution_time,
DROP COLUMN IF EXISTS response_data,
DROP COLUMN IF EXISTS request_data,
DROP COLUMN IF EXISTS description;

-- ============================================
-- STEP 9: Recreate original views
-- ============================================

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

CREATE OR REPLACE VIEW v_summary_jenis AS
SELECT 
    jenis_kendaraan,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak), 2) as persentase
FROM wajib_pajak
GROUP BY jenis_kendaraan
ORDER BY total DESC;

CREATE OR REPLACE VIEW v_summary_kondisi AS
SELECT 
    kondisi,
    COUNT(*) as total,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wajib_pajak), 2) as persentase
FROM wajib_pajak
GROUP BY kondisi
ORDER BY total DESC;

-- ============================================
-- STEP 10: Recreate original stored procedure
-- ============================================

DELIMITER //

DROP PROCEDURE IF EXISTS sp_get_statistics//
CREATE PROCEDURE sp_get_statistics()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM wajib_pajak) as total_kendaraan,
        (SELECT COUNT(*) FROM wajib_pajak WHERE jenis_kendaraan = 'Roda 2') as total_roda_2,
        (SELECT COUNT(*) FROM wajib_pajak WHERE jenis_kendaraan = 'Roda 4') as total_roda_4,
        (SELECT COUNT(DISTINCT kecamatan) FROM wajib_pajak) as total_kecamatan,
        (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users;
END//

DELIMITER ;

-- ============================================
-- Rollback completed
-- ============================================

SELECT 'Rollback completed successfully!' as status,
       'Database restored to previous state' as message,
       NOW() as completed_at;

SELECT 'WARNING: All data in dropped tables has been permanently deleted!' as warning;
