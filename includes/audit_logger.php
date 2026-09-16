<?php
// Author: Zeday @join.co.id
/**
 * Audit Logger
 * Centralized audit logging for all database operations
 * Tracks INSERT, UPDATE, DELETE operations with old/new values
 */

class AuditLogger {
    private $conn;
    private $userId;
    private $ipAddress;
    private $userAgent;
    
    /**
     * Constructor
     */
    public function __construct($conn, $userId = null) {
        $this->conn = $conn;
        $this->userId = $userId;
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        // Always trust REMOTE_ADDR as the authoritative IP.
        // XFF / CLIENT_IP headers are trivially spoofable and must not be trusted
        // unless the server sits behind a known reverse proxy (configure separately).
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $ipAddress;
        }

        return 'Unknown';
    }
    
    /**
     * Log an action
     * 
     * @param string $action Action type (INSERT, UPDATE, DELETE, LOGIN, LOGOUT, etc.)
     * @param string $tableName Table name affected
     * @param int $recordId Record ID affected
     * @param array|null $oldValues Old values (for UPDATE/DELETE)
     * @param array|null $newValues New values (for INSERT/UPDATE)
     * @param string|null $description Human-readable description
     * @param array|null $requestData Additional request data
     * @param array|null $responseData Response data
     * @param float|null $executionTime Execution time in seconds
     * @return bool Success status
     */
    public function log(
        $action,
        $tableName,
        $recordId,
        $oldValues = null,
        $newValues = null,
        $description = null,
        $requestData = null,
        $responseData = null,
        $executionTime = null
    ) {
        try {
            // Prepare values for JSON encoding
            $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
            $requestDataJson = $requestData ? json_encode($requestData, JSON_UNESCAPED_UNICODE) : null;
            $responseDataJson = $responseData ? json_encode($responseData, JSON_UNESCAPED_UNICODE) : null;
            
            // Prepare SQL statement
            $sql = "INSERT INTO audit_log (
                user_id,
                action,
                table_name,
                record_id,
                old_values,
                new_values,
                description,
                request_data,
                response_data,
                execution_time,
                ip_address,
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                error_log("Audit log prepare failed: " . $this->conn->error);
                return false;
            }
            
            $stmt->bind_param(
                "issississdss",
                $this->userId,
                $action,
                $tableName,
                $recordId,
                $oldValuesJson,
                $newValuesJson,
                $description,
                $requestDataJson,
                $responseDataJson,
                $executionTime,
                $this->ipAddress,
                $this->userAgent
            );
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Audit log execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Audit log exception: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log INSERT operation
     */
    public function logInsert($tableName, $recordId, $newValues, $description = null) {
        return $this->log(
            'INSERT',
            $tableName,
            $recordId,
            null,
            $newValues,
            $description ?? "New record created in {$tableName}"
        );
    }
    
    /**
     * Log UPDATE operation
     */
    public function logUpdate($tableName, $recordId, $oldValues, $newValues, $description = null) {
        // Calculate what changed
        $changes = [];
        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            if ($oldValue != $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
            }
        }
        
        $changeCount = count($changes);
        $defaultDescription = "Updated {$changeCount} field(s) in {$tableName}";
        
        return $this->log(
            'UPDATE',
            $tableName,
            $recordId,
            $oldValues,
            $newValues,
            $description ?? $defaultDescription
        );
    }
    
    /**
     * Log DELETE operation (soft delete)
     */
    public function logDelete($tableName, $recordId, $oldValues, $description = null) {
        return $this->log(
            'DELETE',
            $tableName,
            $recordId,
            $oldValues,
            null,
            $description ?? "Record soft deleted from {$tableName}"
        );
    }
    
    /**
     * Log RESTORE operation
     */
    public function logRestore($tableName, $recordId, $values, $description = null) {
        return $this->log(
            'RESTORE',
            $tableName,
            $recordId,
            null,
            $values,
            $description ?? "Record restored in {$tableName}"
        );
    }
    
    /**
     * Log LOGIN operation
     */
    public function logLogin($userId, $username, $success = true) {
        $description = $success 
            ? "User '{$username}' logged in successfully"
            : "Failed login attempt for user '{$username}'";
        
        return $this->log(
            $success ? 'LOGIN_SUCCESS' : 'LOGIN_FAILED',
            'users',
            $userId ?? 0,
            null,
            ['username' => $username],
            $description
        );
    }
    
    /**
     * Log LOGOUT operation
     */
    public function logLogout($userId, $username) {
        return $this->log(
            'LOGOUT',
            'users',
            $userId,
            null,
            ['username' => $username],
            "User '{$username}' logged out"
        );
    }
    
    /**
     * Log EXPORT operation
     */
    public function logExport($exportType, $recordCount, $filters = null) {
        return $this->log(
            'EXPORT',
            'wajib_pajak',
            0,
            null,
            [
                'export_type' => $exportType,
                'record_count' => $recordCount,
                'filters' => $filters
            ],
            "Exported {$recordCount} records as {$exportType}"
        );
    }
    
    /**
     * Log BACKUP operation
     */
    public function logBackup($backupId, $filename, $filesize, $success = true) {
        $description = $success
            ? "Database backup created: {$filename}"
            : "Database backup failed: {$filename}";
        
        return $this->log(
            $success ? 'BACKUP_SUCCESS' : 'BACKUP_FAILED',
            'backups',
            $backupId,
            null,
            [
                'filename' => $filename,
                'filesize' => $filesize
            ],
            $description
        );
    }
    
    /**
     * Log RESTORE operation
     */
    public function logBackupRestore($backupId, $filename, $success = true) {
        $description = $success
            ? "Database restored from backup: {$filename}"
            : "Database restore failed: {$filename}";
        
        return $this->log(
            $success ? 'RESTORE_SUCCESS' : 'RESTORE_FAILED',
            'backups',
            $backupId,
            null,
            ['filename' => $filename],
            $description
        );
    }
    
    /**
     * Log BULK operation
     */
    public function logBulkOperation($operation, $tableName, $recordIds, $description = null) {
        $count = count($recordIds);
        $defaultDescription = "Bulk {$operation} on {$count} records in {$tableName}";
        
        return $this->log(
            'BULK_' . strtoupper($operation),
            $tableName,
            0,
            null,
            ['record_ids' => $recordIds, 'count' => $count],
            $description ?? $defaultDescription
        );
    }
    
    /**
     * Log PASSWORD_CHANGE operation
     */
    public function logPasswordChange($userId, $username, $success = true) {
        $description = $success
            ? "Password changed successfully for user '{$username}'"
            : "Password change failed for user '{$username}'";
        
        return $this->log(
            $success ? 'PASSWORD_CHANGE_SUCCESS' : 'PASSWORD_CHANGE_FAILED',
            'users',
            $userId,
            null,
            ['username' => $username],
            $description
        );
    }
    
    /**
     * Log SECURITY event
     */
    public function logSecurityEvent($eventType, $description, $data = null) {
        return $this->log(
            'SECURITY_' . strtoupper($eventType),
            'security',
            0,
            null,
            $data,
            $description
        );
    }
    
    /**
     * Get audit logs with filters
     * 
     * @param array $filters Filters (user_id, action, table_name, date_from, date_to)
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Audit logs
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0) {
        $sql = "SELECT 
                    al.*,
                    u.username,
                    DATE_FORMAT(al.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
                FROM audit_log al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
            $types .= "s";
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND al.table_name = ?";
            $params[] = $filters['table_name'];
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (al.description LIKE ? OR u.username LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return [];
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            // Decode JSON fields
            if ($row['old_values']) {
                $row['old_values'] = json_decode($row['old_values'], true);
            }
            if ($row['new_values']) {
                $row['new_values'] = json_decode($row['new_values'], true);
            }
            if ($row['request_data']) {
                $row['request_data'] = json_decode($row['request_data'], true);
            }
            if ($row['response_data']) {
                $row['response_data'] = json_decode($row['response_data'], true);
            }
            
            $logs[] = $row;
        }
        
        $stmt->close();
        
        return $logs;
    }
    
    /**
     * Get total count of logs with filters
     */
    public function getLogsCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM audit_log al WHERE 1=1";
        
        $params = [];
        $types = "";
        
        // Apply same filters as getLogs
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= "i";
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
            $types .= "s";
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND al.table_name = ?";
            $params[] = $filters['table_name'];
            $types .= "s";
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= "s";
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= "s";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return 0;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row['total'] ?? 0;
    }
    
    /**
     * Clean old audit logs (retention policy)
     * 
     * @param int $days Number of days to keep
     * @return int Number of deleted records
     */
    public function cleanOldLogs($days = 90) {
        $sql = "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Log the cleanup
        $this->log(
            'CLEANUP',
            'audit_log',
            0,
            null,
            ['deleted_count' => $affected, 'retention_days' => $days],
            "Cleaned {$affected} old audit log entries (older than {$days} days)"
        );
        
        return $affected;
    }
}
