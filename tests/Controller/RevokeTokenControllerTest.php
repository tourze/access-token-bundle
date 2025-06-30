<?php

namespace AccessTokenBundle\Tests\Controller;

use AccessTokenBundle\Controller\RevokeTokenController;
use AccessTokenBundle\Entity\AccessToken;
use AccessTokenBundle\Service\AccessTokenService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

class RevokeTokenControllerTest extends TestCase
{
    private AccessTokenService $accessTokenService;
    private RevokeTokenController $controller;

    protected function setUp(): void
    {
        $this->accessTokenService = $this->createMock(AccessTokenService::class);
        $this->controller = new RevokeTokenController($this->accessTokenService);
        
        // Set up a mock container to avoid the "has() on null" error
        $container = new Container();
        $this->controller->setContainer($container);
    }

    public function testInvoke_withValidTokenId_revokesTokenSuccessfully(): void
    {
        $user = $this->createMock(UserInterface::class);
        $tokenId = 123;

        $token = $this->createMock(AccessToken::class);
        $token->expects($this->once())->method('getId')->willReturn($tokenId);

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([$token]);

        $this->accessTokenService->expects($this->once())
            ->method('revokeToken')
            ->with($token);

        $response = $this->controller->__invoke($tokenId, $user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['success' => true, 'message' => '令牌已吊销'], $data);
    }

    public function testInvoke_withNoUser_returnsUnauthorizedResponse(): void
    {
        $response = $this->controller->__invoke(123, null);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => '未授权访问'], $data);
    }

    public function testInvoke_withTokenNotBelongingToUser_returnsNotFoundResponse(): void
    {
        $user = $this->createMock(UserInterface::class);
        $tokenId = 123;

        $otherToken = $this->createMock(AccessToken::class);
        $otherToken->expects($this->once())->method('getId')->willReturn(456);

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([$otherToken]);

        $this->accessTokenService->expects($this->never())
            ->method('revokeToken');

        $response = $this->controller->__invoke($tokenId, $user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => '令牌不存在或不属于当前用户'], $data);
    }

    public function testInvoke_withNoTokensForUser_returnsNotFoundResponse(): void
    {
        $user = $this->createMock(UserInterface::class);
        $tokenId = 123;

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([]);

        $this->accessTokenService->expects($this->never())
            ->method('revokeToken');

        $response = $this->controller->__invoke($tokenId, $user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['error' => '令牌不存在或不属于当前用户'], $data);
    }

    public function testInvoke_withMultipleTokens_revokesCorrectToken(): void
    {
        $user = $this->createMock(UserInterface::class);
        $targetTokenId = 123;

        $token1 = $this->createMock(AccessToken::class);
        $token1->expects($this->once())->method('getId')->willReturn(111);

        $token2 = $this->createMock(AccessToken::class);
        $token2->expects($this->once())->method('getId')->willReturn($targetTokenId);

        $token3 = $this->createMock(AccessToken::class);
        $token3->expects($this->never())->method('getId');

        $this->accessTokenService->expects($this->once())
            ->method('findTokensByUser')
            ->with($user)
            ->willReturn([$token1, $token2, $token3]);

        $this->accessTokenService->expects($this->once())
            ->method('revokeToken')
            ->with($token2);

        $response = $this->controller->__invoke($targetTokenId, $user);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(['success' => true, 'message' => '令牌已吊销'], $data);
    }
}