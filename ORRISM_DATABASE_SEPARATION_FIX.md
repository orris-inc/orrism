# ORRISM 数据库分离修复

## 问题描述
数据库安装时，表被错误地创建在了WHMCS主数据库中，而不是addon模块配置中指定的独立ORRISM数据库。

## 原因分析
`OrrisDatabaseManager`类直接使用了WHMCS的`Capsule`连接，这会连接到WHMCS主数据库，而不是配置的独立数据库。

## 解决方案
创建独立的数据库连接管理器`OrrisDB`，从addon模块配置中读取数据库连接信息，并建立独立的数据库连接。

## 修改内容

### 1. 新增文件
**`/modules/servers/orrism/includes/orris_db.php`**
- 创建`OrrisDB`类管理独立数据库连接
- 从`tbladdonmodules`表读取ORRISM数据库配置
- 提供独立的schema、table等数据库操作接口

### 2. 更新`database_manager.php`
修改所有数据库操作，使其支持独立数据库：

#### 添加配置选项
```php
private $useOrrisDB = true;  // 使用独立ORRISM数据库
```

#### 更新方法
- `isInstalled()` - 检查ORRISM数据库中的表
- `getCurrentVersion()` - 从ORRISM数据库读取版本
- `install()` - 在ORRISM数据库中创建表
- `createTables()` - 使用OrrisDB::schema()
- `insertDefaultData()` - 使用OrrisDB::table()
- `uninstall()` - 从ORRISM数据库删除表
- `getUserCount()` - 从ORRISM数据库统计用户

## 配置说明

### Addon模块配置
在WHMCS后台 > Setup > Addon Modules > ORRISM Administration中配置：

- **Database Host**: ORRISM数据库服务器地址（如：localhost）
- **Database Name**: ORRISM数据库名（如：orrism）
- **Database User**: 数据库用户名
- **Database Password**: 数据库密码

### 数据库准备
1. 创建独立的ORRISM数据库：
```sql
CREATE DATABASE orrism CHARACTER SET utf8 COLLATE utf8_unicode_ci;
```

2. 创建数据库用户并授权：
```sql
CREATE USER 'orrism_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON orrism.* TO 'orrism_user'@'localhost';
FLUSH PRIVILEGES;
```

## 重要特性

### 1. 双模式支持
- 当`$useOrrisDB = true`时，使用独立的ORRISM数据库
- 当`$useOrrisDB = false`时，回退到WHMCS主数据库（兼容模式）

### 2. 连接缓存
- OrrisDB使用单例模式，避免重复创建连接
- 提供`reset()`方法在配置更改后重置连接

### 3. 错误处理
- 连接失败时返回友好错误信息
- 所有操作都有日志记录

## 使用示例

### 测试连接
```php
// 测试ORRISM数据库连接
$connected = OrrisDB::testConnection();
if ($connected) {
    echo "ORRISM database connected successfully";
}
```

### 创建表
```php
$dbManager = new OrrisDatabaseManager();
$result = $dbManager->install();
// 表将创建在独立的ORRISM数据库中
```

### 查询数据
```php
// 从ORRISM数据库查询
$users = OrrisDB::table('mod_orrism_users')->get();
```

## 优势

1. **数据隔离**：ORRISM数据与WHMCS核心数据完全分离
2. **性能优化**：独立数据库可独立优化和扩展
3. **安全性**：权限分离，降低安全风险
4. **可维护性**：便于备份、迁移和维护
5. **灵活部署**：支持将ORRISM数据库部署在不同服务器

## 注意事项

1. **首次安装**：确保先配置好数据库连接信息再执行安装
2. **现有数据迁移**：如果已有数据在WHMCS主数据库，需要手动迁移
3. **权限要求**：数据库用户需要CREATE、DROP、ALTER等权限
4. **字符集**：建议使用utf8或utf8mb4字符集

## 故障排查

### 连接失败
- 检查addon模块配置是否正确
- 验证数据库服务器是否可访问
- 确认用户名密码是否正确
- 查看日志文件中的详细错误信息

### 表创建失败
- 确认数据库用户有CREATE权限
- 检查数据库字符集设置
- 查看是否有同名表存在

### 数据查询异常
- 使用`OrrisDB::testConnection()`测试连接
- 检查表是否存在于正确的数据库中
- 验证OrrisDB配置是否正确加载