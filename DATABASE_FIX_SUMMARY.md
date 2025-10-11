# Database Connection Fix Summary

## Problem Identified
服务器模块报告 "ORRISM database tables not installed",但 addon 显示 "Status: Installed"

**根本原因:** 服务器模块和 addon 模块连接到了不同的数据库
- **Addon (OrrisDatabaseManager)**: 连接到 OrrisDB 配置的独立数据库
- **Server Module (OrrisDatabase)**: 之前只连接到 WHMCS 数据库 (Capsule)

## Solution Implemented

### 1. 修改 OrrisDatabase 类支持 OrrisDB

**文件:** `modules/servers/orrism/includes/whmcs_database.php`

**核心改动:**

1. **添加 OrrisDB 支持检测** (Line 24-58):
```php
private $useOrrisDB = false;

public function __construct()
{
    // Load OrrisDB if available
    $orrisDbPath = __DIR__ . '/orris_db.php';
    if (file_exists($orrisDbPath)) {
        require_once $orrisDbPath;
    }

    // Check if we should use OrrisDB
    $this->useOrrisDB = class_exists('OrrisDB') && OrrisDB::isConfigured();

    if ($this->useOrrisDB) {
        logModuleCall('orrism', 'OrrisDatabase', [], 'Using OrrisDB for database operations');
    } else {
        logModuleCall('orrism', 'OrrisDatabase', [], 'Using WHMCS Capsule for database operations');
    }
}
```

2. **修复 tablesExist() 支持 OrrisDB** (Line 65-87):
```php
private function tablesExist()
{
    try {
        if ($this->useOrrisDB) {
            // Check in OrrisDB (separate database)
            $schema = OrrisDB::schema();
            if (!$schema) {
                return false;
            }
            return $schema->hasTable('services') &&
                   $schema->hasTable('nodes') &&
                   $schema->hasTable('node_groups');
        } else {
            // Check in WHMCS database
            return Capsule::schema()->hasTable('services') &&
                   Capsule::schema()->hasTable('nodes') &&
                   Capsule::schema()->hasTable('node_groups');
        }
    } catch (Exception $e) {
        logModuleCall('orrism', __METHOD__, [], 'Error checking tables: ' . $e->getMessage());
        return false;
    }
}
```

3. **创建统一的 table() 辅助方法** (Line 118-124):
```php
private function table($table)
{
    if ($this->useOrrisDB) {
        return OrrisDB::table($table);
    }
    return Capsule::table($table);
}
```

4. **替换所有 ORRISM 表操作**:
- ✅ `Capsule::table('services')` → `$this->table('services')`
- ✅ `Capsule::table('nodes')` → `$this->table('nodes')`
- ✅ `Capsule::table('service_usage')` → `$this->table('service_usage')`
- ✅ `Capsule::table('config')` → `$this->table('config')`

**保留 WHMCS 原生表使用 Capsule:**
- `Capsule::table('tblclients')` - WHMCS 客户表
- `Capsule::table('tblcustomfields')` - WHMCS 自定义字段
- `Capsule::table('tblhosting')` - WHMCS 托管服务

## How It Works

### 数据库选择逻辑:

1. **OrrisDB 已配置且可用:**
   - 所有 ORRISM 表操作使用 OrrisDB (独立数据库)
   - WHMCS 表仍使用 Capsule (WHMCS 数据库)

2. **OrrisDB 未配置或不可用:**
   - 所有表操作回退到 WHMCS Capsule
   - 向后兼容不使用独立数据库的部署

### 日志输出:
- 使用 OrrisDB: `logModuleCall('orrism', 'OrrisDatabase', [], 'Using OrrisDB for database operations')`
- 使用 Capsule: `logModuleCall('orrism', 'OrrisDatabase', [], 'Using WHMCS Capsule for database operations')`

## Testing Steps

1. **检查当前配置:**
   ```
   Addon > ORRISM Admin > Settings
   查看 Database Configuration 是否已配置
   ```

2. **验证数据库连接:**
   ```
   Addon > ORRISM Admin > Dashboard
   查看 "ORRISM Database" 状态
   - 应显示 "Connected" (如果使用 OrrisDB)
   ```

3. **测试服务创建:**
   ```
   WHMCS > Products/Services > Create New Order
   选择 ORRISM 产品 → 点击 Create
   ```

4. **预期结果:**
   - ✅ 服务创建成功
   - ✅ Username/Password 字段填充
   - ✅ UUID 生成
   - ✅ 不再显示 "tables not installed" 错误

5. **检查日志:**
   ```
   WHMCS > Utilities > Logs > Module Log
   搜索 "orrism"
   应该看到: "Using OrrisDB for database operations"
   ```

## Files Modified

- ✅ `modules/servers/orrism/includes/whmcs_database.php`
  - 添加 `$useOrrisDB` 属性
  - 添加构造函数检测 OrrisDB
  - 修改 `tablesExist()` 支持 OrrisDB
  - 添加 `table()` 辅助方法
  - 替换所有 ORRISM 表的 Capsule 调用

## Backward Compatibility

✅ **完全向后兼容:**
- 如果 OrrisDB 未配置,自动回退到 WHMCS Capsule
- 不破坏现有只使用 WHMCS 数据库的部署
- 通过 `OrrisDB::isConfigured()` 安全检查

## Next Steps

测试通过后,无需进一步操作。系统将自动:
1. 检测 OrrisDB 配置
2. 使用正确的数据库连接
3. 在两种模式间无缝切换
