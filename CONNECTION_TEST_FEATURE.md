# 数据库连接测试功能

## 功能概述
在Settings页面添加了数据库和Redis连接测试功能，让用户在保存配置前可以验证连接是否正确。

## 功能特性

### 1. 数据库连接测试
- **测试内容**：
  - 验证主机地址是否可达
  - 验证数据库名称是否存在
  - 验证用户名密码是否正确
  - 检测ORRISM表是否已安装
  
- **错误提示**：
  - 访问拒绝：提示检查用户名密码
  - 数据库不存在：提示数据库名称错误
  - 服务器连接失败：提示检查主机地址
  - 其他错误：显示详细错误信息

- **成功反馈**：
  - 显示数据库名称
  - 显示MySQL服务器版本
  - 提示是否已安装ORRISM表

### 2. Redis连接测试
- **测试内容**：
  - 检查Redis扩展是否安装
  - 验证Redis服务器连接
  - 发送PING命令测试响应

- **错误提示**：
  - 扩展未安装：提示安装PHP Redis扩展
  - 连接失败：显示服务器地址和端口

## 使用方法

### 测试数据库连接
1. 在Settings页面填写数据库配置
2. 点击"Test Connection"按钮
3. 查看测试结果：
   - ✅ 绿色：连接成功
   - ⚠️ 黄色：连接成功但未安装表
   - ❌ 红色：连接失败，查看错误信息

### 测试Redis连接
1. 填写Redis主机和端口
2. 点击"Test Redis"按钮
3. 查看测试结果

## 技术实现

### 前端JavaScript
```javascript
function testDatabaseConnection() {
    // 获取表单值
    var host = document.getElementById("db_host").value;
    var name = document.getElementById("db_name").value;
    
    // AJAX请求
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "addonmodules.php?module=orrism_admin&action=test_connection", true);
    
    // 处理响应
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            // 显示结果
        }
    };
}
```

### 后端PHP处理
```php
function testDatabaseConnection() {
    // PDO连接测试
    $dsn = "mysql:host=$host;dbname=$name;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // 检查表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'mod_orrism_%'");
    $tablesExist = $stmt->rowCount() > 0;
    
    return ['success' => true, 'tables_exist' => $tablesExist];
}
```

## UI展示

### 测试按钮
- 位置：紧跟在Save按钮后面
- 图标：数据库使用fa-plug，Redis使用fa-server
- 状态：测试中显示spinner动画，按钮禁用

### 结果显示
- 位置：表单下方独立区域
- 样式：使用Bootstrap Alert组件
- 内容：
  - 成功：显示连接详情
  - 失败：显示错误原因
  - 警告：连接成功但需要操作

## 用户体验优化

### 1. 实时反馈
- 点击即测试，无需保存
- 测试过程显示loading动画
- 结果立即显示在页面上

### 2. 清晰的错误信息
- 根据错误类型显示友好提示
- 避免显示技术性错误信息
- 提供解决建议

### 3. 状态颜色
- 🟢 绿色：完全成功
- 🟡 黄色：部分成功，需要操作
- 🔴 红色：失败，需要修正

## 安全考虑

### 1. 输入验证
- 前端验证必填字段
- 后端二次验证参数

### 2. 错误处理
- PDO异常捕获
- 敏感信息不暴露给用户
- 详细错误记录到日志

### 3. 超时控制
- 数据库连接超时：5秒
- Redis连接超时：2秒
- 防止长时间等待

## 扩展性

### 可添加的功能
1. **批量测试**：同时测试多个配置
2. **性能测试**：测试查询响应时间
3. **权限测试**：验证CREATE、DROP等权限
4. **版本检查**：检查MySQL最低版本要求
5. **网络诊断**：ping、telnet等网络测试

### 配置建议
1. **测试历史**：记录测试结果
2. **自动测试**：保存前自动测试
3. **定期检测**：后台定期检测连接状态

## 故障排查

### 常见问题

1. **AJAX请求失败**
   - 检查URL路径是否正确
   - 验证模块是否激活
   - 查看浏览器控制台错误

2. **返回空响应**
   - 检查PHP错误日志
   - 验证JSON编码是否正确
   - 确认没有额外输出

3. **测试结果不准确**
   - 清除浏览器缓存
   - 检查防火墙设置
   - 验证数据库权限

## 总结

连接测试功能提供了：
- ✅ 即时的配置验证
- ✅ 友好的错误提示
- ✅ 直观的状态反馈
- ✅ 提升配置体验

用户可以在保存配置前确保设置正确，减少配置错误和调试时间。