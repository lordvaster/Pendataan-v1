<?php
// Author: Zeday @join.co.id
/**
 * Delete Kecamatan API
 * Deletes a kecamatan record (with validation)
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can delete kecamatan
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat menghapus kecamatan.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_delete_kecamatan', 10, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit;
}

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
        'message' => 'ID kecamatan tidak boleh kosong.'
    ]);
    exit;
}

$id = intval($data['id']);

try {
    // Start transaction
    $conn->begin_transaction();

    // Get kecamatan data for audit log
    $stmt = $conn->prepare("SELECT * FROM kecamatan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Kecamatan tidak ditemukan.'
        ]);
        exit;
    }

    $kecamatanData = $result->fetch_assoc();
    $stmt->close();

    // Check if kecamatan has related wajib_pajak records
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM wajib_pajak WHERE kecamatan = ?");
    $stmt->bind_param("s", $kecamatanData['nama_kecamatan']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $relatedCount = $row['count'];
    $stmt->close();

    if ($relatedCount > 0) {
        $conn->rollback();
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => "Kecamatan tidak dapat dihapus karena masih memiliki {$relatedCount} data wajib pajak terkait. Pindahkan atau hapus data terkait terlebih dahulu."
        ]);
        exit;
    }

    // Delete kecamatan
    $stmt = $conn->prepare("DELETE FROM kecamatan WHERE id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        throw new Exception("Gagal menghapus kecamatan: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Kecamatan tidak ditemukan.");
    }

    $stmt->close();

    // Log to audit
    $auditLogger->log(
        'DELETE',
        'kecamatan',
        $id,
        $kecamatanData,
        null,
        "Kecamatan dihapus: {$kecamatanData['nama_kecamatan']}"
    );

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Kecamatan berhasil dihapus.',
        'data' => [
            'id' => $id,
            'nama_kecamatan' => $kecamatanData['nama_kecamatan']
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();

    // Log error
    error_log("Delete kecamatan error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
