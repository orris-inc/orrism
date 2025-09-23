# ORRISM - WHMCS 管理模块

专业的 WHMCS 双模块架构，提供完整的 ORRISM 服务管理解决方案。

## 🏗️ 模块架构

### Server Module (`modules/servers/orrism/`)
负责服务生命周期管理：
- ✅ 服务创建、暂停、恢复、终止
- ✅ 用户密码管理和重置
- ✅ 基础流量操作（重置、查看）
- ✅ UUID 管理

### Addon Module (`modules/addons/orrism_admin/`)
负责系统级配置和管理：
- ✅ 数据库安装和配置
- ✅ 节点管理
- ✅ 用户管理
- ✅ 流量监控和统计
- ✅ 系统设置和定时任务

## 🚀 安装部署

### 1. 部署模块文件
```bash
# 复制到 WHMCS 目录
cp -r modules/servers/orrism/ /path/to/whmcs/modules/servers/
cp -r modules/addons/orrism_admin/ /path/to/whmcs/modules/addons/
```

### 2. 激活模块
1. **激活 Addon Module**
   - WHMCS 管理员 → `System Settings` → `Addon Modules`
   - 找到 "ORRISM Administration" → 点击 "Activate"

2. **配置 Server Module**
   - `System Settings` → `Products/Services` → `Servers`
   - 添加服务器，选择 "ORRISM Manager"

### 3. 初始化系统
- 访问 `Addons` → `ORRISM Administration`
- 完成数据库设置和系统配置

## 📁 项目结构

```
orrism/
├── modules/
│   ├── addons/orrism_admin/          # 附加模块
│   │   ├── orrism_admin.php         # 主配置文件
│   │   └── hooks.php                # 系统级钩子
│   └── servers/orrism/              # 服务器模块
│       ├── orrism.php               # 主模块文件
│       ├── hooks.php                # 服务级钩子
│       ├── includes/                # 核心库文件
│       ├── lib/                     # 工具库
│       └── migration/               # 数据库迁移
├── DEPLOYMENT_GUIDE.md              # 详细部署指南
├── INSTALLATION.md                  # 安装说明
└── README.md                        # 项目说明
```

## ✨ 主要特性

- **🔄 双模块架构**：职责分离，维护简单
- **🛡️ 安全增强**：集成 WHMCS 权限系统
- **📊 统一管理**：专门的管理界面
- **⚡ 高性能**：优化的数据库操作
- **🔧 易扩展**：模块化设计

## 📖 使用说明

### 管理员操作
- 系统配置：`Addons` → `ORRISM Administration`
- 服务管理：在产品服务页面中管理具体服务

### 开发者说明
- Server Module：专注服务 CRUD 操作
- Addon Module：处理系统配置和定时任务
- 所有配置文件使用标准 WHMCS 约定

## 🛠️ 技术栈

- **PHP 7.4+**
- **WHMCS 8.0+**
- **MySQL/MariaDB**
- **Redis** (可选，用于缓存)

## 📝 版本信息

- **Version**: 2.0
- **Author**: ORRISM Development Team
- **License**: MIT

## 🔗 相关文档

- [部署指南](DEPLOYMENT_GUIDE.md) - 详细的部署和配置说明
- [安装指南](INSTALLATION.md) - 完整的安装步骤
- [模块开发](https://developers.whmcs.com/) - WHMCS 开发文档
