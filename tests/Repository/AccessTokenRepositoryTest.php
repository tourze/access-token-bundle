<?php

namespace Tourze\AccessTokenBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(AccessTokenRepository::class)]
#[RunTestsInSeparateProcesses]
final class AccessTokenRepositoryTest extends AbstractRepositoryTestCase
{
    private AccessTokenRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(AccessTokenRepository::class);
    }

    protected function onTearDown(): void
    {
        unset($this->repository);
        self::getEntityManager()->clear();
    }

    /**
     * @return ServiceEntityRepository<AccessToken>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): object
    {
        static $counter = 0;
        $counterValue = is_int($counter) ? $counter + 1 : 1;
        $counter = $counterValue;

        $user = $this->createNormalUser("test{$counterValue}@example.com", 'password123');
        $token = AccessToken::create($user, 3600);

        return $token;
    }

    /**
     * 辅助方法：创建一个AccessToken用于测试
     *
     * @param array<string, mixed> $attributes
     */
    private function createAccessTokenForTest(
        UserInterface $user,
        int $expiresInSeconds = 3600,
        array $attributes = [],
    ): AccessToken {
        $token = AccessToken::create($user, $expiresInSeconds);

        // 应用额外属性
        if (isset($attributes['token']) && is_string($attributes['token'])) {
            $token->setToken($attributes['token']);
        }
        if (isset($attributes['valid']) && is_bool($attributes['valid'])) {
            $token->setValid($attributes['valid']);
        }
        if (isset($attributes['expireTime']) && $attributes['expireTime'] instanceof \DateTimeImmutable) {
            $token->setExpireTime($attributes['expireTime']);
        }
        if (isset($attributes['deviceInfo']) && is_string($attributes['deviceInfo'])) {
            $token->setDeviceInfo($attributes['deviceInfo']);
        }
        if (isset($attributes['lastIp']) && is_string($attributes['lastIp'])) {
            $token->setLastIp($attributes['lastIp']);
        }

        // 保存实体
        $this->repository->save($token);

        return $token;
    }

    public function testRepositoryImplementsServiceEntityRepository(): void
    {
        $repository = self::getService(AccessTokenRepository::class);

        $this->assertInstanceOf(ServiceEntityRepository::class, $repository);
    }

    public function testSave(): void
    {
        $user = $this->createNormalUser('save_test@example.com', 'password123');
        $token = AccessToken::create($user, 3600);

        $this->repository->save($token);

        $this->assertEntityPersisted($token);
        $this->assertNotNull($token->getId());
        $this->assertEquals('save_test@example.com', $token->getUser()->getUserIdentifier());
    }

    public function testSaveWithoutFlush(): void
    {
        $user = $this->createNormalUser('save_no_flush@example.com', 'password123');
        $token = AccessToken::create($user, 3600);

        $this->repository->save($token, false);
        self::getEntityManager()->flush();

        $this->assertEntityPersisted($token);
        $this->assertNotNull($token->getId());
    }

    public function testRemove(): void
    {
        $user = $this->createNormalUser('remove_test@example.com', 'password123');
        $token = $this->createAccessTokenForTest($user);

        $id = $token->getId();
        $this->assertNotNull($id);

        $this->repository->remove($token);

        $this->assertEntityNotExists(AccessToken::class, $id);
    }

    public function testRemoveWithFlushFalse(): void
    {
        $user = $this->createNormalUser('remove_no_flush@example.com', 'password123');
        $token = $this->createAccessTokenForTest($user);

        $id = $token->getId();
        $this->assertNotNull($id);

        $this->repository->remove($token, false);
        // 没有flush，实体应该还存在
        $found = $this->repository->find($id);
        $this->assertInstanceOf(AccessToken::class, $found);

        // flush后应该被删除
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();
        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testFindOneByValue(): void
    {
        $user = $this->createNormalUser('findone_test@example.com', 'password123');
        $tokenValue = bin2hex(random_bytes(32));
        $token = $this->createAccessTokenForTest($user, 3600, ['token' => $tokenValue]);

        $found = $this->repository->findOneByValue($tokenValue);

        $this->assertNotNull($found);
        $this->assertInstanceOf(AccessToken::class, $found);
        $this->assertEquals($token->getId(), $found->getId());
        $this->assertEquals($tokenValue, $found->getToken());
    }

    public function testFindOneByValueWithNonExistentToken(): void
    {
        $found = $this->repository->findOneByValue('nonexistent_token_value');

        $this->assertNull($found);
    }

    public function testFindOneByValueShouldIgnoreInvalidTokens(): void
    {
        $user = $this->createNormalUser('invalid_test@example.com', 'password123');
        $tokenValue = bin2hex(random_bytes(32));
        $this->createAccessTokenForTest($user, 3600, [
            'token' => $tokenValue,
            'valid' => false,
        ]);

        $found = $this->repository->findOneByValue($tokenValue);

        $this->assertNull($found);
    }

    public function testFindOneByValueShouldIgnoreExpiredTokens(): void
    {
        $user = $this->createNormalUser('expired_test@example.com', 'password123');
        $tokenValue = bin2hex(random_bytes(32));
        $expireTime = (new \DateTimeImmutable())->modify('-1 hour');
        $this->createAccessTokenForTest($user, 3600, [
            'token' => $tokenValue,
            'expireTime' => $expireTime,
        ]);

        $found = $this->repository->findOneByValue($tokenValue);

        $this->assertNull($found);
    }

    public function testFindValidTokensByUser(): void
    {
        // 禁用防止多点登录功能
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createNormalUser('multi_tokens@example.com', 'password123');

        // 创建多个有效令牌
        $token1 = $this->createAccessTokenForTest($user, 3600);
        $token2 = $this->createAccessTokenForTest($user, 7200);

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertIsArray($tokens);
        $this->assertGreaterThanOrEqual(2, count($tokens));

        $tokenIds = array_map(fn ($t) => $t->getId(), $tokens);
        $this->assertContains($token1->getId(), $tokenIds);
        $this->assertContains($token2->getId(), $tokenIds);
    }

    public function testFindValidTokensByUserShouldIgnoreInvalidTokens(): void
    {
        $user = $this->createNormalUser('invalid_tokens@example.com', 'password123');

        // 创建有效和无效令牌
        $validToken = $this->createAccessTokenForTest($user, 3600);
        $invalidToken = $this->createAccessTokenForTest($user, 3600, ['valid' => false]);

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertIsArray($tokens);
        $tokenIds = array_map(fn ($t) => $t->getId(), $tokens);
        $this->assertContains($validToken->getId(), $tokenIds);
        $this->assertNotContains($invalidToken->getId(), $tokenIds);
    }

    public function testFindValidTokensByUserShouldIgnoreExpiredTokens(): void
    {
        $user = $this->createNormalUser('expired_tokens@example.com', 'password123');

        // 创建有效和过期令牌
        $validToken = $this->createAccessTokenForTest($user, 3600);
        $expiredToken = $this->createAccessTokenForTest($user, 3600, [
            'expireTime' => (new \DateTimeImmutable())->modify('-1 hour'),
        ]);

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertIsArray($tokens);
        $tokenIds = array_map(fn ($t) => $t->getId(), $tokens);
        $this->assertContains($validToken->getId(), $tokenIds);
        $this->assertNotContains($expiredToken->getId(), $tokenIds);
    }

    public function testFindValidTokensByUserWithNoTokens(): void
    {
        $user = $this->createNormalUser('no_tokens@example.com', 'password123');

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertIsArray($tokens);
        $this->assertEmpty($tokens);
    }

    public function testFindValidTokensByUserShouldOrderByCreateTimeDesc(): void
    {
        // 禁用防止多点登录功能
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createNormalUser('ordered_tokens@example.com', 'password123');

        // 创建多个令牌（按顺序）
        $token1 = $this->createAccessTokenForTest($user, 3600);
        sleep(1); // 确保时间戳不同
        $token2 = $this->createAccessTokenForTest($user, 3600);

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertIsArray($tokens);
        $this->assertGreaterThanOrEqual(2, count($tokens));

        // 验证最新创建的在前面
        $firstToken = $tokens[0];
        $this->assertEquals($token2->getId(), $firstToken->getId());
    }

    public function testRemoveExpiredTokens(): void
    {
        $user1 = $this->createNormalUser('expired1@example.com', 'password123');
        $user2 = $this->createNormalUser('expired2@example.com', 'password123');

        // 创建过期令牌
        $expiredToken1 = $this->createAccessTokenForTest($user1, 3600, [
            'expireTime' => (new \DateTimeImmutable())->modify('-1 hour'),
        ]);
        $expiredToken2 = $this->createAccessTokenForTest($user2, 3600, [
            'expireTime' => (new \DateTimeImmutable())->modify('-2 hours'),
        ]);

        // 创建有效令牌
        $validToken = $this->createAccessTokenForTest($user1, 3600);

        // 执行清理
        $deletedCount = $this->repository->removeExpiredTokens();

        // 验证至少删除了我们创建的过期令牌
        $this->assertGreaterThanOrEqual(2, $deletedCount);

        // 验证过期令牌被删除
        $this->assertEntityNotExists(AccessToken::class, $expiredToken1->getId());
        $this->assertEntityNotExists(AccessToken::class, $expiredToken2->getId());

        // 验证有效令牌仍然存在
        $found = $this->repository->find($validToken->getId());
        $this->assertNotNull($found);
    }

    public function testRemoveExpiredTokensShouldRemoveInvalidTokens(): void
    {
        $user = $this->createNormalUser('invalid_remove@example.com', 'password123');

        // 创建无效令牌（即使未过期）
        $invalidToken = $this->createAccessTokenForTest($user, 3600, [
            'valid' => false,
        ]);

        // 创建有效令牌
        $validToken = $this->createAccessTokenForTest($user, 3600);

        // 执行清理
        $deletedCount = $this->repository->removeExpiredTokens();

        // 验证至少删除了无效令牌
        $this->assertGreaterThanOrEqual(1, $deletedCount);

        // 验证无效令牌被删除
        $this->assertEntityNotExists(AccessToken::class, $invalidToken->getId());

        // 验证有效令牌仍然存在
        $found = $this->repository->find($validToken->getId());
        $this->assertNotNull($found);
    }

    public function testRemoveExpiredTokensWhenNoExpiredTokens(): void
    {
        $user = $this->createNormalUser('no_expired@example.com', 'password123');

        // 只创建有效令牌
        $this->createAccessTokenForTest($user, 3600);

        // 先清理一次
        $this->repository->removeExpiredTokens();

        // 再次清理应该返回0
        $deletedCount = $this->repository->removeExpiredTokens();

        $this->assertEquals(0, $deletedCount);
    }

    public function testFindByUser(): void
    {
        $user1 = $this->createNormalUser('user1_tokens@example.com', 'password123');
        $user2 = $this->createNormalUser('user2_tokens@example.com', 'password123');

        $token1 = $this->createAccessTokenForTest($user1);
        $token2 = $this->createAccessTokenForTest($user2);

        $user1Tokens = $this->repository->findBy(['user' => $user1]);
        $user2Tokens = $this->repository->findBy(['user' => $user2]);

        $this->assertCount(1, $user1Tokens);
        $this->assertSame($token1->getId(), $user1Tokens[0]->getId());
        $this->assertCount(1, $user2Tokens);
        $this->assertSame($token2->getId(), $user2Tokens[0]->getId());
    }

    public function testTokenUniqueness(): void
    {
        $user = $this->createNormalUser('unique_test@example.com', 'password123');

        // 创建多个令牌
        $token1 = $this->createAccessTokenForTest($user);
        $token2 = $this->createAccessTokenForTest($user);

        // 验证每个令牌值都是唯一的
        $this->assertNotEquals($token1->getToken(), $token2->getToken());
    }

    public function testDeviceInfoStorage(): void
    {
        $user = $this->createNormalUser('device_test@example.com', 'password123');
        $deviceInfo = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)';

        $token = $this->createAccessTokenForTest($user, 3600, [
            'deviceInfo' => $deviceInfo,
        ]);

        $found = $this->repository->find($token->getId());
        $this->assertNotNull($found);
        $this->assertEquals($deviceInfo, $found->getDeviceInfo());
    }

    public function testLastIpStorage(): void
    {
        $user = $this->createNormalUser('ip_test@example.com', 'password123');
        $ip = '192.168.1.100';

        $token = $this->createAccessTokenForTest($user, 3600, [
            'lastIp' => $ip,
        ]);

        $found = $this->repository->find($token->getId());
        $this->assertNotNull($found);
        $this->assertEquals($ip, $found->getLastIp());
    }

    public function testDefaultValidValueIsTrue(): void
    {
        $user = $this->createNormalUser('default_valid@example.com', 'password123');
        $token = $this->createAccessTokenForTest($user);

        $found = $this->repository->find($token->getId());
        $this->assertNotNull($found);
        $this->assertTrue($found->isValid());
    }

    public function testTokenDeletionDoesNotAffectUser(): void
    {
        $user = $this->createNormalUser('cascade_test@example.com', 'password123');
        $token = $this->createAccessTokenForTest($user);

        $tokenId = $token->getId();
        $this->assertNotNull($tokenId);
        $userId = $user->getUserIdentifier();

        // 删除令牌不应该影响用户
        $this->repository->remove($token);
        self::getEntityManager()->clear();

        // 验证令牌被删除
        $found = $this->repository->find($tokenId);
        $this->assertNull($found);

        // 用户仍然应该存在（验证可以创建新用户对象）
        $newUser = $this->createNormalUser('another_user@example.com', 'password123');
        $this->assertNotNull($newUser);
    }

    public function testCountByValid(): void
    {
        $user1 = $this->createNormalUser('count_valid1@example.com', 'password123');
        $user2 = $this->createNormalUser('count_valid2@example.com', 'password123');

        $this->createAccessTokenForTest($user1, 3600, ['valid' => true]);
        $this->createAccessTokenForTest($user2, 3600, ['valid' => false]);

        $validCount = $this->repository->count(['valid' => true]);
        $invalidCount = $this->repository->count(['valid' => false]);

        $this->assertGreaterThanOrEqual(1, $validCount);
        $this->assertGreaterThanOrEqual(1, $invalidCount);
    }

    public function testFindByToken(): void
    {
        $user = $this->createNormalUser('findby_token@example.com', 'password123');
        $tokenValue = bin2hex(random_bytes(32));
        $token = $this->createAccessTokenForTest($user, 3600, ['token' => $tokenValue]);

        $results = $this->repository->findBy(['token' => $tokenValue]);

        $this->assertCount(1, $results);
        $this->assertEquals($token->getId(), $results[0]->getId());
    }

    public function testMultipleTokensPerUserWithDifferentExpiry(): void
    {
        // 禁用防止多点登录功能
        $_ENV['ACCESS_TOKEN_PREVENT_MULTIPLE_LOGIN'] = 'false';

        $user = $this->createNormalUser('multi_expiry@example.com', 'password123');

        // 创建不同过期时间的令牌
        $shortToken = $this->createAccessTokenForTest($user, 3600); // 1小时
        $longToken = $this->createAccessTokenForTest($user, 86400); // 24小时

        $tokens = $this->repository->findValidTokensByUser($user);

        $this->assertGreaterThanOrEqual(2, count($tokens));

        // 验证两个令牌都在列表中
        $tokenIds = array_map(fn ($t) => $t->getId(), $tokens);
        $this->assertContains($shortToken->getId(), $tokenIds);
        $this->assertContains($longToken->getId(), $tokenIds);
    }
}
