# 📋 Sistem Pendataan Wajib Pajak Kendaraan Bermotor
## Kabupaten Barito Timur, Kalimantan Tengah

![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/License-GPLv3-blue)
![Status](https://img.shields.io/badge/Status-Production-green)

Sistem informasi berbasis web untuk pendataan wajib pajak kendaraan bermotor di Kabupaten Barito Timur, Kalimantan Tengah. Dikembangkan untuk Badan Pendapatan Daerah (Bapenda) dengan fokus pada keamanan, kemudahan penggunaan, dan efisiensi pengelolaan data.

---

## 📑 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Teknologi](#-teknologi)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#-konfigurasi)
- [Penggunaan](#-penggunaan)
- [Keamanan](#-keamanan)
- [API Documentation](#-api-documentation)
- [Troubleshooting](#-troubleshooting)
- [Maintenance](#-maintenance)
- [Contributing](#-contributing)
- [License](#-license)

---

## 🎯 Fitur Utama

### Untuk Masyarakat (Public)
- ✅ **Form Pendaftaran Online** - Input data wajib pajak dengan validasi real-time
- ✅ **Upload Dokumen** - KTP, STNK, dan foto kendaraan (max 5MB per file)
- ✅ **Validasi Otomatis** - NIK, nomor telepon, dan nomor polisi
- ✅ **reCAPTCHA Protection** - Mencegah spam dan bot
- ✅ **Responsive Design** - Akses dari desktop, tablet, atau smartphone

### Untuk Administrator
- ✅ **Dashboard Interaktif** - Statistik dan visualisasi data real-time
- ✅ **Manajemen Data** - CRUD (Create, Read, Update, Delete) data wajib pajak
- ✅ **User Management** - Kelola user dengan role-based access control
- ✅ **Export Data** - Export ke Excel/PDF (coming soon)
- ✅ **Security Logging** - Audit trail untuk semua aktivitas penting
- ✅ **Change Password** - Ganti password dengan validasi keamanan

### Keamanan
- 🔒 **Session Management** - Secure session dengan timeout dan regeneration
- 🔒 **CSRF Protection** - Token-based CSRF protection untuk semua form
- 🔒 **Rate Limiting** - Proteksi terhadap brute force attacks
- 🔒 **Input Sanitization** - Mencegah XSS dan SQL injection
- 🔒 **File Upload Security** - Validasi MIME type dan deteksi malicious code
- 🔒 **Password Hashing** - Bcrypt dengan cost factor 10
- 🔒 **Security Headers** - X-Frame-Options, X-XSS-Protection, CSP, dll

---

## 🛠️ Teknologi

### Backend
- **PHP 7.4+** - Server-side scripting
- **MySQL 5.7+** / **MariaDB 10.3+** - Database
- **MySQLi** - Database driver dengan prepared statements

### Frontend
- **Vue.js 3** - Progressive JavaScript framework
- **Tailwind CSS** - Utility-first CSS framework
- **Chart.js** - Data visualization
- **Vanilla JavaScript** - Core functionality

### Security
- **Google reCAPTCHA v2** - Bot protection
- **bcrypt** - Password hashing
- **CSRF Tokens** - Cross-site request forgery protection

### Tools
- **Apache 2.4+** / **Nginx** - Web server
- **Git** - Version control
- **Bash** - Backup/restore scripts

---

## 💻 Persyaratan Sistem

### Minimum Requirements
- **OS:** Linux (Ubuntu 20.04+, CentOS 7+, Debian 10+)
- **Web Server:** Apache 2.4+ atau Nginx 1.18+
- **PHP:** 7.4 atau lebih tinggi
- **Database:** MySQL 5.7+ atau MariaDB 10.3+
- **RAM:** 2GB minimum, 4GB recommended
- **Storage:** 10GB minimum (untuk database dan uploads)
- **SSL Certificate:** Required untuk production

### PHP Extensions Required
```bash
php-mysqli
php-json
php-mbstring
php-curl
php-gd
php-zip
php-xml
```

### Apache Modules Required
```bash
mod_rewrite
mod_headers
mod_deflate
mod_expires
```

---

## 📦 Instalasi

### 1. Clone Repository

```bash
cd /var/www/pendataan
git clone [repository-url] PAJAK
cd PAJAK
```

### 2. Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/pendataan/PAJAK

# Set directory permissions
sudo find /var/www/pendataan/PAJAK -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/pendataan/PAJAK -type f -exec chmod 644 {} \;

# Make scripts executable
sudo chmod +x scripts/*.sh

# Secure sensitive directories
sudo chmod 700 logs/
sudo chmod 755 uploads/
```

### 3. Create Database

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE db_pajak_kendaraan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user (optional but recommended)
CREATE USER 'pajak_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_pajak_kendaraan.* TO 'pajak_user'@'localhost';
FLUSH PRIVILEGES;

# Exit MySQL
EXIT;

# Import schema
mysql -u root -p db_pajak_kendaraan < database/schema.sql
```

### 4. Configure Environment

```bash
# Copy example env file
cp .env.example .env

# Edit configuration
nano .env
```

**Important:** Configure these values in `.env`:

```env
# Database Configuration
DB_HOST=localhost
DB_USER=pajak_user
DB_PASS=your_secure_password
DB_NAME=db_pajak_kendaraan

# Application Configuration
APP_URL=https://yourdomain.com
APP_ENV=production
APP_DEBUG=false

# Session Configuration
SESSION_LIFETIME=3600
SESSION_SECURE=true
SESSION_HTTPONLY=true

# reCAPTCHA Configuration (Get from https://www.google.com/recaptcha/admin)
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here

# File Upload Configuration
MAX_FILE_SIZE=5242880
ALLOWED_IMAGE_TYPES=image/jpeg,image/png,image/jpg
ALLOWED_DOC_TYPES=application/pdf

# Security Configuration
CSRF_TOKEN_NAME=csrf_token
RATE_LIMIT_LOGIN=5
RATE_LIMIT_FORM=10

# Admin Email (for alerts)
ADMIN_EMAIL=admin@bapenda.kalteng.dev
```

### 5. Create Admin User

```bash
cd database
php create_admin.php
```

Follow the prompts to create your admin account. **Save the credentials securely!**

### 6. Configure Web Server

#### Apache Configuration

Create `/etc/apache2/sites-available/pajak.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAdmin admin@bapenda.kalteng.dev
    DocumentRoot /var/www/pendataan/PAJAK
    
    <Directory /var/www/pendataan/PAJAK>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/pajak_error.log
    CustomLog ${APACHE_LOG_DIR}/pajak_access.log combined
    
    # Redirect to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAdmin admin@bapenda.kalteng.dev
    DocumentRoot /var/www/pendataan/PAJAK
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/pajak.crt
    SSLCertificateKeyFile /etc/ssl/private/pajak.key
    SSLCertificateChainFile /etc/ssl/certs/pajak-chain.crt
    
    <Directory /var/www/pendataan/PAJAK>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/pajak_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/pajak_ssl_access.log combined
</VirtualHost>
```

Enable site and modules:

```bash
sudo a2ensite pajak.conf
sudo a2enmod rewrite headers deflate expires ssl
sudo systemctl restart apache2
```

#### Nginx Configuration

Create `/etc/nginx/sites-available/pajak`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    root /var/www/pendataan/PAJAK;
    index index.php index.html;
    
    ssl_certificate /etc/ssl/certs/pajak.crt;
    ssl_certificate_key /etc/ssl/private/pajak.key;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ \.env$ {
        deny all;
    }
    
    location ~ \.log$ {
        deny all;
    }
    
    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Prevent PHP execution in uploads
    location ~* ^/uploads/.*\.php$ {
        deny all;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/pajak /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 7. Setup SSL Certificate

#### Using Let's Encrypt (Recommended)

```bash
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

sudo certbot --apache -d yourdomain.com  # For Apache
# OR
sudo certbot --nginx -d yourdomain.com   # For Nginx

# Auto-renewal
sudo certbot renew --dry-run
```

### 8. Setup Cron Jobs

```bash
sudo crontab -e
```

Add these lines:

```cron
# Backup database daily at 2 AM
0 2 * * * /var/www/pendataan/PAJAK/scripts/backup.sh

# Clean old logs weekly
0 3 * * 0 find /var/www/pendataan/PAJAK/logs -name "*.log" -mtime +30 -delete

# Security monitoring hourly
0 * * * * cd /var/www/pendataan/PAJAK && php includes/security_monitor.php

# SSL certificate renewal check daily
0 0 * * * certbot renew --quiet
```

### 9. Test Installation

```bash
# Test database connection
php -r "
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
echo 'Database connection: ' . (\$conn ? 'OK' : 'FAILED') . PHP_EOL;
"

# Test web server
curl -I https://yourdomain.com

# Check PHP version
php -v

# Check required extensions
php -m | grep -E 'mysqli|json|mbstring|curl|gd'
```

---

## ⚙️ Konfigurasi

### Environment Variables

Semua konfigurasi ada di file `.env`. Berikut penjelasan setiap variabel:

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `DB_HOST` | Database host | localhost | Yes |
| `DB_USER` | Database username | root | Yes |
| `DB_PASS` | Database password | - | Yes |
| `DB_NAME` | Database name | db_pajak_kendaraan | Yes |
| `APP_URL` | Application URL | http://localhost/PAJAK | Yes |
| `APP_ENV` | Environment (production/development) | production | Yes |
| `APP_DEBUG` | Debug mode (true/false) | false | Yes |
| `SESSION_LIFETIME` | Session timeout (seconds) | 3600 | No |
| `SESSION_SECURE` | Secure cookies (true/false) | false | No |
| `SESSION_HTTPONLY` | HTTP-only cookies (true/false) | true | No |
| `RECAPTCHA_SITE_KEY` | reCAPTCHA site key | - | Yes |
| `RECAPTCHA_SECRET_KEY` | reCAPTCHA secret key | - | Yes |
| `MAX_FILE_SIZE` | Max upload size (bytes) | 5242880 | No |
| `RATE_LIMIT_LOGIN` | Login attempts limit | 5 | No |
| `RATE_LIMIT_FORM` | Form submission limit | 10 | No |
| `ADMIN_EMAIL` | Admin email for alerts | - | No |

### reCAPTCHA Setup

1. Go to https://www.google.com/recaptcha/admin
2. Register your site
3. Choose reCAPTCHA v2 (Checkbox)
4. Add your domain: `yourdomain.com`
5. Copy Site Key and Secret Key to `.env`

### File Upload Limits

Edit `php.ini` or `.htaccess`:

```ini
upload_max_filesize = 5M
post_max_size = 10M
max_execution_time = 30
```

---

## 📖 Penggunaan

### Untuk Masyarakat

1. **Akses Form Pendaftaran**
   - Buka https://yourdomain.com
   - Klik "Isi Form Wajib Pajak"

2. **Isi Data**
   - Lengkapi semua field yang required
   - Upload dokumen (KTP, STNK, Foto Kendaraan)
   - Centang reCAPTCHA
   - Klik "Kirim Form"

3. **Konfirmasi**
   - Tunggu notifikasi sukses
   - Data akan diproses oleh admin

### Untuk Administrator

1. **Login**
   - Buka https://yourdomain.com/loginpage.php
   - Masukkan username dan password
   - Centang reCAPTCHA
   - Klik "Masuk"

2. **Dashboard**
   - Lihat statistik kendaraan
   - Lihat grafik distribusi
   - Search dan filter data
   - Klik row untuk detail

3. **Edit Data**
   - Klik tombol "Edit" pada row
   - Ubah data yang diperlukan
   - Upload dokumen baru (opsional)
   - Klik "Save"

4. **Kelola User** (Administrator only)
   - Klik "Kelola User" di header
   - Tambah user baru
   - Edit atau hapus user existing
   - Assign role (Administrator/View Only)

5. **Ganti Password**
   - Klik username di header
   - Pilih "Change Password"
   - Masukkan password lama dan baru
   - Klik "Ubah Password"

---

## 🔒 Keamanan

### Best Practices

1. **Always Use HTTPS**
   - Never run in production without SSL
   - Enable HSTS header

2. **Strong Passwords**
   - Minimum 12 characters
   - Mix of uppercase, lowercase, numbers, symbols
   - Change regularly (every 90 days)

3. **Regular Updates**
   - Update PHP, MySQL, and dependencies
   - Monitor security advisories
   - Apply patches promptly

4. **Backup Regularly**
   - Daily database backups
   - Weekly full system backups
   - Test restoration procedures

5. **Monitor Logs**
   - Check security logs daily
   - Set up alerts for suspicious activity
   - Review access logs weekly

6. **Limit Access**
   - Use VPN for admin access (recommended)
   - Whitelist IP addresses if possible
   - Implement 2FA (coming soon)

### Security Checklist

Before going to production, complete this checklist:

- [ ] Changed default admin password
- [ ] Configured reCAPTCHA keys
- [ ] Enabled HTTPS with valid SSL certificate
- [ ] Set `SESSION_SECURE=true` in `.env`
- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Enabled HSTS header
- [ ] Configured CSP header
- [ ] Set proper file permissions (755 for dirs, 644 for files)
- [ ] Secured uploads directory
- [ ] Enabled security monitoring
- [ ] Set up automated backups
- [ ] Tested all security features
- [ ] Reviewed all logs
- [ ] Documented all credentials securely

See `SECURITY_FIXES_GUIDE.md` for detailed security implementation.

---

## 📡 API Documentation

### Authentication

All API endpoints (except public form) require authentication via session.

### Endpoints

#### 1. Login
```http
POST /login/login_process.php
Content-Type: application/json

{
  "username": "admin",
  "password": "your_password",
  "captcha": "recaptcha_response"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Login berhasil",
  "data": {
    "username": "admin",
    "csrf_token": "..."
  }
}
```

#### 2. Get All Data
```http
GET /api/tampil_data.php
```

**Response:**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "nama": "John Doe",
      "nik": "1234567890123456",
      "no_hp": "081234567890",
      "alamat": "Jl. Example No. 1",
      "kecamatan": "Dusun Timur",
      "desa": "Tamiang Layang",
      "no_polisi": "KH 1234 AB",
      "jenis_kendaraan": "Roda 2",
      "kondisi": "Baik",
      "ktp_path": "uploads/ktp/...",
      "stnk_path": "uploads/stnk/...",
      "foto_kendaraan_path": "uploads/kendaraan/...",
      "tanggal_input": "2025-01-15 10:30:00"
    }
  ]
}
```

#### 3. Update Data
```http
POST /api/update_data.php
Content-Type: multipart/form-data

id=1
nama=John Doe
nik=1234567890123456
...
ktp_file=@file.jpg (optional)
```

**Response:**
```json
{
  "status": "success",
  "message": "Data berhasil diupdate"
}
```

#### 4. Add User (Admin Only)
```http
POST /api/add_user.php
Content-Type: application/json

{
  "username": "newuser",
  "password": "secure_password",
  "role": "view_only"
}
```

**Response:**
```json
{
  "status": "success",
  "message": "User berhasil ditambahkan"
}
```

### Error Responses

All endpoints return consistent error format:

```json
{
  "status": "error",
  "message": "Error description"
}
```

HTTP Status Codes:
- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `429` - Too Many Requests
- `500` - Internal Server Error

---

## 🔧 Troubleshooting

### Common Issues

#### 1. Cannot Connect to Database

**Error:** `Connection failed: Access denied for user`

**Solution:**
```bash
# Check database credentials in .env
# Verify MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u pajak_user -p db_pajak_kendaraan
```

#### 2. File Upload Fails

**Error:** `File too large` or `Upload failed`

**Solution:**
```bash
# Check PHP limits
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Edit php.ini or .htaccess
upload_max_filesize = 5M
post_max_size = 10M

# Restart web server
sudo systemctl restart apache2
```

#### 3. Session Expired Immediately

**Error:** Redirected to login after successful login

**Solution:**
```bash
# Check session directory permissions
ls -la /var/lib/php/sessions

# Fix permissions
sudo chown -R www-data:www-data /var/lib/php/sessions

# Check session configuration in .env
SESSION_LIFETIME=3600
```

#### 4. reCAPTCHA Not Working

**Error:** `Silakan verifikasi CAPTCHA`

**Solution:**
```bash
# Verify keys in .env
RECAPTCHA_SITE_KEY=your_site_key
RECAPTCHA_SECRET_KEY=your_secret_key

# Check domain in reCAPTCHA admin console
# Ensure domain matches APP_URL
```

#### 5. 500 Internal Server Error

**Solution:**
```bash
# Check error logs
tail -f /var/log/apache2/pajak_error.log
# OR
tail -f /var/log/nginx/error.log

# Check PHP errors
tail -f logs/php_errors.log

# Enable debug mode temporarily
# Edit .env: APP_DEBUG=true
# Remember to disable after fixing!
```

#### 6. HTTPS Not Working

**Solution:**
```bash
# Check SSL certificate
openssl s_client -connect yourdomain.com:443

# Verify certificate files exist
ls -la /etc/ssl/certs/pajak.crt
ls -la /etc/ssl/private/pajak.key

# Check web server SSL configuration
# Apache: /etc/apache2/sites-available/pajak.conf
# Nginx: /etc/nginx/sites-available/pajak
```

### Debug Mode

To enable debug mode:

1. Edit `.env`:
   ```env
   APP_DEBUG=true
   ```

2. Check logs:
   ```bash
   tail -f logs/security_*.log
   tail -f logs/php_errors.log
   ```

3. **IMPORTANT:** Disable debug mode after fixing:
   ```env
   APP_DEBUG=false
   ```

---

## 🔄 Maintenance

### Daily Tasks

- [ ] Check security logs for suspicious activity
- [ ] Monitor disk space usage
- [ ] Verify backups completed successfully

### Weekly Tasks

- [ ] Review access logs
- [ ] Check for failed login attempts
- [ ] Update statistics and reports
- [ ] Test backup restoration

### Monthly Tasks

- [ ] Update PHP and dependencies
- [ ] Review and rotate logs
- [ ] Check SSL certificate expiry
- [ ] Performance optimization
- [ ] Security audit

### Backup & Restore

#### Manual Backup

```bash
# Database backup
./scripts/backup.sh

# Full system backup
tar -czf pajak_backup_$(date +%Y%m%d).tar.gz \
  --exclude='logs/*' \
  --exclude='uploads/*' \
  /var/www/pendataan/PAJAK
```

#### Restore

```bash
# Restore database
./scripts/restore.sh backup_file.sql

# Restore files
tar -xzf pajak_backup_20250115.tar.gz -C /var/www/pendataan/
```

### Log Rotation

Create `/etc/logrotate.d/pajak`:

```
/var/www/pendataan/PAJAK/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload apache2 > /dev/null 2>&1 || true
    endscript
}
```

---

## 👥 Contributing

This is a proprietary project for Bapenda Kabupaten Barito Timur. External contributions are not accepted.

For internal development:

1. Create feature branch from `main`
2. Make changes and test thoroughly
3. Submit pull request with detailed description
4. Wait for code review and approval
5. Merge after approval

---

## 📄 License

Copyright © 2025 Badan Pendapatan Daerah Kabupaten Barito Timur

Licensed under the GNU General Public License v3.0. See [LICENSE](LICENSE) for the full text. You are free to use, modify, and redistribute this software under the terms of that license.

---

## 📞 Support

For technical support or questions:

- **Email:** support@bapenda.kalteng.dev
- **Phone:** +62 XXX XXXX XXXX
- **Office Hours:** Monday - Friday, 08:00 - 16:00 WITA

For security issues:
- **Email:** security@bapenda.kalteng.dev
- **Emergency:** +62 XXX XXXX XXXX (24/7)

---

## 📚 Additional Documentation

- [EVALUATION_REPORT.md](EVALUATION_REPORT.md) - Comprehensive application evaluation
- [SECURITY_FIXES_GUIDE.md](SECURITY_FIXES_GUIDE.md) - Security implementation guide
- [database/schema.sql](database/schema.sql) - Database schema documentation

---

**Version:** 1.0.0  
**Last Updated:** 2025-01-XX  
**Maintained by:** Zeday
