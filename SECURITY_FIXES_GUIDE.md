# 🔒 PANDUAN PERBAIKAN KEAMANAN
## Sistem Pendataan Wajib Pajak Kendaraan

**Prioritas:** CRITICAL  
**Target:** Implementasi dalam 2-3 hari  
**Status:** Action Required

---

## 🚨 CRITICAL SECURITY FIXES

### 1. Hapus Default Credentials dari Schema

**Masalah:**
```sql
-- database/schema.sql (line 95)
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrator')
```
Password hash ini adalah `admin123` yang sangat lemah.

**Solusi:**

**Step 1:** Hapus baris tersebut dari `database/schema.sql`

**Step 2:** Buat file baru `database/create_admin.php`:

```php
<?php
/**
 * Create Initial Admin User
 * Run this ONCE after database setup
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = 'admin'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    die("Admin user already exists!\n");
}

// Prompt for password
echo "Create Admin User\n";
echo "=================\n\n";

// Generate strong random password
$randomPassword = bin2hex(random_bytes(8)); // 16 characters
echo "Generated strong password: $randomPassword\n\n";

echo "Do you want to use this password? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);

if (trim($line) != 'y') {
    echo "Enter custom password (min 12 characters): ";
    $customPassword = trim(fgets($handle));
    
    if (strlen($customPassword) < 12) {
        die("Password must be at least 12 characters!\n");
    }
    
    $randomPassword = $customPassword;
}

// Hash password
$hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

// Insert admin user
$stmt = $conn->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, 'administrator', NOW())");
$stmt->bind_param("ss", $username, $hashedPassword);
$username = 'admin';

if ($stmt->execute()) {
    echo "\n✅ Admin user created successfully!\n";
    echo "Username: admin\n";
    echo "Password: $randomPassword\n\n";
    echo "⚠️  IMPORTANT: Save this password securely and change it after first login!\n";
    
    // Save to secure file (outside webroot)
    $credFile = __DIR__ . '/../../admin_credentials.txt';
    file_put_contents($credFile, "Username: admin\nPassword: $randomPassword\nCreated: " . date('Y-m-d H:i:s'));
    chmod($credFile, 0600); // Only owner can read
    
    echo "Credentials saved to: $credFile\n";
} else {
    echo "❌ Error creating admin user: " . $conn->error . "\n";
}

$stmt->close();
$conn->close();
```

**Step 3:** Jalankan script:
```bash
cd database
php create_admin.php
```

**Step 4:** Update `.gitignore`:
```
admin_credentials.txt
```

---

### 2. Validasi reCAPTCHA Keys

**Masalah:**
Jika reCAPTCHA keys kosong, validasi di-bypass.

**Solusi:**

Edit `includes/config.php`:

```php
// After defining RECAPTCHA keys
if (APP_ENV === 'production') {
    if (empty(RECAPTCHA_SITE_KEY) || empty(RECAPTCHA_SECRET_KEY)) {
        die("CRITICAL ERROR: reCAPTCHA keys not configured. Please set RECAPTCHA_SITE_KEY and RECAPTCHA_SECRET_KEY in .env file.");
    }
}
```

Edit `includes/security.php` - function `verifyRecaptcha()`:

```php
function verifyRecaptcha($response) {
    // Check if reCAPTCHA is configured
    if (empty(RECAPTCHA_SECRET_KEY)) {
        logSecurityEvent('recaptcha_not_configured', [
            'error' => 'Secret key not set'
        ]);
        return false; // Changed from allowing bypass
    }
    
    if (empty($response)) {
        return false;
    }
    
    // ... rest of function
}
```

---

### 3. Enable HTTPS Enforcement

**Masalah:**
HTTPS tidak dipaksa, memungkinkan man-in-the-middle attacks.

**Solusi:**

**Step 1:** Edit `.htaccess` - uncomment lines 6-8:

```apache
# Force HTTPS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

**Step 2:** Update `.env`:

```env
SESSION_SECURE=true
APP_URL=https://yourdomain.com
```

**Step 3:** Pastikan SSL certificate valid:

```bash
# Test SSL
curl -I https://yourdomain.com

# Check certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
```

---

### 4. Enable Session IP Validation

**Masalah:**
Session IP validation disabled, memungkinkan session hijacking.

**Solusi:**

**Option A: Strict IP Validation (Recommended for internal systems)**

Edit `includes/usersession.php` - uncomment lines 47-56:

```php
// Validate session IP
if (!isset($_SESSION['ip_address'])) {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    // IP address changed - possible session hijacking
    logSecurityEvent('session_ip_mismatch', [
        'user_id' => $_SESSION["userid"] ?? 'unknown',
        'original_ip' => $_SESSION['ip_address'],
        'current_ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    // Enforce IP validation
    session_unset();
    session_destroy();
    header("Location: " . APP_URL . "/loginpage.php?security=1");
    exit;
}
```

**Option B: Browser Fingerprinting (Better for mobile users)**

Create `includes/fingerprint.php`:

```php
<?php
/**
 * Browser Fingerprinting for Session Security
 */

function generateFingerprint() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    $fingerprint = hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
    return $fingerprint;
}

function validateFingerprint() {
    $currentFingerprint = generateFingerprint();
    
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $currentFingerprint;
        return true;
    }
    
    if ($_SESSION['fingerprint'] !== $currentFingerprint) {
        logSecurityEvent('fingerprint_mismatch', [
            'user_id' => $_SESSION["userid"] ?? 'unknown',
            'username' => $_SESSION["username"] ?? 'unknown'
        ]);
        return false;
    }
    
    return true;
}
```

Update `includes/usersession.php`:

```php
require_once __DIR__ . '/fingerprint.php';

// After session start
if (!validateFingerprint()) {
    session_unset();
    session_destroy();
    header("Location: " . APP_URL . "/loginpage.php?security=1");
    exit;
}
```

---

### 5. Implement Account Lockout

**Masalah:**
Tidak ada proteksi brute force di database level.

**Solusi:**

**Step 1:** Update database schema:

```sql
-- Add to users table
ALTER TABLE users 
ADD COLUMN failed_login_attempts INT DEFAULT 0,
ADD COLUMN locked_until TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN last_failed_login TIMESTAMP NULL DEFAULT NULL;
```

**Step 2:** Create `includes/account_lockout.php`:

```php
<?php
/**
 * Account Lockout Protection
 */

define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

function checkAccountLockout($username, $conn) {
    $stmt = $conn->prepare("SELECT failed_login_attempts, locked_until FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['locked' => false];
    }
    
    $user = $result->fetch_assoc();
    
    // Check if account is locked
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        $remainingTime = strtotime($user['locked_until']) - time();
        return [
            'locked' => true,
            'remaining_seconds' => $remainingTime,
            'remaining_minutes' => ceil($remainingTime / 60)
        ];
    }
    
    // Reset if lockout expired
    if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
        $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
    }
    
    return ['locked' => false, 'attempts' => $user['failed_login_attempts']];
}

function recordFailedLogin($username, $conn) {
    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    // Check if should lock
    $stmt = $conn->prepare("SELECT failed_login_attempts FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user['failed_login_attempts'] >= MAX_FAILED_ATTEMPTS) {
        $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        $stmt = $conn->prepare("UPDATE users SET locked_until = ? WHERE username = ?");
        $stmt->bind_param("ss", $lockUntil, $username);
        $stmt->execute();
        
        logSecurityEvent('account_locked', [
            'username' => $username,
            'attempts' => $user['failed_login_attempts'],
            'locked_until' => $lockUntil
        ]);
        
        return true; // Account locked
    }
    
    return false; // Not locked yet
}

function resetFailedAttempts($username, $conn) {
    $stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
}
```

**Step 3:** Update `login/login_process.php`:

```php
require_once '../includes/account_lockout.php';

// After getting username, before password check
$lockStatus = checkAccountLockout($username, $conn);

if ($lockStatus['locked']) {
    logSecurityEvent('login_attempt_while_locked', [
        'username' => $username,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Akun terkunci karena terlalu banyak percobaan login gagal. Coba lagi dalam " . $lockStatus['remaining_minutes'] . " menit."
    ]);
    exit;
}

// After password verification fails
recordFailedLogin($username, $conn);

// After successful login
resetFailedAttempts($username, $conn);
```

---

### 6. Add Content Security Policy (CSP)

**Masalah:**
Tidak ada CSP header untuk mencegah XSS.

**Solusi:**

Edit `.htaccess`:

```apache
<IfModule mod_headers.c>
    # Content Security Policy
    Header set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://unpkg.com https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-src https://www.google.com; object-src 'none'; base-uri 'self'; form-action 'self';"
    
    # For development, use report-only mode first
    # Header set Content-Security-Policy-Report-Only "..."
</IfModule>
```

**Note:** Test thoroughly before enforcing!

---

### 7. Secure File Upload Directory

**Masalah:**
Upload directory accessible via direct URL.

**Solusi:**

**Option A: Move Outside Webroot (Recommended)**

```bash
# Move uploads outside webroot
sudo mkdir -p /var/www/pendataan/uploads_secure
sudo mv /var/www/pendataan/PAJAK/uploads/* /var/www/pendataan/uploads_secure/
sudo chown -R www-data:www-data /var/www/pendataan/uploads_secure
sudo chmod -R 755 /var/www/pendataan/uploads_secure
```

Update `includes/config.php`:

```php
define('UPLOAD_DIR', '/var/www/pendataan/uploads_secure/');
```

Create `download.php`:

```php
<?php
/**
 * Secure File Download
 * Requires authentication
 */

require_once 'includes/config.php';
require_once 'includes/security.php';
require_once 'includes/usersession.php';

$file = $_GET['file'] ?? '';
$type = $_GET['type'] ?? ''; // ktp, stnk, kendaraan

// Validate input
if (empty($file) || empty($type)) {
    http_response_code(400);
    die('Invalid request');
}

// Sanitize filename
$file = basename($file);

// Determine directory
$allowedTypes = ['ktp', 'stnk', 'kendaraan'];
if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    die('Invalid file type');
}

$filePath = UPLOAD_DIR . $type . '/' . $file;

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Log access
logSecurityEvent('file_download', [
    'user_id' => $userid,
    'username' => $username,
    'file' => $file,
    'type' => $type
]);

// Serve file
$mimeType = mime_content_type($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
```

Update database paths to use download.php:

```php
// In api/tampil_data.php
$row['ktp_path'] = !empty($row['ktp_path']) 
    ? APP_URL . '/download.php?type=ktp&file=' . basename($row['ktp_path'])
    : "";
```

**Option B: Add Authentication Check (Simpler)**

Create `uploads/.htaccess`:

```apache
# Require authentication for all files
RewriteEngine On
RewriteCond %{REQUEST_URI} ^/uploads/
RewriteRule ^(.*)$ /check_auth.php?file=$1 [L]
```

Create `check_auth.php`:

```php
<?php
require_once 'includes/usersession.php';
// If reaches here, user is authenticated
$file = $_GET['file'] ?? '';
$filePath = __DIR__ . '/uploads/' . $file;

if (file_exists($filePath)) {
    $mimeType = mime_content_type($filePath);
    header('Content-Type: ' . $mimeType);
    readfile($filePath);
} else {
    http_response_code(404);
}
exit;
```

---

### 8. Implement HSTS

**Masalah:**
Tidak ada HSTS header.

**Solusi:**

Edit `.htaccess` - uncomment line 24:

```apache
<IfModule mod_headers.c>
    # HSTS - Force HTTPS for 1 year
    Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
</IfModule>
```

**Important:** Only enable after confirming HTTPS works perfectly!

---

### 9. Add Security Monitoring

Create `includes/security_monitor.php`:

```php
<?php
/**
 * Security Monitoring & Alerting
 */

function checkSecurityThreats() {
    $threats = [];
    
    // Check for suspicious activity in logs
    $logFile = LOG_DIR . 'security_' . date('Y-m-d') . '.log';
    
    if (file_exists($logFile)) {
        $logs = file($logFile);
        $recentLogs = array_slice($logs, -100); // Last 100 entries
        
        // Count failed logins
        $failedLogins = 0;
        foreach ($recentLogs as $log) {
            if (strpos($log, 'login_failed') !== false) {
                $failedLogins++;
            }
        }
        
        if ($failedLogins > 10) {
            $threats[] = [
                'type' => 'brute_force',
                'severity' => 'high',
                'message' => "Detected $failedLogins failed login attempts in last 100 events"
            ];
        }
        
        // Check for rate limit violations
        $rateLimitViolations = 0;
        foreach ($recentLogs as $log) {
            if (strpos($log, 'rate_limit_exceeded') !== false) {
                $rateLimitViolations++;
            }
        }
        
        if ($rateLimitViolations > 5) {
            $threats[] = [
                'type' => 'rate_limit_abuse',
                'severity' => 'medium',
                'message' => "Detected $rateLimitViolations rate limit violations"
            ];
        }
    }
    
    return $threats;
}

function sendSecurityAlert($threats) {
    // Email admin
    $to = env('ADMIN_EMAIL', 'admin@example.com');
    $subject = '🚨 Security Alert - ' . count($threats) . ' threats detected';
    
    $message = "Security threats detected:\n\n";
    foreach ($threats as $threat) {
        $message .= "Type: {$threat['type']}\n";
        $message .= "Severity: {$threat['severity']}\n";
        $message .= "Message: {$threat['message']}\n\n";
    }
    
    $headers = "From: security@" . $_SERVER['HTTP_HOST'];
    
    mail($to, $subject, $message, $headers);
    
    // Log alert
    logSecurityEvent('security_alert_sent', [
        'threat_count' => count($threats),
        'threats' => $threats
    ]);
}

// Run monitoring (call from cron job)
if (php_sapi_name() === 'cli') {
    $threats = checkSecurityThreats();
    if (!empty($threats)) {
        sendSecurityAlert($threats);
    }
}
```

Add to crontab:

```bash
# Run security monitoring every hour
0 * * * * cd /var/www/pendataan/PAJAK && php includes/security_monitor.php
```

---

### 10. Create Security Checklist

Create `SECURITY_CHECKLIST.md`:

```markdown
# Security Checklist

## Pre-Deployment
- [ ] Changed default admin password
- [ ] Configured reCAPTCHA keys
- [ ] Enabled HTTPS
- [ ] Set SESSION_SECURE=true
- [ ] Enabled session IP validation or fingerprinting
- [ ] Implemented account lockout
- [ ] Added CSP headers
- [ ] Secured file upload directory
- [ ] Enabled HSTS
- [ ] Set up security monitoring
- [ ] Reviewed all .env settings
- [ ] Tested all security features
- [ ] Removed debug/test code
- [ ] Set APP_ENV=production
- [ ] Set APP_DEBUG=false
- [ ] Configured proper file permissions
- [ ] Set up automated backups
- [ ] Documented all credentials securely

## Post-Deployment
- [ ] Monitor security logs daily
- [ ] Review failed login attempts
- [ ] Check for suspicious activity
- [ ] Update dependencies monthly
- [ ] Perform security audit quarterly
- [ ] Test backup restoration
- [ ] Review user accounts
- [ ] Check SSL certificate expiry
- [ ] Monitor server resources
- [ ] Review access logs

## Incident Response
- [ ] Document incident response plan
- [ ] Define escalation procedures
- [ ] Set up emergency contacts
- [ ] Prepare rollback procedures
- [ ] Test disaster recovery
```

---

## 📋 Implementation Timeline

| Day | Tasks | Duration |
|-----|-------|----------|
| **Day 1** | 1. Remove default credentials<br>2. Validate reCAPTCHA<br>3. Enable HTTPS | 4-6 hours |
| **Day 2** | 4. Session security<br>5. Account lockout<br>6. CSP headers | 4-6 hours |
| **Day 3** | 7. Secure uploads<br>8. HSTS<br>9. Monitoring<br>10. Testing | 4-6 hours |

**Total:** 12-18 hours over 3 days

---

## ✅ Verification Steps

After implementing all fixes:

1. **Test Authentication:**
   ```bash
   # Test with wrong password (should lock after 5 attempts)
   # Test with correct password
   # Verify session security
   ```

2. **Test HTTPS:**
   ```bash
   curl -I http://yourdomain.com
   # Should redirect to HTTPS
   ```

3. **Test File Upload Security:**
   ```bash
   # Try to upload PHP file (should be rejected)
   # Try to access upload directly (should require auth)
   ```

4. **Test CSP:**
   - Open browser console
   - Check for CSP violations
   - Fix any legitimate violations

5. **Security Scan:**
   ```bash
   # Use online tools
   # https://observatory.mozilla.org/
   # https://securityheaders.com/
   ```

---

## 📞 Support

Jika ada pertanyaan atau masalah saat implementasi:
- Review dokumentasi di EVALUATION_REPORT.md
- Check logs di logs/security_*.log
- Contact: [your-contact]

---

**Document Version:** 1.0  
**Last Updated:** 2025-01-XX  
**Status:** Ready for Implementation
