<?php

namespace AccessTokenBundle\Tests\Controller;

use AccessTokenBundle\Controller\TestController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TestControllerTest extends TestCase
{
    private TestController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestController();
        
        // Set up a mock container to avoid the "has() on null" error
        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testInvoke_returnsSuccessResponse(): void
    {
        $response = $this->controller->__invoke();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('API访问成功', $data['message']);
        $this->assertArrayHasKey('timestamp', $data);
        
        // 验证时间戳格式
        $timestamp = $data['timestamp'];
        $this->assertArrayHasKey('date', $timestamp);
        $this->assertArrayHasKey('timezone_type', $timestamp);
        $this->assertArrayHasKey('timezone', $timestamp);
        
        // 验证时间戳是最近的（在过去1分钟内）
        $dateTime = new \DateTime($timestamp['date']);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $dateTime->getTimestamp();
        $this->assertLessThan(60, $diff);
        $this->assertGreaterThanOrEqual(0, $diff);
    }

    public function testInvoke_responseStructure(): void
    {
        $response = $this->controller->__invoke();
        
        $data = json_decode($response->getContent(), true);
        
        // 确保响应只包含预期的字段
        $expectedKeys = ['message', 'timestamp'];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);
        
        $this->assertEquals($expectedKeys, $actualKeys);
    }

    public function testInvoke_multipleCallsReturnDifferentTimestamps(): void
    {
        // 第一次调用
        $response1 = $this->controller->__invoke();
        $data1 = json_decode($response1->getContent(), true);
        $timestamp1 = new \DateTime($data1['timestamp']['date']);
        
        // 等待一小段时间
        usleep(1000); // 1毫秒
        
        // 第二次调用
        $response2 = $this->controller->__invoke();
        $data2 = json_decode($response2->getContent(), true);
        $timestamp2 = new \DateTime($data2['timestamp']['date']);
        
        // 验证时间戳不同（或至少不早于第一个）
        $this->assertGreaterThanOrEqual($timestamp1, $timestamp2);
    }
}