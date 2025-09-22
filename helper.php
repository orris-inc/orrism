<?php
/**
 * ORRIS - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

/**
 * 字节数转换为可读单位
 * @param int|float $size
 * @param int $digits
 * @return string
 */
function orris_convert_byte($size, $digits=2) {
    if ($size == 0) {
        return '0 B';
    }
    $unit = array('','K','M','G','T','P');
    $base = 1024;
    $i = floor(log($size, $base));
    $n = count($unit);
    if ($i >= $n) {
        $i = $n - 1;
    }
    return round($size / pow($base, $i), $digits) . ' ' . $unit[$i] . 'B';
}

/**
 * GB转字节
 * @param int|float $gb
 * @return int
 */
function gb_to_bytes($gb) {
    return (int)($gb * 1024 * 1024 * 1024);
}

/**
 * 生成UUID v4
 * @return string
 */
function generate_uuid() {
    if (class_exists('Ramsey\\Uuid\\Uuid')) {
        return Ramsey\Uuid\Uuid::uuid4()->toString();
    }
    // 简单兜底
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
 * 生成服务器密钥
 * @param int $timestamp
 * @param int $length
 * @return string
 */
function orris_get_server_key($timestamp, $length) {
    return base64_encode(substr(md5($timestamp), 0, $length));
}

/**
 * UUID截取并Base64编码
 * @param string $uuid
 * @param int $length
 * @return string
 */
function orris_uuidToBase64($uuid, $length) {
    return base64_encode(substr($uuid, 0, $length));
}

/**
 * 生成一个32位的MD5 Token
 * @return string
 */
function orris_generate_md5_token() {
    return md5(uniqid(rand(), true));
} 
