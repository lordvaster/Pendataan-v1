<?php
// Author: Zeday @join.co.id
/**
 * Permanent Delete API
 * Permanently deletes records from trash (cannot be undone)
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can permanently delete
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat menghapus permanen.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_permanent_delete', 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit;
}

// Get database connection
require_once '../includes/db_connect.php';

// Initialize audit logger
$auditLogger = new AuditLogger($conn, $userid);

// Get request data
$data = json_decode(file_get_contents("php://input"), true);

if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
    exit;
}

// Validate input
if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ID record tidak boleh kosong.'
    ]);
    exit;
}

$recordId = intval($data['id']);

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get deleted record data for audit log
    $stmt = $conn->prepare("SELECT * FROM wajib_pajak WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Data tidak ditemukan di trash.'
        ]);
        exit;
    }
    
    $recordData = $result->fetch_assoc();
    $stmt->close();
    
    // Delete associated files
    $filesToDelete = [
        $recordData['ktp_path'],
        $recordData['stnk_path'],
        $recordData['foto_kendaraan_path']
    ];
    
    foreach ($filesToDelete as $filePath) {
        if (!empty($filePath) && file_exists('../' . $filePath)) {
            @unlink('../' . $filePath);
        }
    }
    
    // Permanently delete the record
    $stmt = $conn->prepare("DELETE FROM wajib_pajak WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->bind_param("i", $recordId);
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus data permanen: " . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Data tidak ditemukan di trash.");
    }
    
    $stmt->close();
    
    // Log to audit
    $auditLogger->log(
        'PERMANENT_DELETE',
        'wajib_pajak',
        $recordId,
        $recordData,
        null,
        "Permanently deleted wajib pajak: {$recordData['nama']} (NIK: {$recordData['nik']})"
    );
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data berhasil dihapus permanen dan tidak dapat dikembalikan.',
        'data' => [
            'id' => $recordId,
            'nama' => $recordData['nama']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Log error
    error_log("Permanent delete error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
