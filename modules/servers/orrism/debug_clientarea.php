<?php
/**
 * Debug script for ORRISM Client Area
 *
 * Usage: php debug_clientarea.php [service_id]
 */

// Load WHMCS
define('CLIENTAREA', true);
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/includes/whmcs_database.php';
require_once __DIR__ . '/includes/orris_db.php';

// Get service ID from command line or use default
$serviceid = isset($argv[1]) ? intval($argv[1]) : 1;

echo "=== ORRISM Client Area Debug ===\n\n";

// 1. Check OrrisDB configuration
echo "1. Checking OrrisDB configuration...\n";
$useOrrisDB = class_exists('OrrisDB') && OrrisDB::isConfigured();
echo "   OrrisDB available: " . ($useOrrisDB ? "YES" : "NO") . "\n";

if ($useOrrisDB) {
    echo "   OrrisDB configured: YES\n";
    try {
        $connection = OrrisDB::connection();
        echo "   OrrisDB connection: SUCCESS\n";
        $dbName = $connection->getDatabaseName();
        echo "   Database name: $dbName\n";
    } catch (Exception $e) {
        echo "   OrrisDB connection: FAILED - " . $e->getMessage() . "\n";
    }
} else {
    echo "   Using WHMCS Capsule database\n";
}

echo "\n";

// 2. Check table existence
echo "2. Checking table existence...\n";
$db = OrrisDatabase::getInstance();

try {
    if ($useOrrisDB) {
        $schema = OrrisDB::schema();
        $tables = ['services', 'nodes', 'node_groups', 'service_usage', 'config'];
    } else {
        $schema = \WHMCS\Database\Capsule::schema();
        $tables = ['services', 'nodes', 'node_groups'];
    }

    foreach ($tables as $table) {
        $exists = $schema->hasTable($table);
        echo "   Table '$table': " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
    }
} catch (Exception $e) {
    echo "   Error checking tables: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Check service data
echo "3. Checking service data (service_id = $serviceid)...\n";

try {
    $service = $db->getService($serviceid);

    if ($service) {
        echo "   Service found: YES\n";
        echo "   ID: {$service->id}\n";
        echo "   Service ID: {$service->service_id}\n";
        echo "   Email: {$service->email}\n";
        echo "   UUID: {$service->uuid}\n";
        echo "   Status: {$service->status}\n";
        echo "   Bandwidth limit: " . ($service->bandwidth_limit / 1024 / 1024 / 1024) . " GB\n";
        echo "   Upload bytes: " . ($service->upload_bytes / 1024 / 1024) . " MB\n";
        echo "   Download bytes: " . ($service->download_bytes / 1024 / 1024) . " MB\n";
        echo "   Node group: {$service->node_group_id}\n";
    } else {
        echo "   Service found: NO\n";
        echo "   ERROR: Service with service_id=$serviceid not found in database\n";

        // Try to list all services
        echo "\n   Listing all services in database:\n";
        if ($useOrrisDB) {
            $allServices = OrrisDB::table('services')->get();
        } else {
            $allServices = \WHMCS\Database\Capsule::table('services')->get();
        }

        if ($allServices->isEmpty()) {
            echo "   No services found in database!\n";
        } else {
            foreach ($allServices as $s) {
                echo "   - ID: {$s->id}, service_id: {$s->service_id}, email: {$s->email}\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// 4. Check usage data
if (isset($service) && $service) {
    echo "4. Checking usage data...\n";
    try {
        $usage = $db->getServiceUsage($serviceid);
        echo "   Total bandwidth: {$usage['total_bandwidth']} GB\n";
        echo "   Used bandwidth: {$usage['used_bandwidth']} GB\n";
        echo "   Remaining: {$usage['remaining_bandwidth']} GB\n";
        echo "   Usage percent: {$usage['usage_percent']}%\n";
        echo "   Upload: {$usage['upload_gb']} GB\n";
        echo "   Download: {$usage['download_gb']} GB\n";
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// 5. Check nodes
if (isset($service) && $service) {
    echo "5. Checking nodes...\n";
    try {
        $nodes = $db->getNodesForGroup($service->node_group_id);
        echo "   Nodes found: " . count($nodes) . "\n";
        foreach ($nodes as $node) {
            echo "   - {$node->node_name} ({$node->type}) - {$node->address}:{$node->port}\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== Debug Complete ===\n";
