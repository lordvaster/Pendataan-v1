# 🚀 DEPLOYMENT GUIDE - PAJAK Application

**Quick Setup Guide untuk Sysadmin**

---

## 📋 PREREQUISITES

✅ Apache sudah running di port 443 (internal)  
✅ Aplikasi sudah ada di `/var/www/pendataan/PAJAK/`  
✅ Database `db_pajak_kendaraan` sudah setup  
✅ 852 records data sudah ada dan aman  

**Yang Perlu Dilakukan:** Setup Nginx reverse proxy untuk akses eksternal

---

## 🎯 RECOMMENDED SETUP: SUBDOMAIN

**Domain:** `pajak.yourdomain.com`

### Keuntungan:
- ✅ Pemisahan aplikasi yang jelas
- ✅ Tidak mengganggu aplikasi existing
- ✅ Mudah maintenance
- ✅ SSL certificate terpisah

---

## 📝 STEP-BY-STEP INSTALLATION

### Step 1: Setup DNS (di DNS Provider)

Tambahkan A Record:
```
Type: A
Name: pajak.pendataan.bapenda.kalteng
Value: [IP Server Anda]
TTL: 3600
```

**Verify DNS:**
```bash
nslookup pajak.yourdomain.com
# Should return your server IP
```

---

### Step 2: Install Nginx Configuration

```bash
# Copy config file
sudo cp /var/www/pendataan/PAJAK/nginx_config_pajak.conf /etc/nginx/sites-available/pajak.conf

# Enable site
sudo ln -s /etc/nginx/sites-available/pajak.conf /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# If test OK, reload nginx
sudo systemctl reload nginx
```

**Expected Output:**
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

---

### Step 3: Setup SSL Certificate

```bash
# Install certbot if not installed
sudo apt update
sudo apt install certbot python3-certbot-nginx -y

# Get SSL certificate
sudo certbot --nginx -d pajak.yourdomain.com

# Follow prompts:
# - Enter email address
# - Agree to terms
# - Choose redirect HTTP to HTTPS (recommended)
```

**Certbot will automatically:**
- Generate SSL certificate
- Update Nginx configuration
- Setup auto-renewal

---

### Step 4: Verify Installation

#### A. Test HTTP (should redirect to HTTPS)
```bash
curl -I http://pajak.yourdomain.com/loginpage.php
```

**Expected:**
```
HTTP/1.1 301 Moved Permanently
Location: https://pajak.yourdomain.com/loginpage.php
```

#### B. Test HTTPS
```bash
curl -I https://pajak.yourdomain.com/loginpage.php
```

**Expected:**
```
HTTP/2 200
server: nginx
content-type: text/html; charset=UTF-8
```

#### C. Test in Browser
Open: `https://pajak.yourdomain.com/loginpage.php`

**Expected:**
- ✅ Login page displayed
- ✅ SSL certificate valid (green padlock)
- ✅ No security warnings

---

### Step 5: Test Full Application Flow

```bash
# Run automated test
cd /var/www/pendataan/PAJAK
bash test_full_flow.sh
```

**Expected Output:**
```
========================================
TEST SUMMARY
========================================
Login Page:    ✓ OK
Login Process: ✓ Attempted
Dashboard:     ✓ OK
API Response:  ✓ OK (852 records)
========================================
```

---

### Step 6: Manual Testing

1. **Login Test:**
   - URL: `https://pajak.yourdomain.com/loginpage.php`
   - Username: `admin`
   - Password: `Casval@2007`
   - Expected: Redirect to dashboard

2. **Dashboard Test:**
   - Should display 852 records
   - Check if data table loads
   - Verify pagination works

3. **CRUD Operations:**
   - Try adding new record
   - Try editing existing record
   - Try soft delete (move to trash)
   - Try restore from trash

4. **API Test:**
   - Open browser console (F12)
   - Check Network tab
   - Verify API calls return data
   - Check for any errors

---

## 🔧 TROUBLESHOOTING

### Issue 1: DNS Not Resolving

**Symptom:**
```bash
nslookup pajak.yourdomain.com
# Returns: server can't find pajak.yourdomain.com
```

**Solution:**
- Wait for DNS propagation (up to 24 hours)
- Check DNS provider settings
- Verify A record is correct
- Try: `dig pajak.yourdomain.com`

---

### Issue 2: Nginx 502 Bad Gateway

**Symptom:**
```
HTTP/1.1 502 Bad Gateway
```

**Solution:**
```bash
# Check if Apache is running
sudo systemctl status apache2

# If not running, start it
sudo systemctl start apache2

# Check Apache logs
sudo tail -50 /var/log/apache2/error.log

# Check Nginx logs
sudo tail -50 /var/log/nginx/pajak_error.log
```

---

### Issue 3: SSL Certificate Error

**Symptom:**
- Browser shows "Your connection is not private"
- Certificate invalid

**Solution:**
```bash
# Renew certificate
sudo certbot renew --force-renewal

# Check certificate status
sudo certbot certificates

# If still issues, remove and reinstall
sudo certbot delete --cert-name pajak.yourdomain.com
sudo certbot --nginx -d pajak.yourdomain.com
```

---

### Issue 4: 404 Not Found

**Symptom:**
```
HTTP/1.1 404 Not Found
```

**Solution:**
```bash
# Check Apache DocumentRoot
cat /etc/apache2/sites-available/pendataan-le-ssl.conf | grep DocumentRoot
# Should be: /var/www/pendataan/PAJAK

# Check if files exist
ls -la /var/www/pendataan/PAJAK/loginpage.php

# Check Apache virtual host
sudo apache2ctl -S

# Restart Apache
sudo systemctl restart apache2
```

---

### Issue 5: Login Not Working

**Symptom:**
- Login page loads but login fails
- "Username atau password salah"

**Solution:**
```bash
# Check database connection
php /var/www/pendataan/PAJAK/check_data_status.php

# Check user credentials
mysql -u root -p db_pajak_kendaraan -e "SELECT username, role FROM users WHERE username='admin';"

# Check logs
tail -50 /var/www/pendataan/PAJAK/logs/security.log
```

---

## 📊 MONITORING

### Check Application Status

```bash
# Check Apache
sudo systemctl status apache2

# Check Nginx
sudo systemctl status nginx

# Check MySQL
sudo systemctl status mysql

# Check disk space
df -h

# Check memory
free -h
```

### Monitor Logs

```bash
# Apache error log
sudo tail -f /var/log/apache2/error.log

# Nginx access log
sudo tail -f /var/log/nginx/pajak_access.log

# Nginx error log
sudo tail -f /var/log/nginx/pajak_error.log

# Application security log
tail -f /var/www/pendataan/PAJAK/logs/security.log
```

### Check SSL Certificate Expiry

```bash
# Check certificate
sudo certbot certificates

# Test auto-renewal
sudo certbot renew --dry-run
```

---

## 🔐 SECURITY CHECKLIST

After deployment, verify:

- [ ] SSL certificate valid and auto-renewal enabled
- [ ] HTTPS redirect working (HTTP → HTTPS)
- [ ] Security headers present (X-Frame-Options, etc.)
- [ ] File permissions correct (755 for directories, 644 for files)
- [ ] Database credentials secure (not in public files)
- [ ] Firewall rules configured (allow 80, 443)
- [ ] Backup system running (daily backups)
- [ ] Monitoring alerts configured
- [ ] Log rotation enabled
- [ ] Rate limiting active

---

## 📞 SUPPORT CONTACTS

**For Technical Issues:**
- Check: `LAPORAN_FINAL_DATA_KENDARAAN.md`
- Check: `DEPLOYMENT_ISSUE_REPORT.md`
- Check: `SECURITY_FIXES_GUIDE.md`

**Emergency Commands:**
```bash
# Restart all services
sudo systemctl restart apache2
sudo systemctl restart nginx
sudo systemctl restart mysql

# Check all logs
sudo tail -100 /var/log/apache2/error.log
sudo tail -100 /var/log/nginx/error.log
sudo tail -100 /var/www/pendataan/PAJAK/logs/security.log
```

---

## ✅ POST-DEPLOYMENT CHECKLIST

After successful deployment:

- [ ] DNS resolving correctly
- [ ] SSL certificate installed and valid
- [ ] Login page accessible via HTTPS
- [ ] Login working with admin credentials
- [ ] Dashboard displaying 852 records
- [ ] API returning data correctly
- [ ] CRUD operations working
- [ ] File uploads working
- [ ] Trash/restore functionality working
- [ ] Audit logs recording actions
- [ ] Backup system configured
- [ ] Monitoring alerts setup
- [ ] Documentation updated
- [ ] Team notified of new URL

---

## 🎉 SUCCESS CRITERIA

Deployment is successful when:

1. ✅ URL `https://pajak.yourdomain.com` accessible
2. ✅ SSL certificate valid (green padlock)
3. ✅ Login working with admin/Casval@2007
4. ✅ Dashboard shows 852 records
5. ✅ All CRUD operations functional
6. ✅ No errors in logs
7. ✅ Performance acceptable (page load < 3s)
8. ✅ Mobile responsive working

---

**Deployment Guide Version:** 1.0  
**Last Updated:** 14 Januari 2026  
**Tested On:** Apache 2.4.66, Nginx 1.18.0, PHP 8.x, MySQL 8.x
