<?php

namespace Tourze\AccessTokenBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(AccessToken::class)]
final class AccessTokenTest extends AbstractEntityTestCase
{
    public function testToStringShouldReturnTokenValue(): void
    {
        $token = new AccessToken();
        $token->setToken('test_token_value');

        $this->assertEquals('test_token_value', (string) $token);
    }

    public function testToStringWithNullTokenShouldReturnEmptyString(): void
    {
        $token = new AccessToken();

        $this->assertEquals('', (string) $token);
    }

    public function testIsExpiredWithFutureDateShouldReturnFalse(): void
    {
        $token = new AccessToken();
        $futureDate = new \DateTimeImmutable('+1 day');
        $token->setExpireTime($futureDate);

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredWithPastDateShouldReturnTrue(): void
    {
        $token = new AccessToken();
        $pastDate = new \DateTimeImmutable('-1 day');
        $token->setExpireTime($pastDate);

        $this->assertTrue($token->isExpired());
    }

    public function testExtendShouldUpdateExpireTimeAndLastAccessTime(): void
    {
        $token = new AccessToken();
        // 设置一个未来的过期时间
        $initialExpireTime = (new \DateTimeImmutable())->modify('+1 hour');
        $token->setExpireTime($initialExpireTime);

        // 扩展令牌有效期3600秒（1小时）
        $token->extend(3600);

        // 确保过期时间已更新（应该比初始时间晚1小时）
        $this->assertNotSame($initialExpireTime, $token->getExpireTime());
        $expectedExpiry = $initialExpireTime->modify('+3600 seconds');
        $expireTime = $token->getExpireTime();
        $this->assertNotNull($expireTime);
        $diff = abs($expectedExpiry->getTimestamp() - $expireTime->getTimestamp());
        $this->assertLessThanOrEqual(1, $diff); // 允许1秒误差

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessTime());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = abs($currentTime->getTimestamp() - $token->getLastAccessTime()->getTimestamp());
        $this->assertLessThanOrEqual(1, $accessedDiff);
    }

    public function testExtendWithExpiredTokenShouldExtendFromCurrentTime(): void
    {
        $token = new AccessToken();
        // 设置一个过去的过期时间（已过期）
        $expiredTime = new \DateTimeImmutable('-1 hour');
        $token->setExpireTime($expiredTime);

        // 扩展令牌有效期3600秒（1小时）
        $token->extend(3600);

        // 过期时间应该从当前时间开始延长
        $expectedExpiry = (new \DateTimeImmutable())->modify('+3600 seconds');
        $expireTime = $token->getExpireTime();
        $this->assertNotNull($expireTime);
        $diff = abs($expectedExpiry->getTimestamp() - $expireTime->getTimestamp());
        $this->assertLessThanOrEqual(1, $diff); // 允许1秒误差
    }

    public function testUpdateAccessInfoShouldUpdateLastAccessTimeOnly(): void
    {
        $token = new AccessToken();

        // 记录更新前的时间点
        $beforeUpdate = new \DateTimeImmutable();

        // 更新访问信息，不传IP
        $token->updateAccessInfo();

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessTime());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = $currentTime->getTimestamp() - $token->getLastAccessTime()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($accessedDiff));

        // IP应为null
        $this->assertNull($token->getLastIp());
    }

    public function testUpdateAccessInfoWithIpShouldUpdateLastIpAndLastAccessTime(): void
    {
        $token = new AccessToken();

        // 记录更新前的时间点
        $beforeUpdate = new \DateTimeImmutable();

        // 更新访问信息，传入IP
        $token->updateAccessInfo('192.168.1.1');

        // 确保最后访问时间已更新
        $this->assertNotNull($token->getLastAccessTime());

        // 最后访问时间应约等于当前时间
        $currentTime = new \DateTimeImmutable();
        $accessedDiff = $currentTime->getTimestamp() - $token->getLastAccessTime()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($accessedDiff));

        // IP应已更新
        $this->assertEquals('192.168.1.1', $token->getLastIp());
    }

    public function testCreateShouldReturnNewAccessTokenInstance(): void
    {
        $user = new InMemoryUser('test@example.com', null);
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
        $tokenValue = $token->getToken();
        $this->assertNotNull($tokenValue);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $tokenValue);

        // 检查创建时间是否已设置，并约等于当前时间
        $this->assertNotNull($token->getCreateTime());
        $createdDiff = $beforeCreate->getTimestamp() - $token->getCreateTime()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($createdDiff));

        // 检查过期时间是否已设置，并约等于当前时间+3600秒
        $this->assertNotNull($token->getExpireTime());
        $expectedExpiry = new \DateTimeImmutable();
        $expectedExpiry = $expectedExpiry->modify('+3600 seconds');
        $expiryDiff = $expectedExpiry->getTimestamp() - $token->getExpireTime()->getTimestamp();
        $this->assertLessThanOrEqual(1, abs($expiryDiff));

        // 检查令牌是否默认有效
        $this->assertTrue($token->isValid());

        // 检查最后访问时间和最后IP为null
        $this->assertNull($token->getLastAccessTime());
        $this->assertNull($token->getLastIp());
    }

    public function testCreateWithNullDeviceInfoShouldCreateTokenWithoutDeviceInfo(): void
    {
        $user = new InMemoryUser('test@example.com', null);

        // 创建新令牌，不传设备信息
        $token = AccessToken::create($user, 3600);

        // 检查设备信息为null
        $this->assertNull($token->getDeviceInfo());
    }

    /**
     * 创建被测实体的一个实例.
     */
    protected function createEntity(): object
    {
        return new AccessToken();
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'token' => ['token', 'test_token_value'];
        // yield 'user' => ['user', null]; // User 属性需要 UserInterface 实例，在自动测试中跳过
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2023-01-01 12:00:00')];
        yield 'expireTime' => ['expireTime', new \DateTimeImmutable('2023-01-02 12:00:00')];
        yield 'lastAccessTime' => ['lastAccessTime', new \DateTimeImmutable('2023-01-01 13:00:00')];
        yield 'deviceInfo' => ['deviceInfo', 'test_device'];
        yield 'lastIp' => ['lastIp', '127.0.0.1'];
        yield 'valid' => ['valid', true];
    }
}
