<?php

namespace AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class UserInfoController extends AbstractController
{
    #[Route('/api/user', name: 'api_user_info', methods: ['GET'])]
    public function __invoke(#[CurrentUser] ?UserInterface $user): JsonResponse
    {
        if ($user === null) {
            return $this->json(['error' => '未授权访问'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'identifier' => $user->getUserIdentifier(),
        ]);
    }
}