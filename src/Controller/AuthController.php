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
        return $this->response($authService->loginRequest(
            $request->toArray(),
            $request->getClientIp() ?? 'unknown',
            $request->headers->get('User-Agent'),
        ));
    }

    #[Route('/device/confirm', name: 'device_confirm_page', methods: ['GET', 'POST'])]
    public function confirmDevicePage(Request $request, TrustedDeviceService $service): Response
    {
        $token = (string) ($request->query->get('token') ?? $request->request->get('token', ''));

        if ($request->isMethod('POST')) {
            $ok = $token !== '' && $service->confirm($token);

            return new Response($this->page(
                $ok ? 'Gerät bestätigt. Du kannst dich jetzt in der App anmelden.'
                    : 'Link ungültig oder abgelaufen. Melde dich erneut an, um eine neue Mail zu bekommen.'
            ), $ok ? 200 : 400);
        }

        $safe = htmlspecialchars($token, ENT_QUOTES);

        return new Response($this->page(
            '<p>Möchtest du dieses Gerät als vertrauenswürdig bestätigen?</p>'
            . '<form method="post"><input type="hidden" name="token" value="' . $safe . '">'
            . '<button type="submit">Gerät bestätigen</button></form>',
            true
        ));
    }

    private function page(string $body, bool $raw = false): string
    {
        $content = $raw ? $body : '<p>' . htmlspecialchars($body) . '</p>';

        return '<!doctype html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Gerät bestätigen</title></head><body style="font-family:sans-serif;max-width:420px;margin:4rem auto;">'
            . $content . '</body></html>';
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