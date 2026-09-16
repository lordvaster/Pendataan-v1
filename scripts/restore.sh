#!/bin/bash
# Author: Zeday @join.co.id

###############################################################################
# Restore Script
# Sistem Pendataan Wajib Pajak Kendaraan
# 
# This script restores backups of:
# 1. Database
# 2. Uploaded files
# 3. Logs (optional)
#
# Usage: ./restore.sh [backup_date]
# Example: ./restore.sh 20251219_020000
###############################################################################

# Configuration
APP_DIR="/var/www/pendataan/PAJAK"
BACKUP_DIR="/var/backups/pajak"

# Database configuration
DB_NAME="db_pajak_kendaraan"
DB_USER="root"
DB_PASS=""  # Set this or read from .env

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored messages
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${NC}ℹ $1${NC}"
}

###############################################################################
# Check if backup date is provided
###############################################################################

if [ -z "$1" ]; then
    print_error "Backup date not provided!"
    echo ""
    echo "Usage: $0 [backup_date]"
    echo "Example: $0 20251219_020000"
    echo ""
    echo "Available backups:"
    echo "=================="
    ls -1 "$BACKUP_DIR/database" | grep "db_backup_" | sed 's/db_backup_//' | sed 's/.sql.gz//'
    exit 1
fi

BACKUP_DATE=$1

###############################################################################
# Verify backup files exist
###############################################################################

DB_BACKUP_FILE="$BACKUP_DIR/database/db_backup_$BACKUP_DATE.sql.gz"
UPLOADS_BACKUP_FILE="$BACKUP_DIR/uploads/uploads_backup_$BACKUP_DATE.tar.gz"

print_info "Checking backup files..."

if [ ! -f "$DB_BACKUP_FILE" ]; then
    print_error "Database backup not found: $DB_BACKUP_FILE"
    exit 1
fi

if [ ! -f "$UPLOADS_BACKUP_FILE" ]; then
    print_warning "Uploads backup not found: $UPLOADS_BACKUP_FILE"
    print_warning "Will skip uploads restore"
fi

###############################################################################
# Confirmation
###############################################################################

echo ""
echo "=========================================="
echo "RESTORE CONFIRMATION"
echo "=========================================="
echo "This will restore the following:"
echo "  - Database: $DB_BACKUP_FILE"
if [ -f "$UPLOADS_BACKUP_FILE" ]; then
    echo "  - Uploads: $UPLOADS_BACKUP_FILE"
fi
echo ""
print_warning "WARNING: This will OVERWRITE current data!"
echo ""
read -p "Are you sure you want to continue? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    print_info "Restore cancelled."
    exit 0
fi

###############################################################################
# Create backup of current state before restore
###############################################################################

print_info "Creating backup of current state..."

CURRENT_BACKUP_DIR="$BACKUP_DIR/pre_restore_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$CURRENT_BACKUP_DIR"

# Backup current database
if [ -z "$DB_PASS" ]; then
    mysqldump -u "$DB_USER" "$DB_NAME" | gzip > "$CURRENT_BACKUP_DIR/current_db.sql.gz"
else
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > "$CURRENT_BACKUP_DIR/current_db.sql.gz"
fi

# Backup current uploads
tar -czf "$CURRENT_BACKUP_DIR/current_uploads.tar.gz" -C "$APP_DIR" uploads/

print_success "Current state backed up to: $CURRENT_BACKUP_DIR"

###############################################################################
# 1. Restore Database
###############################################################################

print_info "Restoring database..."

# Decompress SQL file
gunzip -c "$DB_BACKUP_FILE" > /tmp/restore_temp.sql

# Restore database
if [ -z "$DB_PASS" ]; then
    mysql -u "$DB_USER" "$DB_NAME" < /tmp/restore_temp.sql
else
    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < /tmp/restore_temp.sql
fi

if [ $? -eq 0 ]; then
    print_success "Database restored successfully"
    rm /tmp/restore_temp.sql
else
    print_error "Database restore failed!"
    print_info "Rolling back..."
    
    # Rollback database
    gunzip -c "$CURRENT_BACKUP_DIR/current_db.sql.gz" | mysql -u "$DB_USER" "$DB_NAME"
    
    rm /tmp/restore_temp.sql
    exit 1
fi

###############################################################################
# 2. Restore Uploads
###############################################################################

if [ -f "$UPLOADS_BACKUP_FILE" ]; then
    print_info "Restoring uploaded files..."
    
    # Backup current uploads directory
    if [ -d "$APP_DIR/uploads" ]; then
        mv "$APP_DIR/uploads" "$APP_DIR/uploads.old"
    fi
    
    # Extract uploads
    tar -xzf "$UPLOADS_BACKUP_FILE" -C "$APP_DIR"
    
    if [ $? -eq 0 ]; then
        print_success "Uploads restored successfully"
        
        # Remove old uploads backup
        rm -rf "$APP_DIR/uploads.old"
    else
        print_error "Uploads restore failed!"
        
        # Rollback uploads
        if [ -d "$APP_DIR/uploads.old" ]; then
            rm -rf "$APP_DIR/uploads"
            mv "$APP_DIR/uploads.old" "$APP_DIR/uploads"
        fi
    fi
fi

###############################################################################
# 3. Set Permissions
###############################################################################

print_info "Setting correct permissions..."

chown -R www-data:www-data "$APP_DIR/uploads"
chmod -R 755 "$APP_DIR/uploads"
find "$APP_DIR/uploads" -type f -exec chmod 644 {} \;

print_success "Permissions set"

###############################################################################
# 4. Verify Restore
###############################################################################

print_info "Verifying restore..."

# Check database
DB_COUNT=$(mysql -u "$DB_USER" -N -e "SELECT COUNT(*) FROM wajib_pajak" "$DB_NAME" 2>/dev/null)

if [ $? -eq 0 ]; then
    print_success "Database verification: $DB_COUNT records found"
else
    print_error "Database verification failed!"
fi

# Check uploads
if [ -d "$APP_DIR/uploads/ktp" ] && [ -d "$APP_DIR/uploads/stnk" ] && [ -d "$APP_DIR/uploads/kendaraan" ]; then
    UPLOAD_COUNT=$(find "$APP_DIR/uploads" -type f | wc -l)
    print_success "Uploads verification: $UPLOAD_COUNT files found"
else
    print_warning "Uploads directories not found or incomplete"
fi

###############################################################################
# Summary
###############################################################################

echo ""
echo "=========================================="
echo "RESTORE SUMMARY"
echo "=========================================="
print_success "Restore completed successfully!"
echo ""
echo "Restored from backup: $BACKUP_DATE"
echo "Database records: $DB_COUNT"
echo "Uploaded files: $UPLOAD_COUNT"
echo ""
echo "Pre-restore backup saved to:"
echo "  $CURRENT_BACKUP_DIR"
echo ""
print_info "Please verify the application is working correctly"
echo "=========================================="

exit 0
