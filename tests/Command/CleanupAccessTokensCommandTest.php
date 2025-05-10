<?php

namespace AccessTokenBundle\Tests\Command;

use AccessTokenBundle\Command\CleanupAccessTokensCommand;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupAccessTokensCommandTest extends TestCase
{
    private AccessTokenService $accessTokenService;
    private CleanupAccessTokensCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->command = new CleanupAccessTokensCommand($this->accessTokenService);

        $application = new Application();
        $application->add($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecute_withDefaultOptions_shouldCleanupTokens(): void
    {
        // 配置服务模拟对象返回已清理的令牌数量
        $this->accessTokenService->expects($this->once())
            ->method('cleanupExpiredTokens')
            ->willReturn(5);

        // 执行命令
        $exitCode = $this->commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('成功清理 5 个过期访问令牌', $output);
    }

    public function testExecute_withDryRunOption_shouldNotCleanupTokens(): void
    {
        // 配置服务模拟对象
        $this->accessTokenService->expects($this->once())
            ->method('cleanupExpiredTokens')
            ->willReturn(3);

        // 执行命令，带上dry-run选项
        $exitCode = $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('正在以"仅显示模式"运行', $output);
        $this->assertStringContainsString('找到 3 个过期访问令牌需要清理', $output);
    }

    public function testExecute_whenExceptionOccurs_shouldReturnFailure(): void
    {
        // 配置服务模拟对象抛出异常
        $this->accessTokenService->expects($this->once())
            ->method('cleanupExpiredTokens')
            ->willThrowException(new \RuntimeException('测试异常'));

        // 执行命令
        $exitCode = $this->commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(1, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('清理过程中发生错误: 测试异常', $output);
    }

    public function testExecute_withZeroExpiredTokens_shouldShowSuccessMessage(): void
    {
        // 配置服务模拟对象返回0，表示没有过期令牌
        $this->accessTokenService->expects($this->once())
            ->method('cleanupExpiredTokens')
            ->willReturn(0);

        // 执行命令
        $exitCode = $this->commandTester->execute([]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode);

        // 验证输出
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('成功清理 0 个过期访问令牌', $output);
    }
}
