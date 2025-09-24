<?php
/**
 * ORRISM Service Manager
 * Handles service management, traffic management, and service operations
 *
 * @package    WHMCS\Module\Addon\OrrisAdmin\Admin
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    1.0
 */

namespace WHMCS\Module\Addon\OrrisAdmin\Admin;

use WHMCS\Database\Capsule;
use Exception;
use PDO;

/**
 * Service Manager Class
 * Provides centralized service management functionality for ORRISM system
 */
class ServiceManager
{
    /**
     * Database connection instance
     * @var PDO|null
     */
    private $orrisDb = null;
    
    /**
     * Settings cache
     * @var array
     */
    private $settings = [];
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->loadSettings();
        $this->initializeDatabase();
    }
    
    /**
     * Load settings from database
     */
    private function loadSettings()
    {
        try {
            // Load settings from WHMCS addon modules table
            $result = Capsule::table('tbladdonmodules')
                ->where('module', 'orrism_admin')
                ->pluck('value', 'setting');
            
            $this->settings = $result->toArray();
        } catch (Exception $e) {
            $this->logError('Failed to load settings', $e);
        }
    }
    
    /**
     * Initialize ORRISM database connection
     */
    private function initializeDatabase()
    {
        try {
            if (!empty($this->settings['database_host']) && 
                !empty($this->settings['database_name']) &&
                !empty($this->settings['database_user'])) {
                
                $host = $this->settings['database_host'];
                $port = $this->settings['database_port'] ?? '3306';
                $dbname = $this->settings['database_name'];
                $user = $this->settings['database_user'];
                $pass = $this->settings['database_password'] ?? '';
                
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8";
                
                $this->orrisDb = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            }
        } catch (Exception $e) {
            $this->logError('Failed to connect to ORRISM database', $e);
        }
    }
    
    /**
     * Get server list (alias for getServiceList)
     * 
     * @param array $filters Filter options
     * @return array Server list with pagination info
     */
    public function getServerList($filters = [])
    {
        // Get services and format them as servers
        $result = $this->getServiceList($filters);
        
        // Rename key for consistency
        if (isset($result['services'])) {
            $result['servers'] = $result['services'];
            unset($result['services']);
        }
        
        return $result;
    }
    
    /**
     * Synchronize WHMCS users with ORRISM system
     * 
     * @param array $options Synchronization options
     * @return array Result with success status and message
     */
    public function syncUsers($options = [])
    {
        try {
            if (!$this->orrisDb) {
                throw new Exception('ORRISM database not configured');
            }
            
            $syncAll = $options['sync_all'] ?? false;
            $serviceIds = $options['service_ids'] ?? [];
            
            // Get active ORRISM services from WHMCS
            $query = "
                SELECT 
                    h.id as service_id,
                    h.userid as client_id,
                    h.domain,
                    h.domainstatus as status,
                    h.packageid,
                    h.nextduedate,
                    h.billingcycle,
                    c.email,
                    c.firstname,
                    c.lastname,
                    p.configoption1 as bandwidth_gb,
                    p.configoption2 as node_group_id,
                    p.configoption3 as max_devices
                FROM tblhosting h
                JOIN tblclients c ON h.userid = c.id
                JOIN tblproducts p ON h.packageid = p.id
                WHERE p.servertype = 'orrism'
                AND h.domainstatus IN ('Active', 'Suspended')
            ";
            
            if (!$syncAll && !empty($serviceIds)) {
                $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
                $query .= " AND h.id IN ($placeholders)";
            }
            
            $stmt = Capsule::connection()->getPdo()->prepare($query);
            
            if (!$syncAll && !empty($serviceIds)) {
                $stmt->execute($serviceIds);
            } else {
                $stmt->execute();
            }
            
            $services = $stmt->fetchAll();
            
            $synced = 0;
            $failed = 0;
            $errors = [];
            
            foreach ($services as $service) {
                try {
                    $this->syncSingleUser($service);
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "Service #{$service['service_id']}: " . $e->getMessage();
                    $this->logError("Failed to sync service #{$service['service_id']}", $e);
                }
            }
            
            $message = "Synchronized $synced users successfully";
            if ($failed > 0) {
                $message .= ", $failed failed";
                if (count($errors) <= 3) {
                    $message .= ": " . implode('; ', $errors);
                }
            }
            
            // Update last sync time
            $this->updateSetting('last_sync', date('Y-m-d H:i:s'));
            
            return [
                'success' => $failed === 0,
                'message' => $message,
                'synced' => $synced,
                'failed' => $failed,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->logError('User synchronization failed', $e);
            return [
                'success' => false,
                'message' => 'Synchronization failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Sync a single user/service
     * 
     * @param array $service Service data from WHMCS
     */
    private function syncSingleUser($service)
    {
        // Check if user exists in ORRISM
        $stmt = $this->orrisDb->prepare("
            SELECT id, status, bandwidth_limit 
            FROM services 
            WHERE service_id = ?
        ");
        $stmt->execute([$service['service_id']]);
        $orrisUser = $stmt->fetch();
        
        // Calculate bandwidth limit in bytes
        $bandwidthGb = (int)($service['bandwidth_gb'] ?? 100);
        $bandwidthBytes = $bandwidthGb * 1024 * 1024 * 1024;
        
        // Map WHMCS status to ORRISM status
        $orrisStatus = $service['status'] === 'Active' ? 'active' : 'suspended';
        
        if ($orrisUser) {
            // Update existing user
            $stmt = $this->orrisDb->prepare("
                UPDATE services SET
                    email = ?,
                    bandwidth_limit = ?,
                    node_group_id = ?,
                    status = ?,
                    expired_at = ?,
                    updated_at = NOW()
                WHERE service_id = ?
            ");
            
            $stmt->execute([
                $service['email'],
                $bandwidthBytes,
                $service['node_group_id'] ?? 1,
                $orrisStatus,
                $service['nextduedate'],
                $service['service_id']
            ]);
            
        } else {
            // Create new user
            $uuid = $this->generateUuid();
            $password = $this->generatePassword();
            
            $stmt = $this->orrisDb->prepare("
                INSERT INTO services (
                    service_id, client_id, email, uuid, password_hash,
                    bandwidth_limit, node_group_id, status, expired_at,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $service['service_id'],
                $service['client_id'],
                $service['email'],
                $uuid,
                password_hash($password, PASSWORD_DEFAULT),
                $bandwidthBytes,
                $service['node_group_id'] ?? 1,
                $orrisStatus,
                $service['nextduedate']
            ]);
            
            // Store credentials in WHMCS custom fields if needed
            $this->storeCredentialsInWhmcs($service['service_id'], $uuid, $password);
        }
    }
    
    /**
     * Reset user traffic
     * 
     * @param array $options Reset options
     * @return array Result with success status and message
     */
    public function resetTraffic($options = [])
    {
        try {
            if (!$this->orrisDb) {
                throw new Exception('ORRISM database not configured');
            }
            
            $resetAll = $options['reset_all'] ?? false;
            $userIds = $options['user_ids'] ?? [];
            $resetDay = $options['reset_day'] ?? ($this->settings['reset_day'] ?? 1);
            
            // Build query based on options
            $query = "
                UPDATE services 
                SET 
                    upload_bytes = 0,
                    download_bytes = 0,
                    need_reset = 0,
                    last_reset_at = NOW()
                WHERE status = 'active'
            ";
            
            $params = [];
            
            if (!$resetAll) {
                if (!empty($userIds)) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    $query .= " AND id IN ($placeholders)";
                    $params = $userIds;
                } else {
                    // Reset based on reset day
                    $currentDay = (int)date('d');
                    if ($currentDay != $resetDay) {
                        return [
                            'success' => false,
                            'message' => "Traffic reset is scheduled for day $resetDay of the month"
                        ];
                    }
                    
                    // Only reset users who haven't been reset this month
                    $query .= " AND (last_reset_at IS NULL OR MONTH(last_reset_at) != MONTH(NOW()))";
                }
            }
            
            $stmt = $this->orrisDb->prepare($query);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            // Log traffic reset in usage history
            $this->logTrafficReset($resetAll ? null : $userIds);
            
            // Update last reset time
            $this->updateSetting('last_traffic_reset', date('Y-m-d H:i:s'));
            
            return [
                'success' => true,
                'message' => "Traffic reset successful for $affectedRows services",
                'affected_services' => $affectedRows
            ];
            
        } catch (Exception $e) {
            $this->logError('Traffic reset failed', $e);
            return [
                'success' => false,
                'message' => 'Traffic reset failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get service list with filtering and pagination
     * 
     * @param array $filters Filter options
     * @return array Service list with pagination info
     */
    public function getServiceList($filters = [])
    {
        try {
            if (!$this->orrisDb) {
                throw new Exception('ORRISM database not configured');
            }
            
            $page = max(1, (int)($filters['page'] ?? 1));
            $limit = max(10, min(100, (int)($filters['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $search = $filters['search'] ?? '';
            $status = $filters['status'] ?? '';
            $nodeGroupId = $filters['node_group_id'] ?? '';
            $sortBy = $filters['sort_by'] ?? 'id';
            $sortOrder = strtoupper($filters['sort_order'] ?? 'DESC');
            
            // Validate sort parameters
            $allowedSortColumns = ['id', 'whmcs_email', 'service_username', 'created_at', 'upload_bytes', 'download_bytes', 'status'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'id';
            }
            if (!in_array($sortOrder, ['ASC', 'DESC'])) {
                $sortOrder = 'DESC';
            }
            
            // Build query
            $query = "
                SELECT 
                    s.id,
                    s.service_id,
                    s.client_id,
                    s.domain,
                    s.whmcs_username,
                    s.whmcs_email,
                    s.service_username,
                    s.uuid,
                    s.upload_bytes,
                    s.download_bytes,
                    s.bandwidth_limit,
                    s.node_group_id,
                    s.status,
                    s.last_reset_at,
                    s.expired_at,
                    s.created_at,
                    s.updated_at,
                    ng.name as node_group_name,
                    (s.upload_bytes + s.download_bytes) as total_usage,
                    CASE 
                        WHEN s.bandwidth_limit > 0 THEN 
                            ROUND((s.upload_bytes + s.download_bytes) / s.bandwidth_limit * 100, 2)
                        ELSE 0
                    END as usage_percentage
                FROM services s
                LEFT JOIN node_groups ng ON s.node_group_id = ng.id
                WHERE 1=1
            ";
            
            $countQuery = "
                SELECT COUNT(*) as total
                FROM services s
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters
            if (!empty($search)) {
                $searchCondition = " AND (s.whmcs_email LIKE ? OR s.service_username LIKE ? OR s.uuid LIKE ? OR s.domain LIKE ?)";
                $query .= $searchCondition;
                $countQuery .= $searchCondition;
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            if (!empty($status)) {
                $statusCondition = " AND s.status = ?";
                $query .= $statusCondition;
                $countQuery .= $statusCondition;
                $params[] = $status;
            }
            
            if (!empty($nodeGroupId)) {
                $groupCondition = " AND s.node_group_id = ?";
                $query .= $groupCondition;
                $countQuery .= $groupCondition;
                $params[] = $nodeGroupId;
            }
            
            // Get total count
            $countStmt = $this->orrisDb->prepare($countQuery);
            $countStmt->execute($params);
            $totalCount = $countStmt->fetchColumn();
            
            // Add sorting and pagination
            $query .= " ORDER BY $sortBy $sortOrder LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            // Get users
            $stmt = $this->orrisDb->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            // Format traffic data
            foreach ($users as &$user) {
                $user['upload_formatted'] = $this->formatBytes($user['upload_bytes']);
                $user['download_formatted'] = $this->formatBytes($user['download_bytes']);
                $user['total_formatted'] = $this->formatBytes($user['total_usage']);
                $user['bandwidth_formatted'] = $this->formatBytes($user['bandwidth_limit']);
            }
            
            // Calculate pagination info
            $totalPages = ceil($totalCount / $limit);
            
            return [
                'success' => true,
                'services' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_count' => $totalCount,
                    'per_page' => $limit,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];
            
        } catch (Exception $e) {
            $this->logError('Failed to get service list', $e);
            return [
                'success' => false,
                'message' => 'Failed to get service list: ' . $e->getMessage(),
                'services' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_count' => 0,
                    'per_page' => 20
                ]
            ];
        }
    }
    
    /**
     * Get service statistics
     * 
     * @return array Service statistics
     */
    public function getServiceStatistics()
    {
        try {
            if (!$this->orrisDb) {
                return [
                    'total_services' => 0,
                    'active_services' => 0,
                    'suspended_services' => 0,
                    'total_traffic' => 0
                ];
            }
            
            $stmt = $this->orrisDb->query("
                SELECT 
                    COUNT(*) as total_services,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_services,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_services,
                    SUM(upload_bytes + download_bytes) as total_traffic
                FROM services
            ");
            
            $stats = $stmt->fetch();
            
            return [
                'total_services' => (int)$stats['total_services'],
                'active_services' => (int)$stats['active_services'],
                'suspended_services' => (int)$stats['suspended_services'],
                'total_traffic' => $this->formatBytes($stats['total_traffic'] ?? 0),
                'total_traffic_bytes' => (int)($stats['total_traffic'] ?? 0)
            ];
            
        } catch (Exception $e) {
            $this->logError('Failed to get service statistics', $e);
            return [
                'total_services' => 0,
                'active_services' => 0,
                'suspended_services' => 0,
                'total_traffic' => '0 B'
            ];
        }
    }
    
    /**
     * Update service status
     * 
     * @param int $serviceId Service ID
     * @param string $status New status
     * @return array Operation result
     */
    public function updateServiceStatus($serviceId, $status)
    {
        try {
            if (!$this->orrisDb) {
                throw new Exception('ORRISM database not configured');
            }
            
            $allowedStatuses = ['active', 'suspended', 'terminated'];
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception('Invalid status');
            }
            
            $stmt = $this->orrisDb->prepare("
                UPDATE services 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $serviceId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Service not found');
            }
            
            return [
                'success' => true,
                'message' => "Service status updated to $status"
            ];
            
        } catch (Exception $e) {
            $this->logError('Failed to update service status', $e);
            return [
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get node groups for user assignment
     * 
     * @return array Node groups
     */
    public function getNodeGroups()
    {
        try {
            if (!$this->orrisDb) {
                return [];
            }
            
            $stmt = $this->orrisDb->query("
                SELECT id, name, description, status
                FROM node_groups
                WHERE status = 1
                ORDER BY name
            ");
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            $this->logError('Failed to get node groups', $e);
            return [];
        }
    }
    
    /**
     * Generate UUID v4
     * 
     * @return string UUID
     */
    private function generateUuid()
    {
        $data = random_bytes(16);
        
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Generate secure password
     * 
     * @param int $length Password length
     * @return string Password
     */
    private function generatePassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Store credentials in WHMCS custom fields
     * 
     * @param int $serviceId Service ID
     * @param string $uuid UUID
     * @param string $password Password
     */
    private function storeCredentialsInWhmcs($serviceId, $uuid, $password)
    {
        try {
            // This would store the credentials in WHMCS custom fields
            // Implementation depends on WHMCS custom field configuration
            
            // For now, log the action
            $this->logActivity("Generated credentials for service #$serviceId");
            
        } catch (Exception $e) {
            $this->logError('Failed to store credentials in WHMCS', $e);
        }
    }
    
    /**
     * Log traffic reset action
     * 
     * @param array|null $userIds User IDs that were reset
     */
    private function logTrafficReset($userIds = null)
    {
        try {
            $message = 'Traffic reset performed';
            if ($userIds !== null) {
                $message .= ' for ' . count($userIds) . ' users';
            } else {
                $message .= ' for all active users';
            }
            
            $this->logActivity($message);
            
        } catch (Exception $e) {
            // Silent fail for logging
        }
    }
    
    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes Bytes
     * @param int $precision Decimal precision
     * @return string Formatted string
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Update setting in database
     * 
     * @param string $key Setting key
     * @param string $value Setting value
     */
    private function updateSetting($key, $value)
    {
        try {
            // Update setting in WHMCS addon modules table
            Capsule::table('tbladdonmodules')
                ->updateOrInsert(
                    ['module' => 'orrism_admin', 'setting' => $key],
                    ['value' => $value]
                );
            
        } catch (Exception $e) {
            $this->logError('Failed to update setting', $e);
        }
    }
    
    /**
     * Log activity to WHMCS activity log
     * 
     * @param string $message Activity message
     */
    private function logActivity($message)
    {
        if (function_exists('logActivity')) {
            logActivity('[ORRISM UserManager] ' . $message);
        }
    }
    
    /**
     * Log error
     * 
     * @param string $message Error message
     * @param Exception $exception Exception object
     */
    private function logError($message, Exception $exception)
    {
        $errorMessage = $message . ': ' . $exception->getMessage();
        
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'orrism_admin',
                'UserManager::' . debug_backtrace()[1]['function'],
                [],
                $errorMessage
            );
        }
        
        error_log('[ORRISM UserManager] ' . $errorMessage);
    }
}