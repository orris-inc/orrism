<?php
/**
 * Quick test to verify UUID function fix
 * This script tests that there are no more function redefinition errors
 */

echo "Testing UUID function definitions...\n";

// Include files in the same order as the main module
require_once __DIR__ . '/lib/uuid.php';
require_once __DIR__ . '/helper.php';

echo "✓ Files loaded without errors\n";

// Test UUID generation
echo "Testing UUID generation...\n";

$uuid1 = orrism_uuid4();
$uuid2 = orrism_generate_uuid();
$uuid3 = OrrisHelper::generateUuid();

echo "UUID from orrism_uuid4(): " . $uuid1 . "\n";
echo "UUID from orrism_generate_uuid(): " . $uuid2 . "\n";
echo "UUID from OrrisHelper::generateUuid(): " . $uuid3 . "\n";

// Verify UUID format (basic regex check)
$uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

if (preg_match($uuidPattern, $uuid1) && preg_match($uuidPattern, $uuid2) && preg_match($uuidPattern, $uuid3)) {
    echo "✓ All UUIDs are properly formatted\n";
} else {
    echo "✗ UUID format validation failed\n";
}

echo "✓ UUID function fix test completed successfully\n";
?>