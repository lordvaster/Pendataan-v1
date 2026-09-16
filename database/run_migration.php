<?php
// Author: Zeday @join.co.id
/**
 * Database Migration Runner
 * Safely applies database migrations with backup and rollback support
 */

// Prevent direct access from web
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line.\n");
}

// Load configuration
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';

// ANSI color codes for terminal output
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const MAGENTA = "\033[35m";
    const CYAN = "\033[36m";
    const WHITE = "\033[37m";
    const BOLD = "\033[1m";
}

/**
 * Print colored message
 */
function printMessage($message, $color = Colors::WHITE, $bold = false) {
    $prefix = $bold ? Colors::BOLD : '';
    echo $prefix . $color . $message . Colors::RESET . "\n";
}

/**
 * Print header
 */
function printHeader($title) {
    echo "\n";
    printMessage(str_repeat("=", 70), Colors::CYAN);
    printMessage($title, Colors::CYAN, true);
    printMessage(str_repeat("=", 70), Colors::CYAN);
    echo "\n";
}

/**
 * Print section
 */
function printSection($title) {
    echo "\n";
    printMessage(">>> " . $title, Colors::BLUE, true);
}

/**
 * Print success
 */
function printSuccess($message) {
    printMessage("✓ " . $message, Colors::GREEN);
}

/**
 * Print error
 */
function printError($message) {
    printMessage("✗ " . $message, Colors::RED, true);
}

/**
 * Print warning
 */
function printWarning($message) {
    printMessage("⚠ " . $message, Colors::YELLOW);
}

/**
 * Print info
 */
function printInfo($message) {
    printMessage("ℹ " . $message, Colors::CYAN);
}

/**
 * Ask for confirmation
 */
function confirm($question) {
    printMessage($question . " (yes/no): ", Colors::YELLOW, true);
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    return strtolower($line) === 'yes' || strtolower($line) === 'y';
}

/**
 * Create backup before migration
 */
function createBackup($conn) {
    printSection("Creating Database Backup");
    
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . '/backup_before_migration_' . $timestamp . '.sql';
    
    $command = sprintf(
        'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($backupFile)
    );
    
    printInfo("Creating backup: " . basename($backupFile));
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile)) {
        $filesize = filesize($backupFile);
        printSuccess("Backup created successfully (" . formatBytes($filesize) . ")");
        return $backupFile;
    } else {
        printError("Failed to create backup");
        printError("Command output: " . implode("\n", $output));
        return false;
    }
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Run SQL file
 */
function runSQLFile($conn, $filepath) {
    if (!file_exists($filepath)) {
        printError("File not found: " . $filepath);
        return false;
    }
    
    printInfo("Reading SQL file: " . basename($filepath));
    $sql = file_get_contents($filepath);
    
    if ($sql === false) {
        printError("Failed to read SQL file");
        return false;
    }
    
    // Split SQL into individual statements
    $statements = preg_split('/;\s*$/m', $sql);
    $statements = array_filter($statements, function($stmt) {
        return trim($stmt) !== '';
    });
    
    printInfo("Found " . count($statements) . " SQL statements");
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS=0");
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        // Skip comments and empty statements
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        // Execute statement
        if ($conn->multi_query($statement . ';')) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
            
            $successCount++;
        } else {
            $errorCount++;
            $error = $conn->error;
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $error
            ];
            
            // Check if error is critical
            if (strpos($error, 'Duplicate column') === false && 
                strpos($error, 'already exists') === false) {
                printError("Critical error at statement " . ($index + 1) . ": " . $error);
            }
        }
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS=1");
    
    echo "\n";
    printInfo("Execution Summary:");
    printSuccess("Successful statements: " . $successCount);
    
    if ($errorCount > 0) {
        printWarning("Failed statements: " . $errorCount);
        
        if (!empty($errors)) {
            printWarning("\nErrors encountered:");
            foreach ($errors as $error) {
                printWarning("  - " . $error['error']);
            }
        }
    }
    
    return $errorCount === 0;
}

/**
 * Verify migration
 */
function verifyMigration($conn) {
    printSection("Verifying Migration");
    
    $checks = [
        'wajib_pajak columns' => "SHOW COLUMNS FROM wajib_pajak LIKE 'deleted_at'",
        'backups table' => "SHOW TABLES LIKE 'backups'",
        'user_preferences table' => "SHOW TABLES LIKE 'user_preferences'",
        'sessions table' => "SHOW TABLES LIKE 'sessions'",
        'notifications table' => "SHOW TABLES LIKE 'notifications'",
        'analytics_cache table' => "SHOW TABLES LIKE 'analytics_cache'",
        'export_logs table' => "SHOW TABLES LIKE 'export_logs'",
        'v_monthly_trends view' => "SHOW FULL TABLES WHERE TABLE_TYPE = 'VIEW' AND Tables_in_" . DB_NAME . " = 'v_monthly_trends'",
        'sp_get_dashboard_stats procedure' => "SHOW PROCEDURE STATUS WHERE Db = '" . DB_NAME . "' AND Name = 'sp_get_dashboard_stats'"
    ];
    
    $allPassed = true;
    
    foreach ($checks as $name => $query) {
        $result = $conn->query($query);
        if ($result && $result->num_rows > 0) {
            printSuccess($name . " - OK");
        } else {
            printError($name . " - FAILED");
            $allPassed = false;
        }
    }
    
    return $allPassed;
}

/**
 * Main migration process
 */
function runMigration() {
    global $conn;
    
    printHeader("PAJAK System - Database Migration Runner");
    
    // Check database connection
    printSection("Checking Database Connection");
    if (!$conn) {
        printError("Database connection failed: " . mysqli_connect_error());
        return false;
    }
    printSuccess("Connected to database: " . DB_NAME);
    
    // Show current database info
    $result = $conn->query("SELECT COUNT(*) as count FROM wajib_pajak");
    $row = $result->fetch_assoc();
    printInfo("Current records in wajib_pajak: " . $row['count']);
    
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    printInfo("Current users: " . $row['count']);
    
    // Warning
    echo "\n";
    printWarning("⚠️  WARNING: This migration will modify your database structure!");
    printWarning("⚠️  Make sure you have a recent backup before proceeding.");
    echo "\n";
    
    if (!confirm("Do you want to continue?")) {
        printInfo("Migration cancelled by user.");
        return false;
    }
    
    // Create backup
    $backupFile = createBackup($conn);
    if (!$backupFile) {
        printError("Cannot proceed without backup. Migration aborted.");
        return false;
    }
    
    // Run migration
    printSection("Running Migration");
    $migrationFile = __DIR__ . '/migrations/001_add_enhanced_features.sql';
    
    if (!runSQLFile($conn, $migrationFile)) {
        printError("Migration failed!");
        echo "\n";
        printWarning("You can restore from backup: " . $backupFile);
        printWarning("Run: mysql -u" . DB_USER . " -p " . DB_NAME . " < " . $backupFile);
        return false;
    }
    
    // Verify migration
    if (!verifyMigration($conn)) {
        printWarning("Some verification checks failed. Please review the output above.");
        return false;
    }
    
    // Success
    printHeader("Migration Completed Successfully!");
    printSuccess("Database has been upgraded to version 1.1.0");
    printSuccess("Backup saved at: " . $backupFile);
    
    printInfo("\nNext steps:");
    printInfo("1. Test the application thoroughly");
    printInfo("2. Update application code to use new features");
    printInfo("3. Keep the backup file for at least 30 days");
    
    return true;
}

/**
 * Rollback migration
 */
function rollbackMigration() {
    global $conn;
    
    printHeader("PAJAK System - Database Migration Rollback");
    
    printWarning("⚠️  WARNING: This will rollback all changes from the migration!");
    printWarning("⚠️  All data in new tables will be permanently deleted!");
    echo "\n";
    
    if (!confirm("Are you sure you want to rollback?")) {
        printInfo("Rollback cancelled by user.");
        return false;
    }
    
    // Create backup before rollback
    $backupFile = createBackup($conn);
    if (!$backupFile) {
        printError("Cannot proceed without backup. Rollback aborted.");
        return false;
    }
    
    // Run rollback
    printSection("Running Rollback");
    $rollbackFile = __DIR__ . '/migrations/001_rollback.sql';
    
    if (!runSQLFile($conn, $rollbackFile)) {
        printError("Rollback failed!");
        return false;
    }
    
    printHeader("Rollback Completed Successfully!");
    printSuccess("Database has been restored to previous state");
    printSuccess("Backup saved at: " . $backupFile);
    
    return true;
}

// Main execution
if ($argc < 2) {
    printHeader("PAJAK System - Database Migration Tool");
    echo "Usage:\n";
    echo "  php run_migration.php migrate   - Run migration\n";
    echo "  php run_migration.php rollback  - Rollback migration\n";
    echo "  php run_migration.php verify    - Verify migration status\n";
    exit(1);
}

$action = $argv[1];

switch ($action) {
    case 'migrate':
        $success = runMigration();
        exit($success ? 0 : 1);
        
    case 'rollback':
        $success = rollbackMigration();
        exit($success ? 0 : 1);
        
    case 'verify':
        printHeader("Verifying Migration Status");
        $success = verifyMigration($conn);
        exit($success ? 0 : 1);
        
    default:
        printError("Unknown action: " . $action);
        exit(1);
}
