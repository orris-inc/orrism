<?php
/**
 * Test AJAX response for node creation
 * This script simulates the AJAX request and shows the exact response
 */

// Simulate AJAX request
$_GET['module'] = 'orrism_admin';
$_GET['action'] = 'node_create';
$_POST = [
    'node_type' => 'shadowsocks',
    'node_name' => 'Test Node Debug',
    'address' => '192.168.1.100',
    'port' => '8388',
    'group_id' => '1',
    'node_method' => 'aes-256-gcm',
    'status' => '1',
    'sort_order' => '0'
];

// Capture output
ob_start();

// Include WHMCS
define('WHMCS', true);
require_once __DIR__ . '/init.php';

// Get the addon output
require_once __DIR__ . '/modules/addons/orrism_admin/orrism_admin.php';

// The addon should handle the request
$vars = [];
orrism_admin_output($vars);

// Get captured output
$output = ob_get_clean();

// Display results
echo "<pre>";
echo "=== AJAX Response Test ===\n\n";
echo "Request Parameters:\n";
echo "GET: " . print_r($_GET, true) . "\n";
echo "POST: " . print_r($_POST, true) . "\n\n";

echo "Raw Response:\n";
echo "Length: " . strlen($output) . " bytes\n";
echo "Content:\n";
echo htmlspecialchars($output);
echo "\n\n";

// Try to decode as JSON
echo "JSON Decode Test:\n";
$json = json_decode($output);
if ($json === null) {
    echo "ERROR: Invalid JSON\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "\nFirst 500 chars of output:\n";
    echo htmlspecialchars(substr($output, 0, 500)) . "\n";
} else {
    echo "SUCCESS: Valid JSON\n";
    echo "Decoded:\n";
    print_r($json);
}

echo "\n=== End Test ===\n";
echo "</pre>";
