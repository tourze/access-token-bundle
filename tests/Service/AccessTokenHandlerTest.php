<?php

namespace AccessTokenBundle\Tests\Service;

use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Service\AccessTokenHandler;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandlerTest extends TestCase
{
    private AccessTokenService $accessTokenService;
    private AccessTokenHandler $handler;
    private array $originalEnv;
    
    protected function setUp(): void
    {
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->handler = new AccessTokenHandler($this->accessTokenService);
        
        // 保存原来的环境变量
        $this->originalEnv = $_ENV;
    }
    
    protected function tearDown(): void
    {
        // 恢复环境变量
        $_ENV = $this->originalEnv;
    }
    
    public function testGetUserBadgeFrom_withValidToken_shouldReturnUserBadge(): void
    {
        $token = 'valid_token_value';
        $accessToken = $this->createMock(AccessToken::class);
        $user = $this->createMock(UserInterface::class);
        
        // 配置用户模拟对象返回标识符
        $user->method('getUserIdentifier')
            ->willReturn('test_user');
        
        // 配置访问令牌模拟对象返回用户
        $accessToken->method('getUser')
            ->willReturn($user);
        
        // 配置访问令牌服务模拟对象
        $this->accessTokenService->method('validateAndExtendToken')
            ->with($token, 3600)
            ->willReturn($accessToken);
        
        // 调用处理器方法
        $result = $this->handler->getUserBadgeFrom($token);
        
        // 验证结果
        $this->assertInstanceOf(UserBadge::class, $result);
        $this->assertEquals('test_user', $result->getUserIdentifier());
    }
    
    public function testGetUserBadgeFrom_withInvalidToken_shouldThrowException(): void
    {
        $token = 'invalid_token_value';
        
        // 配置访问令牌服务模拟对象返回null，表示无效令牌
        $this->accessTokenService->method('validateAndExtendToken')
            ->with($token, 3600)
            ->willReturn(null);
        
        // 期望抛出BadCredentialsException异常
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('无效的访问令牌');
        
        // 调用处理器方法
        $this->handler->getUserBadgeFrom($token);
    }
    
    public function testGetUserBadgeFrom_withCustomRenewalTime_shouldUseEnvironmentVariable(): void
    {
        $token = 'valid_token_value';
        $customRenewalTime = 7200; // 2小时
        $accessToken = $this->createMock(AccessToken::class);
        $user = $this->createMock(UserInterface::class);
        
        // 设置环境变量
        $_ENV['ACCESS_TOKEN_RENEWAL_TIME'] = $customRenewalTime;
        
        // 配置用户模拟对象返回标识符
        $user->method('getUserIdentifier')
            ->willReturn('test_user');
        
        // 配置访问令牌模拟对象返回用户
        $accessToken->method('getUser')
            ->willReturn($user);
        
        // 配置访问令牌服务模拟对象，期望使用自定义续期时间
        $this->accessTokenService->expects($this->once())
            ->method('validateAndExtendToken')
            ->with($token, $customRenewalTime)
            ->willReturn($accessToken);
        
        // 调用处理器方法
        $result = $this->handler->getUserBadgeFrom($token);
        
        // 验证结果
        $this->assertInstanceOf(UserBadge::class, $result);
    }
} 