<?php

namespace Tourze\AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TestController extends AbstractController
{
    #[Route(path: '/api/test', name: 'api_test', methods: ['GET', 'HEAD', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        if ('OPTIONS' === $request->getMethod()) {
            $response = new Response();
            $response->headers->set('Allow', 'GET, HEAD, OPTIONS');

            return $response;
        }

        return $this->json([
            'message' => 'API访问成功',
            'timestamp' => new \DateTime(),
        ]);
    }
}
