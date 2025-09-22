<?php
/**
 * ORRISM - Unified Helper Functions
 * Consolidated utility functions for WHMCS module
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * ORRISM Utility Helper Class
 * Centralized helper functions with improved performance and type safety
 */
class OrrisHelper
{
    // Size conversion constants
    private const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    private const BYTE_FACTOR = 1024;
    
    /**
     * Convert bytes to human readable format
     * 
     * @param int|float $bytes Size in bytes
     * @param int $precision Decimal precision
     * @param bool $binary Use binary (1024) or decimal (1000) conversion
     * @return string Formatted size string
     */
    public static function formatBytes($bytes, int $precision = 2, bool $binary = true): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        
        $factor = $binary ? self::BYTE_FACTOR : 1000;
        $units = self::BYTE_UNITS;
        
        $power = min(floor(log($bytes, $factor)), count($units) - 1);
        $value = $bytes / pow($factor, $power);
        
        return round($value, $precision) . ' ' . $units[$power];
    }
    
    /**
     * Convert GB to bytes
     * 
     * @param int|float $gb Size in GB
     * @return int Size in bytes
     */
    public static function gbToBytes($gb): int
    {
        return (int)($gb * self::BYTE_FACTOR * self::BYTE_FACTOR * self::BYTE_FACTOR);
    }
    
    /**
     * Convert bytes to GB
     * 
     * @param int $bytes Size in bytes
     * @param int $precision Decimal precision
     * @return float Size in GB
     */
    public static function bytesToGb(int $bytes, int $precision = 2): float
    {
        return round($bytes / (self::BYTE_FACTOR ** 3), $precision);
    }
    
    /**
     * Generate secure server key
     * 
     * @param int $timestamp Unix timestamp
     * @param int $length Key length
     * @return string Base64 encoded key
     */
    public static function generateServerKey(int $timestamp, int $length = 16): string
    {
        $hash = hash('sha256', $timestamp . random_bytes(16));
        return base64_encode(substr($hash, 0, $length));
    }
    
    /**
     * Generate UUID v4 (RFC 4122 compliant)
     * 
     * @return string UUID v4 string
     */
    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        
        // Set version to 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set variant
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
    
    /**
     * Generate secure token
     * 
     * @param int $length Token length
     * @param bool $urlSafe Use URL-safe characters
     * @return string Generated token
     */
    public static function generateToken(int $length = 32, bool $urlSafe = true): string
    {
        $bytes = random_bytes($length);
        
        if ($urlSafe) {
            return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        }
        
        return base64_encode($bytes);
    }
    
    /**
     * Generate MD5 token (legacy compatibility)
     * 
     * @return string 32-character MD5 token
     */
    public static function generateMd5Token(): string
    {
        return md5(uniqid(random_int(100000, 999999), true));
    }
    
    /**
     * UUID to Base64 conversion
     * 
     * @param string $uuid UUID string
     * @param int $length Truncate to length
     * @return string Base64 encoded result
     */
    public static function uuidToBase64(string $uuid, int $length = 22): string
    {
        $cleanUuid = str_replace('-', '', $uuid);
        return base64_encode(substr(hex2bin($cleanUuid), 0, $length));
    }
    
    /**
     * Sanitize and validate email address
     * 
     * @param string $email Email to validate
     * @return string|null Sanitized email or null if invalid
     */
    public static function sanitizeEmail(string $email): ?string
    {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ?: null;
    }
    
    /**
     * Secure password hashing
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain text password
     * @param string $hash Stored hash
     * @return bool Verification result
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate subscription URL with parameters
     * 
     * @param string $baseUrl Base subscription URL
     * @param array $params Parameters to include
     * @return string Complete subscription URL
     */
    public static function buildSubscriptionUrl(string $baseUrl, array $params): string
    {
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return rtrim($baseUrl, '/') . '?' . $queryString;
    }
    
    /**
     * Calculate bandwidth usage percentage
     * 
     * @param int $used Used bandwidth in bytes
     * @param int $total Total bandwidth in bytes
     * @return float Usage percentage (0-100)
     */
    public static function calculateUsagePercentage(int $used, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        
        return min(round(($used / $total) * 100, 2), 100.0);
    }
    
    /**
     * Log with context and structured format
     * 
     * @param string $level Log level (error, warning, info, debug)
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] ORRISM.{$level}: {$message}{$contextStr}";
        
        error_log($logMessage);
    }
}

// Legacy function wrappers for backward compatibility
function orrism_convert_byte($size, $digits = 2) {
    return OrrisHelper::formatBytes($size, $digits);
}

function orrism_gb_to_bytes($gb) {
    return OrrisHelper::gbToBytes($gb);
}

function orrism_get_server_key($timestamp, $length) {
    return OrrisHelper::generateServerKey($timestamp, $length);
}

function orrism_uuidToBase64($uuid, $length) {
    return OrrisHelper::uuidToBase64($uuid, $length);
}

function orrism_generate_md5_token() {
    return OrrisHelper::generateMd5Token();
}

function orrism_uuid4() {
    return OrrisHelper::generateUuid();
}