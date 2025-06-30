<?php

namespace AccessTokenBundle\Tests\Controller;

use AccessTokenBundle\Controller\ListTokensController;
use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class ListTokensControllerTest extends TestCase
{
    private AccessTokenService $accessTokenService;
    private ListTokensController $controller;

    protected function setUp(): void
    {
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->controller = new ListTokensController($this->accessTokenService);
        
        // Set up a mock container to avoid the "has() on null" error
        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testInvoke_withAuthenticatedUser_returnsTokensList(): void
    {
        $user = $this->createMock(UserInterface::class);
        
        $token1 = $this->createMock(AccessToken::class);
        $token1->expects($this->once())->method('getId')->willReturn(1);
        $token1->expects($this->once())->method('getToken')->willReturn('token1234567890');
        $token1->expects($this->once())->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2023-01-01 10:00:00'));
        $token1->expects($this->once())->method('getExpiresAt')->willReturn(new \DateTimeImmutable('2023-01-02 10:00:00'));
        $token1->expects($this->once())->method('getLastAccessedAt')->willReturn(new \DateTimeImmutable('2023-01-01 15:00:00'));
        $token1->expects($this->once())->method('getDeviceInfo')->willReturn('Chrome Browser');

        $token2 = $this->createMock(AccessToken::class);
        $token2->expects($this->once())->method('getId')->willReturn(2);
        $token2->expects($this->once())->method('getToken')->willReturn('token0987654321');
        $token2->expects($this->once())->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2023-01-03 10:00:00'));
        $token2->expects($this->once())->method('getExpiresAt')->willReturn(new \DateTimeImmutable('2023-01-04 10:00:00'));
        $token2->expects($this->once())->method('getLastAccessedAt')->willReturn(null);
        $token2->expects($this->once())->method('getDeviceInfo')->willReturn(null);

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([$token1, $token2]);

        $response = $this->controller->__invoke($user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data);
        
        $this->assertEquals(1, $data[0]['id']);
        $this->assertEquals('token123...', $data[0]['token']);
        $this->assertEquals('2023-01-01 10:00:00', $data[0]['createdAt']);
        $this->assertEquals('2023-01-02 10:00:00', $data[0]['expiresAt']);
        $this->assertEquals('2023-01-01 15:00:00', $data[0]['lastAccessedAt']);
        $this->assertEquals('Chrome Browser', $data[0]['deviceInfo']);

        $this->assertEquals(2, $data[1]['id']);
        $this->assertEquals('token098...', $data[1]['token']);
        $this->assertEquals('2023-01-03 10:00:00', $data[1]['createdAt']);
        $this->assertEquals('2023-01-04 10:00:00', $data[1]['expiresAt']);
        $this->assertNull($data[1]['lastAccessedAt']);
        $this->assertNull($data[1]['deviceInfo']);
    }

    public function testInvoke_withNoUser_returnsUnauthorizedResponse(): void
    {
        $response = $this->controller->__invoke(null);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => '未授权访问'], $data);
    }

    public function testInvoke_withNoTokens_returnsEmptyArray(): void
    {
        $user = $this->createMock(UserInterface::class);

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([]);

        $response = $this->controller->__invoke($user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }
}