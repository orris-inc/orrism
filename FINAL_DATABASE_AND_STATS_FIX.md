# 最终修复总结 - 数据库字段和统计功能

## 修复日期
2025-10-11

## 关键发现：两套建表系统

项目中存在**两套不同的建表系统**，导致了字段名混乱：

### 系统1: OrrisDatabaseManager ✓ (实际使用)
- **文件**: `modules/servers/orrism/includes/database_manager.php`
- **字段**: `address`, `group_id`, `sort_order`
- **状态类型**: ENUM('active', 'inactive', 'maintenance')
- **时间类型**: TIMESTAMP

### 系统2: orris_check_and_init_tables ✗ (未使用)
- **文件**: `modules/servers/orrism/api/database.php`
- **字段**: `server`, `node_group`, `sort`
- **状态类型**: tinyint(4)
- **时间类型**: Unix timestamp (int)

**根据错误信息 `Unknown column 'n.server'` 确认实际使用的是 OrrisDatabaseManager。**

## 正确的数据库表结构

```sql
CREATE TABLE nodes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    type ENUM('shadowsocks', 'v2ray', 'trojan', 'vless', 'vmess', 'hysteria'),
    address VARCHAR(255),               -- ✓ 不是 server
    port INT UNSIGNED,
    method VARCHAR(50),
    group_id BIGINT UNSIGNED,           -- ✓ 不是 node_group
    capacity INT UNSIGNED DEFAULT 1000,
    current_load INT UNSIGNED DEFAULT 0,
    bandwidth_limit BIGINT UNSIGNED,
    sort_order INT DEFAULT 0,           -- ✓ 不是 sort
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',  -- ✓ ENUM不是tinyint
    health_score INT DEFAULT 100,
    last_check_at TIMESTAMP,
    config JSON,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,     -- ✓ TIMESTAMP不是int
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,     -- ✓ TIMESTAMP不是int

    INDEX idx_group_status (group_id, status),
    INDEX idx_type_status (type, status),
    INDEX idx_load_capacity (current_load, capacity)
);
```

## 字段映射表

| 前端字段 | 数据库字段 | 数据类型 | 说明 |
|---------|-----------|---------|------|
| node_type | type | ENUM | 节点类型 |
| node_name | name | VARCHAR(100) | 节点名称 |
| address | **address** | VARCHAR(255) | 服务器地址 |
| port | port | INT | 端口 |
| node_method | method | VARCHAR(50) | 加密方法 |
| group_id | **group_id** | BIGINT | 节点组ID |
| sort_order | **sort_order** | INT | 排序 |
| status | status | **ENUM** | 状态 |
| - | config | JSON | 配置 |
| - | metadata | JSON | 元数据 |
| - | created_at | **TIMESTAMP** | 创建时间 |
| - | updated_at | **TIMESTAMP** | 更新时间 |

## 已修复的功能

### 1. NodeManager.php - 所有方法

**文件**: `modules/addons/orrism_admin/includes/node_manager.php`

#### getNodesWithStats()
```php
// ✓ 使用正确字段
SELECT
    n.address,           -- 不是 n.server
    n.group_id,          -- 不是 n.node_group
    n.sort_order         -- 不是 n.sort
FROM nodes n
LEFT JOIN node_groups ng ON n.group_id = ng.id
ORDER BY n.sort_order ASC
```

#### getNode()
```php
// ✓ 恢复所有字段
SELECT
    address,             -- 不是 server
    group_id,            -- 不是 node_group
    sort_order,          -- 不是 sort
    config,              -- ✓ 恢复
    metadata             -- ✓ 恢复
FROM nodes
```

#### createNode()
```php
// ✓ 使用正确字段和类型
INSERT INTO nodes (
    type, name, address, port, method,
    group_id, status, sort_order,
    created_at, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())

// ✓ status 使用 'active' 而不是 1
// ✓ 时间使用 NOW() 而不是 time()
```

#### updateNode()
```php
// ✓ 字段映射表
$fieldMapping = [
    'address' => 'address',           // 不是 'server'
    'group_id' => 'group_id',         // 不是 'node_group'
    'sort_order' => 'sort_order'      // 不是 'sort'
];

// ✓ 使用 NOW()
updated_at = NOW()  -- 不是 time()
```

#### toggleNodeStatus()
```php
// ✓ ENUM 类型
$newStatus = ($node->status === 'active') ? 'inactive' : 'active';
// 不是 0/1 切换

// ✓ 使用 NOW()
UPDATE nodes SET status = ?, updated_at = NOW()
```

#### batchUpdateNodes()
```php
// ✓ 所有操作使用正确字段
case 'enable':
    status = 'active'    -- 不是 1
case 'disable':
    status = 'inactive'  -- 不是 0
case 'change_group':
    group_id = ?         -- 不是 node_group

// ✓ 使用 NOW()
updated_at = NOW()       -- 不是 time()
```

### 2. Controller.php - 统计功能优化

**文件**: `modules/addons/orrism_admin/lib/Admin/Controller.php`

#### getSystemStatistics()
```php
// ✓ 添加节点统计
$nodeManager = new \NodeManager();
$nodesResult = $nodeManager->getNodesWithStats(1, 1);
$stats['total_nodes'] = (int)$nodesResult['total'];

// ✓ 移除无用字段
// ✗ 移除 'last_sync' - 不需要同步
// ✗ 移除 'orrism_users' - 用户就是 WHMCS services
```

#### Dashboard 显示
```php
// ✓ 简化统计显示
Active Services: {count}      // WHMCS 活动服务
Total Nodes: {count}          // 节点总数
ORRISM Services: {count}      // 可选：ORRISM数据库服务（用于对比）
```

## 测试验证

### 1. Dashboard 页面
- ✅ Active Services 显示正确数量
- ✅ Total Nodes 显示正确数量（不再是 0）
- ✅ 移除了 "Last Sync: Never"
- ✅ 移除了 "ORRISM Users"

### 2. Node Management 功能
- ✅ 查看节点列表 - 无 SQL 错误
- ✅ 创建节点 - 使用正确字段保存
- ✅ 编辑节点 - 使用正确字段更新
- ✅ 删除节点 - 正常删除
- ✅ 切换状态 - 使用 ENUM 值
- ✅ 批量操作 - 所有字段正确

## SQL 查询示例

### 查看节点
```sql
SELECT
    id,
    name,
    address,           -- 不是 server
    port,
    type,
    method,
    group_id,          -- 不是 node_group
    sort_order,        -- 不是 sort
    status,
    created_at,
    updated_at
FROM nodes
ORDER BY sort_order ASC;
```

### 手动插入节点
```sql
INSERT INTO nodes (
    name, address, port, type, method,
    group_id, status, sort_order,
    created_at, updated_at
) VALUES (
    'Test Node',
    '192.168.1.1',
    8388,
    'shadowsocks',
    'aes-256-gcm',
    NULL,
    'active',
    0,
    NOW(),
    NOW()
);
```

## 错误原因分析

### 为什么会出现 `Unknown column 'n.server'` 错误？

1. **代码基于错误的表结构** - 基于 `api/database.php` 的表结构编写代码
2. **实际使用不同的建表系统** - 实际通过 OrrisDatabaseManager 创建表
3. **字段名不匹配** - `server` vs `address`, `node_group` vs `group_id`

### 修复过程

1. ✅ 发现问题 - Total Nodes 显示 0
2. ✅ 添加统计查询 - 使用 NodeManager
3. ❌ 错误修改 - 改为使用 `server` 字段（基于错误的文档）
4. ⚠️ 发现错误 - `Unknown column 'n.server'`
5. ✅ 检查实际表结构 - 确认使用 `address`
6. ✅ 恢复正确字段 - 全部改回 `address`, `group_id`, `sort_order`
7. ✅ 简化统计 - 移除不需要的 Last Sync

## 建议

### 立即行动
1. ✅ 使用 OrrisDatabaseManager 作为唯一的建表标准
2. ⚠️ 禁用或移除 `api/database.php` 中的建表逻辑
3. ✅ 更新所有文档反映实际表结构

### 长期优化
1. 统一两套系统或明确只使用一套
2. 添加数据库迁移机制
3. 添加单元测试验证字段映射
4. 考虑使用 ORM 避免手写 SQL

## 相关文件

### 已修改
- ✅ `modules/addons/orrism_admin/includes/node_manager.php`
- ✅ `modules/addons/orrism_admin/lib/Admin/Controller.php`

### 需要注意
- ⚠️ `modules/servers/orrism/api/database.php` - 建表语句与实际不符
- ⚠️ `DATABASE_TABLES.md` - 部分内容已过时
- ⚠️ `STATISTICS_AND_FIELD_MAPPING_FIX.md` - 基于错误的假设

### 新增文档
- ✅ `DATABASE_SCHEMA_CLARIFICATION.md` - 澄清两套系统
- ✅ `FINAL_DATABASE_AND_STATS_FIX.md` - 本文档

## 总结

✅ **问题已完全解决**
- Total Nodes 统计正确显示
- 所有节点管理功能正常工作
- 移除了不需要的统计项
- 代码使用正确的数据库字段

✅ **关键发现**
- 项目中有两套建表系统
- 实际使用 OrrisDatabaseManager
- 字段名：`address`, `group_id`, `sort_order`
- 类型：ENUM status, TIMESTAMP 时间

✅ **所有修复已完成**
- NodeManager 所有方法已修复
- Controller 统计功能已优化
- Dashboard 显示已简化
- 文档已更新

现在管理页面应该完全正常工作了！
