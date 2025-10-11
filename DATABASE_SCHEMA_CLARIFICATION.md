# 数据库表结构澄清

## 问题发现

项目中存在**两套不同的建表系统**，导致字段名混乱！

## 两套系统对比

### 系统1: OrrisDatabaseManager (实际使用)
**文件：** `modules/servers/orrism/includes/database_manager.php`

**表结构：**
```sql
CREATE TABLE nodes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    type ENUM('shadowsocks', 'v2ray', 'trojan', 'vless', 'vmess', 'hysteria'),
    address VARCHAR(255),               -- ✓ 使用 address
    port INT UNSIGNED,
    method VARCHAR(50),
    group_id BIGINT UNSIGNED,           -- ✓ 使用 group_id
    capacity INT UNSIGNED DEFAULT 1000,
    current_load INT UNSIGNED DEFAULT 0,
    bandwidth_limit BIGINT UNSIGNED,
    sort_order INT DEFAULT 0,           -- ✓ 使用 sort_order
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',  -- ✓ ENUM类型
    health_score INT DEFAULT 100,
    last_check_at TIMESTAMP,
    config JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,     -- ✓ TIMESTAMP
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,     -- ✓ TIMESTAMP

    INDEX idx_group_status (group_id, status),
    INDEX idx_type_status (type, status),
    INDEX idx_load_capacity (current_load, capacity)
);
```

**用途：**
- 通过 WHMCS Addon Module 的 "Install Database Tables" 按钮安装
- 这是**实际运行环境使用的表结构**

### 系统2: orris_check_and_init_tables (未使用)
**文件：** `modules/servers/orrism/api/database.php`

**表结构：**
```sql
CREATE TABLE `nodes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(128) NOT NULL,
    `server` varchar(128) NOT NULL,         -- ✗ 使用 server
    `port` int(11) NOT NULL,
    `type` varchar(32) NOT NULL DEFAULT 'ss',
    `method` varchar(32) NOT NULL DEFAULT 'aes-256-gcm',
    `info` varchar(128) NOT NULL DEFAULT '',
    `status` tinyint(4) NOT NULL DEFAULT '1',  -- ✗ tinyint类型
    `sort` int(11) NOT NULL DEFAULT '0',       -- ✗ 使用 sort
    `traffic_rate` float NOT NULL DEFAULT '1',
    `node_group` int(11) NOT NULL DEFAULT '0', -- ✗ 使用 node_group
    `online_user` int(11) NOT NULL DEFAULT '0',
    `max_user` int(11) NOT NULL DEFAULT '0',
    `updated_at` int(11) NOT NULL DEFAULT '0'  -- ✗ Unix时间戳
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**用途：**
- API 自动初始化（但实际未被调用）
- 仅在定义了 `ORRIS_AUTO_CHECK_TABLES` 常量时才会执行

## 实际使用的字段名

根据运行环境的错误信息 `Unknown column 'n.server'`，确认实际使用的是 **OrrisDatabaseManager** 的表结构。

### 正确的字段映射表

| 前端/API字段名 | 数据库实际字段名 | 数据类型 | 说明 |
|--------------|----------------|---------|------|
| `node_type` | `type` | ENUM | 节点类型 |
| `node_name` | `name` | VARCHAR(100) | 节点名称 |
| `address` | `address` | VARCHAR(255) | ✓ 服务器地址 |
| `port` | `port` | INT | ✓ 端口 |
| `node_method` | `method` | VARCHAR(50) | 加密方法 |
| `group_id` | `group_id` | BIGINT | ✓ 节点组ID |
| `sort_order` | `sort_order` | INT | ✓ 排序 |
| `status` | `status` | ENUM | ✓ 状态 |
| `created_at` | `created_at` | TIMESTAMP | ✓ 创建时间 |
| `updated_at` | `updated_at` | TIMESTAMP | ✓ 更新时间 |

## 修复内容

### ✅ 已修复 NodeManager.php

所有方法已恢复使用正确的字段名：
- `address` (not `server`)
- `group_id` (not `node_group`)
- `sort_order` (not `sort`)
- `status` ENUM类型 ('active', 'inactive', 'maintenance')
- `created_at`, `updated_at` TIMESTAMP类型

### ✅ 修复的方法

1. **getNodesWithStats()** - SELECT 使用 `n.address`, `n.group_id`, `n.sort_order`
2. **getNode()** - SELECT 使用正确字段，包括 `config`, `metadata`
3. **createNode()** - INSERT 使用 `address`, `group_id`, `sort_order`, `created_at`, `updated_at`
4. **updateNode()** - UPDATE 使用正确字段映射，`updated_at = NOW()`
5. **toggleNodeStatus()** - 使用 ENUM 值 ('active'/'inactive')
6. **batchUpdateNodes()** - 所有操作使用正确字段名和 NOW()

## 建议

### 短期
1. ✅ 使用 OrrisDatabaseManager 的表结构作为标准
2. ✅ 更新所有代码使用正确的字段名
3. ⚠️ 考虑移除或禁用 `api/database.php` 中的建表逻辑避免混淆

### 长期
1. 统一两套系统的表结构定义
2. 或者明确指定只使用一套系统
3. 添加数据库迁移机制处理表结构变更

## 总结

- ✅ 实际使用的表由 **OrrisDatabaseManager** 创建
- ✅ 字段名：`address`, `group_id`, `sort_order`
- ✅ 状态类型：ENUM('active', 'inactive', 'maintenance')
- ✅ 时间类型：TIMESTAMP
- ✅ NodeManager 已修复使用正确字段名
- ⚠️ `api/database.php` 的建表逻辑与实际不符，建议移除或更新

现在所有查询应该正常工作了！
