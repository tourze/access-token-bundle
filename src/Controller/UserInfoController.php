<?php

namespace Tourze\AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class UserInfoController extends AbstractController
{
    #[Route(path: '/api/user', name: 'api_user_info', methods: ['GET', 'HEAD', 'OPTIONS', 'POST'])]
    public function __invoke(Request $request, #[CurrentUser] ?UserInterface $user): Response
    {
        if ('OPTIONS' === $request->getMethod()) {
            $response = new Response();
            $response->headers->set('Allow', 'GET, HEAD, OPTIONS, POST');

            return $response;
        }

        if (null === $user) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        if ('POST' === $request->getMethod()) {
            // POST method might be used for profile updates in the future
            return $this->json(['error' => 'Not implemented'], Response::HTTP_NOT_IMPLEMENTED);
        }

        return $this->json([
            'identifier' => $user->getUserIdentifier(),
        ]);
    }
}
