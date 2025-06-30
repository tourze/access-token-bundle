<?php

namespace AccessTokenBundle\Controller;

use AccessTokenBundle\Service\AccessTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class RevokeTokenController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService
    ) {
    }

    #[Route(path: '/api/token/revoke/{id}', name: 'api_revoke_token', methods: ['POST'])]
    public function __invoke(int $id, #[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if ($user === null) {
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
}