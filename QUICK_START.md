# 🚀 Quick Start Guide
## Sistem Pendataan Wajib Pajak Kendaraan

Panduan cepat untuk setup dan deployment aplikasi dalam 30 menit.

---

## ⚡ Prerequisites Checklist

Sebelum memulai, pastikan Anda memiliki:

- [ ] Server Linux (Ubuntu 20.04+ / CentOS 7+ / Debian 10+)
- [ ] Root atau sudo access
- [ ] Domain name (contoh: yourdomain.com)
- [ ] SSL certificate (Let's Encrypt atau commercial)
- [ ] reCAPTCHA keys (dari Google)
- [ ] Email untuk notifikasi

---

## 📋 Step-by-Step Installation

### Step 1: Install Dependencies (5 menit)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Apache, PHP, MySQL
sudo apt install -y apache2 php7.4 php7.4-mysqli php7.4-json php7.4-mbstring \
  php7.4-curl php7.4-gd php7.4-zip php7.4-xml mysql-server

# Install additional tools
sudo apt install -y git certbot python3-certbot-apache

# Enable Apache modules
sudo a2enmod rewrite headers deflate expires ssl

# Start services
sudo systemctl start apache2 mysql
sudo systemctl enable apache2 mysql
```

### Step 2: Secure MySQL (2 menit)

```bash
# Run MySQL secure installation
sudo mysql_secure_installation

# Answer prompts:
# - Set root password: YES (use strong password)
# - Remove anonymous users: YES
# - Disallow root login remotely: YES
# - Remove test database: YES
# - Reload privilege tables: YES
```

### Step 3: Create Database (3 menit)

```bash
# Login to MySQL
sudo mysql -u root -p

# Run these commands in MySQL:
```

```sql
CREATE DATABASE db_pajak_kendaraan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pajak_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123!';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_pajak_kendaraan.* TO 'pajak_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 4: Deploy Application (5 menit)

```bash
# Create directory
sudo mkdir -p /var/www/pendataan
cd /var/www/pendataan

# Clone or upload files
# Option A: Clone from Git
sudo git clone [your-repo-url] PAJAK

# Option B: Upload via SCP
# scp -r PAJAK/ user@server:/var/www/pendataan/

# Set permissions
sudo chown -R www-data:www-data /var/www/pendataan/PAJAK
sudo find /var/www/pendataan/PAJAK -type d -exec chmod 755 {} \;
sudo find /var/www/pendataan/PAJAK -type f -exec chmod 644 {} \;
sudo chmod 700 /var/www/pendataan/PAJAK/logs/
sudo chmod +x /var/www/pendataan/PAJAK/scripts/*.sh

# Import database schema
cd /var/www/pendataan/PAJAK
mysql -u pajak_user -p db_pajak_kendaraan < database/schema.sql
```

### Step 5: Configure Application (5 menit)

```bash
# Create .env file
cd /var/www/pendataan/PAJAK
cp .env.example .env
nano .env
```

**Edit these values in .env:**

```env
# Database
DB_HOST=localhost
DB_USER=pajak_user
DB_PASS=YourSecurePassword123!
DB_NAME=db_pajak_kendaraan

# Application
APP_URL=https://yourdomain.com
APP_ENV=production
APP_DEBUG=false

# Session
SESSION_LIFETIME=3600
SESSION_SECURE=true
SESSION_HTTPONLY=true

# reCAPTCHA (get from https://www.google.com/recaptcha/admin)
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here

# Security
RATE_LIMIT_LOGIN=5
RATE_LIMIT_FORM=10

# Admin Email
ADMIN_EMAIL=admin@bapenda.kalteng.dev
```

Save and exit (Ctrl+X, Y, Enter)

### Step 6: Create Admin User (2 menit)

```bash
cd /var/www/pendataan/PAJAK/database
php create_admin.php

# Follow prompts:
# - Use generated password or enter custom (min 12 chars)
# - Save credentials securely!
```

### Step 7: Configure Apache (3 menit)

```bash
# Create virtual host
sudo nano /etc/apache2/sites-available/pajak.conf
```

**Paste this configuration:**

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
</VirtualHost>
```

Save and enable:

```bash
sudo a2ensite pajak.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### Step 8: Setup SSL Certificate (3 menit)

```bash
# Using Let's Encrypt (Free)
sudo certbot --apache -d yourdomain.com

# Follow prompts:
# - Enter email address
# - Agree to Terms of Service
# - Choose to redirect HTTP to HTTPS: YES

# Test auto-renewal
sudo certbot renew --dry-run
```

### Step 9: Setup Cron Jobs (2 menit)

```bash
sudo crontab -e

# Add these lines:
```

```cron
# Daily backup at 2 AM
0 2 * * * /var/www/pendataan/PAJAK/scripts/backup.sh

# Clean old logs weekly
0 3 * * 0 find /var/www/pendataan/PAJAK/logs -name "*.log" -mtime +30 -delete

# Security monitoring hourly
0 * * * * cd /var/www/pendataan/PAJAK && php includes/security_monitor.php

# SSL renewal check daily
0 0 * * * certbot renew --quiet
```

Save and exit.

### Step 10: Test Installation (5 menit)

```bash
# Test database connection
cd /var/www/pendataan/PAJAK
php -r "
require_once 'includes/config.php';
require_once 'includes/db_connect.php';
echo 'Database: ' . (\$conn ? '✅ OK' : '❌ FAILED') . PHP_EOL;
"

# Test web server
curl -I https://yourdomain.com

# Check SSL
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null

# Test login page
curl -s https://yourdomain.com/loginpage.php | grep -q "Login" && echo "✅ Login page OK" || echo "❌ Login page FAILED"
```

---

## ✅ Post-Installation Checklist

After installation, verify these items:

### Security
- [ ] HTTPS is working and enforced
- [ ] Admin password is strong and saved securely
- [ ] reCAPTCHA is configured and working
- [ ] File permissions are correct (755/644)
- [ ] .env file is not accessible via web
- [ ] Error display is disabled (APP_DEBUG=false)
- [ ] Security logs are being created

### Functionality
- [ ] Can access homepage (index.php)
- [ ] Can access public form (form_wajib_pajak.php)
- [ ] Can login to admin panel
- [ ] Can view dashboard
- [ ] Can add/edit/delete data (admin)
- [ ] Can upload files
- [ ] Charts are displaying correctly

### Backup & Monitoring
- [ ] Backup script is executable
- [ ] Cron jobs are configured
- [ ] Log files are being created
- [ ] Email notifications are working (if configured)

---

## 🔧 Quick Troubleshooting

### Issue: Cannot access website

```bash
# Check Apache status
sudo systemctl status apache2

# Check error logs
sudo tail -f /var/log/apache2/pajak_error.log

# Restart Apache
sudo systemctl restart apache2
```

### Issue: Database connection failed

```bash
# Check MySQL status
sudo systemctl status mysql

# Test connection
mysql -u pajak_user -p db_pajak_kendaraan

# Check credentials in .env
cat /var/www/pendataan/PAJAK/.env | grep DB_
```

### Issue: File upload not working

```bash
# Check upload directory permissions
ls -la /var/www/pendataan/PAJAK/uploads/

# Fix permissions
sudo chown -R www-data:www-data /var/www/pendataan/PAJAK/uploads/
sudo chmod -R 755 /var/www/pendataan/PAJAK/uploads/

# Check PHP upload limits
php -i | grep upload_max_filesize
```

### Issue: Session expires immediately

```bash
# Check session directory
ls -la /var/lib/php/sessions/

# Fix permissions
sudo chown -R www-data:www-data /var/lib/php/sessions/

# Check .env settings
cat /var/www/pendataan/PAJAK/.env | grep SESSION_
```

### Issue: reCAPTCHA not working

```bash
# Verify keys in .env
cat /var/www/pendataan/PAJAK/.env | grep RECAPTCHA_

# Check domain in reCAPTCHA console
# https://www.google.com/recaptcha/admin

# Test in browser console
# Should see reCAPTCHA widget
```

---

## 🎯 First Login

1. **Access Admin Panel:**
   ```
   https://yourdomain.com/loginpage.php
   ```

2. **Login with credentials from Step 6:**
   - Username: `admin`
   - Password: [from create_admin.php output]

3. **Change Password Immediately:**
   - Click username in header
   - Select "Change Password"
   - Enter new strong password
   - Save

4. **Test Functionality:**
   - View dashboard
   - Check statistics
   - Try adding test data
   - Test file upload
   - Verify charts display

---

## 📊 Performance Optimization (Optional)

### Enable OPcache

```bash
# Edit php.ini
sudo nano /etc/php/7.4/apache2/php.ini

# Add/uncomment these lines:
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60

# Restart Apache
sudo systemctl restart apache2
```

### Enable Compression

Already configured in `.htaccess`, verify:

```bash
# Check if mod_deflate is enabled
apache2ctl -M | grep deflate

# If not enabled:
sudo a2enmod deflate
sudo systemctl restart apache2
```

### Database Optimization

```bash
# Login to MySQL
mysql -u root -p

# Run optimization
USE db_pajak_kendaraan;
OPTIMIZE TABLE wajib_pajak;
OPTIMIZE TABLE users;
ANALYZE TABLE wajib_pajak;
ANALYZE TABLE users;
EXIT;
```

---

## 🔒 Security Hardening (Recommended)

### 1. Firewall Configuration

```bash
# Install UFW
sudo apt install ufw

# Allow SSH, HTTP, HTTPS
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
sudo ufw status
```

### 2. Fail2Ban (Brute Force Protection)

```bash
# Install Fail2Ban
sudo apt install fail2ban

# Create jail for Apache
sudo nano /etc/fail2ban/jail.local
```

Add:

```ini
[apache-auth]
enabled = true
port = http,https
filter = apache-auth
logpath = /var/log/apache2/pajak_error.log
maxretry = 5
bantime = 3600
```

```bash
# Restart Fail2Ban
sudo systemctl restart fail2ban
sudo fail2ban-client status
```

### 3. Disable Directory Listing

Already configured in `.htaccess`, verify:

```bash
# Check Apache config
grep -r "Options -Indexes" /etc/apache2/
```

### 4. Hide PHP Version

```bash
# Edit php.ini
sudo nano /etc/php/7.4/apache2/php.ini

# Set:
expose_php = Off

# Restart Apache
sudo systemctl restart apache2
```

---

## 📈 Monitoring Setup (Optional)

### Setup Uptime Monitoring

Use services like:
- UptimeRobot (https://uptimerobot.com) - Free
- Pingdom (https://www.pingdom.com)
- StatusCake (https://www.statuscake.com)

Monitor:
- `https://yourdomain.com` (Homepage)
- `https://yourdomain.com/loginpage.php` (Login)

### Setup Log Monitoring

```bash
# Install logwatch
sudo apt install logwatch

# Configure daily email reports
sudo nano /etc/cron.daily/00logwatch
```

Add:

```bash
#!/bin/bash
/usr/sbin/logwatch --output mail --mailto admin@bapenda.kalteng.dev --detail high
```

```bash
# Make executable
sudo chmod +x /etc/cron.daily/00logwatch
```

---

## 🎓 Next Steps

After successful installation:

1. **Read Full Documentation:**
   - [README.md](README.md) - Complete documentation
   - [EVALUATION_REPORT.md](EVALUATION_REPORT.md) - Application evaluation
   - [SECURITY_FIXES_GUIDE.md](SECURITY_FIXES_GUIDE.md) - Security guide

2. **Implement Critical Security Fixes:**
   - Follow [SECURITY_FIXES_GUIDE.md](SECURITY_FIXES_GUIDE.md)
   - Enable all recommended security features
   - Test thoroughly

3. **Train Users:**
   - Admin panel usage
   - Data entry procedures
   - Security best practices

4. **Setup Monitoring:**
   - Uptime monitoring
   - Log monitoring
   - Performance monitoring

5. **Plan Maintenance:**
   - Schedule regular backups
   - Plan update schedule
   - Document procedures

---

## 📞 Need Help?

If you encounter issues:

1. **Check Logs:**
   ```bash
   tail -f /var/log/apache2/pajak_error.log
   tail -f /var/www/pendataan/PAJAK/logs/security_*.log
   ```

2. **Enable Debug Mode (temporarily):**
   ```bash
   nano /var/www/pendataan/PAJAK/.env
   # Set: APP_DEBUG=true
   # Remember to disable after fixing!
   ```

3. **Review Documentation:**
   - [README.md](README.md) - Troubleshooting section
   - [SECURITY_FIXES_GUIDE.md](SECURITY_FIXES_GUIDE.md)

4. **Contact Support:**
   - Email: support@bapenda.kalteng.dev
   - Phone: +62 XXX XXXX XXXX

---

## ✨ Success!

If all tests pass, your application is ready for production use! 🎉

**Important Reminders:**
- Keep credentials secure
- Monitor logs regularly
- Backup daily
- Update regularly
- Follow security best practices

---

**Document Version:** 1.0  
**Last Updated:** 2025-01-XX  
**Estimated Time:** 30-45 minutes
