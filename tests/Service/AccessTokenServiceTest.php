<?php

namespace Tourze\AccessTokenBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\AccessTokenBundle\Service\AccessTokenService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AccessTokenService::class)]
#[RunTestsInSeparateProcesses]
final class AccessTokenServiceTest extends AbstractIntegrationTestCase
{
    private AccessTokenService $service;

    private AccessTokenRepository $repository;

    protected function onSetUp(): void
    {
        $this->service = self::getService(AccessTokenService::class);
        $this->repository = self::getService(AccessTokenRepository::class);
    }

    protected function onTearDown(): void
    {
        // 清理环境变量
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'], $_ENV['ACCESS_TOKEN_RENEWAL_TIME']);
    }

    public function testCreateTokenShouldCreateAndSaveToken(): void
    {
        $user = $this->createNormalUser('test@example.com');

        // 调用服务方法
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);
        $this->assertNotNull($createdToken->getToken());
        $this->assertSame($user, $createdToken->getUser());
        $this->assertTrue($createdToken->isValid());
        $this->assertFalse($createdToken->isExpired());

        // 验证令牌被保存到数据库
        $tokenValue = $createdToken->getToken();
        $foundToken = $this->repository->findOneByValue($tokenValue);
        $this->assertNotNull($foundToken);
        $this->assertEquals($createdToken->getId(), $foundToken->getId());
    }

    public function testCreateTokenWithCustomExpiryShouldUseCustomExpiry(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $customExpiry = 7200; // 2小时

        // 调用服务方法
        $createdToken = $this->service->createToken($user, $customExpiry);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);

        // 验证过期时间大约是2小时后
        $expireTime = $createdToken->getExpireTime();
        $this->assertNotNull($expireTime);
        $expectedExpiry = (new \DateTimeImmutable())->modify('+7200 seconds');
        $diff = abs($expectedExpiry->getTimestamp() - $expireTime->getTimestamp());
        $this->assertLessThan(5, $diff); // 允许5秒误差
    }

    public function testFindTokenShouldReturnTokenFromRepository(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);

        // 调用服务方法查找令牌
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $result = $this->service->findToken($tokenValue);

        // 验证返回的是同一个令牌
        $this->assertNotNull($result);
        $this->assertEquals($token->getId(), $result->getId());
    }

    public function testFindTokensByUserShouldReturnUserTokens(): void
    {
        // 禁用防止多点登录功能
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createNormalUser('test@example.com');

        // 创建多个令牌
        $token1 = $this->service->createToken($user);
        $token2 = $this->service->createToken($user);

        // 调用服务方法
        $result = $this->service->findTokensByUser($user);

        // 验证返回的令牌
        $this->assertCount(2, $result);
        $tokenIds = array_map(fn ($t) => $t->getId(), $result);
        $this->assertContains($token1->getId(), $tokenIds);
        $this->assertContains($token2->getId(), $tokenIds);
    }

    public function testValidateTokenWithValidTokenShouldReturnTrue(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertTrue($result);
    }

    public function testValidateTokenWithInvalidTokenShouldReturnFalse(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);

        // 撤销令牌
        $this->service->revokeToken($token);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertFalse($result);
    }

    public function testValidateTokenWithExpiredTokenShouldReturnFalse(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user, 1); // 1秒后过期

        // 等待令牌过期
        sleep(2);

        // 调用服务方法
        $result = $this->service->validateToken($token);

        // 验证结果
        $this->assertFalse($result);
    }

    public function testValidateAndExtendTokenWithValidTokenShouldExtendAndReturnToken(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);
        $originalExpiry = $token->getExpireTime();

        // 等待一秒以确保时间有变化
        sleep(1);

        // 调用服务方法
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $result = $this->service->validateAndExtendToken($tokenValue);

        // 验证结果
        $this->assertNotNull($result);
        $this->assertEquals($token->getId(), $result->getId());
        $resultExpireTime = $result->getExpireTime();
        $this->assertNotNull($originalExpiry);
        $this->assertNotNull($resultExpireTime);
        $this->assertGreaterThan($originalExpiry->getTimestamp(), $resultExpireTime->getTimestamp());
        $this->assertNotNull($result->getLastAccessTime());
    }

    public function testValidateAndExtendTokenWithInvalidTokenShouldReturnNull(): void
    {
        // 调用服务方法
        $result = $this->service->validateAndExtendToken('invalid_token');

        // 验证结果
        $this->assertNull($result);
    }

    public function testValidateAndExtendTokenWithExpiredTokenShouldReturnNull(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user, 1); // 1秒后过期

        // 等待令牌过期
        sleep(2);

        // 调用服务方法
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $result = $this->service->validateAndExtendToken($tokenValue);

        // 验证结果
        $this->assertNull($result);
    }

    public function testRevokeTokenShouldSetTokenInvalid(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);
        $tokenId = $token->getId();

        // 调用服务方法
        $this->service->revokeToken($token);

        // 直接从数据库获取令牌（不使用 findOneByValue，因为它会过滤无效令牌）
        $updatedToken = self::getEntityManager()->find(AccessToken::class, $tokenId);
        $this->assertNotNull($updatedToken);
        $this->assertFalse($updatedToken->isValid());
    }

    public function testDeleteTokenShouldRemoveTokenFromRepository(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);
        $tokenValue = $token->getToken();

        // 调用服务方法
        $this->service->deleteToken($token);

        // 验证令牌已被删除
        $this->assertNotNull($tokenValue);
        $deletedToken = $this->repository->findOneByValue($tokenValue);
        $this->assertNull($deletedToken);
    }

    public function testCleanupExpiredTokensShouldRemoveExpiredTokens(): void
    {
        $user = $this->createNormalUser('test@example.com');

        // 创建一个已过期的令牌
        $expiredToken = $this->service->createToken($user, 1); // 1秒后过期
        sleep(2);

        // 创建一个有效的令牌
        $validToken = $this->service->createToken($user);

        // 调用服务方法
        $result = $this->service->cleanupExpiredTokens();

        // 验证结果
        $this->assertGreaterThanOrEqual(1, $result);

        // 验证过期令牌被删除
        $expiredTokenValue = $expiredToken->getToken();
        $this->assertNotNull($expiredTokenValue);
        $this->assertNull($this->repository->findOneByValue($expiredTokenValue));

        // 验证有效令牌仍存在
        $validTokenValue = $validToken->getToken();
        $this->assertNotNull($validTokenValue);
        $this->assertNotNull($this->repository->findOneByValue($validTokenValue));
    }

    public function testCreateTokenWithPreventMultipleLoginEnabledShouldRevokeExistingTokens(): void
    {
        // 设置环境变量启用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'true';

        $user = $this->createNormalUser('test@example.com');

        // 创建现有令牌
        $existingToken1 = $this->service->createToken($user);
        $existingToken2 = $this->service->createToken($user);

        // 创建新令牌
        $newToken = $this->service->createToken($user);

        // 验证现有令牌被撤销
        $token1 = self::getEntityManager()->find(AccessToken::class, $existingToken1->getId());
        $token2 = self::getEntityManager()->find(AccessToken::class, $existingToken2->getId());
        $this->assertNotNull($token1);
        $this->assertNotNull($token2);
        $this->assertFalse($token1->isValid());
        $this->assertFalse($token2->isValid());

        // 验证新令牌有效
        $this->assertTrue($newToken->isValid());
    }

    public function testCreateTokenWithPreventMultipleLoginDisabledShouldNotRevokeExistingTokens(): void
    {
        // 设置环境变量禁用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createNormalUser('test@example.com');

        // 创建现有令牌
        $existingToken = $this->service->createToken($user);

        // 创建新令牌
        $newToken = $this->service->createToken($user);

        // 验证现有令牌仍然有效
        $existingTokenValue = $existingToken->getToken();
        $this->assertNotNull($existingTokenValue);
        $token = $this->repository->findOneByValue($existingTokenValue);
        $this->assertNotNull($token);
        $this->assertTrue($token->isValid());

        // 验证新令牌也有效
        $this->assertTrue($newToken->isValid());
    }

    public function testCreateTokenWithDefaultEnvironmentVariableShouldPreventMultipleLogin(): void
    {
        // 不设置环境变量，应该使用默认值true
        unset($_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN']);

        $user = $this->createNormalUser('test@example.com');

        // 创建现有令牌
        $existingToken = $this->service->createToken($user);

        // 创建新令牌
        $newToken = $this->service->createToken($user);

        // 验证现有令牌被撤销（默认行为）
        $token = self::getEntityManager()->find(AccessToken::class, $existingToken->getId());
        $this->assertNotNull($token);
        $this->assertFalse($token->isValid());

        // 验证新令牌有效
        $this->assertTrue($newToken->isValid());
    }

    public function testCreateTokenWithNoExistingTokensShouldNotCallRevoke(): void
    {
        // 设置环境变量启用防止多点登录
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'true';

        $user = $this->createNormalUser('test@example.com');

        // 直接创建令牌（没有现有令牌）
        $createdToken = $this->service->createToken($user);

        // 验证返回的是AccessToken实例
        $this->assertInstanceOf(AccessToken::class, $createdToken);
        $this->assertTrue($createdToken->isValid());
    }

    public function testValidateAndExtendTokenWithCustomRenewalTime(): void
    {
        $user = $this->createNormalUser('test@example.com');
        $token = $this->service->createToken($user);
        $originalExpiry = $token->getExpireTime();

        // 等待一秒以确保时间有变化
        sleep(1);

        // 调用服务方法，传入自定义续期时间
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $result = $this->service->validateAndExtendToken($tokenValue, 7200);

        // 验证结果
        $this->assertNotNull($result);

        // 验证续期时间增加了约2小时
        $resultExpireTime = $result->getExpireTime();
        $this->assertNotNull($originalExpiry);
        $this->assertNotNull($resultExpireTime);
        $diff = $resultExpireTime->getTimestamp() - $originalExpiry->getTimestamp();
        // 续期时间应该接近7200秒（考虑到 sleep(1) 和处理时间）
        $this->assertGreaterThan(7190, $diff);
        $this->assertLessThan(7210, $diff);
    }
}
