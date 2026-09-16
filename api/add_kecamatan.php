<?php
// Author: Zeday @join.co.id
/**
 * Add Kecamatan API
 * Creates a new kecamatan record
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can add kecamatan
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Akses ditolak. Hanya administrator yang dapat menambah kecamatan.'
    ]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_add_kecamatan', 30, 60)) {
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
if (empty($data['nama_kecamatan'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Nama kecamatan tidak boleh kosong.'
    ]);
    exit;
}

$nama_kecamatan = trim($data['nama_kecamatan']);
$kode_kecamatan = !empty($data['kode_kecamatan']) ? trim($data['kode_kecamatan']) : null;
$jumlah_penduduk = !empty($data['jumlah_penduduk']) ? intval($data['jumlah_penduduk']) : 0;
$luas_wilayah = !empty($data['luas_wilayah']) ? floatval($data['luas_wilayah']) : null;
$status = in_array($data['status'], ['active', 'inactive']) ? $data['status'] : 'active';

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if kecamatan already exists
    $stmt = $conn->prepare("SELECT id FROM kecamatan WHERE nama_kecamatan = ?");
    $stmt->bind_param("s", $nama_kecamatan);
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

    // Insert new kecamatan
    $stmt = $conn->prepare("
        INSERT INTO kecamatan (
            nama_kecamatan,
            kode_kecamatan,
            jumlah_penduduk,
            luas_wilayah,
            status
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssids",
        $nama_kecamatan,
        $kode_kecamatan,
        $jumlah_penduduk,
        $luas_wilayah,
        $status
    );

    if (!$stmt->execute()) {
        throw new Exception("Gagal menambah kecamatan: " . $stmt->error);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    // Log to audit
    $auditLogger->logInsert(
        'kecamatan',
        $newId,
        [
            'nama_kecamatan' => $nama_kecamatan,
            'kode_kecamatan' => $kode_kecamatan,
            'jumlah_penduduk' => $jumlah_penduduk,
            'luas_wilayah' => $luas_wilayah,
            'status' => $status
        ],
        "Kecamatan baru ditambahkan: {$nama_kecamatan}"
    );

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Kecamatan berhasil ditambahkan.',
        'data' => [
            'id' => $newId,
            'nama_kecamatan' => $nama_kecamatan,
            'kode_kecamatan' => $kode_kecamatan,
            'status' => $status
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();

    // Log error
    error_log("Add kecamatan error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
