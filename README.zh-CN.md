# access-token-bundle

Token管理

## 安装

```bash
composer require tourze/access-token-bundle
```

## 使用方法

### 基本用法

1. 创建访问令牌

```php
use AccessTokenBundle\Service\AccessTokenService;

// 注入服务
$accessTokenService = $container->get(AccessTokenService::class);

// 为用户创建令牌
$token = $accessTokenService->createToken($user, 3600, 'iOS App');
echo $token->getToken(); // 输出令牌值
```

2. 验证访问令牌

```php
// 验证并续期令牌
$validToken = $accessTokenService->validateAndExtendToken($tokenValue, 3600);
if ($validToken) {
    // 令牌有效
    $user = $validToken->getUser();
}
```

3. 吊销令牌

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

## 配置

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
                token_handler: AccessTokenBundle\Service\AccessTokenHandler
```

### 数据库配置

确保运行数据库迁移来创建访问令牌表：

```bash
php bin/console doctrine:migrations:migrate
```

## 示例

### 完整的登录流程示例

```php
use AccessTokenBundle\Service\AccessTokenService;
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

## 参考文档

- [Symfony Security Component](https://symfony.com/doc/current/security.html)
- [Doctrine ORM](https://www.doctrine-project.org/projects/orm.html)
