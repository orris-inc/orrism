# ORRISM Administration Module - 故障排除指南

## 问题诊断

如果ORRISM Administration模块显示空白页面，请按以下步骤进行诊断：

### 1. 启用调试模式
在URL中添加 `&debug=1` 参数：
```
/admin/addonmodules.php?module=orrism_admin&debug=1
```

### 2. 检查依赖文件
确保以下文件存在并可读：
- `/modules/servers/orrism/includes/database_manager.php`
- `/modules/servers/orrism/includes/whmcs_database.php`
- `/modules/servers/orrism/helper.php`

### 3. 检查WHMCS错误日志
查看WHMCS错误日志文件，通常位于：
- `{whmcs_root}/storage/logs/`
- 或者服务器的PHP错误日志

### 4. 验证模块配置
确认模块在 WHMCS Admin > 插件管理 中已正确激活。

### 5. 测试基本功能
访问以下URL测试不同的功能页面：
- Dashboard: `?module=orrism_admin&action=dashboard`
- Database Setup: `?module=orrism_admin&action=database`
- Node Management: `?module=orrism_admin&action=nodes`

## 常见问题解决方案

### 空白页面
- **原因**: 依赖文件缺失或PHP致命错误
- **解决**: 启用调试模式查看详细错误信息

### 依赖加载失败
- **原因**: Server模块路径不正确
- **解决**: 检查 `/modules/servers/orrism/` 目录是否存在

### 数据库连接问题
- **原因**: ORRISM 数据库配置不正确
- **解决**: 在模块配置中更新数据库连接参数

## 恢复步骤

如果模块完全无法使用，可以尝试以下恢复步骤：

1. **重新激活模块**
   - 在WHMCS Admin中停用模块
   - 重新激活模块

2. **清除缓存**
   - 清除WHMCS系统缓存
   - 重启Web服务器

3. **检查文件权限**
   - 确保模块文件可读
   - 检查目录权限设置

## 技术支持

如果问题仍然存在，请收集以下信息：
- 调试模式下的完整错误信息
- WHMCS版本信息
- PHP版本信息
- 服务器错误日志相关条目
