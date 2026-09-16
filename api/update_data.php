<?php
// Author: Zeday @join.co.id
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/usersession.php';
require_once '../includes/audit_logger.php';

header("Content-Type: application/json; charset=UTF-8");

// Only administrators can update data
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Akses ditolak. Hanya administrator yang dapat mengubah data."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

// Rate limiting
if (!checkRateLimit('api_update_data', 30, 60)) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Terlalu banyak permintaan. Silakan coba lagi nanti."]);
    exit;
}

require_once '../includes/db_connect.php';

function uploadFileSecure($file, $folder) {
    $allowedMime = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    $allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMime)) {
        return ["error" => "Tipe file tidak diizinkan: " . $file['name']];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        return ["error" => "Ekstensi file tidak diizinkan: " . $file['name']];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ["error" => "Ukuran file terlalu besar (maks 5MB)"];
    }

    // Check for PHP code in images
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/jpg'])) {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<\?php/i', $content)) {
            return ["error" => "File mengandung kode berbahaya"];
        }
    }

    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir     = '../uploads/' . $folder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $path = $dir . $newName;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        chmod($path, 0644);
        return ["path" => "uploads/" . $folder . "/" . $newName];
    }
    return ["error" => "Gagal upload file: " . $file['name']];
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Token keamanan tidak valid."]);
    exit;
}

// Sanitize & validate inputs
$id              = intval($_POST['id'] ?? 0);
$nama            = sanitizeInput($_POST['nama'] ?? '');
$nik             = sanitizeInput($_POST['nik'] ?? '');
$no_hp           = sanitizeInput($_POST['no_hp'] ?? '');
$alamat          = sanitizeInput($_POST['alamat'] ?? '');
$kecamatan       = sanitizeInput($_POST['kecamatan'] ?? '');
$desa            = sanitizeInput($_POST['desa'] ?? '');
$no_polisi       = strtoupper(sanitizeInput($_POST['no_polisi'] ?? ''));
$jenis_kendaraan = sanitizeInput($_POST['jenis_kendaraan'] ?? '');
$kondisi         = sanitizeInput($_POST['kondisi'] ?? '');

if (!$id) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ID tidak ditemukan"]);
    exit;
}

// Validate required fields
$errors = [];
if (empty($nama))            $errors[] = "Nama tidak boleh kosong";
if (empty($nik))             $errors[] = "NIK tidak boleh kosong";
elseif (!validateNIK($nik))  $errors[] = "NIK harus 16 digit angka";
if (empty($no_hp))           $errors[] = "Nomor HP tidak boleh kosong";
elseif (!validatePhone($no_hp)) $errors[] = "Format nomor HP tidak valid";
if (empty($alamat))          $errors[] = "Alamat tidak boleh kosong";
if (empty($kecamatan))       $errors[] = "Kecamatan tidak boleh kosong";
if (empty($desa))            $errors[] = "Desa tidak boleh kosong";
if (empty($no_polisi))       $errors[] = "Nomor polisi tidak boleh kosong";
elseif (!validateLicensePlate($no_polisi)) $errors[] = "Format nomor polisi tidak valid";
if (empty($jenis_kendaraan)) $errors[] = "Jenis kendaraan tidak boleh kosong";
if (empty($kondisi))         $errors[] = "Kondisi tidak boleh kosong";

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Validasi gagal", "errors" => $errors]);
    exit;
}

// Fetch existing record
$stmt = $conn->prepare("SELECT * FROM wajib_pajak WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Data tidak ditemukan"]);
    exit;
}
$oldData = $result->fetch_assoc();
$stmt->close();

// Check duplicate no_polisi (exclude current record)
if (!empty($no_polisi)) {
    $checkStmt = $conn->prepare("SELECT id, nama FROM wajib_pajak WHERE no_polisi = ? AND id != ? AND deleted_at IS NULL");
    $checkStmt->bind_param("si", $no_polisi, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        $existing = $checkResult->fetch_assoc();
        http_response_code(409);
        echo json_encode([
            "status"  => "error",
            "message" => "Nomor polisi '{$no_polisi}' sudah terdaftar atas nama '{$existing['nama']}'"
        ]);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();
}

$ktp_path            = $oldData['ktp_path'];
$stnk_path           = $oldData['stnk_path'];
$foto_kendaraan_path = $oldData['foto_kendaraan_path'];

// Handle optional file uploads
$fileFields = [
    'ktp_file'            => ['folder' => 'ktp',         'pathVar' => &$ktp_path],
    'stnk_file'           => ['folder' => 'stnk',        'pathVar' => &$stnk_path],
    'foto_kendaraan_file' => ['folder' => 'kendaraan',   'pathVar' => &$foto_kendaraan_path],
];

foreach ($fileFields as $inputName => $cfg) {
    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
        $res = uploadFileSecure($_FILES[$inputName], $cfg['folder']);
        if (isset($res['error'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $res['error']]);
            exit;
        }
        $cfg['pathVar'] = $res['path'];
    }
}

// Update database
$sql = "UPDATE wajib_pajak SET
            nama=?, nik=?, no_hp=?, alamat=?, kecamatan=?, desa=?, no_polisi=?,
            jenis_kendaraan=?, kondisi=?, ktp_path=?, stnk_path=?, foto_kendaraan_path=?,
            updated_by=?
        WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "ssssssssssssii",
    $nama, $nik, $no_hp, $alamat, $kecamatan, $desa, $no_polisi,
    $jenis_kendaraan, $kondisi, $ktp_path, $stnk_path, $foto_kendaraan_path, $userid, $id
);

if ($stmt->execute()) {
    $newData = compact('nama', 'nik', 'no_hp', 'alamat', 'kecamatan', 'desa',
                       'no_polisi', 'jenis_kendaraan', 'kondisi');
    $auditLogger = new AuditLogger($conn, $userid);
    $auditLogger->logUpdate('wajib_pajak', $id, $oldData, $newData);

    echo json_encode(["status" => "success", "message" => "Data berhasil diupdate"]);
} else {
    error_log("Update failed: " . $stmt->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal mengupdate data"]);
}

$stmt->close();
$conn->close();
