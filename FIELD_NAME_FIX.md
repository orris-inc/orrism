# Database Field Name Fix Summary

## Problem
SQL Error: `Unknown column 'client_id' in 'field list'`

服务创建失败,因为代码使用的字段名与实际数据库表结构不匹配。

## Root Cause Analysis

### 实际表结构 (database_manager.php)
```php
$table->bigIncrements('id');
$table->unsignedBigInteger('service_id')->unique();
$table->string('email', 255);
$table->string('uuid', 36)->unique();
$table->string('password', 255);
$table->string('password_algo', 20)->default('bcrypt');
// ... other fields
```

### 代码使用的字段 (错误)
```php
'client_id' => $clientid,                    // ❌ 不存在
'domain' => $params['domain'],               // ❌ 不存在
'product_id' => $params['pid'],              // ❌ 不存在
'whmcs_username' => $client->email,          // ❌ 不存在
'whmcs_email' => $email,                     // ❌ 不存在
'service_username' => $serviceUsername,      // ❌ 不存在
'service_password' => password_hash(...),    // ❌ 应该是 'password'
'need_reset' => true,                        // ❌ 不存在
```

## Solution - 字段映射修正

### 1. createService() - whmcs_database.php

**修复前:**
```php
$serviceId = $this->table('services')->insertGetId([
    'service_id' => $serviceid,
    'client_id' => $clientid,              // ❌
    'domain' => $params['domain'],         // ❌
    'product_id' => $params['pid'],        // ❌
    'whmcs_username' => $client->email,    // ❌
    'whmcs_email' => $email,               // ❌
    'service_username' => $serviceUsername,// ❌
    'service_password' => password_hash(), // ❌
    'uuid' => $uuid,
    'need_reset' => true,                  // ❌
    // ...
]);
```

**修复后:**
```php
$serviceId = $this->table('services')->insertGetId([
    'service_id' => $serviceid,           // ✅
    'email' => $email,                    // ✅ 正确字段
    'uuid' => $uuid,                      // ✅
    'password' => password_hash(...),     // ✅ 正确字段
    'password_algo' => 'bcrypt',          // ✅
    'upload_bytes' => 0,
    'download_bytes' => 0,
    'bandwidth_limit' => $bandwidth,
    'node_group_id' => $nodeGroup,
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);
```

### 2. ChangePassword() - orrism.php

**修复前:**
```php
$updated = Capsule::table('users')         // ❌ 错误的表名
    ->where('service_id', $serviceid)
    ->update([
        'password_hash' => password_hash() // ❌ 错误的字段名
    ]);
```

**修复后:**
```php
if ($useOrrisDB) {
    $updated = OrrisDB::table('services')  // ✅ 正确的表名
        ->where('service_id', $serviceid)
        ->update([
            'password' => password_hash(), // ✅ 正确的字段名
            'password_algo' => 'bcrypt',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
} else {
    $updated = Capsule::table('services')  // ✅ 正确的表名
        ->where('service_id', $serviceid)
        ->update([
            'password' => password_hash(), // ✅ 正确的字段名
            'password_algo' => 'bcrypt',
            'updated_at' => date('Y-m-d H:i:s')
        ]);
}
```

### 3. AdminServicesTabFields() - orrism.php

**修复前:**
```php
return [
    'UUID' => $service->uuid,
    'Service Username' => $service->service_username,  // ❌ 字段不存在
    'WHMCS Account' => $service->whmcs_email,          // ❌ 字段不存在
    'Created' => $user->created_at,                     // ❌ 变量错误
    'Updated' => $user->updated_at,                     // ❌ 变量错误
    'Last Reset' => $user->last_reset_at                // ❌ 变量错误
];
```

**修复后:**
```php
return [
    'UUID' => $service->uuid,
    'Service Username' => $service->email,  // ✅ email 作为用户名
    'WHMCS Account' => $service->email,     // ✅ 正确字段
    'Created' => $service->created_at,      // ✅ 正确变量
    'Updated' => $service->updated_at,      // ✅ 正确变量
    'Last Reset' => $service->last_reset_at // ✅ 正确变量
];
```

### 4. WHMCS Custom Fields - whmcs_database.php

**保持不变 (正确):**
```php
// WHMCS custom fields 仍然使用有意义的名称
$this->saveCustomField($serviceid, 'uuid', $uuid);
$this->saveCustomField($serviceid, 'service_username', $email);
$this->saveCustomField($serviceid, 'service_password', $servicePassword);
```

## 字段对应关系总结

| 旧字段名 (代码) | 新字段名 (实际表) | 说明 |
|----------------|------------------|------|
| `client_id` | ❌ 删除 | WHMCS service_id 已足够 |
| `domain` | ❌ 删除 | 存储在 WHMCS |
| `product_id` | ❌ 删除 | 存储在 WHMCS |
| `whmcs_username` | `email` | 统一使用 email |
| `whmcs_email` | `email` | 统一使用 email |
| `service_username` | `email` | Email 作为用户名 |
| `service_password` | `password` | 简化字段名 |
| `password_hash` | `password` | 简化字段名 |
| `need_reset` | ❌ 删除 | 未在表结构中定义 |

## Files Modified

1. ✅ `modules/servers/orrism/includes/whmcs_database.php`
   - Line 173-186: createService() 字段映射修正

2. ✅ `modules/servers/orrism/orrism.php`
   - Line 442-467: ChangePassword() 表名和字段修正
   - Line 920-931: AdminServicesTabFields() 字段名和变量修正

## Testing

测试服务创建:
```
WHMCS > Products/Services > Create New Order
选择 ORRISM 产品 → 点击 Create
```

预期结果:
- ✅ 服务创建成功
- ✅ UUID 生成
- ✅ Email 作为用户名显示
- ✅ 密码正确保存
- ✅ 所有字段正确填充

## Database Schema Reference

完整的 services 表结构:
```sql
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `uuid` varchar(36) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_algo` varchar(20) DEFAULT 'bcrypt',
  `bandwidth_limit` bigint unsigned DEFAULT '0',
  `upload_bytes` bigint unsigned DEFAULT '0',
  `download_bytes` bigint unsigned DEFAULT '0',
  `total_bytes` bigint unsigned GENERATED ALWAYS AS (`upload_bytes` + `download_bytes`) STORED,
  `monthly_upload` bigint unsigned DEFAULT '0',
  `monthly_download` bigint unsigned DEFAULT '0',
  `monthly_reset_day` tinyint unsigned DEFAULT '1',
  `last_reset_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `node_group_id` bigint unsigned DEFAULT NULL,
  `max_devices` int unsigned DEFAULT '3',
  `current_devices` int unsigned DEFAULT '0',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_uuid` (`uuid`),
  UNIQUE KEY `service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
