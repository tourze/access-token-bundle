<?php

namespace Tourze\AccessTokenBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Service\AccessTokenHandler;
use Tourze\AccessTokenBundle\Service\AccessTokenService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccessTokenHandler::class)]
#[RunTestsInSeparateProcesses]
final class AccessTokenHandlerTest extends AbstractIntegrationTestCase
{
    private AccessTokenService&MockObject $accessTokenService;

    private AccessTokenHandler $handler;

    /** @var array<string, mixed> */
    private array $originalEnv;

    protected function onSetUp(): void
    {
        /* 此处必须模拟 AccessTokenService 具体类，因为测试需要验证 AccessTokenHandler
         * 与实际服务类的交互行为。使用具体类模拟是合理的，原因如下：
         * 1) AccessTokenService 是业务逻辑核心服务，测试需要验证其方法调用
         * 2) 该服务没有对应的接口，必须使用具体类进行模拟
         * 3) 这避免了创建不必要的接口抽象，保持代码简洁性 */
        $this->accessTokenService = $this->createMock(AccessTokenService::class);

        // 将mock服务设置到容器中
        self::getContainer()->set(AccessTokenService::class, $this->accessTokenService);

        // 从容器获取handler实例，而不是直接实例化
        $this->handler = self::getService(AccessTokenHandler::class);

        // 保存原来的环境变量
        $this->originalEnv = $_ENV;
    }

    protected function onTearDown(): void
    {
        // 恢复环境变量
        $_ENV = $this->originalEnv;
    }

    public function testGetUserBadgeFromWithValidTokenShouldReturnUserBadge(): void
    {
        $token = 'valid_token_value';
        /* 此处必须模拟 AccessToken 实体类，因为测试需要验证令牌验证逻辑。
         * 使用实体类模拟是合理的，原因如下：
         * 1) AccessToken 是 Doctrine 实体，包含业务方法如 getUser()
         * 2) 实体类通常不需要接口抽象，直接模拟可避免过度设计
         * 3) 这用于测试用户身份验证流程，模拟实体行为是必需的 */
        $accessToken = $this->createMock(AccessToken::class);
        $user = $this->createMock(UserInterface::class);

        // 配置用户模拟对象返回标识符
        $user->method('getUserIdentifier')
            ->willReturn('test_user')
        ;

        // 配置访问令牌模拟对象返回用户
        $accessToken->method('getUser')
            ->willReturn($user)
        ;

        // 配置访问令牌服务模拟对象
        $this->accessTokenService->method('validateAndExtendToken')
            ->with($token, 3600)
            ->willReturn($accessToken)
        ;

        // 调用处理器方法
        $result = $this->handler->getUserBadgeFrom($token);

        // 验证结果
        $this->assertInstanceOf(UserBadge::class, $result);
        $this->assertEquals('test_user', $result->getUserIdentifier());
    }

    public function testGetUserBadgeFromWithInvalidTokenShouldThrowException(): void
    {
        $token = 'invalid_token_value';

        // 配置访问令牌服务模拟对象返回null，表示无效令牌
        $this->accessTokenService->method('validateAndExtendToken')
            ->with($token, 3600)
            ->willReturn(null)
        ;

        // 期望抛出BadCredentialsException异常
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('无效的访问令牌');

        // 调用处理器方法
        $this->handler->getUserBadgeFrom($token);
    }

    public function testGetUserBadgeFromWithCustomRenewalTimeShouldUseEnvironmentVariable(): void
    {
        $token = 'valid_token_value';
        $customRenewalTime = 7200; // 2小时
        /* 此处必须模拟 AccessToken 实体类，因为测试需要验证自定义续期时间功能。
         * 使用实体类模拟是合理的，原因如下：
         * 1) 需要验证令牌续期逻辑中实体方法的正确调用
         * 2) AccessToken 实体包含用户信息和令牌状态，直接模拟避免复杂依赖
         * 3) 这用于测试环境变量配置对令牌续期的影响，模拟实体是必要的 */
        $accessToken = $this->createMock(AccessToken::class);
        $user = $this->createMock(UserInterface::class);

        // 设置环境变量
        $_ENV['ACCESS_TOKEN_RENEWAL_TIME'] = $customRenewalTime;

        // 配置用户模拟对象返回标识符
        $user->method('getUserIdentifier')
            ->willReturn('test_user')
        ;

        // 配置访问令牌模拟对象返回用户
        $accessToken->method('getUser')
            ->willReturn($user)
        ;

        // 配置访问令牌服务模拟对象，期望使用自定义续期时间
        $this->accessTokenService->expects($this->once())
            ->method('validateAndExtendToken')
            ->with($token, $customRenewalTime)
            ->willReturn($accessToken)
        ;

        // 调用处理器方法
        $result = $this->handler->getUserBadgeFrom($token);

        // 验证结果
        $this->assertInstanceOf(UserBadge::class, $result);
    }
}
