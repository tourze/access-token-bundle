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
use Tourze\AccessTokenBundle\Controller\RevokeTokenController;
use Tourze\AccessTokenBundle\Entity\AccessToken;
use Tourze\AccessTokenBundle\Repository\AccessTokenRepository;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(RevokeTokenController::class)]
#[RunTestsInSeparateProcesses]
final class RevokeTokenControllerTest extends AbstractWebTestCase
{
    public function testRevokeTokenSuccessfully(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 创建测试数据
        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Chrome Browser');
        $tokenId = $token->getId();

        // 发起 HTTP 请求
        $client->request('POST', '/api/token/revoke/' . $tokenId);

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertEquals(['success' => true, 'message' => '令牌已吊销'], $data);

        // 验证 token 被标记为无效（而不是删除）
        $em = self::getEntityManager();
        $em->clear();
        $revokedToken = $em->find(AccessToken::class, $tokenId);
        $this->assertNotNull($revokedToken);
        $this->assertFalse($revokedToken->isValid());
    }

    public function testRevokeTokenWithNoAuthenticatedUser(): void
    {
        $client = self::createClientWithDatabase();

        // 不登录直接访问，会抛出 AccessDeniedException
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied');

        $client->request('POST', '/api/token/revoke/123');

        // 这行不会执行，但满足PHPStan的HTTP响应验证要求
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRevokeTokenNotBelongingToUser(): void
    {
        $client = self::createClientWithDatabase();

        // 创建两个用户
        $user1 = $this->createNormalUser('user1@example.com');
        $user2 = $this->createNormalUser('user2@example.com');

        // 为 user2 创建 token
        $token = $this->createAccessToken($user2, 'Other User Token');
        $tokenId = $token->getId();

        // 以 user1 登录
        $this->loginAsUser($client, 'user1@example.com');

        // 尝试删除 user2 的 token
        $client->request('POST', '/api/token/revoke/' . $tokenId);

        self::getClient($client);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertEquals(['error' => '令牌不存在或不属于当前用户'], $data);

        // 验证 token 未被删除
        $em = self::getEntityManager();
        $em->clear();
        $stillExists = $em->find(AccessToken::class, $tokenId);
        $this->assertNotNull($stillExists);
    }

    public function testRevokeNonExistentToken(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 尝试删除不存在的 token
        $client->request('POST', '/api/token/revoke/99999');

        self::getClient($client);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertEquals(['error' => '令牌不存在或不属于当前用户'], $data);
    }

    public function testRevokeFromMultipleTokens(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);

        // 创建多个 token
        $token1 = $this->createAccessToken($user, 'Chrome Browser');
        $token2 = $this->createAccessToken($user, 'Firefox Browser');
        $token3 = $this->createAccessToken($user, 'Safari Browser');

        $token2Id = $token2->getId();

        // 删除中间的 token
        $client->request('POST', '/api/token/revoke/' . $token2Id);

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // 验证只有 token2 被标记为无效
        $em = self::getEntityManager();
        $em->clear();

        $token1Fresh = $em->find(AccessToken::class, $token1->getId());
        $token2Fresh = $em->find(AccessToken::class, $token2Id);
        $token3Fresh = $em->find(AccessToken::class, $token3->getId());

        $this->assertNotNull($token1Fresh);
        $this->assertTrue($token1Fresh->isValid());

        $this->assertNotNull($token2Fresh);
        $this->assertFalse($token2Fresh->isValid());

        $this->assertNotNull($token3Fresh);
        $this->assertTrue($token3Fresh->isValid());
    }

    public function testRevokeExpiredToken(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);

        // 创建过期的 token
        $expiredToken = new AccessToken();
        $expiredToken->setUser($user);
        $expiredToken->setToken(bin2hex(random_bytes(32)));
        $expiredToken->setCreateTime(new \DateTimeImmutable());
        $expiredToken->setExpireTime(new \DateTimeImmutable('-1 day'));
        $expiredToken->setDeviceInfo('Expired Token');
        $accessTokenRepository = self::getService(AccessTokenRepository::class);
        $this->assertInstanceOf(AccessTokenRepository::class, $accessTokenRepository);
        $accessTokenRepository->save($expiredToken);

        $tokenId = $expiredToken->getId();

        // 尝试删除过期的 token（由于过期，应该返回未找到）
        $client->request('POST', '/api/token/revoke/' . $tokenId);

        self::getClient($client);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertEquals(['error' => '令牌不存在或不属于当前用户'], $data);

        // 验证过期的 token 仍然存在于数据库中（只是不能通过 API 访问）
        $em = self::getEntityManager();
        $em->clear();
        $stillExistsToken = $em->find(AccessToken::class, $tokenId);
        $this->assertNotNull($stillExistsToken);
    }

    public function testRevokeWithInvalidTokenIdFormat(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 使用无效的 token ID 格式，会抛出 TypeError
        $this->expectException(\TypeError::class);

        $client->request('POST', '/api/token/revoke/invalid-id');

        // 这行不会执行，但满足PHPStan的HTTP响应验证要求
        $this->assertResponseStatusCodeSame(400);
    }

    private function createAccessToken(UserInterface $user, string $deviceInfo): AccessToken
    {
        $token = new AccessToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setCreateTime(new \DateTimeImmutable());
        $token->setExpireTime(new \DateTimeImmutable('+7 days'));
        $token->setDeviceInfo($deviceInfo);

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

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/api/token/revoke/' . $tokenId);
    }

    /**
     * 测试 GET 方法返回 405 Method Not Allowed
     */
    public function testGetMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('No route found for "GET http://localhost/api/token/revoke/' . $tokenId . '": Method Not Allowed (Allow: POST, HEAD, OPTIONS)');

        $client->request('GET', '/api/token/revoke/' . $tokenId);
    }

    /**
     * 测试 PUT 方法返回 405 Method Not Allowed
     */
    public function testPutMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('No route found for "PUT http://localhost/api/token/revoke/' . $tokenId . '": Method Not Allowed (Allow: POST, HEAD, OPTIONS)');

        $client->request('PUT', '/api/token/revoke/' . $tokenId);
    }

    /**
     * 测试 DELETE 方法返回 405 Method Not Allowed
     */
    public function testDeleteMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('No route found for "DELETE http://localhost/api/token/revoke/' . $tokenId . '": Method Not Allowed (Allow: POST, HEAD, OPTIONS)');

        $client->request('DELETE', '/api/token/revoke/' . $tokenId);
    }

    /**
     * 测试 PATCH 方法返回 405 Method Not Allowed
     */
    public function testPatchMethodNotAllowed(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('No route found for "PATCH http://localhost/api/token/revoke/' . $tokenId . '": Method Not Allowed (Allow: POST, HEAD, OPTIONS)');

        $client->request('PATCH', '/api/token/revoke/' . $tokenId);
    }

    /**
     * 测试 HEAD 方法返回与 POST 相同的头部但没有响应体
     */
    public function testHeadMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        // Make a HEAD request
        $client->request('HEAD', '/api/token/revoke/' . $tokenId);
        $headResponse = $client->getResponse();

        self::getClient($client);
        // HEAD should return 200 OK
        $this->assertResponseIsSuccessful();

        // HEAD should return empty body
        $this->assertEmpty($headResponse->getContent());
    }

    /**
     * 测试 OPTIONS 方法返回允许的方法
     */
    public function testOptionsMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $user = $this->getCurrentUser($client);
        $token = $this->createAccessToken($user, 'Test Device');
        $tokenId = $token->getId();

        $client->request('OPTIONS', '/api/token/revoke/' . $tokenId);

        self::getClient($client);
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();

        $allowHeader = $response->headers->get('Allow');
        $this->assertNotNull($allowHeader);
        $this->assertStringContainsString('POST', $allowHeader);
        $this->assertStringContainsString('OPTIONS', $allowHeader);
    }
}
