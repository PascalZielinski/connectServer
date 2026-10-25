<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\OperationResult;
use App\Service\TrustedDeviceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

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
            $this->deviceSecret($request),
            $request->getClientIp() ?? 'unknown',
            $request->headers->get('User-Agent'),
        ));
    }

    #[Route('/device/confirm', name: 'device_confirm_page', methods: ['GET', 'POST'])]
    public function confirmDevicePage(Request $request, TrustedDeviceService $service): Response
    {
        if (!$request->isMethod('POST')) {
            $response = $this->render('device/confirm_form.html.twig', [
                'token' => $request->query->getString('token'),
            ]);

            return $this->harden($response);
        }

        $token = $request->request->getString('token');
        $ok = $token !== '' && $service->confirm($token);

        $response = $this->render(
            'device/confirm_result.html.twig',
            ['success' => $ok],
            new Response(status: $ok ? 200 : 400),
        );

        return $this->harden($response);
    }

    private function harden(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
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

    #[Route('/api/verify-email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verify(
        Request $request,
        UserRepository $users,
        VerifyEmailHelperInterface $helper,
        EntityManagerInterface $em,
    ): Response {
        $user = $users->findOneBy(['uuid' => $request->query->getString('uuid')]);

        try {
            if ($user === null) {
                throw new BadRequestHttpException();
            }

            $helper->validateEmailConfirmationFromRequest(
                $request,
                $user->getUuid(),
                $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface | BadRequestHttpException) {
            return $this->harden($this->render(
                'email/verify_result.html.twig',
                ['success' => false],
                new Response(status: Response::HTTP_BAD_REQUEST),
            ));
        }

        if (!$request->isMethod('POST')) {
            return $this->harden($this->render('email/verify_form.html.twig', [
                'action' => $request->getRequestUri(),
            ]));
        }

        $user->setIsVerified(true);
        $user->setIsVerifiedExpiresAt(null);
        $em->flush();

        return $this->harden($this->render('email/verify_result.html.twig', ['success' => true]));
    }

    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->refreshRequest(
            $request->toArray(),
            $this->deviceSecret($request),
        ));
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        return $this->response($authService->logoutRequest($request->toArray()));
    }
}