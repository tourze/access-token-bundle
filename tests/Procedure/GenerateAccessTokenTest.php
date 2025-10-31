<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessTokenBundle\Exception\UserNotFoundException;
use Tourze\AccessTokenBundle\Procedure\GenerateAccessToken;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;

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

        $this->procedure->identifier = $user->getUserIdentifier();
        $this->procedure->expiresIn = 7200;
        $this->procedure->deviceInfo = 'iPhone 15';

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expire_time', $result);
        $this->assertArrayHasKey('create_time', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('device_info', $result);

        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('iPhone 15', $result['device_info']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['expire_time']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $result['create_time']);
    }

    public function testTokenGenerationWithDefaultExpiration(): void
    {
        $user = $this->createNormalUser('user2@example.com', 'password456');

        $this->procedure->identifier = $user->getUserIdentifier();
        $this->procedure->expiresIn = null;
        $this->procedure->deviceInfo = null;

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertNull($result['device_info']);
        $this->assertNotEmpty($result['expire_time']);
        $this->assertNotEmpty($result['create_time']);
    }

    public function testUserNotFoundThrowsException(): void
    {
        $this->procedure->identifier = 'nonexistent';

        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('用户标识符 nonexistent 不存在');

        $this->procedure->execute();
    }

    public function testGetMockResultReturnsExpectedStructure(): void
    {
        $mockResult = GenerateAccessToken::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('token', $mockResult);
        $this->assertArrayHasKey('expire_time', $mockResult);
        $this->assertArrayHasKey('create_time', $mockResult);
        $this->assertArrayHasKey('user_id', $mockResult);
        $this->assertArrayHasKey('device_info', $mockResult);

        $this->assertIsString($mockResult['token']);
        $this->assertNotEmpty($mockResult['token']);
        $this->assertEquals('2024-01-02 12:00:00', $mockResult['expire_time']);
        $this->assertEquals('2024-01-01 12:00:00', $mockResult['create_time']);
        $this->assertEquals('123', $mockResult['user_id']);
        $this->assertEquals('iPhone 15', $mockResult['device_info']);
    }

    public function testTokenGenerationWithAdminUser(): void
    {
        $admin = $this->createAdminUser('admin@example.com', 'adminpass');

        $this->procedure->identifier = $admin->getUserIdentifier();
        $this->procedure->expiresIn = 3600;
        $this->procedure->deviceInfo = 'Admin Device';

        $result = $this->procedure->execute();

        $this->assertEquals($admin->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('Admin Device', $result['device_info']);
        $this->assertIsString($result['token']);
        $this->assertNotEmpty($result['token']);
    }

    public function testExecute(): void
    {
        $user = $this->createNormalUser('execute@example.com', 'password');

        $this->procedure->identifier = $user->getUserIdentifier();
        $this->procedure->expiresIn = 3600;
        $this->procedure->deviceInfo = 'Test Device';

        $result = $this->procedure->execute();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertArrayHasKey('device_info', $result);
        $this->assertEquals($user->getUserIdentifier(), $result['user_id']);
        $this->assertEquals('Test Device', $result['device_info']);
    }
}
