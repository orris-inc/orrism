# HTTP 500错误修复说明

## 问题分析

### 现象
```
172.22.0.3 - "GET /admin/addonmodules.php" 500
```

但是:
- ✅ 节点创建成功 (ID: 1)
- ✅ 返回了正确的JSON
- ❌ HTTP状态码是500
- ❌ 前端没有收到响应/提示

### 根本原因

HTTP 500表示服务器内部错误,最常见的原因:

1. **PHP Fatal Error** - 代码执行中断
2. **未捕获的异常** - Exception propagated
3. **数据库连接失败** - 初始化错误
4. **内存不足** - OOM
5. **输出缓冲问题** - Buffer 管理错误

根据日志显示节点已经创建,说明大部分代码执行成功了。500错误可能发生在:
- 响应输出之后
- 脚本清理阶段
- 连接关闭时

## 已实施的修复

### 1. NodeManager数据库连接增强

**文件**: `modules/addons/orrism_admin/includes/node_manager.php`

**修改内容**:

#### 添加三层数据库连接Fallback机制

```php
private function initDatabase()
{
    try {
        // 第1层: 尝试OrrisDB
        if (class_exists('OrrisDB')) {
            try {
                $this->db = OrrisDB::connection();
                if ($this->db) {
                    $this->pdo = $this->db->getPdo();
                    error_log('NodeManager: Using OrrisDB connection');
                    return;
                }
            } catch (Exception $e) {
                error_log('NodeManager: OrrisDB connection failed - ' . $e->getMessage());
            }
        }

        // 第2层: 尝试WHMCS Capsule
        if (!$this->pdo && class_exists('Illuminate\Database\Capsule\Manager')) {
            try {
                $capsule = \Illuminate\Database\Capsule\Manager::connection();
                $this->pdo = $capsule->getPdo();
                error_log('NodeManager: Using WHMCS Capsule connection');
                return;
            } catch (Exception $e) {
                error_log('NodeManager: Capsule connection failed - ' . $e->getMessage());
            }
        }

        // 第3层: 直接PDO连接
        if (!$this->pdo) {
            error_log('NodeManager: Attempting direct PDO connection');
            $this->initDirectPDO();
        }

    } catch (Exception $e) {
        error_log('NodeManager: Failed to initialize database - ' . $e->getMessage());
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}
```

#### 添加直接PDO连接方法

```php
private function initDirectPDO()
{
    try {
        global $whmcs;

        $host = $whmcs->get_config('db_host') ?? 'localhost';
        $name = $whmcs->get_config('db_name') ?? '';
        $user = $whmcs->get_config('db_username') ?? '';
        $pass = $whmcs->get_config('db_password') ?? '';

        if (empty($name)) {
            throw new Exception('Database configuration not found');
        }

        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        error_log('NodeManager: Direct PDO connection established');

    } catch (Exception $e) {
        error_log('NodeManager: Direct PDO connection failed - ' . $e->getMessage());
        throw $e;
    }
}
```

#### 增强createNode的错误检查

```php
// Insert node
$result = $this->execute($sql, $bindings);

if (!$result) {
    throw new Exception('Failed to execute INSERT query');
}

if (!$this->pdo) {
    throw new Exception('PDO connection is null');
}

$nodeId = $this->pdo->lastInsertId();
```

### 2. 前端调试增强

**文件**: `modules/addons/orrism_admin/includes/node_ui.php`

添加了详细的Console日志,帮助诊断前端问题:

```javascript
console.log("=== Saving Node ===");
console.log("=== AJAX Success ===");
console.log("Response:", response);
console.log("Content-Type:", xhr.getResponseHeader("Content-Type"));
```

## 诊断工具

### 1. 诊断脚本

运行诊断脚本来识别具体问题:

```bash
php diagnose_500_error.php
```

或者通过浏览器访问:
```
http://your-whmcs-url/diagnose_500_error.php
```

诊断脚本会检查:
1. PHP版本兼容性
2. WHMCS环境
3. 必要文件存在性
4. OrrisDB类加载
5. NodeManager类初始化
6. 数据库连接
7. 模拟AJAX请求

### 2. 查看PHP错误日志

```bash
# 实时监控错误日志
tail -f /var/log/php_errors.log

# 或Docker容器日志
docker logs -f whmcs_container_name 2>&1 | grep -i "error\|fatal\|warning"
```

### 3. 检查Nginx/Apache日志

```bash
# Nginx错误日志
tail -f /var/log/nginx/error.log

# Apache错误日志
tail -f /var/log/apache2/error.log
```

## 可能的500错误原因及解决方案

### 原因1: 数据库连接在响应后失败

**症状**:
- 节点创建成功
- JSON返回
- 但连接关闭时错误

**解决**:
✅ 已实施 - 三层Fallback机制确保连接稳定

### 原因2: 输出缓冲管理问题

**症状**:
- `ob_clean()` / `ob_end_flush()` 顺序错误
- 缓冲区嵌套问题

**检查代码**: Controller.php ajax()方法

```php
// 清理所有缓冲区
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// ... AJAX处理 ...

ob_clean();
echo json_encode($result);
ob_end_flush();
exit;  // 确保立即退出
```

**可能的问题**: 如果exit之前有错误,`ob_end_flush()`可能失败

**改进方案**:
```php
try {
    ob_clean();
    echo json_encode($result);
    ob_end_flush();
} catch (Exception $e) {
    error_log('Output buffer error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Output error']);
}
exit;
```

### 原因3: Header已发送错误

**症状**:
```
Warning: Cannot modify header information - headers already sent
```

**原因**:
- PHP文件有BOM字符
- 文件开头有空白
- 之前有echo/print输出

**检查**:
```bash
# 检查BOM
hexdump -C node_manager.php | head -1

# 应该是: 00000000  3c 3f 70 68 70 0a  |<?php.|
# 不应该是: 00000000  ef bb bf 3c 3f 70 68 70  |...<?php|
```

**修复BOM**:
```bash
# 移除BOM
sed -i '1s/^\xEF\xBB\xBF//' node_manager.php
```

### 原因4: PHP内存或执行时间限制

**症状**:
- 大量数据处理
- 复杂查询
- 超时

**检查php.ini**:
```ini
memory_limit = 256M        # 应该足够
max_execution_time = 300   # 5分钟
```

**临时增加**:
```php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
```

### 原因5: Exception在exit之后抛出

**症状**:
- 脚本shutdown阶段错误
- 析构函数中的错误

**添加shutdown handler**:
```php
// 在Controller构造函数中添加
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log('Fatal error during shutdown: ' . print_r($error, true));
    }
});
```

## 推荐的排查顺序

### 步骤1: 查看完整的PHP错误日志

```bash
tail -100 /var/log/php_errors.log | grep -A 5 -B 5 "Fatal\|Error"
```

找到Fatal Error或Exception的完整堆栈跟踪。

### 步骤2: 运行诊断脚本

```bash
php diagnose_500_error.php > diagnosis.txt 2>&1
cat diagnosis.txt
```

查看哪个测试失败了。

### 步骤3: 检查浏览器Network标签

1. F12 > Network
2. 执行添加node操作
3. 找到`addonmodules.php`请求
4. 查看:
   - Status: 500
   - Response: 实际返回的内容
   - Headers: Content-Type是否正确

### 步骤4: 测试简化版本

创建测试文件 `test_node_minimal.php`:

```php
<?php
define('WHMCS', true);
require_once 'init.php';

// 最小化测试
try {
    require_once 'modules/addons/orrism_admin/includes/node_manager.php';
    $nm = new NodeManager();
    echo "NodeManager created successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
```

### 步骤5: 启用详细错误报告

在`orrism_admin.php`顶部临时添加:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

## 临时解决方案

如果无法立即定位500错误,可以使用以下临时方案:

### 方案A: 捕获所有错误并返回JSON

修改Controller的ajax()方法:

```php
public function ajax($vars)
{
    // 注册错误处理
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    try {
        // ... 现有代码 ...
    } catch (Throwable $e) {
        // 捕获所有错误和异常
        ob_clean();
        header('HTTP/1.1 200 OK');  // 强制200
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        exit;
    }

    restore_error_handler();
}
```

### 方案B: 使用独立的AJAX端点

创建 `modules/addons/orrism_admin/ajax.php`:

```php
<?php
define('WHMCS', true);
require_once '../../../init.php';

header('Content-Type: application/json');

try {
    $action = $_REQUEST['action'] ?? '';

    if ($action === 'node_create') {
        require_once 'includes/node_manager.php';
        $nm = new NodeManager();

        $nodeData = [
            'node_type' => $_POST['node_type'],
            'node_name' => $_POST['node_name'],
            // ... 其他字段
        ];

        $result = $nm->createNode($nodeData);
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
```

然后修改前端URL:
```javascript
url: "modules/addons/orrism_admin/ajax.php?action=" + action
```

## 验证修复

修复后,检查以下几点:

1. **HTTP状态码**
   ```bash
   curl -I "http://your-site/admin/addonmodules.php?module=orrism_admin&action=node_create"
   ```
   应该看到: `HTTP/1.1 200 OK`

2. **响应Content-Type**
   应该是: `Content-Type: application/json; charset=utf-8`

3. **响应内容**
   应该是纯JSON,没有任何HTML或PHP Notice

4. **浏览器Console**
   应该看到: `=== AJAX Success ===`

5. **前端提示**
   应该弹出: "✓ Node saved successfully! Node ID: X"

## 总结

✅ **已完成的修复**:
1. 增强数据库连接 - 三层Fallback
2. 添加详细错误日志
3. 前端调试增强
4. 创建诊断工具

📋 **下一步**:
1. 运行诊断脚本
2. 查看完整的错误日志
3. 根据具体错误实施对应修复
4. 验证HTTP 200和正确的JSON响应

🔍 **需要的信息**:
请提供以下信息以精确定位问题:
1. PHP错误日志的完整错误信息
2. 诊断脚本的输出结果
3. 浏览器Network标签的完整响应
4. Console的完整输出
