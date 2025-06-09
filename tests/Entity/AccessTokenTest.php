<?php

namespace AccessTokenBundle\Tests\Entity;

use AccessTokenBundle\Entity\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class AccessTokenTest extends TestCase
{
    public function testToString_shouldReturnTokenValue(): void
    {
        $token = new AccessToken();
        $token->setToken('test_token_value');

        $this->assertEquals('test_token_value', (string)$token);
    }

    public function testToString_withNullToken_shouldReturnEmptyString(): void
    {
        $token = new AccessToken();

        $this->assertEquals('', (string)$token);
    }

    public function testGetterAndSetter_shouldWorkCorrectly(): void
    {
        $token = new AccessToken();

        // Test ID
        $token->setToken('test_token');
        $this->assertEquals('test_token', $token->getToken());

        // Test User
        $user = $this->createMock(UserInterface::class);
        $token->setUser($user);
        $this->assertSame($user, $token->getUser());

        // Test CreatedAt
        $createdAt = new \DateTimeImmutable('2023-01-01 12:00:00');
        $token->setCreatedAt($createdAt);
        $this->assertSame($createdAt, $token->getCreatedAt());

        // Test ExpiresAt
        $expiresAt = new \DateTimeImmutable('2023-01-02 12:00:00');
        $token->setExpiresAt($expiresAt);
        $this->assertSame($expiresAt, $token->getExpiresAt());

        // Test LastAccessedAt
        $lastAccessedAt = new \DateTimeImmutable('2023-01-01 13:00:00');
        $token->setLastAccessedAt($lastAccessedAt);
        $this->assertSame($lastAccessedAt, $token->getLastAccessedAt());

        // Test DeviceInfo
        $token->setDeviceInfo('test_device');
        $this->assertEquals('test_device', $token->getDeviceInfo());

        // Test LastIp
        $token->setLastIp('127.0.0.1');
        $this->assertEquals('127.0.0.1', $token->getLastIp());

        // Test Valid
        $token->setValid(false);
        $this->assertFalse($token->isValid());
        $token->setValid(true);
        $this->assertTrue($token->isValid());
    }

    public function testIsExpired_withFutureDate_shouldReturnFalse(): void
    {
        $token = new AccessToken();
        $futureDate = new \DateTimeImmutable('+1 day');
        $token->setExpiresAt($futureDate);

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpired_withPastDate_shouldReturnTrue(): void
    {
        $token = new AccessToken();
        $pastDate = new \DateTimeImmutable('-1 day');
        $token->setExpiresAt($pastDate);

        $this->assertTrue($token->isExpired());
    }

    public function testExtend_shouldUpdateExpiresAtAndLastAccessedAt(): void
    {
        $token = new AccessToken();
        $initialExpiresAt = new \DateTimeImmutable('2023-01-01 12:00:00');
        $token->setExpiresAt($initialExpiresAt);

        // 记录扩展前的时间点，用于后面的比较
        $beforeExtend = new \DateTimeImmutable();

        // 扩展令牌有效期3600秒（1小时）
        $token->extend(3600);

        // 确保过期时间已更新
        $this->assertNotSame($initialExpiresAt, $token->getExpiresAt());
        $this->assertGreaterThan($beforeExtend->getTimestamp(), $token->getExpiresAt()->getTimestamp());

        // 测试设置的具体过期时间（允许1秒误差）
        $expectedExpiry = (new \DateTimeImmutable())->modify('+3600 seconds');
        $diff = $expectedExpiry->getTimestamp() - $token->getExpiresAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($diff));

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessedAt());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = $currentTime->getTimestamp() - $token->getLastAccessedAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($accessedDiff));
    }

    public function testUpdateAccessInfo_shouldUpdateLastAccessedAtOnly(): void
    {
        $token = new AccessToken();

        // 记录更新前的时间点
        $beforeUpdate = new \DateTimeImmutable();

        // 更新访问信息，不传IP
        $token->updateAccessInfo();

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessedAt());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = $currentTime->getTimestamp() - $token->getLastAccessedAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($accessedDiff));

        // IP应为null
        $this->assertNull($token->getLastIp());
    }

    public function testUpdateAccessInfo_withIp_shouldUpdateLastIpAndLastAccessedAt(): void
    {
        $token = new AccessToken();

        // 记录更新前的时间点
        $beforeUpdate = new \DateTimeImmutable();

        // 更新访问信息，传入IP
        $token->updateAccessInfo('192.168.1.1');

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessedAt());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = $currentTime->getTimestamp() - $token->getLastAccessedAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($accessedDiff));

        // IP应已更新
        $this->assertEquals('192.168.1.1', $token->getLastIp());
    }

    public function testCreate_shouldReturnNewAccessTokenInstance(): void
    {
        $user = $this->createMock(UserInterface::class);
        $deviceInfo = 'test_device';

        // 记录创建前的时间点
        $beforeCreate = new \DateTimeImmutable();

        // 创建新令牌，有效期3600秒（1小时）
        $token = AccessToken::create($user, 3600, $deviceInfo);

        // 检查返回的实例类型
        $this->assertInstanceOf(AccessToken::class, $token);

        // 检查用户是否正确设置
        $this->assertSame($user, $token->getUser());

        // 检查设备信息是否正确设置
        $this->assertEquals($deviceInfo, $token->getDeviceInfo());

        // 检查令牌值是否已生成（64个字符的十六进制字符串）
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token->getToken());

        // 检查创建时间是否已设置，并约等于当前时间
        $this->assertNotNull($token->getCreatedAt());
        $createdDiff = $beforeCreate->getTimestamp() - $token->getCreatedAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($createdDiff));

        // 检查过期时间是否已设置，并约等于当前时间+3600秒
        $this->assertNotNull($token->getExpiresAt());
        $expectedExpiry = (new \DateTimeImmutable())->modify('+3600 seconds');
        $expiryDiff = $expectedExpiry->getTimestamp() - $token->getExpiresAt()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($expiryDiff));

        // 检查令牌是否默认有效
        $this->assertTrue($token->isValid());

        // 检查最后访问时间和最后IP为null
        $this->assertNull($token->getLastAccessedAt());
        $this->assertNull($token->getLastIp());
    }

    public function testCreate_withNullDeviceInfo_shouldCreateTokenWithoutDeviceInfo(): void
    {
        $user = $this->createMock(UserInterface::class);

        // 创建新令牌，不传设备信息
        $token = AccessToken::create($user, 3600);

        // 检查设备信息为null
        $this->assertNull($token->getDeviceInfo());
    }
}
