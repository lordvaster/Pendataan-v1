#!/bin/bash
# Author: Zeday @join.co.id

###############################################################################
# Automated Backup Script
# Sistem Pendataan Wajib Pajak Kendaraan
# 
# This script creates backups of:
# 1. Database (MySQL dump)
# 2. Uploaded files (KTP, STNK, Foto Kendaraan)
# 3. Application logs
#
# Usage: ./backup.sh
# Cron: 0 2 * * * /var/www/pendataan/PAJAK/scripts/backup.sh
###############################################################################

# Configuration
APP_DIR="/var/www/pendataan/PAJAK"
BACKUP_DIR="/var/backups/pajak"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Database configuration (read from .env or set manually)
DB_NAME="db_pajak_kendaraan"
DB_USER="root"
DB_PASS=""  # Set this or read from .env

# Create backup directory if not exists
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/uploads"
mkdir -p "$BACKUP_DIR/logs"

# Log file
LOG_FILE="$BACKUP_DIR/backup.log"

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

log_message "=========================================="
log_message "Starting backup process..."
log_message "=========================================="

###############################################################################
# 1. Database Backup
###############################################################################

log_message "Backing up database..."

DB_BACKUP_FILE="$BACKUP_DIR/database/db_backup_$DATE.sql"

if [ -z "$DB_PASS" ]; then
    # No password
    mysqldump -u "$DB_USER" "$DB_NAME" > "$DB_BACKUP_FILE" 2>&1
else
    # With password
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$DB_BACKUP_FILE" 2>&1
fi

if [ $? -eq 0 ]; then
    # Compress the SQL file
    gzip "$DB_BACKUP_FILE"
    log_message "✓ Database backup completed: db_backup_$DATE.sql.gz"
    log_message "  Size: $(du -h "$DB_BACKUP_FILE.gz" | cut -f1)"
else
    log_message "✗ Database backup failed!"
    exit 1
fi

###############################################################################
# 2. Uploads Backup
###############################################################################

log_message "Backing up uploaded files..."

UPLOADS_BACKUP_FILE="$BACKUP_DIR/uploads/uploads_backup_$DATE.tar.gz"

tar -czf "$UPLOADS_BACKUP_FILE" -C "$APP_DIR" uploads/ 2>&1

if [ $? -eq 0 ]; then
    log_message "✓ Uploads backup completed: uploads_backup_$DATE.tar.gz"
    log_message "  Size: $(du -h "$UPLOADS_BACKUP_FILE" | cut -f1)"
else
    log_message "✗ Uploads backup failed!"
fi

###############################################################################
# 3. Logs Backup
###############################################################################

log_message "Backing up logs..."

LOGS_BACKUP_FILE="$BACKUP_DIR/logs/logs_backup_$DATE.tar.gz"

tar -czf "$LOGS_BACKUP_FILE" -C "$APP_DIR" logs/ 2>&1

if [ $? -eq 0 ]; then
    log_message "✓ Logs backup completed: logs_backup_$DATE.tar.gz"
    log_message "  Size: $(du -h "$LOGS_BACKUP_FILE" | cut -f1)"
else
    log_message "✗ Logs backup failed!"
fi

###############################################################################
# 4. Configuration Backup (optional)
###############################################################################

log_message "Backing up configuration files..."

CONFIG_BACKUP_FILE="$BACKUP_DIR/config_backup_$DATE.tar.gz"

tar -czf "$CONFIG_BACKUP_FILE" \
    -C "$APP_DIR" \
    includes/config.php \
    .htaccess \
    2>&1

if [ $? -eq 0 ]; then
    log_message "✓ Configuration backup completed: config_backup_$DATE.tar.gz"
else
    log_message "✗ Configuration backup failed!"
fi

###############################################################################
# 5. Cleanup Old Backups
###############################################################################

log_message "Cleaning up old backups (older than $RETENTION_DAYS days)..."

# Delete old database backups
find "$BACKUP_DIR/database" -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete
DELETED_DB=$(find "$BACKUP_DIR/database" -name "*.sql.gz" -mtime +$RETENTION_DAYS | wc -l)

# Delete old uploads backups
find "$BACKUP_DIR/uploads" -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
DELETED_UPLOADS=$(find "$BACKUP_DIR/uploads" -name "*.tar.gz" -mtime +$RETENTION_DAYS | wc -l)

# Delete old logs backups
find "$BACKUP_DIR/logs" -name "*.tar.gz" -mtime +$RETENTION_DAYS -delete
DELETED_LOGS=$(find "$BACKUP_DIR/logs" -name "*.tar.gz" -mtime +$RETENTION_DAYS | wc -l)

log_message "✓ Cleanup completed"
log_message "  Deleted: $DELETED_DB database backups, $DELETED_UPLOADS uploads backups, $DELETED_LOGS logs backups"

###############################################################################
# 6. Backup Summary
###############################################################################

log_message "=========================================="
log_message "Backup Summary:"
log_message "=========================================="
log_message "Database backups: $(ls -1 "$BACKUP_DIR/database" | wc -l) files"
log_message "Uploads backups: $(ls -1 "$BACKUP_DIR/uploads" | wc -l) files"
log_message "Logs backups: $(ls -1 "$BACKUP_DIR/logs" | wc -l) files"
log_message "Total backup size: $(du -sh "$BACKUP_DIR" | cut -f1)"
log_message "=========================================="
log_message "Backup process completed successfully!"
log_message "=========================================="

###############################################################################
# 7. Optional: Send notification (email, Telegram, etc.)
###############################################################################

# Uncomment to send email notification
# echo "Backup completed successfully at $(date)" | mail -s "Backup Success - Pajak System" admin@example.com

# Uncomment to send Telegram notification
# TELEGRAM_BOT_TOKEN="your_bot_token"
# TELEGRAM_CHAT_ID="your_chat_id"
# MESSAGE="✓ Backup completed successfully at $(date)"
# curl -s -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
#     -d chat_id="$TELEGRAM_CHAT_ID" \
#     -d text="$MESSAGE"

exit 0
