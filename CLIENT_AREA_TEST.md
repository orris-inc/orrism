# 客户端区域功能测试指南

## 快速测试步骤

### 1. 测试服务创建 ✅
```
WHMCS Admin > Orders > Add New Order
- 选择 ORRISM 产品
- 选择客户
- 点击 "Add Order"
- 点击 "Accept Order"
```

**预期结果:**
- ✅ 服务状态变为 Active
- ✅ Username 字段显示邮箱
- ✅ Password 字段显示随机密码
- ✅ UUID 字段填充

### 2. 访问客户端区域 ✅
```
1. 以客户身份登录 WHMCS
2. 点击 "Services" > "My Services"
3. 点击 ORRISM 服务
4. 应该看到完整的客户端区域界面
```

**预期显示:**
- ✅ 账户信息面板 (UUID, Email, Status, Max Devices)
- ✅ 流量使用面板 (总量, 已用, 剩余, 进度条)
- ✅ 订阅链接面板 (通用链接 + 6种客户端)
- ✅ QR 码显示
- ✅ 节点列表 (如果有节点)
- ✅ 客户端下载链接

### 3. 测试订阅链接复制 ✅
```
1. 点击 "Copy URL" 按钮
2. 应该看到成功通知
3. 粘贴到任意位置验证
```

**预期格式:**
```
http://your-server.com/subscribe/7b721997-84dc-49d0-b1fb-48547b831644
```

### 4. 测试 QR 码 ✅
```
1. 检查 QR 码是否显示
2. 用手机扫码测试
3. 应该识别为订阅链接
```

### 5. 测试响应式布局 ✅
```
1. Desktop: 按 F12 打开开发者工具
2. 切换到移动视图 (Toggle Device Toolbar)
3. 验证布局正确调整
```

## 故障排查

### 问题: 页面显示 "Service Error"

**检查步骤:**
1. 确认服务已创建:
   ```sql
   SELECT * FROM services WHERE service_id = [你的服务ID];
   ```

2. 检查 OrrisDB 配置:
   ```
   Addon > ORRISM Admin > Dashboard
   查看数据库状态
   ```

3. 检查模块日志:
   ```
   WHMCS > Utilities > Logs > Module Log
   搜索 "orrism"
   ```

### 问题: 流量显示为 0/0

**原因:** 服务刚创建,还没有流量使用

**解决:** 这是正常的,使用后会更新

### 问题: 节点列表为空

**原因:** 没有创建节点或节点组

**解决:**
```
1. 访问 Addon > ORRISM Admin > Node Management
2. 创建节点组
3. 添加节点到组
4. 确保产品配置的 Node Group ID 正确
```

### 问题: QR 码不显示

**检查:**
1. 浏览器控制台是否有错误
2. CDN 是否可访问:
   ```
   https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js
   ```
3. 订阅链接是否正确生成

## 验证清单

- [ ] 服务创建成功
- [ ] 客户端区域可访问
- [ ] 账户信息正确显示
- [ ] 流量统计显示 (即使为0)
- [ ] 进度条渲染
- [ ] 订阅链接可复制
- [ ] QR 码生成
- [ ] 客户端按钮可点击
- [ ] 下载链接正确
- [ ] 移动端布局正常
- [ ] 浏览器兼容

## 下一步

测试通过后,可以:
1. 添加节点到系统
2. 配置流量监控
3. 测试流量重置功能
4. 验证订阅接口返回
5. 测试客户端连接
