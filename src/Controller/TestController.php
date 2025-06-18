<?php

namespace AccessTokenBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
    #[Route('/api/test', name: 'api_test', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'message' => 'API访问成功',
            'timestamp' => new \DateTime(),
        ]);
    }
}