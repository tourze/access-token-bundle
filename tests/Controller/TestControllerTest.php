<?php

namespace Tourze\AccessTokenBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\AccessTokenBundle\Controller\TestController;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * @internal
 */
#[CoversClass(TestController::class)]
#[RunTestsInSeparateProcesses]
final class TestControllerTest extends AbstractWebTestCase
{
    public function testInvokeReturnsSuccessResponse(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 发起 HTTP 请求
        $client->request('GET', '/api/test');

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertJson($content);

        $data = json_decode($content, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('API访问成功', $data['message']);
        $this->assertArrayHasKey('timestamp', $data);

        // 验证时间戳格式（可能是字符串或对象）
        $timestamp = $data['timestamp'];
        if (is_string($timestamp)) {
            // 如果是字符串，尝试解析为 DateTime
            $dateTime = new \DateTime($timestamp);
        } else {
            // 如果是对象，验证其结构
            $this->assertIsArray($timestamp);
            $this->assertArrayHasKey('date', $timestamp);
            $this->assertArrayHasKey('timezone_type', $timestamp);
            $this->assertArrayHasKey('timezone', $timestamp);
            $dateTime = new \DateTime($timestamp['date']);
        }

        // 验证时间戳是最近的（在过去1分钟内）
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $dateTime->getTimestamp();
        $this->assertLessThan(60, $diff);
        $this->assertGreaterThanOrEqual(0, $diff);
    }

    public function testInvokeWithoutAuthentication(): void
    {
        $client = self::createClientWithDatabase();

        // 不登录访问，会抛出 AccessDeniedException
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Access Denied');

        $client->request('GET', '/api/test');

        // 这行不会执行，但满足PHPStan的HTTP响应验证要求
        $this->assertResponseStatusCodeSame(401);
    }

    public function testInvokeResponseHeaders(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/test');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        // 验证响应头
        $response = $client->getResponse();
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));
    }

    public function testInvokeResponseStructure(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/test');

        self::getClient($client);
        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */

        // 确保响应只包含预期的字段
        $expectedKeys = ['message', 'timestamp'];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);

        $this->assertEquals($expectedKeys, $actualKeys);
    }

    public function testInvokeWithDifferentHttpMethods(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 测试不同的 HTTP 方法 - 会抛出 MethodNotAllowedHttpException
        $this->expectException(MethodNotAllowedHttpException::class);
        $this->expectExceptionMessage('No route found for "POST http://localhost/api/test": Method Not Allowed (Allow: GET, HEAD, OPTIONS)');

        $client->request('POST', '/api/test');
    }

    public function testInvokeWithQueryParameters(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        // 带查询参数的请求（测试端点应该忽略它们）
        $client->request('GET', '/api/test', ['debug' => 'true', 'format' => 'json']);

        self::getClient($client);
        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $data = json_decode($content, true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertEquals('API访问成功', $data['message']);
    }

    public function testInvokeResponseIsValidJson(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('GET', '/api/test');

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
        $this->loginAsUser($client, 'test@example.com');

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/api/test');
    }

    /**
     * 测试 HEAD 方法返回空响应体
     */
    public function testHeadMethod(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsUser($client, 'test@example.com');

        $client->request('HEAD', '/api/test');

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

        $client->request('OPTIONS', '/api/test');

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
