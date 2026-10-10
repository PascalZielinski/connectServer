<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\OperationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->registerRequest($request->toArray()));
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->loginRequest($request->toArray()));
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(AuthService $authService): JsonResponse
    {
        return $this->response($authService->currentUser($this->getUser()));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}
