<?php

namespace Tourze\AccessTokenBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\AccessTokenBundle\Command\CreateAccessTokenCommand;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CreateAccessTokenCommand::class)]
#[RunTestsInSeparateProcesses]
final class CreateAccessTokenCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // AbstractCommandTestCase 会自动清理数据库
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(CreateAccessTokenCommand::class);
        $this->assertInstanceOf(CreateAccessTokenCommand::class, $command);

        return new CommandTester($command);
    }

    public function testExecuteWithValidUsernameShouldCreateToken(): void
    {
        $username = 'test@example.com';
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test@example.com" 生成了新的访问令牌', $output);
        $this->assertStringContainsString('令牌值: ', $output);
        $this->assertStringContainsString('过期时间: ', $output);
        $this->assertStringContainsString('使用方式: Authorization: Bearer ', $output);

        // 验证token确实被创建了
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
    }

    public function testExecuteWithInvalidUsernameShouldReturnFailure(): void
    {
        $username = 'invalid_user@example.com';

        // 执行命令
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
        ]);

        // 验证退出代码
        $this->assertEquals(1, $exitCode);

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('用户 "invalid_user@example.com" 不存在', $output);
    }

    public function testExecuteWithCustomExpiresOptionShouldCreateTokenWithCustomExpires(): void
    {
        $username = 'test_expires@example.com';
        $customExpires = 3600; // 1小时
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令，带上自定义过期时间选项
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
            '--expires' => $customExpires,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_expires@example.com" 生成了新的访问令牌', $output);
        $this->assertStringContainsString('令牌值: ', $output);
        $this->assertStringContainsString('过期时间: ', $output);

        // 验证token的过期时间
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
        $token = $tokens[0];

        // 检查过期时间是否在正确的范围内（约1小时后）
        $now = new \DateTimeImmutable();
        $expireTime = $token->getExpireTime();
        $this->assertNotNull($expireTime);
        $diff = $expireTime->getTimestamp() - $now->getTimestamp();
        $this->assertGreaterThan(3500, $diff); // 至少59分钟
        $this->assertLessThan(3700, $diff); // 最多61分钟
    }

    public function testExecuteWithDeviceOptionShouldCreateTokenWithDeviceInfo(): void
    {
        $username = 'test_device@example.com';
        $deviceInfo = 'Test Device';
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令，带上设备信息选项
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
            '--device' => $deviceInfo,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证输出
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_device@example.com" 生成了新的访问令牌', $output);
        $this->assertStringContainsString('令牌值: ', $output);
        $this->assertStringContainsString('过期时间: ', $output);

        // 验证token的设备信息
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
        $token = $tokens[0];
        $this->assertEquals($deviceInfo, $token->getDeviceInfo());
    }

    public function testArgumentUsername(): void
    {
        $username = 'test_argument@example.com';
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令，验证username参数
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证输出包含用户名
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('为用户 "test_argument@example.com" 生成了新的访问令牌', $output);

        // 验证token确实被创建了
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
    }

    public function testOptionExpires(): void
    {
        $username = 'test_option_expires@example.com';
        $customExpires = 7200; // 2小时
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令，验证--expires选项
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
            '--expires' => $customExpires,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证token的过期时间设置正确
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
        $token = $tokens[0];

        // 检查过期时间是否在正确的范围内（约2小时后）
        $now = new \DateTimeImmutable();
        $expireTime = $token->getExpireTime();
        $this->assertNotNull($expireTime);
        $diff = $expireTime->getTimestamp() - $now->getTimestamp();
        $this->assertGreaterThan(7100, $diff); // 至少1小时58分钟
        $this->assertLessThan(7300, $diff); // 最多2小时2分钟
    }

    public function testOptionDevice(): void
    {
        $username = 'test_option_device@example.com';
        $deviceInfo = 'iPhone 13 Pro Max';
        // 创建真实的测试用户
        $user = $this->createNormalUser($username);

        // 执行命令，验证--device选项
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([
            'username' => $username,
            '--device' => $deviceInfo,
        ]);

        // 验证退出代码
        $this->assertEquals(0, $exitCode, 'Command output: ' . $commandTester->getDisplay());

        // 验证token的设备信息设置正确
        $repository = self::getService(AccessTokenRepository::class);
        $tokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $tokens);
        $token = $tokens[0];
        $this->assertEquals($deviceInfo, $token->getDeviceInfo());
    }
}
