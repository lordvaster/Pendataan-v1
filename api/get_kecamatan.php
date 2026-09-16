<?php
// Author: Zeday @join.co.id
/**
 * Get Kecamatan API
 * Returns list of all kecamatan
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';

header("Content-Type: application/json; charset=UTF-8");

// Rate limiting
if (!checkRateLimit('api_get_kecamatan', 60, 60)) {
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.'
    ]);
    exit;
}

require_once '../includes/db_connect.php';

try {
    $sql = "SELECT
                id,
                nama_kecamatan,
                kode_kecamatan,
                jumlah_penduduk,
                luas_wilayah,
                status,
                created_at,
                updated_at
            FROM kecamatan
            ORDER BY nama_kecamatan ASC";

    $result = $conn->query($sql);
    $data = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
