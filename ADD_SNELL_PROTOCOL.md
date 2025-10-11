# 添加 Snell 协议支持

## 修改日期
2025-10-11

## 概述

为 ORRISM 添加 Snell 协议支持。Snell 是一个轻量级的代理协议，只需要配置地址和端口。

## Snell 协议特点

- **简单配置**: 仅需地址、端口和版本
- **版本**: 支持 v3 和 v4
- **性能**: 轻量级，低延迟
- **用途**: 适合作为简单的代理节点

## 重要说明

### ⚠️ 不需要数据库迁移！

数据库的 `type` 字段已经是 `VARCHAR` 类型，可以直接存储 'snell' 值，**无需执行任何 SQL 迁移**。

- ✅ `type` 字段：VARCHAR(32) - 可以直接使用
- ✅ `method` 字段：VARCHAR(32) - 存储 Snell 版本（v3/v4）
- ✅ 无需修改数据库结构

## 已修改的代码文件

### 1. NodeManager.php

**文件**: `modules/addons/orrism_admin/includes/node_manager.php`

#### 添加节点类型
```php
public function getNodeTypes()
{
    return [
        'shadowsocks' => 'Shadowsocks',
        'vless' => 'VLESS',
        'vmess' => 'VMESS',
        'trojan' => 'Trojan',
        'snell' => 'Snell'          // ✓ 新增
    ];
}
```

#### 添加版本选项（对应 method 字段）
```php
public function getEncryptionMethods($nodeType)
{
    $methods = [
        'shadowsocks' => [
            'aes-128-gcm' => 'AES-128-GCM',
            'aes-192-gcm' => 'AES-192-GCM',
            'aes-256-gcm' => 'AES-256-GCM',
            'chacha20-ietf-poly1305' => 'ChaCha20-IETF-Poly1305'
        ],
        // ... 其他协议 ...
        'snell' => [
            'v3' => 'Snell v3',      // ✓ 版本号，不是加密方法
            'v4' => 'Snell v4'       // ✓ 版本号，不是加密方法
        ]
    ];

    return $methods[$nodeType] ?? [];
}
```

### 2. database_manager.php

**文件**: `modules/servers/orrism/includes/database_manager.php`

#### 更新 ENUM 类型定义（仅新安装生效）
```php
$table->enum('type', [
    'shadowsocks', 'v2ray', 'trojan',
    'vless', 'vmess', 'hysteria',
    'snell'  // ✓ 新增
])->default('shadowsocks');
```

**注意**: 此修改仅影响新安装的数据库。现有数据库因为使用 VARCHAR 类型，无需迁移。

## 字段说明

### Snell 协议的字段对应关系

| 前端字段 | 数据库字段 | Snell 含义 | 示例值 |
|---------|-----------|-----------|--------|
| Node Type | `type` | 协议类型 | `snell` |
| Address | `address` | 服务器地址 | `hk.example.com` |
| Port | `port` | 服务端口 | `6160` |
| Method | `method` | **Snell 版本** | `v3` 或 `v4` |

**重要**:
- `method` 字段对于 Snell 存储的是**版本号**（v3/v4）
- 不是加密方法（Snell 不需要选择加密方法）

## 使用方法

### 在管理界面创建 Snell 节点

1. 进入 **Addons > ORRISM Administration > Node Management**
2. 点击 **Add Node** 按钮
3. 填写表单：
   - **Node Type**: 选择 "Snell"
   - **Node Name**: 节点名称（如 "HK-Snell-01"）
   - **Address**: 服务器地址（如 "hk.example.com"）
   - **Port**: 端口号（如 "6160"）
   - **Method/Version**: 选择 "Snell v3" 或 "Snell v4"
   - **Group**: 选择节点组（可选）
   - **Status**: 选择状态
   - **Sort Order**: 排序值
4. 点击 **Save** 保存

### 示例配置

```
Node Type: Snell
Node Name: HK-Snell-Node
Address: hk-snell.example.com
Port: 6160
Method: v4                    ← Snell 版本
Group: Asia Group
Status: Active
Sort Order: 10
```

## 配置说明

### Snell 协议必填字段
- ✅ **Type**: 固定为 "snell"
- ✅ **Address** (地址): 服务器地址或域名
- ✅ **Port** (端口): Snell 服务端口
- ✅ **Method** (版本): "v3" 或 "v4"

### Snell 协议可选字段
- **Group ID**: 节点分组
- **Status**: 节点状态
- **Sort Order**: 显示顺序
- **Config**: 额外配置（JSON）
- **Metadata**: 元数据（JSON）

## API 示例

### 创建 Snell 节点

```php
$nodeManager = new NodeManager();

$nodeData = [
    'node_type' => 'snell',
    'node_name' => 'Singapore Snell',
    'address' => 'sg.example.com',
    'port' => 6160,
    'node_method' => 'v4',        // Snell 版本
    'group_id' => 1,
    'status' => 'active',
    'sort_order' => 5
];

$result = $nodeManager->createNode($nodeData);
```

### 查询 Snell 节点

```sql
SELECT
    id,
    name,
    address,
    port,
    type,
    method as version,     -- method 字段存储的是版本号
    status
FROM nodes
WHERE type = 'snell'
ORDER BY sort_order ASC;
```

### 示例数据

```sql
-- 插入 Snell v4 节点
INSERT INTO nodes (
    name, type, address, port,
    method, group_id, status,
    sort_order, created_at, updated_at
) VALUES (
    'HK Snell Node',
    'snell',
    'hk.snell.example.com',
    6160,
    'v4',                  -- Snell 版本
    1,
    'active',
    10,
    NOW(),
    NOW()
);
```

## 数据库查询示例

### 按版本统计 Snell 节点

```sql
SELECT
    method as snell_version,
    COUNT(*) as node_count
FROM nodes
WHERE type = 'snell'
GROUP BY method;

-- 示例输出：
-- snell_version | node_count
-- v3           | 5
-- v4           | 12
```

### 查看所有 Snell 节点详情

```sql
SELECT
    id,
    name,
    CONCAT(address, ':', port) as endpoint,
    CONCAT('Snell ', method) as version,
    status,
    CASE
        WHEN status = 'active' THEN '●'
        WHEN status = 'inactive' THEN '○'
        ELSE '◐'
    END as status_icon
FROM nodes
WHERE type = 'snell'
ORDER BY sort_order ASC, id ASC;
```

## 前端显示

在节点列表中，Snell 节点将显示为：

```
[Snell] Singapore Snell v4
Address: sg.example.com:6160
Version: v4
Status: ● Active
```

## Snell 版本说明

### Snell v3
- 稳定版本
- 广泛支持
- 推荐用于兼容性要求高的场景

### Snell v4
- 最新版本
- 性能提升
- 推荐用于新部署节点

## 注意事项

### 配置验证
- ✅ Snell 节点的 `type` 必须为 "snell"
- ✅ `method` 字段存储版本号（v3 或 v4）
- ✅ Address 和 Port 是必填字段
- ⚠️ 不要将 `method` 误认为是加密方法

### 兼容性
- ✅ 新安装的数据库会自动包含 Snell 支持
- ✅ 现有数据库**无需迁移**，直接可用
- ✅ 不影响现有其他类型的节点

### 字段复用
- `method` 字段在不同协议中含义不同：
  - Shadowsocks: 加密方法（aes-256-gcm）
  - VLESS/Trojan: None
  - VMess: 加密方法（auto, aes-128-gcm）
  - **Snell: 版本号（v3, v4）** ⭐

## 配置对比

### Shadowsocks vs Snell

| 配置项 | Shadowsocks | Snell |
|-------|------------|-------|
| 地址 | ✓ 必填 | ✓ 必填 |
| 端口 | ✓ 必填 | ✓ 必填 |
| 加密 | ✓ aes-256-gcm 等 | - 无需配置 |
| 版本 | - 无需配置 | ✓ v3/v4 |
| 密码 | 通过配置管理 | 通过配置管理 |

## 相关文档

- [NodeManager API](modules/addons/orrism_admin/includes/node_manager.php)
- [Database Schema](DATABASE_SCHEMA_CLARIFICATION.md)
- [Node Management UI](modules/addons/orrism_admin/includes/node_ui.php)

## 总结

✅ **已完成的修改**
- 添加 Snell 到节点类型列表
- 添加 v3/v4 版本选项（使用 method 字段）
- 更新数据库 ENUM 定义（仅新安装）

✅ **使用方法**
- 管理界面选择 Snell 类型
- 填写地址和端口
- 选择版本（v3 或 v4）

✅ **无需迁移**
- 现有数据库直接可用
- type 字段已支持任意字符串
- method 字段存储版本号

现在可以在 ORRISM 中使用 Snell 协议节点了！只需在创建节点时选择 Snell 类型，然后填写地址、端口和版本即可。
