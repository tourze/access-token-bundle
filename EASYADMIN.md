# Access Token Bundle - EasyAdmin 管理界面

## 概述

为 access-token-bundle 提供完整的 EasyAdmin 后台管理界面，用于管理系统中的访问令牌。

## 功能特性

### AccessToken 管理

- ✅ **令牌列表查看**: 支持分页、搜索和过滤
- ✅ **令牌详情**: 查看完整的令牌信息
- ✅ **令牌操作**: 
  - 撤销有效令牌
  - 续期令牌（延长24小时）
  - 激活已撤销的令牌
- ✅ **状态显示**: 
  - 过期状态指示（红色🔴/绿色🟢）
  - 访问状态跟踪
  - 令牌有效性标识

### 安全特性

- 🔐 **令牌脱敏**: 前端只显示令牌的前8位和后4位
- 🔍 **多维度过滤**: 支持按用户、状态、设备、IP等过滤
- 📊 **状态监控**: 实时显示令牌过期状态和最后访问时间

## 控制器说明

### AccessTokenCrudController

位置：`src/Controller/Admin/AccessTokenCrudController.php`

**主要功能：**
- 继承自 `AbstractCrudController`
- 提供完整的 CRUD 操作
- 路由路径：`/access-token/token`
- 路由名称：`access_token_token`

**字段配置：**
- `id`: 主键，仅查看模式显示
- `token`: 令牌值，脱敏显示
- `user`: 关联用户，显示用户名
- `valid`: 有效状态
- `expired`: 虚拟字段，显示是否过期
- `deviceInfo`: 设备信息
- `lastIp`: 最后访问IP
- `createTime`: 创建时间
- `expireTime`: 过期时间，带状态颜色指示
- `lastAccessTime`: 最后访问时间

**自定义操作：**
1. **撤销令牌** (`revokeToken`): 将有效令牌标记为无效
2. **续期令牌** (`extendToken`): 延长令牌有效期24小时
3. **激活令牌** (`activateToken`): 重新激活已撤销的令牌

**过滤器：**
- 文本过滤：令牌、设备信息、IP
- 实体过滤：用户
- 布尔过滤：有效状态
- 时间过滤：创建时间、过期时间、访问时间

## 菜单配置

### AdminMenu 服务

位置：`src/Service/AdminMenu.php`

在后台管理系统中创建 **"安全管理"** 顶级菜单，包含：
- 🔑 **访问令牌**: 链接到令牌管理页面

## 配置说明

### 服务注册

在 `services.yaml` 中已自动注册：

```yaml
# 注册 AdminMenu 服务
Tourze\AccessTokenBundle\Service\AdminMenu:
  tags:
    - { name: 'tourze_easy_admin_menu.provider' }
```

### 路由配置

使用 `#[AdminCrud]` 注解自动配置路由：

```php
#[AdminCrud(routePath: '/access-token/token', routeName: 'access_token_token')]
```

## 使用说明

### 访问管理界面

1. 登录到 EasyAdmin 后台
2. 在左侧菜单找到 **"安全管理"**
3. 点击 **"访问令牌"** 进入管理界面

### 令牌管理操作

#### 查看令牌列表
- 显示所有令牌的基本信息
- 支持按多个条件搜索和过滤
- 过期令牌用红色🔴标识

#### 令牌详情查看
- 点击列表中的 "详情" 按钮
- 查看完整的令牌信息，包括设备信息、访问历史等

#### 撤销令牌
- 对有效且未过期的令牌显示 "撤销令牌" 按钮
- 点击后令牌立即失效，无法继续使用

#### 续期令牌
- 对有效令牌显示 "续期令牌" 按钮
- 点击后令牌有效期延长24小时
- 过期令牌也可以续期

#### 激活令牌
- 对已撤销的令牌显示 "激活令牌" 按钮
- 点击后令牌重新变为有效状态

### 搜索和过滤

#### 快速搜索
支持以下字段的快速搜索：
- ID
- 令牌值
- 设备信息
- 最后访问IP

#### 高级过滤
- **用户过滤**: 选择特定用户的令牌
- **状态过滤**: 筛选有效/无效令牌
- **时间过滤**: 按创建时间、过期时间、访问时间筛选
- **文本过滤**: 按设备信息、IP地址等文本字段筛选

## 安全考虑

1. **令牌脱敏**: 前端只显示部分令牌内容，防止完整令牌泄露
2. **操作确认**: 危险操作需要管理员确认
3. **访问控制**: 需要适当的管理员权限才能访问
4. **审计跟踪**: 所有操作都会记录到系统日志

## 测试

提供了完整的单元测试：
- `tests/Controller/Admin/AccessTokenCrudControllerTest.php`
- `tests/Service/AdminMenuTest.php`

运行测试：
```bash
vendor/bin/phpunit packages/access-token-bundle/tests/
```

## 扩展说明

如需扩展功能，可以：
1. 在 `AccessTokenCrudController` 中添加新的自定义操作
2. 修改字段配置以显示更多信息
3. 添加新的过滤器或搜索条件
4. 自定义模板以实现特殊的UI需求