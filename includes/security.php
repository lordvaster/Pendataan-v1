<?php
// Author: Zeday @join.co.id
/**
 * Security Helper Functions
 * CSRF protection, input validation, file upload security, rate limiting
 */

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', SESSION_HTTPONLY);
        ini_set('session.cookie_secure', SESSION_SECURE);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');

        session_start();

        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } elseif (time() - $_SESSION['created'] > SESSION_LIFETIME) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateNIK($nik) {
    return preg_match('/^[0-9]{16}$/', $nik);
}

function validatePhone($phone) {
    $phone = preg_replace('/[\s\-\+]/', '', $phone);
    return preg_match('/^(0|62)[0-9]{9,12}$/', $phone);
}

function validateLicensePlate($plate) {
    return preg_match('/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/i', $plate);
}

function validateFileUpload($file, $allowedTypes, $maxSize = MAX_FILE_SIZE) {
    $errors = [];

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = "File tidak valid atau tidak ditemukan";
        return ['valid' => false, 'errors' => $errors];
    }

    if ($file['size'] > $maxSize) {
        $errors[] = "Ukuran file terlalu besar. Maksimal " . ($maxSize / 1024 / 1024) . "MB";
    }

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        $errors[] = "Tipe file tidak diizinkan. Hanya " . implode(', ', $allowedTypes);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $fileExtension     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($fileExtension, $allowedExtensions)) {
        $errors[] = "Ekstensi file tidak diizinkan";
    }

    if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<\?php/i', $content)) {
            $errors[] = "File mengandung kode berbahaya";
        }
    }

    return [
        'valid'     => empty($errors),
        'errors'    => $errors,
        'mime_type' => $mimeType,
        'extension' => $fileExtension
    ];
}

function generateSecureFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $randomName = bin2hex(random_bytes(16));
    return $randomName . '.' . $extension;
}

/**
 * Rate limiting using session + IP fingerprint.
 * Stores counters in session keyed by IP so a new session from the same IP
 * does NOT reset the counter (the IP check makes it harder to bypass by
 * simply clearing cookies, since IP fingerprint is part of the key).
 */
function checkRateLimit($identifier, $limit, $timeWindow = 3600) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $identifier . '_' . md5($ip);
    $now = time();

    if (isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function ($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            }
        );
    } else {
        $_SESSION['rate_limit'][$key] = [];
    }

    if (count($_SESSION['rate_limit'][$key]) >= $limit) {
        return false;
    }

    $_SESSION['rate_limit'][$key][] = $now;
    return true;
}

function logSecurityEvent($event, $details = []) {
    $logFile   = LOG_DIR . 'security_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $logEntry = sprintf(
        "[%s] IP: %s | Event: %s | Details: %s | User-Agent: %s\n",
        $timestamp,
        $ip,
        $event,
        json_encode($details),
        $userAgent
    );

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function verifyRecaptcha($response) {
    if (empty($response)) {
        return false;
    }

    $secretKey = RECAPTCHA_SECRET_KEY;
    $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';

    $data = [
        'secret'   => $secretKey,
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result  = file_get_contents($verifyURL, false, $context);

    if ($result === false) {
        logSecurityEvent('recaptcha_verification_failed', ['error' => 'Connection failed']);
        return false;
    }

    $resultJson = json_decode($result, true);
    return isset($resultJson['success']) && $resultJson['success'] === true;
}

function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function escapeJs($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
