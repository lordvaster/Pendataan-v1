-- Author: Zeday @join.co.id
-- ============================================
-- Migration: Add Kecamatan Management
-- Version: 2.0.0
-- Date: 2026-01-XX
-- Description: Add kecamatan table and management functionality
-- ============================================

USE db_pajak_kendaraan;

-- ============================================
-- STEP 1: Create kecamatan table
-- ============================================

CREATE TABLE IF NOT EXISTS kecamatan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kecamatan VARCHAR(100) NOT NULL COMMENT 'Nama kecamatan',
    kode_kecamatan VARCHAR(10) DEFAULT NULL COMMENT 'Kode kecamatan',
    jumlah_penduduk INT DEFAULT 0 COMMENT 'Jumlah penduduk (optional)',
    luas_wilayah DECIMAL(10,2) DEFAULT NULL COMMENT 'Luas wilayah dalam km²',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Status kecamatan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Tanggal dibuat',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Tanggal update',

    -- Indexes
    INDEX idx_nama_kecamatan (nama_kecamatan),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),

    -- Unique constraint
    UNIQUE KEY unique_nama_kecamatan (nama_kecamatan),
    UNIQUE KEY unique_kode_kecamatan (kode_kecamatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master data kecamatan';

-- ============================================
-- STEP 2: Insert existing kecamatan from wajib_pajak
-- ============================================

INSERT IGNORE INTO kecamatan (nama_kecamatan, status)
SELECT DISTINCT kecamatan, 'active' FROM wajib_pajak WHERE kecamatan IS NOT NULL AND kecamatan != '';

-- ============================================
-- STEP 3: Add foreign key to wajib_pajak (optional - for data integrity)
-- Note: This is optional since kecamatan field is still VARCHAR for backward compatibility
-- ============================================

-- ALTER TABLE wajib_pajak
-- ADD COLUMN kecamatan_id INT NULL AFTER kecamatan,
-- ADD CONSTRAINT fk_wajib_pajak_kecamatan_id
--     FOREIGN KEY (kecamatan_id) REFERENCES kecamatan(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- STEP 4: Update views to use kecamatan table
-- ============================================

-- Update v_summary_kecamatan to join with kecamatan table
CREATE OR REPLACE VIEW v_summary_kecamatan AS
SELECT
    COALESCE(k.nama_kecamatan, wp.kecamatan) as kecamatan,
    COUNT(*) as total_kendaraan,
    SUM(CASE WHEN wp.jenis_kendaraan = 'Roda 2' THEN 1 ELSE 0 END) as roda_2,
    SUM(CASE WHEN wp.jenis_kendaraan = 'Roda 4' THEN 1 ELSE 0 END) as roda_4,
    SUM(CASE WHEN wp.jenis_kendaraan = 'Roda 3' THEN 1 ELSE 0 END) as roda_3,
    SUM(CASE WHEN wp.jenis_kendaraan LIKE 'Roda 6%' THEN 1 ELSE 0 END) as roda_6_plus,
    k.status as kecamatan_status
FROM wajib_pajak wp
LEFT JOIN kecamatan k ON wp.kecamatan = k.nama_kecamatan
GROUP BY COALESCE(k.nama_kecamatan, wp.kecamatan), k.status
ORDER BY total_kendaraan DESC;

-- ============================================
-- STEP 5: Create stored procedures for kecamatan management
-- ============================================

DELIMITER //

-- Procedure: Get kecamatan list
DROP PROCEDURE IF EXISTS sp_get_kecamatan//
CREATE PROCEDURE sp_get_kecamatan()
BEGIN
    SELECT
        id,
        nama_kecamatan,
        kode_kecamatan,
        jumlah_penduduk,
        luas_wilayah,
        status,
        created_at,
        updated_at
    FROM kecamatan
    ORDER BY nama_kecamatan ASC;
END//

-- Procedure: Get kecamatan statistics
DROP PROCEDURE IF EXISTS sp_get_kecamatan_stats//
CREATE PROCEDURE sp_get_kecamatan_stats()
BEGIN
    SELECT
        k.nama_kecamatan,
        k.status,
        COUNT(wp.id) as total_kendaraan,
        COUNT(DISTINCT wp.desa) as total_desa,
        MAX(wp.tanggal_input) as last_input
    FROM kecamatan k
    LEFT JOIN wajib_pajak wp ON k.nama_kecamatan = wp.kecamatan
    GROUP BY k.id, k.nama_kecamatan, k.status
    ORDER BY total_kendaraan DESC;
END//

DELIMITER ;

-- ============================================
-- STEP 6: Verify migration
-- ============================================

-- Show new table
SELECT 'kecamatan table created:' as status;
DESCRIBE kecamatan;

-- Show data migration
SELECT 'Existing kecamatan migrated:' as status;
SELECT COUNT(*) as total_kecamatan FROM kecamatan;

-- Show procedures
SELECT 'Stored procedures created:' as status;
SHOW PROCEDURE STATUS WHERE Db = 'db_pajak_kendaraan' AND Name LIKE 'sp_get_kecamatan%';

-- ============================================
-- Migration completed successfully!
-- ============================================

SELECT 'Migration 002_add_kecamatan_management.sql completed successfully!' as status,
       NOW() as completed_at;
