<?php

declare(strict_types=1);

namespace Tourze\AccessTokenBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\UserServiceContracts\UserManagerInterface;

#[When(env: 'test')]
#[When(env: 'dev')]
class AccessTokenFixtures extends Fixture
{
    public const VALID_TOKEN_REFERENCE = 'valid-token';
    public const EXPIRED_TOKEN_REFERENCE = 'expired-token';
    public const INVALID_TOKEN_REFERENCE = 'invalid-token';

    public function __construct(
        private readonly UserManagerInterface $userManager,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 通过 UserManager 服务获取或创建测试用户
        $user1 = $this->getOrCreateTestUser('access-token-test-user-1', '访问令牌测试用户1');
        $user2 = $this->getOrCreateTestUser('access-token-test-user-2', '访问令牌测试用户2');

        // 有效的访问令牌
        $validToken = AccessToken::create($user1, 3600, 'Mobile Device');
        $validToken->updateAccessInfo('192.168.1.1');
        $manager->persist($validToken);
        $this->addReference(self::VALID_TOKEN_REFERENCE, $validToken);

        // 已过期的访问令牌
        $expiredToken = AccessToken::create($user1, -3600, 'Desktop Browser');
        $expiredToken->updateAccessInfo('192.168.1.2');
        $manager->persist($expiredToken);
        $this->addReference(self::EXPIRED_TOKEN_REFERENCE, $expiredToken);

        // 无效的访问令牌
        $invalidToken = AccessToken::create($user2, 7200, 'Tablet');
        $invalidToken->setValid(false);
        $invalidToken->updateAccessInfo('192.168.1.3');
        $manager->persist($invalidToken);
        $this->addReference(self::INVALID_TOKEN_REFERENCE, $invalidToken);

        // 长期有效的令牌
        $longTermToken = AccessToken::create($user2, 86400 * 30, 'API Client');
        $manager->persist($longTermToken);

        // 即将过期的令牌
        $soonExpiredToken = AccessToken::create($user1, 300, 'Mobile App');
        $soonExpiredToken->updateAccessInfo('10.0.0.1');
        $manager->persist($soonExpiredToken);

        $manager->flush();
    }

    private function getOrCreateTestUser(string $identifier, string $name): UserInterface
    {
        // 尝试加载已存在的用户
        $user = $this->userManager->loadUserByIdentifier($identifier);

        // 如果用户不存在，创建一个新的测试用户
        if (null === $user) {
            $user = $this->userManager->createUser($identifier, $name);
            $this->userManager->saveUser($user);
        }

        return $user;
    }
}
