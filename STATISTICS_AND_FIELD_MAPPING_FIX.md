# 统计功能和字段映射修复总结

## 修复日期
2025-10-11

## 问题描述

### 1. Total Nodes 统计显示为 0
管理页面的 "Total Nodes" 统计始终显示 0，即使数据库中有节点数据。

**根本原因：** `Controller.php` 中的 `getSystemStatistics()` 方法初始化了 `total_nodes` 为 0，但没有实际查询数据库获取节点数量。

### 2. NodeManager 字段映射错误
NodeManager 使用的字段名与实际数据库表结构不匹配，导致 SQL 查询失败。

**根本原因：** 代码中使用了友好的字段名（如 `address`, `group_id`, `sort_order`），但数据库实际使用简短的字段名（`server`, `node_group`, `sort`）。

## 实际数据库表结构

根据 `modules/servers/orrism/api/database.php` 中的建表语句：

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
    `updated_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='节点信息表';
```

## 字段映射表

| 前端/API字段名 | 数据库实际字段名 | 数据类型 | 说明 |
|--------------|----------------|---------|------|
| `node_type` | `type` | varchar(32) | 节点类型 |
| `node_name` | `name` | varchar(128) | 节点名称 |
| `address` | `server` | varchar(128) | **服务器地址** |
| `port` | `port` | int(11) | ✓ 一致 |
| `node_method` | `method` | varchar(32) | 加密方法 |
| `tag` / `info` | `info` | varchar(128) | 节点信息 |
| `group_id` | `node_group` | int(11) | **节点组ID** |
| `rate` | `traffic_rate` | float | 流量倍率 |
| `sort_order` | `sort` | int(11) | **排序** |
| `status` | `status` | tinyint(4) | 状态 (0/1) |
| `updated_at` | `updated_at` | int(11) | Unix时间戳 |

## 修复内容

### 1. Controller.php - 添加节点统计查询

**文件：** `modules/addons/orrism_admin/lib/Admin/Controller.php`

**修改位置：** 第 1043-1093 行，`getSystemStatistics()` 方法

**修改内容：**
```php
// Get total nodes count
try {
    $nodeManagerPath = dirname(__DIR__, 2) . '/includes/node_manager.php';
    if (file_exists($nodeManagerPath)) {
        require_once $nodeManagerPath;
        if (class_exists('NodeManager')) {
            $nodeManager = new \NodeManager();
            $nodesResult = $nodeManager->getNodesWithStats(1, 1);
            if ($nodesResult['success'] && isset($nodesResult['total'])) {
                $stats['total_nodes'] = (int)$nodesResult['total'];
            }
        }
    }
} catch (Exception $e) {
    error_log('ORRISM Controller: Error getting node count: ' . $e->getMessage());
}
```

**效果：** 现在 "Total Nodes" 会正确显示数据库中的节点总数。

### 2. NodeManager.php - 修复所有字段映射

**文件：** `modules/addons/orrism_admin/includes/node_manager.php`

#### 2.1 getNodesWithStats() 方法
- 修改 SELECT 语句中的字段名：
  - `n.type as node_type` (原 `n.node_type`)
  - `n.name as node_name` (原 `n.node_name`)
  - `n.server as address` (原 `n.address`)
  - `n.sort as sort_order` (原 `n.sort_order`)
  - `n.node_group as group_id` (原 `n.group_id`)
- 修改 JOIN 条件：`n.node_group = ng.id` (原 `n.group_id = ng.id`)
- 修改过滤条件：`n.node_group = ?`, `n.server LIKE ?`
- 修改排序：`ORDER BY n.sort ASC` (原 `n.sort_order`)

#### 2.2 getNode() 方法
- 修改所有字段别名以匹配实际数据库字段名
- 移除不存在的 `config` 和 `metadata` 字段

#### 2.3 createNode() 方法
- 修改 INSERT 字段名：
  - `server` (原 `address`)
  - `node_group` (原 `group_id`)
  - `sort` (原 `sort_order`)
- 使用 `time()` 而不是 `NOW()` 生成 Unix 时间戳
- 移除不存在的 `created_at` 字段

#### 2.4 updateNode() 方法
- 创建字段映射表：
```php
$fieldMapping = [
    'node_type' => 'type',
    'node_name' => 'name',
    'address' => 'server',
    'port' => 'port',
    'group_id' => 'node_group',
    'node_method' => 'method',
    'status' => 'status',
    'sort_order' => 'sort'
];
```
- 使用 `time()` 更新时间戳

#### 2.5 toggleNodeStatus() 方法
- 状态值改为 0/1（原 'active'/'inactive'）
- 使用 `time()` 更新时间戳

#### 2.6 batchUpdateNodes() 方法
- 所有状态更新使用 0/1 值
- 修改 `change_group` 操作使用 `node_group` 字段
- 使用 `time()` 更新时间戳

### 3. DATABASE_TABLES.md - 更新文档

**文件：** `DATABASE_TABLES.md`

**修改内容：**
- 更新 nodes 表结构定义以匹配实际数据库
- 添加字段说明
- 添加完整的字段映射表
- 添加重要提示说明数据类型差异

## 测试验证

修复后，以下功能应该正常工作：

### 1. Dashboard 统计显示
- ✅ Active Services: 显示活动服务数量
- ✅ Total Nodes: 显示节点总数（不再是 0）
- ✅ ORRISM Users: 显示用户总数
- ✅ Last Sync: 显示最后同步时间

### 2. Node Management 功能
- ✅ 查看节点列表 - 无 SQL 错误
- ✅ 创建新节点 - 数据正确保存
- ✅ 编辑节点 - 修改正确更新
- ✅ 删除节点 - 正确删除记录
- ✅ 切换节点状态 - 状态正确切换
- ✅ 批量操作 - 批量启用/禁用/删除正常工作

## 技术要点

### 1. 字段名映射
NodeManager 现在在所有 SQL 操作中自动处理字段名映射，前端代码无需修改。

### 2. 数据类型处理
- **status**: 使用 tinyint(4)，值为 0（维护）或 1（正常）
- **updated_at**: 使用 Unix 时间戳（int），通过 `time()` 函数生成

### 3. 时间处理
所有时间相关操作使用 `time()` 函数返回 Unix 时间戳，而不是 `NOW()` 或 `date()` 函数。

## 日志输出

修复后，相关操作的日志示例：

```
ORRISM Controller: Error getting node count: [如果有错误]
NodeManager: Using OrrisDB connection
Node created successfully with ID: 123
Node updated successfully: ID 456
Node toggle operation for ID 789: {"success":true,"message":"Node status updated","new_status":1}
```

## 相关文件

### 已修改的文件
1. `modules/addons/orrism_admin/lib/Admin/Controller.php`
2. `modules/addons/orrism_admin/includes/node_manager.php`
3. `DATABASE_TABLES.md`

### 参考文件
1. `modules/servers/orrism/api/database.php` - 数据库表结构定义
2. `FIELD_MAPPING_FIX.md` - 字段映射详细说明

## 后续建议

1. **添加数据库视图**：创建视图提供字段别名，简化查询
2. **统一命名规范**：考虑统一前端和后端的字段命名
3. **添加单元测试**：为 NodeManager 添加单元测试
4. **性能优化**：考虑缓存节点列表减少数据库查询

## 总结

✅ 修复了 Total Nodes 统计显示为 0 的问题
✅ 修复了所有 NodeManager 方法的字段映射问题
✅ 更新了文档以反映实际数据库结构
✅ 所有节点管理功能现在应该正常工作
✅ 前端代码无需任何修改

现在管理页面的统计功能和节点管理功能应该完全正常工作了！
