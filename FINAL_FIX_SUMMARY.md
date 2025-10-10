# Node保存功能最终修复总结

## 问题历程

### 1. 最初问题
**症状**: 添加node,点击保存不生效,无提示

**原因**: 后端缺少AJAX处理逻辑

**已修复**: 在Controller.php中添加了完整的AJAX处理

---

### 2. 第一次数据库错误
```
Unknown column 'n.node_type' in 'field list'
```

**原因**: 以为数据库字段是`node_type`,实际应该是`type`

**尝试修复**: 将所有字段改为api/database.php中的定义

---

### 3. 第二次数据库错误
```
Unknown column 'n.server' in 'field list'
```

**真正原因**: 实际使用的数据库表结构在`database_manager.php`中定义,与`api/database.php`完全不同!

---

## 正确的数据库表结构

### 来源
`modules/servers/orrism/includes/database_manager.php` 第370-403行

### nodes表实际结构
```sql
CREATE TABLE nodes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),                  -- 节点名称
    type ENUM(                          -- 节点类型
        'shadowsocks', 'v2ray', 'trojan',
        'vless', 'vmess', 'hysteria'
    ) DEFAULT 'shadowsocks',
    address VARCHAR(255),               -- 服务器地址
    port INT UNSIGNED,                  -- 端口
    method VARCHAR(50),                 -- 加密方法
    group_id BIGINT UNSIGNED,           -- 节点组ID(可为NULL)
    capacity INT UNSIGNED DEFAULT 1000, -- 容量
    current_load INT UNSIGNED DEFAULT 0,-- 当前负载
    bandwidth_limit BIGINT UNSIGNED,    -- 带宽限制
    sort_order INT DEFAULT 0,           -- 排序
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    health_score INT DEFAULT 100,       -- 健康分数
    last_check_at TIMESTAMP,            -- 最后检查时间
    config JSON,                        -- 配置(JSON)
    metadata JSON,                      -- 元数据(JSON)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_group_status (group_id, status),
    INDEX idx_type_status (type, status),
    INDEX idx_load_capacity (current_load, capacity)
);
```

## 正确的字段映射表

| 前端/API字段 | 数据库字段 | 类型 | 说明 |
|------------|-----------|------|------|
| `node_type` | `type` | ENUM | shadowsocks/v2ray/trojan等 |
| `node_name` | `name` | VARCHAR(100) | 节点名称 |
| `address` | `address` | VARCHAR(255) | ✓ 一致 |
| `port` | `port` | INT UNSIGNED | ✓ 一致 |
| `node_method` | `method` | VARCHAR(50) | 加密方法 |
| `group_id` | `group_id` | BIGINT | ✓ 一致,可为NULL |
| `status` | `status` | ENUM | active/inactive/maintenance |
| `sort_order` | `sort_order` | INT | ✓ 一致 |
| `created_at` | `created_at` | TIMESTAMP | ✓ 自动管理 |
| `updated_at` | `updated_at` | TIMESTAMP | ✓ 自动更新 |

**重要区别**:
- ❌ 没有`server`字段 → 使用`address`
- ❌ 没有`node_group`字段 → 使用`group_id`
- ❌ 没有`traffic_rate`字段 → 新表结构不包含
- ❌ 没有`info/tag`字段 → 使用`metadata` JSON字段
- ✅ `updated_at`是TIMESTAMP,自动更新

## 最终修复内容

### 文件1: `modules/addons/orrism_admin/includes/node_manager.php`

#### getNodesWithStats() - 第121-248行
```php
SELECT
    n.id,
    n.type as node_type,
    n.name as node_name,
    n.address,              -- 不是server
    n.port,
    n.status,
    n.sort_order,          -- 不是sort
    n.updated_at as last_check,
    n.group_id,            -- 不是node_group
    ng.name as group_name,
    ...
FROM nodes n
LEFT JOIN node_groups ng ON n.group_id = ng.id
WHERE n.type = ? AND n.group_id = ?
ORDER BY n.sort_order ASC
```

#### createNode() - 第310-362行
```php
INSERT INTO nodes (
    type, name, address, port, method,
    group_id, status, sort_order,
    created_at, updated_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())

// status值: 'active' 或 'inactive' (ENUM,不是1/0)
```

#### updateNode() - 第367-431行
```php
$fieldMapping = [
    'node_type' => 'type',
    'node_name' => 'name',
    'address' => 'address',    // ✓ 一致
    'port' => 'port',          // ✓ 一致
    'group_id' => 'group_id',  // ✓ 一致
    'node_method' => 'method',
    'status' => 'status',      // ✓ 一致
    'sort_order' => 'sort_order' // ✓ 一致
];

UPDATE nodes SET ... updated_at = NOW() WHERE id = ?
```

#### toggleNodeStatus() - 第458-511行
```php
// 状态切换: active <-> inactive
$newStatus = ($node->status === 'active') ? 'inactive' : 'active';
UPDATE nodes SET status = ?, updated_at = NOW() WHERE id = ?
```

#### batchUpdateNodes() - 第516-590行
```php
// Enable
UPDATE nodes SET status = 'active', updated_at = NOW() WHERE id IN (...)

// Disable
UPDATE nodes SET status = 'inactive', updated_at = NOW() WHERE id IN (...)

// Change group
UPDATE nodes SET group_id = ?, updated_at = NOW() WHERE id IN (...)
```

### 文件2: `modules/addons/orrism_admin/lib/Admin/Controller.php`

#### handleNodeCreate() - 第1746-1756行
```php
$nodeData = [
    'node_type' => $_POST['node_type'] ?? '',
    'node_name' => $_POST['node_name'] ?? '',
    'address' => $_POST['address'] ?? '',
    'port' => $_POST['port'] ?? '',
    'group_id' => $_POST['group_id'] ?? null,  // 可以为NULL
    'node_method' => $_POST['node_method'] ?? 'aes-256-gcm',
    'status' => isset($_POST['status']) && $_POST['status'] == '1'
        ? 'active' : 'inactive',  // 转换为ENUM值
    'sort_order' => $_POST['sort_order'] ?? 0
];
```

## 关键注意事项

### 1. status字段
```php
// ✗ 错误 - 旧表结构使用tinyint
'status' => 1 或 0

// ✓ 正确 - 新表结构使用ENUM
'status' => 'active' 或 'inactive' 或 'maintenance'
```

### 2. updated_at字段
```php
// ✗ 错误 - 旧表使用unix timestamp
'updated_at' => time()

// ✓ 正确 - 新表使用TIMESTAMP,自动更新
updated_at = NOW()
```

### 3. group_id字段
```php
// ✓ 正确 - 字段名一致,但可以为NULL
'group_id' => $_POST['group_id'] ?? null
```

### 4. 移除的字段
以下字段在新表结构中不存在:
- `traffic_rate` / `rate` - 流量倍率
- `info` / `tag` - 节点信息(使用metadata JSON代替)
- `node_group` - 改为`group_id`
- `server` - 改为`address`
- `sort` - 改为`sort_order`

## 验证方法

### 1. 检查数据库
```sql
-- 查看表结构
DESCRIBE nodes;

-- 查看现有数据
SELECT id, type, name, address, port, status, group_id FROM nodes;
```

### 2. 测试操作

**添加节点:**
1. 填写表单
2. 点击Save
3. 检查响应: `{success: true, message: "Node created successfully", node_id: X}`
4. 数据库验证: `SELECT * FROM nodes WHERE id = X`

**编辑节点:**
1. 点击Edit
2. 修改字段
3. 保存
4. 验证数据库中的`updated_at`已更新

**删除节点:**
1. 点击Delete
2. 确认
3. 验证数据库中记录已删除

### 3. 查看日志
```bash
tail -f /var/log/php_errors.log | grep -i node
```

应该看到:
```
handleNodeCreate called - POST data: {...}
NodeManager instance created successfully
Node data prepared: {...}
Node created successfully with ID: 123
```

## 两个表结构对比

| 特性 | api/database.php | database_manager.php |
|-----|-----------------|---------------------|
| 字段名 | server, node_group, sort | address, group_id, sort_order |
| status类型 | tinyint(0/1) | ENUM(active/inactive/maintenance) |
| updated_at | int(unix timestamp) | TIMESTAMP(自动更新) |
| traffic_rate | ✓ 有 | ✗ 没有 |
| 使用场景 | 旧版/API模块 | **实际使用的表** |

## 总结

✅ **所有字段名已正确映射到database_manager.php的表结构**
✅ **status字段使用ENUM值(active/inactive)**
✅ **updated_at使用NOW()自动更新**
✅ **group_id可以为NULL**
✅ **移除了不存在的字段(traffic_rate, info等)**
✅ **完整的AJAX处理和错误日志**

现在node的增删改查功能应该**完全正常**工作了! 🎉

## 如果还有问题

请检查:
1. 数据库中实际使用的是哪个表结构?
2. `DESCRIBE nodes;` 的输出
3. PHP错误日志的完整错误信息
4. 浏览器Network标签的AJAX响应内容
