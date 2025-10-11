# Client Area Features - 客户端区域功能

## 概述

为 ORRISM WHMCS 模块创建了完整的客户端区域界面,提供流量信息展示、订阅链接管理和客户端下载引导。

## 主要功能

### 1. 账户信息展示 ✅

显示服务账户的关键信息:
- **UUID**: 唯一标识符
- **Account Email**: 登录邮箱 (用作用户名)
- **Status**: 服务状态 (Active/Suspended/Expired)
- **Max Devices**: 最大并发设备数

### 2. 流量使用统计 ✅

实时展示流量使用情况:
- **Total Bandwidth**: 总流量配额 (GB)
- **Used**: 已使用流量 (GB)
- **Remaining**: 剩余流量 (GB)
- **Upload / Download**: 上传/下载流量详情
- **Usage Progress Bar**: 可视化进度条
  - 正常: 蓝紫渐变
  - 警告 (75%+): 粉红渐变
  - 危险 (90%+): 红黄渐变

### 3. 订阅链接管理 ✅

#### Universal Subscription URL
- 通用订阅链接
- 一键复制功能
- 显示为 monospace 字体便于识别

#### 客户端特定链接
支持主流代理客户端:
- **Clash**: `?client=clash`
- **V2Ray**: `?client=v2ray`
- **Shadowrocket**: `?client=shadowrocket`
- **Surge**: `?client=surge`
- **Quantumult X**: `?client=quantumult`
- **Sing-Box**: `?client=sing-box`

#### QR Code 二维码
- 使用 QRCode.js 生成
- 200x200 像素
- 高纠错级别 (H)
- 品牌配色 (#667eea)
- 适合移动端扫码导入

### 4. 节点列表展示 ✅

显示用户可用的所有节点:
- **节点名称**: 清晰标识
- **节点类型**: Shadowsocks/V2Ray/Trojan 等
- **服务器地址**: IP:Port
- **在线状态**: Online/Offline 徽章

### 5. 客户端下载引导 ✅

提供主流平台客户端下载链接:

| 平台 | 推荐客户端 | 下载链接 |
|------|-----------|---------|
| Windows | V2RayN | GitHub Releases |
| macOS | ClashX | GitHub Releases |
| Android | V2RayNG | GitHub Releases |
| iOS | Shadowrocket | App Store |

## 文件结构

```
modules/servers/orrism/
├── templates/
│   ├── clientarea.tpl     # 客户端区域主模板
│   └── error.tpl           # 错误页面模板
└── orrism.php              # 包含 orrism_ClientArea() 函数
```

## 模板变量

### clientarea.tpl 接收的变量:

```php
[
    'serviceid' => int,              // WHMCS 服务 ID
    'uuid' => string,                // 服务 UUID
    'email' => string,               // 用户邮箱 (用作用户名)
    'nodes' => array,                // 可用节点列表
    'totalBandwidth' => float,       // 总流量 (GB)
    'usedBandwidth' => float,        // 已用流量 (GB)
    'remainingBandwidth' => float,   // 剩余流量 (GB)
    'usagePercent' => float,         // 使用百分比
    'uploadGB' => float,             // 上传流量 (GB)
    'downloadGB' => float,           // 下载流量 (GB)
    'subscriptionUrl' => string,     // 订阅链接
    'allowReset' => bool,            // 是否允许手动重置
    'resetCost' => int,              // 重置费用百分比
    'maxDevices' => int,             // 最大设备数
    'status' => string,              // 服务状态
    'lastReset' => string|null       // 上次重置时间
]
```

## 样式设计

### 配色方案
- **主色**: 蓝紫渐变 (#667eea → #764ba2)
- **成功**: 绿色系 (#d4edda)
- **警告**: 黄色系 (#fff3cd)
- **危险**: 红色系 (#f8d7da)
- **背景**: 浅灰 (#f8f9fa)

### 响应式设计
- **Desktop**: 网格布局 (auto-fit, 200px+)
- **Mobile**: 单列布局
- **断点**: 768px

### UI 组件
- **Panel**: 卡片式面板,圆角边框
- **Progress Bar**: 渐变进度条,动态配色
- **Badge**: 状态徽章 (Success/Warning/Danger)
- **Button**: 渐变按钮,悬停动效

## JavaScript 功能

### 1. 复制到剪贴板
```javascript
function copyToClipboard(elementId)
```
- 优先使用现代 Clipboard API
- 回退到 execCommand 兼容旧浏览器
- 显示成功/失败通知

### 2. 通知系统
```javascript
function showNotification(message, type = 'success')
```
- 固定位置右上角
- 3秒后自动淡出
- 支持 success/error 类型

### 3. QR Code 生成
```javascript
new QRCode(element, options)
```
- 使用 qrcodejs 库
- CDN 加载: jsdelivr.net
- 200x200 像素,高纠错

## 集成说明

### 后端函数 (orrism.php)

```php
function orrism_ClientArea(array $params)
{
    $db = db();  // OrrisDatabase 实例
    $service = $db->getService($serviceid);
    $usage = $db->getServiceUsage($serviceid);
    $nodes = $db->getNodesForGroup($service->node_group_id);

    return [
        'templatefile' => 'clientarea',
        'vars' => [/* ... */]
    ];
}
```

### 订阅链接生成

```php
function generate_subscription_url(array $params, $uuid)
{
    $serverHost = $params['serverhostname'] ?: $params['serverip'];
    $protocol = $params['serversecure'] ? 'https' : 'http';
    return "{$protocol}://{$serverHost}/subscribe/{$uuid}";
}
```

## 用户体验流程

1. **访问服务详情**
   ```
   WHMCS Client Area > My Services > [ORRISM Service] > View Details
   ```

2. **查看账户信息**
   - UUID 和邮箱立即可见
   - 状态徽章清晰标识服务状态

3. **检查流量使用**
   - 进度条直观显示使用比例
   - 颜色变化提醒流量不足
   - 上传/下载分别统计

4. **获取订阅链接**
   - 复制通用订阅链接
   - 或选择特定客户端链接
   - 扫描 QR 码 (移动端)

5. **下载客户端**
   - 根据设备平台选择
   - 直接跳转官方下载页

6. **配置客户端**
   - 导入订阅链接
   - 选择可用节点
   - 开始使用

## 安全考虑

### 1. 数据验证
- Service ID 验证
- UUID 格式检查
- 权限控制 (仅显示自己的服务)

### 2. 敏感信息
- 不显示明文密码
- UUID 可安全分享
- 订阅链接包含认证信息

### 3. XSS 防护
- Smarty 模板自动转义
- 用户输入过滤
- URL 参数验证

## 测试步骤

### 1. 访问客户端区域
```
Login to WHMCS > Services > [Your ORRISM Service] > Manage
```

### 2. 验证功能
- [ ] 账户信息正确显示
- [ ] 流量统计准确
- [ ] 进度条动态更新
- [ ] 订阅链接可复制
- [ ] QR 码正常生成
- [ ] 节点列表完整
- [ ] 客户端链接可访问
- [ ] 下载按钮跳转正确

### 3. 响应式测试
- [ ] Desktop (>1200px)
- [ ] Tablet (768-1200px)
- [ ] Mobile (<768px)

### 4. 浏览器兼容
- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari
- [ ] Mobile Safari
- [ ] Chrome Mobile

## 预期效果截图说明

### Desktop 视图
- 顶部: 账户信息 4 卡片布局
- 中部: 流量统计 + 进度条
- 下部: 订阅链接 + QR 码
- 底部: 节点列表 + 客户端下载

### Mobile 视图
- 单列垂直布局
- 卡片全宽显示
- 按钮拉伸到 100%
- QR 码居中显示

## 扩展功能建议

### 短期增强:
1. **流量重置按钮** (如果 allowReset = true)
2. **节点延迟测试**
3. **使用历史图表**
4. **自定义订阅名称**

### 长期规划:
1. **多订阅管理**
2. **设备管理** (查看/撤销设备)
3. **规则订阅** (分流规则)
4. **流量预警通知**
5. **推荐奖励系统**

## 故障排查

### 问题 1: 模板不显示
**症状**: 页面空白或显示原始变量
**原因**: 模板文件路径错误
**解决**:
```bash
# 确认模板目录存在
ls -la modules/servers/orrism/templates/

# 检查文件权限
chmod 644 modules/servers/orrism/templates/*.tpl
```

### 问题 2: QR 码不生成
**症状**: QR 码位置空白
**原因**: CDN 加载失败或 JS 错误
**解决**:
- 检查浏览器控制台
- 验证 CDN 可访问性
- 确认 subscriptionUrl 变量存在

### 问题 3: 订阅链接复制失败
**症状**: 点击复制按钮无反应
**原因**: HTTPS 环境要求或浏览器权限
**解决**:
- 使用 HTTPS 访问
- 允许剪贴板权限
- 降级到 fallbackCopy()

### 问题 4: 流量显示为 0
**症状**: 所有流量数据为 0
**原因**: getServiceUsage() 返回空数据
**解决**:
```php
// 检查 OrrisDatabase::getServiceUsage()
// 确认 services 表有数据
// 验证 service_id 匹配
```

## 总结

✅ **已完成功能:**
- 客户端区域完整界面
- 流量统计和可视化
- 订阅链接管理 (6种客户端)
- QR 码生成
- 节点列表展示
- 客户端下载引导
- 响应式设计
- 复制/通知交互

🎨 **设计亮点:**
- 现代化渐变配色
- 流畅的动画效果
- 清晰的信息层级
- 友好的用户引导

🔒 **安全特性:**
- 数据验证
- XSS 防护
- 权限控制

📱 **设备兼容:**
- 全平台响应式
- 主流浏览器支持
- 移动端优化

现在用户可以通过 WHMCS 客户端区域完整地管理他们的 ORRISM 服务! 🚀
