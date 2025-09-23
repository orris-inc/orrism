# ORRISM 模块部署指南

## 新的模块架构

ORRISM 现在分为两个独立的 WHMCS 模块：

### 1. Server Module (服务器模块)
**位置：** `modules/servers/orrism/`
**功能：** 专注于服务生命周期管理
- 服务创建、暂停、恢复、终止
- 服务配置和用户密码管理
- 基础的流量重置和查看功能

### 2. Addon Module (附加模块)  
**位置：** `modules/addons/orrism_admin/`
**功能：** 系统级配置和管理
- 数据库安装和配置
- 节点管理
- 用户管理
- 流量监控和统计
- 系统设置和定时任务

## 部署步骤

### 第一步：部署模块文件

1. **复制 Server Module**
   ```bash
   cp -r modules/servers/orrism/ /path/to/whmcs/modules/servers/
   ```

2. **复制 Addon Module**
   ```bash
   cp -r modules/addons/orrism_admin/ /path/to/whmcs/modules/addons/
   ```

### 第二步：激活模块

1. **激活 Addon Module**
   - 登录 WHMCS 管理员面板
   - 进入 `System Settings > Addon Modules`
   - 找到 "ORRISM Administration" 
   - 点击 "Activate"
   - 配置数据库连接信息

2. **配置 Server Module**
   - 进入 `System Settings > Products/Services > Servers`
   - 添加新服务器，选择 "ORRISM Manager" 类型

### 第三步：初始化系统

1. **访问 ORRISM 管理面板**
   - 在 WHMCS 管理员面板中，进入 `Addons > ORRISM Administration`

2. **安装数据库**
   - 点击 "Database Setup" 标签
   - 点击 "Install Database Tables" 按钮

3. **配置节点和用户**
   - 使用各个管理标签页配置系统

## 主要改进

### ✅ 架构优化
- **职责分离**：Server Module 只处理服务，Addon Module 处理配置
- **独立入口**：管理功能有专门的菜单入口
- **更好的用户体验**：统一的管理界面

### ✅ 安全增强
- **移除直接访问**：不再有可直接访问的 admin/setup.php
- **WHMCS 集成**：所有管理功能都通过 WHMCS 权限控制
- **CSRF 保护**：使用 WHMCS 的内置安全机制

### ✅ 功能增强
- **模块化设计**：每个功能模块独立，便于维护
- **统一日志**：所有操作都有详细的日志记录
- **状态监控**：实时显示系统连接状态

## 注意事项

1. **数据库配置**：首次安装时需要在 Addon Module 中配置数据库连接
2. **权限设置**：确保 WHMCS 管理员有 Addon Module 的访问权限
3. **文件权限**：确保 WHMCS 可以读取模块目录中的所有文件
4. **备份数据**：升级前请备份现有的数据库和配置

## 故障排除

### 模块不显示
- 检查文件权限和路径
- 查看 WHMCS 错误日志
- 确认模块文件结构正确

### 数据库连接失败
- 检查 Addon Module 中的数据库配置
- 确认数据库服务器可访问
- 检查用户权限

### 服务创建失败
- 确认 Server Module 已正确配置
- 检查产品配置中的模块设置
- 查看模块调用日志
