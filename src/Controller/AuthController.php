<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, AuthService $authService): JsonResponse
    {
        $data = $request->toArray();
        if (
            !isset($data['email'], $data['password'], $data['password_confirm'])
            || !is_string($data['email'])
            || !is_string($data['password'])
            || !is_string($data['password_confirm'])
        ) {
            return $this->json(
                ['error' => 'Email, password, and password_confirm are required as strings.'],
                JsonResponse::HTTP_BAD_REQUEST,
            );
        }

        return $this->json(
            $authService->register($data['email'], $data['password'], $data['password_confirm']),
            JsonResponse::HTTP_CREATED,
        );
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService): JsonResponse
    {
        $data = $request->toArray();
        if (!isset($data['email'], $data['password']) || !is_string($data['email']) || !is_string($data['password'])) {
            return $this->json(['error' => 'Email and password are required.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($authService->login($data['email'], $data['password']));
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'id' => $user->getId(),
            'uuid' => $user->getUuid(),
            'email' => $user->getEmail(),
            'created_at' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
