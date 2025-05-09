<?php

namespace AccessTokenBundle\Controller;

use AccessTokenBundle\Service\AccessTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService
    ) {
    }

    #[Route('/user', name: 'user_info', methods: ['GET'])]
    public function userInfo(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'identifier' => $user->getUserIdentifier(),
        ]);
    }

    #[Route('/tokens', name: 'list_tokens', methods: ['GET'])]
    public function listTokens(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        $tokens = $this->accessTokenService->findTokensByUser($user);
        $result = [];

        foreach ($tokens as $token) {
            $result[] = [
                'id' => $token->getId(),
                'token' => substr($token->getToken(), 0, 8) . '...',  // 只显示token的一部分
                'createdAt' => $token->getCreatedAt()->format('Y-m-d H:i:s'),
                'expiresAt' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
                'lastAccessedAt' => $token->getLastAccessedAt() ? $token->getLastAccessedAt()->format('Y-m-d H:i:s') : null,
                'deviceInfo' => $token->getDeviceInfo(),
            ];
        }

        return $this->json($result);
    }

    #[Route('/token/revoke/{id}', name: 'revoke_token', methods: ['POST'])]
    public function revokeToken(int $id, #[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        $tokens = $this->accessTokenService->findTokensByUser($user);
        $tokenFound = false;

        foreach ($tokens as $token) {
            if ($token->getId() === $id) {
                $this->accessTokenService->revokeToken($token);
                $tokenFound = true;
                break;
            }
        }

        if (!$tokenFound) {
            return $this->json(['error' => '令牌不存在或不属于当前用户'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['success' => true, 'message' => '令牌已吊销']);
    }

    #[Route('/test', name: 'test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        return $this->json([
            'message' => 'API访问成功',
            'timestamp' => new \DateTime(),
        ]);
    }
}
