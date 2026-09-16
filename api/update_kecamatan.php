<?php
// Author: Zeday @join.co.id
/**
 * Update Kecamatan API
 * Updates an existing kecamatan record
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can update kecamatan
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat mengupdate kecamatan.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_update_kecamatan', 30, 60)) {
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
$nama_kecamatan = !empty($data['nama_kecamatan']) ? trim($data['nama_kecamatan']) : '';
$kode_kecamatan = !empty($data['kode_kecamatan']) ? trim($data['kode_kecamatan']) : null;
$jumlah_penduduk = !empty($data['jumlah_penduduk']) ? intval($data['jumlah_penduduk']) : 0;
$luas_wilayah = !empty($data['luas_wilayah']) ? floatval($data['luas_wilayah']) : null;
$status = in_array($data['status'], ['active', 'inactive']) ? $data['status'] : 'active';

if (empty($nama_kecamatan)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Nama kecamatan tidak boleh kosong.'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Get current data for audit log
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

    $oldData = $result->fetch_assoc();
    $stmt->close();

    // Check if new name conflicts with existing kecamatan (excluding current one)
    if ($nama_kecamatan !== $oldData['nama_kecamatan']) {
        $stmt = $conn->prepare("SELECT id FROM kecamatan WHERE nama_kecamatan = ? AND id != ?");
        $stmt->bind_param("si", $nama_kecamatan, $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $conn->rollback();
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'Kecamatan dengan nama tersebut sudah ada.'
            ]);
            exit;
        }
        $stmt->close();
    }

    // Update kecamatan
    $stmt = $conn->prepare("
        UPDATE kecamatan SET
            nama_kecamatan = ?,
            kode_kecamatan = ?,
            jumlah_penduduk = ?,
            luas_wilayah = ?,
            status = ?
        WHERE id = ?
    ");

    $stmt->bind_param(
        "ssidss",
        $nama_kecamatan,
        $kode_kecamatan,
        $jumlah_penduduk,
        $luas_wilayah,
        $status,
        $id
    );

    if (!$stmt->execute()) {
        throw new Exception("Gagal mengupdate kecamatan: " . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Tidak ada perubahan atau kecamatan tidak ditemukan.");
    }

    $stmt->close();

    // Log to audit
    $auditLogger->logUpdate(
        'kecamatan',
        $id,
        $oldData,
        [
            'nama_kecamatan' => $nama_kecamatan,
            'kode_kecamatan' => $kode_kecamatan,
            'jumlah_penduduk' => $jumlah_penduduk,
            'luas_wilayah' => $luas_wilayah,
            'status' => $status
        ],
        "Kecamatan diupdate: {$nama_kecamatan}"
    );

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Kecamatan berhasil diupdate.',
        'data' => [
            'id' => $id,
            'nama_kecamatan' => $nama_kecamatan,
            'kode_kecamatan' => $kode_kecamatan,
            'status' => $status
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();

    // Log error
    error_log("Update kecamatan error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
