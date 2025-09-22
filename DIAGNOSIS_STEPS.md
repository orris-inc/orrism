# ORRISM 空白页面问题诊断步骤

## 🚨 当前状况
- 访问 `https://portal.milus.one/admin/addonmodules.php?module=orrism_admin` 显示空白页面
- 即使使用极简测试版本仍然空白
- 说明 `orrism_admin_output()` 函数没有被 WHMCS 调用

## 🔍 诊断步骤

### 步骤 1: 检查模块是否被 WHMCS 识别
**请访问**: `https://portal.milus.one/admin/configaddonmods.php`

在 "Available Modules" 列表中查看：
- ✅ **如果看到 "ORRISM Administration"** → 模块被识别，进入步骤 2
- ❌ **如果没有看到** → 模块文件有问题，需要检查文件路径和权限

### 步骤 2: 检查模块激活状态
在 "Currently Active Modules" 部分查看：
- ✅ **如果显示已激活** → 进入步骤 3
- ❌ **如果未激活** → 点击 "Activate" 按钮激活模块

### 步骤 3: 检查错误日志
查看以下日志文件中是否有 `ORRISM DEBUG:` 条目：

**可能的日志位置**:
```bash
# PHP 错误日志
tail -f /var/log/php_errors.log | grep ORRISM

# Apache 错误日志  
tail -f /var/log/apache2/error.log | grep ORRISM

# Nginx 错误日志
tail -f /var/log/nginx/error.log | grep ORRISM

# 系统日志
tail -f /var/log/syslog | grep ORRISM

# 临时调试文件
cat /tmp/orrism_debug.log
```

### 步骤 4: 检查文件权限
```bash
# 检查模块目录权限
ls -la /var/www/html/modules/addons/
ls -la /var/www/html/modules/addons/orrism_admin/

# 确保文件可读
chmod 644 /var/www/html/modules/addons/orrism_admin/orrism_admin.php
```

### 步骤 5: 手动测试模块加载
创建测试页面验证模块是否可以被 PHP 加载：

```php
<?php
// 临时测试文件: /var/www/html/test_orrism.php
define('WHMCS', true);
require_once '/var/www/html/modules/addons/orrism_admin/orrism_admin.php';

echo "Config: ";
var_dump(orrism_admin_config());

echo "Output: ";
var_dump(orrism_admin_output([]));
?>
```

## 🎯 可能的问题原因

### 1. **模块未激活**
- WHMCS 没有激活该模块
- 需要在 Admin → Setup → Addon Modules 中激活

### 2. **文件路径错误**
- 模块文件不在正确位置
- 应该在: `/var/www/html/modules/addons/orrism_admin/orrism_admin.php`

### 3. **文件权限问题**
- Web 服务器无法读取模块文件
- 需要设置正确的文件权限

### 4. **PHP 语法错误**
- 模块文件有语法错误导致加载失败
- 检查 PHP 错误日志

### 5. **WHMCS 版本兼容性**
- 模块可能与当前 WHMCS 版本不兼容
- 需要检查 WHMCS 版本和模块要求

### 6. **函数名冲突**
- 函数名与其他模块冲突
- 需要重命名函数或检查命名空间

## 📋 报告模板

请按以下格式报告检查结果：

```
步骤 1 - 模块识别: [✅/❌] 
步骤 2 - 激活状态: [✅/❌]
步骤 3 - 错误日志: [发现的日志内容]
步骤 4 - 文件权限: [权限信息]
步骤 5 - 手动测试: [测试结果]

WHMCS 版本: [版本号]
PHP 版本: [版本号]
Web 服务器: [Apache/Nginx]
```

根据检查结果，我会提供针对性的解决方案。