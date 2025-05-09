<?php

namespace AccessTokenBundle\Tests\Command;

use AccessTokenBundle\Command\CreateAccessTokenCommand;
use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyEasyAdminDemo\Repository\UserRepository;

class CreateAccessTokenCommandTest extends TestCase
{
    private UserRepository $userRepository;
    private AccessTokenService $accessTokenService;
    private CreateAccessTokenCommand $command;
    private CommandTester $commandTester;
    
    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->command = new CreateAccessTokenCommand(
            $this->userRepository,
            $this->accessTokenService
        );
        
        $application = new Application();
        $application->add($this->command);
        
        $this->commandTester = new CommandTester($this->command);
    }
    
    public function testExecute_withValidUsername_shouldCreateToken(): void
    {
        $username = 'test_user';
        $user = $this->createMock(UserInterface::class);
        $token = $this->createConfiguredMock(AccessToken::class, [
            'getToken' => 'generated_token_value',
            'getExpiresAt' => new \DateTimeImmutable('2023-01-01 12:00:00')
        ]);
        
        // 配置仓库模拟对象返回用户
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username])
            ->willReturn($user);
        
        // 配置服务模拟对象创建令牌
        $this->accessTokenService->expects($this->once())
            ->method('createToken')
            ->with($user, 86400, null)
            ->willReturn($token);
        
        // 执行命令
        $exitCode = $this->commandTester->execute([
            'username' => $username
        ]);
        
        // 验证退出代码
        $this->assertEquals(0, $exitCode);
        
        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_user" 生成了新的访问令牌', $output);
        $this->assertStringContainsString('令牌值: generated_token_value', $output);
        $this->assertStringContainsString('过期时间: 2023-01-01 12:00:00', $output);
        $this->assertStringContainsString('使用方式: Authorization: Bearer generated_token_value', $output);
    }
    
    public function testExecute_withInvalidUsername_shouldReturnFailure(): void
    {
        $username = 'invalid_user';
        
        // 配置仓库模拟对象返回null，表示用户不存在
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username])
            ->willReturn(null);
        
        // 配置服务模拟对象，期望不被调用
        $this->accessTokenService->expects($this->never())
            ->method('createToken');
        
        // 执行命令
        $exitCode = $this->commandTester->execute([
            'username' => $username
        ]);
        
        // 验证退出代码
        $this->assertEquals(1, $exitCode);
        
        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('用户 "invalid_user" 不存在', $output);
    }
    
    public function testExecute_withCustomExpiresOption_shouldCreateTokenWithCustomExpires(): void
    {
        $username = 'test_user';
        $customExpires = 3600; // 1小时
        $user = $this->createMock(UserInterface::class);
        $token = $this->createConfiguredMock(AccessToken::class, [
            'getToken' => 'generated_token_value',
            'getExpiresAt' => new \DateTimeImmutable('2023-01-01 10:00:00')
        ]);
        
        // 配置仓库模拟对象返回用户
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username])
            ->willReturn($user);
        
        // 配置服务模拟对象创建令牌，期望使用自定义过期时间
        $this->accessTokenService->expects($this->once())
            ->method('createToken')
            ->with($user, $customExpires, null)
            ->willReturn($token);
        
        // 执行命令，带上自定义过期时间选项
        $exitCode = $this->commandTester->execute([
            'username' => $username,
            '--expires' => $customExpires
        ]);
        
        // 验证退出代码
        $this->assertEquals(0, $exitCode);
        
        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_user" 生成了新的访问令牌', $output);
    }
    
    public function testExecute_withDeviceOption_shouldCreateTokenWithDeviceInfo(): void
    {
        $username = 'test_user';
        $deviceInfo = 'Test Device';
        $user = $this->createMock(UserInterface::class);
        $token = $this->createConfiguredMock(AccessToken::class, [
            'getToken' => 'generated_token_value',
            'getExpiresAt' => new \DateTimeImmutable('2023-01-01 10:00:00')
        ]);
        
        // 配置仓库模拟对象返回用户
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username])
            ->willReturn($user);
        
        // 配置服务模拟对象创建令牌，期望使用设备信息
        $this->accessTokenService->expects($this->once())
            ->method('createToken')
            ->with($user, 86400, $deviceInfo)
            ->willReturn($token);
        
        // 执行命令，带上设备信息选项
        $exitCode = $this->commandTester->execute([
            'username' => $username,
            '--device' => $deviceInfo
        ]);
        
        // 验证退出代码
        $this->assertEquals(0, $exitCode);
        
        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_user" 生成了新的访问令牌', $output);
    }
} 