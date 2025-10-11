<?php
/**
 * ORRISM WHMCS Database Helper
 * Provides database access using WHMCS native connections
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * ORRISM Database Helper Class
 */
class OrrisDatabase
{
    private static $instance = null;
    private $useOrrisDB = false;

    /**
     * Get singleton instance
     *
     * @return OrrisDatabase
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Check if OrrisDB is available
     */
    public function __construct()
    {
        // Load OrrisDB if available
        $orrisDbPath = __DIR__ . '/orris_db.php';
        if (file_exists($orrisDbPath)) {
            require_once $orrisDbPath;
        }

        // Check if we should use OrrisDB
        $this->useOrrisDB = class_exists('OrrisDB') && OrrisDB::isConfigured();

        if ($this->useOrrisDB) {
            logModuleCall('orrism', 'OrrisDatabase', [], 'Using OrrisDB for database operations');
        } else {
            logModuleCall('orrism', 'OrrisDatabase', [], 'Using WHMCS Capsule for database operations');
        }
    }

    /**
     * Check if ORRISM tables are installed
     *
     * @return bool
     */
    private function tablesExist()
    {
        try {
            if ($this->useOrrisDB) {
                // Check in OrrisDB (separate database)
                $schema = OrrisDB::schema();
                if (!$schema) {
                    return false;
                }
                return $schema->hasTable('services') &&
                       $schema->hasTable('nodes') &&
                       $schema->hasTable('node_groups');
            } else {
                // Check in WHMCS database
                return Capsule::schema()->hasTable('services') &&
                       Capsule::schema()->hasTable('nodes') &&
                       Capsule::schema()->hasTable('node_groups');
            }
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Error checking tables: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get friendly error message when tables don't exist
     *
     * @return string
     */
    private function getTablesNotInstalledMessage()
    {
        return 'ORRISM database tables not installed. Please install database from: Addons > ORRISM Admin > Settings > Install Database';
    }
    
    /**
     * Get database connection (OrrisDB or WHMCS Capsule)
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        if ($this->useOrrisDB) {
            return OrrisDB::connection();
        }
        return Capsule::connection();
    }

    /**
     * Get database table builder
     *
     * @param string $table Table name
     * @return \Illuminate\Database\Query\Builder
     */
    private function table($table)
    {
        if ($this->useOrrisDB) {
            return OrrisDB::table($table);
        }
        return Capsule::table($table);
    }
    
    /**
     * Create or update service account
     * 
     * @param array $params Module parameters
     * @return array Result with service data or error
     */
    public function createService(array $params)
    {
        try {
            // Check if ORRISM tables are installed
            if (!$this->tablesExist()) {
                return [
                    'success' => false,
                    'message' => $this->getTablesNotInstalledMessage()
                ];
            }

            $serviceid = $params['serviceid'];
            $clientid = $params['userid'];
            $email = $params['clientsdetails']['email'];
            $bandwidth = ($params['configoption2'] ?: 100) * 1024 * 1024 * 1024; // Convert GB to bytes - configoption2 = Monthly Bandwidth (GB)
            $nodeGroup = $params['configoption1'] ?: 1; // configoption1 = Node Group ID

            // Generate UUID
            $uuid = $this->generateUUID();

            // Check if service already exists
            $existingService = $this->table('services')
                ->where('service_id', $serviceid)
                ->first();

            if ($existingService) {
                return [
                    'success' => false,
                    'message' => 'Service account already exists'
                ];
            }

            // Get WHMCS client info (always from WHMCS database)
            $client = Capsule::table('tblclients')
                ->where('id', $clientid)
                ->first();

            // Generate service credentials
            $servicePassword = $this->generatePassword();

            // Create new service (matching actual table structure)
            $serviceId = $this->table('services')->insertGetId([
                'service_id' => $serviceid,
                'email' => $email,
                'uuid' => $uuid,
                'password' => password_hash($servicePassword, PASSWORD_DEFAULT),
                'password_algo' => 'bcrypt',
                'upload_bytes' => 0,
                'download_bytes' => 0,
                'bandwidth_limit' => $bandwidth,
                'node_group_id' => $nodeGroup,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update WHMCS service with credentials
            // Note: service_username is the email, service_password is the generated password
            $this->saveCustomField($serviceid, 'uuid', $uuid);
            $this->saveCustomField($serviceid, 'service_username', $email);
            $this->saveCustomField($serviceid, 'service_password', $servicePassword);
            
            return [
                'success' => true,
                'service_id' => $serviceId,
                'uuid' => $uuid,
                'username' => $email,  // Email is used as username in the new schema
                'password' => $servicePassword,
                'message' => 'Service account created successfully'
            ];
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, $params, 'Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create service: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get service by service ID
     *
     * @param int $serviceid WHMCS service ID
     * @return object|null Service data
     */
    public function getUser($serviceid)
    {
        try {
            // Check if table exists
            if (!$this->tablesExist()) {
                logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $this->getTablesNotInstalledMessage());
                return null;
            }

            return $this->table('services')
                ->where('service_id', $serviceid)
                ->first();
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Alias for getUser - Get service by service ID
     *
     * @param int $serviceid WHMCS service ID
     * @return object|null Service data
     */
    public function getService($serviceid)
    {
        return $this->getUser($serviceid);
    }
    
    /**
     * Update service status
     *
     * @param int $serviceid Service ID
     * @param string $status New status (active, suspended, expired, banned, pending)
     * @return bool Success status
     */
    public function updateServiceStatus($serviceid, $status)
    {
        try {
            // Check if table exists
            if (!$this->tablesExist()) {
                logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid, 'status' => $status], 'Error: ' . $this->getTablesNotInstalledMessage());
                return false;
            }

            $updated = $this->table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            return $updated > 0;

        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid, 'status' => $status], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete service account
     * 
     * @param int $serviceid Service ID
     * @return bool Success status
     */
    public function deleteService($serviceid)
    {
        try {
            return $this->table('services')
                ->where('service_id', $serviceid)
                ->delete() > 0;
                
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset service traffic
     *
     * @param int $serviceid Service ID
     * @return bool Success status
     */
    public function resetServiceTraffic($serviceid)
    {
        try {
            $updated = $this->table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'upload_bytes' => 0,
                    'download_bytes' => 0,
                    'last_reset_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return $updated > 0;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user UUID
     * 
     * @param int $serviceid Service ID
     * @return array Result with new UUID or error
     */
    public function regenerateCredentials($serviceid)
    {
        try {
            $newUuid = $this->generateUUID();
            $newPassword = $this->generatePassword();
            $newUsername = 'orrism_' . $serviceid;
            
            $updated = $this->table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'uuid' => $newUuid,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            if ($updated > 0) {
                // Update custom field
                $this->saveCustomField($serviceid, 'uuid', $newUuid);
                
                return [
                    'success' => true,
                    'uuid' => $newUuid,
                    'message' => 'UUID regenerated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to regenerate UUID: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update user package settings
     * 
     * @param int $serviceid Service ID
     * @param array $params New package parameters
     * @return bool Success status
     */
    public function updateUserPackage($serviceid, array $params)
    {
        try {
            $bandwidth = ($params['configoption2'] ?: 100) * 1024 * 1024 * 1024; // Convert GB to bytes - configoption2 = Monthly Bandwidth (GB)
            $nodeGroup = $params['configoption1'] ?: 1; // configoption1 = Node Group ID

            $updated = $this->table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'bandwidth_limit' => $bandwidth,
                    'node_group_id' => $nodeGroup,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                
            return $updated > 0;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get service usage statistics
     * 
     * @param int $serviceid Service ID
     * @return array Usage statistics
     */
    public function getServiceUsage($serviceid)
    {
        try {
            $service = $this->getService($serviceid);
            if (!$service) {
                return [];
            }
            
            $totalGB = round($service->bandwidth_limit / 1024 / 1024 / 1024, 2);
            $usedGB = round(($service->upload_bytes + $service->download_bytes) / 1024 / 1024 / 1024, 2);
            $remainingGB = max(0, $totalGB - $usedGB);
            $usagePercent = $totalGB > 0 ? round($usedGB / $totalGB * 100, 2) : 0;
            
            return [
                'total_bandwidth' => $totalGB,
                'used_bandwidth' => $usedGB,
                'remaining_bandwidth' => $remainingGB,
                'usage_percent' => $usagePercent,
                'upload_gb' => round($service->upload_bytes / 1024 / 1024 / 1024, 2),
                'download_gb' => round($service->download_bytes / 1024 / 1024 / 1024, 2),
                'status' => $service->status,
                'last_reset' => $service->last_reset_at
            ];
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active nodes for a user's group
     * 
     * @param int $nodeGroupId Node group ID
     * @return array List of active nodes
     */
    public function getNodesForGroup($nodeGroupId)
    {
        try {
            return $this->table('nodes')
                ->where('group_id', $nodeGroupId)
                ->where('status', 1)
                ->orderBy('sort_order')
                ->orderBy('node_name')
                ->get()
                ->toArray();
                
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['group_id' => $nodeGroupId], 'Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Record service traffic usage
     *
     * @param int $serviceid Service ID
     * @param int $nodeId Node ID
     * @param int $uploadBytes Upload bytes
     * @param int $downloadBytes Download bytes
     * @param string $clientIp Client IP address
     * @return bool Success status
     */
    public function recordUsage($serviceid, $nodeId, $uploadBytes, $downloadBytes, $clientIp = null)
    {
        try {
            $service = $this->getUser($serviceid); // getUser actually gets service data
            if (!$service) {
                return false;
            }

            // Insert usage record
            $this->table('service_usage')->insert([
                'service_id' => $service->id,
                'node_id' => $nodeId,
                'upload_bytes' => $uploadBytes,
                'download_bytes' => $downloadBytes,
                'session_start' => date('Y-m-d H:i:s'),
                'session_end' => date('Y-m-d H:i:s'),
                'client_ip' => $clientIp,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update service totals
            $this->table('services')
                ->where('id', $service->id)
                ->increment('upload_bytes', $uploadBytes);

            $this->table('services')
                ->where('id', $service->id)
                ->increment('download_bytes', $downloadBytes);

            return true;

        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate UUID v4
     * 
     * @return string UUID
     */
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Generate a secure password
     * 
     * @param int $length Password length
     * @return string Generated password
     */
    private function generatePassword($length = 16)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
    
    /**
     * Save custom field value
     * 
     * @param int $serviceid Service ID
     * @param string $fieldname Field name
     * @param string $value Field value
     */
    private function saveCustomField($serviceid, $fieldname, $value)
    {
        try {
            // Get custom field ID
            $field = Capsule::table('tblcustomfields')
                ->where('type', 'product')
                ->where('fieldname', $fieldname)
                ->first();
                
            if ($field) {
                // Update or insert custom field value
                Capsule::table('tblcustomfieldsvalues')
                    ->updateOrInsert(
                        ['fieldid' => $field->id, 'relid' => $serviceid],
                        ['value' => $value]
                    );
            }
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid, 'field' => $fieldname], 'Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed Configuration value
     */
    public function getConfig($key, $default = null)
    {
        try {
            $config = $this->table('config')
                ->where('config_key', $key)
                ->first();
                
            if (!$config) {
                return $default;
            }
            
            // Convert value based on type
            switch ($config->config_type) {
                case 'boolean':
                    return filter_var($config->config_value, FILTER_VALIDATE_BOOLEAN);
                case 'integer':
                    return intval($config->config_value);
                case 'json':
                    return json_decode($config->config_value, true);
                default:
                    return $config->config_value;
            }
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['key' => $key], 'Error: ' . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @param string $type Value type
     * @param string $category Configuration category
     * @param string $description Configuration description
     * @param bool $isSystem Is system configuration
     * @return bool Success status
     */
    public function setConfig($key, $value, $type = 'string', $category = 'general', $description = null, $isSystem = false)
    {
        try {
            // Convert value based on type
            switch ($type) {
                case 'boolean':
                    $value = $value ? '1' : '0';
                    break;
                case 'json':
                    $value = json_encode($value);
                    break;
                default:
                    $value = strval($value);
            }

            $updated = $this->table('config')
                ->where('config_key', $key)
                ->update([
                    'config_value' => $value,
                    'config_type' => $type,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

            // If no rows updated, insert new config
            if ($updated === 0) {
                $this->table('config')->insert([
                    'config_key' => $key,
                    'config_value' => $value,
                    'config_type' => $type,
                    'category' => $category,
                    'description' => $description,
                    'is_system' => $isSystem ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }

            return true;

        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['key' => $key], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get count of active services for a specific module
     * 
     * @param string $moduleName Module name
     * @return int Number of active services
     */
    public function getActiveServiceCount($moduleName = 'orrism')
    {
        try {
            return Capsule::table('tblhosting')
                ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
                ->where('tblproducts.servertype', $moduleName)
                ->whereIn('tblhosting.domainstatus', ['Active', 'Suspended'])
                ->count();
                
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['module' => $moduleName], 'Error: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Helper function to get database instance
 * 
 * @return OrrisDatabase
 */
function db()
{
    return OrrisDatabase::getInstance();
}