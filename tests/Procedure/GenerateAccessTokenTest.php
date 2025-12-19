<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessTokenBundle\Exception\UserNotFoundException;
use Tourze\AccessTokenBundle\Param\GenerateAccessTokenParam;
use Tourze\AccessTokenBundle\Procedure\GenerateAccessToken;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GenerateAccessToken::class)]
#[RunTestsInSeparateProcesses]
final class GenerateAccessTokenTest extends AbstractProcedureTestCase
{
    private GenerateAccessToken $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GenerateAccessToken::class);
    }

    public function testSuccessfulTokenGeneration(): void
    {
        $user = $this->createNormalUser('test@example.com', 'password123');

        $param = new GenerateAccessTokenParam(
            identifier: $user->getUserIdentifier(),
            expiresIn: 7200,
            deviceInfo: 'iPhone 15'
        );

        $result = $this->procedure->execute($param);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expire_time', $result);
        $this->assertArrayHasKey('create_time', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('device_info', $result);

        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('iPhone 15', $result['device_info']);
        $this->assertArrayHasKey('expire_time', $result);
        $this->assertIsString($result['expire_time']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['expire_time']);
        $this->assertArrayHasKey('create_time', $result);
        $this->assertIsString($result['create_time']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['create_time']);
    }

    public function testTokenGenerationWithDefaultExpiration(): void
    {
        $user = $this->createNormalUser('user2@example.com', 'password456');

        $param = new GenerateAccessTokenParam(
            identifier: $user->getUserIdentifier(),
            expiresIn: null,
            deviceInfo: null
        );

        $result = $this->procedure->execute($param);

        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertNull($result['device_info']);
        $this->assertNotEmpty($result['expire_time']);
        $this->assertNotEmpty($result['create_time']);
    }

    public function testUserNotFoundThrowsException(): void
    {
        $param = new GenerateAccessTokenParam(
            identifier: 'nonexistent'
        );

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('用户标识符 nonexistent 不存在');

        $this->procedure->execute($param);
    }

    public function testTokenGenerationWithAdminUser(): void
    {
        $admin = $this->createAdminUser('admin@example.com', 'adminpass');

        $param = new GenerateAccessTokenParam(
            identifier: $admin->getUserIdentifier(),
            expiresIn: 3600,
            deviceInfo: 'Admin Device'
        );

        $result = $this->procedure->execute($param);

        $this->assertEquals($admin->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('Admin Device', $result['device_info']);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    public function testExecute(): void
    {
        $user = $this->createNormalUser('execute@example.com', 'password');

        $param = new GenerateAccessTokenParam(
            identifier: $user->getUserIdentifier(),
            expiresIn: 3600,
            deviceInfo: 'Test Device'
        );

        $result = $this->procedure->execute($param);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('device_info', $result);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('Test Device', $result['device_info']);
    }
}
