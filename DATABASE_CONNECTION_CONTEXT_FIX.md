# 数据库连接上下文错误修复

## 错误描述
```
PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'orrism.tblconfiguration' doesn't exist
```

## 问题原因
OrrisDB类在初始化时错误地：
1. 设置了全局Capsule实例 (`setAsGlobal()`)
2. 修改了默认数据库连接 (`setDefaultConnection('orrism')`)

这导致后续所有的数据库查询都使用了ORRISM数据库连接，包括WHMCS核心功能查询`tblconfiguration`等表时也会在错误的数据库中查找。

## 解决方案

### 1. 修复OrrisDB初始化方式

**修复前的问题代码：**
```php
// 创建新的Capsule实例
self::$capsule = new Capsule();
self::$capsule->addConnection($config, 'orrism');

// 错误：设置为全局实例
self::$capsule->setAsGlobal();

// 错误：修改默认连接
self::$capsule->getDatabaseManager()->setDefaultConnection('orrism');
```

**修复后的正确代码：**
```php
// 使用现有的WHMCS Capsule实例
self::$capsule = WhmcsCapsule::getFacadeRoot();

// 只添加ORRISM连接，不修改默认设置
self::$capsule->addConnection($config, 'orrism');

// 不设置为全局，不修改默认连接
// 这样WHMCS的查询仍使用默认连接
```

### 2. 修复数据库管理器方法

更新`database_manager.php`中的所有方法，确保正确使用连接：

#### testConnection方法
```php
// 修复前
Capsule::connection()->getPdo();

// 修复后
if ($this->useOrrisDB) {
    $connection = OrrisDB::connection();
    $connection->getPdo();
} else {
    Capsule::connection()->getPdo();
}
```

#### migrate方法
```php
// 修复前
Capsule::beginTransaction();
Capsule::commit();
Capsule::rollback();

// 修复后
$connection = $this->useOrrisDB ? OrrisDB::connection() : Capsule::connection();
$connection->beginTransaction();
$connection->commit();
$connection->rollback();
```

#### getStatus方法
```php
// 修复前
$exists = Capsule::schema()->hasTable($table);
$count = Capsule::table($table)->count();

// 修复后
if ($this->useOrrisDB) {
    $schema = OrrisDB::schema();
    $exists = $schema->hasTable($table);
    $count = OrrisDB::table($table)->count();
} else {
    $exists = Capsule::schema()->hasTable($table);
    $count = Capsule::table($table)->count();
}
```

## 技术细节

### 连接隔离原理
- **WHMCS连接**：使用默认的Capsule连接访问WHMCS表
- **ORRISM连接**：使用命名连接'orrism'访问ORRISM表
- **不干扰原则**：不修改WHMCS的默认连接设置

### 连接使用方式
```php
// WHMCS表查询（使用默认连接）
Capsule::table('tblconfiguration')->get();

// ORRISM表查询（使用命名连接）
OrrisDB::table('mod_orrism_users')->get();

// 或者显式指定连接
Capsule::connection('orrism')->table('mod_orrism_users')->get();
```

## 修改的文件

### 1. `/modules/servers/orrism/includes/orris_db.php`
- 移除`setAsGlobal()`调用
- 移除`setDefaultConnection()`调用
- 使用现有WHMCS Capsule实例而非创建新实例

### 2. `/modules/servers/orrism/includes/database_manager.php`
- 更新`testConnection()`方法
- 更新`migrate()`方法中的事务处理
- 更新`getStatus()`方法中的表检查

## 验证方法

### 1. 检查连接状态
```php
// 应该返回WHMCS数据库
$default = Capsule::connection()->getDatabaseName();

// 应该返回ORRISM数据库
$orrism = OrrisDB::connection()->getDatabaseName();
```

### 2. 测试表查询
```php
// 应该成功查询WHMCS表
$config = Capsule::table('tblconfiguration')->first();

// 应该成功查询ORRISM表（如果已安装）
$users = OrrisDB::table('mod_orrism_users')->count();
```

## 预防措施

### 1. 代码规范
- OrrisDB相关操作始终使用`OrrisDB::`类方法
- WHMCS相关操作使用`Capsule::`或`WhmcsCapsule::`
- 避免修改全局数据库设置

### 2. 测试建议
- 数据库安装前后都要测试WHMCS功能正常
- 验证两个数据库的查询都能正常工作
- 检查日志确保没有表不存在的错误

## 影响范围

### 修复前的问题
- WHMCS后台可能出现空白页面
- 配置页面加载失败
- 其他WHMCS功能异常

### 修复后的改进
- ✅ WHMCS功能正常运行
- ✅ ORRISM数据库独立工作
- ✅ 两套系统互不干扰
- ✅ 连接管理更加安全

## 总结

这次修复解决了数据库连接上下文混乱的根本问题，确保：
1. **数据隔离**：ORRISM和WHMCS数据完全分离
2. **功能独立**：两套系统各自使用正确的数据库
3. **稳定性**：不会因为ORRISM模块影响WHMCS核心功能
4. **可维护性**：清晰的连接管理逻辑