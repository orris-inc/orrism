<?php
/**
 * ORRISM - WHMCS Utility Functions
 * Unified WHMCS integration helpers
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/../helper.php';

/**
 * WHMCS Integration Helper Class
 */
class OrrisWhmcsHelper
{
    /**
     * Call WHMCS API with error handling and logging
     * 
     * @param string $command API command
     * @param array $postData Post data
     * @param string $adminUser Admin username
     * @param string $logContext Log context for debugging
     * @return array API response
     */
    public static function callAPI(string $command, array $postData, string $adminUser, string $logContext = ''): array
    {
        try {
            $results = localAPI($command, $postData, $adminUser);
            
            $success = ($results['result'] ?? '') === 'success';
            
            if ($logContext) {
                OrrisHelper::log(
                    $success ? 'info' : 'warning',
                    "WHMCS API call: {$command}",
                    [
                        'context' => $logContext,
                        'success' => $success,
                        'command' => $command,
                        'admin_user' => $adminUser
                    ]
                );
            }
            
            return $results;
            
        } catch (Exception $e) {
            OrrisHelper::log('error', 'WHMCS API call failed', [
                'command' => $command,
                'error' => $e->getMessage(),
                'context' => $logContext
            ]);
            
            return [
                'result' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get product IDs for specific module
     * 
     * @param string $adminUser Admin username
     * @param string $moduleName Module name
     * @return array Product ID list
     */
    public static function getModuleProductIds(string $adminUser, string $moduleName = 'orrism'): array
    {
        $response = self::callAPI(
            'GetProducts',
            ['module' => $moduleName],
            $adminUser,
            "Get products for module: {$moduleName}"
        );
        
        $productIds = [];
        if (isset($response['products']['product'])) {
            $productIds = array_column($response['products']['product'], 'pid');
        }
        
        OrrisHelper::log('debug', 'Module product IDs retrieved', [
            'module' => $moduleName,
            'product_count' => count($productIds),
            'product_ids' => $productIds
        ]);
        
        return $productIds;
    }
    
    /**
     * Get client services for product IDs
     * 
     * @param string $adminUser Admin username
     * @param array $productIds Product ID array
     * @param string $status Service status filter
     * @return array Client services list
     */
    public static function getModuleClientServices(string $adminUser, array $productIds, string $status = 'Active'): array
    {
        if (empty($productIds)) {
            return [];
        }
        
        $allServices = [];
        
        foreach ($productIds as $productId) {
            $response = self::callAPI(
                'GetClientsProducts',
                [
                    'limitnum' => 10000,
                    'status' => $status,
                    'pid' => $productId
                ],
                $adminUser,
                "Get {$status} services for product: {$productId}"
            );
            
            if (isset($response['products']['product'])) {
                $services = is_array($response['products']['product'][0] ?? null) 
                    ? $response['products']['product']
                    : [$response['products']['product']];
                    
                $allServices = array_merge($allServices, $services);
            }
        }
        
        OrrisHelper::log('debug', 'Client services retrieved', [
            'product_ids' => $productIds,
            'status' => $status,
            'service_count' => count($allServices)
        ]);
        
        return $allServices;
    }
    
    /**
     * Get service configuration options
     * 
     * @param int $serviceId Service ID
     * @return array Configuration options
     */
    public static function getServiceConfigOptions(int $serviceId): array
    {
        try {
            // This would typically use WHMCS database or API
            // Implementation depends on specific requirements
            return [];
        } catch (Exception $e) {
            OrrisHelper::log('error', 'Failed to get service config options', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Update service custom field
     * 
     * @param int $serviceId Service ID
     * @param string $fieldName Field name
     * @param string $value Field value
     * @param string $adminUser Admin username
     * @return bool Success status
     */
    public static function updateServiceCustomField(int $serviceId, string $fieldName, string $value, string $adminUser): bool
    {
        $response = self::callAPI(
            'UpdateClientProduct',
            [
                'serviceid' => $serviceId,
                'customfields' => base64_encode(serialize([$fieldName => $value]))
            ],
            $adminUser,
            "Update custom field {$fieldName} for service {$serviceId}"
        );
        
        return ($response['result'] ?? '') === 'success';
    }
}

// Legacy function wrappers for backward compatibility
function orrism_callAPI($command, $postData, $adminUser, $logMessage = '') {
    return OrrisWhmcsHelper::callAPI($command, $postData, $adminUser, $logMessage);
}

function orrism_get_module_product_ids($adminUser, $moduleName = 'orrism') {
    return OrrisWhmcsHelper::getModuleProductIds($adminUser, $moduleName);
}

function orrism_get_module_client_services($adminUser, array $productIds, $status = 'Active') {
    return OrrisWhmcsHelper::getModuleClientServices($adminUser, $productIds, $status);
}