<?php

namespace Tourze\AccessTokenBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tourze\AccessTokenBundle\Controller\UserInfoController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(UserInfoController::class)]
#[RunTestsInSeparateProcesses]
final class UserInfoControllerTest extends AbstractWebTestCase
{
    public function testGetUserInfoWithAuthenticatedUser(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 发起 HTTP 请求
        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('identifier', $data);
        $this->assertEquals('test@example.com', $data['identifier']);
    }

    public function testGetUserInfoWithoutAuthentication(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);  // 启用异常捕获，将异常转换为 HTTP 响应

        // 不登录直接访问，Security 层返回 302 重定向到登录页（form_login Entry Point）
        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseRedirects('/login', Response::HTTP_FOUND);
    }

    public function testGetUserInfoResponseStructure(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */

        // 确保响应只包含预期的字段
        $expectedKeys = ['identifier'];
        $actualKeys = array_keys($data);

        $this->assertEquals($expectedKeys, $actualKeys);
    }

    public function testGetUserInfoWithDifferentUsers(): void
    {
        // 测试第一个用户
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'user1@example.com');
        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $content1 = $client->getResponse()->getContent();
        $this->assertIsString($content1);
        $data1 = json_decode($content1, true);
        $this->assertIsArray($data1);
        /** @var array<string, mixed> $data1 */
        $this->assertEquals('user1@example.com', $data1['identifier']);
    }

    public function testGetUserInfoWithAnotherUser(): void
    {
        // 测试第二个用户（单独的测试方法避免内核重启问题）
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'user2@example.com');
        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $content2 = $client->getResponse()->getContent();
        $this->assertIsString($content2);
        $data2 = json_decode($content2, true);
        $this->assertIsArray($data2);
        /** @var array<string, mixed> $data2 */
        $this->assertEquals('user2@example.com', $data2['identifier']);
    }

    public function testGetUserInfoResponseHeaders(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        // 验证响应头
        $response = $client->getResponse();
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testGetUserInfoWithDifferentHttpMethods(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 测试 POST 方法 - 返回 501 Not Implemented
        $client->request('POST', '/api/user');

        self::getClient($client);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_IMPLEMENTED);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertEquals(['error' => 'Not implemented'], $data);
    }

    public function testGetUserInfoAfterLogout(): void
    {
        $client = self::createClientWithDatabase();

        // 先登录
        $this->loginAsUser($client, 'test@example.com');
        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        // 模拟登出：先关闭当前内核，然后创建新客户端（等价于"换了个浏览器"，没有会话）
        $client->getKernel()->shutdown();
        static::ensureKernelShutdown();  // 确保内核完全关闭

        $newClient = self::createClientWithDatabase();
        $newClient->catchExceptions(true);  // 启用异常捕获，将异常转换为 HTTP 响应

        // 未登录的客户端访问应该返回 302 重定向到登录页（form_login Entry Point）
        $newClient->request('GET', '/api/user');

        self::getClient($newClient);
        $this->assertResponseRedirects('/login', Response::HTTP_FOUND);
    }

    public function testGetUserInfoResponseIsValidJson(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/user');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        // 验证响应是有效的 JSON
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);

        // 验证 JSON 解析不会出错
        $data = json_decode($content, true);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);

        match ($method) {
            'PUT' => $client->request('PUT', '/api/user'),
            'DELETE' => $client->request('DELETE', '/api/user'),
            'PATCH' => $client->request('PATCH', '/api/user'),
            'TRACE' => $client->request('TRACE', '/api/user'),
            'PURGE' => $client->request('PURGE', '/api/user'),
            default => self::fail("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * 测试 GET 方法为 HEAD 对比做准备
     */
    public function testGetMethodForHeadComparison(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/user');

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

        $client->request('HEAD', '/api/user');

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

        $client->request('OPTIONS', '/api/user');

        self::getClient($client);
        $response = $client->getResponse();
        $this->assertResponseIsSuccessful();

        $allowHeader = $response->headers->get('Allow');
        $this->assertNotNull($allowHeader);
        $this->assertStringContainsString('GET', $allowHeader);
        $this->assertStringContainsString('HEAD', $allowHeader);
        $this->assertStringContainsString('OPTIONS', $allowHeader);
        $this->assertStringContainsString('POST', $allowHeader);
    }

    /**
     * 测试 POST 方法支持用户资料更新
     */
    public function testPostMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // POST method might be used for profile updates
        $client->request('POST', '/api/user', [
            'name' => 'Updated Name',
        ]);

        self::getClient($client);
        $response = $client->getResponse();
        // Expecting NOT_IMPLEMENTED for POST method
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_IMPLEMENTED);
    }
}
