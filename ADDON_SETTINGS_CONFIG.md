# ORRISM Addon 配置管理更新

## 更新概述
移除了addon模块激活时的配置参数，改为激活后在Settings页面进行配置。这提供了更好的用户体验和灵活性。

## 主要变更

### 1. 简化的激活流程
**之前**：激活时需要填写数据库连接信息等多个参数
**现在**：激活时无需任何配置，直接激活即可

### 2. 独立的Settings页面
在addon激活后，通过Settings页面配置所有参数：
- **Database Configuration** - 配置ORRISM独立数据库
- **Cache Configuration** - 配置Redis缓存（可选）
- **General Settings** - 配置同步和流量重置等选项

### 3. 配置存储位置
所有配置存储在`mod_orrism_admin_settings`表中：
- `setting_key` - 配置项名称
- `setting_value` - 配置值
- `created_at/updated_at` - 时间戳

## 使用流程

### 步骤1：激活Addon
1. 进入WHMCS后台 > Setup > Addon Modules
2. 找到"ORRISM Administration"
3. 点击"Activate"激活（无需填写任何参数）
4. 配置管理员访问权限

### 步骤2：配置数据库
1. 进入addon页面（Addons > ORRISM Administration）
2. 点击"Settings"标签
3. 在"Database Configuration"部分填写：
   - Database Host（如：localhost）
   - Database Name（如：orrism）
   - Database Username
   - Database Password
4. 点击"Save Database Settings"保存

### 步骤3：安装数据库
1. 配置保存后，点击"Database Setup"标签
2. 点击"Install Database Tables"
3. 表将安装在配置的独立数据库中

## 配置项说明

### Database Configuration
```
database_host     - 数据库服务器地址
database_name     - 数据库名称
database_user     - 数据库用户名
database_password - 数据库密码
```

### Cache Configuration（可选）
```
redis_host - Redis服务器地址
redis_port - Redis端口号
```

### General Settings
```
auto_sync         - 启用自动用户同步
auto_reset_traffic - 启用自动流量重置
reset_day         - 流量重置日期（1-28）
```

## 技术实现

### 配置读取（OrrisDB类）
```php
// 从mod_orrism_admin_settings表读取配置
$settings = WhmcsCapsule::table('mod_orrism_admin_settings')
    ->whereIn('setting_key', ['database_host', 'database_name', ...])
    ->pluck('setting_value', 'setting_key')
    ->toArray();
```

### 配置保存
```php
function saveOrrisSettings($settings) {
    foreach ($settings as $key => $value) {
        // INSERT ... ON DUPLICATE KEY UPDATE
        // 存在则更新，不存在则插入
    }
    
    // 清除OrrisDB缓存
    OrrisDB::reset();
}
```

### 配置缓存
- OrrisDB使用静态变量缓存配置
- 配置更新后调用`OrrisDB::reset()`清除缓存
- 下次访问时重新加载最新配置

## 优势

1. **更好的用户体验**
   - 激活过程简化，降低使用门槛
   - 配置界面友好，有说明和验证

2. **灵活性**
   - 可随时修改配置无需重新激活
   - 支持部分配置更新

3. **安全性**
   - 密码字段使用password类型
   - 留空保持原密码不变

4. **可维护性**
   - 配置集中管理
   - 便于添加新配置项

## Dashboard提示
当数据库未配置时，Dashboard会显示提示：
- 状态：Not Configured（黄色警告）
- 操作：Configure Now按钮直接跳转Settings页面

## 注意事项

1. **首次使用**
   - 必须先在Settings页面配置数据库
   - 然后才能进行Database Setup

2. **配置更新**
   - 修改配置后立即生效
   - 无需重启或重新加载

3. **错误处理**
   - 配置错误时会显示友好提示
   - 日志记录详细错误信息

## 后续改进建议

1. **配置验证**
   - 实现"Test Connection"功能
   - 保存前验证数据库连接

2. **配置导入导出**
   - 支持配置文件导入
   - 便于批量部署

3. **配置加密**
   - 敏感信息（如密码）加密存储
   - 提高安全性

4. **配置历史**
   - 记录配置变更历史
   - 支持配置回滚