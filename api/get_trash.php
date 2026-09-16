<?php
// Author: Zeday @join.co.id
/**
 * Get Trash API
 * Retrieves soft-deleted records
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can view trash
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat melihat trash.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_get_trash', 60, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit;
}

// Get database connection
require_once '../includes/db_connect.php';

try {
    // Get trash items using the view
    $sql = "SELECT 
                id,
                nama,
                nik,
                no_hp,
                alamat,
                kecamatan,
                desa,
                no_polisi,
                jenis_kendaraan,
                kondisi,
                status,
                tanggal_input,
                deleted_at,
                deleted_by_username,
                days_in_trash,
                CASE 
                    WHEN days_in_trash >= 30 THEN 'expired'
                    WHEN days_in_trash >= 25 THEN 'warning'
                    ELSE 'active'
                END as trash_status
            FROM v_trash_items
            ORDER BY deleted_at DESC";
    
    $result = $conn->query($sql);
    
    $trashItems = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $trashItems[] = $row;
        }
    }
    
    // Get statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN DATEDIFF(NOW(), deleted_at) >= 30 THEN 1 ELSE 0 END) as expired_items,
                    SUM(CASE WHEN DATEDIFF(NOW(), deleted_at) >= 25 AND DATEDIFF(NOW(), deleted_at) < 30 THEN 1 ELSE 0 END) as warning_items
                   FROM wajib_pajak 
                   WHERE deleted_at IS NOT NULL";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'data' => $trashItems,
        'statistics' => [
            'total_items' => intval($stats['total_items']),
            'expired_items' => intval($stats['expired_items']),
            'warning_items' => intval($stats['warning_items']),
            'retention_days' => 30
        ],
        'message' => 'Items akan dihapus permanen setelah 30 hari di trash.'
    ]);
    
} catch (Exception $e) {
    error_log("Get trash error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan saat mengambil data trash.'
    ]);
}

$conn->close();
