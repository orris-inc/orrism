# 数据库安装错误修复

## 错误描述
```
Database installation failed: SQLSTATE[42S01]: Base table or view already exists: 
1050 Table 'mod_orrism_node_groups' already exists
```

## 问题原因
在`database_manager.php`中的`createTables()`方法直接使用`Capsule::schema()->create()`创建表，没有检查表是否已经存在。当表已存在时，会抛出SQL错误。

## 修复方案
在创建每个表之前添加存在性检查，确保只在表不存在时才创建。

## 修改内容

### 1. 表创建添加存在性检查
修改了`/modules/servers/orrism/includes/database_manager.php`中的`createTables()`方法：

#### 修改前：
```php
Capsule::schema()->create('mod_orrism_node_groups', function ($table) {
    // 表结构定义
});
```

#### 修改后：
```php
if (!Capsule::schema()->hasTable('mod_orrism_node_groups')) {
    Capsule::schema()->create('mod_orrism_node_groups', function ($table) {
        // 表结构定义
    });
}
```

### 2. 修改的表
- `mod_orrism_node_groups` - 节点组表
- `mod_orrism_nodes` - 节点表
- `mod_orrism_users` - 用户表
- `mod_orrism_user_usage` - 用户使用记录表
- `mod_orrism_config` - 配置表
- `mod_orrism_migrations` - 迁移记录表

### 3. 数据插入添加存在性检查
修改了`insertDefaultData()`方法，在插入默认数据前检查数据是否已存在：

#### 节点组默认数据：
```php
if (!Capsule::table('mod_orrism_node_groups')->where('id', 1)->exists()) {
    // 插入默认节点组
}
```

#### 配置项默认数据：
```php
foreach ($defaultConfigs as $config) {
    if (!Capsule::table('mod_orrism_config')->where('config_key', $config['config_key'])->exists()) {
        // 插入配置项
    }
}
```

## 优势
1. **幂等性**：多次执行安装操作不会出错
2. **增量更新**：可以在已有表的基础上添加缺失的表
3. **数据保护**：不会覆盖已存在的数据
4. **错误容错**：即使部分表已存在，安装过程仍可继续

## 验证方法
1. 首次安装：应该成功创建所有表和默认数据
2. 重复安装：不会报错，跳过已存在的表和数据
3. 部分安装：如果只有部分表存在，会创建缺失的表

## 建议
1. 考虑添加版本控制机制，支持数据库结构升级
2. 添加卸载功能，可以清理创建的表
3. 实现数据库备份和恢复功能
4. 添加更详细的日志记录