<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\ProfileService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    #[Route('/api/me/profile', name: 'api_profile_create', methods: ['POST'])]
    public function create(Request $request, ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->create($this->getUser(), $request->toArray()));
    }

    #[Route('/api/me/profile', name: 'api_profile_show', methods: ['GET'])]
    public function show(ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->show($this->getUser()));
    }

    #[Route('/api/me/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function update(Request $request, ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->update($this->getUser(), $request->toArray()));
    }

    #[Route('/api/me/profile', name: 'api_profile_delete', methods: ['DELETE'])]
    public function delete(ProfileService $profiles): JsonResponse
    {
        return $this->response($profiles->delete($this->getUser()));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}
