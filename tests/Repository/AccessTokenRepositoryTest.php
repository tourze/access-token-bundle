<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\Tests\Repository;

use BizUserBundle\Entity\BizUser;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
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
    protected function onSetUp(): void
    {
        // AbstractRepositoryTestCase 会自动处理设置
    }

    public function testFindOneByValueWithValidToken(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user = $this->createNormalUser('test@example.com');

        // Create a valid token
        $token = AccessToken::create($user, 3600, 'Test Device');
        $repository->save($token);

        // Test finding the token
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $foundToken = $repository->findOneByValue($tokenValue);
        $this->assertNotNull($foundToken);
        $this->assertEquals($token->getId(), $foundToken->getId());
        $this->assertEquals($token->getToken(), $foundToken->getToken());
    }

    public function testFindValidTokensByUserWithMultipleTokens(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user = $this->createNormalUser('test@example.com');

        // Clean up all existing tokens for this user to ensure test isolation
        $existingTokens = $repository->findBy(['user' => $user]);
        foreach ($existingTokens as $token) {
            $repository->remove($token);
        }

        // Create multiple tokens
        $token1 = AccessToken::create($user, 3600, 'Device 1');
        $token2 = AccessToken::create($user, 7200, 'Device 2');

        // Create an expired token by manually setting the expiry date
        $expiredToken = AccessToken::create($user, 3600, 'Old Device');
        $expiredToken->setExpireTime(new \DateTimeImmutable('-1 hour'));

        $invalidToken = AccessToken::create($user, 3600, 'Invalid Device');
        $invalidToken->setValid(false);

        $repository->save($token1);
        $repository->save($token2);
        $repository->save($expiredToken);
        $repository->save($invalidToken);

        // Test finding valid tokens
        $validTokens = $repository->findValidTokensByUser($user);
        $this->assertCount(2, $validTokens);

        $tokenIds = array_map(fn ($token) => $token->getId(), $validTokens);
        $this->assertContains($token1->getId(), $tokenIds);
        $this->assertContains($token2->getId(), $tokenIds);
    }

    public function testRemoveExpiredTokensRemovesExpiredAndInvalidTokens(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);

        // 先清理可能存在的过期 tokens
        $repository->removeExpiredTokens();

        $user = $this->createNormalUser('test@example.com');

        // Create tokens with different states
        $validToken = AccessToken::create($user, 3600, 'Valid Device');

        // Create an expired token by manually setting the expiry date
        $expiredToken = AccessToken::create($user, 3600, 'Expired Device');
        $expiredToken->setExpireTime(new \DateTimeImmutable('-1 hour'));

        $invalidToken = AccessToken::create($user, 3600, 'Invalid Device');
        $invalidToken->setValid(false);

        $repository->save($validToken);
        $repository->save($expiredToken);
        $repository->save($invalidToken);

        // Remove expired tokens
        $removedCount = $repository->removeExpiredTokens();
        $this->assertEquals(2, $removedCount);

        // Verify only valid token remains
        $remainingTokens = $repository->findValidTokensByUser($user);
        $this->assertCount(1, $remainingTokens);
        $this->assertEquals($validToken->getId(), $remainingTokens[0]->getId());
    }

    public function testFindOneByValueShouldReturnNullForNonExistentToken(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $result = $repository->findOneByValue('non_existent_token');
        $this->assertNull($result);
    }

    public function testFindValidTokensByUserShouldReturnEmptyArrayForUserWithoutTokens(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user = $this->createNormalUser('test@example.com');
        $result = $repository->findValidTokensByUser($user);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRemoveExpiredTokensShouldReturnZeroWhenNoExpiredTokens(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);

        // 先清理可能存在的过期 tokens
        $repository->removeExpiredTokens();

        // 创建一个有效的 token，确保不是过期的
        $user = $this->createNormalUser('test@example.com');
        $validToken = AccessToken::create($user, 3600, 'Valid Device');
        $repository->save($validToken);

        // 再次调用应该返回 0
        $result = $repository->removeExpiredTokens();
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);

        // 验证有效 token 仍然存在
        $foundToken = $repository->find($validToken->getId());
        $this->assertNotNull($foundToken);
    }

    public function testFindOneByAssociationUserShouldReturnMatchingEntity(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user = $this->createNormalUser('test@example.com');

        $token = AccessToken::create($user, 3600, 'Test Device');
        $repository->save($token);

        // 使用 findOneBy 查找
        $foundToken = $repository->findOneBy(['user' => $user]);
        $this->assertNotNull($foundToken);
        $this->assertEquals($token->getId(), $foundToken->getId());
        $this->assertSame($user, $foundToken->getUser());
    }

    public function testCountByAssociationUserShouldReturnCorrectNumber(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        // 创建不同数量的 token
        for ($i = 0; $i < 3; ++$i) {
            $repository->save(AccessToken::create($user1, 3600, "Device {$i}"));
        }
        for ($i = 0; $i < 2; ++$i) {
            $repository->save(AccessToken::create($user2, 3600, "Device {$i}"));
        }

        // 计数
        $count = $repository->count(['user' => $user1]);
        $this->assertEquals(3, $count);

        $count = $repository->count(['user' => $user2]);
        $this->assertEquals(2, $count);
    }

    public function testFindOneByOrderByLogic(): void
    {
        $repository = self::getRepository();
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
        $user = $this->createNormalUser('test@example.com');

        // 先清理该用户可能存在的所有 tokens
        $existingTokens = $repository->findBy(['user' => $user]);
        foreach ($existingTokens as $token) {
            $repository->remove($token);
        }

        // 创建多个相同用户的 tokens，使用不同的过期时间以确保排序
        $baseTime = new \DateTimeImmutable();

        $token1 = AccessToken::create($user, 1800, 'Device 1');
        $token1->setCreateTime(new \DateTimeImmutable('-3 minutes'));
        $repository->save($token1);

        $token2 = AccessToken::create($user, 3600, 'Device 2');
        $token2->setCreateTime(new \DateTimeImmutable('-2 minutes'));
        $repository->save($token2);

        $token3 = AccessToken::create($user, 7200, 'Device 3');
        $token3->setCreateTime(new \DateTimeImmutable('-1 minute'));
        $repository->save($token3);

        // 测试按 expireTime 升序排序，应该返回最早过期的
        $earliestToken = $repository->findOneBy(['user' => $user], ['expireTime' => 'ASC']);
        $this->assertNotNull($earliestToken);
        $this->assertEquals('Device 1', $earliestToken->getDeviceInfo());

        // 测试按 expireTime 降序排序，应该返回最晚过期的
        $latestToken = $repository->findOneBy(['user' => $user], ['expireTime' => 'DESC']);
        $this->assertNotNull($latestToken);
        $this->assertEquals('Device 3', $latestToken->getDeviceInfo());

        // 测试按 createTime 升序排序
        $oldestToken = $repository->findOneBy(['user' => $user], ['createTime' => 'ASC']);
        $this->assertNotNull($oldestToken);
        $this->assertEquals('Device 1', $oldestToken->getDeviceInfo());

        // 测试按 createTime 降序排序
        $newestToken = $repository->findOneBy(['user' => $user], ['createTime' => 'DESC']);
        $this->assertNotNull($newestToken);
        $this->assertEquals('Device 3', $newestToken->getDeviceInfo());
    }

    protected function createNewEntity(): object
    {
        // 创建 BizUser 对象，但不持久化
        $user = new BizUser();
        $user->setUsername('test-user-' . uniqid());
        $user->setPasswordHash('password-hash');

        return AccessToken::create($user, 3600, 'Test Device');
    }

    /**
     * @return ServiceEntityRepository<AccessToken>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(AccessTokenRepository::class);
    }

    /**
     * 创建一个普通用户用于测试
     */
    protected function createNormalUser(?string $username = null, ?string $password = null): BizUser
    {
        $user = new BizUser();
        $user->setUsername($username ?? 'test-user-' . uniqid());
        $user->setPasswordHash($password ?? 'password-hash');

        return $user;
    }
}
