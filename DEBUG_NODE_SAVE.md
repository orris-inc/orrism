# 调试Node保存不生效问题

## 当前状态
已添加所有必要的后端代码,但保存仍然不生效。现在需要调试找出具体问题。

## 调试步骤

### 步骤1: 检查浏览器控制台

1. 打开WHMCS管理后台
2. 进入 Addons > ORRISM Administration > Node Management
3. 按F12打开浏览器开发者工具
4. 切换到 **Network** 标签
5. 点击 "Add Node",填写表单,点击 "Save"
6. 观察Network标签中的请求:

**需要检查的内容:**

| 检查项 | 预期值 | 如果不符合 |
|--------|--------|------------|
| 请求URL | `addonmodules.php?module=orrism_admin&action=node_create` | 检查node_ui.php第500行 |
| 请求方法 | POST | 检查$.post调用 |
| 响应状态 | 200 OK | 如果404/500,检查错误 |
| Content-Type | application/json | 应该返回JSON |
| 响应内容 | `{success: true/false, message: "..."}` | 检查是否是JSON格式 |

**截图需要的信息:**
- Request URL
- Request Headers
- Request Payload (POST data)
- Response Headers
- Response body

### 步骤2: 检查PHP错误日志

我已经在代码中添加了详细的debug日志。查看PHP错误日志应该能看到:

```
AJAX method called - action: node_create
GET params: {...}
POST params: {...}
handleNodeCreate called - POST data: {...}
Loading NodeManager from: /path/to/node_manager.php
NodeManager instance created successfully
Node data prepared: {...}
Node create operation result: {...}
```

**日志文件位置:**
- WHMCS默认: `/var/log/php_errors.log`
- 或者在php.ini中配置的error_log路径
- 或者Apache/Nginx日志目录

**查看日志命令:**
```bash
# 实时查看日志
tail -f /path/to/php_errors.log

# 搜索特定关键词
grep -i "node" /path/to/php_errors.log
grep -i "AJAX method called" /path/to/php_errors.log
```

### 步骤3: 使用测试页面

打开测试页面:
```
http://your-whmcs-url/modules/addons/orrism_admin/test_ajax.html
```

点击 "Test Create Node" 和 "Test AJAX Routing" 按钮,查看结果。

### 步骤4: 检查WHMCS路由

WHMCS的addon模块通过 `addonmodules.php` 路由请求。验证流程:

1. **请求到达** `addonmodules.php`
2. **加载** `modules/addons/orrism_admin/orrism_admin.php`
3. **调用** `orrism_admin_output()` 函数
4. **创建** Controller实例
5. **调用** `Controller->dispatch()`
6. **检查** `isAjaxRequest()` 返回true
7. **调用** `Controller->ajax()`
8. **执行** switch分支中的 `handleNodeCreate()`

**可能的断点:**
- `orrism_admin.php` 第269行: Controller实例创建
- `Controller.php` 第100行: isAjaxRequest检查
- `Controller.php` 第430行: AJAX日志输出
- `Controller.php` 第448行: node_create case分支

## 常见问题排查

### 问题1: AJAX请求根本没有发送

**症状:** Network标签中看不到请求

**原因:**
- JavaScript错误导致代码没有执行
- jQuery未加载
- saveNode函数未定义

**解决:**
1. 检查Console标签是否有JavaScript错误
2. 在浏览器console中测试:
   ```javascript
   typeof jQuery  // 应该返回 "function"
   typeof saveNode  // 应该返回 "function"
   ```

### 问题2: 请求发送了但返回404

**症状:** Network显示404 Not Found

**原因:**
- URL路径错误
- addonmodules.php文件不存在
- WHMCS安装目录问题

**解决:**
1. 检查WHMCS是否正确安装
2. 验证URL: `http://your-site/whmcs/addonmodules.php`
3. 检查Apache/Nginx配置

### 问题3: 返回200但不是JSON格式

**症状:**
- 响应状态200
- Content-Type不是application/json
- 或者响应body是HTML而不是JSON

**原因:**
- `isAjaxRequest()` 返回false,走了正常页面路由
- AJAX处理中有PHP错误,输出了HTML错误页面
- Buffer没有正确清理

**解决:**
1. 检查 `isAjaxRequest()` 的返回值
2. 确保action名称在$ajaxActions数组中
3. 查看响应HTML,找出错误信息

### 问题4: 返回JSON但success=false

**症状:** 收到JSON响应,但`{success: false, message: "..."}`

**原因:**
- NodeManager加载失败
- 数据库连接失败
- 必填字段缺失
- SQL执行错误

**解决:**
1. 查看返回的message字段
2. 检查PHP错误日志
3. 验证数据库连接
4. 检查POST数据是否完整

### 问题5: 返回success=true但数据库没有记录

**症状:**
- AJAX返回成功
- 但数据库中没有新记录

**原因:**
- NodeManager::createNode()虽然返回成功但实际没执行INSERT
- PDO执行失败但被捕获了
- 数据库事务未提交

**解决:**
1. 在NodeManager::createNode()中添加日志
2. 检查PDO错误模式
3. 验证SQL语句
4. 检查数据库用户权限

## 快速诊断脚本

创建文件 `modules/addons/orrism_admin/diagnose.php`:

```php
<?php
define('WHMCS', true);
require_once '../../../init.php';

echo "<pre>";
echo "=== ORRISM Node Save Diagnostics ===\n\n";

// Check 1: Controller file exists
$controllerPath = __DIR__ . '/lib/Admin/Controller.php';
echo "1. Controller file: ";
echo file_exists($controllerPath) ? "✓ EXISTS\n" : "✗ NOT FOUND\n";

// Check 2: NodeManager file exists
$nodeManagerPath = __DIR__ . '/includes/node_manager.php';
echo "2. NodeManager file: ";
echo file_exists($nodeManagerPath) ? "✓ EXISTS\n" : "✗ NOT FOUND\n";

// Check 3: Load Controller class
try {
    require_once $controllerPath;
    echo "3. Load Controller: ✓ SUCCESS\n";
} catch (Exception $e) {
    echo "3. Load Controller: ✗ FAILED - " . $e->getMessage() . "\n";
}

// Check 4: Controller class exists
echo "4. Controller class: ";
echo class_exists('WHMCS\\Module\\Addon\\OrrisAdmin\\Admin\\Controller') ? "✓ EXISTS\n" : "✗ NOT FOUND\n";

// Check 5: Load NodeManager
try {
    require_once $nodeManagerPath;
    echo "5. Load NodeManager: ✓ SUCCESS\n";
} catch (Exception $e) {
    echo "5. Load NodeManager: ✗ FAILED - " . $e->getMessage() . "\n";
}

// Check 6: NodeManager class exists
echo "6. NodeManager class: ";
echo class_exists('NodeManager') ? "✓ EXISTS\n" : "✗ NOT FOUND\n";

// Check 7: Check if methods exist
if (class_exists('WHMCS\\Module\\Addon\\OrrisAdmin\\Admin\\Controller')) {
    $reflection = new ReflectionClass('WHMCS\\Module\\Addon\\OrrisAdmin\\Admin\\Controller');
    $methods = ['handleNodeCreate', 'handleNodeUpdate', 'handleNodeGet', 'ajax', 'isAjaxRequest'];

    echo "\n7. Controller methods:\n";
    foreach ($methods as $method) {
        $exists = $reflection->hasMethod($method);
        echo "   - $method: " . ($exists ? "✓" : "✗") . "\n";
    }
}

// Check 8: Simulate AJAX request detection
if (class_exists('WHMCS\\Module\\Addon\\OrrisAdmin\\Admin\\Controller')) {
    echo "\n8. AJAX detection test:\n";

    $controller = new WHMCS\Module\Addon\OrrisAdmin\Admin\Controller([]);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('isAjaxRequest');
    $method->setAccessible(true);

    // Test different actions
    $testActions = ['node_create', 'node_update', 'node_get', 'test_database'];
    foreach ($testActions as $action) {
        $_GET['action'] = $action;
        $isAjax = $method->invoke($controller);
        echo "   - action=$action: " . ($isAjax ? "✓ Recognized" : "✗ Not recognized") . "\n";
    }
}

echo "\n=== End of Diagnostics ===\n";
echo "</pre>";
```

然后访问: `http://your-site/whmcs/modules/addons/orrism_admin/diagnose.php`

## 下一步

根据上述调试结果,请提供:

1. 浏览器Network标签的截图或详细信息
2. PHP错误日志的相关内容
3. 测试页面的输出结果
4. 诊断脚本的输出

这样我就能准确定位问题所在并提供针对性的解决方案。
