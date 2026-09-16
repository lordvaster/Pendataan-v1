<?php
// Author: Zeday @join.co.id
/**
 * Tampil Data API — with server-side pagination & search
 * Requires authentication.
 */

require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';

header("Content-Type: application/json; charset=UTF-8");

if (!checkRateLimit('api_tampil_data', 120, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan.']);
    exit;
}

require_once '../includes/db_connect.php';

// Pagination & search params
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = min(9999, max(10, intval($_GET['per_page'] ?? 25)));
$search  = trim($_GET['search'] ?? '');
$offset  = ($page - 1) * $perPage;

// Build WHERE clause
$where  = "deleted_at IS NULL";
$params = [];
$types  = "";

if ($search !== '') {
    $like    = '%' . $search . '%';
    $where  .= " AND (nama LIKE ? OR nik LIKE ? OR no_polisi LIKE ? OR kecamatan LIKE ?)";
    $params  = [$like, $like, $like, $like];
    $types   = "ssss";
}

// Total count
$countSql  = "SELECT COUNT(*) as total FROM wajib_pajak WHERE $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Data query
$dataSql  = "SELECT id, nama, nik, no_hp, alamat, kecamatan, desa,
                     no_polisi, jenis_kendaraan, kondisi,
                     ktp_path, stnk_path, foto_kendaraan_path, tanggal_input
             FROM wajib_pajak
             WHERE $where
             ORDER BY id DESC
             LIMIT ? OFFSET ?";

$dataParams = array_merge($params, [$perPage, $offset]);
$dataTypes  = $types . "ii";

$dataStmt = $conn->prepare($dataSql);
$dataStmt->bind_param($dataTypes, ...$dataParams);
$dataStmt->execute();
$result   = $dataStmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['ktp_path']            = $row['ktp_path'] ?? "";
    $row['stnk_path']           = $row['stnk_path'] ?? "";
    $row['foto_kendaraan_path'] = $row['foto_kendaraan_path'] ?? "";
    $data[] = $row;
}
$dataStmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "data"   => $data,
    "meta"   => [
        "total"       => (int)$total,
        "page"        => $page,
        "per_page"    => $perPage,
        "total_pages" => (int)ceil($total / $perPage),
        "search"      => $search
    ]
]);
