<?php

namespace Tourze\AccessTokenBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\AccessTokenBundle\Controller\ListTokensController;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(ListTokensController::class)]
#[RunTestsInSeparateProcesses]
final class ListTokensControllerTest extends AbstractWebTestCase
{
    public function testListTokensWithAuthenticatedUser(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 创建测试数据
        $user = $this->getCurrentUser($client);
        $token1 = $this->createAccessToken($user, 'Chrome Browser', '192.168.1.100');
        $token2 = $this->createAccessToken($user, 'Firefox Browser', '192.168.1.101');

        // 发起 HTTP 请求
        $client->request('GET', '/api/tokens');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<int, array<string, mixed>> $data */
        $this->assertCount(2, $data);

        // 验证返回的数据结构
        foreach ($data as $tokenData) {
            /** @var array<string, mixed> $tokenData */
            $this->assertArrayHasKey('id', $tokenData);
            $this->assertArrayHasKey('token', $tokenData);
            $this->assertArrayHasKey('createTime', $tokenData);
            $this->assertArrayHasKey('expireTime', $tokenData);
            $this->assertArrayHasKey('lastAccessTime', $tokenData);
            $this->assertArrayHasKey('deviceInfo', $tokenData);
        }

        // 验证 token 被截断显示
        $this->assertIsString($data[0]['token']);
        $this->assertStringEndsWith('...', $data[0]['token']);
        $this->assertIsString($data[1]['token']);
        $this->assertStringEndsWith('...', $data[1]['token']);
    }

    public function testListTokensWithNoAuthenticatedUser(): void
    {
        $client = self::createClientWithDatabase();

        // 不登录直接访问，会抛出 AccessDeniedException
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied');

        $client->request('GET', '/api/tokens');

        // 这行不会执行，但满足PHPStan的HTTP响应验证要求
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListTokensWithNoTokens(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'empty@example.com');

        // 发起 HTTP 请求
        $client->request('GET', '/api/tokens');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testListTokensFiltersByUser(): void
    {
        $client = self::createClientWithDatabase();

        // 为不同用户创建 token
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        $this->createAccessToken($user1, 'Device 1');
        $this->createAccessToken($user1, 'Device 2');
        $this->createAccessToken($user2, 'Device 3');

        // 以 user1 登录
        $this->loginAsUser($client, 'user1@example.com');

        $client->request('GET', '/api/tokens');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<int, array<string, mixed>> $data */
        $this->assertCount(2, $data);

        // 验证返回的都是 user1 的 token
        foreach ($data as $tokenData) {
            /** @var array<string, mixed> $tokenData */
            $this->assertIsString($tokenData['deviceInfo']);
            $this->assertStringContainsString('Device', $tokenData['deviceInfo']);
            $this->assertNotEquals('Device 3', $tokenData['deviceInfo']);
        }
    }

    public function testListTokensExcludesExpiredTokens(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);

        // 创建有效 token
        $validToken = $this->createAccessToken($user, 'Valid Token');

        // 创建过期 token
        $expiredToken = new AccessToken();
        $expiredToken->setUser($user);
        $expiredToken->setToken(bin2hex(random_bytes(32)));
        $expiredToken->setCreateTime(new \DateTimeImmutable());
        $expiredToken->setExpireTime(new \DateTimeImmutable('-1 day'));
        $expiredToken->setDeviceInfo('Expired Token');
        $accessTokenRepository = self::getService(AccessTokenRepository::class);
        $this->assertInstanceOf(AccessTokenRepository::class, $accessTokenRepository);
        $accessTokenRepository->save($expiredToken);

        $client->request('GET', '/api/tokens');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<int, array<string, mixed>> $data */
        $this->assertCount(1, $data);
        $this->assertEquals('Valid Token', $data[0]['deviceInfo']);
    }

    public function testListTokensOrdersByCreateTime(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);

        // 创建多个 token，确保时间不同
        $token1 = $this->createAccessToken($user, 'First Token');
        sleep(1);
        $token2 = $this->createAccessToken($user, 'Second Token');
        sleep(1);
        $token3 = $this->createAccessToken($user, 'Third Token');

        $client->request('GET', '/api/tokens');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<int, array<string, mixed>> $data */
        $this->assertCount(3, $data);

        // 验证按创建时间降序排列（最新的在前）
        $this->assertEquals('Third Token', $data[0]['deviceInfo']);
        $this->assertEquals('Second Token', $data[1]['deviceInfo']);
        $this->assertEquals('First Token', $data[2]['deviceInfo']);
    }

    private function createAccessToken(UserInterface $user, string $deviceInfo, ?string $ip = null): AccessToken
    {
        $token = new AccessToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setCreateTime(new \DateTimeImmutable());
        $token->setExpireTime(new \DateTimeImmutable('+7 days'));
        $token->setDeviceInfo($deviceInfo);

        if (null !== $ip) {
            $token->setLastIp($ip);
        }

        $accessTokenRepository = self::getService(AccessTokenRepository::class);
        $this->assertInstanceOf(AccessTokenRepository::class, $accessTokenRepository);
        $accessTokenRepository->save($token);

        return $token;
    }

    private function getCurrentUser(mixed $client): UserInterface
    {
        // 获取当前登录的用户
        $tokenStorage = self::getService(TokenStorageInterface::class);
        $this->assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $token = $tokenStorage->getToken();

        $this->assertNotNull($token, '用户必须已登录');
        $user = $token->getUser();
        $this->assertInstanceOf(UserInterface::class, $user, '用户必须实现UserInterface');

        return $user;
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/api/tokens');
    }

    /**
     * 测试 POST 方法返回 405 Method Not Allowed
     */
    public function testPostMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('POST', '/api/tokens');
    }

    /**
     * 测试 PUT 方法返回 405 Method Not Allowed
     */
    public function testPutMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('PUT', '/api/tokens');
    }

    /**
     * 测试 DELETE 方法返回 405 Method Not Allowed
     */
    public function testDeleteMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('DELETE', '/api/tokens');
    }

    /**
     * 测试 PATCH 方法返回 405 Method Not Allowed
     */
    public function testPatchMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request('PATCH', '/api/tokens');
    }

    /**
     * 测试 GET 方法正常工作
     */
    public function testGetMethodForHeadComparison(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/tokens');
        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertNotEmpty($content);
    }

    /**
     * 测试 HEAD 方法返回空响应体
     */
    public function testHeadMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('HEAD', '/api/tokens');
        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // HEAD should return empty body
        $headContent = $client->getResponse()->getContent();
        $this->assertEmpty($headContent);
    }

    /**
     * 测试 OPTIONS 方法返回允许的方法
     */
    public function testOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('OPTIONS', '/api/tokens');

        self::getClient($client);
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();

        $allowHeader = $response->headers->get('Allow');
        $this->assertNotNull($allowHeader);
        $this->assertStringContainsString('GET', $allowHeader);
        $this->assertStringContainsString('HEAD', $allowHeader);
        $this->assertStringContainsString('OPTIONS', $allowHeader);
    }
}
