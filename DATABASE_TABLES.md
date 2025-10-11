# ORRISM 数据库表创建机制

## 📊 表创建总结

### 🔧 **激活 Addon Module 时**（保存配置时）
在 `orrism_admin_activate()` 函数中**只创建一个表**：

```sql
CREATE TABLE mod_orrism_admin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

**用途**: 存储 Addon Module 的配置信息

---

### 🗄️ **点击 "Install Database Tables" 时**
在 Database Setup 页面点击安装按钮时，会创建**完整的 ORRISM 业务表**：

#### 1. `mod_orrism_node_groups` - 节点组
```sql
CREATE TABLE mod_orrism_node_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE,
    description TEXT,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

#### 2. `nodes` - 节点信息（实际表结构）
```sql
CREATE TABLE `nodes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(128) NOT NULL COMMENT '节点名称',
    `server` varchar(128) NOT NULL COMMENT '服务器地址',
    `port` int(11) NOT NULL COMMENT '服务端口',
    `type` varchar(32) NOT NULL DEFAULT 'ss' COMMENT '节点类型',
    `method` varchar(32) NOT NULL DEFAULT 'aes-256-gcm' COMMENT '加密方式',
    `info` varchar(128) NOT NULL DEFAULT '' COMMENT '节点信息',
    `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 0-维护中 1-正常',
    `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
    `traffic_rate` float NOT NULL DEFAULT '1' COMMENT '流量倍率',
    `node_group` int(11) NOT NULL DEFAULT '0' COMMENT '节点分组',
    `online_user` int(11) NOT NULL DEFAULT '0' COMMENT '在线用户数',
    `max_user` int(11) NOT NULL DEFAULT '0' COMMENT '最大用户数',
    `updated_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间(Unix时间戳)',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='节点信息表';
```

**字段说明：**
- `name`: 节点名称（不是 node_name）
- `server`: 服务器地址（不是 address）
- `type`: 节点类型（不是 node_type）
- `method`: 加密方式（不是 node_method）
- `node_group`: 节点分组ID（不是 group_id，默认0表示无分组）
- `sort`: 排序（不是 sort_order）
- `status`: 状态，0=维护中，1=正常（不是ENUM类型）
- `updated_at`: 更新时间，存储Unix时间戳（不是datetime）

#### 3. `mod_orrism_users` - 用户账户
```sql
CREATE TABLE mod_orrism_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT UNIQUE,
    email VARCHAR(255),
    uuid VARCHAR(36) UNIQUE,
    password VARCHAR(255),
    transfer_enable BIGINT DEFAULT 0,
    upload BIGINT DEFAULT 0,
    download BIGINT DEFAULT 0,
    total BIGINT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended'),
    node_group_id INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (node_group_id) REFERENCES mod_orrism_node_groups(id)
)
```

> 说明：每个 WHMCS 服务（无论同一客户购买多少个产品/数量）都会生成一个独立的 ORRISM 模块账户，上表即记录这些“模块用户”。

#### 4. `mod_orrism_user_usage` - 使用记录
```sql
CREATE TABLE mod_orrism_user_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    node_id INT,
    upload_bytes BIGINT DEFAULT 0,
    download_bytes BIGINT DEFAULT 0,
    client_ip VARCHAR(45),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES mod_orrism_users(id) ON DELETE CASCADE,
    FOREIGN KEY (node_id) REFERENCES mod_orrism_nodes(id) ON DELETE CASCADE
)
```

#### 5. `mod_orrism_config` - 配置表
```sql
CREATE TABLE mod_orrism_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE,
    config_value TEXT,
    config_type ENUM('string', 'boolean', 'json') DEFAULT 'string',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)
```

#### 6. `mod_orrism_migrations` - 迁移记录
```sql
CREATE TABLE mod_orrism_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(20),
    description TEXT,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

---

## 🔄 创建流程

### **第一步**: 激活 Addon Module
1. 进入 `System Settings > Addon Modules`
2. 激活 "ORRISM Administration"
3. **自动创建**: `mod_orrism_admin_settings` 表

### **第二步**: 安装业务表
1. 进入 `Addons > ORRISM Administration`
2. 点击 "Database Setup" 标签
3. 点击 "Install Database Tables" 按钮
4. **自动创建**: 所有 ORRISM 业务表（6个表）

---

## ✅ 验证安装

安装完成后，数据库中应该有 **7个表**：
- ✅ `mod_orrism_admin_settings` (1个 - Addon配置)
- ✅ `mod_orrism_node_groups` (6个 - 业务表)
- ✅ `mod_orrism_nodes`
- ✅ `mod_orrism_users` 
- ✅ `mod_orrism_user_usage`
- ✅ `mod_orrism_config`
- ✅ `mod_orrism_migrations`

## 🛡️ 安全特性

- ✅ **重复安装检查**: 如果表已存在，会显示警告
- ✅ **事务保护**: 使用数据库事务，失败时回滚
- ✅ **错误日志**: 所有操作都记录到 WHMCS 日志
- ✅ **外键约束**: 确保数据完整性

---

## 📋 节点表字段映射说明

由于前端使用友好的字段名，而数据库使用简短的字段名，NodeManager 会自动处理映射：

| 前端/API字段名 | 数据库实际字段名 | 数据类型 | 说明 |
|--------------|----------------|---------|------|
| `node_type` | `type` | varchar(32) | 节点类型 |
| `node_name` | `name` | varchar(128) | 节点名称 |
| `address` | `server` | varchar(128) | 服务器地址 |
| `port` | `port` | int(11) | ✓ 字段名一致 |
| `node_method` | `method` | varchar(32) | 加密方法 |
| `tag` / `info` | `info` | varchar(128) | 节点信息/标签 |
| `group_id` | `node_group` | int(11) | 节点组ID |
| `rate` | `traffic_rate` | float | 流量倍率 |
| `sort_order` | `sort` | int(11) | 排序 |
| `status` | `status` | tinyint(4) | ✓ 字段名一致，但类型不同 |
| `updated_at` | `updated_at` | int(11) | ✓ 字段名一致，存储Unix时间戳 |

**重要提示：**
- `status` 字段：数据库中是 tinyint(4)，值为 0（维护中）或 1（正常），不是ENUM类型
- `updated_at` 字段：数据库中存储Unix时间戳（int），不是datetime或timestamp
- 前端代码无需关心映射，NodeManager会自动处理所有字段名转换
