# Node字段映射修复说明

## 问题根源

数据库错误:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'n.node_type' in 'field list'
```

**原因**: NodeManager代码中使用的字段名与实际数据库表结构不匹配

## 数据库表实际结构

根据 `modules/servers/orrism/api/database.php` 第129-145行的建表语句:

```sql
CREATE TABLE `nodes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(128) NOT NULL,           -- 不是 node_name
    `server` varchar(128) NOT NULL,         -- 不是 address
    `port` int(11) NOT NULL,
    `type` varchar(32) NOT NULL,            -- 不是 node_type
    `method` varchar(32) NOT NULL,          -- 不是 node_method
    `info` varchar(128) NOT NULL,           -- 不是 tag
    `status` tinyint(4) NOT NULL,
    `sort` int(11) NOT NULL,                -- 不是 sort_order
    `traffic_rate` float NOT NULL,          -- 不是 rate
    `node_group` int(11) NOT NULL,          -- 不是 group_id
    `online_user` int(11) NOT NULL,
    `max_user` int(11) NOT NULL,
    `updated_at` int(11) NOT NULL,          -- unix timestamp,不是datetime
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 字段名映射表

| 前端/API字段名 | 数据库实际字段名 | 说明 |
|--------------|----------------|------|
| `node_type` | `type` | 节点类型 |
| `node_name` | `name` | 节点名称 |
| `address` | `server` | 服务器地址 |
| `port` | `port` | ✓ 一致 |
| `node_method` | `method` | 加密方法 |
| `tag` | `info` | 节点信息/标签 |
| `group_id` | `node_group` | 节点组ID |
| `rate` | `traffic_rate` | 流量倍率 |
| `sort_order` | `sort` | 排序 |
| `status` | `status` | ✓ 一致 |
| `updated_at` | `updated_at` | ✓ 字段名一致,但类型不同 |

## 已修复的文件

### 文件: `modules/addons/orrism_admin/includes/node_manager.php`

#### 1. getNodesWithStats() 方法 (第121-249行)

**修改前:**
```php
SELECT
    n.node_type,
    n.node_name,
    n.address,
    n.rate as traffic_rate,
    n.sort_order,
    ...
FROM nodes n
LEFT JOIN node_groups ng ON n.group_id = ng.id
```

**修改后:**
```php
SELECT
    n.type as node_type,
    n.name as node_name,
    n.server as address,
    n.traffic_rate,
    n.sort,
    n.node_group as group_id,
    ...
FROM nodes n
```

**修改内容:**
- ✅ 所有SELECT字段已更正
- ✅ 移除了不存在的node_groups表的JOIN
- ✅ 过滤条件中的字段名已更正
- ✅ ORDER BY使用正确的`sort`字段

#### 2. getNode() 方法 (第252-305行)

**修改前:**
```php
SELECT n.*, ng.name as group_name
FROM nodes n
LEFT JOIN node_groups ng ON n.group_id = ng.id
```

**修改后:**
```php
SELECT
    id,
    type as node_type,
    name as node_name,
    server as address,
    port,
    method as node_method,
    node_group as group_id,
    traffic_rate as rate,
    status,
    sort as sort_order,
    info,
    updated_at
FROM nodes
WHERE id = ?
```

**修改内容:**
- ✅ 显式列出所有字段并重命名
- ✅ 移除了node_groups表的JOIN
- ✅ 使用正确的数据库字段名

#### 3. createNode() 方法 (第310-362行)

**修改前:**
```php
INSERT INTO nodes (
    node_type, node_name, address, port,
    group_id, rate, node_method, network_type,
    status, sort_order, tag, created_at, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

**修改后:**
```php
INSERT INTO nodes (
    type, name, server, port,
    node_group, traffic_rate, method, info,
    status, sort, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
```

**修改内容:**
- ✅ 使用正确的数据库字段名
- ✅ 移除了不存在的`network_type`和`created_at`字段
- ✅ `updated_at`使用`time()`返回unix timestamp
- ✅ 添加了详细的注释说明字段映射

#### 4. updateNode() 方法 (第367-431行)

**修改前:**
```php
$allowedFields = [
    'node_type', 'node_name', 'address', 'port',
    'group_id', 'rate', 'node_method', 'network_type',
    'status', 'sort_order', 'tag'
];

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $updates[] = "$field = ?";
        $bindings[] = $data[$field];
    }
}
```

**修改后:**
```php
$fieldMapping = [
    'node_type' => 'type',
    'node_name' => 'name',
    'address' => 'server',
    'port' => 'port',
    'group_id' => 'node_group',
    'rate' => 'traffic_rate',
    'node_method' => 'method',
    'status' => 'status',
    'sort_order' => 'sort',
    'tag' => 'info'
];

foreach ($fieldMapping as $frontendField => $dbColumn) {
    if (isset($data[$frontendField])) {
        $updates[] = "$dbColumn = ?";
        $bindings[] = $data[$frontendField];
    }
}
```

**修改内容:**
- ✅ 创建了字段映射表
- ✅ 前端字段名映射到正确的数据库字段名
- ✅ 移除了不存在的`network_type`字段
- ✅ `updated_at`使用unix timestamp

#### 5. toggleNodeStatus() 方法 (第458-511行)

**修改:**
```php
// 修改前
$this->execute($sql, [$newStatus, date('Y-m-d H:i:s'), $nodeId]);

// 修改后
$this->execute($sql, [$newStatus, time(), $nodeId]);
```

#### 6. batchUpdateNodes() 方法 (第516-590行)

**修改内容:**
- ✅ `group_id` → `node_group` (第562行)
- ✅ 所有`updated_at`使用`time()`而不是`date('Y-m-d H:i:s')`

## 数据类型注意事项

### updated_at字段
- **数据库类型**: `int(11)` - 存储Unix时间戳
- **正确用法**: `time()` - 返回当前Unix时间戳
- **错误用法**: ~~`date('Y-m-d H:i:s')`~~ - 这会返回字符串

### status字段
- **数据库类型**: `tinyint(4)`
- **值**: 0 = 禁用, 1 = 启用
- **注意**: 不是ENUM类型

## 测试验证

修复后,以下操作应该能正常工作:

### 1. 查看节点列表
```
访问: Node Management页面
预期: 正常显示所有节点,无SQL错误
```

### 2. 创建节点
```
点击: Add Node
填写: 所有必填字段
预期: 保存成功,显示成功消息,数据库有新记录
```

### 3. 编辑节点
```
点击: Edit按钮
修改: 任意字段
预期: 保存成功,数据库记录已更新
```

### 4. 删除节点
```
点击: Delete按钮
确认: 删除操作
预期: 节点从列表和数据库中删除
```

## SQL查询示例

### 查看创建的节点
```sql
SELECT
    id,
    type,
    name,
    server,
    port,
    node_group,
    traffic_rate,
    method,
    status,
    sort,
    FROM_UNIXTIME(updated_at) as last_updated
FROM nodes
ORDER BY sort ASC;
```

### 手动插入测试节点
```sql
INSERT INTO nodes (
    name, server, port, type, method,
    info, status, sort, traffic_rate,
    node_group, updated_at
) VALUES (
    'Test Node',
    '192.168.1.1',
    8388,
    'ss',
    'aes-256-gcm',
    'Test node for debugging',
    1,
    0,
    1.0,
    0,
    UNIX_TIMESTAMP()
);
```

## 兼容性说明

### 前端代码无需修改
前端(node_ui.php)继续使用友好的字段名:
- `node_type`
- `node_name`
- `address`
- `rate`
- 等等

### NodeManager负责映射
NodeManager在所有SQL操作中自动处理字段名映射,前端代码完全透明。

## 日志输出

修复后,在PHP错误日志中应该能看到:
```
handleNodeCreate called - POST data: {...}
Loading NodeManager from: /path/to/node_manager.php
NodeManager instance created successfully
Node data prepared: {...}
Node created successfully with ID: 123
Node create operation result: {"success":true,"message":"Node created successfully","node_id":123}
```

## 后续优化建议

1. **创建数据库视图** - 提供字段别名,避免手动映射
2. **统一字段命名** - 考虑重构数据库或API,使用一致的命名
3. **添加数据验证** - 在NodeManager中增加更严格的数据验证
4. **缓存优化** - 考虑缓存节点列表,减少数据库查询

## 总结

✅ 所有数据库字段名映射已修复
✅ 所有SQL查询已更新为正确的字段名
✅ `updated_at`字段使用正确的数据类型(Unix时间戳)
✅ 添加了详细的错误日志便于调试
✅ 前端代码无需任何修改

现在node的添加、编辑、删除功能应该能完全正常工作了!
