<?php

namespace App\Controller;

use App\Service\AuthService;
use App\Service\OperationResult;
use App\Service\TrustedDeviceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->registerRequest(
            $request->toArray(),
            $this->deviceSecret($request),
            $request->headers->get('User-Agent'),
        ));
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->loginRequest(
            $request->toArray(),
            $this->deviceSecret($request),
            $request->headers->get('User-Agent'),
        ));
    }

    #[Route('/device/confirm', name: 'device_confirm_page', methods: ['GET', 'POST'])]
    public function confirmDevicePage(Request $request, TrustedDeviceService $service): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render('device/confirm_form.html.twig', [
                'token' => $request->query->getString('token'),
            ]);
        }

        $token = $request->request->getString('token');
        $ok = $token !== '' && $service->confirm($token);

        return $this->render(
            'device/confirm_result.html.twig',
            ['success' => $ok],
            new Response(status: $ok ? 200 : 400),
        );
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

    private function deviceSecret(Request $request): string
    {
        $secret = (string) $request->headers->get('X-Device-Secret', '');

        if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
            throw new BadRequestHttpException('Invalid device secret.');
        }

        return $secret;
    }
}