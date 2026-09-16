<?php
// Author: Zeday @join.co.id
/**
 * Soft Delete API
 * Marks records as deleted (sets deleted_at timestamp) without permanently removing them.
 * Deleted records go to Trash and can be restored by administrator.
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can delete
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat menghapus data.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_soft_delete', 30, 60)) {
    http_response_code(429);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit;
}

require_once '../includes/db_connect.php';

$auditLogger = new AuditLogger($conn, $userid);

$data = json_decode(file_get_contents("php://input"), true);

if (!verifyCSRFToken($data['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token keamanan tidak valid.']);
    exit;
}

if (empty($data['id'])) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'ID record tidak boleh kosong.'
    ]);
    exit;
}

$recordId = intval($data['id']);

try {
    $conn->begin_transaction();

    // Get current record
    $stmt = $conn->prepare("SELECT * FROM wajib_pajak WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $recordId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Data tidak ditemukan atau sudah dihapus.'
        ]);
        exit;
    }

    $oldData = $result->fetch_assoc();
    $stmt->close();

    // Soft delete: set deleted_at and deleted_by
    $stmt = $conn->prepare("UPDATE wajib_pajak SET deleted_at = NOW(), deleted_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $userid, $recordId);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus data: " . $stmt->error);
    }

    $stmt->close();

    $auditLogger->logDelete(
        'wajib_pajak',
        $recordId,
        $oldData,
        "Soft delete wajib pajak: {$oldData['nama']} (NIK: {$oldData['nik']})"
    );

    $conn->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Data berhasil dipindahkan ke Trash. Data dapat dipulihkan oleh administrator.',
        'data'    => [
            'id'   => $recordId,
            'nama' => $oldData['nama']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Soft delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
