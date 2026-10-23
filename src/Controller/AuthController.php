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
        $authService->registerRequest(
            $request->toArray(),
            $this->deviceSecret($request),
            $request->headers->get('User-Agent'),
        );

        return $this->json(
            ['message' => 'If this address can be registered, we have sent you an email.'],
            Response::HTTP_ACCEPTED,
        );
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

        #[Route('/api/verify-email', name: 'app_verify_email', methods: ['GET'])]
        public function verify(
            Request $request,
            UserRepository $users,
            VerifyEmailHelperInterface $helper,
            EntityManagerInterface $em,
        ): JsonResponse {
            $user = $users->find((int) $request->query->get('id'));

            if ($user === null) {
                return new JsonResponse(['message' => 'Invalid link.'], Response::HTTP_BAD_REQUEST);
            }

            try {
                $helper->validateEmailConfirmationFromRequest(
                    $request,
                    (string) $user->getId(),
                    $user->getEmail(),
                );
            } catch (VerifyEmailExceptionInterface) {
                return new JsonResponse(['message' => 'Invalid or expired link.'], Response::HTTP_BAD_REQUEST);
            }

            $user->setVerified(true);
            $em->flush();

            return new JsonResponse(['message' => 'Email verified.']);
        }
}