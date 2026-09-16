<?php
// Author: Zeday @join.co.id
/**
 * Secure Insert Pajak API
 * Fixed SQL Injection, File Upload Vulnerability, and added validation
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/security.php';
startSecureSession();

// Set JSON response header
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

try {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        logSecurityEvent('csrf_token_invalid', ['ip' => $_SERVER['REMOTE_ADDR']]);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    // Verify reCAPTCHA (enforcement controlled by RECAPTCHA_ENFORCE in .env)
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    if (!verifyRecaptcha($recaptchaResponse)) {
        logSecurityEvent('recaptcha_failed', ['ip' => $_SERVER['REMOTE_ADDR']]);
        if (RECAPTCHA_ENFORCE) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.']);
            exit;
        }
    }

    // Check rate limit
    if (!checkRateLimit('form_submission', RATE_LIMIT_FORM)) {
        logSecurityEvent('rate_limit_exceeded', ['type' => 'form_submission']);
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan. Silakan coba lagi nanti.']);
        exit;
    }

    // Sanitize and validate input data
    $nama = sanitizeInput($_POST['nama'] ?? '');
    $nik = sanitizeInput($_POST['nik'] ?? '');
    $no_hp = sanitizeInput($_POST['no_hp'] ?? '');
    $alamat = sanitizeInput($_POST['alamat'] ?? '');
    $kecamatan = sanitizeInput($_POST['kecamatan'] ?? '');
    $desa = sanitizeInput($_POST['desa'] ?? '');
    $no_polisi = strtoupper(sanitizeInput($_POST['no_polisi'] ?? ''));
    $jenis_kendaraan = sanitizeInput($_POST['jenis_kendaraan'] ?? '');
    $kondisi = sanitizeInput($_POST['kondisi'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($nama)) {
        $errors[] = "Nama tidak boleh kosong";
    }
    
    if (empty($nik)) {
        $errors[] = "NIK tidak boleh kosong";
    } elseif (!validateNIK($nik)) {
        $errors[] = "NIK harus 16 digit angka";
    }
    
    if (empty($no_hp)) {
        $errors[] = "Nomor HP tidak boleh kosong";
    } elseif (!validatePhone($no_hp)) {
        $errors[] = "Format nomor HP tidak valid";
    }
    
    if (empty($alamat)) {
        $errors[] = "Alamat tidak boleh kosong";
    }
    
    if (empty($kecamatan)) {
        $errors[] = "Kecamatan tidak boleh kosong";
    }
    
    if (empty($desa)) {
        $errors[] = "Desa tidak boleh kosong";
    }
    
    if (empty($no_polisi)) {
        $errors[] = "Nomor polisi tidak boleh kosong";
    } elseif (!validateLicensePlate($no_polisi)) {
        $errors[] = "Format nomor polisi tidak valid";
    }
    
    if (empty($jenis_kendaraan)) {
        $errors[] = "Jenis kendaraan tidak boleh kosong";
    }
    
    if (empty($kondisi)) {
        $errors[] = "Kondisi kendaraan tidak boleh kosong";
    }
    
    // Return validation errors first before any DB queries
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validasi gagal', 'errors' => $errors]);
        exit;
    }

    // Check for duplicate no_polisi
    if (!empty($no_polisi)) {
        $checkStmt = $conn->prepare("SELECT id, nama FROM wajib_pajak WHERE no_polisi = ? AND deleted_at IS NULL");
        $checkStmt->bind_param("s", $no_polisi);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $existingRow = $checkResult->fetch_assoc();
            $checkStmt->close();
            http_response_code(409);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nomor polisi sudah terdaftar',
                'errors'  => ["Nomor polisi '{$no_polisi}' sudah terdaftar atas nama '{$existingRow['nama']}'"]
            ]);
            exit;
        }
        $checkStmt->close();
    }

    // Handle file uploads
    $ktp_path = "";
    $stnk_path = "";
    $foto_kendaraan_path = "";
    
    // Upload KTP
    if (isset($_FILES['ktp']) && $_FILES['ktp']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
        $validation = validateFileUpload($_FILES['ktp'], $allowedTypes);
        
        if (!$validation['valid']) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Upload KTP gagal', 'errors' => $validation['errors']]);
            exit;
        }
        
        $secureFilename = generateSecureFilename($_FILES['ktp']['name']);
        $uploadPath = KTP_DIR . $secureFilename;
        
        if (move_uploaded_file($_FILES['ktp']['tmp_name'], $uploadPath)) {
            $ktp_path = "uploads/ktp/" . $secureFilename;
            chmod($uploadPath, 0644); // Set secure permissions
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file KTP']);
            exit;
        }
    }
    
    // Upload STNK
    if (isset($_FILES['stnk']) && $_FILES['stnk']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOC_TYPES);
        $validation = validateFileUpload($_FILES['stnk'], $allowedTypes);
        
        if (!$validation['valid']) {
            // Delete previously uploaded KTP if exists
            if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
                unlink('../' . $ktp_path);
            }
            
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Upload STNK gagal', 'errors' => $validation['errors']]);
            exit;
        }
        
        $secureFilename = generateSecureFilename($_FILES['stnk']['name']);
        $uploadPath = STNK_DIR . $secureFilename;
        
        if (move_uploaded_file($_FILES['stnk']['tmp_name'], $uploadPath)) {
            $stnk_path = "uploads/stnk/" . $secureFilename;
            chmod($uploadPath, 0644);
        } else {
            // Delete previously uploaded files
            if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
                unlink('../' . $ktp_path);
            }
            
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file STNK']);
            exit;
        }
    }
    
    // Upload Foto Kendaraan
    if (isset($_FILES['foto_kendaraan']) && $_FILES['foto_kendaraan']['error'] === UPLOAD_ERR_OK) {
        $validation = validateFileUpload($_FILES['foto_kendaraan'], ALLOWED_IMAGE_TYPES);
        
        if (!$validation['valid']) {
            // Delete previously uploaded files
            if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
                unlink('../' . $ktp_path);
            }
            if (!empty($stnk_path) && file_exists('../' . $stnk_path)) {
                unlink('../' . $stnk_path);
            }
            
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Upload foto kendaraan gagal', 'errors' => $validation['errors']]);
            exit;
        }
        
        $secureFilename = generateSecureFilename($_FILES['foto_kendaraan']['name']);
        $uploadPath = KENDARAAN_DIR . $secureFilename;
        
        if (move_uploaded_file($_FILES['foto_kendaraan']['tmp_name'], $uploadPath)) {
            $foto_kendaraan_path = "uploads/kendaraan/" . $secureFilename;
            chmod($uploadPath, 0644);
        } else {
            // Delete previously uploaded files
            if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
                unlink('../' . $ktp_path);
            }
            if (!empty($stnk_path) && file_exists('../' . $stnk_path)) {
                unlink('../' . $stnk_path);
            }
            
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan foto kendaraan']);
            exit;
        }
    }
    
    // Insert into database using prepared statement (SECURE)
    $stmt = $conn->prepare("INSERT INTO wajib_pajak (nama, nik, no_hp, alamat, kecamatan, desa, no_polisi, jenis_kendaraan, kondisi, ktp_path, stnk_path, foto_kendaraan_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        // Log error
        error_log("Prepare statement failed: " . $conn->error);
        
        // Delete uploaded files
        if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
            unlink('../' . $ktp_path);
        }
        if (!empty($stnk_path) && file_exists('../' . $stnk_path)) {
            unlink('../' . $stnk_path);
        }
        if (!empty($foto_kendaraan_path) && file_exists('../' . $foto_kendaraan_path)) {
            unlink('../' . $foto_kendaraan_path);
        }
        
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem']);
        exit;
    }
    
    $stmt->bind_param("ssssssssssss", $nama, $nik, $no_hp, $alamat, $kecamatan, $desa, $no_polisi, $jenis_kendaraan, $kondisi, $ktp_path, $stnk_path, $foto_kendaraan_path);
    
    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        
        // Log successful submission
        logSecurityEvent('data_inserted', [
            'id' => $insertId,
            'nik' => $nik,
            'no_polisi' => $no_polisi
        ]);
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data berhasil disimpan',
            'data' => ['id' => $insertId]
        ]);
    } else {
        // Log error
        error_log("Execute failed: " . $stmt->error);
        
        // Delete uploaded files
        if (!empty($ktp_path) && file_exists('../' . $ktp_path)) {
            unlink('../' . $ktp_path);
        }
        if (!empty($stnk_path) && file_exists('../' . $stnk_path)) {
            unlink('../' . $stnk_path);
        }
        if (!empty($foto_kendaraan_path) && file_exists('../' . $foto_kendaraan_path)) {
            unlink('../' . $foto_kendaraan_path);
        }
        
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // Log exception
    error_log("Exception in insert_pajak: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem']);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
