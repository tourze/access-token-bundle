<?php

namespace AccessTokenBundle\Tests\Service;

use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Repository\AccessTokenRepository;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;

class AccessTokenServiceTest extends TestCase
{
    private AccessTokenRepository $repository;
    private RequestStack $requestStack;
    private Request $request;
    private AccessTokenService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AccessTokenRepository::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->request = $this->createMock(Request::class);

        // 配置RequestStack以返回Request对象
        $this->requestStack->method('getCurrentRequest')
            ->willReturn($this->request);

        // 创建服务实例，设置默认过期时间为86400秒（1天）
        $this->service = new AccessTokenService(
            $this->repository,
            $this->requestStack,
            86400
        );
    }

    public function testCreateToken_shouldCreateAndSaveToken(): void
    {
        $user = $this->createMock(UserInterface::class);

        // 期望save方法被调用一次
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(AccessToken::class));

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);
    }

    public function testCreateToken_withCustomExpiry_shouldUseCustomExpiry(): void
    {
        $user = $this->createMock(UserInterface::class);
        $customExpiry = 7200; // 2小时

        // 期望save方法被调用一次
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(AccessToken::class));

        // 调用服务方法
        $createdToken = $this->service->createToken($user, $customExpiry);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);
    }

    public function testFindToken_shouldCallRepositoryMethod(): void
    {
        $token = new AccessToken();
        $tokenValue = 'test_token_value';

        // 设置仓库方法findOneByValue期望返回模拟的令牌
        $this->repository->expects($this->once())
            ->method('findOneByValue')
            ->with($tokenValue)
            ->willReturn($token);

        // 调用服务方法
        $result = $this->service->findToken($tokenValue);

        // 验证返回的是同一个令牌实例
        $this->assertSame($token, $result);
    }

    public function testFindTokensByUser_shouldCallRepositoryMethod(): void
    {
        $tokens = [new AccessToken(), new AccessToken()];
        $user = $this->createMock(UserInterface::class);

        // 设置仓库方法findValidTokensByUser期望返回模拟的令牌数组
        $this->repository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with($user)
            ->willReturn($tokens);

        // 调用服务方法
        $result = $this->service->findTokensByUser($user);

        // 验证返回的是同一个令牌数组
        $this->assertSame($tokens, $result);
    }

    public function testValidateToken_withValidToken_shouldReturnTrue(): void
    {
        $token = $this->createMock(AccessToken::class);

        // 配置令牌模拟对象
        $token->method('isValid')->willReturn(true);
        $token->method('isExpired')->willReturn(false);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertTrue($result);
    }

    public function testValidateToken_withInvalidToken_shouldReturnFalse(): void
    {
        $token = $this->createMock(AccessToken::class);

        // 配置令牌模拟对象
        $token->method('isValid')->willReturn(false);
        $token->method('isExpired')->willReturn(false);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertFalse($result);
    }

    public function testValidateToken_withExpiredToken_shouldReturnFalse(): void
    {
        $token = $this->createMock(AccessToken::class);

        // 配置令牌模拟对象
        $token->method('isValid')->willReturn(true);
        $token->method('isExpired')->willReturn(true);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertFalse($result);
    }

    public function testValidateAndExtendToken_withValidToken_shouldExtendAndReturnToken(): void
    {
        $tokenValue = 'valid_token';
        $clientIp = '192.168.1.1';
        $token = $this->createMock(AccessToken::class);

        // 配置Request模拟对象
        $this->request->method('getClientIp')
            ->willReturn($clientIp);

        // 配置仓库模拟对象
        $this->repository->method('findOneByValue')
            ->with($tokenValue)
            ->willReturn($token);

        // 配置令牌模拟对象
        $token->method('isValid')->willReturn(true);
        $token->method('isExpired')->willReturn(false);

        // 期望令牌的updateAccessInfo和extend方法被调用
        $token->expects($this->once())
            ->method('updateAccessInfo')
            ->with($clientIp)
            ->willReturnSelf();

        $token->expects($this->once())
            ->method('extend')
            ->with(3600) // 默认续期1小时
            ->willReturnSelf();

        // 期望仓库的save方法被调用
        $this->repository->expects($this->once())
            ->method('save')
            ->with($token);

        // 调用服务方法
        $result = $this->service->validateAndExtendToken($tokenValue);

        // 验证结果
        $this->assertSame($token, $result);
    }

    public function testValidateAndExtendToken_withInvalidToken_shouldReturnNull(): void
    {
        $tokenValue = 'invalid_token';

        // 配置仓库模拟对象
        $this->repository->method('findOneByValue')
            ->with($tokenValue)
            ->willReturn(null);

        // 调用服务方法
        $result = $this->service->validateAndExtendToken($tokenValue);

        // 验证结果
        $this->assertNull($result);
    }

    public function testValidateAndExtendToken_withExpiredToken_shouldReturnNull(): void
    {
        $tokenValue = 'expired_token';
        $token = $this->createMock(AccessToken::class);

        // 配置仓库模拟对象
        $this->repository->method('findOneByValue')
            ->with($tokenValue)
            ->willReturn($token);

        // 配置令牌模拟对象
        $token->method('isValid')->willReturn(true);
        $token->method('isExpired')->willReturn(true);

        // 调用服务方法
        $result = $this->service->validateAndExtendToken($tokenValue);

        // 验证结果
        $this->assertNull($result);
    }

    public function testRevokeToken_shouldSetTokenInvalidAndSave(): void
    {
        $token = $this->createMock(AccessToken::class);

        // 期望令牌的setValid方法被调用
        $token->expects($this->once())
            ->method('setValid')
            ->with(false)
            ->willReturnSelf();

        // 期望仓库的save方法被调用
        $this->repository->expects($this->once())
            ->method('save')
            ->with($token);

        // 调用服务方法
        $this->service->revokeToken($token);
    }

    public function testDeleteToken_shouldRemoveTokenFromRepository(): void
    {
        $token = $this->createMock(AccessToken::class);

        // 期望仓库的remove方法被调用
        $this->repository->expects($this->once())
            ->method('remove')
            ->with($token);

        // 调用服务方法
        $this->service->deleteToken($token);
    }

    public function testCleanupExpiredTokens_shouldCallRepositoryMethod(): void
    {
        // 期望仓库的removeExpiredTokens方法被调用，并返回删除的数量
        $this->repository->expects($this->once())
            ->method('removeExpiredTokens')
            ->willReturn(5);

        // 调用服务方法
        $result = $this->service->cleanupExpiredTokens();

        // 验证结果
        $this->assertEquals(5, $result);
    }

    public function testCreateToken_withPreventMultipleLoginEnabled_shouldRevokeExistingTokens(): void
    {
        // 设置环境变量启用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'true';

        $user = $this->createMock(UserInterface::class);
        $existingToken1 = $this->createMock(AccessToken::class);
        $existingToken2 = $this->createMock(AccessToken::class);
        $existingTokens = [$existingToken1, $existingToken2];

        // 配置仓库返回现有令牌
        $this->repository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with($user)
            ->willReturn($existingTokens);

        // 期望现有令牌被吊销
        $existingToken1->expects($this->once())
            ->method('setValid')
            ->with(false)
            ->willReturnSelf();

        $existingToken2->expects($this->once())
            ->method('setValid')
            ->with(false)
            ->willReturnSelf();

        // 期望save方法被调用3次（2次吊销 + 1次创建新令牌）
        $this->repository->expects($this->exactly(3))
            ->method('save');

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);

        // 清理环境变量
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN']);
    }

    public function testCreateToken_withPreventMultipleLoginDisabled_shouldNotRevokeExistingTokens(): void
    {
        // 设置环境变量禁用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createMock(UserInterface::class);

        // 期望不会查询现有令牌
        $this->repository->expects($this->never())
            ->method('findValidTokensByUser');

        // 期望save方法只被调用1次（创建新令牌）
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(AccessToken::class));

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);

        // 清理环境变量
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN']);
    }

    public function testCreateToken_withDefaultEnvironmentVariable_shouldPreventMultipleLogin(): void
    {
        // 不设置环境变量，应该使用默认值true
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN']);

        $user = $this->createMock(UserInterface::class);
        $existingToken = $this->createMock(AccessToken::class);
        $existingTokens = [$existingToken];

        // 配置仓库返回现有令牌
        $this->repository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with($user)
            ->willReturn($existingTokens);

        // 期望现有令牌被吊销
        $existingToken->expects($this->once())
            ->method('setValid')
            ->with(false)
            ->willReturnSelf();

        // 期望save方法被调用2次（1次吊销 + 1次创建新令牌）
        $this->repository->expects($this->exactly(2))
            ->method('save');

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);
    }

    public function testCreateToken_withNoExistingTokens_shouldNotCallRevoke(): void
    {
        // 设置环境变量启用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'true';

        $user = $this->createMock(UserInterface::class);

        // 配置仓库返回空数组（无现有令牌）
        $this->repository->expects($this->once())
            ->method('findValidTokensByUser')
            ->with($user)
            ->willReturn([]);

        // 期望save方法只被调用1次（创建新令牌）
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(AccessToken::class));

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);

        // 清理环境变量
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN']);
    }
}
