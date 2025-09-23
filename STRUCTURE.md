# ORRISM 项目架构说明

## 📁 项目目录结构

```
orrism/
├── modules/                           # WHMCS 模块目录
│   ├── addons/orrism_admin/          # 附加模块 - 系统管理
│   │   ├── orrism_admin.php         # 主配置文件 (2,000+ 行)
│   │   └── hooks.php                # 系统级钩子 (定时任务)
│   └── servers/orrism/              # 服务器模块 - 服务管理
│       ├── orrism.php               # 主模块文件 (500+ 行)
│       ├── hooks.php                # 服务级钩子 (生命周期)
│       ├── api/                     # API 接口目录
│       │   ├── checkmate/           # 订阅配置生成
│       │   │   ├── index.php        # 主入口
│       │   │   ├── generators.php   # 配置生成器
│       │   │   └── utils.php        # 工具函数
│       │   ├── database.php         # 数据库操作
│       │   ├── node.php            # 节点管理
│       │   ├── product.php         # 产品管理
│       │   ├── traffic.php         # 流量管理
│       │   └── user.php            # 用户管理
│       ├── includes/               # 核心库文件
│       │   ├── database_manager.php # 数据库管理器
│       │   ├── whmcs_database.php   # WHMCS 数据库
│       │   ├── whmcs_utils.php      # WHMCS 工具
│       │   └── hook_logic.php       # 钩子逻辑
│       ├── lib/                    # 工具库
│       │   ├── database.php        # 数据库抽象
│       │   ├── uuid.php            # UUID 生成
│       │   └── yaml.php            # YAML 处理
│       ├── migration/              # 数据库迁移
│       │   └── legacy_data_migration.php
│       └── helper.php              # 辅助函数
├── DEPLOYMENT_GUIDE.md             # 部署指南
├── INSTALLATION.md                 # 安装说明  
├── README.md                       # 项目说明
└── STRUCTURE.md                    # 架构说明 (本文件)
```

## 🏗️ 双模块架构

### Addon Module (`modules/addons/orrism_admin/`)
**目的**: 系统级配置和管理
- ✅ **独立管理入口**: 在 WHMCS `Addons` 菜单显示
- ✅ **数据库管理**: 安装、配置 ORRISM 数据库
- ✅ **节点管理**: 添加、编辑、删除节点
- ✅ **用户管理**: 批量用户操作
- ✅ **系统监控**: Redis、数据库连接状态
- ✅ **定时任务**: 流量检查、账单处理

### Server Module (`modules/servers/orrism/`)
**目的**: 服务生命周期管理
- ✅ **服务管理**: Create, Suspend, Unsuspend, Terminate
- ✅ **用户操作**: 重置流量、重置 UUID、查看使用情况
- ✅ **API 接口**: 与 ORRISM 服务通信
- ✅ **配置生成**: Checkmate 订阅链接
- ✅ **数据同步**: 用户数据与 SS 数据库同步

## 🔧 核心组件说明

### API 接口层
- **checkmate/**: 订阅配置生成器，支持多种客户端
- **database.php**: Redis/MySQL 数据库操作抽象
- **user.php**: 用户 CRUD 操作
- **traffic.php**: 流量统计和重置
- **node.php**: 节点信息管理
- **product.php**: 产品配置管理

### 数据管理层
- **DatabaseManager**: 统一的数据库连接管理
- **WhmcsDatabase**: WHMCS 数据库操作封装
- **OrrisHelper**: 通用工具函数类

### 安全机制
- ✅ **函数名前缀**: 所有公共函数使用 `orrism_` 前缀
- ✅ **CSRF 保护**: 集成 WHMCS 安全机制
- ✅ **权限控制**: 通过 WHMCS 管理员权限
- ✅ **输入验证**: 所有用户输入都经过验证

## 📊 性能优化

- **Redis 缓存**: 高频数据使用 Redis 缓存
- **数据库连接池**: 复用数据库连接
- **延迟加载**: 按需加载模块组件
- **批量操作**: 减少数据库查询次数

## 🔄 部署流程

1. **文件部署**: 复制两个模块到 WHMCS 对应目录
2. **激活模块**: 先激活 Addon Module，再配置 Server Module
3. **数据库初始化**: 通过 Addon Module 安装数据库
4. **配置验证**: 检查连接状态和基础功能

## 📈 扩展性

- **模块化设计**: 每个功能独立，便于维护
- **标准化接口**: 遵循 WHMCS 开发规范
- **配置驱动**: 通过配置调整行为
- **插件机制**: 支持第三方扩展

## 🚀 优势总结

1. **架构清晰**: 职责分离，Server 管服务，Addon 管系统
2. **用户友好**: 统一的管理界面，专业的操作体验
3. **安全可靠**: 集成 WHMCS 安全机制，防止函数冲突
4. **性能优异**: 缓存机制和优化的数据库操作
5. **易于维护**: 模块化代码，标准化开发流程
