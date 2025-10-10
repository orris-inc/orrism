# WHMCS Module Best Practices - 改进总结

## 📅 改进日期
2024年 - ORRISM WHMCS Module 优化

## 🎯 改进目标
根据 WHMCS 官方最佳实践,优化 ORRISM module 的安全性、可维护性和代码质量。

---

## ✅ 已完成的改进

### 1. 敏感数据保护 (高优先级 - 安全性) ✅

#### 问题
所有 `logModuleCall()` 调用未使用 `$replaceVars` 参数隐藏敏感信息,可能导致密码、API密钥等泄露到日志中。

#### 解决方案
为所有 `logModuleCall()` 调用添加敏感字段保护:

```php
// ❌ 修改前
logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());

// ✅ 修改后
logModuleCall(
    'orrism',
    __FUNCTION__,
    $params,
    $e->getMessage(),
    $e->getTraceAsString(),
    ['password', 'serverpassword', 'apikey', 'token'] // 隐藏敏感字段
);
```

#### 影响范围
- 文件: `modules/servers/orrism/orrism.php`
- 修改位置: 约 15 处函数
- 涉及函数:
  - TestConnection
  - CreateAccount
  - SuspendAccount
  - UnsuspendAccount
  - TerminateAccount
  - ChangePassword
  - ChangePackage
  - Renew
  - ResetTraffic
  - ResetUUID
  - ViewUsage
  - ClientArea
  - ClientResetTraffic
  - AdminServicesTabFields
  - ServiceSingleSignOn
  - AdminSingleSignOn
  - UsageUpdate
  - save_custom_field

---

### 2. 统一 API 调用封装 (中优先级 - 可维护性) ✅

#### 问题
部分代码直接调用 `localAPI()`,未使用已有的 `OrrisWhmcsHelper::callAPI()` 封装,导致日志不统一。

#### 解决方案
将所有直接 `localAPI()` 调用替换为统一封装:

```php
// ❌ 修改前
$result = localAPI('GetClientsProducts', $data, $adminUsername);

// ✅ 修改后
$result = OrrisWhmcsHelper::callAPI(
    'GetClientsProducts',
    $data,
    $adminUsername,
    "Get client product info for service {$sid}"
);
```

#### 影响范围
- 文件:
  - `modules/servers/orrism/api/product.php`
  - `modules/servers/orrism/api/traffic.php`
- 修改函数:
  - `orris_get_client_products()` - 统一使用 API 封装
  - `orris_get_invoice()` - 统一使用 API 封装并改进错误处理
  - `orris_reset_traffic_month()` - GetOrders 和 GetProducts 调用统一封装

#### 好处
- 统一的日志记录
- 统一的错误处理
- 更好的调试能力
- 符合 WHMCS 最佳实践

---

### 3. 统一错误处理机制 (中优先级 - 一致性) ✅

#### 问题
错误响应格式不一致,有些返回字符串,有些返回数组。

#### 解决方案
在 `OrrisHelper` 类中添加统一错误处理方法:

```php
// 新增方法
class OrrisHelper {
    /**
     * Format error response for WHMCS module functions
     */
    public static function formatModuleError(string $message, string $code = ''): string

    /**
     * Format error response for API/AJAX returns
     */
    public static function formatApiError(string $message, string $code = 'ERROR', array $additionalData = []): array

    /**
     * Format success response for API/AJAX returns
     */
    public static function formatApiSuccess(string $message = 'Operation completed successfully', array $data = []): array

    /**
     * Validate and sanitize module parameters
     */
    public static function validateModuleParams(array $params, array $required = []): array
}
```

#### 使用示例
```php
// Module 函数错误返回
return OrrisHelper::formatModuleError('User not found', 'USER_NOT_FOUND');
// 输出: "Error [USER_NOT_FOUND]: User not found"

// API 错误返回
return OrrisHelper::formatApiError('Invalid parameters', 'INVALID_PARAMS', ['field' => 'serviceid']);
// 输出: ['result' => 'error', 'status' => 'fail', 'error' => 'Invalid parameters', ...]

// API 成功返回
return OrrisHelper::formatApiSuccess('Service created', ['service_id' => 123]);
// 输出: ['result' => 'success', 'status' => 'success', 'service_id' => 123, ...]
```

---

### 4. 完善参数验证和文档 (低优先级 - 可读性) ✅

#### 问题
缺少对 WHMCS 自动注入参数的文档说明,不利于代码维护。

#### 解决方案
为关键函数添加详细的 PHPDoc 注释:

```php
/**
 * Create account
 *
 * Automatically called by WHMCS when a new service is activated.
 * Creates a new user account in the ORRISM database.
 *
 * @param array $params Module parameters automatically injected by WHMCS including:
 *   - serviceid: int Service ID from WHMCS
 *   - pid: int Product ID
 *   - userid: int Client user ID
 *   - domain: string Service domain
 *   - username: string Service username
 *   - password: string Service password
 *   - serverhostname: string Server hostname from server configuration
 *   - serverip: string Server IP address
 *   - serverusername: string Server username
 *   - serverpassword: string Server password
 *   - serverport: int Server port
 *   - serversecure: bool Whether to use SSL
 *   - configoption[N]: mixed Product configuration options
 * @return string "success" or error message
 */
function CreateAccount(array $params)
```

#### 改进的函数
- `MetaData()` - 添加返回值详细说明
- `ConfigOptions()` - 添加配置选项结构说明
- `CreateAccount()` - 添加完整的参数列表
- `ServiceSingleSignOn()` - 说明自动注入的服务器参数
- `AdminSingleSignOn()` - 说明自动注入的管理员参数

#### 新增参数验证工具
```php
// 使用示例
$validation = OrrisHelper::validateModuleParams($params, ['serviceid', 'userid']);
if (!$validation['valid']) {
    return OrrisHelper::formatModuleError(
        'Missing required parameters: ' . implode(', ', $validation['missing'])
    );
}
```

---

## 📊 改进统计

| 改进项 | 文件数 | 修改处数 | 优先级 | 状态 |
|--------|--------|----------|--------|------|
| 敏感数据保护 | 1 | ~15处 | 高 | ✅ 完成 |
| 统一 API 封装 | 2 | ~5处 | 中 | ✅ 完成 |
| 统一错误处理 | 1 | 新增4个方法 | 中 | ✅ 完成 |
| 参数文档完善 | 1 | 5个函数 | 低 | ✅ 完成 |

---

## 🎯 符合的 WHMCS 最佳实践

### ✅ 1. 依赖注入模式
- 使用 WHMCS 自动注入的 `$params` 参数
- 不手动调用 `GetServers` API
- 直接使用 `$params['serverhostname']`, `$params['serverpassword']` 等

### ✅ 2. 敏感数据保护
- 所有 `logModuleCall()` 都使用 `$replaceVars` 隐藏敏感字段
- 隐藏字段包括: password, serverpassword, apikey, token

### ✅ 3. 统一日志记录
- 成功和失败都记录日志
- 包含详细的上下文信息
- 使用结构化的 JSON 格式

### ✅ 4. 统一 API 调用
- 使用 `OrrisWhmcsHelper::callAPI()` 封装
- 自动日志记录和错误处理
- 便于调试和维护

### ✅ 5. Single Sign-On 实现
- `ServiceSingleSignOn()` - 客户端 SSO
- `AdminSingleSignOn()` - 管理员 SSO
- 返回标准格式: `['success' => bool, 'redirectTo' => string, 'errorMsg' => string]`

### ✅ 6. 异常处理
- 所有关键函数都有 try-catch
- 错误信息清晰明确
- 返回格式统一

---

## 📝 未实现的可选功能

### Server Sync (不需要)
根据需求,项目不需要实现 `ListAccounts()` 功能,因此跳过此项。

如需未来添加,可参考:
```php
function orrism_MetaData() {
    return [
        // ... 现有配置
        'ListAccountsUniqueIdentifierDisplayName' => 'Service ID',
        'ListAccountsUniqueIdentifierField' => 'service_id',
        'ListAccountsProductField' => 'node_group',
    ];
}

function orrism_ListAccounts(array $params) {
    // 实现账户同步逻辑
}
```

---

## 🔧 使用新增的工具函数

### 1. 错误格式化
```php
// Module 函数返回
return OrrisHelper::formatModuleError('Database connection failed', 'DB_ERROR');

// API 响应
return OrrisHelper::formatApiError('Invalid service ID', 'INVALID_SID', ['sid' => $sid]);
return OrrisHelper::formatApiSuccess('Traffic reset successfully', ['service_id' => $sid]);
```

### 2. 参数验证
```php
$validation = OrrisHelper::validateModuleParams($params, ['serviceid', 'userid']);
if (!$validation['valid']) {
    $missing = implode(', ', $validation['missing']);
    return OrrisHelper::formatModuleError("Missing: {$missing}");
}
```

### 3. 日志记录
```php
OrrisHelper::log('error', 'Failed to create service', [
    'service_id' => $sid,
    'error' => $e->getMessage()
]);
```

---

## ✨ 总结

通过本次优化:

1. **安全性提升** - 所有敏感数据在日志中被自动隐藏
2. **可维护性提升** - 统一的 API 调用和错误处理
3. **可读性提升** - 完善的文档注释和参数说明
4. **符合标准** - 完全符合 WHMCS 官方最佳实践

所有改进都保持了向后兼容性,不影响现有功能。
