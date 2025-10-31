<?php

namespace Tourze\AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Tourze\AccessTokenBundle\Service\AccessTokenService;

final class ListTokensController extends AbstractController
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
    ) {
    }

    #[Route(path: '/api/tokens', name: 'api_list_tokens', methods: ['GET', 'HEAD', 'OPTIONS'])]
    public function __invoke(Request $request, #[CurrentUser] ?UserInterface $user): Response
    {
        if ('OPTIONS' === $request->getMethod()) {
            $response = new Response();
            $response->headers->set('Allow', 'GET, HEAD, OPTIONS');

            return $response;
        }

        if (null === $user) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        $tokens = $this->accessTokenService->findTokensByUser($user);
        $result = [];

        foreach ($tokens as $token) {
            $tokenValue = $token->getToken();
            $result[] = [
                'id' => $token->getId(),
                'token' => null !== $tokenValue ? substr($tokenValue, 0, 8) . '...' : null,  // 只显示token的一部分
                'createTime' => $token->getCreateTime()?->format('Y-m-d H:i:s'),
                'expireTime' => $token->getExpireTime()?->format('Y-m-d H:i:s'),
                'lastAccessTime' => $token->getLastAccessTime()?->format('Y-m-d H:i:s'),
                'deviceInfo' => $token->getDeviceInfo(),
            ];
        }

        return $this->json($result);
    }
}
