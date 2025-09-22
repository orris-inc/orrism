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
     * Get WHMCS database connection
     * 
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return Capsule::connection();
    }
    
    /**
     * Create or update user account
     * 
     * @param array $params Module parameters
     * @return array Result with user data or error
     */
    public function createUser(array $params)
    {
        try {
            $serviceid = $params['serviceid'];
            $clientid = $params['userid'];
            $email = $params['clientsdetails']['email'];
            $bandwidth = ($params['configoption4'] ?: 100) * 1024 * 1024 * 1024; // Convert GB to bytes
            $nodeGroup = $params['configoption7'] ?: 1;
            
            // Generate UUID
            $uuid = $this->generateUUID();
            
            // Check if user already exists
            $existingUser = Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->first();
                
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'User account already exists for this service'
                ];
            }
            
            // Create new user
            $userId = Capsule::table('mod_orrism_users')->insertGetId([
                'service_id' => $serviceid,
                'client_id' => $clientid,
                'email' => $email,
                'uuid' => $uuid,
                'upload_bytes' => 0,
                'download_bytes' => 0,
                'bandwidth_limit' => $bandwidth,
                'node_group_id' => $nodeGroup,
                'status' => 'active',
                'need_reset' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Update WHMCS service with UUID
            $this->saveCustomField($serviceid, 'uuid', $uuid);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'uuid' => $uuid,
                'message' => 'User account created successfully'
            ];
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, $params, 'Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user by service ID
     * 
     * @param int $serviceid WHMCS service ID
     * @return object|null User data
     */
    public function getUser($serviceid)
    {
        try {
            return Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->first();
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update user status
     * 
     * @param int $serviceid Service ID
     * @param string $status New status (active, suspended, terminated)
     * @return bool Success status
     */
    public function updateUserStatus($serviceid, $status)
    {
        try {
            $updated = Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->update([
                    'status' => $status,
                    'updated_at' => now()
                ]);
                
            return $updated > 0;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid, 'status' => $status], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete user account
     * 
     * @param int $serviceid Service ID
     * @return bool Success status
     */
    public function deleteUser($serviceid)
    {
        try {
            return Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->delete() > 0;
                
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset user traffic
     * 
     * @param int $serviceid Service ID
     * @return bool Success status
     */
    public function resetUserTraffic($serviceid)
    {
        try {
            $updated = Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->update([
                    'upload_bytes' => 0,
                    'download_bytes' => 0,
                    'last_reset_at' => now(),
                    'updated_at' => now()
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
    public function regenerateUUID($serviceid)
    {
        try {
            $newUuid = $this->generateUUID();
            
            $updated = Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->update([
                    'uuid' => $newUuid,
                    'updated_at' => now()
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
            $bandwidth = ($params['configoption4'] ?: 100) * 1024 * 1024 * 1024; // Convert GB to bytes
            $nodeGroup = $params['configoption7'] ?: 1;
            
            $updated = Capsule::table('mod_orrism_users')
                ->where('service_id', $serviceid)
                ->update([
                    'bandwidth_limit' => $bandwidth,
                    'node_group_id' => $nodeGroup,
                    'updated_at' => now()
                ]);
                
            return $updated > 0;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['serviceid' => $serviceid], 'Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user usage statistics
     * 
     * @param int $serviceid Service ID
     * @return array Usage statistics
     */
    public function getUserUsage($serviceid)
    {
        try {
            $user = $this->getUser($serviceid);
            if (!$user) {
                return [];
            }
            
            $totalGB = round($user->bandwidth_limit / 1024 / 1024 / 1024, 2);
            $usedGB = round(($user->upload_bytes + $user->download_bytes) / 1024 / 1024 / 1024, 2);
            $remainingGB = max(0, $totalGB - $usedGB);
            $usagePercent = $totalGB > 0 ? round($usedGB / $totalGB * 100, 2) : 0;
            
            return [
                'total_bandwidth' => $totalGB,
                'used_bandwidth' => $usedGB,
                'remaining_bandwidth' => $remainingGB,
                'usage_percent' => $usagePercent,
                'upload_gb' => round($user->upload_bytes / 1024 / 1024 / 1024, 2),
                'download_gb' => round($user->download_bytes / 1024 / 1024 / 1024, 2),
                'status' => $user->status,
                'last_reset' => $user->last_reset_at
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
            return Capsule::table('mod_orrism_nodes')
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
     * Record user traffic usage
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
            $user = $this->getUser($serviceid);
            if (!$user) {
                return false;
            }
            
            // Insert usage record
            Capsule::table('mod_orrism_user_usage')->insert([
                'user_id' => $user->id,
                'service_id' => $serviceid,
                'node_id' => $nodeId,
                'upload_bytes' => $uploadBytes,
                'download_bytes' => $downloadBytes,
                'session_start' => now(),
                'client_ip' => $clientIp,
                'recorded_at' => now()
            ]);
            
            // Update user totals
            Capsule::table('mod_orrism_users')
                ->where('id', $user->id)
                ->increment('upload_bytes', $uploadBytes);
                
            Capsule::table('mod_orrism_users')
                ->where('id', $user->id)
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
            $config = Capsule::table('mod_orrism_config')
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
     * @return bool Success status
     */
    public function setConfig($key, $value, $type = 'string')
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
            
            $updated = Capsule::table('mod_orrism_config')
                ->where('config_key', $key)
                ->update([
                    'config_value' => $value,
                    'config_type' => $type,
                    'updated_at' => now()
                ]);
            
            // If no rows updated, insert new config
            if ($updated === 0) {
                Capsule::table('mod_orrism_config')->insert([
                    'config_key' => $key,
                    'config_value' => $value,
                    'config_type' => $type,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['key' => $key], 'Error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Helper function to get database instance
 * 
 * @return OrrisDatabase
 */
function orrism_db()
{
    return OrrisDatabase::getInstance();
}