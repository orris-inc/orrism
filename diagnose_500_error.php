<?php
/**
 * Diagnose 500 Error for Node Creation
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "=== ORRISM Node Creation 500 Error Diagnosis ===\n\n";

// Test 1: Check PHP version
echo "1. PHP Version: " . PHP_VERSION . "\n";
echo "   Required: >= 7.4\n";
echo "   Status: " . (version_compare(PHP_VERSION, '7.4', '>=') ? "✓ OK" : "✗ FAIL") . "\n\n";

// Test 2: Check WHMCS
echo "2. WHMCS Environment:\n";
define('WHMCS', true);
if (file_exists(__DIR__ . '/init.php')) {
    echo "   ✓ init.php found\n";
    try {
        require_once __DIR__ . '/init.php';
        echo "   ✓ WHMCS initialized\n";
    } catch (Exception $e) {
        echo "   ✗ WHMCS init error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ init.php not found\n";
}
echo "\n";

// Test 3: Check addon files
echo "3. Addon Files:\n";
$files = [
    'modules/addons/orrism_admin/orrism_admin.php',
    'modules/addons/orrism_admin/lib/Admin/Controller.php',
    'modules/addons/orrism_admin/includes/node_manager.php',
    'modules/servers/orrism/includes/orris_db.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "   ✓ $file\n";
    } else {
        echo "   ✗ $file NOT FOUND\n";
    }
}
echo "\n";

// Test 4: Check OrrisDB class
echo "4. OrrisDB Class:\n";
$orrisDbPath = __DIR__ . '/modules/servers/orrism/includes/orris_db.php';
if (file_exists($orrisDbPath)) {
    try {
        require_once $orrisDbPath;
        if (class_exists('OrrisDB')) {
            echo "   ✓ OrrisDB class loaded\n";

            try {
                $conn = OrrisDB::connection();
                if ($conn) {
                    echo "   ✓ OrrisDB connection successful\n";
                    $pdo = $conn->getPdo();
                    echo "   ✓ PDO obtained\n";
                } else {
                    echo "   ✗ OrrisDB connection returned null\n";
                }
            } catch (Exception $e) {
                echo "   ✗ OrrisDB connection error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✗ OrrisDB class not found after require\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error loading OrrisDB: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ orris_db.php not found\n";
}
echo "\n";

// Test 5: Check NodeManager
echo "5. NodeManager Class:\n";
$nodeManagerPath = __DIR__ . '/modules/addons/orrism_admin/includes/node_manager.php';
if (file_exists($nodeManagerPath)) {
    try {
        require_once $nodeManagerPath;
        if (class_exists('NodeManager')) {
            echo "   ✓ NodeManager class loaded\n";

            try {
                $nodeManager = new NodeManager();
                echo "   ✓ NodeManager instance created\n";
            } catch (Exception $e) {
                echo "   ✗ NodeManager instantiation error: " . $e->getMessage() . "\n";
                echo "   Stack trace:\n";
                echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
            }
        } else {
            echo "   ✗ NodeManager class not found after require\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Error loading NodeManager: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ node_manager.php not found\n";
}
echo "\n";

// Test 6: Check database connection
echo "6. Database Connection:\n";
if (class_exists('Illuminate\Database\Capsule\Manager')) {
    try {
        $capsule = \Illuminate\Database\Capsule\Manager::connection();
        $pdo = $capsule->getPdo();
        echo "   ✓ Capsule connection successful\n";

        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM nodes");
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        echo "   ✓ Nodes table accessible, " . $result->count . " nodes found\n";
    } catch (Exception $e) {
        echo "   ✗ Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Capsule not available\n";
}
echo "\n";

// Test 7: Simulate AJAX request
echo "7. Simulate Node Creation:\n";
$_GET['module'] = 'orrism_admin';
$_GET['action'] = 'node_create';
$_POST = [
    'node_type' => 'shadowsocks',
    'node_name' => 'Diagnosis Test Node',
    'address' => '192.168.1.200',
    'port' => '9999',
    'group_id' => '1',
    'node_method' => 'aes-256-gcm',
    'status' => '1',
    'sort_order' => '999'
];

try {
    echo "   Request: POST /addonmodules.php?module=orrism_admin&action=node_create\n";

    // Start output buffering
    ob_start();

    // Load controller
    require_once __DIR__ . '/modules/addons/orrism_admin/lib/Admin/Controller.php';

    if (class_exists('WHMCS\Module\Addon\OrrisAdmin\Admin\Controller')) {
        $controller = new WHMCS\Module\Addon\OrrisAdmin\Admin\Controller([]);

        // Simulate AJAX request
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        // Dispatch
        $output = $controller->dispatch('node_create', []);

        // Get any buffered output
        $buffered = ob_get_clean();

        echo "   Response length: " . strlen($output) . " bytes\n";
        echo "   Buffered output: " . strlen($buffered) . " bytes\n";

        // Try to decode JSON
        $json = json_decode($output);
        if ($json === null) {
            echo "   ✗ Invalid JSON response\n";
            echo "   JSON Error: " . json_last_error_msg() . "\n";
            echo "   First 200 chars: " . substr($output, 0, 200) . "\n";
        } else {
            echo "   ✓ Valid JSON response\n";
            echo "   Success: " . ($json->success ? "true" : "false") . "\n";
            if (isset($json->message)) {
                echo "   Message: " . $json->message . "\n";
            }
            if (isset($json->node_id)) {
                echo "   Node ID: " . $json->node_id . "\n";
            }
        }
    } else {
        echo "   ✗ Controller class not found\n";
        ob_end_clean();
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== End Diagnosis ===\n";
