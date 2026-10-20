<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\TrustedDeviceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TrustedDeviceController extends AbstractController
{
    #[Route('api/trusted/device', name: 'app_trusted_device_register', methods: ['POST'])]
    public function register(Request $request, TrustedDeviceService $trusted): JsonResponse
    {
        return $this->response($trusted->register($this->getUser(), $request->toArray()));
    }

    #[Route('api/trusted/devices', name: 'app_trusted_device_list', methods: ['GET'])]
    public function list(TrustedDeviceService $trusted): JsonResponse
    {
        return $this->response($trusted->list($this->getUser()));
    }

    #[Route('api/trusted/device/{id}', name: 'app_trusted_device_revoke', methods: ['DELETE'])]
    public function revoke(int $id, TrustedDeviceService $trusted): JsonResponse
    {
        return $this->response($trusted->revoke($this->getUser(), $id));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}