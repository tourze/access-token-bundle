<?php

namespace AccessTokenBundle\Controller;

use AccessTokenBundle\Service\AccessTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ListTokensController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService
    ) {
    }

    #[Route(path: '/api/tokens', name: 'api_list_tokens', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if ($user === null) {
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
                'lastAccessedAt' => $token->getLastAccessedAt()?->format('Y-m-d H:i:s'),
                'deviceInfo' => $token->getDeviceInfo(),
            ];
        }

        return $this->json($result);
    }
}
