<?php
/**
 * 简单的UUID生成库，作为Ramsey UUID库的替代
 */

// 全局函数 - 生成UUID
if (!function_exists('orrism_generate_uuid')) {
    /**
     * 生成UUID v4
     * 这是一个简单的实现，可以生成符合RFC 4122规范的UUID
     * 
     * @return string UUID字符串
     */
    function orrism_generate_uuid() {
        // 生成16字节的随机数据
        $data = random_bytes(16);
        
        // 设置版本为4（随机生成）
        $data[6] = chr(ord($data[6]) & 0x0F | 0x40);
        
        // 设置变体为RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3F | 0x80);
        
        // 格式化为标准的UUID字符串
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// 定义全局函数以替代命名空间类
if (!function_exists('orrism_uuid4')) {
    /**
     * 生成UUID v4并返回字符串 (模拟Ramsey\Uuid\Uuid::uuid4()->toString())
     * 
     * @return string
     */
    function orrism_uuid4() {
        return orrism_generate_uuid();
    }
}

// 不再尝试创建Ramsey\Uuid命名空间和类，而是使用全局函数替代 