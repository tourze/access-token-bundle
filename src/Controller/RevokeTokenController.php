<?php

namespace Tourze\AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Tourze\AccessTokenBundle\Service\AccessTokenService;

final class RevokeTokenController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
    ) {
    }

    #[Route(path: '/api/token/revoke/{id}', name: 'api_revoke_token', methods: ['POST', 'HEAD', 'OPTIONS'])]
    public function __invoke(Request $request, int $id, #[CurrentUser] ?UserInterface $user): Response
    {
        if ('OPTIONS' === $request->getMethod()) {
            $response = new Response();
            $response->headers->set('Allow', 'POST, HEAD, OPTIONS');

            return $response;
        }

        if ('HEAD' === $request->getMethod()) {
            return new Response();
        }

        if (null === $user) {
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
