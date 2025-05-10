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
}
