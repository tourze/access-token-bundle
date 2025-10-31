# Access Token Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/access-token-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/access-token-bundle)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/tourze/access-token-bundle)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/owner/repo/ci.yml)](https://github.com/owner/repo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/owner/repo)](https://codecov.io/github/owner/repo)

一个用于管理访问令牌的 Symfony Bundle，具有内置的安全特性、
令牌生命周期管理和 API 认证支持。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [快速开始](#快速开始)
- [配置](#配置)
- [API 使用](#api-使用)
- [配置选项](#配置选项)
- [高级示例](#高级示例)
- [API 参考](#api-参考)
- [贡献](#贡献)
- [测试](#测试)
- [许可证](#许可证)
- [参考](#参考)

## 功能特性

- 🔐 **安全令牌管理** - 为 API 认证生成和管理安全访问令牌
- 🚫 **防止多点登录** - 可选功能，防止用户同时拥有多个活动会话
- 🔄 **令牌生命周期管理** - 自动令牌过期和续期
- 🧹 **自动清理** - 内置命令清理过期令牌
- 🌐 **API 就绪** - 预置的令牌管理 API 端点
- 🛡️ **Symfony 安全集成** - 与 Symfony 安全组件无缝集成
- 📱 **设备跟踪** - 跟踪每个令牌的设备信息
- ⚡ **高性能** - 使用 Doctrine ORM 优化的数据库查询

## 系统要求

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- Doctrine ORM 3.0 或更高版本

## 安装

```bash
composer require tourze/access-token-bundle
```

## 快速开始

只需几分钟即可开始使用 Access Token Bundle：

### 1. 安装和设置

```bash
# 安装 bundle
composer require tourze/access-token-bundle

# 运行数据库迁移创建访问令牌表
php bin/console doctrine:migrations:migrate
```

### 2. 配置安全（可选）

在 `config/packages/security.yaml` 中添加访问令牌认证：

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### 3. 创建你的第一个令牌

```bash
# 通过命令行为用户创建访问令牌
php bin/console app:create-access-token your_username --expires=3600

# 或在代码中使用服务
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;

$token = $accessTokenService->createToken($user, 3600, 'My Device');
echo $token->getToken(); // 使用此令牌进行 API 调用
```

### 4. 测试你的令牌

```bash
# 使用 curl 测试令牌
curl -H "Authorization: Bearer your_token_here" http://localhost/api/test
```

就是这样！你现在拥有了一个可用的 Symfony 应用访问令牌认证系统。

## 配置

### 启用 Bundle

如果你使用 Symfony Flex，bundle 会自动启用。否则，将其添加到你的 `config/bundles.php`：

```php
return [
    // ...
    Tourze\AccessTokenBundle\Tourze\AccessTokenBundle::class => ['all' => true],
];
```

### 配置安全设置

在安全配置中添加访问令牌认证：

```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### 运行数据库迁移

创建访问令牌表：

```bash
php bin/console doctrine:migrations:migrate
```

## API 使用

### 基本用法

1. **创建访问令牌**

```php
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;

// 注入服务
$accessTokenService = $container->get(AccessTokenService::class);

// 为用户创建令牌（1小时后过期，设备：iOS App）
$token = $accessTokenService->createToken($user, 3600, 'iOS App');
echo $token->getToken(); // 输出令牌值
```

2. **验证访问令牌**

```php
// 验证并续期令牌
$validToken = $accessTokenService->validateAndExtendToken($tokenValue, 3600);
if ($validToken) {
    // 令牌有效
    $user = $validToken->getUser();
}
```

3. **吊销令牌**

```php
$accessTokenService->revokeToken($token);
```

### 命令行工具

1. 创建访问令牌

```bash
php bin/console app:create-access-token username --expires=3600 --device="Mobile App"
```

2. 清理过期令牌

```bash
php bin/console app:cleanup-access-tokens
php bin/console app:cleanup-access-tokens --dry-run  # 仅查看，不删除
```

### API接口

Bundle提供了以下API接口：

- `GET /api/user` - 获取当前用户信息
- `GET /api/tokens` - 获取当前用户的所有令牌
- `POST /api/token/revoke/{id}` - 吊销指定令牌
- `GET /api/test` - 测试API访问

## 配置选项

### 环境变量

在 `.env` 文件中配置以下环境变量：

```env
# 访问令牌续期时间（秒），默认3600秒（1小时）
ACCESS_TOKEN_RENEWAL_TIME=3600

# 是否防止多点登录，默认true（防止多点登录）
# true: 创建新令牌时会自动吊销用户的所有现有令牌
# false: 允许用户同时拥有多个有效令牌
ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=true
```

### 安全配置

在 `config/packages/security.yaml` 中配置访问令牌认证：

```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            access_token:
                token_handler: Tourze\AccessTokenBundle\Service\AccessTokenHandler
```

### 数据库配置

确保运行数据库迁移来创建访问令牌表：

```bash
php bin/console doctrine:migrations:migrate
```

## 高级示例

### 完整的登录流程示例

```php
use Tourze\Tourze\AccessTokenBundle\Service\AccessTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthController extends AbstractController
{
    public function login(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        AccessTokenService $accessTokenService
    ): JsonResponse {
        $username = $request->request->get('username');
        $password = $request->request->get('password');

        // 验证用户凭据
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => '用户名或密码错误'], 401);
        }

        // 创建访问令牌
        $deviceInfo = $request->headers->get('User-Agent');
        $token = $accessTokenService->createToken($user, 86400, $deviceInfo);

        return $this->json([
            'access_token' => $token->getToken(),
            'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
            ]
        ]);
    }
}
```

### 使用访问令牌调用API

```bash
# 使用Bearer Token认证
curl -H "Authorization: Bearer your_access_token_here" \
     http://localhost/api/user
```

### 防止多点登录示例

当 `ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=true` 时：

```php
// 用户第一次登录
$token1 = $accessTokenService->createToken($user, 3600, 'Web Browser');

// 用户在另一设备登录，之前的令牌会被自动吊销
$token2 = $accessTokenService->createToken($user, 3600, 'Mobile App');

// 此时 $token1 已失效，只有 $token2 有效
```

当 `ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN=false` 时：

```php
// 用户可以同时拥有多个有效令牌
$token1 = $accessTokenService->createToken($user, 3600, 'Web Browser');
$token2 = $accessTokenService->createToken($user, 3600, 'Mobile App');

// $token1 和 $token2 都保持有效
```

## API 参考

### 令牌仓储方法

```php
use Tourze\Tourze\AccessTokenBundle\Repository\AccessTokenRepository;

// 查找用户的令牌
$tokens = $accessTokenRepository->findByUser($user);

// 查找活跃令牌
$activeTokens = $accessTokenRepository->findActiveTokensByUser($user);

// 统计过期令牌
$expiredCount = $accessTokenRepository->countExpired();

// 删除过期令牌
$deletedCount = $accessTokenRepository->deleteExpired();
```

### 自定义令牌验证

```php
use Tourze\Tourze\AccessTokenBundle\Entity\AccessToken;

// 自定义验证逻辑
$token = $accessTokenService->findToken($tokenValue);
if ($token && !$token->isExpired()) {
    // 额外的自定义检查
    if ($token->getDeviceInfo() === $expectedDevice) {
        // 令牌对此设备有效
    }
}
```

## 贡献

欢迎贡献！请随时提交 Pull Request。

1. Fork 仓库
2. 创建你的功能分支 (`git checkout -b feature/amazing-feature`)
3. 提交你的更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 开启一个 Pull Request

## 测试

运行测试套件：

```bash
./vendor/bin/phpunit packages/access-token-bundle/tests
```

运行 PHPStan 分析：

```bash
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/access-token-bundle
```

## 许可证

MIT 许可证 (MIT)。请查看 [许可证文件](LICENSE) 了解更多信息。

## 参考

- [Symfony Security Component](https://symfony.com/doc/current/security.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
- [Symfony Access Token Authenticator](https://symfony.com/doc/current/security/access_token.html)
