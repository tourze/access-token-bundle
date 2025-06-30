<?php

namespace AccessTokenBundle\Tests\Controller;

use AccessTokenBundle\Controller\UserInfoController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class UserInfoControllerTest extends TestCase
{
    private UserInfoController $controller;

    protected function setUp(): void
    {
        $this->controller = new UserInfoController();
        
        // Set up a mock container to avoid the "has() on null" error
        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testInvoke_withAuthenticatedUser_returnsUserInfo(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('test_user@example.com');

        $response = $this->controller->__invoke($user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['identifier' => 'test_user@example.com'], $data);
    }

    public function testInvoke_withNoUser_returnsUnauthorizedResponse(): void
    {
        $response = $this->controller->__invoke(null);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => '未授权访问'], $data);
    }

    public function testInvoke_withDifferentUserIdentifiers(): void
    {
        // 测试用户名标识符
        $user1 = $this->createMock(UserInterface::class);
        $user1->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('username123');

        $response1 = $this->controller->__invoke($user1);
        $data1 = json_decode($response1->getContent(), true);
        $this->assertEquals(['identifier' => 'username123'], $data1);

        // 测试邮箱标识符
        $user2 = $this->createMock(UserInterface::class);
        $user2->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('user@domain.com');

        $response2 = $this->controller->__invoke($user2);
        $data2 = json_decode($response2->getContent(), true);
        $this->assertEquals(['identifier' => 'user@domain.com'], $data2);

        // 测试数字标识符
        $user3 = $this->createMock(UserInterface::class);
        $user3->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('12345');

        $response3 = $this->controller->__invoke($user3);
        $data3 = json_decode($response3->getContent(), true);
        $this->assertEquals(['identifier' => '12345'], $data3);
    }

    public function testInvoke_responseStructure(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->expects($this->once())
            ->method('getUserIdentifier')
            ->willReturn('test_user');

        $response = $this->controller->__invoke($user);
        $data = json_decode($response->getContent(), true);
        
        // 确保响应只包含预期的字段
        $expectedKeys = ['identifier'];
        $actualKeys = array_keys($data);
        
        $this->assertEquals($expectedKeys, $actualKeys);
    }
}